<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Subsidiary_model extends CI_Model
{
    private $table = 'subsidiaries';

    public function count_all($search)
    {
        if ($search !== '') {
            $this->db->like('sub_name', $search);
        }
        return (int) $this->db->count_all_results($this->table);
    }

    /*
     * Keep sorting optional for compatibility with Departments.php, which
     * uses the older three-argument call when loading subsidiary choices.
     */
    public function get_all($limit, $offset, $search, $sort = 'name', $order = 'asc')
    {
        $this->db->select('sub_id, sub_name');
        $this->db->from($this->table);
        if ($search !== '') {
            $this->db->like('sub_name', $search);
        }
        /* Map URL choices to known columns; never pass raw input to ORDER BY. */
        $sort_column = $sort === 'id' ? 'sub_id' : 'sub_name';
        $sort_direction = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($sort_column, $sort_direction);
        $this->db->limit((int) $limit, (int) $offset);
        return $this->db->get()->result_array();
    }

    public function find($sub_id)
    {
        return $this->db
            ->select('sub_id, sub_name')
            ->where('sub_id', (int) $sub_id)
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    public function name_exists($sub_name, $exclude_id)
    {
        $this->db->from($this->table);
        $this->db->where('sub_name', trim($sub_name));
        if ((int) $exclude_id > 0) {
            $this->db->where('sub_id !=', (int) $exclude_id);
        }
        return $this->db->count_all_results() > 0;
    }

    public function create($sub_name)
    {
        return $this->db->insert($this->table, array('sub_name' => trim($sub_name)));
    }

    public function update($sub_id, $sub_name)
    {
        $this->db->where('sub_id', (int) $sub_id);
        return $this->db->update($this->table, array('sub_name' => trim($sub_name)));
    }

    public function related_counts($sub_id)
    {
        $counts = array('departments' => 0, 'users' => 0);
        if ($this->db->table_exists('departments') && $this->db->field_exists('sub_id', 'departments')) {
            $counts['departments'] = (int) $this->db->where('sub_id', (int) $sub_id)
                ->count_all_results('departments');
        }
        if ($this->db->table_exists('users') && $this->db->field_exists('sub_id', 'users')) {
            $counts['users'] = (int) $this->db->where('sub_id', (int) $sub_id)
                ->count_all_results('users');
        }
        return $counts;
    }

    public function delete($sub_id)
    {
        $this->db->where('sub_id', (int) $sub_id);
        return $this->db->delete($this->table);
    }

    public function storage_roots()
    {
        $roots = array();
        if (!$this->db->table_exists('system_setting')) {
            return $roots;
        }
        $rows = $this->db->select('setting_id, value')
            ->where_in('setting_id', array(1, 7))->get('system_setting')->result_array();
        foreach ($rows as $row) {
            $roots[(int) $row['setting_id']] = rtrim((string) $row['value'], '/\\');
        }
        return $roots;
    }
}
