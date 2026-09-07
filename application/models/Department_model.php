<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Department_model extends CI_Model
{
    private $table = 'departments';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Count departments for pagination.
     */
    public function count_all($search = '')
    {
        $this->db->from($this->table);
        $this->db->join(
            'subsidiaries',
            'subsidiaries.sub_id = departments.sub_id',
            'left'
        );

        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('departments.dept_name', $search);
            $this->db->or_like('subsidiaries.sub_name', $search);
            $this->db->group_end();
        }

        return (int) $this->db->count_all_results();
    }

    /**
     * Get departments with search and pagination.
     */
    public function get_all($limit = 10, $offset = 0, $search = '', $sort = 'name', $order = 'asc')
    {
        $this->db->select(
            'departments.dept_id,
             departments.sub_id,
             departments.dept_name,
             subsidiaries.sub_name'
        );

        $this->db->from($this->table);
        $this->db->join(
            'subsidiaries',
            'subsidiaries.sub_id = departments.sub_id',
            'left'
        );

        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('departments.dept_name', $search);
            $this->db->or_like('subsidiaries.sub_name', $search);
            $this->db->group_end();
        }

        /* Map public sort keys to trusted database columns. */
        $sort_columns = array(
            'name' => 'departments.dept_name',
            'subsidiary' => 'subsidiaries.sub_name',
            'id' => 'departments.dept_id'
        );
        $sort_column = isset($sort_columns[$sort])
            ? $sort_columns[$sort]
            : $sort_columns['name'];
        $sort_direction = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($sort_column, $sort_direction);
        $this->db->order_by('departments.dept_name', 'ASC');

        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    /**
     * Find one department.
     */
    public function find($dept_id)
    {
        $this->db->select(
            'departments.dept_id,
             departments.sub_id,
             departments.dept_name,
             subsidiaries.sub_name'
        );

        $this->db->from($this->table);
        $this->db->join(
            'subsidiaries',
            'subsidiaries.sub_id = departments.sub_id',
            'left'
        );

        $this->db->where('departments.dept_id', (int) $dept_id);

        $row = $this->db->get()->row_array();

        return $row ? $row : FALSE;
    }

    /**
     * Check duplicate department name within a subsidiary.
     */
    public function name_exists($dept_name, $sub_id, $exclude_dept_id = 0)
    {
        /* Compare normalized names within one subsidiary to prevent duplicates. */
        $this->db->from($this->table);
        $this->db->where('sub_id', (int) $sub_id);
        $this->db->where(
            'LOWER(dept_name) = '.$this->db->escape(strtolower(trim($dept_name))),
            NULL,
            FALSE
        );

        if ((int) $exclude_dept_id > 0) {
            $this->db->where(
                'dept_id !=',
                (int) $exclude_dept_id
            );
        }

        return $this->db->count_all_results() > 0;
    }

    /**
     * Create a department.
     */
    public function create($sub_id, $dept_name)
    {
        /* Return the inserted identifier so creation can be verified. */
        $saved = $this->db->insert($this->table, array(
            'sub_id'    => (int) $sub_id,
            'dept_name' => trim($dept_name)
        ));

        return $saved ? (int) $this->db->insert_id() : FALSE;
    }

    /**
     * Update a department.
     */
    public function update($dept_id, $sub_id, $dept_name)
    {
        $this->db->where('dept_id', (int) $dept_id);

        return $this->db->update($this->table, array(
            'sub_id'    => (int) $sub_id,
            'dept_name' => trim($dept_name)
        ));
    }

    /**
     * Count records that reference this department.
     *
     * Add more related tables here only if they exist in your database.
     */
    public function related_counts($dept_id)
    {
        $counts = array(
            'users' => 0
        );

        if ($this->db->table_exists('users') &&
            $this->db->field_exists('dept_id', 'users')) {
            $counts['users'] = (int) $this->db
                ->where('dept_id', (int) $dept_id)
                ->count_all_results('users');
        }

        return $counts;
    }

    /**
     * Delete a department.
     */
    public function delete($dept_id)
    {
        $this->db->where('dept_id', (int) $dept_id);

        return $this->db->delete($this->table);
    }
}
