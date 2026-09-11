<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Regular-user authentication and first portal screen.
 *
 * Administrator roles continue to use /administrator. This controller owns a
 * separate user session so portal authorization is explicit on every request.
 */
class User_portal extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        /* Load only the services shared by all User Portal actions. */
        $this->config->load('rms_auth');
        $this->load->database();
        $this->load->helper(array('url', 'form', 'security'));
        $this->load->library(array('session', 'form_validation'));
        $this->load->model('User_portal_model');

        /*
         * ACTIVITY LOG:
         * Load the RMS CI daily activity logger.
         *
         * The logger writes only to:
         * /var/www/html/rms_ci/assets/activity_log/
         *
         * No old /rms activity-log files are used here.
         */
        $this->load->model('Access_log_model');
    }

    /* Display and process the regular-user login form. */
    public function login()
    {
        /* A valid portal session should never see the login form again. */
        if ($this->session->userdata('rms_portal_logged_in') === TRUE) {
            redirect('portal/dashboard');
            return;
        }

        $data = array(
            'page_title'   => 'User Login',
            'system_name'  => $this->config->item('rms_auth_system_name'),
            'company_name' => $this->config->item('rms_auth_company_name'),
            'login_error'  => '',
            'admin_account_restricted' => FALSE
        );

        $this->form_validation->set_rules('username', 'Username', 'trim|required|max_length[25]');
        $this->form_validation->set_rules('password', 'Password', 'required|max_length[50]');

        /* Authenticate only after CodeIgniter has accepted both fields. */
        if ($this->form_validation->run() === TRUE) {
            $result = $this->User_portal_model->authenticate(
                $this->input->post('username', TRUE),
                (string) $this->input->post('password', FALSE)
            );

            if ($result['success'] === TRUE) {
                $user = $result['user'];

                /* Rotate the session ID before storing authenticated data. */
                $this->session->sess_regenerate(TRUE);
                $this->session->set_userdata(array(
                    'rms_portal_user_id'      => (int) $user['user_id'],
                    'rms_portal_username'     => $user['username'],
                    'rms_portal_display_name' => $user['emp_name'],
                    'rms_portal_role'         => (int) $user['role_id'],
                    'rms_portal_department'   => $user['dept_name'],
                    'rms_portal_subsidiary'   => $user['sub_name'],
                    'rms_portal_logged_in'    => TRUE
                ));

                /*
                 * ACTIVITY LOG - SUCCESSFUL PORTAL LOGIN
                 *
                 * This runs only after authenticate() succeeds.
                 * The user's password is never written to the log.
                 */
                $this->Access_log_model->append_activity(
                    $user['username'],
                    '[Portal] Login',
                    'Records'
                );

                redirect('portal/dashboard');
                return;
            }

            $data['login_error'] = $result['message'];
            $data['admin_account_restricted'] = isset($result['reason'])
                && $result['reason'] === 'administrator_portal';
        }

        $this->load->view('user/login', $data);
    }

    /* Show the first document-focused portal workspace. */
    public function dashboard()
    {
        /* Every protected portal screen must pass this session check. */
        if (!$this->require_portal_session()) {
            return;
        }

        $user_id = (int) $this->session->userdata('rms_portal_user_id');
        $data = array(
            'page_title'       => 'My Workspace',
            'display_name'     => $this->session->userdata('rms_portal_display_name'),
            'username'         => $this->session->userdata('rms_portal_username'),
            'department'       => $this->session->userdata('rms_portal_department'),
            'subsidiary'       => $this->session->userdata('rms_portal_subsidiary'),
            'permission_count' => $this->User_portal_model->count_permissions($user_id)
        );

        $this->load->view('user/dashboard', $data);
    }

    /* Display only document records assigned to the current portal user. */
    public function documents()
    {
        if (!$this->require_portal_session()) {
            return;
        }

        $user_id = (int) $this->session->userdata('rms_portal_user_id');
        $search = trim((string) $this->input->get('search', TRUE));
        /*
         * Folder tokens contain only server-generated numeric path IDs.
         * Read the raw query value so legacy XSS filtering cannot rewrite the
         * transport token before the model validates it.
         */
        $folder_token = trim((string) $this->input->get('folder', FALSE));
        $browser = $this->User_portal_model->get_authorized_browser(
            $user_id,
            $folder_token,
            $search
        );

        /* A changed folder token must never reveal another user's branch. */
        if ($browser === FALSE) {
            show_error('This folder is unavailable or is not assigned to your account.', 404);
            return;
        }

        /* During search, show the number of globally matched authorized files. */
        $total = $this->User_portal_model->count_authorized_documents($user_id, $search);

        /* Show the latest authorized files only at the root workspace. */
        $recent_documents = array();
        if ($folder_token === '' && $search === '') {
            $recent_documents = $this->User_portal_model->get_authorized_documents(
                $user_id,
                10,
                0,
                ''
            );
        }

        $data = array(
            'page_title' => 'My Documents',
            'display_name' => $this->session->userdata('rms_portal_display_name'),
            'username' => $this->session->userdata('rms_portal_username'),
            'department' => $this->session->userdata('rms_portal_department'),
            'subsidiary' => $this->session->userdata('rms_portal_subsidiary'),
            'role_id' => (int) $this->session->userdata('rms_portal_role'),
            'folders' => $browser['folders'],
            'documents' => $browser['documents'],
            'recent_documents' => $recent_documents,
            'breadcrumbs' => $browser['breadcrumbs'],
            'current_folder' => $folder_token,
            'parent_folder' => $browser['parent_token'],
            'document_count' => $total,
            'search' => $search
        );

        $this->load->view('user/documents', $data);
    }

    /* Display and securely update the signed-in user's own profile. */
    public function profile()
    {
        if (!$this->require_portal_session()) {
            return;
        }

        $user_id = (int) $this->session->userdata('rms_portal_user_id');
        $profile = $this->User_portal_model->get_profile($user_id);
        if ($profile === FALSE) {
            show_error('Your profile could not be loaded.', 404);
            return;
        }

        $success_message = '';
        $error_message = '';

        $this->form_validation->set_rules('complete_name', 'Complete name', 'trim|required|max_length[150]');
        $this->form_validation->set_rules('username', 'Username', 'trim|required|max_length[25]');
        $this->form_validation->set_rules('new_password', 'New password', 'trim|min_length[8]|max_length[50]');
        $this->form_validation->set_rules('confirm_password', 'Confirm new password', 'trim|matches[new_password]');

        /* Process only submitted profile forms, not the initial page request. */
        if ($this->input->method(TRUE) === 'POST' && $this->form_validation->run() === TRUE) {
            $complete_name = trim((string) $this->input->post('complete_name', TRUE));
            $username = trim((string) $this->input->post('username', TRUE));
            $new_password = (string) $this->input->post('new_password', FALSE);

            if ($this->User_portal_model->username_exists_for_other_user($username, $user_id)) {
                $error_message = 'That username is already used by another account.';
            } else {
                $updated = $this->User_portal_model->update_profile(
                    $user_id,
                    $complete_name,
                    $username,
                    $new_password
                );

                if ($updated) {
                    $this->session->set_userdata(array(
                        'rms_portal_display_name' => $complete_name,
                        'rms_portal_username' => $username
                    ));
                    $success_message = 'Your profile changes were saved successfully.';
                    $profile = $this->User_portal_model->get_profile($user_id);
                } else {
                    $error_message = 'Your profile could not be saved. Please try again.';
                }
            }
        } elseif ($this->input->method(TRUE) === 'POST') {
            $error_message = 'Please correct the highlighted profile fields.';
        }

        $data = array(
            'page_title' => 'Edit Profile',
            'display_name' => $this->session->userdata('rms_portal_display_name'),
            'username' => $this->session->userdata('rms_portal_username'),
            'department' => $this->session->userdata('rms_portal_department'),
            'subsidiary' => $this->session->userdata('rms_portal_subsidiary'),
            'profile' => $profile,
            'success_message' => $success_message,
            'error_message' => $error_message
        );

        $this->load->view('user/profile', $data);
    }

    /* Open a protected viewer copy in the browser. */
    public function view_document($token)
    {
        $this->serve_document($token, FALSE);
    }

    /* Download an original document for Viewer Level 2 accounts only. */
    public function download_document($token)
    {
        if (!$this->require_portal_session()) {
            return;
        }
        if ((int) $this->session->userdata('rms_portal_role') !== 3) {
            show_error('Your account has view-only document access.', 403);
            return;
        }
        $this->serve_document($token, TRUE);
    }

    /* Download selected authorized originals in one ZIP archive. */
    public function download_selected()
    {
        if (!$this->require_portal_session()) {
            return;
        }

        /* Batch download follows the same Viewer Level 2 rule as one file. */
        if ((int) $this->session->userdata('rms_portal_role') !== 3) {
            show_error('Your account has view-only document access.', 403);
            return;
        }

        $tokens = $this->input->post('document_tokens', FALSE);
        if (!is_array($tokens)) {
            show_error('Select at least one document to download.', 400);
            return;
        }

        /* Limit one archive request to a practical number of selected files. */
        $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
        if (count($tokens) === 0 || count($tokens) > 50) {
            show_error('Select between 1 and 50 documents for one download.', 400);
            return;
        }
        if (!class_exists('ZipArchive')) {
            show_error('ZIP downloads are unavailable on this server. Enable the PHP ZIP extension.', 500);
            return;
        }

        $temporary_path = tempnam(sys_get_temp_dir(), 'rms_selected_');
        $zip = new ZipArchive();
        if ($temporary_path === FALSE || $zip->open($temporary_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            if ($temporary_path !== FALSE && is_file($temporary_path)) {
                @unlink($temporary_path);
            }
            show_error('The selected download could not be prepared.', 500);
            return;
        }

        $user_id = (int) $this->session->userdata('rms_portal_user_id');
        $used_names = array();

        /*
         * ACTIVITY LOG:
         * Keep the real selected filenames while the existing ZIP process runs.
         * We do not write the log until every selected file has been validated
         * and the ZIP archive has been prepared successfully.
         */
        $downloaded_names = array();

        /* Revalidate every selected token before adding any original file. */
        foreach ($tokens as $token) {
            $document = $this->User_portal_model->find_authorized_document($user_id, trim($token), TRUE);
            $path = $document ? $this->User_portal_model->resolve_document_path($document, TRUE) : FALSE;
            if (!$document || $path === FALSE || !is_file($path) || !is_readable($path)) {
                $zip->close();
                @unlink($temporary_path);
                show_error('One selected document is unavailable or is not assigned to your account.', 404);
                return;
            }

            $archive_name = $this->make_unique_archive_name($document['served_name'], $used_names);

            /*
             * Keep the real served filename for the activity log.
             * This does not change the ZIP filename or storage path.
             */
            $downloaded_names[] = basename((string) $document['served_name']);

            if (!$zip->addFile($path, $archive_name)) {
                $zip->close();
                @unlink($temporary_path);
                show_error('One selected document could not be added to the ZIP file.', 500);
                return;
            }
        }

        $zip->close();
        $download_name = 'RMS-selected-documents-' . date('Y-m-d-His') . '.zip';

        /*
         * ACTIVITY LOG - SUCCESSFUL SELECTED DOWNLOAD
         *
         * At this point every selected file passed authorization/readability
         * checks and was added to the ZIP. Record each real filename.
         *
         * Example:
         * user|...|[Portal] Downloaded: upload_1.png|Records
         */
        $portal_username = (string) $this->session->userdata(
            'rms_portal_username'
        );

        foreach ($downloaded_names as $downloaded_name) {
            $this->Access_log_model->append_portal_activity(
                $portal_username,
                'Downloaded',
                $downloaded_name
            );
        }

        /* Stream the temporary archive and remove it immediately afterward. */
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . filesize($temporary_path));
        readfile($temporary_path);
        @unlink($temporary_path);
        exit;
    }

    /* Keep duplicate source filenames distinct inside the ZIP archive. */
    private function make_unique_archive_name($name, &$used_names)
    {
        $safe_name = basename(str_replace('\\', '/', (string) $name));
        if ($safe_name === '' || $safe_name === '.' || $safe_name === '..') {
            $safe_name = 'document';
        }

        $candidate = $safe_name;
        $extension = pathinfo($safe_name, PATHINFO_EXTENSION);
        $stem = $extension !== '' ? substr($safe_name, 0, -(strlen($extension) + 1)) : $safe_name;
        $counter = 2;

        while (isset($used_names[strtolower($candidate)])) {
            $candidate = $stem . ' (' . $counter . ')' . ($extension !== '' ? '.' . $extension : '');
            $counter++;
        }

        $used_names[strtolower($candidate)] = TRUE;
        return $candidate;
    }

    /* Revalidate permission and stream the resolved storage file. */
    private function serve_document($token, $download)
    {
        if (!$this->require_portal_session()) {
            return;
        }
        $user_id = (int) $this->session->userdata('rms_portal_user_id');
        $document = $this->User_portal_model->find_authorized_document($user_id, $token, $download);
        if (!$document) {
            show_error('The document is unavailable or is not assigned to your account.', 404);
            return;
        }
        $path = $this->User_portal_model->resolve_document_path($document, $download);
        if ($path === FALSE || !is_file($path) || !is_readable($path)) {
            show_error('The document file could not be found in storage.', 404);
            return;
        }
        $name = basename($document['served_name']);
        $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';

        /*
         * ACTIVITY LOG - SUCCESSFUL SINGLE VIEW / DOWNLOAD
         *
         * This runs only after:
         * - the portal session is valid,
         * - the user is authorized,
         * - the document record exists,
         * - the physical file exists and is readable.
         *
         * Failed/denied requests are therefore not recorded as successful.
         */
        $this->Access_log_model->append_portal_activity(
            $this->session->userdata('rms_portal_username'),
            $download ? 'Downloaded' : 'Viewed',
            $name
        );

        /* Stream the binary directly. The CI output pipeline can prepend
         * buffered warnings/notices and corrupt otherwise valid images/PDFs,
         * which makes the Level 1/2 document viewer show a broken preview. */
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        if (function_exists('header_remove')) {
            @header_remove('Content-Type');
            @header_remove('Content-Length');
        }

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header(
            'Content-Disposition: ' .
                ($download ? 'attachment' : 'inline') .
                '; filename="' . str_replace('"', '', $name) . '"'
        );
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $handle = @fopen($path, 'rb');
        if ($handle === FALSE) {
            show_error('The document file could not be opened from storage.', 500);
            return;
        }

        fpassthru($handle);
        fclose($handle);
        exit;
    }

    /* End the portal session and return the legacy account to offline. */
    public function logout()
    {
        /*
         * Preserve the username before the session is destroyed so the
         * logout activity can still identify the correct portal user.
         */
        $portal_username = (string) $this->session->userdata(
            'rms_portal_username'
        );

        /* Preserve the legacy online/offline account status on sign-out. */
        $user_id = (int) $this->session->userdata('rms_portal_user_id');
        if ($user_id > 0) {
            $this->User_portal_model->mark_offline($user_id);
        }

        /*
         * ACTIVITY LOG - PORTAL LOGOUT
         */
        if (trim($portal_username) !== '') {
            $this->Access_log_model->append_activity(
                $portal_username,
                '[Portal] Logout',
                'Records'
            );
        }

        $this->session->sess_destroy();
        redirect('portal');
    }

    /*
     * Validate the session and refresh access changed by an administrator.
     *
     * The database remains the source of truth for account status and Viewer
     * level. This keeps both the visible buttons and protected download route
     * synchronized with the Administrator Users module.
     */
    private function require_portal_session()
    {
        /* Reject missing sessions before making a database request. */
        if ($this->session->userdata('rms_portal_logged_in') !== TRUE) {
            redirect('portal');
            return FALSE;
        }

        $user_id = (int) $this->session->userdata('rms_portal_user_id');
        $access = $this->User_portal_model->get_portal_access($user_id);
        $status = $access !== FALSE ? (int) $access['status'] : -1;

        /* Status 3 means the administrator requested a forced logout. */
        if ($status !== 1) {
            if ($status === 3) {
                $this->User_portal_model->mark_offline($user_id);
            }
            $this->session->sess_destroy();
            redirect('portal');
            return FALSE;
        }

        /* Administrator roles must continue using the separate Admin Portal. */
        if ((int) $access['role_id'] <= 2) {
            $this->session->sess_destroy();
            redirect('portal');
            return FALSE;
        }

        /* Refresh only the access value that controls View and Download. */
        $this->session->set_userdata(
            'rms_portal_role',
            (int) $access['role_id']
        );

        return TRUE;
    }
}