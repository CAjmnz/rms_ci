<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Subsidiaries extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->config->load('rms_auth');
        $this->load->helper(array('url', 'form', 'security'));
        $this->load->library(array('session', 'form_validation'));
        $this->load->database();
        $this->load->model('Subsidiary_model');
    }

    private function require_manager()
    {
        if ($this->session->userdata('rms_logged_in') !== TRUE) {
            redirect('administrator/');
            return FALSE;
        }
        /* The legacy RMS allows only the Super User (role 1) here. */
        if ((int) $this->session->userdata('rms_role') !== 1) {
            $this->show_access_restricted('Subsidiaries');
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

    private function view_data()
    {
        $allowed_sizes = array(10, 25, 50, 100);
        $per_page = (int) $this->input->get('per_page', TRUE);
        if (!in_array($per_page, $allowed_sizes, TRUE)) {
            $per_page = 10;
        }
        $search = trim((string) $this->input->get('search', TRUE));
        /* Validate sorting before passing it to the model. */
        $sort = (string) $this->input->get('sort', TRUE);
        $sort = in_array($sort, array('name', 'id'), TRUE) ? $sort : 'name';
        $order = strtolower((string) $this->input->get('order', TRUE));
        $order = $order === 'desc' ? 'desc' : 'asc';
        $total = $this->Subsidiary_model->count_all($search);
        $pages = max(1, (int) ceil($total / $per_page));
        $page = max(1, min((int) $this->input->get('page', TRUE), $pages));
        $offset = ($page - 1) * $per_page;

        return array(
            'page_title' => 'Subsidiaries',
            'system_name' => $this->config->item('rms_auth_system_name'),
            'display_name' => $this->session->userdata('rms_display_name'),
            'username' => $this->session->userdata('rms_username'),
            'position' => $this->session->userdata('rms_position'),
            'routes' => $this->config->item('rms_dashboard_routes'),
            'subsidiaries' => $this->Subsidiary_model->get_all($per_page, $offset, $search, $sort, $order),
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

    public function index()
    {
        if (!$this->require_manager()) { return; }
        $this->load->view('admin/subsidiaries/index', $this->view_data());
    }

    public function form($sub_id)
    {
        if (!$this->require_manager()) { return; }
        $sub_id = (int) $sub_id;
        $record = $sub_id > 0 ? $this->Subsidiary_model->find($sub_id) : array();
        if ($sub_id > 0 && !$record) {
            return $this->json(FALSE, 'The selected subsidiary no longer exists.');
        }
        return $this->json(TRUE, '', array('subsidiary' => $record));
    }

    public function save()
    {
        if (!$this->require_manager()) { return; }
        if (strtoupper($this->input->method()) !== 'POST') { show_404(); return; }

        $sub_id = (int) $this->input->post('sub_id', TRUE);
        $sub_name = trim((string) $this->input->post('sub_name', TRUE));
        if ($sub_name === '') {
            return $this->json(FALSE, 'Please enter a subsidiary name.');
        }
        if (strlen($sub_name) > 100) {
            return $this->json(FALSE, 'The subsidiary name must not exceed 100 characters.');
        }
        if (preg_match('/[\\x00-\\x1F\\x7F\\/\\\\]/', $sub_name)) {
            return $this->json(FALSE, 'The subsidiary name contains a character that cannot be used in a storage folder.');
        }
        if ($sub_id > 0 && !$this->Subsidiary_model->find($sub_id)) {
            return $this->json(FALSE, 'The selected subsidiary no longer exists.');
        }
        if ($this->Subsidiary_model->name_exists($sub_name, $sub_id)) {
            return $this->json(FALSE, 'A subsidiary with this name already exists.');
        }

        $old = $sub_id > 0 ? $this->Subsidiary_model->find($sub_id) : array();

        /*
         * The local CI3 copy may run without the sibling legacy RMS project.
         * Require the two old-system folders only in the production/live
         * environment; local development saves the database record normally.
         */
        $storage_required = $this->requires_legacy_storage();
        if ($storage_required) {
            $storage_ok = $this->sync_storage(
                $sub_id > 0 ? $old['sub_name'] : '',
                $sub_name,
                FALSE
            );
            if (!$storage_ok) {
                return $this->json(FALSE, $this->storage_error !== ''
                    ? $this->storage_error
                    : 'The storage folders could not be prepared. The database was not changed.');
            }
        }

        /* Save only after both old-system folders are ready. */
        $this->db->trans_start();
        $saved = $sub_id > 0
            ? $this->Subsidiary_model->update($sub_id, $sub_name)
            : $this->Subsidiary_model->create($sub_name);
        $this->db->trans_complete();

        if (!$saved || $this->db->trans_status() === FALSE) {
            /* Restore live storage only when this request changed it. */
            if ($storage_required) {
                $this->sync_storage(
                    $sub_name,
                    $sub_id > 0 ? $old['sub_name'] : '',
                    $sub_id === 0
                );
            }
            return $this->json(FALSE, 'The subsidiary could not be saved. Please try again.');
        }
        $message = $sub_id > 0
            ? 'The subsidiary was updated successfully.'
            : 'The subsidiary was added successfully.';

        return $this->json(TRUE, $message);
    }

    public function delete()
    {
        if (!$this->require_manager()) { return; }
        if (strtoupper($this->input->method()) !== 'POST') { show_404(); return; }
        $sub_id = (int) $this->input->post('sub_id', TRUE);
        $record = $this->Subsidiary_model->find($sub_id);
        if (!$record) {
            return $this->json(FALSE, 'The selected subsidiary no longer exists.');
        }
        $counts = $this->Subsidiary_model->related_counts($sub_id);
        if ($counts['departments'] > 0 || $counts['users'] > 0) {
            return $this->json(FALSE, 'This subsidiary cannot be deleted because it is currently used by a department or user account.');
        }
        $storage_required = $this->requires_legacy_storage();
        if ($storage_required && !$this->sync_storage($record['sub_name'], '', TRUE)) {
            return $this->json(FALSE, 'The storage folder is not empty or could not be removed. The database was not changed.');
        }
        if (!$this->Subsidiary_model->delete($sub_id)) {
            if ($storage_required) {
                $this->sync_storage('', $record['sub_name'], FALSE);
            }
            return $this->json(FALSE, 'The subsidiary could not be deleted. Please try again.');
        }
        return $this->json(TRUE, 'The subsidiary was deleted successfully.');
    }

    private $storage_error = '';

    /**
     * Local/development installations are allowed to manage the database
     * without the sibling native RMS storage tree. Production keeps the old
     * system's strict two-folder behavior.
     *
     * Set CI_ENV to "production" on the live server. CI3 exposes that value
     * through the ENVIRONMENT constant defined by index.php.
     */
    private function requires_legacy_storage()
    {
        return defined('ENVIRONMENT') && strtolower((string) ENVIRONMENT) === 'production';
    }

    private function sync_storage($old_name, $new_name, $remove)
    {
        $this->storage_error = '';
        $roots = $this->legacy_storage_roots();
        foreach ($roots as $root) {
            if ($root === '' || !is_dir($root)) {
                $this->storage_error = 'The old-system storage root does not exist: '.$root;
                return FALSE;
            }
            if (!is_writable($root)) {
                $this->storage_error = 'Apache/PHP cannot write to the old-system storage root: '.$root;
                return FALSE;
            }
            $check_old = $old_name !== '' ? $root.DIRECTORY_SEPARATOR.$old_name : '';
            $check_new = $new_name !== '' ? $root.DIRECTORY_SEPARATOR.$new_name : '';
            if ($remove && $check_old !== '' && is_dir($check_old)) {
                $check_items = array_values(array_diff(scandir($check_old), array('.', '..', 'index.html')));
                if (count($check_items) > 0) {
                    $this->storage_error = 'The storage folder is not empty: '.$check_old;
                    return FALSE;
                }
                if (!is_writable($check_old)) {
                    $this->storage_error = 'Apache/PHP cannot remove the storage folder: '.$check_old;
                    return FALSE;
                }
            }
            if (!$remove && $old_name !== '' && $old_name !== $new_name && is_dir($check_new)) {
                $this->storage_error = 'A storage folder already uses the new subsidiary name: '.$check_new;
                return FALSE;
            }
        }
        $completed = array();
        foreach ($roots as $root) {
            $old_path = $old_name !== '' ? $root.DIRECTORY_SEPARATOR.$old_name : '';
            $new_path = $new_name !== '' ? $root.DIRECTORY_SEPARATOR.$new_name : '';
            if ($remove) {
                if ($old_path !== '' && is_dir($old_path)) {
                    $items = array_values(array_diff(scandir($old_path), array('.', '..', 'index.html')));
                    if (count($items) > 0) { return FALSE; }
                    if (is_file($old_path.DIRECTORY_SEPARATOR.'index.html')) { @unlink($old_path.DIRECTORY_SEPARATOR.'index.html'); }
                    if (!@rmdir($old_path)) {
                        foreach ($completed as $removed_path) {
                            if (!is_dir($removed_path)) {
                                @mkdir($removed_path, 0755);
                                @file_put_contents($removed_path.DIRECTORY_SEPARATOR.'index.html', '<!DOCTYPE html><title></title>');
                            }
                        }
                        $this->storage_error = 'The storage folder could not be removed: '.$old_path;
                        return FALSE;
                    }
                    $completed[] = $old_path;
                }
            } elseif ($old_name !== '') {
                if ($old_name !== $new_name && is_dir($old_path)) {
                    if (!@rename($old_path, $new_path)) {
                        foreach (array_reverse($completed) as $change) {
                            @rename($change['new'], $change['old']);
                        }
                        $this->storage_error = 'The storage folder could not be renamed: '.$old_path;
                        return FALSE;
                    }
                    $completed[] = array('old' => $old_path, 'new' => $new_path);
                }
            } elseif (!is_dir($new_path)) {
                if (!@mkdir($new_path, 0755)) {
                    foreach (array_reverse($completed) as $created_path) {
                        @unlink($created_path.DIRECTORY_SEPARATOR.'index.html');
                        @rmdir($created_path);
                    }
                    $this->storage_error = 'The storage folder could not be created: '.$new_path;
                    return FALSE;
                }
                $completed[] = $new_path;
                if (@file_put_contents($new_path.DIRECTORY_SEPARATOR.'index.html', '<!DOCTYPE html><title></title>') === FALSE) {
                    foreach (array_reverse($completed) as $created_path) {
                        @unlink($created_path.DIRECTORY_SEPARATOR.'index.html');
                        @rmdir($created_path);
                    }
                    $this->storage_error = 'The storage marker could not be written: '.$new_path.DIRECTORY_SEPARATOR.'index.html';
                    return FALSE;
                }
            }
        }
        
        return TRUE;
    }

    /**
     * Exact native RMS directories used by the company live server.
     * Keeping these paths here prevents incorrect database storage settings
     * from sending subsidiary folders to another location.
     */
    private function legacy_storage_roots()
    {
        return array(
            '/var/www/html/rms/administrator/agc_data',
            '/var/www/html/rms/data'
        );
    }

    private function json($success, $message, $extra = array())
    {
        /* Return a fresh CSRF value so consecutive modal actions remain valid. */
        $data = is_array($extra) ? $extra : array();
        $data['success'] = (bool) $success;
        $data['message'] = (string) $message;
        $data['csrfName'] = $this->security->get_csrf_token_name();
        $data['csrfHash'] = $this->security->get_csrf_hash();
        $this->output->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
