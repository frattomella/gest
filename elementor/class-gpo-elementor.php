<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Elementor {
    public static function init() {
        add_action('elementor/widgets/register', [__CLASS__, 'register_widgets']);
        add_action('elementor/frontend/after_enqueue_styles', [__CLASS__, 'enqueue_editor_assets']);
    }

    public static function register_widgets($widgets_manager) {
        if (!did_action('elementor/loaded')) {
            return;
        }

        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }

        require_once GPO_PLUGIN_DIR . 'elementor/class-gpo-widget-grid.php';
        require_once GPO_PLUGIN_DIR . 'elementor/class-gpo-widget-carousel.php';
        require_once GPO_PLUGIN_DIR . 'elementor/class-gpo-widget-catalog.php';

        $widgets_manager->register(new GPO_Elementor_Widget_Grid());
        $widgets_manager->register(new GPO_Elementor_Widget_Carousel());
        $widgets_manager->register(new GPO_Elementor_Widget_Catalog());
    }

    public static function enqueue_editor_assets() {
        if (!self::is_editor_context()) {
            return;
        }

        wp_enqueue_style(
            'gpo-elementor-editor',
            GPO_PLUGIN_URL . 'elementor/assets/gpo-elementor-editor.css',
            [],
            GPO_VERSION
        );
    }

    public static function decorate_widget_wrapper($widget) {
        if (!is_object($widget) || !method_exists($widget, 'add_render_attribute')) {
            return;
        }

        $widget->add_render_attribute('_wrapper', 'class', 'gpo-elementor-widget-shell');

        if (self::is_editor_context()) {
            $widget->add_render_attribute('_wrapper', 'class', 'gpo-elementor-widget-shell--editor');
        }
    }

    public static function is_editor_context() {
        if (!did_action('elementor/loaded') || !class_exists('\Elementor\Plugin')) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance;
        $is_preview = isset($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode();
        $is_edit_mode = isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode();

        return $is_preview || $is_edit_mode;
    }
}
