<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class System_setting_model extends CI_Model
{
    /**
     * Return all legacy system settings in their original display order.
     *
     * @return array
     */
    public function get_all()
    {
        return $this->db
            ->order_by('setting_id', 'ASC')
            ->get('system_setting')
            ->result_array();
    }

    /**
     * Index settings by ID so the controller can validate submitted IDs.
     *
     * @return array
     */
    public function get_all_indexed()
    {
        $indexed = array();
        foreach ($this->get_all() as $row) {
            $indexed[(int) $row['setting_id']] = $row;
        }

        return $indexed;
    }

    /**
     * Update every setting inside one transaction.
     *
     * @param array $updates Setting ID => new value.
     * @return bool
     */
    public function update_values($updates)
    {
        $this->db->trans_begin();

        foreach ($updates as $id => $value) {
            $this->db
                ->where('setting_id', (int) $id)
                ->update('system_setting', array('value' => $value));

            // Roll back immediately when any individual update fails.
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }
        }

        $this->db->trans_commit();
        return TRUE;
    }
}
