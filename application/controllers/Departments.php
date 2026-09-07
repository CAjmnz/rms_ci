<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Departments extends CI_Controller
{
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

        $this->load->database();

        $this->load->model('Department_model');
        $this->load->model('Subsidiary_model');
    }

    /**
     * Only the legacy Super User role can manage departments.
     */
    private function require_manager()
    {
        if ($this->session->userdata('rms_logged_in') !== TRUE) {
            redirect('administrator/');
            return FALSE;
        }

        if ((int) $this->session->userdata('rms_role') !== 1) {
            $this->show_access_restricted('Departments');
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Show the shared branded restriction page for normal page requests.
     * Background/write requests receive a safe JSON 403 response instead.
     */
    private function show_access_restricted($module_name)
    {
        $this->output->set_status_header(403);

        if ($this->input->is_ajax_request() || strtoupper($this->input->method()) !== 'GET') {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => FALSE,
                    'message' => 'Super User access is required for this action.'
                )));
            return;
        }

        $this->load->view('admin/access_restricted', array(
            'page_title' => 'Access Restricted',
            'module_name' => $module_name,
            'system_name' => $this->config->item('rms_auth_system_name'),
            'display_name' => $this->session->userdata('rms_display_name'),
            'username' => $this->session->userdata('rms_username'),
            'position' => $this->session->userdata('rms_position'),
            'routes' => $this->config->item('rms_dashboard_routes')
        ));
    }

    /**
     * Prepare department listing, searching, and pagination data.
     */
    private function view_data()
    {
        $allowed_sizes = array(10, 25, 50, 100);

        $per_page = (int) $this->input->get('per_page', TRUE);

        if (!in_array($per_page, $allowed_sizes, TRUE)) {
            $per_page = 10;
        }

        $search = trim((string) $this->input->get('search', TRUE));
        /* Validate user-facing sort keys before querying the database. */
        $sort = (string) $this->input->get('sort', TRUE);
        $sort = in_array($sort, array('name', 'subsidiary', 'id'), TRUE)
            ? $sort
            : 'name';
        $order = strtolower((string) $this->input->get('order', TRUE));
        $order = $order === 'desc' ? 'desc' : 'asc';

        $total = $this->Department_model->count_all($search);
        $pages = max(1, (int) ceil($total / $per_page));

        $page = (int) $this->input->get('page', TRUE);
        $page = max(1, min($page, $pages));

        $offset = ($page - 1) * $per_page;

        return array(
            'page_title' => 'Departments',
            'system_name' => $this->config->item('rms_auth_system_name'),
            'display_name' => $this->session->userdata('rms_display_name'),
            'username' => $this->session->userdata('rms_username'),
            'position' => $this->session->userdata('rms_position'),
            'routes' => $this->config->item('rms_dashboard_routes'),

            'departments' => $this->Department_model->get_all(
                $per_page,
                $offset,
                $search,
                $sort,
                $order
            ),

            'subsidiaries' => $this->Subsidiary_model->get_all(
                1000,
                0,
                ''
            ),

            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $per_page,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
            'first_row' => $total ? $offset + 1 : 0,
            'last_row' => min($offset + $per_page, $total)
        );
    }

    /**
     * Display the department management page.
     */
    public function index()
    {
        if (!$this->require_manager()) {
            return;
        }

        $this->load->view(
            'admin/departments/index',
            $this->view_data()
        );
    }

    /**
     * Return department information for the add/edit modal.
     */
    public function form($dept_id = 0)
    {
        if (!$this->require_manager()) {
            return;
        }

        $dept_id = (int) $dept_id;

        $record = $dept_id > 0
            ? $this->Department_model->find($dept_id)
            : array();

        if ($dept_id > 0 && !$record) {
            return $this->json(
                FALSE,
                'The selected department no longer exists.'
            );
        }

        return $this->json(TRUE, '', array(
            'department' => $record,
            'subsidiaries' => $this->Subsidiary_model->get_all(
                1000,
                0,
                ''
            )
        ));
    }

    /**
     * Create or update a department.
     */
    public function save()
    {
        if (!$this->require_manager()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $dept_id = (int) $this->input->post('dept_id', TRUE);
        $sub_id = (int) $this->input->post('sub_id', TRUE);

        /*
         * Keep the legacy input name "department_name".
         * Also accept "dept_name" if your current form uses that name.
         */
        $dept_name = trim(
            (string) $this->input->post('department_name', TRUE)
        );

        if ($dept_name === '') {
            $dept_name = trim(
                (string) $this->input->post('dept_name', TRUE)
            );
        }

        if ($sub_id <= 0) {
            return $this->json(
                FALSE,
                'Please select a subsidiary.'
            );
        }

        $subsidiary = $this->Subsidiary_model->find($sub_id);

        if (!$subsidiary) {
            return $this->json(
                FALSE,
                'The selected subsidiary no longer exists.'
            );
        }

        if ($dept_name === '') {
            return $this->json(
                FALSE,
                'Please enter a department name.'
            );
        }

        if (strlen($dept_name) > 100) {
            return $this->json(
                FALSE,
                'The department name must not exceed 100 characters.'
            );
        }

        /*
         * Folder names cannot contain control characters, slash, or backslash.
         */
        if (preg_match('/[\\x00-\\x1F\\x7F\\/\\\\]/', $dept_name)) {
            return $this->json(
                FALSE,
                'The department name contains a character that cannot be used in a storage folder.'
            );
        }

        $old = array();

        if ($dept_id > 0) {
            $old = $this->Department_model->find($dept_id);

            if (!$old) {
                return $this->json(
                    FALSE,
                    'The selected department no longer exists.'
                );
            }
        }

        /*
         * A department name must be unique within its subsidiary.
         */
        if ($this->Department_model->name_exists(
            $dept_name,
            $sub_id,
            $dept_id
        )) {
            return $this->json(
                FALSE,
                'A department with this name already exists under the selected subsidiary.'
            );
        }

        $new_sub_name = $subsidiary['sub_name'];

        $old_sub_name = '';
        $old_dept_name = '';

        if ($dept_id > 0) {
            $old_sub_name = isset($old['sub_name'])
                ? $old['sub_name']
                : '';

            $old_dept_name = isset($old['dept_name'])
                ? $old['dept_name']
                : '';
        }

        /*
         * Local CI3 installations can work without the sibling native RMS
         * storage tree. Production must prepare both legacy folders first.
         */
        $storage_required = $this->requires_legacy_storage();
        if ($storage_required && !$this->sync_storage(
            $old_sub_name,
            $old_dept_name,
            $new_sub_name,
            $dept_name,
            FALSE
        )) {
            return $this->json(
                FALSE,
                'The department storage folders could not be prepared. The database was not changed.'
            );
        }

        /* Save the record transactionally. */
        $this->db->trans_start();
        if ($dept_id > 0) {
            $saved = $this->Department_model->update(
                $dept_id,
                $sub_id,
                $dept_name
            );
        } else {
            $saved = $this->Department_model->create(
                $sub_id,
                $dept_name
            );
        }
        $this->db->trans_complete();

        if (!$saved || $this->db->trans_status() === FALSE) {
            if ($storage_required) {
                $this->sync_storage(
                    $new_sub_name,
                    $dept_name,
                    $old_sub_name,
                    $old_dept_name,
                    $dept_id === 0
                );
            }
            return $this->json(
                FALSE,
                'The department could not be saved. Please try again.'
            );
        }

        return $this->json(TRUE, $dept_id > 0
            ? 'The department was updated successfully.'
            : 'The department was added successfully.');
    }

    /**
     * Delete a department and its matching folders.
     */
    public function delete()
    {
        if (!$this->require_manager()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $dept_id = (int) $this->input->post('dept_id', TRUE);

        $record = $this->Department_model->find($dept_id);

        if (!$record) {
            return $this->json(
                FALSE,
                'The selected department no longer exists.'
            );
        }

        /*
         * Do not delete a department that is still referenced by users,
         * folders, documents, or other existing legacy records.
         */
        $counts = $this->Department_model->related_counts($dept_id);

        foreach ($counts as $count) {
            if ((int) $count > 0) {
                return $this->json(
                    FALSE,
                    'This department cannot be deleted because it is currently being used by another system record.'
                );
            }
        }

        $storage_required = $this->requires_legacy_storage();
        if ($storage_required && !$this->sync_storage(
            $record['sub_name'],
            $record['dept_name'],
            '',
            '',
            TRUE
        )) {
            return $this->json(
                FALSE,
                'The department storage folder is not empty or could not be removed. The database was not changed.'
            );
        }

        if (!$this->Department_model->delete($dept_id)) {
            /*
             * Recreate the folder when the database deletion fails.
             */
            if ($storage_required) {
                $this->sync_storage(
                    '',
                    '',
                    $record['sub_name'],
                    $record['dept_name'],
                    FALSE
                );
            }

            return $this->json(
                FALSE,
                'The department could not be deleted. Please try again.'
            );
        }

        return $this->json(
            TRUE,
            'The department was deleted successfully.'
        );
    }

    /**
     * Create, rename, move, or remove the department directory in both
     * configured storage locations.
     *
     * Structure:
     * storage root / subsidiary / department
     */
    /**
     * Only production requires the sibling old-system storage tree. Set
     * CI_ENV to "production" on the live server; use "development" locally.
     */
    private function requires_legacy_storage()
    {
        return defined('ENVIRONMENT') && strtolower((string) ENVIRONMENT) === 'production';
    }

    private function sync_storage(
        $old_sub_name,
        $old_dept_name,
        $new_sub_name,
        $new_dept_name,
        $remove
    ) {
        $roots = $this->legacy_storage_roots();

        /*
         * Validate all storage operations before changing any folder.
         */
        foreach ($roots as $root) {
            if ($root === '' || !is_dir($root) || !is_writable($root)) {
                return FALSE;
            }

            $old_path = '';

            if ($old_sub_name !== '' && $old_dept_name !== '') {
                $old_path = $root
                    . DIRECTORY_SEPARATOR
                    . $old_sub_name
                    . DIRECTORY_SEPARATOR
                    . $old_dept_name;
            }

            $new_parent = '';

            if ($new_sub_name !== '') {
                $new_parent = $root
                    . DIRECTORY_SEPARATOR
                    . $new_sub_name;
            }

            $new_path = '';

            if ($new_sub_name !== '' && $new_dept_name !== '') {
                $new_path = $new_parent
                    . DIRECTORY_SEPARATOR
                    . $new_dept_name;
            }

            if ($remove && $old_path !== '' && is_dir($old_path)) {
                $items = array_values(
                    array_diff(
                        scandir($old_path),
                        array('.', '..', 'index.html')
                    )
                );

                if (count($items) > 0 || !is_writable($old_path)) {
                    return FALSE;
                }
            }

            if (!$remove && $new_parent !== '' && !is_dir($new_parent)) {
                return FALSE;
            }

            if (
                !$remove &&
                $old_path !== '' &&
                $old_path !== $new_path &&
                is_dir($new_path)
            ) {
                return FALSE;
            }
        }

        /*
         * Apply the validated storage operation to both roots.
         */
        foreach ($roots as $root) {
            $old_path = '';

            if ($old_sub_name !== '' && $old_dept_name !== '') {
                $old_path = $root
                    . DIRECTORY_SEPARATOR
                    . $old_sub_name
                    . DIRECTORY_SEPARATOR
                    . $old_dept_name;
            }

            $new_parent = '';

            if ($new_sub_name !== '') {
                $new_parent = $root
                    . DIRECTORY_SEPARATOR
                    . $new_sub_name;
            }

            $new_path = '';

            if ($new_sub_name !== '' && $new_dept_name !== '') {
                $new_path = $new_parent
                    . DIRECTORY_SEPARATOR
                    . $new_dept_name;
            }

            if ($remove) {
                if ($old_path !== '' && is_dir($old_path)) {
                    $items = array_values(
                        array_diff(
                            scandir($old_path),
                            array('.', '..', 'index.html')
                        )
                    );

                    if (count($items) > 0) {
                        return FALSE;
                    }

                    $index_file = $old_path
                        . DIRECTORY_SEPARATOR
                        . 'index.html';

                    if (is_file($index_file)) {
                        @unlink($index_file);
                    }

                    if (!@rmdir($old_path)) {
                        return FALSE;
                    }
                }

                continue;
            }

            if ($old_path !== '') {
                /*
                 * This also moves the department when its subsidiary changes.
                 */
                if (
                    $old_path !== $new_path &&
                    is_dir($old_path) &&
                    !@rename($old_path, $new_path)
                ) {
                    return FALSE;
                }
            } elseif (!is_dir($new_path)) {
                if (!@mkdir($new_path, 0755)) {
                    return FALSE;
                }

                $index_file = $new_path
                    . DIRECTORY_SEPARATOR
                    . 'index.html';

                if (
                    @file_put_contents(
                        $index_file,
                        '<!DOCTYPE html><title></title>'
                    ) === FALSE
                ) {
                    return FALSE;
                }
            }
        }

        return TRUE;
    }

    /**
     * Exact native RMS directories used by the company live server.
     * Keeping these paths here prevents incorrect database storage settings
     * from sending department folders to another location.
     */
    private function legacy_storage_roots()
    {
        return array(
            '/var/www/html/rms/administrator/agc_data',
            '/var/www/html/rms/data'
        );
    }

    /**
     * Output a standard JSON response.
     */
    private function json($success, $message, $extra = array())
    {
        /* Return the regenerated token for the next AJAX save or delete. */
        $data = is_array($extra) ? $extra : array();

        $data['success'] = (bool) $success;
        $data['message'] = (string) $message;
        $data['csrfName'] = $this->security->get_csrf_token_name();
        $data['csrfHash'] = $this->security->get_csrf_hash();

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
