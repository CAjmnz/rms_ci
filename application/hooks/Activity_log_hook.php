<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * RMS CI GLOBAL ACTIVITY LOG HOOK
 *
 * PURPOSE
 * -------------------------------------------------------------------------
 * This hook lets the application record important activity WITHOUT adding
 * logging code into every Users/Documents/Subsidiaries/Departments controller.
 *
 * The hook runs after the controller constructor, takes a safe snapshot of the
 * request, then registers a shutdown function.
 *
 * WHY SHUTDOWN?
 * -------------------------------------------------------------------------
 * Many existing RMS controller actions use redirect(), and CodeIgniter's
 * redirect() exits the request immediately. A normal post_controller hook can
 * therefore miss those actions.
 *
 * PHP shutdown functions still run after redirect()/exit, so this approach
 * catches those existing processes without destroying/changing them.
 *
 * Passwords, password hashes, CSRF tokens and secret values are removed before
 * anything is sent to Access_log_model.
 *
 * PHP 5.4 / CodeIgniter 3 compatible.
 */
class Activity_log_hook
{
    /**
     * Store a safe request snapshot for shutdown_capture().
     *
     * Add this class through application/config/hooks.php using the provided
     * snippet.
     */
    public function register()
    {
        $CI =& get_instance();

        /*
         * Do not register twice during one request.
         */
        if (
            isset($GLOBALS['RMS_ACTIVITY_HOOK_REGISTERED'])
            && $GLOBALS['RMS_ACTIVITY_HOOK_REGISTERED'] === TRUE
        ) {
            return;
        }

        $GLOBALS['RMS_ACTIVITY_HOOK_REGISTERED'] = TRUE;

        /*
         * Resolve the current controller/method after routing.
         */
        $controller = isset($CI->router)
            ? strtolower((string) $CI->router->class)
            : '';

        $method = isset($CI->router)
            ? strtolower((string) $CI->router->method)
            : '';

        /*
         * Resolve the actor BEFORE the controller action runs.
         * This preserves the username even when logout destroys the session.
         */
        $identity = '';

        if (isset($CI->session)) {
            $identity = trim(
                (string) $CI->session->userdata('rms_username')
            );

            if ($identity === '') {
                $identity = trim(
                    (string) $CI->session->userdata(
                        'rms_portal_username'
                    )
                );
            }

            if ($identity === '') {
                $identity = trim(
                    (string) $CI->session->userdata(
                        'rms_display_name'
                    )
                );
            }

            if ($identity === '') {
                $identity = trim(
                    (string) $CI->session->userdata(
                        'rms_portal_display_name'
                    )
                );
            }
        }

        /*
         * For login requests there may not be a session yet.
         * Use the submitted username, but NEVER the submitted password.
         */
        if ($identity === '') {
            $identity = $this->first_request_value(
                $_POST,
                array(
                    'username',
                    'uname',
                    'user_name'
                )
            );
        }

        if ($identity === '') {
            $identity = 'Unknown';
        }

        /*
         * Keep only safe POST fields.
         */
        $safe_post = $this->safe_post($_POST);

        /*
         * Keep upload metadata only. Never read/store uploaded file content.
         */
        $safe_files = $this->safe_files($_FILES);

        /*
         * Save everything needed by the model after redirect()/exit.
         */
        $GLOBALS['RMS_ACTIVITY_REQUEST'] = array(
            'controller'  => $controller,
            'method'      => $method,
            'http_method' => isset($_SERVER['REQUEST_METHOD'])
                ? strtoupper((string) $_SERVER['REQUEST_METHOD'])
                : 'GET',
            'identity'    => $identity,
            'post'        => $safe_post,
            'files'       => $safe_files,
            'uri'         => isset($_SERVER['REQUEST_URI'])
                ? (string) $_SERVER['REQUEST_URI']
                : '',
            'fatal_error' => ''
        );

        /*
         * This function still runs when an existing controller redirects/exits.
         */
        register_shutdown_function(
            array(
                'Activity_log_hook',
                'shutdown_capture'
            )
        );
    }

    /**
     * Write the request snapshot into today's local RMS CI text file.
     */
    public static function shutdown_capture()
    {
        if (
            !isset($GLOBALS['RMS_ACTIVITY_REQUEST'])
            || !is_array($GLOBALS['RMS_ACTIVITY_REQUEST'])
        ) {
            return;
        }

        $snapshot = $GLOBALS['RMS_ACTIVITY_REQUEST'];

        /*
         * Keep fatal PHP failures visible in the audit trail.
         */
        $error = error_get_last();

        if (
            is_array($error)
            && isset($error['type'])
            && in_array(
                (int) $error['type'],
                array(
                    E_ERROR,
                    E_PARSE,
                    E_CORE_ERROR,
                    E_COMPILE_ERROR,
                    E_USER_ERROR
                ),
                TRUE
            )
        ) {
            $snapshot['fatal_error'] = isset($error['message'])
                ? (string) $error['message']
                : 'Fatal PHP error';
        }

        /*
         * Access the existing CI application and use the model.
         */
        $CI =& get_instance();

        if (!isset($CI) || !is_object($CI)) {
            return;
        }

        /*
         * Load only when needed.
         */
        $CI->load->model('Access_log_model');

        if (!isset($CI->Access_log_model)) {
            return;
        }

        /*
         * Best-effort audit logging: it must never break the user's RMS action.
         */
        $CI->Access_log_model->capture_request(
            $snapshot
        );
    }

    /**
     * Return the first scalar request value.
     */
    private function first_request_value($data, $keys)
    {
        if (!is_array($data)) {
            return '';
        }

        foreach ($keys as $key) {
            if (
                isset($data[$key])
                && is_scalar($data[$key])
            ) {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }

    /**
     * Remove password/token/security fields before taking the request snapshot.
     */
    private function safe_post($post)
    {
        $safe = array();

        if (!is_array($post)) {
            return $safe;
        }

        foreach ($post as $key => $value) {
            $lower = strtolower((string) $key);

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

            $safe[$key] = $this->clean_snapshot_value(
                $value
            );
        }

        return $safe;
    }

    /**
     * Keep only safe upload metadata such as file name/type/size/error.
     */
    private function safe_files($files)
    {
        $safe = array();

        if (!is_array($files)) {
            return $safe;
        }

        foreach ($files as $key => $file) {
            if (!is_array($file)) {
                continue;
            }

            $safe[$key] = array(
                'name' => isset($file['name'])
                    ? $this->clean_snapshot_value($file['name'])
                    : '',
                'type' => isset($file['type'])
                    ? $this->clean_snapshot_value($file['type'])
                    : '',
                'size' => isset($file['size'])
                    ? $this->clean_snapshot_value($file['size'])
                    : '',
                'error' => isset($file['error'])
                    ? $this->clean_snapshot_value($file['error'])
                    : ''
            );
        }

        return $safe;
    }

    /**
     * Recursively clean arrays/scalars without changing their structure.
     */
    private function clean_snapshot_value($value)
    {
        if (is_array($value)) {
            $safe = array();

            foreach ($value as $key => $item) {
                $safe[$key] = $this->clean_snapshot_value(
                    $item
                );
            }

            return $safe;
        }

        if (!is_scalar($value)) {
            return '';
        }

        return trim(
            str_replace(
                array("\r", "\n", '|'),
                array(' ', ' ', ' '),
                (string) $value
            )
        );
    }
}
