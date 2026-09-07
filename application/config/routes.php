<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['default_controller'] = 'user_portal/login';

/*
 * Administrator module routes.
 * These routes keep the current controllers in place while presenting the
 * Admin module under /administrator/ in the browser address bar.
 */
$route['administrator'] = 'login/index';
$route['administrator/login'] = 'login/index';
$route['administrator/logout'] = 'login/logout';
$route['administrator/dashboard'] = 'dashboard/index';
$route['administrator/users'] = 'users/index';
$route['administrator/users/profile'] = 'users/profile';
$route['administrator/users/create'] = 'users/create';
$route['administrator/users/edit/(:num)'] = 'users/edit/$1';
$route['administrator/users/access/(:num)'] = 'users/access/$1';
$route['administrator/users/export/(:num)'] = 'users/export/$1';
$route['administrator/users/logout'] = 'users/logout';
$route['administrator/users/delete'] = 'users/remove';
$route['administrator/users/block'] = 'users/block';
$route['administrator/users/viewer'] = 'users/viewer';
$route['administrator/users/uploader'] = 'users/uploader';

/* Organization management routes. */
$route['administrator/subsidiaries'] = 'subsidiaries/index';
$route['administrator/subsidiaries/form'] = 'subsidiaries/form/0';
$route['administrator/subsidiaries/form/(:num)'] = 'subsidiaries/form/$1';
$route['administrator/subsidiaries/save'] = 'subsidiaries/save';
$route['administrator/subsidiaries/delete'] = 'subsidiaries/delete';
$route['administrator/departments'] = 'departments/index';
$route['administrator/departments/form'] = 'departments/form/0';
$route['administrator/departments/form/(:num)'] = 'departments/form/$1';
$route['administrator/departments/save'] = 'departments/save';
$route['administrator/departments/delete'] = 'departments/delete';

/* System administration routes (Super User only). */
$route['administrator/system'] = 'system/index';
$route['administrator/system/global-configuration'] = 'system/index';
$route['administrator/system/save'] = 'system/save';
$route['administrator/system/file-types'] = 'system/file_types';
$route['administrator/system/file-types/save'] = 'system/save_file_type';
$route['administrator/system/file-types/status'] = 'system/set_file_type_status';
$route['administrator/system/file-types/delete'] = 'system/delete_file_types';
$route['administrator/system/access-logs'] = 'system/access_logs';
$route['administrator/system/access-logs/clear'] = 'system/clear_access_logs';
$route['administrator/system/backup'] = 'system/backup';
$route['administrator/system/backup/download'] = 'system/download_backup';

/*
 * Regular-user portal routes.
 * Keep these entries in the active routes file so /rms_ci/portal is handled
 * by User_portal instead of falling through to CodeIgniter's 404 response.
 */
$route['portal'] = 'user_portal/login';
$route['portal/login'] = 'user_portal/login';
$route['portal/dashboard'] = 'user_portal/dashboard';
$route['portal/profile'] = 'user_portal/profile';
$route['portal/documents'] = 'user_portal/documents';
$route['portal/documents/download-selected'] = 'user_portal/download_selected';
$route['portal/documents/view/(:any)'] = 'user_portal/view_document/$1';
$route['portal/documents/download/(:any)'] = 'user_portal/download_document/$1';

$route['portal/logout'] = 'user_portal/logout';
/*
 * Temporary compatibility routes.
 * Keep these while the remaining Admin links are moved one module at a time.
 */
$route['login'] = 'user_portal/login';
$route['logout'] = 'login/logout';
$route['dashboard'] = 'dashboard/index';
$route['users'] = 'users/index';
$route['users/profile'] = 'users/profile';
$route['users/create'] = 'users/create';
$route['users/edit/(:num)'] = 'users/edit/$1';
$route['users/access/(:num)'] = 'users/access/$1';
$route['users/export/(:num)'] = 'users/export/$1';
$route['users/logout'] = 'users/logout';
$route['users/block'] = 'users/block';
$route['users/viewer'] = 'users/viewer';
$route['users/uploader'] = 'users/uploader';
/*
 * Documents module routes.
 * These routes keep the current controllers in place while presenting the
 */
$route['administrator/documents'] = 'documents/index';
$route['administrator/documents/new'] = 'documents/create';
$route['administrator/documents/upload'] = 'documents/upload';
$route['administrator/documents/record'] = 'documents/record';
$route['administrator/documents/publish'] = 'documents/publish';
$route['administrator/documents/toggle-pin'] = 'documents/toggle_pin';
$route['administrator/documents/datatable'] = 'documents/datatable';
$route['administrator/documents/create-filename'] ='documents/create_filename';
$route['administrator/documents/create-subfolder'] ='documents/create_subfolder';
$route['administrator/documents/save'] ='documents/update_record';
$route['administrator/documents/delete'] ='documents/delete_record';
/* Opaque encrypted paths – must sit above the generic (:any) rule. */
$route['administrator/documents/manage/(:any)'] = 'documents/manage/$1';
$route['administrator/documents/browse/(:any)'] = 'documents/browse/$1';
$route['administrator/documents/(:any)'] = 'documents/$1';


$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
