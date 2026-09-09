<?php
/*
 * User model
 *
 * Purpose:
 * - Contains every database query used by the Users list, account forms, and access tree.
 * - Uses CI3 Query Builder so values are escaped and queries remain readable.
 * - Preserves the old RMS tables and business rules without schema changes.
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    /**
     * Adds the same INNER JOIN chain used by the old Manage Users page.
     *
     * INNER JOIN intentionally lists only users that have matching role,
     * department, and subsidiary records.
     */
    private function add_joins()
    {
        $this->db
            ->from('users')
            ->join('user_role', 'users.role_id = user_role.role_id', 'inner')
            ->join('departments', 'users.dept_id = departments.dept_id', 'inner')
            ->join('subsidiaries', 'departments.sub_id = subsidiaries.sub_id', 'inner');
    }

    /**
     * Adds grouped LIKE conditions to the current list/count query.
     *
     * This is read-only filtering. Query Builder escapes the search value.
     *
     * @param string $search Text entered in the Users search field.
     */
    private function add_search($search)
    {
        if ($search === '') {
            return;
        }

        $this->db
            ->group_start()
            ->like('users.username', $search)
            ->or_like('users.emp_name', $search)
            ->or_like('subsidiaries.sub_name', $search)
            ->or_like('departments.dept_name', $search)
            ->or_like('user_role.title', $search)
            ->or_like('users.date_registered', $search)
            ->or_like('users.last_date_visit', $search)
            ->group_end();
    }

    /**
     * Maps a public sort name to a fixed database column.
     *
     * A fixed map prevents arbitrary column or SQL text from being used in
     * ORDER BY.
     *
     * @param string $sort Requested sort key.
     * @return string Safe qualified database column.
     */
    private function sort_column($sort)
    {
        $columns = array(
            'username'   => 'users.username',
            'emp_name'   => 'users.emp_name',
            'sub_name'   => 'subsidiaries.sub_name',
            'dept_name'  => 'departments.dept_name',
            'role'       => 'user_role.title',
            'status'     => 'users.stat',
            'uploader'   => 'users.allowed_upload',
            'registered' => 'users.date_registered',
            'last_visit' => 'users.last_date_visit'
        );

        return isset($columns[$sort]) ? $columns[$sort] : 'users.emp_name';
    }

    /**
     * Read-only query: counts users matching the old join and current search.
     *
     * Tables read: users, user_role, departments, subsidiaries.
     *
     * @param string $search
     * @return int
     */
    public function count_users($search)
    {
        $this->add_joins();
        $this->add_search($search);

        return (int) $this->db->count_all_results();
    }

    /**
     * Read-only query: returns one paginated page of Manage Users records.
     *
     * The password column is deliberately never selected.
     */
    public function get_users($limit, $offset, $search, $sort, $direction)
    {
        /*
         * This module performs a read-only SELECT against the same four tables
         * used by the old Manage Users page.
         * The password column is intentionally excluded.
         */
        $this->db->select(
            'users.user_id, users.username, users.emp_name, users.role_id, '.
            'users.stat, users.allowed_upload, users.date_registered, '.
            'users.last_date_visit, user_role.title AS role_title, '.
            'departments.dept_name, subsidiaries.sub_name'
        );
        $this->add_joins();
        $this->add_search($search);

        return $this->db
            ->order_by($this->sort_column($sort), $direction === 'desc' ? 'DESC' : 'ASC')
            ->order_by('users.user_id', 'ASC')
            ->limit((int) $limit, (int) $offset)
            ->get()
            ->result_array();
    }

    /**
     * Read-only query: gets user levels that the logged-in manager may assign.
     *
     * Super User role 1 is never assignable. A non-Super User also cannot
     * create another account with the same manager role.
     */
    public function get_assignable_roles($creator_role)
    {
        $this->db
            ->select('role_id, title')
            ->from('user_role')
            ->where('role_id !=', 1);

        if ((int) $creator_role !== 1) {
            $this->db->where('role_id !=', (int) $creator_role);
        }

        return $this->db
            ->order_by('role_id', 'DESC')
            ->get()
            ->result_array();
    }

    /**
     * Read-only query: supplies the Subsidiary dropdown.
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
     * Read-only query: supplies departments and their subsidiary relationship.
     */
    public function get_departments()
    {
        return $this->db
            ->select('dept_id, dept_name, sub_id')
            ->from('departments')
            ->order_by('dept_name', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Read-only validation query: confirms a department belongs to a subsidiary.
     */
    public function department_belongs_to_subsidiary($department_id, $subsidiary_id)
    {
        return $this->db
            ->where('dept_id', (int) $department_id)
            ->where('sub_id', (int) $subsidiary_id)
            ->count_all_results('departments') === 1;
    }

    /**
     * Checks the selected role against the already-filtered assignable roles.
     */
    public function role_is_assignable($role_id, $creator_role)
    {
        $assignable_roles = $this->get_assignable_roles($creator_role);

        foreach ($assignable_roles as $role) {
            if ((int) $role['role_id'] === (int) $role_id) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Read-only duplicate query: checks whether a username already exists.
     */
    public function username_exists($username, $exclude_user_id = 0)
    {
        $this->db->where('username', $username);

        // Edit User excludes its own row so an unchanged username is valid.
        if ((int) $exclude_user_id > 0) {
            $this->db->where('user_id !=', (int) $exclude_user_id);
        }

        return $this->db->count_all_results('users') > 0;
    }

    /**
     * Read-only duplicate query: checks whether an employee name already exists.
     */
    public function employee_name_exists($employee_name, $exclude_user_id = 0)
    {
        $this->db->where('emp_name', $employee_name);

        // Edit User excludes its own row so an unchanged name is valid.
        if ((int) $exclude_user_id > 0) {
            $this->db->where('user_id !=', (int) $exclude_user_id);
        }

        return $this->db->count_all_results('users') > 0;
    }

    /**
     * Creates exactly one new user after controller validation.
     *
     * Queries:
     * - SELECT department/subsidiary: obtains the old location value.
     * - SELECT role: obtains the compatible position value.
     * - INSERT users: writes one account inside a transaction.
     *
     * No existing row and no table structure is changed.
     *
     * @param array $data Validated account fields.
     * @return int|false New user ID on success; FALSE on failure.
     */
    public function create_user($data)
    {
        // Read the selected department and subsidiary used to populate location.
        $department = $this->db
            ->select('departments.dept_id, departments.dept_name, subsidiaries.sub_name')
            ->from('departments')
            ->join('subsidiaries', 'departments.sub_id = subsidiaries.sub_id', 'inner')
            ->where('departments.dept_id', (int) $data['dept_id'])
            ->limit(1)
            ->get()
            ->row_array();

        // Read the selected role title used to populate the required position field.
        $role = $this->db
            ->select('title')
            ->where('role_id', (int) $data['role_id'])
            ->limit(1)
            ->get('user_role')
            ->row_array();

        // Stop before INSERT if either selected reference no longer exists.
        if (!$department || !$role) {
            return FALSE;
        }

        /*
         * The first ten values preserve the old addUser() behavior. The
         * remaining values satisfy the extra required columns already present
         * in the current users table; no schema change is performed.
         */
        $record = array(
            'username'        => $data['username'],
            'password'        => $data['password'],
            'emp_name'        => $data['emp_name'],
            'role_id'         => (int) $data['role_id'],
            'dept_id'         => (int) $data['dept_id'],
            'location'        => $department['sub_name'],
            'emp_id'          => '',
            'position'        => $role['title'],
            'employee_status' => 'Active',
            'profile_pic'     => '',
            'date_registered' => $data['date_registered'],
            'last_date_visit' => $data['last_date_visit'],
            'stat'            => (int) $data['stat'],
            'conf_stat'       => (int) $data['conf_stat'],
            'allowed_upload'  => (int) $data['allowed_upload']
        );

        /*
         * Start a transaction so the INSERT either completes successfully or
         * is treated as failed. This writes only one row to the users table.
         */
        $this->db->trans_start();
        $this->db->insert('users', $record);
        $new_user_id = (int) $this->db->insert_id();
        $this->db->trans_complete();

        // Return FALSE when the transaction failed or no new primary key was created.
        if ($this->db->trans_status() === FALSE || $new_user_id < 1) {
            return FALSE;
        }

        return $new_user_id;
    }

    /**
     * Read-only query: loads one account for the Edit User modal.
     *
     * The password column is deliberately excluded. The department join also
     * supplies the existing subsidiary selection without changing any data.
     *
     * @param int $user_id Existing users.user_id value.
     * @return array Empty when the user or related lookup records do not exist.
     */
    public function get_user_for_edit($user_id)
    {
        return $this->db
            ->select(
                'users.user_id, users.username, users.emp_name, users.role_id, '.
                'users.dept_id, users.allowed_upload, users.stat, departments.sub_id'
            )
            ->from('users')
            ->join('departments', 'users.dept_id = departments.dept_id', 'inner')
            ->where('users.user_id', (int) $user_id)
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Loads the signed-in user's complete profile without selecting a password.
     *
     * @param int $user_id Signed-in users.user_id value.
     * @return array Empty when the account or related lookup rows are missing.
     */
    public function get_profile($user_id)
    {
        return $this->db
            ->select(
                'users.user_id, users.username, users.emp_name, users.date_registered, '.
                'users.last_date_visit, users.allowed_upload, user_role.title AS role_title, '.
                'departments.dept_name, subsidiaries.sub_name'
            )
            ->from('users')
            ->join('user_role', 'users.role_id = user_role.role_id', 'inner')
            ->join('departments', 'users.dept_id = departments.dept_id', 'inner')
            ->join('subsidiaries', 'departments.sub_id = subsidiaries.sub_id', 'inner')
            ->where('users.user_id', (int) $user_id)
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Updates only the profile fields the signed-in user is allowed to change.
     *
     * @param int   $user_id Signed-in users.user_id value.
     * @param array $data Validated editable profile fields.
     * @return bool TRUE when the transaction succeeds.
     */
    public function update_profile($user_id, $data)
    {
        $record = array(
            'username' => $data['username'],
            'emp_name' => $data['emp_name']
        );

        // A missing password key means the current password remains unchanged.
        if (isset($data['password']) && $data['password'] !== '') {
            $record['password'] = $data['password'];
        }

        $this->db->trans_start();
        $this->db
            ->where('user_id', (int) $user_id)
            ->limit(1)
            ->update('users', $record);
        $this->db->trans_complete();

        return $this->db->trans_status() !== FALSE;
    }

    /**
     * Applies the account protection rules before an Edit User UPDATE.
     *
     * - Nobody edits their currently signed-in account through Manage Users.
     * - Super User accounts remain protected.
     * - Administrators may edit only lower user levels.
     *
     * @param array $user Target record returned by get_user_for_edit().
     * @param int $current_user_id Signed-in users.user_id.
     * @param int $manager_role Signed-in users.role_id.
     * @return bool
     */
    public function user_is_manageable($user, $current_user_id, $manager_role)
    {
        if (!$user || (int) $user['user_id'] === (int) $current_user_id) {
            return FALSE;
        }

        if ((int) $user['role_id'] === 1) {
            return FALSE;
        }

        return (int) $manager_role === 1
            || (int) $user['role_id'] !== (int) $manager_role;
    }

    /**
     * Deletes one user and the account's file-access rows atomically.
     *
     * The controller applies all account-protection checks first. Removing
     * user_allowed_data before users preserves compatibility whether or not
     * the old database has a foreign-key constraint on that relationship.
     *
     * @param int $user_id Existing users.user_id value.
     * @return bool TRUE only when exactly one users row was deleted.
     */
    public function delete_user($user_id)
    {
        $deleted_users = 0;

        $this->db->trans_begin();

        if (!$this->db->where('user_id', (int) $user_id)->delete('user_allowed_data')) {
            $this->db->trans_rollback();
            return FALSE;
        }

        if (!$this->db
            ->where('user_id', (int) $user_id)
            ->limit(1)
            ->delete('users')) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $deleted_users = (int) $this->db->affected_rows();

        if ($deleted_users !== 1 || $this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return TRUE;
    }

    /**
     * Blocks or unblocks exactly one existing account.
     *
     * This preserves the old RMS status meanings:
     * - 2 means Blocked.
     * - 0 means Offline and is used when access is restored.
     *
     * The controller applies all protected-account checks before this UPDATE.
     * No other users column, record, or table is changed.
     *
     * @param int $user_id Existing users.user_id value.
     * @param int $status Either 0 (unblocked/offline) or 2 (blocked).
     * @return bool TRUE when the transaction succeeds.
     */
    public function set_block_status($user_id, $status)
    {
        $safe_status = (int) $status === 2 ? 2 : 0;

        $this->db->trans_start();
        $this->db
            ->where('user_id', (int) $user_id)
            ->limit(1)
            ->update('users', array('stat' => $safe_status));
        $this->db->trans_complete();

        return $this->db->trans_status() !== FALSE;
    }

    /**
     * Marks one online account for forced logout using the old RMS status 3.
     *
     * The user's existing session detects this value and ends on its next
     * request. Only users.stat is updated; permissions and profile data remain
     * unchanged.
     *
     * @param int $user_id Existing users.user_id value.
     * @return bool TRUE when the transaction succeeds.
     */
    public function force_logout_user($user_id)
    {
        $affected_rows = 0;

        $this->db->trans_start();
        $this->db
            ->where('user_id', (int) $user_id)
            ->where('stat', 1)
            ->limit(1)
            ->update('users', array('stat' => 3));

        // Read this before COMMIT; the transaction query can reset this value.
        $affected_rows = (int) $this->db->affected_rows();
        $this->db->trans_complete();

        return $this->db->trans_status() !== FALSE
            && $affected_rows === 1;
    }

    /**
     * Changes exactly one manageable account to an old RMS Viewer level.
     *
     * The existing database meanings are preserved without adding a table:
     * - role_id 4: Viewer Level 1 (view only).
     * - role_id 3: Viewer Level 2 (view and download).
     *
     * The controller validates the selected account and requested level before
     * this method runs. No password, status, upload permission, or file access
     * record is changed.
     *
     * @param int $user_id Existing users.user_id value.
     * @param int $role_id Either 3 or 4.
     * @return bool TRUE when the transaction succeeds.
     */
    public function set_viewer_role($user_id, $role_id)
    {
        $safe_role = (int) $role_id;

        if ($safe_role !== 3 && $safe_role !== 4) {
            return FALSE;
        }

        $this->db->trans_start();
        $this->db
            ->where('user_id', (int) $user_id)
            ->limit(1)
            ->update('users', array('role_id' => $safe_role));
        $this->db->trans_complete();

        return $this->db->trans_status() !== FALSE;
    }

    /**
     * Enables or disables uploading for exactly one manageable account.
     *
     * This preserves the old RMS values without changing any other field:
     * - allowed_upload 1: Yes, the user may upload.
     * - allowed_upload 0: No, the user may not upload.
     *
     * @param int $user_id Existing users.user_id value.
     * @param int $allowed_upload Either 1 (Yes) or 0 (No).
     * @return bool TRUE when the transaction succeeds.
     */
    public function set_uploader_permission($user_id, $allowed_upload)
    {
        $safe_value = (int) $allowed_upload === 1 ? 1 : 0;

        $this->db->trans_start();
        $this->db
            ->where('user_id', (int) $user_id)
            ->limit(1)
            ->update('users', array('allowed_upload' => $safe_value));
        $this->db->trans_complete();

        return $this->db->trans_status() !== FALSE;
    }

    /**
     * Updates exactly one existing user after controller validation.
     *
     * Queries:
     * - SELECT department/subsidiary: obtains the compatible location value.
     * - SELECT role: obtains the compatible position value.
     * - UPDATE users WHERE user_id: changes only the selected account.
     *
     * The password key is optional. When absent, the current password hash is
     * not included in UPDATE and therefore remains unchanged.
     *
     * @param int $user_id Existing users.user_id value.
     * @param array $data Validated editable fields.
     * @return bool TRUE when the transaction succeeds.
     */
    public function update_user($user_id, $data)
    {
        // Read existing lookup values used by required compatibility columns.
        $department = $this->db
            ->select('departments.dept_id, subsidiaries.sub_name')
            ->from('departments')
            ->join('subsidiaries', 'departments.sub_id = subsidiaries.sub_id', 'inner')
            ->where('departments.dept_id', (int) $data['dept_id'])
            ->limit(1)
            ->get()
            ->row_array();

        $role = $this->db
            ->select('title')
            ->where('role_id', (int) $data['role_id'])
            ->limit(1)
            ->get('user_role')
            ->row_array();

        // Stop before UPDATE if a selected lookup record disappeared.
        if (!$department || !$role) {
            return FALSE;
        }

        $record = array(
            'username'        => $data['username'],
            'emp_name'        => $data['emp_name'],
            'role_id'         => (int) $data['role_id'],
            'dept_id'         => (int) $data['dept_id'],
            'allowed_upload'  => (int) $data['allowed_upload'],
            'location'        => $department['sub_name'],
            'position'        => $role['title']
        );

        // Preserve the current password unless the controller supplied a new hash.
        if (isset($data['password']) && $data['password'] !== '') {
            $record['password'] = $data['password'];
        }

        // The WHERE clause limits this transaction to one selected users row.
        $this->db->trans_start();
        $this->db
            ->where('user_id', (int) $user_id)
            ->limit(1)
            ->update('users', $record);
        $this->db->trans_complete();

        return $this->db->trans_status() !== FALSE;
    }

    /**
     * Reads the complete existing file tree and marks this user's access.
     *
     * Performance improvement: the old page queried children inside nested
     * loops. This method uses one SELECT for filename, one for each of the ten
     * existing subfolder tables, and one for user_allowed_data. The number of
     * queries stays fixed even when the tree contains many folders.
     *
     * @param int $user_id Existing users.user_id value.
     * @return array Nested file/folder nodes for the modal.
     */
    public function get_access_tree($user_id)
    {
        $allowed_rows = $this->db
            ->select(
                'file_id, sub1_id, sub2_id, sub3_id, sub4_id, sub5_id, '.
                'sub6_id, sub7_id, sub8_id, sub9_id, sub10_id'
            )
            ->where('user_id', (int) $user_id)
            ->get('user_allowed_data')
            ->result_array();

        // Convert existing permission rows into fast lookup keys.
        $checked_keys = array();
        foreach ($allowed_rows as $allowed_row) {
            $key = $this->permission_row_key($allowed_row);
            if ($key !== '') {
                $checked_keys[$key] = TRUE;
            }
        }

        $levels = array();
        $children = array();
        $levels[0] = array();

        // Level zero is the existing filename table shown at the tree root.
        $root_rows = $this->db
            ->select('file_id, filename')
            ->from('filename')
            ->order_by('filename', 'ASC')
            ->get()
            ->result_array();

        foreach ($root_rows as $row) {
            $levels[0][(int) $row['file_id']] = array(
                'id' => (int) $row['file_id'],
                'name' => $row['filename']
            );
        }

        /*
         * Existing RMS hierarchy metadata. No table is created or altered.
         * depth 1 uses file_id as its parent; later levels use the previous
         * subfolder ID column.
         */
        $specifications = array(
            1  => array('subfolder1', 'subfolder1_id', 'subfolder1_name', 'file_id'),
            2  => array('subfolder2', 'subfolder2_id', 'subfolder2_name', 'subfolder1_id'),
            3  => array('subfolder3', 'subfolder3_id', 'subfolder3_name', 'subfolder2_id'),
            4  => array('subfolder4', 'subfolder4_id', 'subfolder4_name', 'subfolder3_id'),
            5  => array('subfolder5', 'subfolder5_id', 'subfolder5_name', 'subfolder4_id'),
            6  => array('subfolder6', 'subfolder6_id', 'subfolder6_name', 'subfolder5_id'),
            7  => array('subfolder7', 'subfolder7_id', 'subfolder7_name', 'subfolder6_id'),
            8  => array('subfolder8', 'subfolder8_id', 'subfolder8_name', 'subfolder7_id'),
            9  => array('subfolder9', 'subfolder9_id', 'subfolder9_name', 'subfolder8_id'),
            10 => array('subfolder10', 'subfolder10_id', 'subfolder10_name', 'subfolder9_id')
        );

        foreach ($specifications as $depth => $specification) {
            $table = $specification[0];
            $id_column = $specification[1];
            $name_column = $specification[2];
            $parent_column = $specification[3];
            $levels[$depth] = array();
            $children[$depth] = array();

            $rows = $this->db
                ->select($id_column.', '.$name_column.', '.$parent_column)
                ->from($table)
                ->order_by($name_column, 'ASC')
                ->get()
                ->result_array();

            foreach ($rows as $row) {
                $id = (int) $row[$id_column];
                $parent_id = (int) $row[$parent_column];
                $levels[$depth][$id] = array(
                    'id' => $id,
                    'name' => $row[$name_column]
                );

                if (!isset($children[$depth][$parent_id])) {
                    $children[$depth][$parent_id] = array();
                }
                $children[$depth][$parent_id][] = $id;
            }
        }

        // Build nested presentation nodes after all fixed SELECTs complete.
        $tree = array();
        foreach ($levels[0] as $file_id => $unused) {
            $tree[] = $this->build_access_node(
                0,
                (int) $file_id,
                $levels,
                $children,
                array(),
                $checked_keys
            );
        }

        return $tree;
    }

    /**
     * Returns one user's effective file/folder access for spreadsheet export.
     *
     * A checked parent grants access to its descendants in the old RMS. The
     * export therefore includes that node and every folder below it, without
     * duplicating paths when both a parent and child are checked. All queries
     * are read-only and use the existing filename, subfolder1..subfolder10,
     * and user_allowed_data tables.
     *
     * @param int $user_id Existing users.user_id value.
     * @return array Rows containing Filename followed by Subfolder1..10.
     */
    public function get_user_access_export($user_id)
    {
        $tree = $this->get_access_tree((int) $user_id);
        $rows = array();

        $this->collect_effective_access_rows($tree, array(), FALSE, $rows);

        return $rows;
    }

    /**
     * Flattens effective access nodes while retaining each complete path.
     *
     * @param array $nodes Current hierarchy level.
     * @param array $parent_names Resolved names above the current level.
     * @param bool $parent_allowed Whether an ancestor grants access.
     * @param array $rows Export rows collected by reference.
     */
    private function collect_effective_access_rows(
        $nodes,
        $parent_names,
        $parent_allowed,
        &$rows
    )
    {
        foreach ($nodes as $node) {
            $path = $parent_names;
            $path[] = isset($node['name']) ? (string) $node['name'] : '';
            $is_allowed = $parent_allowed || !empty($node['checked']);

            if ($is_allowed) {
                $rows[] = array_pad(array_slice($path, 0, 11), 11, '');
            }

            if (!empty($node['children'])) {
                $this->collect_effective_access_rows(
                    $node['children'],
                    $path,
                    $is_allowed,
                    $rows
                );
            }
        }
    }

    /**
     * Recursively converts level maps into a nested display tree.
     */
    private function build_access_node(
        $depth,
        $id,
        &$levels,
        &$children,
        $parent_path,
        &$checked_keys
    ) {
        $path = $parent_path;
        $path[] = (int) $id;
        $token = (int) $depth.':'.implode('/', $path);
        $node = array(
            'depth' => (int) $depth,
            'id' => (int) $id,
            'name' => $levels[$depth][$id]['name'],
            'token' => $token,
            'checked' => isset($checked_keys[$token]),
            'children' => array()
        );

        $child_depth = (int) $depth + 1;
        if ($child_depth <= 10 && isset($children[$child_depth][$id])) {
            foreach ($children[$child_depth][$id] as $child_id) {
                $node['children'][] = $this->build_access_node(
                    $child_depth,
                    (int) $child_id,
                    $levels,
                    $children,
                    $path,
                    $checked_keys
                );
            }
        }

        return $node;
    }

    /**
     * Converts one old user_allowed_data row into the browser's path token.
     */
    private function permission_row_key($row)
    {
        $path = array((int) $row['file_id']);
        $depth = 0;

        for ($level = 1; $level <= 10; $level++) {
            $column = 'sub'.$level.'_id';
            $value = isset($row[$column]) ? (int) $row[$column] : 0;

            if ($value < 1) {
                break;
            }

            $path[] = $value;
            $depth = $level;
        }

        return $path[0] > 0 ? $depth.':'.implode('/', $path) : '';
    }

    /**
     * Replaces one user's access rows using only validated existing tree paths.
     *
     * This preserves the old RMS process: permissions live in
     * user_allowed_data, with zeroes after the selected folder depth. No table
     * or unrelated account is changed. The previous rows are held in memory
     * and restored if the batch insert fails (important because this old table
     * uses MyISAM and does not support transactions).
     *
     * @param int   $user_id Existing users.user_id value.
     * @param array $tokens Checked access_path[] values from the form.
     * @return bool TRUE when the replacement succeeds.
     */
    public function replace_user_access($user_id, $tokens)
    {
        // Build the authoritative token map from existing filename/subfolder IDs.
        $tree = $this->get_access_tree(0);
        $valid_paths = array();
        $this->collect_access_paths($tree, $valid_paths);
        $new_rows = array();
        $seen = array();

        foreach ($tokens as $token) {
            $token = trim((string) $token);

            // Reject the complete request if any submitted path was fabricated.
            if (!isset($valid_paths[$token])) {
                return FALSE;
            }

            if (isset($seen[$token])) {
                continue;
            }
            $seen[$token] = TRUE;

            $path = $valid_paths[$token];
            $record = array(
                'user_id' => (int) $user_id,
                'file_id' => (int) $path[0]
            );

            for ($level = 1; $level <= 10; $level++) {
                $record['sub'.$level.'_id'] = isset($path[$level])
                    ? (int) $path[$level]
                    : 0;
            }

            $new_rows[] = $record;
        }

        // Preserve a recoverable copy before following the old replace process.
        $old_rows = $this->db
            ->select(
                'user_id, file_id, sub1_id, sub2_id, sub3_id, sub4_id, '.
                'sub5_id, sub6_id, sub7_id, sub8_id, sub9_id, sub10_id'
            )
            ->where('user_id', (int) $user_id)
            ->get('user_allowed_data')
            ->result_array();

        if (!$this->db->where('user_id', (int) $user_id)->delete('user_allowed_data')) {
            return FALSE;
        }

        // An empty checked list intentionally removes this user's file access.
        if (empty($new_rows)) {
            return TRUE;
        }

        if ($this->db->insert_batch('user_allowed_data', $new_rows) !== FALSE) {
            return TRUE;
        }

        // Best-effort restoration protects the previous permissions on failure.
        if (!empty($old_rows)) {
            $this->db->insert_batch('user_allowed_data', $old_rows);
        }

        return FALSE;
    }

    /**
     * Return active RMS users together with whether they are tagged to one
     * exact existing filename/subfolder path.
     *
     * @param string $token Existing access token, e.g. 2:4/8/15.
     * @return array
     */
    public function get_path_access_users($token)
    {
        $tree = $this->get_access_tree(0);
        $valid_paths = array();
        $this->collect_access_paths($tree, $valid_paths);
        $token = trim((string) $token);

        if (!isset($valid_paths[$token])) {
            return array();
        }

        $path = $valid_paths[$token];
        $users = $this->db
            ->select('user_id, username, emp_name, role_id, stat')
            ->from('users')
            ->where('stat', 0)
            ->order_by('emp_name', 'ASC')
            ->order_by('username', 'ASC')
            ->get()
            ->result_array();

        foreach ($users as &$user) {
            $query = $this->db->from('user_allowed_data')
                ->where('user_id', (int) $user['user_id'])
                ->where('file_id', (int) $path[0]);

            for ($level = 1; $level <= 10; $level++) {
                $query->where(
                    'sub'.$level.'_id',
                    isset($path[$level]) ? (int) $path[$level] : 0
                );
            }

            $user['has_access'] = $query->count_all_results() > 0 ? 1 : 0;
        }
        unset($user);

        return $users;
    }

    /**
     * Replace only the selected-user assignments for one exact path while
     * preserving every other existing user_allowed_data permission row.
     *
     * @param string $token
     * @param array $selected_user_ids
     * @return bool
     */
    public function set_path_access_users($token, $selected_user_ids)
    {
        $tree = $this->get_access_tree(0);
        $valid_paths = array();
        $this->collect_access_paths($tree, $valid_paths);
        $token = trim((string) $token);

        if (!isset($valid_paths[$token])) {
            return FALSE;
        }

        $path = $valid_paths[$token];
        $selected = array();
        foreach ((array) $selected_user_ids as $user_id) {
            $user_id = (int) $user_id;
            if ($user_id > 0) {
                $selected[$user_id] = TRUE;
            }
        }

        $users = $this->db
            ->select('user_id')
            ->from('users')
            ->where('stat', 0)
            ->get()
            ->result_array();

        foreach ($users as $user) {
            $user_id = (int) $user['user_id'];
            $where = array(
                'user_id' => $user_id,
                'file_id' => (int) $path[0]
            );
            for ($level = 1; $level <= 10; $level++) {
                $where['sub'.$level.'_id'] = isset($path[$level])
                    ? (int) $path[$level]
                    : 0;
            }

            $exists = $this->db
                ->from('user_allowed_data')
                ->where($where)
                ->count_all_results() > 0;

            if (isset($selected[$user_id])) {
                if (!$exists && !$this->db->insert('user_allowed_data', $where)) {
                    return FALSE;
                }
            } elseif ($exists) {
                if (!$this->db->where($where)->delete('user_allowed_data')) {
                    return FALSE;
                }
            }
        }

        return TRUE;
    }

    /**
     * Add or remove selected users for one exact RMS path without replacing
     * the other users already tagged to that path.
     *
     * @param string $token
     * @param array $user_ids
     * @param bool $grant TRUE to add, FALSE to remove
     * @return bool
     */
    public function change_path_access_users($token, $user_ids, $grant)
    {
        $tree = $this->get_access_tree(0);
        $valid_paths = array();
        $this->collect_access_paths($tree, $valid_paths);
        $token = trim((string) $token);

        if (!isset($valid_paths[$token])) {
            return FALSE;
        }

        $selected = array();
        foreach ((array) $user_ids as $user_id) {
            $user_id = (int) $user_id;
            if ($user_id > 0) {
                $selected[$user_id] = TRUE;
            }
        }

        if (empty($selected)) {
            return TRUE;
        }

        $valid_users = $this->db
            ->select('user_id')
            ->from('users')
            ->where('stat', 0)
            ->where_in('user_id', array_keys($selected))
            ->get()
            ->result_array();

        $path = $valid_paths[$token];

        foreach ($valid_users as $user) {
            $where = array(
                'user_id' => (int) $user['user_id'],
                'file_id' => (int) $path[0]
            );

            for ($level = 1; $level <= 10; $level++) {
                $where['sub'.$level.'_id'] = isset($path[$level])
                    ? (int) $path[$level]
                    : 0;
            }

            $exists = $this->db
                ->from('user_allowed_data')
                ->where($where)
                ->count_all_results() > 0;

            if ($grant) {
                if (!$exists && !$this->db->insert('user_allowed_data', $where)) {
                    return FALSE;
                }
            } elseif ($exists && !$this->db->where($where)->delete('user_allowed_data')) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Flattens presentation nodes into token => numeric path validation data.
     */
    private function collect_access_paths($nodes, &$valid_paths)
    {
        foreach ($nodes as $node) {
            $parts = explode(':', $node['token'], 2);
            $valid_paths[$node['token']] = array_map('intval', explode('/', $parts[1]));

            if (!empty($node['children'])) {
                $this->collect_access_paths($node['children'], $valid_paths);
            }
        }
    }
}
