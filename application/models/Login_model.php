<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login_model extends CI_Model
{
    public function verify_credentials($username, $password)
    {
        $table = $this->config->item('rms_auth_table');
        $username_column = $this->config->item('rms_auth_username_column');
        $password_column = $this->config->item('rms_auth_password_column');
        $status_column = $this->config->item('rms_auth_status_column');

        $this->db->from($table);
        $this->db->where($username_column, $username);
        $this->db->limit(1);

        $query = $this->db->get();

        if ($query->num_rows() !== 1) {
            return FALSE;
        }

        $row = $query->row_array();

        if ($status_column !== ''
            && isset($row[$status_column])
            && (string) $row[$status_column] !== (string) $this->config->item('rms_auth_active_value')) {
            return FALSE;
        }

        if (!isset($row[$password_column])
            || !$this->password_matches($password, (string) $row[$password_column])) {
            return FALSE;
        }

        return array(
            'id'              => $this->column_value($row, 'rms_auth_id_column'),
            'username'        => $this->column_value($row, 'rms_auth_username_column'),
            'display_name'    => $this->column_value($row, 'rms_auth_display_name_column'),
            'role'            => $this->column_value($row, 'rms_auth_role_column'),
            'department'      => $this->column_value($row, 'rms_auth_department_column'),
            'location'        => $this->column_value($row, 'rms_auth_location_column'),
            'employee_id'     => $this->column_value($row, 'rms_auth_employee_id_column'),
            'position'        => $this->column_value($row, 'rms_auth_position_column'),
            'profile_picture' => $this->column_value($row, 'rms_auth_profile_picture_column'),
            'allowed_upload'  => $this->column_value($row, 'rms_auth_allowed_upload_column'),
            'last_visit'      => $this->column_value($row, 'rms_auth_last_visit_column')
        );
    }

    private function column_value($row, $config_key)
    {
        $column = $this->config->item($config_key);

        if ($column === '' || !isset($row[$column])) {
            return '';
        }

        return $row[$column];
    }

    private function password_matches($password, $stored_password)
    {
        $mode = strtolower((string) $this->config->item('rms_auth_password_mode'));

        if ($mode === 'auto') {
            if (preg_match('/^\$(2[axy]|5|6)\$/', $stored_password)) {
                $mode = 'crypt';
            } elseif (preg_match('/^[a-f0-9]{32}$/i', $stored_password)) {
                $mode = 'md5';
            } elseif (preg_match('/^[a-f0-9]{40}$/i', $stored_password)) {
                $mode = 'sha1';
            } elseif (preg_match('/^[a-f0-9]{64}$/i', $stored_password)) {
                $mode = 'sha256';
            } else {
                $mode = 'plain';
            }
        }

        switch ($mode) {
            case 'crypt':
                $calculated = crypt($password, $stored_password);
                return strlen($calculated) >= 13
                    && $this->constant_time_equals($stored_password, $calculated);

            case 'sha256':
                return $this->constant_time_equals(
                    strtolower($stored_password),
                    hash('sha256', $password)
                );

            case 'sha1':
                return $this->constant_time_equals(
                    strtolower($stored_password),
                    sha1($password)
                );

            case 'md5':
                return $this->constant_time_equals(
                    strtolower($stored_password),
                    md5($password)
                );

            case 'plain':
                return $this->constant_time_equals($stored_password, $password);
        }

        log_message('error', 'Unsupported RMS password mode: '.$mode);
        return FALSE;
    }

    /*
     * PHP 5.4-compatible comparison. hash_equals() is not available until
     * newer PHP versions.
     */
    private function constant_time_equals($known_string, $user_string)
    {
        if (!is_string($known_string) || !is_string($user_string)) {
            return FALSE;
        }

        $known_length = strlen($known_string);
        $user_length = strlen($user_string);
        $result = $known_length ^ $user_length;

        for ($index = 0; $index < $known_length; $index++) {
            $user_character = $index < $user_length ? ord($user_string[$index]) : 0;
            $result |= ord($known_string[$index]) ^ $user_character;
        }

        return $result === 0;
    }
}
