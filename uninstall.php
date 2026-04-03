<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('gpo_settings');
delete_option('gpo_logs');
delete_option('gpo_last_sync_result');
