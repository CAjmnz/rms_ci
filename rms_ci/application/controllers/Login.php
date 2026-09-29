<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->config->load('rms_auth');
        $this->load->database();
        $this->load->helper(array('url', 'form', 'security'));
        $this->load->library(array('session', 'form_validation'));
        $this->load->model('Login_model');
    }

    public function index()
    {
        if ($this->session->userdata('rms_logged_in') === TRUE) {
            redirect('administrator/dashboard');
        }

        $data = array(
            'page_title'   => 'Admin Login',
            'system_name'  => $this->config->item('rms_auth_system_name'),
            'company_name' => $this->config->item('rms_auth_company_name'),
            'login_error'  => '',
            'access_restricted' => FALSE
        );

        $this->form_validation->set_rules(
            'username',
            'Username',
            'trim|required|max_length[25]'
        );
        $this->form_validation->set_rules(
            'password',
            'Password',
            'required|max_length[50]'
        );

        if ($this->form_validation->run() === TRUE) {
            $username = $this->input->post('username', TRUE);
            $password = (string) $this->input->post('password', FALSE);
            $user = $this->Login_model->verify_credentials($username, $password);

            if ($user !== FALSE) {
                /*
                 * Only legacy Level 1 and Level 2 accounts may enter the
                 * Administrator module. Reject all other valid accounts before
                 * creating an Admin session and direct them to the root portal.
                 */
                $role_id = (int) $user['role'];
                if ($role_id !== 1 && $role_id !== 2) {
                    $data['access_restricted'] = TRUE;
                    $data['login_error'] = 'This account cannot access the Administrator Portal.';
                    $this->load->view('admin/login', $data);
                    return;
                }

                $this->session->sess_regenerate(TRUE);
                $this->session->set_userdata(array(
                    'rms_user_id'      => $user['id'],
                    'rms_username'     => $user['username'],
                    'rms_display_name' => $user['display_name'],
                    'rms_role'         => $user['role'],
                    'rms_department'   => $user['department'],
                    'rms_location'     => $user['location'],
                    'rms_employee_id'  => $user['employee_id'],
                    'rms_position'     => $user['position'],
                    'rms_profile_pic'  => $user['profile_picture'],
                    'rms_allowed_upload' => $user['allowed_upload'],
                    'rms_last_visit'   => $user['last_visit'],
                    'rms_logged_in'    => TRUE
                ));

                /* Keep every successful Admin login inside the Admin URL. */
                redirect('administrator/dashboard');
            }

            $data['login_error'] = 'The username or password is incorrect.';
        }

        $this->load->view('admin/login', $data);
    }

    public function logout()
    {
        $this->session->sess_destroy();

        /* Return signed-out administrators to the canonical Admin login. */
        redirect('administrator');
    }
}
