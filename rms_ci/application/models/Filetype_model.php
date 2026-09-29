<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Database operations for the legacy filetypes table.
 */
class Filetype_model extends CI_Model
{
    /**
     * Return all file types in their original identifier order.
     *
     * @return array
     */
    public function get_all()
    {
        return $this->db
            ->order_by('filetype_id', 'ASC')
            ->get('filetypes')
            ->result_array();
    }

    /**
     * Find one file type by its primary key.
     *
     * @param int $id
     * @return array|null
     */
    public function find($id)
    {
        $row = $this->db
            ->where('filetype_id', (int) $id)
            ->limit(1)
            ->get('filetypes')
            ->row_array();

        return $row ? $row : NULL;
    }

    /**
     * Check for another row containing the same extension.
     *
     * @param string $type
     * @param int    $ignore_id
     * @return bool
     */
    public function exists($type, $ignore_id)
    {
        // The legacy MySQL table uses a case-insensitive collation.
        $this->db->where('type', strtolower($type));

        if ((int) $ignore_id > 0) {
            $this->db->where('filetype_id !=', (int) $ignore_id);
        }

        return $this->db->count_all_results('filetypes') > 0;
    }

    /**
     * Insert an enabled file type, matching legacy stat value 0.
     *
     * @param string $type
     * @return bool
     */
    public function create($type)
    {
        return (bool) $this->db->insert('filetypes', array(
            'type' => $type,
            'image' => '',
            'stat' => 0
        ));
    }

    /**
     * Rename an existing file type.
     *
     * @param int    $id
     * @param string $type
     * @return bool
     */
    public function update_type($id, $type)
    {
        return (bool) $this->db
            ->where('filetype_id', (int) $id)
            ->update('filetypes', array('type' => $type));
    }

    /**
     * Apply the legacy enabled/disabled value to selected rows.
     *
     * @param array $ids
     * @param int   $status
     * @return bool
     */
    public function set_status($ids, $status)
    {
        $this->db->trans_begin();
        $this->db->where_in('filetype_id', $ids)->update('filetypes', array('stat' => (int) $status));

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return TRUE;
    }

    /**
     * Delete selected file type records in one transaction.
     *
     * @param array $ids
     * @return bool
     */
    public function delete_many($ids)
    {
        $this->db->trans_begin();
        $this->db->where_in('filetype_id', $ids)->delete('filetypes');

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return TRUE;
    }
}
