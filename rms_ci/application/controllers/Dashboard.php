    <?php
    defined('BASEPATH') or exit('No direct script access allowed');

    class Dashboard extends CI_Controller
    {
        public function __construct()
        {
            parent::__construct();

            $this->config->load('rms_auth');
            $this->load->helper('url');
            $this->load->database();
            $this->load->library('session');
        }

        public function index()
        {
            if ($this->session->userdata('rms_logged_in') !== TRUE) {
                redirect('login');
                return;
            }

            /*
            * Read-only count from the existing users table.
            * This does not insert, update, or delete any database record.
            */
            $users_count = (int) $this->db->count_all('users');
            /*
            * Read-only count of online users.
            * Old RMS status meaning: users.stat = 1 means Online.
            * This does not insert, update, or delete any database record.
            */
            $online_count = (int) $this->db
                ->where('stat', 1)
                ->count_all_results('users');

            $data = array(
                'page_title'       => 'Dashboard',
                'system_name'      => $this->config->item('rms_auth_system_name'),
                'company_name'     => $this->config->item('rms_auth_company_name'),
                'display_name'     => $this->session->userdata('rms_display_name'),
                'username'         => $this->session->userdata('rms_username'),
                'position'         => $this->session->userdata('rms_position'),
                'employee_id'      => $this->session->userdata('rms_employee_id'),
                'location'         => $this->session->userdata('rms_location'),
                'last_login'       => $this->session->userdata('rms_last_visit'),
                'pending_count'    => $this->unpublished_directory_count(),
                /*
                * Directory total only: Filename plus Subfolder1 through
                * Subfolder10. Uploaded rows in data/data_f are excluded.
                */
                'documents_count'  => $this->document_directory_count(),
                'users_count'      => $users_count,
                'online_count'     =>  $online_count,
                'routes'           => $this->config->item('rms_dashboard_routes')
            );

            $this->load->view('admin/dashboard', $data);
        }

        /**
         * Count active directory records without changing the legacy schema.
         *
         * Every table is checked before it is queried so installations with fewer
         * legacy subfolder tables still load the Dashboard safely. The uploaded
         * document tables are intentionally absent from this list.
         */
        private function document_directory_count()
        {
            $tables = array('filename');

            for ($level = 1; $level <= 10; $level++) {
                $tables[] = 'subfolder' . $level;
            }

            $total = 0;

            foreach ($tables as $table) {
                if (!$this->db->table_exists($table)) {
                    continue;
                }

                if ($this->db->field_exists('stat', $table)) {
                    $this->db->where('stat', 0);
                }

                $total += (int) $this->db
                    ->from($table)
                    ->count_all_results();
            }

            return $total;
        }
        /**
         * Count every active unpublished directory.
         *
         * Includes:
         * - filename
         * - subfolder1 through subfolder10
         *
         * Excludes uploaded files from data and data_f.
         * This is read-only and does not modify the legacy database.
         */
        private function unpublished_directory_count()
        {
            $tables = array(
                'filename'    => 'publish',
                'subfolder1'  => 'publish1',
                'subfolder2'  => 'publish2',
                'subfolder3'  => 'publish3',
                'subfolder4'  => 'publish4',
                'subfolder5'  => 'publish5',
                'subfolder6'  => 'publish6',
                'subfolder7'  => 'publish7',
                'subfolder8'  => 'publish8',
                'subfolder9'  => 'publish9',
                'subfolder10' => 'publish10'
            );

            $total = 0;

            foreach ($tables as $table => $publish_field) {
                /*
         * Skip missing legacy subfolder tables so the Dashboard
         * remains compatible with installations using fewer levels.
         */
                if (!$this->db->table_exists($table)) {
                    continue;
                }

                if (!$this->db->field_exists($publish_field, $table)) {
                    continue;
                }

                // Legacy RMS: publish = 0 means unpublished.
                $this->db->where($publish_field, 0);

                // Legacy RMS: stat = 0 means the directory record is active.
                if ($this->db->field_exists('stat', $table)) {
                    $this->db->where('stat', 0);
                }

                $total += (int) $this->db
                    ->from($table)
                    ->count_all_results();
            }

            return $total;
        }
    }
