<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Database operations used only by the regular-user portal. */
class User_portal_model extends CI_Model
{
    private $maximum_level = 10;

    public function __construct()
    {
        parent::__construct();
        $this->load->library('encryption');
    }
    /**
     * Authenticate an existing RMS account without changing its password.
     * Roles 1 and 2 remain administrator-only; stat 2 is a blocked account.
     */
    public function authenticate($username, $password)
    {
        /* Read the account and its organization labels in one query. */
        $query = $this->db
            ->select('users.user_id, users.username, users.password, users.emp_name, users.role_id, users.stat, departments.dept_name, subsidiaries.sub_name')
            ->from('users')
            ->join('departments', 'departments.dept_id = users.dept_id', 'left')
            ->join('subsidiaries', 'subsidiaries.sub_id = departments.sub_id', 'left')
            ->where('users.username', $username)
            ->limit(1)
            ->get();

        if ($query->num_rows() !== 1) {
            return $this->failure('The username or password is incorrect.');
        }

        $user = $query->row_array();

        /* Verify the password before revealing any account-specific state. */
        if (!$this->password_matches($password, (string) $user['password'])) {
            return $this->failure('The username or password is incorrect.');
        }

        /* Keep administrator and blocked accounts outside this portal. */
        if ((int) $user['role_id'] <= 2) {
            return $this->failure(
                'Administrator accounts must use the Admin Portal.',
                'administrator_portal'
            );
        }
        if ((int) $user['stat'] === 2) {
            return $this->failure('This account is blocked. Contact your system administrator.');
        }

        /* Mark a successful login online and update its last-visit value. */
        $this->db->trans_start();
        $this->db
            ->where('user_id', (int) $user['user_id'])
            ->update('users', array('stat' => 1, 'last_date_visit' => date('Y-m-d H:i:s')));
        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            return $this->failure('The account could not be opened. Please try again.');
        }

        return array('success' => TRUE, 'message' => '', 'user' => $user);
    }

    /** Count existing tagged access rows; no new table is required. */
    public function count_permissions($user_id)
    {
        return (int) $this->db
            ->where('user_id', (int) $user_id)
            ->count_all_results('user_allowed_data');
    }

    /** Count document rows that exactly match this user's tagged paths. */
    public function count_authorized_documents($user_id, $search)
    {
        $this->build_documents_query($user_id, $search, FALSE);
        /*
         * Count only the document primary key. The former count_all_results()
         * combined DISTINCT with every joined table and forced MySQL to build
         * a very large temporary result for the legacy data table.
         */
        $row = $this->db
            ->select('COUNT(DISTINCT data.data_id) AS document_count', FALSE)
            ->get()
            ->row_array();

        return isset($row['document_count'])
            ? (int) $row['document_count']
            : 0;
    }

    /** Return one permission-filtered page of original document metadata. */
    public function get_authorized_documents($user_id, $limit, $offset, $search)
    {
        $this->build_documents_query($user_id, $search, TRUE);
        /* The legacy data table stores the document upload date. */
        $rows = $this->db->order_by('data.date_uploaded', 'DESC')
            ->limit(max(1, (int) $limit), max(0, (int) $offset))
            ->get()->result_array();
        foreach ($rows as $index => $row) {
            $rows[$index]['token'] = $this->make_token($row);
            $rows[$index]['path_label'] = $this->path_label($row);
        }
        return $rows;
    }

    /**
     * Return only the immediate folders and files at one authorized location.
     *
     * The existing filename/subfolder hierarchy remains the source of truth.
     * No folder record is created and no permission row is changed.
     */
    public function get_authorized_browser($user_id, $folder_token, $search)
    {
        $search = trim((string) $search);

        /*
         * Search is intentionally global inside this user's authorized tree.
         * It does not inherit the currently opened folder, so root folders,
         * every subfolder level, and files can all be found in one request.
         */
        if ($search !== '') {
            return array(
                'folders' => $this->search_authorized_folders($user_id, $search),
                'documents' => $this->get_authorized_documents($user_id, 100, 0, $search),
                'breadcrumbs' => $this->build_breadcrumbs(array()),
                'parent_token' => ''
            );
        }

        $path = $this->decode_folder_token($folder_token);
        if ($path === FALSE) {
            return FALSE;
        }

        if (!empty($path) && !$this->authorized_branch_exists($user_id, $path)) {
            return FALSE;
        }

        return array(
            'folders' => $this->get_immediate_folders($user_id, $path, $search),
            'documents' => $this->get_immediate_documents($user_id, $path, $search),
            'breadcrumbs' => $this->build_breadcrumbs($path),
            'parent_token' => $this->make_parent_folder_token($path)
        );
    }

    /**
     * Search root folders and subfolder1 through subfolder10 without exposing
     * any branch that is not granted through user_allowed_data.
     */
    private function search_authorized_folders($user_id, $search)
    {
        $results = array();
        $seen = array();

        /* Search root filename folders. */
        $this->build_browser_base($user_id);
        $root_rows = $this->db
            ->select('filename.file_id AS folder_id, filename.filename AS folder_name, COUNT(DISTINCT data.data_id) AS file_count', FALSE)
            ->like('filename.filename', $search)
            ->group_by(array('filename.file_id', 'filename.filename'))
            ->order_by('filename.filename', 'ASC')
            ->get()->result_array();

        foreach ($root_rows as $row) {
            $path = array('file_id' => (int) $row['folder_id']);
            $token = $this->make_folder_token($path);
            $seen[$token] = TRUE;
            $row['token'] = $token;
            $row['path_label'] = $row['folder_name'];
            $results[] = $row;
        }

        /* Search every populated legacy subfolder level. */
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $this->build_browser_base($user_id);
            $fields = 'data.file_id, filename.filename';
            $groups = array('data.file_id', 'filename.filename');

            for ($join_level = 1; $join_level <= $level; $join_level++) {
                $table = 'subfolder' . $join_level;
                $id_column = $table . '_id';
                $name_column = $table . '_name';
                $this->db->join($table, $table . '.' . $id_column . ' = data.' . $id_column, 'inner');
                $fields .= ', data.' . $id_column . ', ' . $table . '.' . $name_column;
                $groups[] = 'data.' . $id_column;
                $groups[] = $table . '.' . $name_column;
            }

            $table = 'subfolder' . $level;
            $name_column = $table . '_name';
            $rows = $this->db
                ->select($fields . ', COUNT(DISTINCT data.data_id) AS file_count', FALSE)
                ->where('data.subfolder' . $level . '_id >', 0)
                ->like($table . '.' . $name_column, $search)
                ->group_by($groups)
                ->order_by($table . '.' . $name_column, 'ASC')
                ->get()->result_array();

            foreach ($rows as $row) {
                $path = array('file_id' => (int) $row['file_id']);
                $labels = array($row['filename']);
                for ($path_level = 1; $path_level <= $level; $path_level++) {
                    $path['sub' . $path_level] = (int) $row['subfolder' . $path_level . '_id'];
                    $labels[] = $row['subfolder' . $path_level . '_name'];
                }

                $token = $this->make_folder_token($path);
                if (isset($seen[$token])) {
                    continue;
                }

                $seen[$token] = TRUE;
                $results[] = array(
                    'folder_id' => (int) $row['subfolder' . $level . '_id'],
                    'folder_name' => $row['subfolder' . $level . '_name'],
                    'file_count' => (int) $row['file_count'],
                    'token' => $token,
                    'path_label' => implode(' / ', $labels)
                );
            }
        }

        return $results;
    }

    /** Read the next visible folder level without flattening its documents. */
    private function get_immediate_folders($user_id, $path, $search)
    {
        $this->build_browser_base($user_id);
        $this->apply_browser_path($path);

        if (empty($path)) {
            $this->db
                ->select('filename.file_id AS folder_id, filename.filename AS folder_name, COUNT(DISTINCT data.data_id) AS file_count', FALSE)
                ->group_by(array('filename.file_id', 'filename.filename'));
            if ($search !== '') {
                $this->db->like('filename.filename', $search);
            }
        } else {
            $next_level = $this->path_depth($path) + 1;
            if ($next_level > $this->maximum_level) {
                return array();
            }
            $table = 'subfolder' . $next_level;
            $id_column = $table . '_id';
            $name_column = $table . '_name';
            $this->db->join($table, $table . '.' . $id_column . ' = data.' . $id_column, 'inner')
                ->select('data.' . $id_column . ' AS folder_id, ' . $table . '.' . $name_column . ' AS folder_name, COUNT(DISTINCT data.data_id) AS file_count', FALSE)
                ->group_by(array('data.' . $id_column, $table . '.' . $name_column))
                ->where('data.' . $id_column . ' >', 0);
            if ($search !== '') {
                $this->db->like($table . '.' . $name_column, $search);
            }
        }

        $rows = $this->db->order_by('folder_name', 'ASC')->get()->result_array();
        foreach ($rows as $index => $row) {
            if (empty($path)) {
                $child_path = array('file_id' => (int) $row['folder_id']);
            } else {
                $child_path = $path;
                $next_level = $this->path_depth($path) + 1;
                $child_path['sub' . $next_level] = (int) $row['folder_id'];
            }
            $rows[$index]['token'] = $this->make_folder_token($child_path);
        }
        return $rows;
    }

    /** Return files stored directly in the open folder, not in descendants. */
    private function get_immediate_documents($user_id, $path, $search)
    {
        if (empty($path)) {
            return array();
        }

        $this->build_documents_query($user_id, '', TRUE);
        $this->apply_browser_path($path);
        $next_level = $this->path_depth($path) + 1;
        if ($next_level <= $this->maximum_level) {
            $this->db->where('data.subfolder' . $next_level . '_id', 0);
        }
        if ($search !== '') {
            $this->db->like('data.data_name', $search);
        }

        $rows = $this->db->order_by('data.date_uploaded', 'DESC')->get()->result_array();
        foreach ($rows as $index => $row) {
            $rows[$index]['token'] = $this->make_token($row);
            $rows[$index]['path_label'] = $this->path_label($row);
        }
        return $rows;
    }

    /** Build the light permission-filtered hierarchy query used by folders. */
    private function build_browser_base($user_id)
    {
        $join = 'user_allowed_data.file_id = data.file_id';
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $join .= ' AND (user_allowed_data.sub' . $level . '_id = 0 OR user_allowed_data.sub' . $level . '_id = data.subfolder' . $level . '_id)';
        }
        $this->db->from('data')
            ->join('user_allowed_data', $join, 'inner')
            ->join('filename', 'filename.file_id = data.file_id', 'inner')
            ->where('user_allowed_data.user_id', (int) $user_id)
            ->where('data.stat', 0);
    }

    /** Apply the filename and populated subfolder IDs from the open path. */
    private function apply_browser_path($path)
    {
        if (empty($path)) {
            return;
        }
        $this->db->where('data.file_id', (int) $path['file_id']);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            if (isset($path['sub' . $level])) {
                $this->db->where('data.subfolder' . $level . '_id', (int) $path['sub' . $level]);
            }
        }
    }

    /** Confirm the requested branch still contains at least one allowed file. */
    private function authorized_branch_exists($user_id, $path)
    {
        $this->build_browser_base($user_id);
        $this->apply_browser_path($path);

        /*
         * We only need to know whether one authorized document exists.
         * count_all_results() scans the complete legacy branch before its
         * LIMIT is applied and can exhaust the request on a large data table.
         */
        $row = $this->db
            ->select('data.data_id', FALSE)
            ->limit(1)
            ->get()
            ->row_array();

        return !empty($row);
    }

    /** Build trusted breadcrumb labels from the existing hierarchy tables. */
    private function build_breadcrumbs($path)
    {
        $crumbs = array(array('label' => 'My Documents', 'token' => ''));
        if (empty($path)) {
            return $crumbs;
        }

        $file = $this->db->select('filename')->where('file_id', (int) $path['file_id'])
            ->limit(1)->get('filename')->row_array();
        $running = array('file_id' => (int) $path['file_id']);
        $crumbs[] = array(
            'label' => isset($file['filename']) ? $file['filename'] : 'Folder',
            'token' => $this->make_folder_token($running)
        );
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            if (!isset($path['sub' . $level])) {
                break;
            }
            $table = 'subfolder' . $level;
            $row = $this->db->select($table . '_name AS folder_name', FALSE)
                ->where($table . '_id', (int) $path['sub' . $level])
                ->limit(1)->get($table)->row_array();
            $running['sub' . $level] = (int) $path['sub' . $level];
            $crumbs[] = array(
                'label' => isset($row['folder_name']) ? $row['folder_name'] : 'Folder',
                'token' => $this->make_folder_token($running)
            );
        }
        return $crumbs;
    }

    /** Encode a folder location using only existing hierarchy IDs. */
    private function make_folder_token($path)
    {
        $values = array((int) $path['file_id']);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $values[] = isset($path['sub' . $level]) ? (int) $path['sub' . $level] : 0;
        }

        /*
         * A numeric token is stable in Apache query strings and does not need
         * Base64 padding. The IDs are still treated as untrusted input and the
         * complete branch is rechecked against user_allowed_data below.
         */
        return implode('-', $values);
    }

    /** Decode a folder location and reject broken or skipped hierarchy levels. */
    private function decode_folder_token($token)
    {
        if ($token === '') {
            return array();
        }
        $token = trim((string) $token);

        /* New stable numeric format: file-sub1-sub2-...-sub10. */
        if (preg_match('/^[0-9]+(?:-[0-9]+){10}$/', $token)) {
            $values = explode('-', $token);
        } else {
            /*
             * Accept links created by the previous folder-browser package.
             * Restore omitted Base64 padding before strict decoding.
             */
            $base64 = strtr($token, '-_', '+/');
            $remainder = strlen($base64) % 4;
            if ($remainder > 0) {
                $base64 .= str_repeat('=', 4 - $remainder);
            }
            $raw = base64_decode($base64, TRUE);
            $values = $raw !== FALSE ? json_decode($raw, TRUE) : FALSE;
        }

        if (!is_array($values) || count($values) !== 11 || (int) $values[0] <= 0) {
            return FALSE;
        }
        $path = array('file_id' => (int) $values[0]);
        $zero_seen = FALSE;
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $value = (int) $values[$level];
            if ($value <= 0) {
                $zero_seen = TRUE;
                continue;
            }
            if ($zero_seen) {
                return FALSE;
            }
            $path['sub' . $level] = $value;
        }
        return $path;
    }

    /** Return the number of populated subfolder levels in a browser path. */
    private function path_depth($path)
    {
        $depth = 0;
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            if (isset($path['sub' . $level])) {
                $depth = $level;
            }
        }
        return $depth;
    }

    /** Create the Back target without trusting a label from the browser. */
    private function make_parent_folder_token($path)
    {
        if (empty($path) || $this->path_depth($path) === 0) {
            return '';
        }
        unset($path['sub' . $this->path_depth($path)]);
        return $this->make_folder_token($path);
    }

    /** Re-query one token through user_allowed_data before serving any bytes. */
    public function find_authorized_document($user_id, $token, $original)
    {
        $identity = $this->decode_token($token);
        if ($identity === FALSE) {
            return FALSE;
        }
        $this->build_documents_query($user_id, '', TRUE);
        $this->db->where('data.data_name', $identity['name'])
            ->where('data.page_no', $identity['page'])
            ->where('data.file_id', $identity['file']);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $this->db->where('data.subfolder' . $level . '_id', $identity['sub' . $level]);
        }
        $row = $this->db->limit(1)->get()->row_array();
        if (!$row) {
            return FALSE;
        }
        $row['served_name'] = $row['data_name'];
        if (!$original) {
            $viewer = $this->find_viewer_copy($row);
            if (!$viewer) {
                return FALSE;
            }
            $row['served_name'] = $viewer['data_name'];
        }
        return $row;
    }

    /** Build a safe absolute path from the existing System storage setting. */
    public function resolve_document_path($row, $original)
    {
        $setting = $this->db->select('value')->where('setting_id', $original ? 1 : 7)
            ->limit(1)->get('system_setting')->row_array();
        $fallback = $original ? FCPATH . 'administrator/agc_data/' : FCPATH . 'data/';
        $root = isset($setting['value']) && trim($setting['value']) !== '' ? trim($setting['value']) : $fallback;
        if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $root)) {
            /* Preserve legacy ../rms/... traversal so the portal uses the same
             * physical storage root as the Documents uploader. */
            $relative = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $root);
            $root = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $relative;
            $resolved = realpath($root);
            if ($resolved !== FALSE) {
                $root = $resolved;
            }
        }

        $parts = array($row['sub_name'], $row['dept_name'], $row['filename']);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            if (!empty($row['subfolder' . $level . '_name'])) {
                $parts[] = $row['subfolder' . $level . '_name'];
            }
        }
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..' || basename(str_replace('\\', '/', $part)) !== $part) {
                return FALSE;
            }
        }

        $directory = rtrim($root, '/\\');
        foreach ($parts as $part) {
            $raw = $directory . DIRECTORY_SEPARATOR . $part;
            $normalized = $directory . DIRECTORY_SEPARATOR . preg_replace('/\s+/', '_', trim((string) $part));
            if (is_dir($raw)) {
                $directory = $raw;
            } elseif (is_dir($normalized)) {
                $directory = $normalized;
            } else {
                return FALSE;
            }
        }

        return $this->resolve_portal_physical_file($directory, $row['served_name']);
    }

    /** Resolve legacy plaintext files and newer encrypted physical filenames. */
    private function resolve_portal_physical_file($directory, $friendly_name)
    {
        $directory = rtrim((string) $directory, '/\\');
        $friendly_name = basename(str_replace('\\', '/', (string) $friendly_name));
        if ($directory === '' || $friendly_name === '') {
            return FALSE;
        }

        $legacy = $directory . DIRECTORY_SEPARATOR . $friendly_name;
        if (is_file($legacy)) {
            return $legacy;
        }
        if (!is_dir($directory) || !is_readable($directory)) {
            return FALSE;
        }

        $entries = @scandir($directory);
        if (!is_array($entries)) {
            return FALSE;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== strtolower(pathinfo($friendly_name, PATHINFO_EXTENSION))) {
                continue;
            }
            $base = pathinfo($entry, PATHINFO_FILENAME);
            if (strpos($base, 'rmsenc_') !== 0) {
                continue;
            }
            $encoded = substr($base, strlen('rmsenc_'));
            $encoded = strtr($encoded, '-_', '+/');
            $padding = strlen($encoded) % 4;
            if ($padding !== 0) {
                $encoded .= str_repeat('=', 4 - $padding);
            }
            $decrypted = $this->encryption->decrypt($encoded);
            if ($decrypted === hash('sha256', $friendly_name, TRUE)) {
                $path = $directory . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path)) {
                    return $path;
                }
            }
        }
        return FALSE;
    }

    /** Assemble the shared list/count query with an exact hierarchy match. */
    private function build_documents_query($user_id, $search, $select)
    {
        $search = trim((string) $search);

        /* One file may match both a parent tag and a more specific child tag. */
        if ($select) {
            $this->db->distinct();
            $fields = 'data.data_name, data.page_no, data.date_uploaded, data.file_id, filename.filename, subsidiaries.sub_name, departments.dept_name';
            for ($level = 1; $level <= $this->maximum_level; $level++) {
                $fields .= ', data.subfolder' . $level . '_id, subfolder' . $level . '.subfolder' . $level . '_name';
            }
            $this->db->select($fields);
        }
        $join = 'user_allowed_data.file_id = data.file_id';
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            /*
             * Zero after the selected depth means the tagged folder grants
             * access to its descendants; populated parent IDs must still
             * match, which prevents access leaking into sibling branches.
             */
            $join .= ' AND (user_allowed_data.sub' . $level . '_id = 0 OR user_allowed_data.sub' . $level . '_id = data.subfolder' . $level . '_id)';
        }
        $this->db->from('data')->join('user_allowed_data', $join, 'inner')
            ->join('filename', 'filename.file_id = data.file_id', 'inner')
            ->join('departments', 'departments.dept_id = filename.dept_id', 'left')
            ->join('subsidiaries', 'subsidiaries.sub_id = filename.sub_id', 'left');
        /*
         * Folder labels are needed for list output and folder-name searches.
         * A plain page count does not need ten additional lookup-table joins.
         */
        if ($select || $search !== '') {
            for ($level = 1; $level <= $this->maximum_level; $level++) {
                $this->db->join('subfolder' . $level, 'subfolder' . $level . '.subfolder' . $level . '_id = data.subfolder' . $level . '_id', 'left');
            }
        }
        /* A valid tag remains authoritative even when Admin unpublishes its folder path. */
        $this->db->where('user_allowed_data.user_id', (int) $user_id)->where('data.stat', 0);
        if ($search !== '') {
            $this->db->group_start()->like('data.data_name', $search)->or_like('filename.filename', $search);
            for ($level = 1; $level <= $this->maximum_level; $level++) {
                $this->db->or_like('subfolder' . $level . '.subfolder' . $level . '_name', $search);
            }
            $this->db->group_end();
        }
    }

    /** Find the protected viewer copy for the same page and hierarchy. */
    private function find_viewer_copy($row)
    {
        $this->db->from('data_f')->where('file_id', (int) $row['file_id'])
            ->where('page_nof', (int) $row['page_no'])
            ->where('date_uploadedf', (string) $row['date_uploaded'])
            ->where('statf', 0);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $this->db->where('subfolder' . $level . '_idf', (int) $row['subfolder' . $level . '_id']);
        }
        $viewer = $this->db->limit(1)->get()->row_array();

        if (!$viewer) {
            return FALSE;
        }

        /* Normalize the protected-copy name for the shared serving code. */
        $viewer['data_name'] = $viewer['data_namef'];

        return $viewer;
    }

    /** Encode the composite legacy identity without adding a database field. */
    private function make_token($row)
    {
        $values = array($row['data_name'], (int) $row['page_no'], (int) $row['file_id']);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $values[] = (int) $row['subfolder' . $level . '_id'];
        }
        return rtrim(strtr(base64_encode(json_encode($values)), '+/', '-_'), '=');
    }

    /** Decode and strictly validate the legacy composite identity. */
    private function decode_token($token)
    {
        $raw = base64_decode(strtr((string) $token, '-_', '+/'), TRUE);
        $values = json_decode($raw, TRUE);
        if (!is_array($values) || count($values) !== 13 || !is_string($values[0])) {
            return FALSE;
        }
        $identity = array('name' => $values[0], 'page' => (int) $values[1], 'file' => (int) $values[2]);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $identity['sub' . $level] = (int) $values[$level + 2];
        }
        return $identity;
    }

    /** Format a readable hierarchy label for the user interface. */
    private function path_label($row)
    {
        $parts = array($row['filename']);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            if (!empty($row['subfolder' . $level . '_name'])) {
                $parts[] = $row['subfolder' . $level . '_name'];
            }
        }
        return implode(' / ', $parts);
    }

    /** Read the current legacy online/block/forced-logout state. */
    public function get_status($user_id)
    {
        $row = $this->db->select('stat')->where('user_id', (int) $user_id)->limit(1)->get('users')->row_array();
        return isset($row['stat']) ? (int) $row['stat'] : -1;
    }

    /**
     * Read the account state that may change while the portal session is open.
     *
     * The Administrator Users module stores Viewer Level 1 as role_id 4 and
     * Viewer Level 2 as role_id 3. Reading these existing fields allows the
     * portal to honor an access-level change without requiring another login.
     */
    public function get_portal_access($user_id)
    {
        $row = $this->db
            ->select('stat, role_id')
            ->where('user_id', (int) $user_id)
            ->limit(1)
            ->get('users')
            ->row_array();

        if (!isset($row['stat']) || !isset($row['role_id'])) {
            return FALSE;
        }

        return array(
            'status'  => (int) $row['stat'],
            'role_id' => (int) $row['role_id']
        );
    }

    /* Return the existing account and organization fields for one profile. */
    public function get_profile($user_id)
    {
        $row = $this->db
            ->select('users.user_id, users.username, users.emp_name, users.role_id, users.date_registered, users.stat, departments.dept_name, subsidiaries.sub_name')
            ->from('users')
            ->join('departments', 'departments.dept_id = users.dept_id', 'left')
            ->join('subsidiaries', 'subsidiaries.sub_id = departments.sub_id', 'left')
            ->where('users.user_id', (int) $user_id)
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($row)) {
            return FALSE;
        }

        $row['access_label'] = (int) $row['role_id'] === 3
            ? 'Viewer Level 2'
            : 'Viewer Level 1';
        $row['status_label'] = (int) $row['stat'] === 1
            ? 'Active account'
            : 'Account unavailable';

        return $row;
    }

    /* Prevent a profile from taking another existing account's username. */
    public function username_exists_for_other_user($username, $user_id)
    {
        return $this->db
            ->where('username', $username)
            ->where('user_id !=', (int) $user_id)
            ->count_all_results('users') > 0;
    }

    /* Update only the editable profile fields in the existing users table. */
    public function update_profile($user_id, $complete_name, $username, $new_password)
    {
        $values = array(
            'emp_name' => $complete_name,
            'username' => $username
        );

        /* Preserve the legacy 32-character format when a new password is set. */
        if ($new_password !== '') {
            $values['password'] = md5($new_password);
        }

        $this->db->trans_start();
        $this->db
            ->where('user_id', (int) $user_id)
            ->update('users', $values);
        $this->db->trans_complete();

        return $this->db->trans_status() !== FALSE;
    }

    /** Mark the signed-out account offline. */
    public function mark_offline($user_id)
    {
        return $this->db->where('user_id', (int) $user_id)->update('users', array('stat' => 0));
    }

    /** Support the same legacy hashes accepted by the Admin Portal. */
    private function password_matches($password, $stored_password)
    {
        /* Check the stored format without silently replacing old hashes. */
        if (preg_match('/^[a-f0-9]{32}$/i', $stored_password)) {
            return $this->constant_time_equals(strtolower($stored_password), md5($password));
        }
        if (preg_match('/^[a-f0-9]{40}$/i', $stored_password)) {
            return $this->constant_time_equals(strtolower($stored_password), sha1($password));
        }
        if (preg_match('/^\$(2[axy]|5|6)\$/', $stored_password)) {
            return $this->constant_time_equals($stored_password, crypt($password, $stored_password));
        }
        return $this->constant_time_equals($stored_password, $password);
    }

    /** PHP 5.4-safe constant-time string comparison. */
    private function constant_time_equals($known, $provided)
    {
        if (!is_string($known) || !is_string($provided)) {
            return FALSE;
        }
        /* Compare every byte to reduce timing differences on PHP 5.4. */
        $result = strlen($known) ^ strlen($provided);
        for ($index = 0, $length = strlen($known); $index < $length; $index++) {
            $result |= ord($known[$index]) ^ ($index < strlen($provided) ? ord($provided[$index]) : 0);
        }
        return $result === 0;
    }

    /** Build one consistent failed-authentication response. */
    private function failure($message, $reason = '')
    {
        return array(
            'success' => FALSE,
            'message' => $message,
            'reason' => $reason,
            'user' => array()
        );
    }
}
