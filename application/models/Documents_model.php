<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Documents_model extends CI_Model
{
    private $maximum_level = 10;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /** Return the current user's pins for one Manage Documents listing. */
    public function get_user_pin_map($user_id, $folder_level, $document_level)
    {
        $user_id = (int) $user_id;
        $folder_level = $this->normalize_level($folder_level);
        $document_level = $this->normalize_level($document_level);
        $pins = array();

        if ($user_id <= 0 || !$this->db->table_exists('document_pins')) {
            return $pins;
        }

        $this->db
            ->select('item_type, record_id')
            ->from('document_pins')
            ->where('user_id', $user_id)
            ->group_start()
                ->group_start()
                    ->where('item_type', 'folder')
                    ->where('record_level', $folder_level)
                ->group_end()
                ->or_group_start()
                    ->where('item_type', 'document')
                    ->where('record_level', $document_level)
                ->group_end()
            ->group_end();

        foreach ($this->db->get()->result_array() as $row) {
            $prefix = $row['item_type'] === 'document' ? 'd:' : 'f:';
            $record_id = (int) $row['record_id'];
            if ($record_id > 0) {
                $pins[$prefix . $record_id] = TRUE;
            }
        }

        return $pins;
    }

    /** Return every pinned folder key used by breadcrumb/context markers. */
    public function get_user_folder_pins($user_id)
    {
        $user_id = (int) $user_id;
        $pins = array();

        if ($user_id <= 0 || !$this->db->table_exists('document_pins')) {
            return $pins;
        }

        $rows = $this->db
            ->select('record_level, record_id')
            ->from('document_pins')
            ->where('user_id', $user_id)
            ->where('item_type', 'folder')
            ->get()
            ->result_array();

        foreach ($rows as $row) {
            $level = $this->normalize_level($row['record_level']);
            $record_id = (int) $row['record_id'];
            if ($record_id > 0) {
                $pins[$level . ':' . $record_id] = 1;
            }
        }

        return $pins;
    }

    /** Return one user's complete pin list for the global topbar popup. */
    public function get_user_pins($user_id)
    {
        $user_id = (int) $user_id;

        if ($user_id <= 0 || !$this->db->table_exists('document_pins')) {
            return array();
        }

        return $this->db
            ->select('pin_id, item_type, record_level, record_id, pinned_at')
            ->from('document_pins')
            ->where('user_id', $user_id)
            ->order_by('pinned_at', 'DESC')
            ->order_by('pin_id', 'DESC')
            ->limit(500)
            ->get()
            ->result_array();
    }

    /** Toggle one personal pin and return its final state. */
    public function toggle_user_pin($user_id, $item_type, $record_level, $record_id)
    {
        $user_id = (int) $user_id;
        $item_type = strtolower(trim((string) $item_type));
        $record_level = $this->normalize_level($record_level);
        $record_id = (int) $record_id;

        if (
            $user_id <= 0 || $record_id <= 0 ||
            !in_array($item_type, array('folder', 'document'), TRUE) ||
            !$this->db->table_exists('document_pins')
        ) {
            return FALSE;
        }

        $where = array(
            'user_id' => $user_id,
            'item_type' => $item_type,
            'record_level' => $record_level,
            'record_id' => $record_id
        );
        $existing = $this->db
            ->select('pin_id')->from('document_pins')->where($where)
            ->limit(1)->get()->row_array();

        if ($existing) {
            $deleted = $this->db
                ->where('pin_id', (int) $existing['pin_id'])
                ->where('user_id', $user_id)
                ->delete('document_pins');
            return $deleted ? array('pinned' => FALSE) : FALSE;
        }

        $inserted = $this->db->insert('document_pins', array(
            'user_id' => $user_id,
            'item_type' => $item_type,
            'record_level' => $record_level,
            'record_id' => $record_id,
            'pinned_at' => date('Y-m-d H:i:s')
        ));

        return $inserted ? array('pinned' => TRUE) : FALSE;
    }

    /** Remove orphaned pins after a Documents record is deleted. */
    public function remove_pins_for_record($item_type, $record_level, $record_id)
    {
        $record_id = (int) $record_id;
        if ($record_id <= 0 || !$this->db->table_exists('document_pins')) {
            return TRUE;
        }

        return $this->db
            ->where('item_type', (string) $item_type)
            ->where('record_level', $this->normalize_level($record_level))
            ->where('record_id', $record_id)
            ->delete('document_pins');
    }

    /**
     * Count records displayed in one Manage Documents tab.
     *
     * Level 0 = filename
     * Level 1 = subfolder1
     * ...
     * Level 10 = subfolder10
     */
    public function count_all($level, $search = '', $parent_id = 0)
    {
        $level = $this->normalize_level($level);

        $this->build_manage_query(
            $level,
            $search,
            FALSE,
            $parent_id
        );

        return (int) $this->db->count_all_results();
    }

    /**
     * Return records for one Manage Documents tab.
     */
    public function get_all(
        $level,
        $limit = 10,
        $offset = 0,
        $search = '',
        $parent_id = 0,
        $order_key = 'name',
        $order_direction = 'ASC'
    ) {
        $level = $this->normalize_level($level);
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);
        $order_direction = strtoupper((string) $order_direction) === 'DESC'
            ? 'DESC'
            : 'ASC';

        $this->build_manage_query(
            $level,
            $search,
            TRUE,
            $parent_id
        );

        if ($level === 0) {
            $order_columns = array(
                'name' => 'filename.filename',
                'department' => 'departments.dept_name',
                'subsidiary' => 'subsidiaries.sub_name',
                'date_created' => 'filename.date_created',
                'date_modified' => 'filename.date_modified',
                'status' => 'filename.publish'
            );
        } else {
            $table = 'subfolder' . $level;

            $order_columns = array(
                'name' => $table . '.subfolder' . $level . '_name',
                'department' => 'departments.dept_name',
                'subsidiary' => 'subsidiaries.sub_name',
                'date_created' => $table . '.date_created' . $level,
                'date_modified' => $table . '.date_modified' . $level,
                'status' => $table . '.publish' . $level
            );
        }

        $order_column = isset($order_columns[$order_key])
            ? $order_columns[$order_key]
            : $order_columns['name'];

        $this->db->order_by($order_column, $order_direction);

        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    /**
     * Find one filename or subfolder record.
     */
    public function find($level, $record_id)
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;

        if ($record_id <= 0) {
            return FALSE;
        }

        $this->build_manage_query($level, '', TRUE);

        if ($level === 0) {
            $this->db->where('filename.file_id', $record_id);
        } else {
            $this->db->where(
                'subfolder' . $level . '.subfolder' . $level . '_id',
                $record_id
            );
        }

        $row = $this->db->get()->row_array();

        return $row ? $row : FALSE;
    }
    /**
     * Count documents uploaded directly to one Filename/Subfolder record.
     *
     * IMPORTANT FOR FUTURE MAINTAINERS:
     * Parent IDs are copied into descendant uploads. The next-level-zero
     * condition prevents a Filename from counting every document stored in
     * all of its subfolders; only files uploaded to this exact record count.
     */
    public function count_documents($level, $record_id, $search = '')
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;
        $search = trim((string) $search);

        $this->db->from('data');
        $this->apply_exact_document_path($level, $record_id);
        $this->db->where('stat', 0);

        if ($search !== '') {
            $this->db->like('data_name', $search);
        }

        return (int) $this->db->count_all_results();
    }

    /**
     * Return one page of uploaded documents for one subfolder10 leaf.
     */
    public function get_documents(
        $level,
        $record_id,
        $limit = 10,
        $offset = 0,
        $search = '',
        $order_key = 'data_name',
        $order_direction = 'ASC'
    ) {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);
        $order_direction = strtoupper((string) $order_direction) === 'DESC'
            ? 'DESC'
            : 'ASC';

        $order_columns = array(
            'data_name' => 'data_name',
            'page_no' => 'page_no',
            'date_uploaded' => 'date_uploaded',
            'status' => 'stat'
        );

        $order_column = isset($order_columns[$order_key])
            ? $order_columns[$order_key]
            : 'data_name';

        $this->db->select('data_id, data_name, page_no, date_uploaded, stat');
        $this->db->from('data');
        $this->apply_exact_document_path($level, $record_id);
        $this->db->where('stat', 0);

        if (trim((string) $search) !== '') {
            $this->db->like('data_name', trim($search));
        }

        $this->db->order_by($order_column, $order_direction);
        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    /**
     * Limit a data-table query to files uploaded at exactly one hierarchy
     * level. Filename is level 0; Subfolder1..10 are levels 1..10.
     */
    private function apply_exact_document_path($level, $record_id)
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;

        if ($level === 0) {
            $this->db->where('file_id', $record_id);
        } else {
            $this->db->where('subfolder' . $level . '_id', $record_id);
        }

        if ($level < $this->maximum_level) {
            $this->db->where('subfolder' . ($level + 1) . '_id', 0);
        }
    }

    /**
     * Resolve one uploaded document and all directory labels needed to stream
     * it safely. Viewer copies come from data_f; the original remains the
     * fallback when no matching protected copy exists.
     */
    public function get_document_file($data_id)
    {
        $data_id = (int) $data_id;

        if ($data_id <= 0) {
            return FALSE;
        }

        $fields = 'data.*, filename.filename, departments.dept_name, ' .
            'subsidiaries.sub_name';

        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $fields .= ', subfolder' . $level . '.subfolder' . $level .
                '_name';
        }

        $this->db
            ->select($fields)
            ->from('data')
            ->join('filename', 'filename.file_id = data.file_id', 'inner')
            ->join('departments', 'departments.dept_id = filename.dept_id', 'left')
            ->join('subsidiaries', 'subsidiaries.sub_id = filename.sub_id', 'left');

        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $this->db->join(
                'subfolder' . $level,
                'subfolder' . $level . '.subfolder' . $level .
                    '_id = data.subfolder' . $level . '_id',
                'left'
            );
        }

        $row = $this->db
            ->where('data.data_id', $data_id)
            ->where('data.stat', 0)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row) {
            return FALSE;
        }

        $this->db
            ->select('data_namef')
            ->from('data_f')
            ->where('file_id', (int) $row['file_id'])
            ->where('page_nof', (int) $row['page_no'])
            /* WATERMARK PAIRING: match the exact document, not only page/time. */
            ->where('data_namef', (string) $row['data_name'])
            ->where('date_uploadedf', (string) $row['date_uploaded'])
            ->where('statf', 0);

        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $this->db->where(
                'subfolder' . $level . '_idf',
                (int) $row['subfolder' . $level . '_id']
            );
        }

        $viewer = $this->db->limit(1)->get()->row_array();
        $row['viewer_name'] = $viewer && !empty($viewer['data_namef'])
            ? (string) $viewer['data_namef']
            : '';

        return $row;
    }

    /**
     * Delete selected original metadata and matching viewer-copy metadata.
     * Physical files are staged by the controller before this transaction.
     */
    public function delete_uploaded_documents($data_ids)
    {
        if (!is_array($data_ids) || empty($data_ids)) {
            return FALSE;
        }

        $documents = array();
        foreach (array_unique(array_map('intval', $data_ids)) as $data_id) {
            if ($data_id <= 0) {
                continue;
            }
            $document = $this->get_document_file($data_id);
            if (!$document) {
                return FALSE;
            }
            $documents[] = $document;
        }

        if (empty($documents)) {
            return FALSE;
        }

        $this->db->trans_begin();

        foreach ($documents as $document) {
            $this->db
                ->where('file_id', (int) $document['file_id'])
                ->where('page_nof', (int) $document['page_no']);

            if ($document['viewer_name'] !== '') {
                $this->db->where('data_namef', $document['viewer_name']);
            }

            for ($level = 1; $level <= $this->maximum_level; $level++) {
                $this->db->where(
                    'subfolder' . $level . '_idf',
                    (int) $document['subfolder' . $level . '_id']
                );
            }

            if (!$this->db->delete('data_f')) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if (
                !$this->db
                    ->where('data_id', (int) $document['data_id'])
                    ->delete('data')
            ) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if (!$this->remove_pins_for_record(
                'document',
                $this->document_record_level($document),
                (int) $document['data_id']
            )) {
                $this->db->trans_rollback();
                return FALSE;
            }
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return TRUE;
    }

    /** Update original and viewer names after physical rename succeeds. */
    public function rename_uploaded_document(
        $document,
        $original_name,
        $viewer_name
    ) {
        $this->db->trans_begin();

        if (
            !$this->db
                ->where('data_id', (int) $document['data_id'])
                ->update('data', array('data_name' => $original_name))
        ) {
            $this->db->trans_rollback();
            return FALSE;
        }

        if ($document['viewer_name'] !== '') {
            $this->apply_viewer_identity($document);
            if (!$this->db->update('data_f', array('data_namef' => $viewer_name))) {
                $this->db->trans_rollback();
                return FALSE;
            }
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return TRUE;
    }

    /** Move original and viewer metadata to another existing hierarchy path. */
    public function transfer_uploaded_documents($documents, $path_ids)
    {
        if (!is_array($documents) || empty($documents) || !is_array($path_ids)) {
            return FALSE;
        }

        $original_update = array('file_id' => (int) $path_ids['file_id']);
        $viewer_update = array('file_id' => (int) $path_ids['file_id']);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $column = 'subfolder' . $level . '_id';
            $original_update[$column] = (int) $path_ids[$column];
            $viewer_update[$column . 'f'] = (int) $path_ids[$column];
        }

        $this->db->trans_begin();
        foreach ($documents as $document) {
            if (
                !$this->db
                    ->where('data_id', (int) $document['data_id'])
                    ->update('data', $original_update)
            ) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if ($document['viewer_name'] !== '') {
                $this->apply_viewer_identity($document);
                if (!$this->db->update('data_f', $viewer_update)) {
                    $this->db->trans_rollback();
                    return FALSE;
                }
            }
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }
        $this->db->trans_commit();
        return TRUE;
    }

    /** Apply the composite legacy identity of one data_f viewer row. */
    private function apply_viewer_identity($document)
    {
        $this->db
            ->where('file_id', (int) $document['file_id'])
            ->where('page_nof', (int) $document['page_no'])
            ->where('data_namef', $document['viewer_name']);
        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $this->db->where(
                'subfolder' . $level . '_idf',
                (int) $document['subfolder' . $level . '_id']
            );
        }
    }

    /**
     * Update one legacy Filename/Subfolder record after its physical
     * directory has been renamed successfully by the controller.
     */
    public function update_record($level, $record_id, $name, $sub_id = 0, $dept_id = 0)
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;
        $name = trim((string) $name);

        if ($record_id <= 0 || $name === '') {
            return FALSE;
        }

        if ($level === 0) {
            $data = array(
                'filename' => $name,
                'sub_id' => (int) $sub_id,
                'dept_id' => (int) $dept_id,
                'date_modified' => date('Y/m/d g:ia')
            );

            if ($data['sub_id'] <= 0 || $data['dept_id'] <= 0) {
                return FALSE;
            }

            $this->db->where('file_id', $record_id);
            return $this->db->update('filename', $data);
        }

        $table = 'subfolder' . $level;
        $this->db->where('subfolder' . $level . '_id', $record_id);

        return $this->db->update($table, array(
            'subfolder' . $level . '_name' => $name,
            'date_modified' . $level => date('Y/m/d g:ia')
        ));
    }

    /**
     * Check the same-name rule used by the legacy editor.
     */
    public function record_name_exists($level, $record_id, $name, $sub_id = 0, $dept_id = 0, $parent_id = 0)
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;
        $name = trim((string) $name);

        if ($level === 0) {
            $this->db->from('filename');
            $this->db->where('filename', $name);
            $this->db->where('sub_id', (int) $sub_id);
            $this->db->where('dept_id', (int) $dept_id);
            $this->db->where('file_id !=', $record_id);
        } else {
            $table = 'subfolder' . $level;
            $id_column = 'subfolder' . $level . '_id';
            $parent_column = $level === 1
                ? 'file_id'
                : 'subfolder' . ($level - 1) . '_id';

            $this->db->from($table);
            $this->db->where('subfolder' . $level . '_name', $name);
            $this->db->where($parent_column, (int) $parent_id);
            $this->db->where($id_column . ' !=', $record_id);
        }

        return $this->db->count_all_results() > 0;
    }

    public function get_department_with_subsidiary($dept_id, $sub_id)
    {
        $this->db->select(
            'departments.dept_id, departments.dept_name,
             subsidiaries.sub_id, subsidiaries.sub_name'
        );
        $this->db->from('departments');
        $this->db->join(
            'subsidiaries',
            'departments.sub_id = subsidiaries.sub_id',
            'inner'
        );
        $this->db->where('departments.dept_id', (int) $dept_id);
        $this->db->where('subsidiaries.sub_id', (int) $sub_id);

        $row = $this->db->get()->row_array();
        return $row ? $row : FALSE;
    }

    /**
     * Delete the selected legacy folder record and its document metadata.
     * The physical directories are removed by Documents.php first.
     */
    public function delete_record($level, $record_id)
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;

        if ($record_id <= 0) {
            return FALSE;
        }

        if ($level === 0) {
            $table = 'filename';
            $id_column = 'file_id';
            $data_column = 'file_id';
        } else {
            $table = 'subfolder' . $level;
            $id_column = 'subfolder' . $level . '_id';
            $data_column = 'subfolder' . $level . '_id';
        }

        $deleted_document_ids = $this->db
            ->select('data_id')
            ->from('data')
            ->where($data_column, $record_id)
            ->get()
            ->result_array();

        $this->db->trans_begin();

        $data_deleted = $this->db
            ->where($data_column, $record_id)
            ->delete('data');

        $record_deleted = $this->db
            ->where($id_column, $record_id)
            ->delete($table);

        $pins_deleted = $this->remove_pins_for_record(
            'folder',
            $level,
            $record_id
        );

        foreach ($deleted_document_ids as $deleted_document) {
            if (!$this->remove_pins_for_record(
                'document',
                $level,
                (int) $deleted_document['data_id']
            )) {
                $pins_deleted = FALSE;
                break;
            }
        }

        if (
            !$data_deleted ||
            !$record_deleted ||
            !$pins_deleted ||
            $this->db->trans_status() === FALSE
        ) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();

        return TRUE;
    }

    /** Resolve the deepest populated hierarchy level for one data row. */
    private function document_record_level($document)
    {
        for ($level = $this->maximum_level; $level >= 1; $level--) {
            if (!empty($document['subfolder' . $level . '_id'])) {
                return $level;
            }
        }

        return 0;
    }
    /**
     * Publish or unpublish one record.
     *
     * Existing RMS values:
     * 0 = unpublished
     * 1 = published
     */
    public function set_publish($level, $record_id, $publish)
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;
        $publish = (int) $publish === 1 ? 1 : 0;

        if ($record_id <= 0) {
            return FALSE;
        }

        if ($level === 0) {
            $table = 'filename';
            $id_column = 'file_id';
            $publish_column = 'publish';
            $modified_column = 'date_modified';
        } else {
            $table = 'subfolder' . $level;
            $id_column = 'subfolder' . $level . '_id';
            $publish_column = 'publish' . $level;
            $modified_column = 'date_modified' . $level;
        }

        $data = array(
            $publish_column => $publish,
            $modified_column => date('Y/m/d g:ia')
        );

        $this->db->where($id_column, $record_id);

        return $this->db->update($table, $data);
    }

    /**
     * Publish or unpublish selected records from one tab.
     *
     * IMPORTANT FOR FUTURE MAINTAINERS:
     * This method changes only the legacy RMS publish flag. User ownership
     * is intentionally handled by Documents.php because the active RMS
     * filename/subfolder tables do not contain a reliable ownership column.
     */
    public function set_publish_many($level, $record_ids, $publish)
    {
        $level = $this->normalize_level($level);

        if (!is_array($record_ids) || empty($record_ids)) {
            return FALSE;
        }

        $clean_ids = array();

        foreach ($record_ids as $record_id) {
            $record_id = (int) $record_id;

            if ($record_id > 0) {
                $clean_ids[] = $record_id;
            }
        }

        $clean_ids = array_values(array_unique($clean_ids));

        if (empty($clean_ids)) {
            return FALSE;
        }

        if ($level === 0) {
            $table = 'filename';
            $id_column = 'file_id';
            $publish_column = 'publish';
            $modified_column = 'date_modified';
        } else {
            $table = 'subfolder' . $level;
            $id_column = 'subfolder' . $level . '_id';
            $publish_column = 'publish' . $level;
            $modified_column = 'date_modified' . $level;
        }

        $data = array(
            $publish_column => (int) $publish === 1 ? 1 : 0,
            $modified_column => date('Y/m/d g:ia')
        );

        $this->db->where_in($id_column, $clean_ids);

        return $this->db->update($table, $data);
    }

    /**
     * Confirm that every requested record is globally unpublished.
     *
     * Level 4 bypasses per-user ownership, but edit/delete operations still
     * retain the RMS safety rule that the target must be unpublished. This
     * helper reads the existing publish/publishN columns; it adds no schema.
     */
    public function records_are_unpublished($level, $record_ids)
    {
        return $this->records_have_publish_status(
            $level,
            $record_ids,
            0
        );
    }

    /**
     * Confirm that every requested record is globally published.
     * Used before assigning a Level 3 owner so one user's existing claim
     * cannot be replaced through a manually submitted Unpublish request.
     */
    public function records_are_published($level, $record_ids)
    {
        return $this->records_have_publish_status(
            $level,
            $record_ids,
            1
        );
    }

    /**
     * Shared validator for the existing publish/publishN columns.
     */
    private function records_have_publish_status(
        $level,
        $record_ids,
        $publish_status
    )
    {
        $level = $this->normalize_level($level);
        $publish_status = (int) $publish_status === 1 ? 1 : 0;

        if (!is_array($record_ids) || empty($record_ids)) {
            return FALSE;
        }

        $clean_ids = array_values(array_unique(array_filter(
            array_map('intval', $record_ids),
            function ($record_id) {
                return $record_id > 0;
            }
        )));

        if (empty($clean_ids)) {
            return FALSE;
        }

        if ($level === 0) {
            $table = 'filename';
            $id_column = 'file_id';
            $publish_column = 'publish';
        } else {
            $table = 'subfolder' . $level;
            $id_column = 'subfolder' . $level . '_id';
            $publish_column = 'publish' . $level;
        }

        $count = $this->db
            ->from($table)
            ->where_in($id_column, $clean_ids)
            ->where($publish_column, $publish_status)
            ->count_all_results();

        return (int) $count === count($clean_ids);
    }

    /**
     * Legacy helper retained for callers that require published-only updates.
     * Level 3 ownership is handled separately by Documents.php; this method
     * changes only the existing shared publish column.
     */
    public function unpublish_published_many($level, $record_ids)
    {
        $level = $this->normalize_level($level);

        if (!is_array($record_ids) || empty($record_ids)) {
            return FALSE;
        }

        $clean_ids = array();

        foreach ($record_ids as $record_id) {
            $record_id = (int) $record_id;

            if ($record_id > 0) {
                $clean_ids[] = $record_id;
            }
        }

        $clean_ids = array_values(array_unique($clean_ids));

        if (empty($clean_ids)) {
            return FALSE;
        }

        if ($level === 0) {
            $table = 'filename';
            $id_column = 'file_id';
            $publish_column = 'publish';
            $modified_column = 'date_modified';
        } else {
            $table = 'subfolder' . $level;
            $id_column = 'subfolder' . $level . '_id';
            $publish_column = 'publish' . $level;
            $modified_column = 'date_modified' . $level;
        }

        $this->db->trans_begin();

        $this->db
            ->where_in($id_column, $clean_ids)
            ->where($publish_column, 1)
            ->update($table, array(
                $publish_column => 0,
                $modified_column => date('Y/m/d g:ia')
            ));

        $updated_count = (int) $this->db->affected_rows();

        if (
            $this->db->trans_status() === FALSE ||
            $updated_count !== count($clean_ids)
        ) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();

        return TRUE;
    }

    /**
     * Get unpublished paths available on the New Documents page.
     *
     * This preserves the existing RMS process:
     * folders must first be unpublished through Manage Documents.
     */
    public function get_upload_paths(
        $owned_records = array(),
        $include_all_unpublished = FALSE
    )
    {
        $paths = array();
        $owned_records = $this->normalize_owned_records(
            $owned_records
        );

        for ($level = 0; $level <= $this->maximum_level; $level++) {
            $rows = $this->get_unpublished_level(
                $level,
                $owned_records,
                $include_all_unpublished
            );

            foreach ($rows as $row) {
                /*
                 * Uploads belong only at the deepest existing record. If a
                 * filename/subfolder has children, the user must continue to
                 * a leaf record before it becomes an upload destination.
                 */
                if (
                    isset($row['child_count']) &&
                    (int) $row['child_count'] > 0
                ) {
                    continue;
                }

                $row['record_level'] =  $level;
                $paths[] = $row;
            }
        }

        return $paths;
    }

    /**
     * Return unpublished records for one hierarchy level.
     */
    private function get_unpublished_level(
        $level,
        $owned_records = array(),
        $include_all_unpublished = FALSE
    ) {
        $level = $this->normalize_level($level);
        $owned_records = $this->normalize_owned_records(
            $owned_records
        );

        /*
         * Level 4 / Super Admin must see every globally unpublished path,
         * including paths unpublished by Level 3 and paths created before the
         * current login session. A valid destination requires the filename
         * and every ancestor through the selected level to be unpublished.
         */
        if ($include_all_unpublished) {
            $this->build_manage_query($level, '', TRUE);
            $this->db->where('filename.publish', 0);

            /* Deleted/inactive legacy rows are never valid subfolder parents. */
            if ($level === 0) {
                $this->db->where('filename.stat', 0);
            } else {
                $this->db->where('subfolder' . $level . '.stat', 0);
            }

            for ($current = 1; $current <= $level; $current++) {
                $this->db->where(
                    'subfolder' . $current . '.publish' . $current,
                    0
                );
            }

            if ($level === 0) {
                $this->db->order_by('filename.filename', 'ASC');
            } else {
                $this->db->order_by(
                    'subfolder' . $level . '.subfolder' . $level . '_name',
                    'ASC'
                );
            }

            return $this->db->get()->result_array();
        }

        /*
         * A Level 3 destination belongs to the user who unpublished that
         * exact filename/subfolder record. Requiring ownership of every
         * ancestor incorrectly hid a newly unpublished destination.
         */
        if (empty($owned_records[$level])) {
            return array();
        }

        $this->build_manage_query($level, '', TRUE);

        /* Filter the exact destination; parent joins only build its label. */
        if ($level === 0) {
            $this->db->where_in(
                'filename.file_id',
                $owned_records[0]
            );
            $this->db->where('filename.publish', 0);
            $this->db->where('filename.stat', 0);
        } else {
            $this->db->where_in(
                'subfolder' . $level . '.subfolder' . $level . '_id',
                $owned_records[$level]
            );
            $this->db->where(
                'subfolder' . $level . '.publish' . $level,
                0
            );
            $this->db->where('subfolder' . $level . '.stat', 0);
        }

        if ($level === 0) {
            $this->db->order_by('filename.filename', 'ASC');
        } else {
            $this->db->order_by(
                'subfolder' . $level . '.subfolder' . $level . '_name',
                'ASC'
            );
        }

        return $this->db->get()->result_array();
    }

    /**
     * Save an uploaded document record in the existing data table.
     *
     * No database field is added or changed.
     */
    public function create_document($data_name, $page_no, $path_ids)
    {
        if (!is_array($path_ids)) {
            return FALSE;
        }

        $insert = array(
            'data_name' => (string) $data_name,
            'page_no' => (int) $page_no,
            'date_uploaded' => date('Y/m/d g:ia'),
            'stat' => 0,
            'file_id' => isset($path_ids['file_id'])
                ? (int) $path_ids['file_id']
                : 0
        );

        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $column = 'subfolder' . $level . '_id';

            $insert[$column] = isset($path_ids[$column])
                ? (int) $path_ids[$column]
                : 0;
        }

        return $this->db->insert('data', $insert);
    }
    /**
     * Save the watermarked/viewer copy in the existing data_f table.
     *
     * The legacy data_f schema uses an "f" suffix for every document and
     * subfolder field except file_id. These names intentionally differ from
     * the original-document columns in data.
     */
    public function create_view_document($data_name, $page_no, $path_ids)
    {
        if (!is_array($path_ids)) {
            return FALSE;
        }

        $insert = array(
            'data_namef' => (string) $data_name,
            'page_nof' => (int) $page_no,
            'date_uploadedf' => date('Y/m/d g:ia'),
            'statf' => 0,
            'file_id' => isset($path_ids['file_id'])
                ? (int) $path_ids['file_id']
                : 0
        );

        for ($level = 1; $level <= $this->maximum_level; $level++) {
            $source_column = 'subfolder' . $level . '_id';
            $target_column = $source_column . 'f';

            $insert[$target_column] = isset($path_ids[$source_column])
                ? (int) $path_ids[$source_column]
                : 0;
        }

        return $this->db->insert('data_f', $insert);
    }

    /**
     * Update the exact filename/subfolder that received uploaded documents.
     *
     * The legacy RMS upload methods update date_modified/date_modifiedN after
     * inserting data and data_f records. Keeping the update here preserves that
     * behavior without changing the database structure.
     */
    public function touch_record($level, $record_id)
    {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;

        if ($record_id <= 0) {
            return FALSE;
        }

        if ($level === 0) {
            $table = 'filename';
            $id_column = 'file_id';
            $modified_column = 'date_modified';
        } else {
            $table = 'subfolder' . $level;
            $id_column = 'subfolder' . $level . '_id';
            $modified_column = 'date_modified' . $level;
        }

        return $this->db
            ->where($id_column, $record_id)
            ->update($table, array(
                $modified_column => date('Y/m/d g:ia')
            ));
    }

    /**
     * Resolve and validate one unpublished upload destination.
     */
    public function get_upload_path(
        $level,
        $record_id,
        $owned_records = array(),
        $include_all_unpublished = FALSE,
        $require_leaf = FALSE
    ) {
        $level = $this->normalize_level($level);
        $record_id = (int) $record_id;

        if ($record_id <= 0) {
            return FALSE;
        }

        $paths = $this->get_unpublished_level(
            $level,
            $owned_records,
            $include_all_unpublished
        );

        foreach ($paths as $path) {
            if ((int) $path['record_id'] === $record_id) {
                if (
                    $require_leaf &&
                    isset($path['child_count']) &&
                    (int) $path['child_count'] > 0
                ) {
                    return FALSE;
                }

                $path['record_level'] = $level;

                return $path;
            }
        }

        return FALSE;
    }

    /**
     * Return an existing RMS system-setting value.
     */
    public function get_system_setting($setting_id)
    {
        $setting_id = (int) $setting_id;

        if ($setting_id <= 0) {
            return FALSE;
        }

        $row = $this->db
            ->select('value')
            ->from('system_setting')
            ->where('setting_id', $setting_id)
            ->get()
            ->row_array();

        return $row && isset($row['value'])
            ? $row['value']
            : FALSE;
    }

    /**
     * Return active subsidiaries for the Add Filename modal.
     */
    public function get_subsidiaries()
    {
        return $this->db
            ->select('sub_id, sub_name')
            ->from('subsidiaries')
            ->order_by('sub_name', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Return departments, optionally limited to one subsidiary.
     */
    public function get_departments($sub_id = 0)
    {
        $sub_id = (int) $sub_id;

        $this->db
            ->select('dept_id, dept_name, sub_id')
            ->from('departments');

        if ($sub_id > 0) {
            $this->db->where('sub_id', $sub_id);
        }

        return $this->db
            ->order_by('dept_name', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Return all valid parent paths for the Add Subfolder modal.
     *
     * A new subfolder can be created under:
     * Filename through Subfolder9.
     *
     * Subfolder10 cannot be used as a parent because it is the
     * maximum hierarchy level.
     */
    public function get_subfolder_parent_paths(
        $owned_records = array(),
        $include_all_unpublished = FALSE
    )
    {
        $paths = array();
        $owned_records = $this->normalize_owned_records(
            $owned_records
        );

        for ($level = 0; $level < $this->maximum_level; $level++) {
            /*
         * Use the same eligibility rule as document uploads. Level 3 must
         * own the exact unpublished parent; Level 4 receives all valid
         * globally unpublished paths through include_all_unpublished.
         */
            $rows = $this->get_unpublished_level(
                $level,
                $owned_records,
                $include_all_unpublished
            );

            foreach ($rows as $row) {
                $row['record_level'] = $level;
                $paths[] = $row;
            }
        }

        return $paths;
    }

    /**
     * Check whether a filename already exists under the selected
     * subsidiary and department.
     */
    public function filename_exists($sub_id, $dept_id, $filename)
    {
        return $this->db
            ->from('filename')
            ->where('sub_id', (int) $sub_id)
            ->where('dept_id', (int) $dept_id)
            ->where('filename', trim((string) $filename))
            ->count_all_results() > 0;
    }

    /**
     * Create a main filename using the existing RMS table structure.
     */
    public function create_filename(
        $sub_id,
        $dept_id,
        $filename,
        $user_id
    ) {
        $date = date('Y/m/d g:ia');

        $insert = array(
            'sub_id' => (int) $sub_id,
            'dept_id' => (int) $dept_id,
            'filename' => trim((string) $filename),
            'date_created' => $date,
            'date_modified' => $date,
            'publish' => 0,
            'stat' => 0
        );

        if (!$this->db->insert('filename', $insert)) {
            return FALSE;
        }

        /*
     * The controller uses the new ID to claim this unpublished filename
     * for its creator's current login session. No database column is added.
     */
        return (int) $this->db->insert_id();
    }

    /**
     * Check whether a subfolder name already exists under its
     * selected parent.
     */
    public function subfolder_exists(
        $parent_level,
        $parent_id,
        $subfolder_name
    ) {
        $parent_level = $this->normalize_level($parent_level);
        $child_level = $parent_level + 1;
        $parent_id = (int) $parent_id;

        if (
            $child_level < 1 ||
            $child_level > $this->maximum_level ||
            $parent_id <= 0
        ) {
            return TRUE;
        }

        $table = 'subfolder' . $child_level;
        $name_column = 'subfolder' . $child_level . '_name';

        $parent_column = $parent_level === 0
            ? 'file_id'
            : 'subfolder' . $parent_level . '_id';

        return $this->db
            ->from($table)
            ->where($parent_column, $parent_id)
            ->where($name_column, trim((string) $subfolder_name))
            ->count_all_results() > 0;
    }

    /**
     * Create the next subfolder level under the selected parent.
     *
     * Filename    -> Subfolder1
     * Subfolder1  -> Subfolder2
     * ...
     * Subfolder9  -> Subfolder10
     */
    public function create_subfolder(
        $parent_level,
        $parent_id,
        $subfolder_name,
        $user_id
    ) {
        $parent_level = $this->normalize_level($parent_level);
        $child_level = $parent_level + 1;
        $parent_id = (int) $parent_id;

        if (
            $child_level < 1 ||
            $child_level > $this->maximum_level ||
            $parent_id <= 0
        ) {
            return FALSE;
        }

        $table = 'subfolder' . $child_level;
        $name_column = 'subfolder' . $child_level . '_name';

        $parent_column = $parent_level === 0
            ? 'file_id'
            : 'subfolder' . $parent_level . '_id';

        $insert = array(
            $parent_column => $parent_id,
            $name_column => trim((string) $subfolder_name),
            'date_created' . $child_level => date('Y/m/d g:ia'),
            'date_modified' . $child_level => date('Y/m/d g:ia'),
            'publish' . $child_level => 0,
            'stat' => 0
        );

        if (!$this->db->insert($table, $insert)) {
            return FALSE;
        }

        /*
     * Return the child ID so Documents.php can make the new unpublished
     * subfolder available only to the logged-in user who created it.
     */
        return (int) $this->db->insert_id();
    }
    /**
     * Build the query used by Manage Documents.
     */
    private function build_manage_query(
        $level,
        $search,
        $with_select,
        $parent_id = 0
    ) {
        $level = $this->normalize_level($level);
        $search = trim((string) $search);
        $parent_id = (int) $parent_id;

        if ($with_select) {
            if ($level === 0) {
                $this->db->select(
                    'filename.file_id AS record_id,
                     filename.file_id,
                     filename.filename AS record_name,
                     filename.filename,
                     filename.sub_id,
                     filename.dept_id,
                     filename.date_created,
                     filename.date_modified,
                     filename.publish AS publish_status,
                     filename.publish AS filename_publish_status,
                     filename.publish,
                     filename.stat,
                     subsidiaries.sub_name,
                     departments.dept_name'
                );
            } else {
                $table = 'subfolder' . $level;

                $this->db->select(
                    $table . '.subfolder' . $level . '_id AS record_id,
                     ' . $table . '.subfolder' . $level . '_id,
                     ' . $table . '.subfolder' . $level . '_name AS record_name,
                     ' . $table . '.subfolder' . $level . '_name,
                     ' . $table . '.date_created' . $level . ' AS date_created,
                     ' . $table . '.date_modified' . $level . ' AS date_modified,
                     ' . $table . '.publish' . $level . ' AS publish_status,
                     ' . $table . '.publish' . $level . ',
                     ' . $table . '.stat,
                     filename.file_id,
                     filename.filename,
                     filename.publish AS filename_publish_status,
                     filename.sub_id,
                     filename.dept_id,
                     subsidiaries.sub_name,
                     departments.dept_name'
                );

                for ($current = 1; $current < $level; $current++) {
                    $this->db->select(
                        'subfolder' . $current . '.subfolder' .
                            $current . '_id,
                         subfolder' . $current . '.subfolder' .
                            $current . '_name,
                         subfolder' . $current . '.publish' . $current .
                            ' AS subfolder' . $current . '_publish_status'
                    );
                }
            }

            if ($level < $this->maximum_level) {
                $child_table = 'subfolder' . ($level + 1);

                if ($level === 0) {
                    $parent_column = 'file_id';
                    $current_id = 'filename.file_id';
                } else {
                    $parent_column = 'subfolder' . $level . '_id';
                    $current_id = 'subfolder' . $level .
                        '.subfolder' . $level . '_id';
                }

                $this->db->select(
                    '(SELECT COUNT(*) FROM ' . $child_table .
                        ' AS child_records WHERE child_records.' .
                        $parent_column . ' = ' . $current_id .
                        ') AS child_count',
                    FALSE
                );
            } else {
                $this->db->select('0 AS child_count', FALSE);
            }
        }

        if ($level === 0) {
            $this->db->from('filename');
        } else {
            $this->db->from('subfolder' . $level);

            /*
             * Join backwards from the selected subfolder level
             * until reaching the filename table.
             */
            for ($current = $level; $current >= 2; $current--) {
                $child = 'subfolder' . $current;
                $parent = 'subfolder' . ($current - 1);

                $this->db->join(
                    $parent,
                    $child . '.subfolder' . ($current - 1) . '_id = ' .
                        $parent . '.subfolder' . ($current - 1) . '_id',
                    'inner'
                );
            }

            $this->db->join(
                'filename',
                'subfolder1.file_id = filename.file_id',
                'inner'
            );
        }

        $this->db->join(
            'subsidiaries',
            'filename.sub_id = subsidiaries.sub_id',
            'inner'
        );

        $this->db->join(
            'departments',
            'filename.dept_id = departments.dept_id',
            'inner'
        );

        if ($level > 0 && $parent_id > 0) {
            $parent_column = $level === 1
                ? 'file_id'
                : 'subfolder' . ($level - 1) . '_id';

            $this->db->where(
                'subfolder' . $level . '.' . $parent_column,
                $parent_id
            );
        }

        if ($search !== '') {
            $this->db->group_start();

            if ($level === 0) {
                $this->db->like('filename.filename', $search);
            } else {
                $this->db->like(
                    'subfolder' . $level . '.subfolder' .
                        $level . '_name',
                    $search
                );

                $this->db->or_like('filename.filename', $search);
            }

            $this->db->or_like('subsidiaries.sub_name', $search);
            $this->db->or_like('departments.dept_name', $search);
            $this->db->group_end();
        }
    }

    /**
     * Ensure the hierarchy level is between 0 and 10.
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
     * Normalize the controller's persistent per-account ownership map.
     *
     * Expected shape:
     * array(
     *     0 => array(filename IDs),
     *     1 => array(subfolder1 IDs),
     *     ...
     *     10 => array(subfolder10 IDs)
     * )
     */
    private function normalize_owned_records($owned_records)
    {
        $normalized = array();

        for ($level = 0; $level <= $this->maximum_level; $level++) {
            $normalized[$level] = array();

            if (
                !is_array($owned_records) ||
                !isset($owned_records[$level]) ||
                !is_array($owned_records[$level])
            ) {
                continue;
            }

            foreach ($owned_records[$level] as $record_id) {
                $record_id = (int) $record_id;

                if ($record_id > 0) {
                    $normalized[$level][] = $record_id;
                }
            }

            $normalized[$level] = array_values(
                array_unique($normalized[$level])
            );
        }

        return $normalized;
    }
    private function documents_storage_roots()
    {
        return array(
            '/var/www/html/rms/administrator/agc_data',
            '/var/www/html/rms/data'
        );
    }
}
