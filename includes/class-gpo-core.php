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
