<?php
/*
 * Users controller
 *
 * Purpose:
 * - Displays the Manage Users list.
 * - Processes New User, Edit Info, and Edit Access inside Users-page modals.
 * - Protects both pages with the existing RMS login and role rules.
 *
 * Database work is delegated to User_model so SQL-related logic stays out of
 * the controller. This controller does not alter the database structure.
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends CI_Controller
{
    /**
     * Loads everything used by all actions in this controller.
     */
    public function __construct()
    {
        parent::__construct();

        // Load RMS-specific settings such as system name and dashboard routes.
        $this->config->load('rms_auth');

        // URL/form helpers build links and forms; security supports safe input handling.
        $this->load->helper(array('url', 'form', 'security'));

        // Session identifies the logged-in user; form_validation checks submitted fields.
        $this->load->library(array('session', 'form_validation'));

        // Open the configured CI3 database connection and load the Users model.
        $this->load->database();
        $this->load->model('User_model');
    }

    /**
     * Allows access only when the RMS login session is active.
     *
     * @return bool TRUE when logged in; otherwise redirects and returns FALSE.
     */
    private function require_login()
    {
        if ($this->session->userdata('rms_logged_in') !== TRUE) {
            redirect('administrator/');
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Allows only Super User (1) and Administrator (2) to manage accounts.
     *
     * @return bool TRUE when the logged-in user may manage accounts.
     */
    private function require_user_manager()
    {
        if (!$this->require_login()) {
            return FALSE;
        }

        $role_id = (int) $this->session->userdata('rms_role');

        if ($role_id !== 1 && $role_id !== 2) {
            show_error('You are not allowed to manage user accounts.', 403);
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Creates the shared data used by the Users list and New User views.
     *
     * This keeps repeated header, profile, and navigation values in one place.
     *
     * @param string $page_title Browser and page title.
     * @return array
     */
    private function common_view_data($page_title)
    {
        return array(
            'page_title'      => $page_title,
            'system_name'     => $this->config->item('rms_auth_system_name'),
            'company_name'    => $this->config->item('rms_auth_company_name'),
            'display_name'    => $this->session->userdata('rms_display_name'),
            'username'        => $this->session->userdata('rms_username'),
            'position'        => $this->session->userdata('rms_position'),
            'current_user_id' => (int) $this->session->userdata('rms_user_id'),
            'current_role'    => (int) $this->session->userdata('rms_role'),
            'routes'          => $this->config->item('rms_dashboard_routes')
        );
    }

    /**
     * Builds the read-only Users list data used by both list and modal responses.
     *
     * GET parameters keep the existing search, sorting, and pagination process.
     * The model still runs only COUNT and SELECT queries for this portion.
     *
     * @return array
     */
    private function users_list_data()
    {
        // Read filter and pagination inputs. The TRUE flag applies CI3 XSS filtering.
        $allowed_page_sizes = array(10, 25, 50, 100);
        $per_page = (int) $this->input->get('per_page', TRUE);
        $search = trim((string) $this->input->get('search', TRUE));
        $sort = strtolower((string) $this->input->get('sort', TRUE));
        $direction = strtolower((string) $this->input->get('direction', TRUE));

        // Reject unexpected page sizes so a URL cannot request an unlimited list.
        if (!in_array($per_page, $allowed_page_sizes, TRUE)) {
            $per_page = 10;
        }

        // Only these named sort choices can be passed to the model.
        $allowed_sorts = array(
            'username',
            'emp_name',
            'sub_name',
            'dept_name',
            'role',
            'status',
            'uploader',
            'registered',
            'last_visit'
        );

        // Use employee name as the safe default when an invalid sort is requested.
        if (!in_array($sort, $allowed_sorts, TRUE)) {
            $sort = 'emp_name';
        }

        // The list defaults to ascending unless DESC was explicitly requested.
        if ($direction !== 'desc') {
            $direction = 'asc';
        }

        // Read-only COUNT query determines the matching rows and number of pages.
        $total_users = $this->User_model->count_users($search);
        $total_pages = max(1, (int) ceil($total_users / $per_page));
        $current_page = (int) $this->input->get('page', TRUE);

        // Keep the requested page inside the available page range.
        if ($current_page < 1) {
            $current_page = 1;
        } elseif ($current_page > $total_pages) {
            $current_page = $total_pages;
        }

        $offset = ($current_page - 1) * $per_page;

        return array(
            'users'        => $this->User_model->get_users(
                $per_page,
                $offset,
                $search,
                $sort,
                $direction
            ),
            'total_users'  => $total_users,
            'current_page' => $current_page,
            'total_pages'  => $total_pages,
            'first_row'    => $total_users > 0 ? $offset + 1 : 0,
            'last_row'     => min($offset + $per_page, $total_users),
            'search'       => $search,
            'sort'         => $sort,
            'direction'    => $direction,
            'per_page'     => $per_page
        );
    }

    /**
     * Loads existing lookup records needed by the New User modal.
     *
     * These are read-only SELECT queries against user_role, subsidiaries, and
     * departments. They do not create or change database records.
     *
     * @param string $error_message Business-rule error to show in the modal.
     * @param mixed  $created_account One-time username/password success data.
     * @param bool   $modal_open Whether the modal must open after rendering.
     * @return array
     */
    private function create_modal_data($error_message, $created_account, $modal_open)
    {
        $creator_role = (int) $this->session->userdata('rms_role');
        $can_create_user = $creator_role === 1 || $creator_role === 2;

        return array(
            'can_create_user'  => $can_create_user,
            'roles'            => $can_create_user
                ? $this->User_model->get_assignable_roles($creator_role)
                : array(),
            'subsidiaries'     => $can_create_user
                ? $this->User_model->get_subsidiaries()
                : array(),
            'departments'      => $can_create_user
                ? $this->User_model->get_departments()
                : array(),
            'error_message'    => (string) $error_message,
            'created_account'  => $created_account,
            'open_create_modal'=> (bool) $modal_open,
            'create_form_active'=> (bool) $modal_open
        );
    }

    /**
     * Loads the selected account and lookup records for the Edit User modal.
     *
     * Every query here is read-only. The selected user's password is never
     * loaded because the existing hash must not be displayed in the browser.
     *
     * @param int    $user_id Selected users.user_id value.
     * @param string $error_message Validation or business-rule error.
     * @param string $success_message One-time update confirmation.
     * @param bool   $modal_open Whether the Edit modal should be visible.
     * @return array
     */
    private function edit_modal_data($user_id, $error_message, $success_message, $modal_open)
    {
        $manager_role = (int) $this->session->userdata('rms_role');
        $user = $user_id > 0 ? $this->User_model->get_user_for_edit($user_id) : array();

        // Prevent editing the signed-in account, Super Users, or an equal manager.
        if ($user && !$this->User_model->user_is_manageable(
            $user,
            (int) $this->session->userdata('rms_user_id'),
            $manager_role
        )) {
            $user = array();
            $error_message = 'This protected account cannot be edited.';
        }

        return array(
            'edit_user'          => $user,
            'edit_roles'         => $user
                ? $this->User_model->get_assignable_roles($manager_role)
                : array(),
            'edit_subsidiaries'  => $user
                ? $this->User_model->get_subsidiaries()
                : array(),
            'edit_departments'   => $user
                ? $this->User_model->get_departments()
                : array(),
            'edit_error_message' => (string) $error_message,
            'edit_success'       => (string) $success_message,
            'open_edit_modal'    => (bool) ($modal_open && $user),
            'edit_form_active'   => (bool) $modal_open
        );
    }

    /**
     * Displays the Manage Users list and supplies the embedded New User modal.
     */
    public function index()
    {
        if (!$this->require_login()) {
            return;
        }

        $created_account = $this->session->flashdata('rms_new_user_success');
        $requested_modal = $this->input->get('new_user', TRUE) === '1';
        $requested_edit_id = (int) $this->input->get('edit_user', TRUE);
        $edit_success = (string) $this->session->flashdata('rms_edit_user_success');

        // Never open two account dialogs at once; Edit takes priority when requested.
        if ($requested_edit_id > 0) {
            $requested_modal = FALSE;
        }

        // Combine layout, list, and modal data in one responsive Users page.
        $data = array_merge(
            $this->common_view_data('Users'),
            $this->users_list_data(),
            $this->create_modal_data(
                '',
                $created_account,
                $requested_modal || is_array($created_account)
            ),
            $this->edit_modal_data(
                $requested_edit_id,
                '',
                $edit_success,
                $requested_edit_id > 0
            )
        );

        // Render the Users list after all output values have been prepared.
        $this->load->view('admin/users', $data);
    }

    /**
     * Processes the New User modal form.
     *
     * GET requests return to the Users page and open its modal. POST preserves
     * the existing validation, duplicate checks, and one-record INSERT.
     */
    public function create()
    {
        if (!$this->require_user_manager()) {
            return;
        }

        // A direct users/create link now opens the modal on the Users page.
        if ($this->input->method(TRUE) !== 'POST') {
            redirect('administrator/users?new_user=1');
            return;
        }

        $creator_role = (int) $this->session->userdata('rms_role');
        $error_message = '';

        // Validation rules preserve the accepted fields and limits of the old process.
        $this->form_validation->set_rules(
            'cname',
            'Complete name',
            'trim|required|max_length[150]|regex_match[/^[A-Za-z .\'-]+$/]'
        );
        $this->form_validation->set_rules(
            'username',
            'Username',
            'trim|required|max_length[25]|regex_match[/^[A-Za-z0-9 _.-]+$/]'
        );
        $this->form_validation->set_rules(
            'password',
            'Password',
            'required|min_length[6]|max_length[50]'
        );
        $this->form_validation->set_rules(
            'subsidiary',
            'Subsidiary',
            'required|integer'
        );
        $this->form_validation->set_rules(
            'department',
            'Department',
            'required|integer'
        );
        $this->form_validation->set_rules(
            'role',
            'User level',
            'required|integer'
        );

        // The insert path runs only when all server-side validation rules pass.
        if ($this->form_validation->run() === TRUE) {
            // Read the validated form values and convert numeric IDs to integers.
            $complete_name = trim((string) $this->input->post('cname', TRUE));
            $username = trim((string) $this->input->post('username', TRUE));
            $password = (string) $this->input->post('password', FALSE);
            $subsidiary_id = (int) $this->input->post('subsidiary', TRUE);
            $department_id = (int) $this->input->post('department', TRUE);
            $role_id = (int) $this->input->post('role', TRUE);
            $allowed_upload = $this->input->post('al_upload', TRUE) === '1' ? 1 : 0;

            /*
             * Perform business-rule checks before inserting:
             * 1. Department must belong to the selected subsidiary.
             * 2. Logged-in manager must be allowed to assign the selected role.
             * 3. Username and employee name must not already exist.
             */
            if (!$this->User_model->department_belongs_to_subsidiary(
                $department_id,
                $subsidiary_id
            )) {
                $error_message = 'Select a valid department for the chosen subsidiary.';
            } elseif (!$this->User_model->role_is_assignable($role_id, $creator_role)) {
                $error_message = 'Select a valid user level.';
            } elseif ($this->User_model->username_exists($username)) {
                $error_message = $username.' is already taken.';
            } elseif ($this->User_model->employee_name_exists($complete_name)) {
                $error_message = $complete_name.' is already registered.';
            } else {
                /*
                 * Insert one compatible users record.
                 * MD5 is retained only because the existing RMS login/database
                 * process currently uses legacy MD5 passwords.
                 */
                $result = $this->User_model->create_user(array(
                    'username'       => $username,
                    'password'       => md5($password),
                    'emp_name'       => ucwords(strtolower($complete_name)),
                    'role_id'        => $role_id,
                    'dept_id'        => $department_id,
                    'date_registered'=> date('Y/m/d g:ia'),
                    'last_date_visit'=> '',
                    // A new account has no active session until its first login.
                    'stat'           => 0,
                    'conf_stat'      => 0,
                    'allowed_upload' => $allowed_upload
                ));

                if ($result !== FALSE) {
                    // Flash data survives one redirect and shows the generated password once.
                    $this->session->set_flashdata('rms_new_user_success', array(
                        'username' => $username,
                        'password' => $password
                    ));
                    // Return to the list and reopen the modal to show credentials once.
                    redirect('administrator/users?new_user=1');
                    return;
                }

                $error_message = 'The user could not be saved. No record was added.';
            }
        }

        // Re-render the Users page with the modal open and validation preserved.
        $data = array_merge(
            $this->common_view_data('Users'),
            $this->users_list_data(),
            $this->create_modal_data($error_message, NULL, TRUE),
            $this->edit_modal_data(0, '', '', FALSE)
        );

        $this->load->view('admin/users', $data);
    }

    /**
     * Forces one selected online account to log out.
     *
     * The old RMS uses users.stat = 3 as the forced-logout signal. This action
     * keeps that process, accepts POST only, and changes no other account data.
     */
    public function logout()
    {
        if (!$this->require_user_manager()) {
            return;
        }

        if ($this->input->method(TRUE) !== 'POST') {
            redirect('administrator/users');
            return;
        }

        $user_id = (int) $this->input->post('user_id', TRUE);
        $manager_role = (int) $this->session->userdata('rms_role');
        $selected_user = $this->User_model->get_user_for_edit($user_id);

        if (!$selected_user || !$this->User_model->user_is_manageable(
            $selected_user,
            (int) $this->session->userdata('rms_user_id'),
            $manager_role
        )) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The selected account is protected and was not changed.'
            );
            redirect('administrator/users');
            return;
        }

        $current_status = isset($selected_user['stat'])
            ? (int) $selected_user['stat']
            : 0;

        if ($current_status === 2) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The selected account is blocked. Unblock it before forcing logout.'
            );
            redirect('administrator/users');
            return;
        }

        if ($current_status === 0 || $current_status === 3) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                $current_status === 3
                    ? 'The selected account is already marked for forced logout.'
                    : 'The selected account is already offline.'
            );
            redirect('administrator/users');
            return;
        }

        if ($current_status !== 1) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The selected account is not in a logout-ready status.'
            );
            redirect('administrator/users');
            return;
        }

        if ($this->User_model->force_logout_user($user_id)) {
            $this->session->set_flashdata(
                'rms_user_action_success',
                array(
                    'kicker' => 'LOGOUT COMPLETE',
                    'title'   => 'Logged out successfully',
                    'message' => 'The selected user was marked for logout successfully.'
                )
            );
        } else {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The user could not be logged out because the account status changed.'
            );
        }

        redirect('administrator/users');
    }

    /**
     * Permanently deletes one manageable account and its saved file access.
     *
     * The action is POST-only. The current account, Super Users, and peer
     * Administrator accounts remain protected by the same rule used by Edit.
     */
    public function remove()
    {
        if (!$this->require_user_manager()) {
            return;
        }

        if ($this->input->method(TRUE) !== 'POST') {
            redirect('administrator/users');
            return;
        }

        $user_id = (int) $this->input->post('user_id', TRUE);
        $manager_role = (int) $this->session->userdata('rms_role');
        $selected_user = $this->User_model->get_user_for_edit($user_id);

        if (!$selected_user || !$this->User_model->user_is_manageable(
            $selected_user,
            (int) $this->session->userdata('rms_user_id'),
            $manager_role
        )) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The selected account is protected and was not deleted.'
            );
            redirect('administrator/users');
            return;
        }

        if ($this->User_model->delete_user($user_id)) {
            $this->session->set_flashdata(
                'rms_user_action_success',
                array(
                    'kicker' => 'DELETION COMPLETE',
                    'title'   => 'User deleted',
                    'message' => 'The selected user was deleted successfully.'
                )
            );
        } else {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The selected user could not be deleted. No partial deletion was saved.'
            );
        }

        redirect('administrator/users');
    }

    /**
     * Toggles one selected account between Blocked and Offline.
     *
     * The current database value decides the action, so a changed or repeated
     * browser request cannot force an unexpected status. All protection rules
     * are checked again on the server before the single-column UPDATE.
     */
    public function block()
    {
        if (!$this->require_user_manager()) {
            return;
        }

        // The toolbar action is POST-only; direct links simply return to Users.
        if ($this->input->method(TRUE) !== 'POST') {
            redirect('administrator/users');
            return;
        }

        $user_id = (int) $this->input->post('user_id', TRUE);
        $manager_role = (int) $this->session->userdata('rms_role');
        $selected_user = $this->User_model->get_user_for_edit($user_id);

        // Reuse Edit's protection for the current account, Super Users, and peers.
        if (!$selected_user || !$this->User_model->user_is_manageable(
            $selected_user,
            (int) $this->session->userdata('rms_user_id'),
            $manager_role
        )) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The selected account is protected and was not changed.'
            );
            redirect('administrator/users');
            return;
        }

        // Status 2 is Blocked; restoring access returns the account to Offline (0).
        $is_blocked = isset($selected_user['stat'])
            && (int) $selected_user['stat'] === 2;
        $new_status = $is_blocked ? 0 : 2;

        if ($this->User_model->set_block_status($user_id, $new_status)) {
            $this->session->set_flashdata(
                'rms_user_action_success',
                array(
                    'kicker' => 'STATUS UPDATED',
                    'title'   => 'Updated successfully',
                    'message' => $is_blocked
                        ? 'User account was unblocked successfully.'
                        : 'User account was blocked successfully.'
                )
            );
        } else {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The account status could not be changed. No other data was affected.'
            );
        }

        redirect('administrator/users');
    }

    /**
     * Assigns one selected account to an existing old RMS Viewer level.
     *
     * Level 1 maps to role_id 4 (view only); Level 2 maps to role_id 3
     * (view and download). This POST-only action changes no other users field
     * and reuses the same protection rules as Edit and Block.
     */
    public function viewer()
    {
        if (!$this->require_user_manager()) {
            return;
        }

        if ($this->input->method(TRUE) !== 'POST') {
            redirect('administrator/users');
            return;
        }

        $user_id = (int) $this->input->post('user_id', TRUE);
        $viewer_level = (int) $this->input->post('viewer_level', TRUE);
        $manager_role = (int) $this->session->userdata('rms_role');
        $selected_user = $this->User_model->get_user_for_edit($user_id);

        if (!$selected_user || !$this->User_model->user_is_manageable(
            $selected_user,
            (int) $this->session->userdata('rms_user_id'),
            $manager_role
        )) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The selected account is protected and its Viewer level was not changed.'
            );
            redirect('administrator/users');
            return;
        }

        if ($viewer_level !== 1 && $viewer_level !== 2) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'Select a valid Viewer level. No account data was changed.'
            );
            redirect('administrator/users');
            return;
        }

        // Preserve the old RMS mapping: Level 1 = role 4; Level 2 = role 3.
        $role_id = $viewer_level === 1 ? 4 : 3;

        if ($this->User_model->set_viewer_role($user_id, $role_id)) {
            $this->session->set_flashdata(
                'rms_user_action_success',
                array(
                    'kicker' => 'VIEWER UPDATED',
                    'title'   => 'Updated successfully',
                    'message' => $viewer_level === 1
                        ? 'Viewer Level 1 was assigned successfully (view only).'
                        : 'Viewer Level 2 was assigned successfully (view and download).'
                )
            );
        } else {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The Viewer level could not be changed. No other data was affected.'
            );
        }

        redirect('administrator/users');
    }

    /**
     * Sets the old RMS upload permission for one selected account.
     *
     * The action is POST-only and changes only users.allowed_upload. The
     * selected account is checked again on the server so protected/current
     * accounts cannot be changed through a manually submitted request.
     */
    public function uploader()
    {
        if (!$this->require_user_manager()) {
            return;
        }

        if ($this->input->method(TRUE) !== 'POST') {
            redirect('administrator/users');
            return;
        }

        $user_id = (int) $this->input->post('user_id', TRUE);
        $permission = (int) $this->input->post('allowed_upload', TRUE);
        $manager_role = (int) $this->session->userdata('rms_role');
        $selected_user = $this->User_model->get_user_for_edit($user_id);

        if (!$selected_user || !$this->User_model->user_is_manageable(
            $selected_user,
            (int) $this->session->userdata('rms_user_id'),
            $manager_role
        )) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The selected account is protected and its upload permission was not changed.'
            );
            redirect('administrator/users');
            return;
        }

        if ($permission !== 0 && $permission !== 1) {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'Select a valid Uploader option. No account data was changed.'
            );
            redirect('administrator/users');
            return;
        }

        if ($this->User_model->set_uploader_permission($user_id, $permission)) {
            $this->session->set_flashdata(
                'rms_user_action_success',
                array(
                    'kicker' => 'UPLOADER UPDATED',
                    'title'   => 'Updated successfully',
                    'message' => $permission === 1
                        ? 'Uploader permission was enabled successfully.'
                        : 'Uploader permission was disabled successfully.'
                )
            );
        } else {
            $this->session->set_flashdata(
                'rms_user_status_error',
                'The upload permission could not be changed. No other data was affected.'
            );
        }

        redirect('administrator/users');
    }

    /**
     * Opens and processes the Edit User modal for one existing account.
     *
     * A blank password preserves the existing hash, matching the old RMS
     * updateUser() behavior. A supplied password is MD5-hashed only for
     * compatibility with the existing login process.
     *
     * @param int $user_id Existing users.user_id value from the route.
     */
    public function edit($user_id = 0)
    {
        if (!$this->require_user_manager()) {
            return;
        }

        $user_id = (int) $user_id;

        // GET keeps navigation simple: return to Users and open the Edit modal.
        if ($this->input->method(TRUE) !== 'POST') {
            redirect('administrator/users?edit_user='.$user_id);
            return;
        }

        $manager_role = (int) $this->session->userdata('rms_role');
        $selected_user = $this->User_model->get_user_for_edit($user_id);
        $error_message = '';

        // Stop before validation or UPDATE when the target is missing/protected.
        if (!$selected_user || !$this->User_model->user_is_manageable(
            $selected_user,
            (int) $this->session->userdata('rms_user_id'),
            $manager_role
        )) {
            show_error('The selected account cannot be edited.', 403);
            return;
        }

        // The editable fields and limits match the existing New User process.
        $this->form_validation->set_rules(
            'cname',
            'Complete name',
            'trim|required|max_length[150]|regex_match[/^[A-Za-z .\'-]+$/]'
        );
        $this->form_validation->set_rules(
            'username',
            'Username',
            'trim|required|max_length[25]|regex_match[/^[A-Za-z0-9 _.-]+$/]'
        );
        // Password is optional: blank means keep the current password unchanged.
        $this->form_validation->set_rules(
            'password',
            'New password',
            'trim|min_length[6]|max_length[50]'
        );
        $this->form_validation->set_rules('subsidiary', 'Subsidiary', 'required|integer');
        $this->form_validation->set_rules('department', 'Department', 'required|integer');
        $this->form_validation->set_rules('role', 'User level', 'required|integer');

        if ($this->form_validation->run() === TRUE) {
            $complete_name = trim((string) $this->input->post('cname', TRUE));
            $username = trim((string) $this->input->post('username', TRUE));
            $password = (string) $this->input->post('password', FALSE);
            $subsidiary_id = (int) $this->input->post('subsidiary', TRUE);
            $department_id = (int) $this->input->post('department', TRUE);
            $role_id = (int) $this->input->post('role', TRUE);

            // Validate references and duplicates before touching the selected row.
            if (!$this->User_model->department_belongs_to_subsidiary(
                $department_id,
                $subsidiary_id
            )) {
                $error_message = 'Select a valid department for the chosen subsidiary.';
            } elseif (!$this->User_model->role_is_assignable($role_id, $manager_role)) {
                $error_message = 'Select a valid user level.';
            } elseif ($this->User_model->username_exists($username, $user_id)) {
                $error_message = $username.' is already taken.';
            } elseif ($this->User_model->employee_name_exists($complete_name, $user_id)) {
                $error_message = $complete_name.' is already registered.';
            } else {
                $update_data = array(
                    'username' => $username,
                    'emp_name' => ucwords(strtolower($complete_name)),
                    'role_id'  => $role_id,
                    'dept_id'  => $department_id
                );

                // Add a new hash only when the manager generated a replacement password.
                if ($password !== '') {
                    $update_data['password'] = md5($password);
                }

                if ($this->User_model->update_user($user_id, $update_data)) {
                    // Edit User uses the same dedicated success presentation
                    // as Edit Access instead of the general toolbar-action popup.
                    $this->session->set_flashdata(
                        'rms_edit_user_success',
                        'User information was saved successfully.'
                    );
                    redirect('administrator/users');
                    return;
                }

                $error_message = 'The user could not be updated. No other record was changed.';
            }
        }

        // Re-render the directory with only the Edit modal open.
        $data = array_merge(
            $this->common_view_data('Users'),
            $this->users_list_data(),
            $this->create_modal_data('', NULL, FALSE),
            $this->edit_modal_data($user_id, $error_message, '', TRUE)
        );

        $this->load->view('admin/users', $data);
    }

    /**
     * Displays and updates the signed-in user's own profile.
     *
     * Organization, role, registration, and visit values remain read-only.
     * A blank password preserves the existing legacy-compatible password hash.
     */
    public function profile()
    {
        if (!$this->require_login()) {
            return;
        }

        $user_id = (int) $this->session->userdata('rms_user_id');
        $profile = $this->User_model->get_profile($user_id);

        if (!$profile) {
            show_error('Your account details could not be loaded.', 404);
            return;
        }

        $error_message = '';

        // Process only submitted forms; a normal GET simply displays the profile.
        if ($this->input->method(TRUE) === 'POST') {
            $this->form_validation->set_rules(
                'cname',
                'Complete name',
                'trim|required|max_length[150]|regex_match[/^[A-Za-z .\'-]+$/]'
            );
            $this->form_validation->set_rules(
                'username',
                'Username',
                'trim|required|max_length[25]|regex_match[/^[A-Za-z0-9 _.-]+$/]'
            );
            // Password is optional; when supplied, the existing minimum is enforced.
            $this->form_validation->set_rules(
                'password',
                'New password',
                'trim|min_length[6]|max_length[50]'
            );

            if ($this->form_validation->run() === TRUE) {
                $complete_name = trim((string) $this->input->post('cname', TRUE));
                $username = trim((string) $this->input->post('username', TRUE));
                $password = (string) $this->input->post('password', FALSE);

                // Prevent the signed-in account from taking another user's identity.
                if ($this->User_model->username_exists($username, $user_id)) {
                    $error_message = $username.' is already taken.';
                } elseif ($this->User_model->employee_name_exists($complete_name, $user_id)) {
                    $error_message = $complete_name.' is already registered.';
                } else {
                    $update_data = array(
                        'username' => $username,
                        'emp_name' => ucwords(strtolower($complete_name))
                    );

                    // Retain MD5 only because the current RMS login still supports it.
                    if ($password !== '') {
                        $update_data['password'] = md5($password);
                    }

                    if ($this->User_model->update_profile($user_id, $update_data)) {
                        // Keep the shared header accurate without requiring a new login.
                        $this->session->set_userdata(array(
                            'rms_username' => $update_data['username'],
                            'rms_display_name' => $update_data['emp_name']
                        ));
                        $this->session->set_flashdata(
                            'rms_profile_success',
                            'Your account details were updated successfully.'
                        );
                        redirect('administrator/users/profile');
                        return;
                    }

                    $error_message = 'Your profile could not be updated. No account data was changed.';
                }
            }

            // Reload read-only values and preserve validated form values after an error.
            $profile = $this->User_model->get_profile($user_id);
        }

        $data = array_merge($this->common_view_data('Edit Profile'), array(
            'profile' => $profile,
            'profile_error' => $error_message,
            'profile_success' => (string) $this->session->flashdata('rms_profile_success')
        ));

        $this->load->view('admin/profile', $data);
    }

    /**
     * Loads or saves the selected user's existing file/folder permissions.
     *
     * GET is requested by JavaScript only after Edit Access is chosen. This
     * avoids loading the large folder tree during every Users-page request.
     * POST replaces permissions using the same user_allowed_data structure and
     * path meanings used by the old RMS.
     *
     * @param int $user_id Existing users.user_id value from the route.
     */
    public function access($user_id = 0)
    {
        if (!$this->require_user_manager()) {
            return;
        }

        $user_id = (int) $user_id;
        $manager_role = (int) $this->session->userdata('rms_role');
        $selected_user = $this->User_model->get_user_for_edit($user_id);

        // Apply the same protected-account rules used by Edit Info.
        if (!$selected_user || !$this->User_model->user_is_manageable(
            $selected_user,
            (int) $this->session->userdata('rms_user_id'),
            $manager_role
        )) {
            if ($this->input->method(TRUE) === 'GET') {
                $this->json_response(FALSE, 'The selected account cannot be edited.');
                return;
            }

            show_error('The selected account cannot be edited.', 403);
            return;
        }

        if ($this->input->method(TRUE) === 'POST') {
            /*
             * Each value contains the complete existing database ID path for
             * one checked file/folder. The model validates every path before
             * replacing this user's access rows.
             */
            $selected_paths = $this->input->post('access_path');
            $selected_paths = is_array($selected_paths) ? $selected_paths : array();

            if ($this->User_model->replace_user_access($user_id, $selected_paths)) {
                $this->session->set_flashdata(
                    'rms_access_success',
                    'File access was updated successfully.'
                );
                redirect('administrator/users');
                return;
            }

            $this->session->set_flashdata(
                'rms_access_error',
                'File access was not changed because one or more paths were invalid.'
            );
            redirect('administrator/users');
            return;
        }

        // Fixed-query tree loading replaces the old query-inside-loop process.
        $tree = $this->User_model->get_access_tree($user_id);
        $html = $this->load->view('admin/partials/user_access_tree', array(
            'access_user' => $selected_user,
            'access_tree' => $tree
        ), TRUE);

        /*
         * Return the rendered list directly. The legacy RMS can contain old
         * filename text that makes json_encode() reject the complete payload;
         * direct HTML keeps every valid file/folder visible and searchable.
         * This response is read-only and does not change access records.
         */
        $this->output
            ->set_content_type('text/html', 'utf-8')
            ->set_output($html);
    }

    /**
     * Downloads one selected user's effective file/folder access as CSV.
     *
     * This converts the old exportUserAllowedData.php process into a valid
     * UTF-8 CSV while preserving its one-user rule and Filename/Subfolder1..10
     * layout. It performs SELECT queries only and changes no database data.
     *
     * @param int $user_id Existing users.user_id value from the route.
     */
    public function export($user_id = 0)
    {
        if (!$this->require_user_manager()) {
            return;
        }

        if ($this->input->method(TRUE) !== 'GET') {
            show_error('This export is available by download request only.', 405);
            return;
        }

        $user_id = (int) $user_id;
        $manager_role = (int) $this->session->userdata('rms_role');
        $selected_user = $this->User_model->get_user_for_edit($user_id);

        // Apply the same account protection enforced by the Users table.
        if (!$selected_user || !$this->User_model->user_is_manageable(
            $selected_user,
            (int) $this->session->userdata('rms_user_id'),
            $manager_role
        )) {
            show_error('The selected account cannot be exported.', 403);
            return;
        }

        $rows = $this->User_model->get_user_access_export($user_id);
        $safe_username = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            (string) $selected_user['username']
        );
        $safe_username = trim($safe_username, '.-_');

        if ($safe_username === '') {
            $safe_username = 'user-'.$user_id;
        }

        $download_name = $safe_username.'-allowed-data.csv';
        $stream = fopen('php://temp', 'w+');

        if ($stream === FALSE) {
            show_error('The export file could not be prepared.', 500);
            return;
        }

        // The UTF-8 marker keeps legacy names readable when opened in Excel.
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array(
            'Filename',
            'Subfolder1',
            'Subfolder2',
            'Subfolder3',
            'Subfolder4',
            'Subfolder5',
            'Subfolder6',
            'Subfolder7',
            'Subfolder8',
            'Subfolder9',
            'Subfolder10'
        ));

        foreach ($rows as $row) {
            $safe_row = array();

            foreach ($row as $cell) {
                $safe_row[] = $this->spreadsheet_cell($cell);
            }

            fputcsv($stream, $safe_row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $this->output
            ->set_content_type('text/csv', 'utf-8')
            ->set_header('Content-Disposition: attachment; filename="'.$download_name.'"')
            ->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output($csv);
    }

    /**
     * Prevents legacy labels from becoming executable spreadsheet formulas.
     *
     * @param mixed $value Filename or folder label.
     * @return string Safe single-line spreadsheet value.
     */
    private function spreadsheet_cell($value)
    {
        $cell = str_replace(array("\r", "\n"), ' ', (string) $value);

        if (preg_match('/^[=+\-@]/', $cell)) {
            $cell = "'".$cell;
        }

        return $cell;
    }

    /**
     * Sends a small JSON response for the asynchronous Edit Access loader.
     *
     * @param bool   $success Whether the request completed successfully.
     * @param string $message Error or status text.
     * @param array  $extra Additional response fields.
     */
    private function json_response($success, $message, $extra = array())
    {
        $payload = array_merge(array(
            'success' => (bool) $success,
            'message' => (string) $message
        ), $extra);

        $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload));
    }
}
