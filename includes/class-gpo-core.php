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
        add_action('init', [$this, 'maybe_cleanup_legacy_demo_content'], 20);
        add_action('gpo_cron_sync', ['GPO_Sync_Manager', 'run_scheduled_sync']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('gestpark-online', false, dirname(plugin_basename(GPO_PLUGIN_FILE)) . '/languages');
    }

    public static function activate() {
        GPO_CPT::register_post_types();
        GPO_CPT::register_taxonomies();
        flush_rewrite_rules();

        if (!wp_next_scheduled('gpo_cron_sync')) {
            wp_schedule_event(time() + 300, 'gpo_five_minutes', 'gpo_cron_sync');
        }

        add_option('gpo_settings', GPO_Admin::default_settings());
        self::ensure_default_vehicle_template();
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
        $existing = get_posts([
            'post_type' => 'gpo_template',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        if (!empty($existing)) {
            $settings = wp_parse_args(get_option('gpo_settings', []), GPO_Admin::default_settings());
            if (empty($settings['style']['single_template_id'])) {
                $settings['style']['single_template_id'] = absint($existing[0]);
                update_option('gpo_settings', $settings);
            }
            return;
        }

        $content = '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} --><div class="wp-block-group alignwide">'
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

        $template_id = wp_insert_post([
            'post_type' => 'gpo_template',
            'post_status' => 'publish',
            'post_title' => 'Scheda veicolo predefinita',
            'post_content' => $content,
        ]);

        if ($template_id && !is_wp_error($template_id)) {
            $settings = wp_parse_args(get_option('gpo_settings', []), GPO_Admin::default_settings());
            $settings['style']['single_template_id'] = absint($template_id);
            update_option('gpo_settings', $settings);
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('gpo_cron_sync');
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

    $schedules['gpo_thirty_minutes'] = [
        'interval' => 1800,
        'display'  => 'Ogni 30 minuti',
    ];

    return $schedules;
});
