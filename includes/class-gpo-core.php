<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Core {
    public function boot() {
        GPO_CPT::init();
        GPO_Admin::init();
        GPO_Frontend::init();
        GPO_Blocks::init();
        GPO_Elementor::init();
        GPO_GitHub_Updater::init();

        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_init', [__CLASS__, 'ensure_sync_schedule'], 5);
        add_action('admin_init', [$this, 'maybe_cleanup_legacy_demo_content'], 20);
        add_action('gpo_cron_sync', ['GPO_Sync_Manager', 'run_scheduled_sync']);
        add_action('update_option_gpo_settings', [__CLASS__, 'handle_settings_update'], 10, 2);
    }

    public function load_textdomain() {
        load_plugin_textdomain('gestpark-online', false, dirname(plugin_basename(GPO_PLUGIN_FILE)) . '/languages');
    }

    public static function activate() {
        GPO_CPT::register_post_types();
        GPO_CPT::register_taxonomies();
        flush_rewrite_rules();

        add_option('gpo_settings', GPO_Admin::default_settings());
        self::ensure_sync_schedule();
    }

    public static function handle_settings_update($old_value, $value) {
        $old_sync = is_array($old_value) && isset($old_value['sync']) ? (array) $old_value['sync'] : [];
        $new_sync = is_array($value) && isset($value['sync']) ? (array) $value['sync'] : [];

        if ($old_sync !== $new_sync) {
            self::ensure_sync_schedule();
        }
    }

    public static function ensure_sync_schedule() {
        self::maybe_enable_automatic_sync();

        $settings = get_option('gpo_settings', []);
        $sync = is_array($settings) && isset($settings['sync']) ? (array) $settings['sync'] : [];
        $enabled = !empty($sync['enabled']);
        $schedule = self::sync_schedule_name(isset($sync['interval']) ? $sync['interval'] : 5);
        $event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event('gpo_cron_sync') : null;

        if (!$enabled) {
            if ($event || wp_next_scheduled('gpo_cron_sync')) {
                wp_clear_scheduled_hook('gpo_cron_sync');
            }
            return;
        }

        if ($event && isset($event->schedule) && $event->schedule !== $schedule) {
            wp_clear_scheduled_hook('gpo_cron_sync');
            $event = null;
        }

        if (!$event && !wp_next_scheduled('gpo_cron_sync')) {
            wp_schedule_event(time() + 60, $schedule, 'gpo_cron_sync');
        }
    }

    protected static function maybe_enable_automatic_sync() {
        if (get_option('gpo_auto_sync_migrated_20260719')) {
            return;
        }

        $settings = get_option('gpo_settings', []);
        update_option('gpo_auto_sync_migrated_20260719', current_time('mysql'), false);

        if (is_array($settings)) {
            $settings['sync'] = isset($settings['sync']) && is_array($settings['sync']) ? $settings['sync'] : [];
            $settings['sync']['enabled'] = 1;
            $settings['sync']['interval'] = isset($settings['sync']['interval']) ? absint($settings['sync']['interval']) : 5;
            update_option('gpo_settings', $settings);
        }
    }

    protected static function sync_schedule_name($interval) {
        $schedules = [
            5 => 'gpo_five_minutes',
            10 => 'gpo_ten_minutes',
            15 => 'gpo_fifteen_minutes',
            30 => 'gpo_thirty_minutes',
            60 => 'hourly',
        ];
        $interval = absint($interval);

        return isset($schedules[$interval]) ? $schedules[$interval] : 'gpo_five_minutes';
    }

    public function maybe_cleanup_legacy_demo_content() {
        if (get_option('gpo_legacy_demo_cleanup_20260402')) {
            return;
        }

        if (class_exists('GPO_Sync_Manager')) {
            GPO_Sync_Manager::purge_legacy_demo_vehicles();
        }

        update_option('gpo_legacy_demo_cleanup_20260402', current_time('mysql'), false);
    }

    public static function ensure_default_vehicle_template() {
        return 0;
    }

    protected static function default_vehicle_template_content() {
        return '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} --><div class="wp-block-group alignwide">'
            . '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
            . '<!-- wp:column {"width":"64%"} --><div class="wp-block-column" style="flex-basis:64%">'
            . '<!-- wp:gestpark/vehicle-gallery /-->'
            . '</div><!-- /wp:column -->'
            . '<!-- wp:column {"width":"36%"} --><div class="wp-block-column" style="flex-basis:36%">'
            . '<!-- wp:gestpark/vehicle-hero {"showImage":false,"showMeta":false,"showLeadForm":true} /-->'
            . '</div><!-- /wp:column -->'
            . '</div><!-- /wp:columns -->'
            . '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
            . '<!-- wp:column {"width":"66.66%"} --><div class="wp-block-column" style="flex-basis:66.66%">'
            . '<!-- wp:gestpark/vehicle-description /-->'
            . '<!-- wp:gestpark/vehicle-accessories /-->'
            . '<!-- wp:gestpark/vehicle-notes /-->'
            . '</div><!-- /wp:column -->'
            . '<!-- wp:column {"width":"33.33%"} --><div class="wp-block-column" style="flex-basis:33.33%">'
            . '<!-- wp:gestpark/vehicle-specs /-->'
            . '</div><!-- /wp:column -->'
            . '</div><!-- /wp:columns -->'
            . '<!-- wp:gestpark/vehicle-carousel {"source":"related_brand","limit":6} /-->'
            . '</div><!-- /wp:group -->';
    }

    protected static function legacy_default_vehicle_template_content_v3() {
        return '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} --><div class="wp-block-group alignwide">'
            . '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
            . '<!-- wp:column {"width":"63%"} --><div class="wp-block-column" style="flex-basis:63%">'
            . '<!-- wp:gestpark/vehicle-gallery /-->'
            . '</div><!-- /wp:column -->'
            . '<!-- wp:column {"width":"37%"} --><div class="wp-block-column" style="flex-basis:37%">'
            . '<!-- wp:gestpark/vehicle-hero {"showImage":false,"showMeta":false,"showButton":false,"showLeadForm":true} /-->'
            . '</div><!-- /wp:column -->'
            . '</div><!-- /wp:columns -->'
            . '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
            . '<!-- wp:column {"width":"66.66%"} --><div class="wp-block-column" style="flex-basis:66.66%">'
            . '<!-- wp:gestpark/vehicle-description /-->'
            . '<!-- wp:gestpark/vehicle-accessories /-->'
            . '<!-- wp:gestpark/vehicle-notes /-->'
            . '</div><!-- /wp:column -->'
            . '<!-- wp:column {"width":"33.33%"} --><div class="wp-block-column" style="flex-basis:33.33%">'
            . '<!-- wp:gestpark/vehicle-specs /-->'
            . '</div><!-- /wp:column -->'
            . '</div><!-- /wp:columns -->'
            . '<!-- wp:gestpark/vehicle-carousel {"source":"related_brand","limit":6} /-->'
            . '</div><!-- /wp:group -->';
    }

    protected static function legacy_default_vehicle_template_content() {
        return '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} --><div class="wp-block-group alignwide">'
            . '<!-- wp:gestpark/vehicle-hero /-->'
            . '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
            . '<!-- wp:column {"width":"66.66%"} --><div class="wp-block-column" style="flex-basis:66.66%">'
            . '<!-- wp:gestpark/vehicle-description /-->'
            . '<!-- wp:gestpark/vehicle-accessories /-->'
            . '<!-- wp:gestpark/vehicle-notes /-->'
            . '</div><!-- /wp:column -->'
            . '<!-- wp:column {"width":"33.33%"} --><div class="wp-block-column" style="flex-basis:33.33%">'
            . '<!-- wp:gestpark/vehicle-specs /-->'
            . '<!-- wp:gestpark/vehicle-contact /-->'
            . '</div><!-- /wp:column -->'
            . '</div><!-- /wp:columns -->'
            . '<!-- wp:gestpark/vehicle-carousel {"source":"related_brand","limit":6} /-->'
            . '</div><!-- /wp:group -->';
    }

    protected static function legacy_default_vehicle_template_content_v2() {
        return '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} --><div class="wp-block-group alignwide">'
            . '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
            . '<!-- wp:column {"width":"63%"} --><div class="wp-block-column" style="flex-basis:63%">'
            . '<!-- wp:gestpark/vehicle-gallery /-->'
            . '</div><!-- /wp:column -->'
            . '<!-- wp:column {"width":"37%"} --><div class="wp-block-column" style="flex-basis:37%">'
            . '<!-- wp:gestpark/vehicle-hero {"showImage":false,"showMeta":false,"showButton":false} /-->'
            . '<!-- wp:gestpark/vehicle-contact /-->'
            . '</div><!-- /wp:column -->'
            . '</div><!-- /wp:columns -->'
            . '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
            . '<!-- wp:column {"width":"66.66%"} --><div class="wp-block-column" style="flex-basis:66.66%">'
            . '<!-- wp:gestpark/vehicle-description /-->'
            . '<!-- wp:gestpark/vehicle-accessories /-->'
            . '<!-- wp:gestpark/vehicle-notes /-->'
            . '</div><!-- /wp:column -->'
            . '<!-- wp:column {"width":"33.33%"} --><div class="wp-block-column" style="flex-basis:33.33%">'
            . '<!-- wp:gestpark/vehicle-specs /-->'
            . '</div><!-- /wp:column -->'
            . '</div><!-- /wp:columns -->'
            . '<!-- wp:gestpark/vehicle-carousel {"source":"related_brand","limit":6} /-->'
            . '</div><!-- /wp:group -->';
    }

    protected static function maybe_refresh_default_vehicle_template($template_id) {
        if (!$template_id || get_option('gpo_vehicle_template_refresh_20260403_layout')) {
            return;
        }

        $template = get_post($template_id);
        if (!$template || $template->post_type !== 'gpo_template') {
            return;
        }

        $current = trim((string) $template->post_content);
        $legacy = trim(self::legacy_default_vehicle_template_content());
        $legacy_v2 = trim(self::legacy_default_vehicle_template_content_v2());
        $legacy_v3 = trim(self::legacy_default_vehicle_template_content_v3());

        if ($current !== $legacy && $current !== $legacy_v2 && $current !== $legacy_v3) {
            update_option('gpo_vehicle_template_refresh_20260403_layout', 'skipped', false);
            return;
        }

        wp_update_post([
            'ID' => $template_id,
            'post_content' => self::default_vehicle_template_content(),
        ]);
        update_option('gpo_vehicle_template_refresh_20260403_layout', current_time('mysql'), false);
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('gpo_cron_sync');
        if (class_exists('GPO_Sync_Manager')) {
            delete_option(GPO_Sync_Manager::LOCK_OPTION);
        }
        flush_rewrite_rules();
    }
}

add_filter('cron_schedules', function ($schedules) {
    $schedules['gpo_five_minutes'] = [
        'interval' => 300,
        'display'  => 'Ogni 5 minuti',
    ];

    $schedules['gpo_ten_minutes'] = [
        'interval' => 600,
        'display'  => 'Ogni 10 minuti',
    ];

    $schedules['gpo_fifteen_minutes'] = [
        'interval' => 900,
        'display'  => 'Ogni 15 minuti',
    ];

    $schedules['gpo_thirty_minutes'] = [
        'interval' => 1800,
        'display'  => 'Ogni 30 minuti',
    ];

    return $schedules;
});
