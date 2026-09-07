<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Existing RMS user-table mapping
|--------------------------------------------------------------------------
| Change only these values to match the current database. No table or column
| is created or altered by this login module.
*/
$config['rms_auth_table']               = 'users';
$config['rms_auth_id_column']           = 'user_id';
$config['rms_auth_username_column']     = 'username';
$config['rms_auth_password_column']     = 'password';
$config['rms_auth_display_name_column'] = 'emp_name';
$config['rms_auth_role_column']         = 'role_id';
$config['rms_auth_password_mode'] = 'md5';
$config['rms_auth_status_column'] = '';
$config['rms_auth_active_value']  = '';

/*
| Extra columns stored in the CI session after a successful login.
| These match the existing users table shown in the supplied screenshot.
*/
$config['rms_auth_department_column']     = 'dept_id';
$config['rms_auth_location_column']       = 'location';
$config['rms_auth_employee_id_column']    = 'emp_id';
$config['rms_auth_position_column']       = 'position';
$config['rms_auth_profile_picture_column'] = 'profile_pic';
$config['rms_auth_allowed_upload_column'] = 'allowed_upload';
$config['rms_auth_last_visit_column']      = 'last_date_visit';

/*
| Password modes: auto, crypt, sha256, sha1, md5, or plain.
| "auto" recognizes common legacy hashes so the existing database can remain
| unchanged during conversion. Set the exact mode when the old code is known.
*/
$config['rms_auth_password_mode'] = 'auto';

/*
|--------------------------------------------------------------------------
| Screen and redirect settings
|--------------------------------------------------------------------------
*/
$config['rms_auth_system_name']     = 'Records Management System';
$config['rms_auth_company_name']    = 'Alturas Group of Companies';
$config['rms_auth_dashboard_route'] = 'administrator/dashboard';

/*
|--------------------------------------------------------------------------
| Existing module routes used by the dashboard
|--------------------------------------------------------------------------
| Every Administrator link uses the administrator/ URL prefix so the browser
| remains inside the separate Admin module. This does not change the database.
*/
$config['rms_dashboard_routes'] = array(
    'home'                => 'administrator/dashboard',
    'new_document'        => 'administrator/documents/new',
    'manage_documents'    => 'administrator/documents',
    'manage_users'        => 'administrator/users',
    'manage_departments'  => 'administrator/departments',
    'manage_subsidiaries' => 'administrator/subsidiaries',
    'edit_profile'        => 'administrator/users/profile',
    'system'              => 'administrator/system',
    'logout'              => 'administrator/logout'
);
