<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Elementor {
    protected static $frontend_assets_ready = false;

    public static function init() {
        add_action('elementor/elements/categories_registered', [__CLASS__, 'register_category']);
        add_action('elementor/widgets/register', [__CLASS__, 'register_widgets']);
        add_action('elementor/editor/after_enqueue_styles', [__CLASS__, 'enqueue_editor_assets']);
        add_action('elementor/editor/after_enqueue_scripts', [__CLASS__, 'enqueue_editor_assets']);
        add_action('elementor/preview/enqueue_styles', [__CLASS__, 'enqueue_editor_assets']);
        add_action('elementor/preview/enqueue_scripts', [__CLASS__, 'enqueue_editor_assets']);
        add_action('elementor/frontend/after_enqueue_styles', [__CLASS__, 'enqueue_editor_assets']);
    }

    public static function register_category($elements_manager) {
        if (!is_object($elements_manager) || !method_exists($elements_manager, 'add_category')) {
            return;
        }

        $elements_manager->add_category(self::widget_category(), [
            'title' => 'GestPark Online',
            'icon' => 'fa fa-car',
        ]);
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
        require_once GPO_PLUGIN_DIR . 'elementor/class-gpo-widget-featured.php';
        require_once GPO_PLUGIN_DIR . 'elementor/class-gpo-widget-brand-carousel.php';
        require_once GPO_PLUGIN_DIR . 'elementor/class-gpo-widget-search.php';

        $widgets_manager->register(new GPO_Elementor_Widget_Grid());
        $widgets_manager->register(new GPO_Elementor_Widget_Carousel());
        $widgets_manager->register(new GPO_Elementor_Widget_Catalog());
        $widgets_manager->register(new GPO_Elementor_Widget_Featured());
        $widgets_manager->register(new GPO_Elementor_Widget_Brand_Carousel());
        $widgets_manager->register(new GPO_Elementor_Widget_Search());
    }

    public static function enqueue_editor_assets() {
        if (!self::is_editor_context()) {
            return;
        }

        self::ensure_frontend_assets();

        wp_enqueue_style('gpo-public');
        wp_enqueue_script('gpo-carousel');
        wp_enqueue_script('gpo-live-search');
        wp_enqueue_script('gpo-vehicle-gallery');
        wp_enqueue_style(
            'gpo-elementor-editor',
            GPO_PLUGIN_URL . 'elementor/assets/gpo-elementor-editor.css',
            ['gpo-public'],
            gpo_asset_version('elementor/assets/gpo-elementor-editor.css')
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

    public static function widget_category() {
        return 'gestpark';
    }

    public static function page_options() {
        $options = [
            '0' => 'Usa catalogo automatico',
        ];

        $pages = get_pages([
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
        ]);

        foreach ($pages as $page) {
            $title = trim((string) $page->post_title);
            $options[(string) $page->ID] = $title !== '' ? $title : ('Pagina #' . (int) $page->ID);
        }

        return $options;
    }

    protected static function ensure_frontend_assets() {
        if (self::$frontend_assets_ready) {
            return;
        }

        if (class_exists('GPO_Frontend') && method_exists('GPO_Frontend', 'assets')) {
            GPO_Frontend::assets();
        }

        self::$frontend_assets_ready = true;
    }
}
