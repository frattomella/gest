<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Logger {
    public static function add($message, $context = []) {
        $logs = get_option('gpo_logs', []);
        $logs[] = [
            'time'    => current_time('mysql'),
            'message' => wp_strip_all_tags((string) $message),
            'context' => $context,
        ];

        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }

        update_option('gpo_logs', $logs, false);
    }

    public static function all() {
        return array_reverse((array) get_option('gpo_logs', []));
    }

    public static function clear() {
        delete_option('gpo_logs');
    }
}
