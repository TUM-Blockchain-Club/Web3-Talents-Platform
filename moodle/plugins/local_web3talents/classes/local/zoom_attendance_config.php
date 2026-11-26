<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Separate from host delegation. Secrets live outside the web root and Moodle settings DB. */
class zoom_attendance_config {
    private static function path(): string {
        global $CFG;
        return $CFG->dataroot . '/web3talents-private/zoom-attendance.json';
    }

    public static function read(): array {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    public static function save(array $data): void {
        $path = self::path();
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) {
            throw new \RuntimeException('zoom_attendance_config_error');
        }
        $temporary = tempnam(dirname($path), '.zoom-');
        try {
            if (!$temporary || !chmod($temporary, 0600) ||
                    file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false ||
                    !rename($temporary, $path)) {
                throw new \RuntimeException('zoom_attendance_config_error');
            }
        } finally {
            if ($temporary && is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
