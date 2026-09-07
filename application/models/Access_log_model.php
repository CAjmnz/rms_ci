<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * RMS CI DAILY ACTIVITY LOG MODEL
 *
 * PURPOSE
 * -------------------------------------------------------------------------
 * This model stores RMS CI activity logs ONLY inside this project:
 *
 *     /var/www/html/rms_ci/assets/activity_log/
 *
 * Daily files are created automatically:
 *
 *     activity-log-2026-08-18.txt
 *     activity-log-2026-08-19.txt
 *     activity-log-2026-08-20.txt
 *
 * IMPORTANT
 * -------------------------------------------------------------------------
 * - This code DOES NOT read logs from /var/www/html/rms/.
 * - This code DOES NOT write logs to /var/www/html/rms/.
 * - This keeps the existing Access Logs page methods:
 *      get_all()
 *      get_portal_activity()
 *      append_portal_activity()
 *      source_status()
 *      clear_all()
 * - It also adds capture_request() so one global CI hook can record actions
 *   from Users, Documents, Subsidiaries, Departments, System and Portal.
 *
 * PHP 5.4 / CodeIgniter 3 compatible.
 */
class Access_log_model extends CI_Model
{
    /**
     * Absolute local daily-log directory.
     *
     * On your server this becomes:
     * /var/www/html/rms_ci/assets/activity_log
     *
     * @var string
     */
    private $archive_dir = '';

    public function __construct()
    {
        parent::__construct();

        /*
         * FCPATH is the CodeIgniter project root:
         *
         *     /var/www/html/rms_ci/
         *
         * Therefore the final directory is:
         *
         *     /var/www/html/rms_ci/assets/activity_log
         */
        $this->archive_dir = rtrim(FCPATH, '/\\')
            . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'activity_log';

        /*
         * Try to create/protect the folder immediately when this model loads.
         *
         * Logging is intentionally best-effort. If Linux permissions prevent
         * the folder from being created, normal RMS functions must continue.
         */
        $this->prepare_archive_directory();
    }

    /**
     * Get all LOCAL daily activity log rows, newest first.
     *
     * Reads ONLY:
     * /var/www/html/rms_ci/assets/activity_log/activity-log-*.txt
     *
     * @return array
     */
    public function get_all()
    {
        $records = array();

        if (!is_dir($this->archive_dir) || !is_readable($this->archive_dir)) {
            return $records;
        }

        $files = glob(
            $this->archive_dir
            . DIRECTORY_SEPARATOR
            . 'activity-log-*.txt'
        );

        if (!is_array($files)) {
            return $records;
        }

        /*
         * YYYY-MM-DD filenames sort correctly as dates.
         * Read newest daily file first.
         */
        rsort($files, SORT_STRING);

        $order = 0;

        foreach ($files as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $lines = file(
                $path,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

            if (!is_array($lines)) {
                continue;
            }

            /*
             * New events are appended to the bottom of each file.
             * Read bottom-to-top so the UI receives newest events first.
             */
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim((string) $lines[$i]);

                if ($line === '') {
                    continue;
                }

                /*
                 * LOCAL FORMAT:
                 * identity|date|activity|source
                 *
                 * We also accept an older 3-field row for compatibility.
                 */
                $parts = explode('|', $line, 4);

                if (count($parts) < 3) {
                    continue;
                }

                $records[] = array(
                    'identity' => trim($parts[0]),
                    'date'     => trim($parts[1]),
                    'activity' => trim($parts[2]),
                    'source'   => isset($parts[3]) && trim($parts[3]) !== ''
                        ? trim($parts[3])
                        : 'RMS CI',
                    'order'    => $order
                );

                $order++;
            }
        }

        return $records;
    }

    /**
     * Return only the current Portal user's View/Download records.
     *
     * This keeps your existing User Portal activity list compatible.
     *
     * @param string $identity
     * @param string $search
     * @return array
     */
    public function get_portal_activity($identity, $search)
    {
        $identity = trim((string) $identity);
        $search = strtolower(trim((string) $search));
        $matches = array();

        foreach ($this->get_all() as $record) {
            if ($record['identity'] !== $identity) {
                continue;
            }

            /*
             * Portal document records always begin with [Portal].
             */
            if (strpos($record['activity'], '[Portal] ') !== 0) {
                continue;
            }

            if (
                $search !== ''
                && strpos(
                    strtolower(
                        $record['activity']
                        . ' '
                        . $record['date']
                    ),
                    $search
                ) === FALSE
            ) {
                continue;
            }

            /*
             * Convert:
             * [Portal] Viewed: sample.pdf
             *
             * into:
             * action   => Viewed
             * document => sample.pdf
             */
            $activity = substr($record['activity'], 9);
            $separator = strpos($activity, ': ');

            $record['action'] = $separator === FALSE
                ? $activity
                : substr($activity, 0, $separator);

            $record['document'] = $separator === FALSE
                ? ''
                : substr($activity, $separator + 2);

            $matches[] = $record;
        }

        return $matches;
    }

    /**
     * Save a Portal View/Download event into the LOCAL daily log.
     *
     * The old model wrote this first to /rms/logs/logs.txt.
     * This version writes DIRECTLY to rms_ci/assets/activity_log.
     *
     * @param string $identity
     * @param string $action
     * @param string $document_name
     * @return bool
     */
    public function append_portal_activity($identity, $action, $document_name)
    {
        $action = $action === 'Downloaded'
            ? 'Downloaded'
            : 'Viewed';

        $document_name = basename((string) $document_name);

        return $this->append_activity(
            $identity,
            '[Portal] ' . $action . ': ' . $document_name,
            'Records'
        );
    }

    /**
     * MAIN LOGGER.
     *
     * Any controller or hook can use this method.
     *
     * Example:
     *
     * $this->Access_log_model->append_activity(
     *     'admin',
     *     'Create User: juan',
     *     'Administrator'
     * );
     *
     * Saved line:
     *
     * admin|2026-08-18 18:15:00|Create User: juan|Administrator
     *
     * @param string $identity Username/person performing the action.
     * @param string $activity Human-readable activity description.
     * @param string $source Administrator, Records, or another RMS CI source.
     * @return bool
     */
    public function append_activity($identity, $activity, $source)
    {
        if (!$this->prepare_archive_directory()) {
            return FALSE;
        }

        /*
         * Protect the pipe-delimited file format.
         * Passwords are never passed to this method by the hook.
         */
        $identity = $this->clean_value($identity);
        $activity = $this->clean_value($activity);
        $source = $this->clean_value($source);

        if ($identity === '') {
            $identity = 'Unknown';
        }

        if ($activity === '') {
            return FALSE;
        }

        if ($source === '') {
            $source = 'RMS CI';
        }

        /*
         * Use Asia/Manila without changing the application's global timezone.
         */
        $now = $this->manila_now();

        /*
         * ================================================================
         * THIS CODE CREATES/SELECTS THE DAILY TEXT FILE.
         * ================================================================
         *
         * Example result:
         * /var/www/html/rms_ci/assets/activity_log/activity-log-2026-08-18.txt
         */
        $path = $this->archive_dir
            . DIRECTORY_SEPARATOR
            . 'activity-log-'
            . $now['day']
            . '.txt';

        /*
         * One activity = one physical line.
         */
        $line = $identity
            . '|'
            . $now['date_time']
            . '|'
            . $activity
            . '|'
            . $source
            . PHP_EOL;

        /*
         * FILE_APPEND prevents existing activity from being erased.
         * LOCK_EX prevents simultaneous requests from overlapping writes.
         */
        return file_put_contents(
            $path,
            $line,
            FILE_APPEND | LOCK_EX
        ) !== FALSE;
    }

    /**
     * Capture one RMS request prepared by Activity_log_hook.
     *
     * This makes logging broad enough to cover:
     * - Login / Logout
     * - Create/Edit/Delete users
     * - User access/viewer/uploader/block/force-logout changes
     * - Document uploads
     * - Create filename / create subfolder
     * - Update/delete document records
     * - Publish/unpublish
     * - Subsidiary create/update/delete
     * - Department create/update/delete
     * - System configuration/file-type actions
     * - Backup
     * - Portal document view/download
     * - Future POST actions through a generic fallback
     *
     * @param array $request
     * @return bool
     */
    public function capture_request($request)
    {
        if (!is_array($request)) {
            return FALSE;
        }

        $controller = isset($request['controller'])
            ? strtolower(trim((string) $request['controller']))
            : '';

        $method = isset($request['method'])
            ? strtolower(trim((string) $request['method']))
            : '';

        $http_method = isset($request['http_method'])
            ? strtoupper(trim((string) $request['http_method']))
            : 'GET';

        $identity = isset($request['identity'])
            ? trim((string) $request['identity'])
            : '';

        $post = isset($request['post']) && is_array($request['post'])
            ? $request['post']
            : array();

        $files = isset($request['files']) && is_array($request['files'])
            ? $request['files']
            : array();

        $uri = isset($request['uri'])
            ? trim((string) $request['uri'])
            : '';

        $source = $controller === 'user_portal'
            ? 'Records'
            : 'Administrator';

        /*
         * Do not log ordinary list/page browsing.
         * We log meaningful actions and all POST operations instead.
         */
        $activity = $this->describe_known_action(
            $controller,
            $method,
            $http_method,
            $post,
            $files,
            $uri
        );

        if ($activity === '') {
            return FALSE;
        }

        /*
         * If the PHP request ended in a fatal error, keep that in the audit log.
         */
        if (
            isset($request['fatal_error'])
            && trim((string) $request['fatal_error']) !== ''
        ) {
            $activity .= ' [PHP ERROR]';
        }

        return $this->append_activity(
            $identity,
            $activity,
            $source
        );
    }

    /**
     * Convert known RMS controller actions into readable legacy-style labels.
     *
     * Unknown POST actions are still recorded through the generic fallback,
     * so future RMS modules are not silently missed.
     *
     * @return string
     */
    private function describe_known_action(
        $controller,
        $method,
        $http_method,
        $post,
        $files,
        $uri
    ) {
        $value = '';
        $ids = '';

        /*
         * -------------------------
         * LOGIN / LOGOUT
         * -------------------------
         */
        if ($controller === 'login' && $method === 'index' && $http_method === 'POST') {
            return 'Login to Administrator Portal';
        }

        if ($controller === 'login' && $method === 'logout') {
            return 'Logout from Administrator Portal';
        }

        /*
         * -------------------------
         * USERS
         * -------------------------
         */
        if ($controller === 'users') {
            if ($method === 'create' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('username', 'uname', 'user_name', 'cname')
                );

                return 'Create User'
                    . ($value !== '' ? ': ' . $value : '');
            }

            if ($method === 'edit' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('username', 'uname', 'user_name', 'cname', 'user_id')
                );

                return 'Update User'
                    . ($value !== '' ? ': ' . $value : '');
            }

            if ($method === 'access' && $http_method === 'POST') {
                return 'Update User Access'
                    . $this->post_identifier_suffix($post);
            }

            if (($method === 'remove' || $method === 'delete') && $http_method === 'POST') {
                return 'Delete User'
                    . $this->post_identifier_suffix($post);
            }

            if ($method === 'logout' && $http_method === 'POST') {
                return 'Force Logout User'
                    . $this->post_identifier_suffix($post);
            }

            if ($method === 'block' && $http_method === 'POST') {
                return 'Change User Block Status'
                    . $this->post_identifier_suffix($post);
            }

            if ($method === 'viewer' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('role_id', 'viewer', 'viewer_level')
                );

                return 'Change Viewer Level'
                    . $this->post_identifier_suffix($post)
                    . ($value !== '' ? ' to ' . $value : '');
            }

            if ($method === 'uploader' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('allowed_upload')
                );

                return 'Change Uploader Permission'
                    . $this->post_identifier_suffix($post)
                    . ($value !== '' ? ' to ' . $value : '');
            }

            if ($method === 'export') {
                return 'Export User Access Data';
            }
        }

        /*
         * -------------------------
         * DOCUMENTS
         * -------------------------
         */
        if ($controller === 'documents') {
            if ($method === 'upload' && $http_method === 'POST') {
                $value = $this->join_file_names($files);

                return 'Upload Data'
                    . ($value !== '' ? ': ' . $value : '');
            }

            if ($method === 'record' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('filename', 'data_name', 'name', 'record_name')
                );

                return 'Create Document Record'
                    . ($value !== '' ? ': ' . $value : '');
            }

            if ($method === 'create_filename' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('filename', 'name')
                );

                return 'Create Filename'
                    . ($value !== '' ? ': ' . $value : '');
            }

            if ($method === 'create_subfolder' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array(
                        'subfolder_name',
                        'folder_name',
                        'name',
                        'subfolder'
                    )
                );

                return 'Create Subfolder'
                    . ($value !== '' ? ': ' . $value : '');
            }

            if ($method === 'update_record' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('name', 'filename', 'data_name', 'record_name')
                );

                return 'Update Document Record'
                    . ($value !== '' ? ': ' . $value : '')
                    . $this->post_identifier_suffix($post);
            }

            if ($method === 'delete_record' && $http_method === 'POST') {
                return 'Delete Document Record'
                    . $this->post_identifier_suffix($post);
            }

            if ($method === 'publish' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('publish')
                );

                $ids = $this->first_post_value(
                    $post,
                    array('record_ids', 'ids')
                );

                return ($value === '1' ? 'Publish Data' : 'Unpublish Data')
                    . ($ids !== '' ? ': ' . $ids : '');
            }
        }

        /*
         * -------------------------
         * SUBSIDIARIES
         * -------------------------
         */
        if ($controller === 'subsidiaries') {
            if ($method === 'save' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('sub_name', 'subsidiary_name')
                );

                $ids = $this->first_post_value(
                    $post,
                    array('sub_id')
                );

                return ($ids !== '' && (int) $ids > 0
                    ? 'Update Subsidiary'
                    : 'Create Subsidiary')
                    . ($value !== '' ? ': ' . $value : '');
            }

            if ($method === 'delete' && $http_method === 'POST') {
                return 'Delete Subsidiary'
                    . $this->post_identifier_suffix($post);
            }
        }

        /*
         * -------------------------
         * DEPARTMENTS
         * -------------------------
         */
        if ($controller === 'departments') {
            if ($method === 'save' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('dept_name', 'department_name')
                );

                $ids = $this->first_post_value(
                    $post,
                    array('dept_id')
                );

                return ($ids !== '' && (int) $ids > 0
                    ? 'Update Department'
                    : 'Create Department')
                    . ($value !== '' ? ': ' . $value : '');
            }

            if ($method === 'delete' && $http_method === 'POST') {
                return 'Delete Department'
                    . $this->post_identifier_suffix($post);
            }
        }

        /*
         * -------------------------
         * SYSTEM
         * -------------------------
         */
        if ($controller === 'system') {
            if ($method === 'save' && $http_method === 'POST') {
                return 'Update Global Configuration';
            }

            if ($method === 'save_file_type' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('type', 'file_type', 'extension')
                );

                return 'Save File Type'
                    . ($value !== '' ? ': ' . $value : '');
            }

            if ($method === 'set_file_type_status' && $http_method === 'POST') {
                return 'Change File Type Status';
            }

            if ($method === 'delete_file_types' && $http_method === 'POST') {
                return 'Delete File Type';
            }

            if ($method === 'clear_access_logs' && $http_method === 'POST') {
                return 'Clear Access Logs';
            }

            if ($method === 'download_backup' && $http_method === 'POST') {
                $value = $this->first_post_value(
                    $post,
                    array('backup_mode')
                );

                return 'Download Backup'
                    . ($value !== '' ? ': ' . $value : '');
            }
        }

        /*
         * -------------------------
         * USER PORTAL
         * -------------------------
         *
         * User Portal login/view/download/logout are logged DIRECTLY inside
         * User_portal.php after the action succeeds. Returning an empty string
         * here prevents the global hook from creating duplicate Portal rows.
         *
         * Direct controller logging also lets us save the REAL document name
         * instead of only the route/token.
         */
        if ($controller === 'user_portal') {
            return '';
        }

        /*
         * ================================================================
         * GENERIC FALLBACK
         * ================================================================
         *
         * Every future POST action is still logged even when it has not been
         * explicitly named above. This is what helps keep the logger complete
         * as new RMS modules/actions are added.
         */
        if ($http_method === 'POST') {
            $activity = 'RMS Action: '
                . ucfirst($controller)
                . ' / '
                . $method;

            $details = $this->safe_post_summary($post);

            if ($details !== '') {
                $activity .= ' (' . $details . ')';
            }

            return $activity;
        }

        return '';
    }

    /**
     * Return the LOCAL activity_log directory status for the System page.
     *
     * @return array
     */
    public function source_status()
    {
        return array(
            array(
                'path'     => $this->archive_dir,
                'exists'   => is_dir($this->archive_dir),
                'readable' => is_readable($this->archive_dir),
                'writable' => is_writable($this->archive_dir)
            )
        );
    }

    /**
     * Delete only LOCAL daily activity-log text files.
     *
     * Does NOT touch /var/www/html/rms/.
     *
     * The hook will record "Clear Access Logs" after the request, so the clear
     * action itself remains as a new audit event.
     *
     * @return array
     */
    public function clear_all()
    {
        if (!is_dir($this->archive_dir)) {
            return array(
                'success' => TRUE,
                'message' => ''
            );
        }

        if (!is_writable($this->archive_dir)) {
            return array(
                'success' => FALSE,
                'message' => 'The RMS CI activity_log directory is not writable.'
            );
        }

        $files = glob(
            $this->archive_dir
            . DIRECTORY_SEPARATOR
            . 'activity-log-*.txt'
        );

        if (!is_array($files)) {
            $files = array();
        }

        foreach ($files as $path) {
            if (!is_file($path)) {
                continue;
            }

            if (!is_writable($path) || !@unlink($path)) {
                return array(
                    'success' => FALSE,
                    'message' => 'One or more RMS CI activity log files could not be deleted.'
                );
            }
        }

        return array(
            'success' => TRUE,
            'message' => ''
        );
    }

    /**
     * ================================================================
     * THIS METHOD CREATES THE "activity_log" DIRECTORY.
     * ================================================================
     *
     * Target:
     * /var/www/html/rms_ci/assets/activity_log
     *
     * @return bool
     */
    private function prepare_archive_directory()
    {
        /*
         * THIS mkdir() IS THE ACTUAL DIRECTORY-CREATION CODE.
         *
         * 0775 = owner/group may write.
         * TRUE = allow recursive creation if a parent is missing.
         */
        if (!is_dir($this->archive_dir)) {
            if (
                !@mkdir($this->archive_dir, 0775, TRUE)
                && !is_dir($this->archive_dir)
            ) {
                return FALSE;
            }
        }

        /*
         * Linux/Apache/PHP still needs real filesystem permission.
         */
        if (!is_writable($this->archive_dir)) {
            return FALSE;
        }

        /*
         * Protect log files from direct browser access.
         */
        $htaccess = $this->archive_dir
            . DIRECTORY_SEPARATOR
            . '.htaccess';

        if (!is_file($htaccess)) {
            $rules = "<IfModule mod_authz_core.c>\n"
                . "    Require all denied\n"
                . "</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n"
                . "    Deny from all\n"
                . "</IfModule>\n";

            @file_put_contents(
                $htaccess,
                $rules,
                LOCK_EX
            );
        }

        /*
         * Also prevent directory listing if .htaccess is ignored.
         */
        $index = $this->archive_dir
            . DIRECTORY_SEPARATOR
            . 'index.html';

        if (!is_file($index)) {
            @file_put_contents(
                $index,
                '',
                LOCK_EX
            );
        }

        return TRUE;
    }

    /**
     * Get current Asia/Manila daily-file date/time.
     *
     * We use DateTimeZone instead of changing PHP's global timezone.
     *
     * @return array
     */
    private function manila_now()
    {
        $date = new DateTime(
            'now',
            new DateTimeZone('Asia/Manila')
        );

        return array(
            'day'       => $date->format('Y-m-d'),
            'date_time' => $date->format('Y-m-d H:i:s')
        );
    }

    /**
     * Remove characters that would break one physical pipe-delimited row.
     *
     * @param mixed $value
     * @return string
     */
    private function clean_value($value)
    {
        return trim(
            str_replace(
                array('|', "\r", "\n"),
                array(' ', ' ', ' '),
                (string) $value
            )
        );
    }

    /**
     * Safely return the first matching POST value.
     *
     * Arrays are converted to comma-separated IDs/values.
     *
     * @param array $post
     * @param array $keys
     * @return string
     */
    private function first_post_value($post, $keys)
    {
        foreach ($keys as $key) {
            if (!isset($post[$key])) {
                continue;
            }

            if (is_array($post[$key])) {
                $values = array();

                foreach ($post[$key] as $value) {
                    if (is_scalar($value)) {
                        $values[] = $this->clean_value($value);
                    }
                }

                return implode(', ', $values);
            }

            if (is_scalar($post[$key])) {
                return $this->clean_value($post[$key]);
            }
        }

        return '';
    }

    /**
     * Add a useful record ID suffix when one is available.
     *
     * @param array $post
     * @return string
     */
    private function post_identifier_suffix($post)
    {
        $value = $this->first_post_value(
            $post,
            array(
                'user_id',
                'record_id',
                'record_ids',
                'sub_id',
                'dept_id',
                'id',
                'ids'
            )
        );

        return $value !== ''
            ? ': ' . $value
            : '';
    }

    /**
     * Turn uploaded $_FILES data into a readable filename list.
     *
     * @param array $files
     * @return string
     */
    private function join_file_names($files)
    {
        $names = array();

        foreach ($files as $file) {
            if (!is_array($file) || !isset($file['name'])) {
                continue;
            }

            $this->collect_file_names(
                $file['name'],
                $names
            );
        }

        $names = array_values(
            array_unique($names)
        );

        return implode(', ', $names);
    }

    /**
     * Recursively collect file names from single/multiple upload fields.
     *
     * @param mixed $value
     * @param array $names
     * @return void
     */
    private function collect_file_names($value, &$names)
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collect_file_names(
                    $item,
                    $names
                );
            }

            return;
        }

        $value = basename((string) $value);

        if ($value !== '') {
            $names[] = $this->clean_value($value);
        }
    }

    /**
     * Generic safe POST summary for future/unmapped actions.
     *
     * Passwords, password hashes, CSRF tokens and other secrets are NEVER
     * written into activity logs.
     *
     * @param array $post
     * @return string
     */
    private function safe_post_summary($post)
    {
        $parts = array();
        $count = 0;

        foreach ($post as $key => $value) {
            if ($count >= 6) {
                break;
            }

            $lower = strtolower((string) $key);

            /*
             * Never log secrets.
             */
            if (
                strpos($lower, 'pass') !== FALSE
                || strpos($lower, 'pwd') !== FALSE
                || strpos($lower, 'csrf') !== FALSE
                || strpos($lower, 'token') !== FALSE
                || strpos($lower, 'hash') !== FALSE
                || strpos($lower, 'secret') !== FALSE
            ) {
                continue;
            }

            if (is_array($value)) {
                $safe = array();

                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $safe[] = $this->clean_value($item);
                    }
                }

                $value = implode(',', $safe);
            } elseif (is_scalar($value)) {
                $value = $this->clean_value($value);
            } else {
                continue;
            }

            if (strlen($value) > 80) {
                $value = substr($value, 0, 77) . '...';
            }

            $parts[] = $this->clean_value($key)
                . '='
                . $value;

            $count++;
        }

        return implode(', ', $parts);
    }

    /**
     * Retained for compatibility if existing code calls this method.
     */
    public function newest_first($left, $right)
    {
        if ($left['order'] === $right['order']) {
            return 0;
        }

        return $left['order'] < $right['order']
            ? -1
            : 1;
    }
}
