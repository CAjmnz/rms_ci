<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Documents extends CI_Controller
{
    private $maximum_level = 10;

    /**
     * Prefix for the current account's unpublished working-path session mirror.
     *
     * IMPORTANT FOR FUTURE MAINTAINERS:
     * Never store every account's working paths under one shared session key.
     * Some RMS login flows reuse the browser session, so a shared key can make
     * User 2 inherit paths that User 1 unpublished. ownership_session_key()
     * adds a fingerprint made from the authenticated account identifiers.
     *
     * The persistent source is a Documents-only cache registry because this
     * conversion must not add or change database columns. The session copy is
     * retained only for compatibility and one-time migration.
     */
    private $unpublished_ownership_session_key_prefix =
    'rms_documents_unpublished_ownership';

    /**
     * Documents-only ownership registry. This is intentionally stored in the
     * existing writable CI cache directory because the project forbids DB
     * changes and Level 3 ownership must survive logout/login.
     */
    private $unpublished_ownership_registry =
    'rms_documents_unpublished_owners.php';

    /**
     * Stable Level 3 ownership storage used when application/cache is not
     * writable on the deployed server. CI's logs directory must already be
     * writable for the application, and unlike a browser session it survives
     * RJ logging out and back in. No database or other module is changed.
     */
    private $unpublished_ownership_fallback_registry =
    'rms_documents_unpublished_owners.php';

    public function __construct()
    {
        parent::__construct();

        $this->config->load('rms_auth');

        $this->load->helper(array(
            'url',
            'form',
            'security'
        ));

        $this->load->library(array(
            'session',
            'form_validation'
        ));

        /* Documents-only physical filename encryption. */
        $this->config->load('documents_encryption');

        $documents_encryption_key = (string) $this->config->item(
            'documents_filename_encryption_key'
        );

        if (
            $documents_encryption_key === '' ||
            $documents_encryption_key ===
            'PASTE_YOUR_GENERATED_64_CHARACTER_KEY_HERE'
        ) {
            show_error(
                'The Documents filename encryption key is not configured.',
                500
            );
        }

        $this->load->library(
            'encryption',
            array('key' => $documents_encryption_key)
        );

        $this->load->database();
        $this->load->model('Documents_model');
    }

    /**
     * Only logged-in Super Admin and Admin users may access
     * the administrator document-management module.
     */
    private function require_manager()
    {
        if ($this->session->userdata('rms_logged_in') !== TRUE) {
            redirect('administrator/');
            return FALSE;
        }

        $role = (int) $this->session->userdata('rms_role');

        if ($role !== 1 && $role !== 2) {
            show_error(
                'You are not allowed to manage documents.',
                403
            );

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Level 4 / Super Admin is stored as role_id 1 in the existing RMS DB.
     *
     * IMPORTANT FOR FUTURE MAINTAINERS:
     * Level 4 must not be limited by the per-account ownership list used
     * for Level 3. Super Admin works from the shared legacy publish columns so
     * globally unpublished paths remain available after logout/login and no
     * matter which manager originally unpublished them.
     */
    private function is_super_admin()
    {
        return (int) $this->session->userdata('rms_role') === 1;
    }

    /**
     * Return an empty ownership map for Filename through Subfolder10.
     */
    private function empty_unpublished_ownership()
    {
        $records = array();

        for ($level = 0; $level <= $this->maximum_level; $level++) {
            $records[$level] = array();
        }

        return $records;
    }

    /**
     * Build a stable fingerprint for the authenticated RMS account.
     *
     * Using all three existing login values prevents accidental ownership
     * sharing when one value is missing, stale, or reused by an older login
     * flow. No raw username or employee number is placed in the session key.
     */
    private function ownership_identity()
    {
        $user_id = trim((string) $this->session->userdata(
            'rms_user_id'
        ));

        $employee_id = trim((string) $this->session->userdata(
            'rms_employee_id'
        ));

        $username = strtolower(trim((string) $this->session->userdata(
            'rms_username'
        )));

        if ($user_id === '' && $employee_id === '' && $username === '') {
            return '';
        }

        /*
         * The database user ID is the stable account key. Employee/username
         * session values can be formatted differently between login flows;
         * including them in the primary fingerprint made the same RJ account
         * look like a new owner after logout/login.
         */
        if ($user_id !== '') {
            return sha1('rms-user-id|' . $user_id);
        }

        return sha1('rms-user-fallback|' . $employee_id . '|' . $username);
    }

    /**
     * Fingerprints previously used by this module. They are accepted only to
     * migrate existing Level 3 claims to the stable user-ID fingerprint.
     */
    private function legacy_ownership_identities()
    {
        $user_id = trim((string) $this->session->userdata('rms_user_id'));
        $employee_id = trim((string) $this->session->userdata('rms_employee_id'));
        $username = strtolower(trim((string) $this->session->userdata('rms_username')));
        $identities = array($this->ownership_identity());

        if ($user_id !== '' || $employee_id !== '' || $username !== '') {
            $identities[] = sha1($user_id . '|' . $employee_id . '|' . $username);
        }

        return array_values(array_unique(array_filter($identities)));
    }

    /**
     * Return the ownership session key for this account only.
     */
    private function ownership_session_key()
    {
        $identity = $this->ownership_identity();

        if ($identity === '') {
            return '';
        }

        return $this->unpublished_ownership_session_key_prefix .
            '_' . $identity;
    }

    /**
     * Return the absolute path of the Level 3 ownership registry.
     */
    private function ownership_registry_candidates()
    {
        $filename = $this->unpublished_ownership_fallback_registry;
        $original_root = $this->resolve_storage_root(
            $this->Documents_model->get_system_setting(1),
            FCPATH . 'administrator/agc_data/'
        );
        $viewer_root = $this->resolve_storage_root(
            $this->Documents_model->get_system_setting(7),
            FCPATH . 'data/'
        );

        return array_values(array_unique(array(
            APPPATH . 'cache/' . $this->unpublished_ownership_registry,
            APPPATH . 'logs/' . $filename,
            rtrim($original_root, '/\\') . DIRECTORY_SEPARATOR . '.' . $filename,
            rtrim($viewer_root, '/\\') . DIRECTORY_SEPARATOR . '.' . $filename
        )));
    }

    /**
     * Select the first writable registry location. Uploaded-document storage
     * is the final fallback because the same PHP account already writes files
     * there successfully, as shown by normal RMS uploads.
     */
    private function ownership_registry_path()
    {
        $candidates = $this->ownership_registry_candidates();

        foreach ($candidates as $path) {
            $directory = dirname($path);

            if (
                is_dir($directory) &&
                is_writable($directory) &&
                (!is_file($path) || is_writable($path))
            ) {
                return $path;
            }
        }

        /* Preserve the established failure path and clear error message. */
        return $candidates[0];
    }

    /**
     * Normalize a registry document read from disk.
     *
     * Registry shape: owners[level][record_id] = account fingerprint.
     * Only irreversible account fingerprints are stored; no usernames or
     * employee numbers are written to this file.
     */
    private function normalize_ownership_registry($registry)
    {
        $normalized = array(
            'version' => 1,
            'owners' => array()
        );

        for ($level = 0; $level <= $this->maximum_level; $level++) {
            $normalized['owners'][$level] = array();

            if (
                !is_array($registry) ||
                !isset($registry['owners'][$level]) ||
                !is_array($registry['owners'][$level])
            ) {
                continue;
            }

            foreach ($registry['owners'][$level] as $record_id => $owner) {
                $record_id = (int) $record_id;
                $owner = trim((string) $owner);

                if ($record_id > 0 && $owner !== '') {
                    $normalized['owners'][$level][$record_id] = $owner;
                }
            }
        }

        return $normalized;
    }

    /**
     * Read the ownership registry while holding a shared lock.
     */
    private function read_ownership_registry()
    {
        $path = $this->ownership_registry_path();

        if (!is_file($path)) {
            /*
             * A server may make application/cache read-only after claims were
             * already created there. Read that legacy file until the next
             * mutation safely migrates it to the writable fallback.
             */
            $found = FALSE;
            foreach ($this->ownership_registry_candidates() as $candidate) {
                if ($candidate !== $path && is_file($candidate)) {
                    $path = $candidate;
                    $found = TRUE;
                    break;
                }
            }

            if (!$found) {
                return $this->normalize_ownership_registry(array());
            }
        }

        $handle = @fopen($path, 'rb');

        if (!$handle) {
            return $this->normalize_ownership_registry(array());
        }

        $contents = '';

        if (@flock($handle, LOCK_SH)) {
            $contents = stream_get_contents($handle);
            @flock($handle, LOCK_UN);
        }

        fclose($handle);

        /* Accept both Linux LF and Windows CRLF registry headers. */
        $contents = preg_replace(
            '/\A<\?php exit; \?>\R?/',
            '',
            (string) $contents,
            1
        );

        $decoded = json_decode(trim($contents), TRUE);

        return $this->normalize_ownership_registry($decoded);
    }

    /**
     * Atomically mutate the ownership registry under an exclusive lock.
     */
    private function update_ownership_registry($callback)
    {
        $path = $this->ownership_registry_path();
        $handle = @fopen($path, 'c+');

        if (!$handle || !@flock($handle, LOCK_EX)) {
            if ($handle) {
                fclose($handle);
            }
            return FALSE;
        }

        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        $prefix = "<?php exit; ?>\n";

        $contents = preg_replace(
            '/\A<\?php exit; \?>\R?/',
            '',
            $contents,
            1
        );

        $registry = $this->normalize_ownership_registry(
            json_decode(trim($contents), TRUE)
        );

        /*
         * When cache is read-only, seed the writable fallback with existing
         * claims once. This preserves RJ's pre-fix ownership during rollout.
         */
        if (empty(array_filter($registry['owners']))) {
            foreach ($this->ownership_registry_candidates() as $legacy_path) {
                if ($legacy_path === $path || !is_file($legacy_path)) {
                    continue;
                }
                $legacy_contents = (string) @file_get_contents($legacy_path);
                $legacy_contents = preg_replace(
                    '/\A<\?php exit; \?>\R?/',
                    '',
                    $legacy_contents,
                    1
                );
                $legacy = $this->normalize_ownership_registry(
                    json_decode(trim($legacy_contents), TRUE)
                );
                if (!empty(array_filter($legacy['owners']))) {
                    $registry = $legacy;
                    break;
                }
            }
        }

        $registry = call_user_func($callback, $registry);
        $registry = $this->normalize_ownership_registry($registry);
        $payload = $prefix . json_encode($registry);

        rewind($handle);
        $written = fwrite($handle, $payload);

        if ($written !== FALSE && $written === strlen($payload)) {
            ftruncate($handle, strlen($payload));
            fflush($handle);
        }

        @flock($handle, LOCK_UN);
        fclose($handle);

        return $written !== FALSE && $written === strlen($payload);
    }

    /**
     * Read and normalize the unpublished records owned by this login.
     *
     * The full account fingerprint stored beside the map prevents a later
     * user in the same browser from inheriting the previous user's working
     * paths if the wider authentication code reuses a CodeIgniter session.
     */
    private function get_unpublished_ownership()
    {
        $records = $this->empty_unpublished_ownership();
        $identity = $this->ownership_identity();
        $session_key = $this->ownership_session_key();

        /*
         * Fail closed when the account cannot be identified. An unidentified
         * login must never inherit another account's unpublished paths.
         */
        if ($identity === '' || $session_key === '') {
            return $records;
        }

        /*
         * INTENTIONAL TEMPORARY ROLLBACK (Level 3/RJ): ownership is kept only
         * in this authenticated session. Do not read or write the filesystem
         * registry here. The account-wide logout/login persistence design was
         * postponed because the deployed server denies registry writes.
         *
         * Known limitation: logging out before publishing the path clears the
         * Level 3 working set. Level 4 can recover an orphaned unpublished path.
         * Keep this isolated from the unified UI/viewer code until a durable
         * storage approach is approved.
         */
        $state = $this->session->userdata($session_key);

        if (
            is_array($state) &&
            isset($state['identity']) &&
            (string) $state['identity'] === $identity &&
            isset($state['records']) &&
            is_array($state['records'])
        ) {
            for ($level = 0; $level <= $this->maximum_level; $level++) {
                if (!isset($state['records'][$level]) || !is_array($state['records'][$level])) {
                    continue;
                }
                foreach ($state['records'][$level] as $record_id) {
                    $record_id = (int) $record_id;
                    if ($record_id > 0) {
                        $records[$level][] = $record_id;
                    }
                }
                $records[$level] = array_values(array_unique($records[$level]));
            }
        }

        return $records;
    }

    /**
     * Save the current logged-in user's normalized ownership map.
     */
    private function save_unpublished_ownership($records)
    {
        $identity = $this->ownership_identity();
        $session_key = $this->ownership_session_key();

        if ($identity === '' || $session_key === '') {
            return FALSE;
        }

        $normalized = $this->empty_unpublished_ownership();

        for ($level = 0; $level <= $this->maximum_level; $level++) {
            if (
                !is_array($records) ||
                !isset($records[$level]) ||
                !is_array($records[$level])
            ) {
                continue;
            }

            foreach ($records[$level] as $record_id) {
                $record_id = (int) $record_id;

                if ($record_id > 0) {
                    $normalized[$level][] = $record_id;
                }
            }

            $normalized[$level] = array_values(
                array_unique($normalized[$level])
            );
        }

        $this->session->set_userdata(
            $session_key,
            array(
                'identity' => $identity,
                'records' => $normalized
            )
        );

        /* Session save is the complete operation during the temporary rollback. */
        return TRUE;
    }

    /**
     * Claim records after this user successfully unpublishes or creates them.
     */
    private function add_unpublished_ownership($level, $record_ids)
    {
        $level = $this->normalize_level($level);
        $records = $this->get_unpublished_ownership();

        if (!is_array($record_ids)) {
            $record_ids = array($record_ids);
        }

        foreach ($record_ids as $record_id) {
            $record_id = (int) $record_id;

            if ($record_id > 0) {
                $records[$level][] = $record_id;
            }
        }

        return $this->save_unpublished_ownership($records);
    }

    /**
     * Release records after this user publishes them again.
     */
    private function remove_unpublished_ownership($level, $record_ids)
    {
        $level = $this->normalize_level($level);
        $records = $this->get_unpublished_ownership();

        if (!is_array($record_ids)) {
            $record_ids = array($record_ids);
        }

        $remove_ids = array();

        foreach ($record_ids as $record_id) {
            $record_id = (int) $record_id;

            if ($record_id > 0) {
                $remove_ids[] = $record_id;
            }
        }

        $records[$level] = array_values(array_diff(
            $records[$level],
            $remove_ids
        ));

        return $this->save_unpublished_ownership($records);
    }

    /**
     * Verify that every selected record belongs to this user's working set.
     */
    private function owns_unpublished_records($level, $record_ids)
    {
        $level = $this->normalize_level($level);
        $records = $this->get_unpublished_ownership();

        if (!is_array($record_ids) || empty($record_ids)) {
            return FALSE;
        }

        foreach ($record_ids as $record_id) {
            if (!in_array((int) $record_id, $records[$level], TRUE)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Apply the correct unpublished-record rule for the logged-in manager.
     * Level 4 may manage every globally unpublished record; Level 3 keeps the
     * existing private ownership rule until its policy is reviewed separately.
     */
    private function can_manage_unpublished_records($level, $record_ids)
    {
        if ($this->is_super_admin()) {
            return $this->Documents_model->records_are_unpublished(
                $level,
                $record_ids
            );
        }

        return
            $this->owns_unpublished_records($level, $record_ids) &&
            $this->Documents_model->records_are_unpublished(
                $level,
                $record_ids
            );
    }

    /**
     * Display Manage Documents.
     *
     * Level 0  = Filename
     * Level 1  = Subfolder1
     * ...
     * Level 10 = Subfolder10
     */
    public function index()
    {
        if (!$this->require_manager()) {
            return;
        }

        if ((int) $this->input->get('datatable', TRUE) === 1) {
            return $this->data_table();
        }

        $level = $this->normalize_level(
            $this->input->get('level', TRUE)
        );

        $parent_id = (int) $this->input->get(
            'parent_id',
            TRUE
        );

        return $this->render_manage_documents($level, $parent_id);
    }

    /**
     * Opaque path-style entry for the hierarchy browser.
     * URL form: /administrator/documents/browse/{token}
     */
    public function browse($token = '')
    {
        if (!$this->require_manager()) {
            return;
        }

        $resolved = $this->decrypt_document_context_token(
            $token,
            'document-browse'
        );

        if ($resolved === FALSE) {
            show_error('The selected folder is invalid or has expired.', 404);
            return;
        }

        list($level, $parent_id) = $resolved;

        return $this->render_manage_documents($level, $parent_id);
    }

    /**
     * Shared loader for the Manage Documents hierarchy page.
     */
    private function render_manage_documents($level, $parent_id)
    {
        $level = $this->normalize_level($level);
        $parent_id = (int) $parent_id;

        $current_folder = FALSE;

        if ($level > 0) {
            if ($parent_id <= 0) {
                redirect('administrator/documents');
                return;
            }

            $current_folder = $this->Documents_model->find(
                $level - 1,
                $parent_id
            );

            if (!$current_folder) {
                show_404();
                return;
            }
        } else {
            $parent_id = 0;
        }

        $data = $this->common_view_data();
        $owned_records = $this->get_unpublished_ownership();
        $include_all_unpublished = $this->is_super_admin();

        $data['page_title'] = 'Manage Documents';
        $data['active_level'] = $level;
        $data['active_parent_id'] = $parent_id;
        $data['current_folder'] = $current_folder;
        $data['current_document_count'] = $current_folder
            ? $this->Documents_model->count_documents($level - 1, $parent_id)
            : 0;
        /*
 * CURRENT FOLDER ACTION:
 * Reuse the existing publish/unpublish ownership rules.
 * A published folder may be unpublished by a manager.
 * An unpublished folder may be published only by its owner
 * or by Super Admin.
 */
        $data['current_can_change_status'] = FALSE;

        if ($current_folder && $level > 0) {
            $current_record_level = $level - 1;
            $current_record_status = isset(
                $current_folder['publish_status']
            )
                ? (int) $current_folder['publish_status']
                : 1;

            /*
 * LEVEL 3 SHARED STATUS:
 * index() already passed require_manager(), so every existing Level 3
 * Admin and Level 4 Super Admin may change the current folder status.
 */
            $data['current_can_change_status'] = TRUE;
        }
        $data['total'] = $this->Documents_model->count_all(
            $level,
            '',
            $parent_id
        );
        $data['levels'] = $this->document_levels();

        /*
         * Data used by the three Manage Documents modals.
         */
        $data['upload_paths'] =
            $this->Documents_model->get_upload_paths(
                $owned_records,
                $include_all_unpublished
            );

        $data['subsidiaries'] =
            $this->Documents_model->get_subsidiaries();

        $data['departments'] =
            $this->Documents_model->get_departments();

        $data['subfolder_parent_paths'] =
            $this->Documents_model->get_subfolder_parent_paths(
                $owned_records,
                $include_all_unpublished
            );

        /* Pre-build encrypted breadcrumb URLs so the view never emits plain IDs. */
        $data['encrypted_browse_urls'] = $this->build_encrypted_breadcrumb_urls(
            $level,
            $parent_id,
            $current_folder
        );

        $this->load->view(
            'admin/documents/manage',
            $data
        );
    }

    /**
     * Build the set of encrypted hierarchy URLs used by breadcrumbs.
     * Keys are "level:parent_id" strings.
     */
    private function build_encrypted_breadcrumb_urls($active_level, $active_parent_id, $current_folder)
    {
        $urls = array();
        $urls['0:0'] = site_url('administrator/documents');

        if ($active_level <= 0 || empty($current_folder)) {
            return $urls;
        }

        $file_id = isset($current_folder['file_id'])
            ? (int) $current_folder['file_id']
            : 0;

        if ($file_id > 0) {
            $urls['1:' . $file_id] = $this->document_browse_url(1, $file_id);
        }

        for ($level = 1; $level < $active_level; $level++) {
            $id_field = 'subfolder' . $level . '_id';
            if (empty($current_folder[$id_field])) {
                continue;
            }
            $rid = (int) $current_folder[$id_field];
            $urls[($level + 1) . ':' . $rid] = $this->document_browse_url($level + 1, $rid);
        }

        return $urls;
    }
    /**
     * Display documents uploaded directly to Filename or Subfolder1..10.
     * The level/record_id pair is generated by Manage Documents. parent_id is
     * retained as a backward-compatible Subfolder10 fallback for old links.
     */
    public function view_documents()
    {
        if (!$this->require_manager()) {
            return;
        }

        if ((int) $this->input->get('datatable', TRUE) === 1) {
            return $this->document_data_table();
        }

        $level_input = $this->input->get('level', TRUE);
        $record_input = $this->input->get('record_id', TRUE);

        $level = $level_input === NULL
            ? $this->maximum_level
            : $this->normalize_level($level_input);

        $record_id = (int) ($record_input === NULL
            ? $this->input->get('parent_id', TRUE)
            : $record_input);

        return $this->render_view_documents($level, $record_id);
    }

    /**
     * Opaque path-style entry for the uploaded-documents page.
     * URL form: /administrator/documents/manage/{token}
     * Matches the encrypted manage-link style used in the Laravel counterpart.
     */
    public function manage($token = '')
    {
        if (!$this->require_manager()) {
            return;
        }

        /* DataTables still posts to the classic endpoint; this method is only
         * for the browser address-bar navigation. */
        $resolved = $this->decrypt_document_context_token($token);

        if ($resolved === FALSE) {
            show_error('The selected document folder is invalid or has expired.', 404);
            return;
        }

        list($level, $record_id) = $resolved;

        return $this->render_view_documents($level, $record_id);
    }

    /**
     * Shared loader for the View Documents page (query-string or encrypted path).
     */
    private function render_view_documents($level, $record_id)
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;

        if ($record_id <= 0) {
            redirect('administrator/documents');
            return;
        }

        $current_folder = $this->Documents_model->find(
            $level,
            $record_id
        );

        if (!$current_folder) {
            show_404();
            return;
        }

        $data = $this->common_view_data();

        $data['page_title'] = 'View Documents';
        $data['current_folder'] = $current_folder;
        $data['folder_level'] = $level;
        $data['record_id'] = $record_id;
        $data['parent_id'] = $record_id;
        $data['can_download_documents'] = TRUE;
        $data['can_manage_uploaded_files'] =
            $this->can_manage_unpublished_records($level, array($record_id));
        $data['transfer_paths'] = $data['can_manage_uploaded_files']
            ? $this->Documents_model->get_upload_paths(
                $this->get_unpublished_ownership(),
                $this->is_super_admin()
            )
            : array();
        $data['total'] = $this->Documents_model->count_documents(
            $level,
            $record_id
        );

        $this->load->view('admin/documents/view_documents', $data);
    }

    /**
     * Return one page of uploaded-document rows for DataTables
     * server-side mode. Same response shape as data_table().
     */
    private function document_data_table()
    {
        $level_input = $this->input->get('level', TRUE);
        $record_input = $this->input->get('record_id', TRUE);
        $level = $level_input === NULL
            ? $this->maximum_level
            : $this->normalize_level($level_input);
        $record_id = (int) ($record_input === NULL
            ? $this->input->get('parent_id', TRUE)
            : $record_input);

        if ($record_id <= 0 || !$this->Documents_model->find($level, $record_id)) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'draw' => (int) $this->input->get('draw', TRUE),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => array(),
                    'error' => 'The selected folder no longer exists.'
                )));
        }

        $draw = max(0, (int) $this->input->get('draw', TRUE));
        $start = max(0, (int) $this->input->get('start', TRUE));
        $length = (int) $this->input->get('length', TRUE);

        if (!in_array($length, array(10, 25, 50, 100), TRUE)) {
            $length = 10;
        }

        $search_request = $this->input->get('search', TRUE);
        $search = is_array($search_request) && isset($search_request['value'])
            ? trim((string) $search_request['value'])
            : '';

        $order_request = $this->input->get('order', TRUE);
        $order_column = 1;
        $order_direction = 'ASC';

        if (is_array($order_request) && isset($order_request[0])) {
            if (isset($order_request[0]['column'])) {
                $order_column = (int) $order_request[0]['column'];
            }
            if (isset($order_request[0]['dir']) && strtolower($order_request[0]['dir']) === 'desc') {
                $order_direction = 'DESC';
            }
        }

        $order_keys = array(
            1 => 'data_name',
            2 => 'page_no',
            3 => 'date_uploaded',
            4 => 'status'
        );

        $order_key = isset($order_keys[$order_column]) ? $order_keys[$order_column] : 'data_name';

        $records_total = $this->Documents_model->count_documents(
            $level,
            $record_id
        );
        $records_filtered = $search === ''
            ? $records_total
            : $this->Documents_model->count_documents(
                $level,
                $record_id,
                $search
            );

        $records = $this->Documents_model->get_documents(
            $level,
            $record_id,
            $length,
            $start,
            $search,
            $order_key,
            $order_direction
        );

        $rows = array();

        foreach ($records as $record) {
            $data_id = (int) $record['data_id'];
            $token = $this->encrypt_document_id_token($data_id, 'document');

            if ($token === FALSE) {
                continue;
            }

            /* WATERMARK-ONLY DISPLAY: resolve the protected row and filename. */
            $preview = $this->document_preview_info($data_id);

            $rows[] = array(
                /* Browser-facing opaque token; internal PK stays integer. */
                'data_id' => $token,
                /* WATERMARK TABLE NAME: show data_f.data_namef when a protected copy exists. */
                'data_name' => (string) $preview['display_name'],
                'has_watermark' => $preview['has_watermark'],
                'preview_type' => $preview['preview_type'],
                'preview_label' => $preview['preview_label'],
                'page_no' => (int) $record['page_no'],
                'date_uploaded' => (string) $record['date_uploaded'],
                'status' => (int) $record['stat'],
                /* WATERMARK-ONLY PREVIEW: never expose the original as a viewer source. */
                'view_url' => $preview['has_watermark']
                    ? site_url('administrator/documents/file') .
                    '?' . http_build_query(array('token' => $token))
                    : '',
                'download_url' => site_url('administrator/documents/file') .
                    '?' . http_build_query(array(
                        'token' => $token,
                        'download' => 1
                    ))
            );
        }

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'draw' => $draw,
                'recordsTotal' => $records_total,
                'recordsFiltered' => $records_filtered,
                'data' => $rows
            )));
    }

    /**
     * Stream one document after revalidating the active manager session.
     * Viewer requests prefer data_f; downloads always use the original data
     * file. No physical storage path is exposed to JavaScript.
     */
    public function file()
    {
        if (!$this->require_manager()) {
            return;
        }

        $token = $this->input->get('token', TRUE);
        $legacy_id = $this->input->get('data_id', TRUE);

        if ($token !== NULL && $token !== '') {
            $data_id = $this->decrypt_document_id_token($token, 'document');
        } else {
            /* Temporary compatibility for bookmarks that still use data_id=. */
            $resolved = $this->resolve_document_ids(array($legacy_id), 'document');
            $data_id = !empty($resolved) ? $resolved[0] : FALSE;
        }

        if ($data_id === FALSE || $data_id <= 0) {
            show_error('The selected document no longer exists.', 404);
            return;
        }

        $download = (int) $this->input->get('download', TRUE) === 1;
        $document = $this->Documents_model->get_document_file($data_id);

        if (!$document) {
            show_error('The selected document no longer exists.', 404);
            return;
        }

        /* WATERMARK-ONLY VIEW: inline viewing uses data_f; downloads use data. */
        $served_name = $document['data_name'];
        $path = FALSE;

        if (
            !$download &&
            !empty($document['viewer_name'])
        ) {
            $watermark_path = $this->document_storage_path(
                $document,
                $document['viewer_name'],
                FALSE
            );

            if (
                $watermark_path !== FALSE &&
                is_file($watermark_path) &&
                is_readable($watermark_path)
            ) {
                $served_name = $document['viewer_name'];
                $path = $watermark_path;
            }
        }

        /* WATERMARK-ONLY PREVIEW: the original is available only as a download. */
        if ($download) {
            $served_name = $document['data_name'];

            $path = $this->document_storage_path(
                $document,
                $document['data_name'],
                TRUE
            );
        }

        if (!$download && $path === FALSE) {
            show_error('A watermarked preview is not available for this document.', 404);
            return;
        }

        if (
            $path === FALSE ||
            !is_file($path) ||
            !is_readable($path)
        ) {
            show_error(
                'The document file could not be found in storage.',
                404
            );

            return;
        }

        $mime = function_exists('mime_content_type')
            ? mime_content_type($path)
            : 'application/octet-stream';

        $this->output
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_header('Content-Type: ' . $mime)
            ->set_header('Content-Length: ' . filesize($path))
            ->set_header(
                'Content-Disposition: ' .
                    ($download ? 'attachment' : 'inline') .
                    /*
 * WATERMARK-FIRST DISPLAY:
 * Never expose the internal watermark filename to the browser.
 */
                    '; filename="' .
                    str_replace(
                        '"',
                        '',
                        basename($document['data_name'])
                    ) .
                    '"'
            )
            ->set_output(file_get_contents($path));
    }

    /**
     * Delete selected files from one View Documents page.
     * Level 3 may delete only inside its own unpublished destination; Level 4
     * may delete inside any globally unpublished destination.
     */
    public function delete_uploaded_files()
    {
        if (!$this->require_manager()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $level = $this->normalize_level($this->input->post('level', TRUE));
        $record_id = (int) $this->input->post('record_id', TRUE);
        $data_ids = $this->input->post('data_ids', TRUE);

        if (
            $record_id <= 0 ||
            !is_array($data_ids) ||
            empty($data_ids) ||
            !$this->can_manage_unpublished_records(
                $level,
                array($record_id)
            )
        ) {
            return $this->json(
                FALSE,
                'Only files in an Unpublished destination allowed for your role can be deleted.'
            );
        }

        $clean_ids = $this->resolve_document_ids($data_ids, 'document');

        if (empty($clean_ids)) {
            return $this->json(FALSE, 'Please select at least one file.');
        }

        $documents = array();
        $staged = array();

        foreach ($clean_ids as $data_id) {
            $document = $this->Documents_model->get_document_file($data_id);

            if (
                !$document ||
                !$this->document_matches_record($document, $level, $record_id)
            ) {
                $this->rollback_staged_file_deletions($staged);
                return $this->json(FALSE, 'One or more selected files do not belong to this folder.');
            }

            $documents[] = $document;
            $candidates = array(
                $this->document_storage_path(
                    $document,
                    $document['data_name'],
                    TRUE
                )
            );

            if ($document['viewer_name'] !== '') {
                $candidates[] = $this->document_storage_path(
                    $document,
                    $document['viewer_name'],
                    FALSE
                );
            }

            foreach (array_unique($candidates) as $path) {
                if ($path === FALSE || !is_file($path)) {
                    continue;
                }

                $temporary = $path . '.rms-delete-' . $data_id . '-' . time();
                if (file_exists($temporary) || !@rename($path, $temporary)) {
                    $this->rollback_staged_file_deletions($staged);
                    return $this->json(FALSE, 'A selected physical file could not be prepared for deletion.');
                }

                $staged[] = array(
                    'original' => $path,
                    'temporary' => $temporary
                );
            }
        }

        if (!$this->Documents_model->delete_uploaded_documents($clean_ids)) {
            $this->rollback_staged_file_deletions($staged);
            return $this->json(FALSE, 'The selected file records could not be deleted.');
        }

        foreach ($staged as $file) {
            if (is_file($file['temporary']) && !@unlink($file['temporary'])) {
                log_message('error', 'Documents staged file requires cleanup: ' . $file['temporary']);
            }
        }

        return $this->json(
            TRUE,
            count($clean_ids) === 1
                ? 'The selected file was deleted successfully.'
                : count($clean_ids) . ' selected files were deleted successfully.'
        );
    }

    /** Rename one uploaded file while preserving its original file types. */
    public function rename_uploaded_file()
    {
        if (!$this->require_manager()) return;
        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $level = $this->normalize_level($this->input->post('level', TRUE));
        $record_id = (int) $this->input->post('record_id', TRUE);
        $resolved = $this->resolve_document_ids(
            array($this->input->post('data_id', TRUE)),
            'document'
        );
        $data_id = !empty($resolved) ? $resolved[0] : 0;
        $requested_input = (string) $this->input->post('name', TRUE);
        $requested = trim($requested_input);
        if (!$this->can_manage_unpublished_records($level, array($record_id))) {
            return $this->json(FALSE, 'This destination is not available for file changes.');
        }
        $document = $this->Documents_model->get_document_file($data_id);
        if (!$document || !$this->document_matches_record($document, $level, $record_id)) {
            return $this->json(FALSE, 'The selected file does not belong to this folder.');
        }

        $name_error = $this->windows_name_error($requested_input);
        if ($name_error !== '') {
            return $this->json(FALSE, $name_error);
        }
        $base = trim(pathinfo(basename(str_replace('\\', '/', $requested)), PATHINFO_FILENAME));
        $original_extension = pathinfo($document['data_name'], PATHINFO_EXTENSION);
        $viewer_extension = pathinfo($document['viewer_name'], PATHINFO_EXTENSION);
        $original_name = $base . ($original_extension !== '' ? '.' . $original_extension : '');
        $viewer_name = $document['viewer_name'] !== ''
            ? $base . ($viewer_extension !== '' ? '.' . $viewer_extension : '') : '';

        $moves = array();
        $names = array(array($document['data_name'], $original_name, TRUE));
        if ($document['viewer_name'] !== '') $names[] = array($document['viewer_name'], $viewer_name, FALSE);
        foreach ($names as $pair) {
            $old_path = $this->document_storage_path($document, $pair[0], $pair[2]);
            $existing_new_path = $this->document_storage_path(
                $document,
                $pair[1],
                $pair[2]
            );
            $new_path = $this->new_document_storage_path(
                $document,
                $pair[1],
                $pair[2]
            );
            if ($old_path === FALSE || $new_path === FALSE || !is_file($old_path)) {
                $this->rollback_file_moves($moves);
                return $this->json(FALSE, 'The physical file could not be found.');
            }
            if ($existing_new_path !== FALSE && $existing_new_path !== $old_path) {
                $this->rollback_file_moves($moves);
                return $this->json(FALSE, 'A file with that name already exists.');
            }
            if ($old_path !== $new_path && (file_exists($new_path) || !@rename($old_path, $new_path))) {
                $this->rollback_file_moves($moves);
                return $this->json(FALSE, 'The encrypted physical file could not be renamed.');
            }
            if ($old_path !== $new_path) $moves[] = array('old' => $old_path, 'new' => $new_path);
        }
        if (!$this->Documents_model->rename_uploaded_document($document, $original_name, $viewer_name)) {
            $this->rollback_file_moves($moves);
            return $this->json(FALSE, 'The file record could not be renamed.');
        }
        return $this->json(TRUE, 'The file was renamed successfully.');
    }

    /** Transfer selected files to another owned, unpublished leaf folder. */
    public function transfer_uploaded_files()
    {
        if (!$this->require_manager()) return;
        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $level = $this->normalize_level($this->input->post('level', TRUE));
        $record_id = (int) $this->input->post('record_id', TRUE);
        $target_level = $this->normalize_level($this->input->post('target_level', TRUE));
        $target_id = (int) $this->input->post('target_id', TRUE);
        $data_ids = $this->input->post('data_ids', TRUE);
        if (
            !is_array($data_ids) || empty($data_ids) ||
            !$this->can_manage_unpublished_records($level, array($record_id))
        ) {
            return $this->json(FALSE, 'Please select files from an allowed Unpublished folder.');
        }
        $target = $this->Documents_model->get_upload_path(
            $target_level,
            $target_id,
            $this->get_unpublished_ownership(),
            $this->is_super_admin(),
            TRUE
        );
        if (!$target || ($level === $target_level && $record_id === $target_id)) {
            return $this->json(FALSE, 'Select a different Unpublished folder with no existing subfolder.');
        }

        $documents = array();
        $moves = array();
        foreach ($this->resolve_document_ids($data_ids, 'document') as $data_id) {
            $document = $this->Documents_model->get_document_file($data_id);
            if (!$document || !$this->document_matches_record($document, $level, $record_id)) {
                $this->rollback_file_moves($moves);
                return $this->json(FALSE, 'One or more selected files do not belong to this folder.');
            }
            $destination = array_merge($document, $target);
            foreach (array(array($document['data_name'], TRUE), array($document['viewer_name'], FALSE)) as $file) {
                if ($file[0] === '') continue;
                $old_path = $this->document_storage_path($document, $file[0], $file[1]);
                $existing_target = $this->document_storage_path(
                    $destination,
                    $file[0],
                    $file[1]
                );
                $new_path = $this->new_document_storage_path(
                    $destination,
                    $file[0],
                    $file[1]
                );
                if (
                    $old_path === FALSE || $new_path === FALSE ||
                    $existing_target !== FALSE || !is_file($old_path) ||
                    !$this->ensure_directory(dirname($new_path)) || file_exists($new_path) ||
                    !@rename($old_path, $new_path)
                ) {
                    $this->rollback_file_moves($moves);
                    return $this->json(FALSE, 'A selected file could not be transferred safely.');
                }
                $moves[] = array('old' => $old_path, 'new' => $new_path);
            }
            $documents[] = $document;
        }
        if (!$this->Documents_model->transfer_uploaded_documents($documents, $this->build_upload_path_ids($target))) {
            $this->rollback_file_moves($moves);
            return $this->json(FALSE, 'The transferred file records could not be updated.');
        }
        return $this->json(TRUE, 'The selected files were transferred successfully.');
    }
    /** Return the authenticated user's global pin list for the topbar popup. */
    public function pinned_items()
    {
        if (!$this->require_manager()) {
            return;
        }

        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'GET') {
            return $this->json(FALSE, 'Invalid pinned-items request method.');
        }

        $user_id = (int) $this->session->userdata('rms_user_id');
        $filter = strtolower(trim((string) $this->input->get('type', TRUE)));
        $search = strtolower(trim((string) $this->input->get('search', TRUE)));
        $show_all = (int) $this->input->get('all', TRUE) === 1;

        if ($user_id <= 0) {
            return $this->json(FALSE, 'Your RMS account could not be identified. Please sign in again.');
        }

        if (!in_array($filter, array('all', 'filename', 'subfolder', 'document'), TRUE)) {
            $filter = 'all';
        }

        $items = array();
        $stored_pins = $this->Documents_model->get_user_pins($user_id);

        foreach ($stored_pins as $pin) {
            $item_type = (string) $pin['item_type'];
            $record_level = $this->normalize_level($pin['record_level']);
            $record_id = (int) $pin['record_id'];
            $extension = '';

            if ($item_type === 'folder') {
                $record = $this->Documents_model->find($record_level, $record_id);
                if (!$record) {
                    continue;
                }

                $segments = $this->record_path_segments($record, $record_level);
                $name = isset($record['record_name'])
                    ? (string) $record['record_name'] : '';
                $type_label = $record_level === 0
                    ? 'Filename' : 'Subfolder' . $record_level;
                $group_type = $record_level === 0 ? 'filename' : 'subfolder';
                $url = $record_level < $this->maximum_level
                    ? $this->document_browse_url($record_level + 1, $record_id)
                    : $this->document_manage_url($record_level, $record_id);
            } elseif ($item_type === 'document') {
                $record = $this->Documents_model->get_document_file($record_id);
                if (!$record) {
                    continue;
                }

                $segments = $this->record_path_segments($record, $record_level);
                $name = isset($record['data_name'])
                    ? (string) $record['data_name'] : '';
                $extension = strtoupper((string) pathinfo($name, PATHINFO_EXTENSION));
                $type_label = 'Document';
                $group_type = 'document';
                $token = $this->encrypt_document_id_token($record_id, 'document');
                if ($token === FALSE) {
                    continue;
                }
                $preview = $this->document_preview_info($record_id);
                $url = site_url('administrator/documents/file') . '?' .
                    http_build_query(array(
                        'token' => $token,
                        'download' => $preview['has_watermark'] ? 0 : 1
                    ));
            } else {
                continue;
            }

            if ($filter !== 'all' && $filter !== $group_type) {
                continue;
            }

            $path = !empty($segments)
                ? implode(' / ', $segments)
                : '/';

            $parent = count($segments) > 1
                ? (string) $segments[count($segments) - 2]
                : 'Root';

            if (
                $search !== '' &&
                strpos(strtolower($name . ' ' . $type_label . ' ' . $path), $search) === FALSE
            ) {
                continue;
            }

            $items[] = array(
                'item_type' => $item_type,
                'group_type' => $group_type,
                'record_level' => $record_level,
                'name' => $name,
                'type_label' => $type_label,
                'extension' => $extension,
                'parent' => $parent,
                'path' => $path,
                'url' => $url
            );

            if (!$show_all && count($items) >= 8) {
                break;
            }
        }

        return $this->json(TRUE, '', array(
            'count' => count($stored_pins),
            'items' => $items
        ));
    }

    /** Toggle one database-backed personal pin for the logged-in manager. */
    public function toggle_pin()
    {
        if (!$this->require_manager()) {
            return;
        }

        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') {
            return $this->json(FALSE, 'Invalid Pin request method.');
        }

        $user_id = (int) $this->session->userdata('rms_user_id');
        $item_type = strtolower(trim((string) $this->input->post('item_type', TRUE)));
        $level_input = $this->input->post('record_level', TRUE);
        $record_level = (int) $level_input;
        $parent_id = (int) $this->input->post('parent_id', TRUE);

        if ($user_id <= 0) {
            return $this->json(FALSE, 'Your RMS account could not be identified. Please sign in again.');
        }

        if (
            !in_array($item_type, array('folder', 'document'), TRUE) ||
            !is_numeric($level_input) ||
            $record_level < 0 ||
            $record_level > $this->maximum_level
        ) {
            return $this->json(FALSE, 'The selected pin is invalid.');
        }

        if ($item_type === 'document') {
            $token = (string) $this->input->post('record_token', FALSE);
            $record_id = $this->decrypt_document_id_token($token, 'document');
            $record = $record_id !== FALSE
                ? $this->Documents_model->get_document_file($record_id)
                : FALSE;

            if (
                !$record ||
                $parent_id <= 0 ||
                !$this->document_matches_record($record, $record_level, $parent_id)
            ) {
                return $this->json(FALSE, 'The selected document no longer exists in this folder.');
            }
        } else {
            $record_id = (int) $this->input->post('record_id', TRUE);
            $record = $record_id > 0
                ? $this->Documents_model->find($record_level, $record_id)
                : FALSE;

            if (!$record) {
                return $this->json(FALSE, 'The selected folder no longer exists.');
            }

            if ($record_level === 0) {
                $valid_parent = $parent_id === 0;
            } elseif ($record_level === 1) {
                $valid_parent = isset($record['file_id']) &&
                    (int) $record['file_id'] === $parent_id;
            } else {
                $parent_column = 'subfolder' . ($record_level - 1) . '_id';
                $valid_parent = isset($record[$parent_column]) &&
                    (int) $record[$parent_column] === $parent_id;
            }

            if (!$valid_parent) {
                return $this->json(FALSE, 'The selected folder does not belong to this location.');
            }
        }

        $result = $this->Documents_model->toggle_user_pin(
            $user_id,
            $item_type,
            $record_level,
            $record_id
        );

        if ($result === FALSE) {
            return $this->json(FALSE, 'The pin could not be saved. Confirm that the document_pins table is installed.');
        }

        return $this->json(
            TRUE,
            !empty($result['pinned'])
                ? 'The item was added to your pinned Documents.'
                : 'The item was removed from your pinned Documents.',
            array('pinned' => !empty($result['pinned']))
        );
    }

    /**
     * Return one parent-scoped page of Manage Documents rows in the
     * response format expected by jQuery DataTables server-side mode.
     */
    private function data_table()
    {
        $level = $this->normalize_level(
            $this->input->get('level', TRUE)
        );

        $parent_id = (int) $this->input->get(
            'parent_id',
            TRUE
        );

        if ($level === 0) {
            $parent_id = 0;
        } elseif (
            $parent_id <= 0 ||
            !$this->Documents_model->find($level - 1, $parent_id)
        ) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'draw' => (int) $this->input->get('draw', TRUE),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => array(),
                    'error' => 'The selected folder no longer exists.'
                )));
        }

        $draw = max(
            0,
            (int) $this->input->get('draw', TRUE)
        );

        $start = max(
            0,
            (int) $this->input->get('start', TRUE)
        );

        $length = (int) $this->input->get('length', TRUE);

        if (!in_array($length, array(10, 25, 50, 100), TRUE)) {
            $length = 10;
        }

        $search_request = $this->input->get('search', TRUE);

        $search = is_array($search_request) &&
            isset($search_request['value'])
            ? trim((string) $search_request['value'])
            : '';

        /* Pins are loaded from the database for the authenticated account. */
        $pin_user_id = (int) $this->session->userdata('rms_user_id');
        $pinned_map = $this->Documents_model->get_user_pin_map(
            $pin_user_id,
            $level,
            $level > 0 ? $level - 1 : 0
        );

        $order_request = $this->input->get('order', TRUE);
        $order_column = 1;
        $order_direction = 'ASC';

        if (is_array($order_request) && isset($order_request[0])) {
            if (isset($order_request[0]['column'])) {
                $order_column = (int) $order_request[0]['column'];
            }

            if (
                isset($order_request[0]['dir']) &&
                strtolower($order_request[0]['dir']) === 'desc'
            ) {
                $order_direction = 'DESC';
            }
        }

        /* Whitelist the actual Manage table columns; never trust a SQL name. */
        $order_keys = array(
            1 => 'name',
            2 => 'status',
            3 => 'date_modified',
            4 => 'owner'
        );

        $order_key = isset($order_keys[$order_column])
            ? $order_keys[$order_column]
            : 'name';

        $folder_total = $this->Documents_model->count_all($level, '', $parent_id);
        $folder_filtered = $search === ''
            ? $folder_total
            : $this->Documents_model->count_all($level, $search, $parent_id);

        /*
         * Unified Google-Drive-style listing:
         * when a directory is open (level > 0), return its child folders and
         * the files uploaded directly to that directory in the same response.
         * The root has no parent directory, so it continues to list filenames
         * only.  Fetching both matching sets before slicing keeps pagination,
         * search and sorting correct across the two record types.
         */
        $file_level = $level - 1;
        $file_total = $level > 0
            ? $this->Documents_model->count_documents($file_level, $parent_id)
            : 0;
        $file_filtered = $level > 0 && $search !== ''
            ? $this->Documents_model->count_documents($file_level, $parent_id, $search)
            : $file_total;

        $folders = $folder_filtered > 0
            ? $this->Documents_model->get_all(
                $level,
                $folder_filtered,
                0,
                $search,
                $parent_id,
                $order_key,
                $order_direction
            )
            : array();
        $documents = $file_filtered > 0
            ? $this->Documents_model->get_documents(
                $file_level,
                $parent_id,
                $file_filtered,
                0,
                $search,
                'data_name',
                $order_direction
            )
            : array();

        $records_total = $folder_total + $file_total;
        $records_filtered = $folder_filtered + $file_filtered;
        $records = array();

        foreach ($folders as $folder) {
            $folder['_item_type'] = 'folder';
            $records[] = $folder;
        }

        foreach ($documents as $document) {
            $document['_item_type'] = 'file';
            $records[] = $document;
        }

        /* Counts describe the complete filtered result, not this page slice. */
        $group_counts = array(
            'unpublished' => 0,
            'published' => 0,
            'files' => 0
        );
        foreach ($records as $record) {
            if ($record['_item_type'] === 'file') {
                $group_counts['files']++;
            } elseif ((int) $record['publish_status'] === 0) {
                $group_counts['unpublished']++;
            } else {
                $group_counts['published']++;
            }
        }

        $sort_fields = array(
            'name' => array('record_name', 'data_name'),
            'department' => array('dept_name', ''),
            'subsidiary' => array('sub_name', ''),
            'date_created' => array('date_created', 'date_uploaded'),
            'date_modified' => array('date_modified', 'date_uploaded'),
            'status' => array('publish_status', 'stat'),
            'owner' => array('created_by', 'created_by')
        );
        $sort_pair = isset($sort_fields[$order_key])
            ? $sort_fields[$order_key]
            : $sort_fields['name'];

        usort($records, function ($left, $right) use ($sort_pair, $order_key, $order_direction, $pinned_map) {
            $left_key = $left['_item_type'] === 'folder'
                ? 'f:' . (int) $left['record_id']
                : 'd:' . (int) $left['data_id'];
            $right_key = $right['_item_type'] === 'folder'
                ? 'f:' . (int) $right['record_id']
                : 'd:' . (int) $right['data_id'];
            $left_pinned = isset($pinned_map[$left_key]);
            $right_pinned = isset($pinned_map[$right_key]);

            /* Status is ranked globally before DataTables pagination. */
            if ($order_key === 'status') {
                $left_rank = $left['_item_type'] === 'file'
                    ? 3
                    : ((int) $left['publish_status'] === 0 ? 1 : 2);
                $right_rank = $right['_item_type'] === 'file'
                    ? 3
                    : ((int) $right['publish_status'] === 0 ? 1 : 2);

                if ($left_rank !== $right_rank) {
                    $rank_comparison = $left_rank < $right_rank ? -1 : 1;
                    return $order_direction === 'DESC'
                        ? -$rank_comparison
                        : $rank_comparison;
                }
            }

            if ($left_pinned !== $right_pinned) {
                return $left_pinned ? -1 : 1;
            }

            /* Non-status sorting preserves the existing Drive-style order. */
            if ($order_key !== 'status' && $left['_item_type'] !== $right['_item_type']) {
                return $left['_item_type'] === 'folder' ? -1 : 1;
            }
            $field = $left['_item_type'] === 'folder' ? $sort_pair[0] : $sort_pair[1];
            $left_value = $field !== '' && isset($left[$field])
                ? $left[$field]
                : ($order_key === 'owner' ? 'Admin' : '');
            $right_value = $field !== '' && isset($right[$field])
                ? $right[$field]
                : ($order_key === 'owner' ? 'Admin' : '');
            $comparison = strnatcasecmp((string) $left_value, (string) $right_value);
            if ($comparison !== 0) {
                return $order_direction === 'DESC' ? -$comparison : $comparison;
            }

            /* Stable tie-breaker prevents rows jumping between pages. */
            $left_id = $left['_item_type'] === 'folder'
                ? (int) $left['record_id']
                : (int) $left['data_id'];
            $right_id = $right['_item_type'] === 'folder'
                ? (int) $right['record_id']
                : (int) $right['data_id'];
            return $left_id === $right_id ? 0 : ($left_id < $right_id ? -1 : 1);
        });

        $records = array_slice($records, $start, $length);

        $rows = array();
        $owned_records = $this->get_unpublished_ownership();
        $super_admin = $this->is_super_admin();

        foreach ($records as $record) {
            if (
                isset($record['_item_type']) &&
                $record['_item_type'] === 'file'
            ) {
                $data_id = (int) $record['data_id'];
                $token = $this->encrypt_document_id_token($data_id, 'document');

                if ($token === FALSE) {
                    continue;
                }

                /* WATERMARK-ONLY DISPLAY: use the matched data_f filename. */
                $preview = $this->document_preview_info($data_id);

                $rows[] = array(
                    'item_type' => 'file',
                    'is_pinned' => isset($pinned_map['d:' . $data_id]) ? 1 : 0,
                    'record_id' => 0,
                    /* Browser-facing opaque token; internal PK stays integer. */
                    'data_id' => $token,
                    /* WATERMARK TABLE NAME: the visible/openable row is the protected copy. */
                    'record_name' => (string) $preview['display_name'],
                    'has_watermark' => $preview['has_watermark'],
                    'preview_type' => $preview['preview_type'],
                    'preview_label' => $preview['preview_label'],
                    'department' => '',
                    'subsidiary' => '',
                    'date_created' => isset($record['date_uploaded']) ? (string) $record['date_uploaded'] : '',
                    'date_modified' => isset($record['date_uploaded']) ? (string) $record['date_uploaded'] : '',
                    'publish_status' => 1,
                    'owned_by_current_user' => 0,
                    'status_locked' => 1,
                    'child_count' => 0,
                    'document_count' => 0,
                    'page_no' => isset($record['page_no']) ? (int) $record['page_no'] : 0,
                    'file_status' => isset($record['stat']) ? (int) $record['stat'] : 0,
                    'stat' => isset($record['stat']) ? (int) $record['stat'] : 0,
                    'r_stat' => 0,
                    'created_by' => 'Admin',
                    'reviewed_by' => 'Admin',
                    /* WATERMARK-ONLY PREVIEW: files without a watermark cannot open the original inline. */
                    'view_url' => $preview['has_watermark']
                        ? site_url('administrator/documents/file') . '?' .
                        http_build_query(array('token' => $token))
                        : '',
                    'download_url' => site_url('administrator/documents/file') . '?' .
                        http_build_query(array('token' => $token, 'download' => 1)),
                    'manage_url' => $this->document_manage_url($file_level, $parent_id),
                    'next_url' => ''
                );
                continue;
            }

            $record_id = isset($record['record_id'])
                ? (int) $record['record_id']
                : 0;

            $child_count = isset($record['child_count'])
                ? (int) $record['child_count']
                : 0;

            $next_url = '';
            $view_url = '';
            $document_count = $record_id > 0
                ? $this->Documents_model->count_documents(
                    $level,
                    $record_id
                )
                : 0;

            if ($record_id > 0 && $document_count > 0) {
                $view_url = $this->document_manage_url($level, $record_id);
            }

            if (
                $record_id > 0 &&
                $level < $this->maximum_level
            ) {
                $next_url = $this->document_browse_url($level + 1, $record_id);
            } elseif (
                $record_id > 0 &&
                $level === $this->maximum_level
            ) {
                $next_url = $this->document_manage_url($level, $record_id);
            }

            $global_publish_status = isset($record['publish_status'])
                ? (int) $record['publish_status']
                : 1;

            $owned_by_current_user = $super_admin
                ? $global_publish_status === 0
                : in_array(
                    $record_id,
                    $owned_records[$level],
                    TRUE
                );

            /*
             * Always display the real shared RMS status. For Level 3, status
             * and ownership are deliberately separate: RJ can see that STY's
             * record is Unpublished, but owned_by_current_user remains 0 so
             * Publish/Edit/Delete stay locked. Level 4 owns every unpublished
             * row for action purposes and therefore remains unrestricted.
             */
            $display_publish_status = $global_publish_status;

            $rows[] = array(
                'item_type' => 'folder',
                'is_pinned' => isset($pinned_map['f:' . $record_id]) ? 1 : 0,
                'record_id' => $record_id,
                'record_name' => isset($record['record_name'])
                    ? (string) $record['record_name']
                    : '',
                'department' => isset($record['dept_name'])
                    ? (string) $record['dept_name']
                    : '',
                'subsidiary' => isset($record['sub_name'])
                    ? (string) $record['sub_name']
                    : '',
                'date_created' => isset($record['date_created'])
                    ? (string) $record['date_created']
                    : '',
                'date_modified' => isset($record['date_modified'])
                    ? (string) $record['date_modified']
                    : '',
                'publish_status' => $display_publish_status,
                'created_by' => 'Admin',
                /*
                 * The browser enables Publish for owned records and
                 * Unpublish for records not yet owned by this user.
                 */
                'owned_by_current_user' =>
                $owned_by_current_user ? 1 : 0,
                'status_locked' => 0,
                'child_count' => $child_count,
                'document_count' => $document_count,
                'view_url' => $view_url,
                'next_url' => $next_url
            );
        }

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'draw' => $draw,
                'recordsTotal' => $records_total,
                'recordsFiltered' => $records_filtered,
                'groupCounts' => $group_counts,
                'folderPins' =>
                $this->Documents_model->get_user_folder_pins($pin_user_id),
                'data' => $rows
            )));
    }

    /**
     * Display Add New Documents.
     *
     * Only unpublished filename/subfolder paths are returned
     * by the model. This follows the existing RMS process:
     *
     * 1. Open Manage Documents.
     * 2. Unpublish the destination hierarchy.
     * 3. Open Add New Documents.
     * 4. Select the unpublished path.
     * 5. Upload the files.
     * 6. Publish the hierarchy again.
     */
    public function create()
    {
        if (!$this->require_manager()) {
            return;
        }

        $data = $this->common_view_data();

        $data['page_title'] = 'New Documents';

        $data['upload_paths'] =
            $this->Documents_model->get_upload_paths(
                $this->get_unpublished_ownership(),
                $this->is_super_admin()
            );

        $this->load->view(
            'admin/documents/create',
            $data
        );
    }

    /**
     * Return one filename or subfolder record.
     *
     * This endpoint will be used when a Manage Documents
     * row is selected.
     */
    public function record()
    {
        if (!$this->require_manager()) {
            return;
        }

        $level = $this->normalize_level(
            $this->input->get('level', TRUE)
        );

        $record_id = (int) $this->input->get(
            'record_id',
            TRUE
        );

        if ($record_id <= 0) {
            return $this->json(
                FALSE,
                'Please select a valid record.'
            );
        }

        $record = $this->Documents_model->find(
            $level,
            $record_id
        );

        if (!$record) {
            return $this->json(
                FALSE,
                'The selected record no longer exists.'
            );
        }

        return $this->json(
            TRUE,
            '',
            array(
                'record' => $record
            )
        );
    }

    /**
     * Update one unpublished Filename/Subfolder from the Manage Documents
     * modal and keep both legacy storage trees in sync.
     */
    public function update_record()
    {
        if (!$this->require_manager()) {
            return;
        }

        $request_method = strtoupper(
            (string) $this->input->server('REQUEST_METHOD')
        );

        if ($request_method !== 'POST') {
            return $this->json(
                FALSE,
                'Invalid Edit request method.'
            );
        }

        $level = $this->normalize_level($this->input->post('level', TRUE));
        $record_id = (int) $this->input->post('record_id', TRUE);
        $name_input = (string) $this->input->post('record_name', TRUE);
        $name = trim($name_input);
        $sub_id = (int) $this->input->post('sub_id', TRUE);
        $dept_id = (int) $this->input->post('dept_id', TRUE);

        if ($record_id <= 0 || $name === '') {
            return $this->json(FALSE, 'Please complete the required fields.');
        }

        $name_error = $this->windows_name_error($name_input);
        if ($name_error !== '') {
            return $this->json(FALSE, $name_error);
        }

        if (!$this->valid_directory_name($name)) {
            return $this->json(FALSE, 'The name contains characters that cannot be used in an RMS directory.');
        }

        if (!$this->can_manage_unpublished_records($level, array($record_id))) {
            return $this->json(FALSE, 'Publish status must be Unpublished before editing.');
        }

        $record = $this->Documents_model->find($level, $record_id);

        if (!$record) {
            return $this->json(FALSE, 'The selected record no longer exists.');
        }

        $parent_id = 0;
        if ($level === 1) {
            $parent_id = isset($record['file_id']) ? (int) $record['file_id'] : 0;
        } elseif ($level > 1) {
            $parent_key = 'subfolder' . ($level - 1) . '_id';
            $parent_id = isset($record[$parent_key]) ? (int) $record[$parent_key] : 0;
        }

        $department = FALSE;
        if ($level === 0) {
            $department = $this->Documents_model->get_department_with_subsidiary($dept_id, $sub_id);
            if (!$department) {
                return $this->json(FALSE, 'Please select a valid subsidiary and department.');
            }
        } else {
            $sub_id = isset($record['sub_id']) ? (int) $record['sub_id'] : 0;
            $dept_id = isset($record['dept_id']) ? (int) $record['dept_id'] : 0;
        }

        if ($this->Documents_model->record_name_exists(
            $level,
            $record_id,
            $name,
            $sub_id,
            $dept_id,
            $parent_id
        )) {
            return $this->json(FALSE, 'A record with the same name already exists in this location.');
        }

        $old_segments = $this->record_path_segments($record, $level);
        if (empty($old_segments)) {
            return $this->json(FALSE, 'The existing RMS directory path is invalid.');
        }
        $new_segments = $old_segments;

        if ($level === 0) {
            $new_segments[0] = (string) $department['sub_name'];
            $new_segments[1] = (string) $department['dept_name'];
            $new_segments[2] = $name;
        } else {
            $new_segments[count($new_segments) - 1] = $name;
        }

        $roots = $this->documents_storage_roots();
        $renamed = array();

        foreach ($roots as $root) {
            $legacy_old_path = $this->join_storage_path($root, $old_segments);
            $server_old_segments = array_map(array($this, 'server_storage_name'), $old_segments);
            $server_old_path = $this->join_storage_path($root, $server_old_segments);
            $old_path = is_dir($server_old_path) ? $server_old_path : $legacy_old_path;

            $server_new_segments = array_map(array($this, 'server_storage_name'), $new_segments);
            $new_path = $this->join_storage_path($root, $server_new_segments);

            if ($old_path === $new_path || !is_dir($old_path)) {
                continue;
            }

            if (file_exists($new_path)) {
                $this->rollback_directory_renames($renamed);
                return $this->json(FALSE, 'The new directory name already exists in RMS storage.');
            }

            if (!$this->ensure_directory(dirname($new_path)) || !@rename($old_path, $new_path)) {
                $this->rollback_directory_renames($renamed);
                return $this->json(FALSE, 'The RMS directory could not be renamed. Check ownership and write permissions for both storage roots.');
            }

            $renamed[] = array('old' => $old_path, 'new' => $new_path);
        }

        if (!$this->Documents_model->update_record($level, $record_id, $name, $sub_id, $dept_id)) {
            $this->rollback_directory_renames($renamed);
            return $this->json(FALSE, 'The record could not be saved. No directory changes were kept.');
        }

        return $this->json(TRUE, ($level === 0 ? 'Filename' : 'Subfolder') . ' updated successfully.');
    }

    /**
     * Delete one unpublished folder and its matching legacy metadata.
     */
    public function delete_record()
    {
        if (!$this->require_manager()) {
            return;
        }

        $request_method = strtoupper(
            (string) $this->input->server('REQUEST_METHOD')
        );

        if ($request_method !== 'POST') {
            return $this->json(
                FALSE,
                'Invalid Delete request method.'
            );
        }

        $level = $this->normalize_level(
            $this->input->post('level', TRUE)
        );

        $record_id = (int) $this->input->post(
            'record_id',
            TRUE
        );


        if ($record_id <= 0 || !$this->can_manage_unpublished_records($level, array($record_id))) {
            return $this->json(FALSE, 'Only an Unpublished record allowed for your role can be deleted.');
        }

        $record = $this->Documents_model->find($level, $record_id);
        if (!$record) {
            return $this->json(FALSE, 'The selected record no longer exists.');
        }

        $segments = $this->record_path_segments($record, $level);
        if (empty($segments)) {
            return $this->json(FALSE, 'The existing RMS directory path is invalid.');
        }
        if (isset($record['child_count']) && (int) $record['child_count'] > 0) {
            return $this->json(FALSE, 'Delete the folders inside this record first. This protects the RMS hierarchy from orphaned subfolders.');
        }

        $staged = array();
        foreach ($this->documents_storage_roots() as $root) {
            $legacy_path = $this->join_storage_path($root, $segments);
            $server_segments = array_map(array($this, 'server_storage_name'), $segments);
            $server_path = $this->join_storage_path($root, $server_segments);
            $path = is_dir($server_path) ? $server_path : $legacy_path;

            if (!is_dir($path)) {
                continue;
            }

            $temporary = $path . '.rms-delete-' . $record_id . '-' . time();
            if (file_exists($temporary) || !@rename($path, $temporary)) {
                $this->rollback_staged_deletions($staged);
                return $this->json(FALSE, 'The RMS directory could not be prepared for deletion. Check storage permissions.');
            }

            $staged[] = array('original' => $path, 'temporary' => $temporary);
        }

        if (!$this->Documents_model->delete_record($level, $record_id)) {
            $this->rollback_staged_deletions($staged);
            return $this->json(FALSE, 'The database record could not be deleted. The physical directories were restored.');
        }

        $this->remove_unpublished_ownership($level, array($record_id));

        foreach ($staged as $item) {
            if (!$this->delete_directory_tree($item['temporary'])) {
                log_message('error', 'Documents staged directory requires manual cleanup: ' . $item['temporary']);
            }
        }

        return $this->json(TRUE, 'The selected record was deleted successfully.');
    }

    /**
     * Publish or unpublish selected filename/subfolder records.
     */
    public function publish()
    {
        if (!$this->require_manager()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $level = $this->normalize_level(
            $this->input->post('level', TRUE)
        );

        $publish = (int) $this->input->post(
            'publish',
            TRUE
        );

        $record_ids = $this->input->post(
            'record_ids',
            TRUE
        );

        if ($publish !== 0 && $publish !== 1) {
            return $this->json(
                FALSE,
                'The requested document status is invalid.'
            );
        }

        if (!is_array($record_ids) || empty($record_ids)) {
            return $this->json(
                FALSE,
                'Please select at least one record.'
            );
        }

        $clean_ids = array();

        foreach ($record_ids as $record_id) {
            $record_id = (int) $record_id;

            if ($record_id > 0) {
                $clean_ids[] = $record_id;
            }
        }

        $clean_ids = array_values(
            array_unique($clean_ids)
        );

        if (empty($clean_ids)) {
            return $this->json(
                FALSE,
                'Please select at least one valid record.'
            );
        }

        /*
         * Keep the existing RMS publish column authoritative and persistent.
         * This is essential for Level 4: after logout/login, Super Admin must
         * still see paths unpublished by any manager. Level 3 continues to
         * use its persistent per-account ownership map for restrictions.
         */
        foreach ($clean_ids as $record_id) {
            if (!$this->Documents_model->find($level, $record_id)) {
                return $this->json(
                    FALSE,
                    'One or more selected records no longer exist.'
                );
            }
        }

        /*
 * LEVEL 3 SHARED STATUS:
 * Every authorized Level 3 Admin and Level 4 Super Admin may publish
 * any globally Unpublished filename or subfolder.
 *
 * Do not use can_manage_unpublished_records() here because that
 * ownership function must remain active for Upload, Add Subfolder,
 * Edit, Delete, Rename and Transfer.
 */
        if (
            $publish === 1 &&
            !$this->Documents_model->records_are_unpublished(
                $level,
                $clean_ids
            )
        ) {
            return $this->json(
                FALSE,
                'One or more selected records are already Published.'
            );
        }

        /*
         * A record already unpublished by any user cannot be claimed again.
         * This closes the direct-request bypass of the disabled Unpublish UI.
         */
        if (
            $publish === 0 &&
            !$this->Documents_model->records_are_published($level, $clean_ids)
        ) {
            return $this->json(
                FALSE,
                'One or more selected records are already Unpublished.'
            );
        }

        $updated = $this->Documents_model->set_publish_many(
            $level,
            $clean_ids,
            $publish
        );

        if (!$updated) {
            return $this->json(
                FALSE,
                'The selected records could not be updated.'
            );
        }

        if ($publish === 1) {

            $this->remove_unpublished_ownership(
                $level,
                $clean_ids
            );
        } else {
            /* Session ownership is intentionally sufficient until persistence is revisited. */
            $this->add_unpublished_ownership($level, $clean_ids);
        }

        /*
 * LEVEL 3 SHARED STATUS:
 * Confirm that Publish/Unpublish is shared across Level 3 Admins.
 */
        return $this->json(
            TRUE,
            $publish === 1
                ? 'The selected records were published successfully.'
                : 'The selected records were unpublished successfully. Any Level 3 Admin can publish them.'
        );
    }

    /**
     * Create a new main filename from the Manage Documents modal.
     */
    public function create_filename()
    {
        if (!$this->require_manager()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $sub_id = (int) $this->input->post('sub_id', TRUE);
        $dept_id = (int) $this->input->post('dept_id', TRUE);
        $filename_input = (string) $this->input->post('filename', TRUE);
        $filename = trim($filename_input);

        if ($sub_id <= 0) {
            return $this->json(
                FALSE,
                'Please select a subsidiary.'
            );
        }

        if ($dept_id <= 0) {
            return $this->json(
                FALSE,
                'Please select a department.'
            );
        }

        if ($filename === '') {
            return $this->json(
                FALSE,
                'Please enter the filename.'
            );
        }

        if (strlen($filename) > 250) {
            return $this->json(
                FALSE,
                'The filename must not exceed 250 characters.'
            );
        }

        $name_error = $this->windows_name_error($filename_input);
        if ($name_error !== '') {
            return $this->json(FALSE, $name_error);
        }
        /*
     * Confirm that the selected department belongs to
     * the selected subsidiary.
     */
        $departments =
            $this->Documents_model->get_departments($sub_id);

        $valid_department = FALSE;

        foreach ($departments as $department) {
            if ((int) $department['dept_id'] === $dept_id) {
                $valid_department = TRUE;
                break;
            }
        }

        if (!$valid_department) {
            return $this->json(
                FALSE,
                'The selected department does not belong to that subsidiary.'
            );
        }

        if (
            $this->Documents_model->filename_exists(
                $sub_id,
                $dept_id,
                $filename
            )
        ) {
            return $this->json(
                FALSE,
                'That filename already exists under the selected department.'
            );
        }

        $user_id = (int) $this->session->userdata(
            'rms_user_id'
        );

        $new_file_id = $this->Documents_model->create_filename(
            $sub_id,
            $dept_id,
            $filename,
            $user_id
        );

        if (!$new_file_id) {
            return $this->json(
                FALSE,
                'The filename could not be created.'
            );
        }

        /*
         * New filenames are unpublished by the legacy workflow. Claim the
         * new ID immediately so only its creator sees it in both Path lists.
         */
        $this->add_unpublished_ownership(0, $new_file_id);

        return $this->json(
            TRUE,
            'The filename was created successfully.'
        );
    }
    /**
     * Create the next subfolder level under a selected parent.
     */
    public function create_subfolder()
    {
        if (!$this->require_manager()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $parent_level_input = trim((string) $this->input->post(
            'parent_level',
            TRUE
        ));

        /* A parent is strictly Filename (0) through Subfolder9 (9). */
        if (!preg_match('/^[0-9]$/', $parent_level_input)) {
            return $this->json(FALSE, 'Please select a valid parent folder.');
        }

        $parent_level = (int) $parent_level_input;

        $parent_id = (int) $this->input->post(
            'parent_id',
            TRUE
        );

        $subfolder_name_input = (string) $this->input->post(
            'subfolder_name',
            TRUE
        );
        $subfolder_name = trim($subfolder_name_input);

        if ($parent_level >= $this->maximum_level) {
            return $this->json(
                FALSE,
                'Subfolder10 is the maximum folder level.'
            );
        }

        if ($parent_id <= 0) {
            return $this->json(
                FALSE,
                'Please select a valid parent folder.'
            );
        }

        if ($subfolder_name === '') {
            return $this->json(
                FALSE,
                'Please enter the subfolder name.'
            );
        }

        if (strlen($subfolder_name) > 250) {
            return $this->json(
                FALSE,
                'The subfolder name must not exceed 250 characters.'
            );
        }

        $name_error = $this->windows_name_error($subfolder_name_input);
        if ($name_error !== '') {
            return $this->json(FALSE, $name_error);
        }

        /*
         * A subfolder may be added only when the selected parent
         * and its complete hierarchy are unpublished.
         */
        $parent = $this->Documents_model->get_upload_path(
            $parent_level,
            $parent_id,
            $this->get_unpublished_ownership(),
            $this->is_super_admin()
        );

        if (!$parent) {
            return $this->json(
                FALSE,
                'The selected parent hierarchy is not available as an unpublished path for your role.'
            );
        }

        if (
            $this->Documents_model->subfolder_exists(
                $parent_level,
                $parent_id,
                $subfolder_name
            )
        ) {
            return $this->json(
                FALSE,
                'That subfolder already exists under the selected parent.'
            );
        }

        $user_id = (int) $this->session->userdata(
            'rms_user_id'
        );

        $new_subfolder_id =
            $this->Documents_model->create_subfolder(
                $parent_level,
                $parent_id,
                $subfolder_name,
                $user_id
            );

        if (!$new_subfolder_id) {
            return $this->json(
                FALSE,
                'The subfolder could not be created.'
            );
        }

        /*
         * A new subfolder starts unpublished. Give its creator ownership of
         * the new child so the user can continue building the next level.
         */
        $this->add_unpublished_ownership(
            $parent_level + 1,
            $new_subfolder_id
        );

        return $this->json(
            TRUE,
            'The subfolder was created successfully.'
        );
    }
    /**
     * Upload original documents and optional watermark copies.
     *
     * Original files:
     * system_setting setting_id = 1
     * database table = data
     *
     * Watermark/viewer files:
     * system_setting setting_id = 7
     * database table = data_f
     */
    public function upload()
    {
        if (!$this->require_manager()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $level = $this->normalize_level(
            $this->input->post('record_level', TRUE)
        );

        $record_id = (int) $this->input->post(
            'record_id',
            TRUE
        );

        if ($record_id <= 0) {
            return $this->upload_response(
                FALSE,
                'Please select a valid upload destination.'
            );
        }

        /*
         * Validate the destination again on the server. A manually changed
         * record ID must never bypass the role's unpublished-path rule. Level
         * 4 uses every global unpublished path; Level 3 uses its owned paths.
         */
        $path = $this->Documents_model->get_upload_path(
            $level,
            $record_id,
            $this->get_unpublished_ownership(),
            $this->is_super_admin(),
            TRUE
        );

        if (!$path) {
            return $this->upload_response(
                FALSE,
                'Upload is allowed only in an Unpublished folder with no existing subfolder.'
            );
        }

        $original_files = $this->normalize_uploaded_files(
            isset($_FILES['original_files'])
                ? $_FILES['original_files']
                : array()
        );

        $watermark_files = $this->normalize_uploaded_files(
            isset($_FILES['watermark_files'])
                ? $_FILES['watermark_files']
                : array()
        );

        if (empty($original_files)) {
            return $this->upload_response(
                FALSE,
                'Please select at least one original file.'
            );
        }

        if (count($original_files) > 250) {
            return $this->upload_response(
                FALSE,
                'A maximum of 250 original files may be uploaded at one time.'
            );
        }

        if (count($watermark_files) > 250) {
            return $this->upload_response(
                FALSE,
                'A maximum of 250 watermark files may be uploaded at one time.'
            );
        }

        /*
         * If watermark files were selected, require one watermark file for
         * each original file. This preserves the legacy page-number pairing
         * between data and data_f without requiring a schema change.
         */
        if (
            !empty($watermark_files) &&
            count($watermark_files) !== count($original_files)
        ) {
            return $this->upload_response(
                FALSE,
                'The number of watermark files must match the number of original files.'
            );
        }

        /* Reject Windows-incompatible names before creating any directory. */
        foreach ($original_files as $original_file) {
            $validation = $this->validate_document_file($original_file);
            if ($validation !== TRUE) {
                return $this->upload_response(FALSE, $validation);
            }
        }

        foreach ($watermark_files as $watermark_file) {
            $validation = $this->validate_document_file($watermark_file);
            if ($validation !== TRUE) {
                return $this->upload_response(FALSE, $validation);
            }
        }

        /*
         * Keep document files in the existing legacy RMS storage roots.
         * These paths are intentionally absolute because the CI3 project
         * may be installed in a different directory (for example, rms_ci).
         */
        /* STORAGE FIX: uploads and previews must resolve the same configured roots. */
        $original_root = $this->resolve_storage_root(
            $this->Documents_model->get_system_setting(1),
            FCPATH . 'administrator/agc_data/'
        );
        $watermark_root = $this->resolve_storage_root(
            $this->Documents_model->get_system_setting(7),
            FCPATH . 'data/'
        );

        $relative_path = $this->build_upload_relative_path($path);

        if ($relative_path === FALSE) {
            return $this->upload_response(
                FALSE,
                'The selected destination contains an invalid folder name.'
            );
        }

        $original_directory = $this->join_storage_path(
            $original_root,
            $relative_path
        );

        $watermark_directory = $this->join_storage_path(
            $watermark_root,
            $relative_path
        );

        if (!$this->ensure_directory($original_directory)) {
            return $this->upload_response(
                FALSE,
                'The original-file destination could not be created or accessed.'
            );
        }

        if (
            !empty($watermark_files) &&
            !$this->ensure_directory($watermark_directory)
        ) {
            return $this->upload_response(
                FALSE,
                'The watermark-file destination could not be created or accessed.'
            );
        }

        $path_ids = $this->build_upload_path_ids($path);
        $stored_files = array();

        $this->db->trans_begin();

        foreach ($original_files as $index => $original_file) {
            $validation = $this->validate_document_file(
                $original_file
            );

            if ($validation !== TRUE) {
                $this->db->trans_rollback();
                $this->remove_uploaded_files($stored_files);

                return $this->upload_response(
                    FALSE,
                    $validation
                );
            }

            $original_name = $this->safe_upload_filename(
                $original_file['name']
            );

            $original_name = $this->unique_upload_filename(
                $original_directory,
                $original_name
            );
            $original_destination = $this->new_encrypted_document_path(
                $original_directory,
                $original_name
            );

            log_message(
                'error',
                'RMS ENCRYPTION TEST - original destination: ' .
                    $original_destination
            );

            if ($original_destination === FALSE) {
                $this->db->trans_rollback();
                $this->remove_uploaded_files($stored_files);

                return $this->upload_response(
                    FALSE,
                    'The encrypted original filename could not be generated.'
                );
            }

            if (
                !is_uploaded_file($original_file['tmp_name']) ||
                !move_uploaded_file(
                    $original_file['tmp_name'],
                    $original_destination
                )
            ) {
                $this->db->trans_rollback();
                $this->remove_uploaded_files($stored_files);

                return $this->upload_response(
                    FALSE,
                    'An original file could not be saved: ' .
                        $original_file['name']
                );
            }

            $stored_files[] = $original_destination;

            /*
             * Page numbers begin at 1 and preserve the selected-file order,
             * matching the legacy New Documents V1 upload form.
             */
            $page_no = $index + 1;

            if (
                !$this->Documents_model->create_document(
                    $original_name,
                    $page_no,
                    $path_ids
                )
            ) {
                $this->db->trans_rollback();
                $this->remove_uploaded_files($stored_files);

                return $this->upload_response(
                    FALSE,
                    'The original document record could not be saved.'
                );
            }

            if (!empty($watermark_files)) {
                $watermark_file = $watermark_files[$index];

                $validation = $this->validate_document_file(
                    $watermark_file
                );

                if ($validation !== TRUE) {
                    $this->db->trans_rollback();
                    $this->remove_uploaded_files($stored_files);

                    return $this->upload_response(
                        FALSE,
                        $validation
                    );
                }

                $watermark_name = $this->safe_upload_filename(
                    $watermark_file['name']
                );

                $watermark_name = $this->unique_upload_filename(
                    $watermark_directory,
                    $watermark_name
                );

                $watermark_destination = $this->new_encrypted_document_path(
                    $watermark_directory,
                    $watermark_name
                );

                log_message(
                    'error',
                    'RMS ENCRYPTION TEST - watermark destination: ' .
                        $watermark_destination
                );

                if ($watermark_destination === FALSE) {
                    $this->db->trans_rollback();
                    $this->remove_uploaded_files($stored_files);

                    return $this->upload_response(
                        FALSE,
                        'The encrypted watermark filename could not be generated.'
                    );
                }
                if (
                    !is_uploaded_file($watermark_file['tmp_name']) ||
                    !move_uploaded_file(
                        $watermark_file['tmp_name'],
                        $watermark_destination
                    )
                ) {
                    $this->db->trans_rollback();
                    $this->remove_uploaded_files($stored_files);

                    return $this->upload_response(
                        FALSE,
                        'A watermark file could not be saved: ' .
                            $watermark_file['name']
                    );
                }

                $stored_files[] = $watermark_destination;

                if (
                    !$this->Documents_model->create_view_document(
                        $watermark_name,
                        $page_no,
                        $path_ids
                    )
                ) {
                    $this->db->trans_rollback();
                    $this->remove_uploaded_files($stored_files);

                    return $this->upload_response(
                        FALSE,
                        'The watermark document record could not be saved.'
                    );
                }
            }
        }

        /*
         * The legacy uploader updates the date_modified field of the exact
         * filename/subfolder that received the files. Keep that behavior so
         * Manage Documents immediately reflects the upload activity.
         */
        if (!$this->Documents_model->touch_record($level, $record_id)) {
            $this->db->trans_rollback();
            $this->remove_uploaded_files($stored_files);

            return $this->upload_response(
                FALSE,
                'The destination folder could not be updated.'
            );
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            $this->remove_uploaded_files($stored_files);

            return $this->upload_response(
                FALSE,
                'The document upload could not be completed.'
            );
        }

        $this->db->trans_commit();

        $uploaded_count = count($original_files);
        $message = $uploaded_count === 1
            ? 'The document was uploaded successfully.'
            : $uploaded_count .
            ' documents were uploaded successfully.';

        return $this->upload_response(
            TRUE,
            $message,
            array(
                'uploadedCount' => $uploaded_count,

   
                'destination' => str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    $relative_path
                )
            )
        );
    }

    /**
     * Keep uploads on Manage Documents.
     *
     * The modal receives JSON. A normal multipart POST remains supported as
     * a safe fallback, but it also returns to the same Manage Documents
     * location instead of opening the separate create.php page.
     */
    private function upload_response(
        $success,
        $message,
        $extra = array()
    ) {
        if ($this->input->is_ajax_request()) {
            return $this->json(
                $success,
                $message,
                $extra
            );
        }

        $this->session->set_flashdata(
            $success ? 'success' : 'error',
            $message
        );

        redirect($this->upload_return_url());
    }

    /**
     * Preserve the current folder when JavaScript is unavailable.
     */
    private function upload_return_url()
    {
        $level = $this->normalize_level(
            $this->input->post('return_level', TRUE)
        );

        $parent_id = (int) $this->input->post(
            'return_parent_id',
            TRUE
        );

        $url = 'administrator/documents';

        if ($level > 0 && $parent_id > 0) {
            $url .= '?' . http_build_query(array(
                'level' => $level,
                'parent_id' => $parent_id
            ));
        }

        return $url;
    }

    /**
     * Convert the multiple-file $_FILES format into individual files.
     */
    private function normalize_uploaded_files($files)
    {
        $normalized = array();

        if (
            !is_array($files) ||
            !isset($files['name']) ||
            !is_array($files['name'])
        ) {
            return $normalized;
        }

        foreach ($files['name'] as $index => $name) {
            $error = isset($files['error'][$index])
                ? (int) $files['error'][$index]
                : UPLOAD_ERR_NO_FILE;

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = array(
                'name' => (string) $name,
                'type' => isset($files['type'][$index])
                    ? (string) $files['type'][$index]
                    : '',
                'tmp_name' => isset($files['tmp_name'][$index])
                    ? (string) $files['tmp_name'][$index]
                    : '',
                'error' => $error,
                'size' => isset($files['size'][$index])
                    ? (int) $files['size'][$index]
                    : 0
            );
        }

        return $normalized;
    }

    /**
     * Validate the legacy RMS file extensions and 15 MB limit.
     */
    private function validate_document_file($file)
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return 'A selected file could not be uploaded: ' .
                (
                    isset($file['name'])
                    ? $file['name']
                    : 'Unknown file'
                );
        }

        if (
            !isset($file['size']) ||
            $file['size'] <= 0 ||
            $file['size'] > (15 * 1024 * 1024)
        ) {
            return 'Each file must be no larger than 15 MB: ' .
                $file['name'];
        }

        $name_error = $this->windows_name_error(
            isset($file['name']) ? $file['name'] : ''
        );

        if ($name_error !== '') {
            return $name_error;
        }

        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        $allowed_extensions = array(
            'jpg',
            'jpeg',
            'png',
            'docx',
            'doc',
            'pdf',
            'xlsx'
        );

        if (!in_array($extension, $allowed_extensions, TRUE)) {
            return 'This file type is not allowed: ' .
                $file['name'];
        }

        return TRUE;
    }

    /**
     * Build the subsidiary/department/folder hierarchy.
     */
    private function build_upload_relative_path($path)
    {
        $segments = array();

        if (!is_array($path)) {
            return FALSE;
        }

        /*
         * The legacy physical hierarchy always starts with:
         * Subsidiary / Department / Filename.
         */
        foreach (array('sub_name', 'dept_name', 'filename') as $field) {
            if (!isset($path[$field])) {
                return FALSE;
            }

            $segment = $this->safe_folder_segment($path[$field]);

            if ($segment === FALSE) {
                return FALSE;
            }

            $segments[] = $this->server_storage_name($segment);
        }

        $record_level = isset($path['record_level'])
            ? $this->normalize_level($path['record_level'])
            : 0;

        /*
         * build_manage_query() returns subfolderN_name fields. The earlier
         * conversion looked for subfolderN and silently uploaded a Subfolder4
         * document into the main Filename directory. Require every expected
         * folder segment so an incomplete path fails safely instead.
         */
        for ($level = 1; $level <= $record_level; $level++) {
            $name_field = 'subfolder' . $level . '_name';
            $legacy_field = 'subfolder' . $level;

            if (!empty($path[$name_field])) {
                $value = $path[$name_field];
            } elseif (!empty($path[$legacy_field])) {
                $value = $path[$legacy_field];
            } else {
                return FALSE;
            }

            $segment = $this->safe_folder_segment($value);

            if ($segment === FALSE) {
                return FALSE;
            }

            $segments[] = $this->server_storage_name($segment);
        }

        return implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Prepare hierarchy IDs for data and data_f records.
     */
    private function build_upload_path_ids($path)
    {
        $ids = array(
            'file_id' => isset($path['file_id'])
                ? (int) $path['file_id']
                : 0
        );

        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $column = 'subfolder' . $level . '_id';

            $ids[$column] = isset($path[$column])
                ? (int) $path[$column]
                : 0;
        }

        return $ids;
    }

    /**
     * Use a configured RMS storage directory or a safe legacy fallback.
     */
    private function resolve_storage_root($configured_path, $fallback)
    {
        $configured_path = trim((string) $configured_path);

        if ($configured_path === '') {
            return rtrim(
                $fallback,
                '/\\'
            );
        }

        /* Absolute Linux path. */
        if (substr($configured_path, 0, 1) === '/') {
            return rtrim($configured_path, '/\\');
        }

        /* Absolute Windows path. */
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $configured_path)) {
            return rtrim($configured_path, '/\\');
        }

        /*
         * STORAGE FIX: the supplied RMS database stores paths such as
         * ../rms/administrator/agc_data and ../rms/data. They are relative to
         * the application web root, not to an added /administrator directory.
         */
        $legacy_base = rtrim(FCPATH, '/\\');

        $relative_path = str_replace(
            array('/', '\\'),
            DIRECTORY_SEPARATOR,
            $configured_path
        );

        $candidate = $legacy_base . DIRECTORY_SEPARATOR .
            ltrim($relative_path, '/\\');

        $resolved = realpath($candidate);

        return $resolved !== FALSE
            ? rtrim($resolved, '/\\')
            : rtrim($candidate, '/\\');
    }

    /**
     * Allow a folder name while preventing directory traversal.
     */
    private function safe_folder_segment($value)
    {
        $value = trim((string) $value);

        if (
            $value === '' ||
            $value === '.' ||
            $value === '..' ||
            strpos($value, "\0") !== FALSE ||
            strpos($value, '/') !== FALSE ||
            strpos($value, '\\') !== FALSE
        ) {
            return FALSE;
        }

        return $value;
    }

    /**
     * Convert a valid user-facing folder/file base name to its physical
     * server-storage form. Database/display names remain unchanged.
     */
    private function server_storage_name($value)
    {
        return preg_replace('/\s+/', '_', trim((string) $value));
    }

    /**
     * Sanitize an uploaded basename without changing its extension.
     */
    private function safe_upload_filename($filename)
    {
        $filename = basename(
            str_replace('\\', '/', (string) $filename)
        );

        $extension = strtolower(
            pathinfo($filename, PATHINFO_EXTENSION)
        );

        $name = pathinfo($filename, PATHINFO_FILENAME);

        $name = preg_replace(
            '/[^A-Za-z0-9._ -]+/',
            '_',
            $name
        );

        /*
         * Legacy storage accepts spaces, but normalized underscore names are
         * safer in URLs and match the requested RMS naming convention.
         * Collapse a run of spaces instead of creating repeated underscores.
         */
        $name = preg_replace('/\s+/', '_', $name);

        $name = trim($name, " ._-");

        if ($name === '') {
            $name = 'document';
        }

        return $name . '.' . $extension;
    }


    /**
     * Encrypt a Documents data_id into a URL-safe opaque token.
     *
     * Browser-facing URLs and AJAX payloads use the token. The database
     * primary key remains an integer. Purpose markers prevent a token
     * created for one Documents action from being reused for another.
     */
    private function encrypt_document_id_token($data_id, $purpose = 'document')
    {
        $data_id = (int) $data_id;

        if ($data_id <= 0) {
            return FALSE;
        }

        $purpose = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $purpose));
        if ($purpose === '') {
            $purpose = 'document';
        }

        $payload = $purpose . '|' . $data_id;
        $encrypted = $this->encryption->encrypt($payload);

        if ($encrypted === FALSE || $encrypted === '') {
            return FALSE;
        }

        return rtrim(strtr($encrypted, '+/', '-_'), '=');
    }

    /**
     * Decrypt a Documents opaque token back to a positive integer data_id.
     * Returns FALSE for missing, modified, or wrong-purpose tokens.
     */
    private function decrypt_document_id_token($token, $purpose = 'document')
    {
        $token = trim((string) $token);

        if ($token === '') {
            return FALSE;
        }

        $purpose = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $purpose));
        if ($purpose === '') {
            $purpose = 'document';
        }

        $encoded = strtr($token, '-_', '+/');
        $padding = strlen($encoded) % 4;

        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decrypted = $this->encryption->decrypt($encoded);

        if ($decrypted === FALSE || $decrypted === '') {
            return FALSE;
        }

        $parts = explode('|', $decrypted, 2);

        if (count($parts) !== 2 || $parts[0] !== $purpose) {
            return FALSE;
        }

        $data_id = (int) $parts[1];

        return $data_id > 0 ? $data_id : FALSE;
    }

    /**
     * Resolve one or more browser tokens (or legacy integer IDs) into
     * positive integer data_id values. Invalid tokens are dropped.
     */
    private function resolve_document_ids($raw_ids, $purpose = 'document')
    {
        if (!is_array($raw_ids)) {
            $raw_ids = array($raw_ids);
        }

        $resolved = array();

        foreach ($raw_ids as $raw) {
            if ($raw === NULL || $raw === '') {
                continue;
            }

            /* Prefer token decryption. Fall back to plain positive integers
             * only while transitional links still exist. */
            if (is_string($raw) && !ctype_digit($raw)) {
                $data_id = $this->decrypt_document_id_token($raw, $purpose);
            } else {
                $data_id = (int) $raw;
                if ($data_id <= 0) {
                    $data_id = $this->decrypt_document_id_token((string) $raw, $purpose);
                }
            }

            if ($data_id !== FALSE && $data_id > 0) {
                $resolved[] = $data_id;
            }
        }

        return array_values(array_unique($resolved));
    }


    /**
     * Encrypt a Documents folder context (level + record_id) into a
     * URL-safe opaque path token. Used for /documents/manage/{token}
     * style links that hide the numeric hierarchy IDs from the address bar.
     */
    private function encrypt_document_context_token($level, $record_id, $purpose = 'document-manage')
    {
        $level = (int) $level;
        $record_id = (int) $record_id;

        if ($record_id <= 0) {
            return FALSE;
        }

        $purpose = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $purpose));
        if ($purpose === '') {
            $purpose = 'document-manage';
        }

        $payload = $purpose . '|' . $level . '|' . $record_id;
        $encrypted = $this->encryption->encrypt($payload);

        if ($encrypted === FALSE || $encrypted === '') {
            return FALSE;
        }

        return rtrim(strtr($encrypted, '+/', '-_'), '=');
    }

    /**
     * Decrypt a Documents context token back to [level, record_id].
     * Returns FALSE on any failure or purpose mismatch.
     */
    private function decrypt_document_context_token($token, $purpose = 'document-manage')
    {
        $token = trim((string) $token);

        if ($token === '') {
            return FALSE;
        }

        $purpose = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $purpose));
        if ($purpose === '') {
            $purpose = 'document-manage';
        }

        $encoded = strtr($token, '-_', '+/');
        $padding = strlen($encoded) % 4;

        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decrypted = $this->encryption->decrypt($encoded);

        if ($decrypted === FALSE || $decrypted === '') {
            return FALSE;
        }

        $parts = explode('|', $decrypted, 3);

        if (count($parts) !== 3 || $parts[0] !== $purpose) {
            return FALSE;
        }

        $level = (int) $parts[1];
        $record_id = (int) $parts[2];

        if ($record_id <= 0) {
            return FALSE;
        }

        return array($level, $record_id);
    }

    /**
     * Build the opaque manage URL for a folder that contains uploaded documents.
     * Falls back to the classic query-string form if encryption fails.
     */

    /**
     * Opaque URL for the hierarchy browser (Manage Documents).
     * Form: /administrator/documents/browse/{token}
     * Token carries level + parent_id so numeric IDs never appear in the address bar.
     */
    private function document_browse_url($level, $parent_id)
    {
        $level = (int) $level;
        $parent_id = (int) $parent_id;

        if ($level <= 0) {
            return site_url('administrator/documents');
        }

        $token = $this->encrypt_document_context_token(
            $level,
            $parent_id,
            'document-browse'
        );

        if ($token === FALSE) {
            return site_url('administrator/documents') . '?' .
                http_build_query(array(
                    'level' => $level,
                    'parent_id' => $parent_id
                ));
        }

        return site_url('administrator/documents/browse/' . $token);
    }

    private function document_manage_url($level, $record_id)
    {
        $token = $this->encrypt_document_context_token($level, $record_id);

        if ($token === FALSE) {
            return site_url('administrator/documents/view_documents') . '?' .
                http_build_query(array(
                    'level' => (int) $level,
                    'record_id' => (int) $record_id
                ));
        }

        return site_url('administrator/documents/manage/' . $token);
    }

    /**
     * Create a filesystem-safe encrypted physical filename.
     *
     * The database continues to store the friendly filename so the existing
     * table, search, sorting, rename display and download name keep working.
     */
    private function encrypted_storage_filename($friendly_name)
    {
        $friendly_name = basename(
            str_replace('\\', '/', (string) $friendly_name)
        );

        if ($friendly_name === '') {
            return FALSE;
        }

        $extension = strtolower(
            pathinfo($friendly_name, PATHINFO_EXTENSION)
        );

        $fingerprint = hash('sha256', $friendly_name, TRUE);
        $encrypted = $this->encryption->encrypt($fingerprint);

        if ($encrypted === FALSE || $encrypted === '') {
            return FALSE;
        }

        $token = rtrim(strtr($encrypted, '+/', '-_'), '=');
        $physical_name = 'rmsenc_' . $token;

        if ($extension !== '') {
            $physical_name .= '.' . $extension;
        }

        return strlen($physical_name) <= 240
            ? $physical_name
            : FALSE;
    }

    /** Confirm that one encrypted physical name belongs to a friendly name. */
    private function encrypted_filename_matches(
        $physical_name,
        $friendly_name
    ) {
        $physical_name = basename((string) $physical_name);
        $friendly_name = basename(
            str_replace('\\', '/', (string) $friendly_name)
        );

        if (
            strtolower(pathinfo($physical_name, PATHINFO_EXTENSION)) !==
            strtolower(pathinfo($friendly_name, PATHINFO_EXTENSION))
        ) {
            return FALSE;
        }

        $physical_base = pathinfo($physical_name, PATHINFO_FILENAME);

        if (strpos($physical_base, 'rmsenc_') !== 0) {
            return FALSE;
        }

        $encoded = substr($physical_base, strlen('rmsenc_'));

        if ($encoded === '') {
            return FALSE;
        }

        $encoded = strtr($encoded, '-_', '+/');
        $padding = strlen($encoded) % 4;

        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decrypted = $this->encryption->decrypt($encoded);

        if ($decrypted === FALSE) {
            return FALSE;
        }

        return $decrypted === hash('sha256', $friendly_name, TRUE);
    }

    /** Resolve encrypted files while retaining existing plaintext files. */
    private function resolve_physical_document_path(
        $directory,
        $friendly_name
    ) {
        $directory = rtrim((string) $directory, '/\\');
        $friendly_name = basename(
            str_replace('\\', '/', (string) $friendly_name)
        );

        if ($directory === '' || $friendly_name === '') {
            return FALSE;
        }

        $legacy_path = $this->join_storage_path(
            $directory,
            $friendly_name
        );

        if (is_file($legacy_path)) {
            return $legacy_path;
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

            if (!$this->encrypted_filename_matches($entry, $friendly_name)) {
                continue;
            }

            $path = $this->join_storage_path($directory, $entry);

            if (is_file($path)) {
                return $path;
            }
        }

        return FALSE;
    }

    /** Build a new encrypted physical path for Upload, Rename or Transfer. */
    private function new_encrypted_document_path(
        $directory,
        $friendly_name
    ) {
        $encrypted_name = $this->encrypted_storage_filename($friendly_name);

        if ($encrypted_name === FALSE) {
            return FALSE;
        }

        return $this->join_storage_path($directory, $encrypted_name);
    }

    /**
     * Prevent an existing physical file from being overwritten.
     */
    private function unique_upload_filename($directory, $filename)
    {
        $candidate = $filename;
        $extension = pathinfo(
            $filename,
            PATHINFO_EXTENSION
        );

        $name = pathinfo(
            $filename,
            PATHINFO_FILENAME
        );

        $counter = 1;

        while (
            $this->resolve_physical_document_path(
                $directory,
                $candidate
            ) !== FALSE
        ) {
            $candidate = $name .
                '_' .
                $counter .
                (
                    $extension !== ''
                    ? '.' . $extension
                    : ''
                );

            $counter++;
        }

        return $candidate;
    }

    private function documents_storage_roots()
    {
        return array(
            '/var/www/html/rms/administrator/agc_data',
            '/var/www/html/rms/data'
        );
    }

    /**
     * Build a safe storage path for an original or viewer document using the
     * existing system_setting values. Every segment must be a basename so a
     * database value cannot escape the configured RMS storage directory.
     */
    private function document_storage_directory($document, $original)
    {
        $setting_id = $original ? 1 : 7;
        $fallback = $original
            ? FCPATH . 'administrator/agc_data/'
            : FCPATH . 'data/';

        /* STORAGE FIX: use exactly the same root resolver used during upload. */
        $root = $this->resolve_storage_root(
            $this->Documents_model->get_system_setting($setting_id),
            $fallback
        );

        $parts = array(
            isset($document['sub_name']) ? $document['sub_name'] : '',
            isset($document['dept_name']) ? $document['dept_name'] : '',
            isset($document['filename']) ? $document['filename'] : ''
        );

        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $name = isset($document['subfolder' . $level . '_name'])
                ? $document['subfolder' . $level . '_name']
                : '';

            if ($name !== '') {
                $parts[] = $name;
            }
        }

        $legacy_parts = array();
        $server_parts = array();

        foreach ($parts as $part) {
            $safe = $this->safe_folder_segment($part);
            if ($safe === FALSE) {
                return FALSE;
            }

            $legacy_parts[] = $safe;
            $server_parts[] = $this->server_storage_name($safe);
        }

        $server_directory = rtrim($root, '/\\') . DIRECTORY_SEPARATOR .
            implode(DIRECTORY_SEPARATOR, $server_parts);

        if (is_dir($server_directory)) {
            return $server_directory;
        }

        /* Backward compatibility for folders created before underscore normalization. */
        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR .
            implode(DIRECTORY_SEPARATOR, $legacy_parts);
    }

    /** Resolve an existing encrypted or legacy physical document. */
    private function document_storage_path($document, $served_name, $original)
    {
        $directory = $this->document_storage_directory(
            $document,
            $original
        );

        if ($directory === FALSE) {
            return FALSE;
        }

        return $this->resolve_physical_document_path(
            $directory,
            $served_name
        );
    }

    /** Build a new encrypted destination used during Rename and Transfer. */
    private function new_document_storage_path(
        $document,
        $served_name,
        $original
    ) {
        $directory = $this->document_storage_directory(
            $document,
            $original
        );

        if ($directory === FALSE) {
            return FALSE;
        }

        return $this->new_encrypted_document_path(
            $directory,
            $served_name
        );
    }
    /* WATERMARK-ONLY DISPLAY: expose the protected name only when its file exists. */
    private function document_preview_info($data_id)
    {
        $document = $this->Documents_model->get_document_file(
            (int) $data_id
        );

        $has_watermark = FALSE;

        if (
            $document &&
            !empty($document['viewer_name'])
        ) {
            $watermark_path = $this->document_storage_path(
                $document,
                $document['viewer_name'],
                FALSE
            );

            $has_watermark =
                $watermark_path !== FALSE &&
                is_file($watermark_path) &&
                is_readable($watermark_path);
        }

        return array(
            'has_watermark' => $has_watermark ? 1 : 0,
            'display_name' => $has_watermark
                ? (string) $document['viewer_name']
                : ($document ? (string) $document['data_name'] : ''),
            'preview_type' => $has_watermark
                ? 'watermark'
                : 'unavailable',
            'preview_label' => $has_watermark
                ? 'Watermarked preview'
                : 'Watermarked preview unavailable'
        );
    }
    /** Confirm a selected data row belongs to the open View Documents page. */
    private function document_matches_record($document, $level, $record_id)
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;

        if ($level === 0) {
            return
                (int) $document['file_id'] === $record_id &&
                (int) $document['subfolder1_id'] === 0;
        }

        if ((int) $document['subfolder' . $level . '_id'] !== $record_id) {
            return FALSE;
        }

        return $level === $this->maximum_level ||
            (int) $document['subfolder' . ($level + 1) . '_id'] === 0;
    }

    /** Restore physical files when database deletion cannot be committed. */
    private function rollback_staged_file_deletions($staged)
    {
        for ($index = count($staged) - 1; $index >= 0; $index--) {
            if (is_file($staged[$index]['temporary'])) {
                @rename(
                    $staged[$index]['temporary'],
                    $staged[$index]['original']
                );
            }
        }
    }

    /** Reverse completed physical renames/transfers in last-in-first-out order. */
    private function rollback_file_moves($moves)
    {
        for ($index = count($moves) - 1; $index >= 0; $index--) {
            if (is_file($moves[$index]['new'])) {
                @rename($moves[$index]['new'], $moves[$index]['old']);
            }
        }
    }

    private function valid_directory_name($name)
    {
        return $this->safe_folder_segment($name) !== FALSE;
    }

    private function record_path_segments($record, $level)
    {
        $segments = array(
            isset($record['sub_name']) ? (string) $record['sub_name'] : '',
            isset($record['dept_name']) ? (string) $record['dept_name'] : '',
            isset($record['filename']) ? (string) $record['filename'] : ''
        );

        for ($current = 1; $current <= $level; $current++) {
            $key = 'subfolder' . $current . '_name';
            $segments[] = isset($record[$key]) ? (string) $record[$key] : '';
        }

        foreach ($segments as $index => $segment) {
            $safe = $this->safe_folder_segment($segment);
            if ($safe === FALSE) {
                return array();
            }
            $segments[$index] = $safe;
        }

        return $segments;
    }

    private function rollback_directory_renames($renamed)
    {
        for ($index = count($renamed) - 1; $index >= 0; $index--) {
            if (is_dir($renamed[$index]['new'])) {
                @rename($renamed[$index]['new'], $renamed[$index]['old']);
            }
        }
    }

    private function rollback_staged_deletions($staged)
    {
        for ($index = count($staged) - 1; $index >= 0; $index--) {
            if (is_dir($staged[$index]['temporary'])) {
                @rename($staged[$index]['temporary'], $staged[$index]['original']);
            }
        }
    }

    private function delete_directory_tree($directory)
    {
        if (!is_dir($directory)) {
            return TRUE;
        }

        $items = @scandir($directory);
        if ($items === FALSE) {
            return FALSE;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                if (!$this->delete_directory_tree($path)) {
                    return FALSE;
                }
            } elseif (!@unlink($path)) {
                return FALSE;
            }
        }

        return @rmdir($directory);
    }

    /**
     * Join two storage-path sections.
     */
    private function join_storage_path($base, $path)
    {
        if (is_array($path)) {
            $path = implode(DIRECTORY_SEPARATOR, $path);
        }

        return rtrim($base, '/\\') .
            DIRECTORY_SEPARATOR .
            ltrim($path, '/\\');
    }

    /**
     * Create a directory when it does not already exist.
     */
    private function ensure_directory($directory)
    {
        clearstatcache(TRUE, $directory);

        if (is_dir($directory)) {
            return is_writable($directory);
        }

        if (!@mkdir($directory, 0775, TRUE) && !is_dir($directory)) {
            log_message(
                'error',
                'Documents upload directory could not be created: ' .
                    $directory
            );

            return FALSE;
        }

        clearstatcache(TRUE, $directory);

        return is_dir($directory) && is_writable($directory);
    }

    /**
     * Remove physical files when the database transaction fails.
     */
    private function remove_uploaded_files($files)
    {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Return a validation message for names that Windows cannot create.
     * This is read-only and does not change the legacy RMS schema or name.
     */
    private function windows_name_error($name)
    {
        $name = (string) $name;

        if (trim($name) === '') {
            return 'A filename or subfolder name is required.';
        }

        if (strlen($name) > 250) {
            return 'The filename or subfolder name must not exceed 250 characters.';
        }

        if (preg_match('~[<>:"/\\\\|?*\x00-\x1F]~', $name)) {
            return 'Invalid name. Remove these characters: < > : " / \\ | ? *';
        }

        if (preg_match('/[. ]$/', $name)) {
            return 'A Windows name cannot end with a period or space.';
        }

        if ($name === '.' || $name === '..') {
            return 'The name "." or ".." is not allowed.';
        }

        if (
            preg_match(
                '/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i',
                $name
            )
        ) {
            return 'This name is reserved by Windows. Choose another name.';
        }

        return '';
    }

    /**
     * Common information used by the existing administrator layout.
     */
    private function common_view_data()
    {
        return array(
            'system_name' => $this->config->item(
                'rms_auth_system_name'
            ),

            'display_name' => $this->session->userdata(
                'rms_display_name'
            ),

            'username' => $this->session->userdata(
                'rms_username'
            ),

            'position' => $this->session->userdata(
                'rms_position'
            ),

            'employee_id' => $this->session->userdata(
                'rms_employee_id'
            ),

            'routes' => $this->config->item(
                'rms_dashboard_routes'
            )
        );
    }

    /**
     * Labels used by the tabs in Manage Documents.
     */
    private function document_levels()
    {
        $levels = array(
            0 => 'Filename'
        );

        for (
            $level = 1;
            $level <= $this->maximum_level;
            $level++
        ) {
            $levels[$level] = 'Subfolder' . $level;
        }

        return $levels;
    }

    /**
     * Keep the selected hierarchy level between 0 and 10.
     */
    private function normalize_level($level)
    {
        $level = (int) $level;

        if ($level < 0) {
            return 0;
        }

        if ($level > $this->maximum_level) {
            return $this->maximum_level;
        }

        return $level;
    }

    /**
     * Return a JSON response for AJAX requests.
     */
    private function json(
        $success,
        $message = '',
        $extra = array()
    ) {
        $response = array_merge(
            array(
                'success' => (bool) $success,
                'message' => (string) $message,
                'csrfName' =>
                $this->security->get_csrf_token_name(),
                'csrfHash' =>
                $this->security->get_csrf_hash()
            ),
            is_array($extra) ? $extra : array()
        );

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
}
