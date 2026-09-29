<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Creates downloadable RMS database and application backups.
 */
class Backup_model extends CI_Model
{
    /**
     * Generate a portable SQL export using CodeIgniter's database utility.
     *
     * @return array
     */
    public function create_database_backup()
    {
        $this->load->dbutil();
        $sql = $this->dbutil->backup(array(
            'format' => 'txt',
            'add_drop' => TRUE,
            'add_insert' => TRUE,
            'newline' => "\n"
        ));

        if ($sql === FALSE || $sql === '') {
            return $this->failure('The database backup could not be generated.');
        }

        $path = $this->temporary_path('.sql');
        if ($path === FALSE || file_put_contents($path, $sql, LOCK_EX) === FALSE) {
            return $this->failure('The server could not create the temporary SQL file.');
        }

        return array(
            'success' => TRUE,
            'path' => $path,
            'filename' => 'rms-database-' . date('Y-m-d-His') . '.sql',
            'mime' => 'application/sql'
        );
    }

    /**
     * Package the current CI3 application together with its SQL export.
     *
     * @return array
     */
    public function create_system_backup()
    {
        if (!class_exists('ZipArchive')) {
            return $this->failure('ZIP support is not enabled on this server. Enable the PHP zip extension first.');
        }

        $database = $this->create_database_backup();
        if (!$database['success']) {
            return $database;
        }

        $zip_path = $this->temporary_path('.zip');
        $zip = new ZipArchive();
        if ($zip_path === FALSE || $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            @unlink($database['path']);
            return $this->failure('The system ZIP file could not be created.');
        }

        $root = realpath(FCPATH);
        $closed = FALSE;

        try {
            $zip->addFile($database['path'], 'database/' . $database['filename']);
            $this->add_application_files($zip, $root);
            $closed = $zip->close();
        } catch (Exception $exception) {
            // Close and remove partial output when a server folder is unreadable.
            $zip->close();
        }
        @unlink($database['path']);

        if (!$closed || !is_file($zip_path) || filesize($zip_path) < 1) {
            @unlink($zip_path);
            return $this->failure('The system backup could not be completed.');
        }

        return array(
            'success' => TRUE,
            'path' => $zip_path,
            'filename' => 'rms-system-and-database-' . date('Y-m-d-His') . '.zip',
            'mime' => 'application/zip'
        );
    }

    /**
     * Add application files while excluding volatile or unsafe directories.
     */
    private function add_application_files($zip, $root)
    {
        $excluded = array('.git', 'application/cache', 'application/logs', 'application/sessions');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $absolute = $file->getRealPath();
            $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
            if ($this->is_excluded($relative, $excluded)) {
                continue;
            }

            $zip->addFile($absolute, 'application/' . $relative);
        }
    }

    /**
     * Check a normalized relative path against excluded directory prefixes.
     */
    private function is_excluded($path, $excluded)
    {
        foreach ($excluded as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Reserve a writable temporary filename using the operating system folder.
     */
    private function temporary_path($extension)
    {
        $base = tempnam(sys_get_temp_dir(), 'rms_backup_');
        if ($base === FALSE) {
            return FALSE;
        }

        $path = $base . $extension;
        if (!@rename($base, $path)) {
            @unlink($base);
            return FALSE;
        }
        return $path;
    }

    /**
     * Return one consistent failure result to the controller.
     */
    private function failure($message)
    {
        return array('success' => FALSE, 'message' => $message);
    }
}
