<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class System extends CI_Controller
{
    /**
     * Load the dependencies shared by every System action.
     */
    public function __construct()
    {
        parent::__construct();
        $this->config->load('rms_auth');
        $this->load->helper(array('url', 'form', 'security'));
        $this->load->library(array('session', 'form_validation'));
        $this->load->database();
        $this->load->model('System_setting_model');
        $this->load->model('Filetype_model');
        $this->load->model('Access_log_model');
        $this->load->model('Backup_model');
    }

    /**
     * Allow only authenticated Super Users (role 1) into this module.
     *
     * @return bool
     */
    private function require_super_user()
    {
        if ($this->session->userdata('rms_logged_in') !== TRUE) {
            redirect('administrator/');
            return FALSE;
        }
        if ((int) $this->session->userdata('rms_role') !== 1) {
            $this->show_access_restricted('System');
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
     * Build the values required by the shared admin layout and settings view.
     *
     * @return array
     */
    private function common_data($page_title, $extra = array())
    {
        $data = array(
            'page_title' => $page_title,
            'system_name' => $this->config->item('rms_auth_system_name'),
            'display_name' => $this->session->userdata('rms_display_name'),
            'username' => $this->session->userdata('rms_username'),
            'position' => $this->session->userdata('rms_position'),
            'routes' => $this->config->item('rms_dashboard_routes'),
            'settings' => $this->System_setting_model->get_all()
        );

        return array_merge($data, $extra);
    }

    /**
     * Display the Global Configuration page.
     */
    public function index()
    {
        if (!$this->require_super_user()) {
            return;
        }

        $this->load->view(
            'admin/system/global_configuration',
            $this->common_data('Global Configuration')
        );
    }

    /**
     * Display all legacy file types in the current System layout.
     */
    public function file_types()
    {
        if (!$this->require_super_user()) {
            return;
        }

        $this->load->view(
            'admin/system/file_types',
            $this->common_data('File Type Setting', array(
                'filetypes' => $this->Filetype_model->get_all()
            ))
        );
    }

    /**
     * Display the activity stored by the legacy RMS text log files.
     */
    public function access_logs()
    {
        if (!$this->require_super_user()) {
            return;
        }

        $logs = $this->Access_log_model->get_all();
        $this->load->view(
            'admin/system/access_logs',
            $this->common_data('Access Logs', array(
                'logs' => $logs,
                'log_sources' => $this->Access_log_model->source_status()
            ))
        );
    }

    /**
     * Display the two backup choices retained from the legacy RMS page.
     */
    public function backup()
    {
        if (!$this->require_super_user()) {
            return;
        }

        $this->load->view(
            'admin/system/backup',
            $this->common_data('Backup', array(
                'zip_available' => class_exists('ZipArchive')
            ))
        );
    }

    /**
     * Build and download the selected backup after a CSRF-protected POST.
     */
    public function download_backup()
    {
        if (!$this->require_super_user()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $mode = trim((string) $this->input->post('backup_mode'));
        if (!in_array($mode, array('database', 'system_database'), TRUE)) {
            $this->session->set_flashdata('backup_error', 'Choose a backup option before continuing.');
            redirect('administrator/system/backup');
            return;
        }

        $result = $mode === 'database'
            ? $this->Backup_model->create_database_backup()
            : $this->Backup_model->create_system_backup();

        if (!$result['success']) {
            $this->session->set_flashdata('backup_error', $result['message']);
            redirect('administrator/system/backup');
            return;
        }

        // Stream the generated file without exposing its temporary server path.
        $this->output->set_header('Content-Type: ' . $result['mime']);
        $this->output->set_header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        $this->output->set_header('Content-Length: ' . filesize($result['path']));
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->output->set_header('Pragma: no-cache');

        readfile($result['path']);
        @unlink($result['path']);
        exit;
    }

    /**
     * Empty both legacy log files after an explicit confirmation.
     */
    public function clear_access_logs()
    {
        if (!$this->require_super_user()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $result = $this->Access_log_model->clear_all();
        if (!$result['success']) {
            return $this->json(FALSE, $result['message']);
        }

        return $this->json(TRUE, 'All access logs were deleted successfully.');
    }

    /**
     * Create a new file type or update an existing record.
     */
    public function save_file_type()
    {
        if (!$this->require_super_user()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $id = (int) $this->input->post('filetype_id');
        $type = $this->normalize_file_type($this->input->post('type'));

        if ($type === '') {
            return $this->json(FALSE, 'File type is required.', array('field' => 'filetype-name'));
        }

        if (!preg_match('/^\.[A-Za-z0-9]{1,15}$/', $type)) {
            return $this->json(
                FALSE,
                'Enter an extension beginning with a dot, for example .pdf.',
                array('field' => 'filetype-name')
            );
        }

        if ($this->Filetype_model->exists($type, $id)) {
            return $this->json(FALSE, 'That file type already exists.', array('field' => 'filetype-name'));
        }

        if ($id > 0) {
            if (!$this->Filetype_model->find($id)) {
                return $this->json(FALSE, 'The selected file type no longer exists.');
            }

            $saved = $this->Filetype_model->update_type($id, $type);
            $message = 'File type was updated successfully.';
        } else {
            $saved = $this->Filetype_model->create($type);
            $message = 'File type was added successfully.';
        }

        if (!$saved) {
            return $this->json(FALSE, 'The file type could not be saved. Please try again.');
        }

        return $this->json(TRUE, $message);
    }

    /**
     * Enable or disable one or more selected file types.
     */
    public function set_file_type_status()
    {
        if (!$this->require_super_user()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $ids = $this->clean_ids($this->input->post('ids'));
        $status = (int) $this->input->post('status');

        if (count($ids) === 0) {
            return $this->json(FALSE, 'Select at least one file type.');
        }

        if (!in_array($status, array(0, 1), TRUE)) {
            return $this->json(FALSE, 'Invalid file type status.');
        }

        if (!$this->Filetype_model->set_status($ids, $status)) {
            return $this->json(FALSE, 'The selected file types could not be updated.');
        }

        return $this->json(
            TRUE,
            $status === 0 ? 'Selected file types were enabled.' : 'Selected file types were disabled.'
        );
    }

    /**
     * Permanently remove one or more selected file types.
     */
    public function delete_file_types()
    {
        if (!$this->require_super_user()) {
            return;
        }

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $ids = $this->clean_ids($this->input->post('ids'));
        if (count($ids) === 0) {
            return $this->json(FALSE, 'Select at least one file type.');
        }

        if (!$this->Filetype_model->delete_many($ids)) {
            return $this->json(FALSE, 'The selected file types could not be deleted.');
        }

        return $this->json(TRUE, 'Selected file types were deleted successfully.');
    }

    /**
     * Convert user input to the extension format retained by the old system.
     *
     * @param string $type
     * @return string
     */
    private function normalize_file_type($type)
    {
        $type = strtolower(trim((string) $type));
        if ($type !== '' && substr($type, 0, 1) !== '.') {
            $type = '.' . $type;
        }
        return $type;
    }

    /**
     * Accept only unique positive numeric identifiers from an AJAX request.
     *
     * @param mixed $ids
     * @return array
     */
    private function clean_ids($ids)
    {
        $clean = array();
        if (!is_array($ids)) {
            return $clean;
        }

        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return array_values($clean);
    }

    /**
     * Validate and save the submitted settings through an AJAX request.
     */
    public function save()
    {
        if (!$this->require_super_user()) {
            return;
        }

        // Saving is intentionally restricted to POST requests.
        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }

        $posted = $this->input->post('settings');
        if (!is_array($posted)) {
            return $this->json(FALSE, 'No system settings were submitted.');
        }

        // Use database records as the allowlist; unknown setting IDs are ignored.
        $existing = $this->System_setting_model->get_all_indexed();
        $updates = array();

        foreach ($existing as $id => $row) {
            if (!array_key_exists($id, $posted)) {
                continue;
            }

            $value = trim((string) $posted[$id]);

            if ($value === '') {
                return $this->json(
                    FALSE,
                    $row['name'] . ' is required.',
                    array('field' => 'setting-' . $id)
                );
            }

            if (strlen($value) > 100) {
                return $this->json(
                    FALSE,
                    $row['name'] . ' must not exceed 100 characters.',
                    array('field' => 'setting-' . $id)
                );
            }

            // Setting 3 stores the legacy Yes/No maintenance value.
            if ((int) $id === 3 && !in_array($value, array('Yes', 'No'), TRUE)) {
                return $this->json(FALSE, 'Maintenance mode must be Yes or No.', array('field' => 'setting-3'));
            }

            // Settings 5 and 6 must remain positive whole-number limits.
            if (in_array((int) $id, array(5, 6), TRUE) && (!ctype_digit($value) || (int) $value < 1)) {
                return $this->json(
                    FALSE,
                    $row['name'] . ' must be a whole number greater than zero.',
                    array('field' => 'setting-' . $id)
                );
            }

            $updates[(int) $id] = $value;
        }

        if (count($updates) === 0) {
            return $this->json(FALSE, 'No recognized settings were submitted.');
        }

        if (!$this->System_setting_model->update_values($updates)) {
            return $this->json(FALSE, 'The system settings could not be updated. Please try again.');
        }

        return $this->json(TRUE, 'Global configuration was updated successfully.');
    }

    /**
     * Return a consistent JSON response and refresh the CSRF token.
     *
     * @param bool   $success
     * @param string $message
     * @param array  $extra
     * @return CI_Output
     */
    private function json($success, $message, $extra = array())
    {
        $data = is_array($extra) ? $extra : array();
        $data['success'] = (bool) $success;
        $data['message'] = (string) $message;
        $data['csrfName'] = $this->security->get_csrf_token_name();
        $data['csrfHash'] = $this->security->get_csrf_hash();
        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
