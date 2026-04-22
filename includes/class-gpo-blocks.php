<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Blocks {
    public static function init() {
        add_action('init', [__CLASS__, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [__CLASS__, 'enqueue_shared_block_assets']);
    }

    public static function register_blocks() {
        wp_register_script('gpo-blocks', GPO_PLUGIN_URL . 'blocks/gpo-blocks.js', ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-data'], gpo_asset_version('blocks/gpo-blocks.js'), true);

        if (!wp_style_is('gpo-public', 'registered')) {
            wp_register_style('gpo-public', GPO_PLUGIN_URL . 'public/assets/css/gpo-public.css', [], gpo_asset_version('public/assets/css/gpo-public.css'));
        }
        wp_register_style('gpo-editor', GPO_PLUGIN_URL . 'blocks/gpo-editor.css', ['wp-edit-blocks', 'gpo-public'], gpo_asset_version('blocks/gpo-editor.css'));

        self::register_catalog_blocks();
        self::register_brand_search_blocks();
        self::register_single_vehicle_blocks();
    }

    protected static function common_supports() {
        return [
            'spacing' => [
                'margin' => true,
                'padding' => true,
                'blockGap' => true,
            ],
            'align' => ['wide', 'full'],
        ];
    }

    protected static function internal_supports() {
        return array_merge(self::common_supports(), [
            'inserter' => false,
        ]);
    }

    protected static function styled_card_attributes(array $defaults = []) {
        return array_merge($defaults, [
            'showDesktop' => ['type' => 'string', 'default' => ''],
            'showTablet' => ['type' => 'string', 'default' => ''],
            'showMobile' => ['type' => 'string', 'default' => ''],
            'primaryColor' => ['type' => 'string', 'default' => ''],
            'accentColor' => ['type' => 'string', 'default' => ''],
            'bgColor' => ['type' => 'string', 'default' => ''],
            'textColor' => ['type' => 'string', 'default' => ''],
            'buttonColor' => ['type' => 'string', 'default' => ''],
            'buttonTextColor' => ['type' => 'string', 'default' => ''],
            'primaryButtonLabel' => ['type' => 'string', 'default' => 'Scheda veicolo'],
            'outerPaddingX' => ['type' => 'number', 'default' => 18],
            'sectionGap' => ['type' => 'number', 'default' => 24],
        ]);
    }

    protected static function responsive_show_shortcode_attributes($attributes = []) {
        $parts = '';
        $map = [
            'showDesktop' => 'show_desktop',
            'showTablet' => 'show_tablet',
            'showMobile' => 'show_mobile',
        ];

        foreach ($map as $attribute => $shortcode_attribute) {
            if (!empty($attributes[$attribute])) {
                $parts .= ' ' . $shortcode_attribute . '="' . esc_attr($attributes[$attribute]) . '"';
            }
        }

        return $parts;
    }

    protected static function responsive_filter_shortcode_attributes($attributes = []) {
        $parts = '';
        $map = [
            'filterFieldsDesktop' => 'filter_fields_desktop',
            'filterFieldsTablet' => 'filter_fields_tablet',
            'filterFieldsMobile' => 'filter_fields_mobile',
        ];

        foreach ($map as $attribute => $shortcode_attribute) {
            if (!empty($attributes[$attribute])) {
                $parts .= ' ' . $shortcode_attribute . '="' . esc_attr($attributes[$attribute]) . '"';
            }
        }

        return $parts;
    }

    protected static function search_shortcode_attributes($attributes = []) {
        $parts = '';
        $visibility = [
            'showOnDesktop' => 'show_on_desktop',
            'showOnTablet' => 'show_on_tablet',
            'showOnMobile' => 'show_on_mobile',
        ];

        foreach ($visibility as $attribute => $shortcode_attribute) {
            if (array_key_exists($attribute, $attributes)) {
                $parts .= ' ' . $shortcode_attribute . '="' . (!empty($attributes[$attribute]) ? 'yes' : 'no') . '"';
            }
        }

        if (!empty($attributes['mobileMode'])) {
            $parts .= ' mobile_mode="' . esc_attr($attributes['mobileMode']) . '"';
        }

        return $parts;
    }

    public static function enqueue_shared_block_assets() {
        if (!wp_style_is('gpo-public', 'registered')) {
            wp_register_style('gpo-public', GPO_PLUGIN_URL . 'public/assets/css/gpo-public.css', [], gpo_asset_version('public/assets/css/gpo-public.css'));
        }

        $defaults = method_exists('GPO_Admin', 'default_settings') ? GPO_Admin::default_settings() : [];
        $settings = wp_parse_args(get_option('gpo_settings', []), $defaults);
        $style = isset($settings['style']) && is_array($settings['style']) ? $settings['style'] : [];
        $css = ':root{' .
            '--gpo-primary:' . esc_attr($style['primary_color'] ?? '#111827') . ';' .
            '--gpo-accent:' . esc_attr($style['accent_color'] ?? '#dc2626') . ';' .
            '--gpo-promo-color:' . esc_attr(class_exists('GPO_Engagement') ? GPO_Engagement::promo_color() : '#dc2626') . ';' .
            '--gpo-bg:' . esc_attr($style['card_bg'] ?? '#ffffff') . ';' .
            '--gpo-radius:' . esc_attr($style['radius'] ?? '16px') . ';' .
            '--gpo-title-font:' . esc_attr($style['title_font'] ?? 'inherit') . ';' .
            '--gpo-body-font:' . esc_attr($style['body_font'] ?? 'inherit') . ';' .
            '--gpo-card-gap:' . absint($style['card_gap'] ?? 24) . 'px;' .
            '--gpo-card-padding:' . absint($style['card_padding'] ?? 22) . 'px;' .
            '--gpo-content-max-width:' . absint($style['content_max_width'] ?? 1280) . 'px;' .
            '--gpo-shell-margin-y:' . absint($style['outer_margin_y'] ?? 0) . 'px;' .
            '--gpo-shell-padding-x:' . absint($style['outer_padding_x'] ?? 18) . 'px;' .
            '--gpo-section-gap:' . absint($style['section_gap'] ?? 24) . 'px;' .
            '--gpo-filter-columns:' . max(2, min(6, absint($style['filter_columns'] ?? 5))) . ';' .
            '--gpo-muted:#6b7280;' .
            '--gpo-border:#e5e7eb;' .
            '--gpo-surface:#f8fafc;' .
            '--gpo-shadow:0 16px 40px rgba(15,23,42,.08);' .
        '}';

        wp_add_inline_style('gpo-public', $css);

        if (is_admin()) {
            wp_enqueue_style('gpo-public');
        }
    }

    public static function enqueue_editor_assets() {
        self::enqueue_shared_block_assets();
        wp_enqueue_style('gpo-editor');
        wp_enqueue_script('gpo-blocks');
        wp_localize_script('gpo-blocks', 'gpoBlockData', [
            'catalogPages' => self::catalog_pages_for_editor(),
        ]);
    }

    protected static function register_catalog_blocks() {
        register_block_type('gestpark/vehicle-grid', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => function ($attributes) {
                $limit = isset($attributes['limit']) ? absint($attributes['limit']) : 6;
                $columns = isset($attributes['columns']) ? absint($attributes['columns']) : 3;
                $show = isset($attributes['show']) ? sanitize_text_field($attributes['show']) : '';
                $card_layout = isset($attributes['cardLayout']) ? sanitize_key($attributes['cardLayout']) : 'default';
                $filter_fields = isset($attributes['filterFields']) ? sanitize_text_field($attributes['filterFields']) : '';
                $outer_padding_x = isset($attributes['outerPaddingX']) ? absint($attributes['outerPaddingX']) : 18;
                $section_gap = isset($attributes['sectionGap']) ? absint($attributes['sectionGap']) : 24;
                return self::safe_dynamic_block('gpo-block-catalog', $attributes, function () use ($limit, $columns, $show, $attributes, $card_layout, $filter_fields, $outer_padding_x, $section_gap) {
                    return do_shortcode('[gestpark_vehicle_grid limit="' . $limit . '" columns="' . $columns . '" show="' . esc_attr($show) . '"' . self::responsive_show_shortcode_attributes($attributes) . ' card_layout="' . esc_attr($card_layout) . '" filter_fields="' . esc_attr($filter_fields) . '"' . self::responsive_filter_shortcode_attributes($attributes) . ' outer_padding_x="' . $outer_padding_x . '" section_gap="' . $section_gap . '" primary_color="' . esc_attr($attributes['primaryColor'] ?? '') . '" accent_color="' . esc_attr($attributes['accentColor'] ?? '') . '" bg_color="' . esc_attr($attributes['bgColor'] ?? '') . '" text_color="' . esc_attr($attributes['textColor'] ?? '') . '" button_color="' . esc_attr($attributes['buttonColor'] ?? '') . '" button_text_color="' . esc_attr($attributes['buttonTextColor'] ?? '') . '" primary_button_label="' . esc_attr($attributes['primaryButtonLabel'] ?? 'Scheda veicolo') . '"]');
                }, 'Anteprima griglia non disponibile', 'Importa almeno un veicolo reale oppure verifica la connessione ParkPlatform.');
            },
            'attributes' => self::styled_card_attributes([
                'limit' => ['type' => 'number', 'default' => 6],
                'columns' => ['type' => 'number', 'default' => 3],
                'show' => ['type' => 'string', 'default' => ''],
                'cardLayout' => ['type' => 'string', 'default' => 'default'],
                'filterFields' => ['type' => 'string', 'default' => ''],
                'filterFieldsDesktop' => ['type' => 'string', 'default' => ''],
                'filterFieldsTablet' => ['type' => 'string', 'default' => ''],
                'filterFieldsMobile' => ['type' => 'string', 'default' => ''],
            ]),
            'supports' => self::common_supports(),
        ]);

        register_block_type('gestpark/vehicle-catalog', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => function ($attributes) {
                $limit = isset($attributes['limit']) ? absint($attributes['limit']) : 12;
                $columns = isset($attributes['columns']) ? absint($attributes['columns']) : 3;
                $show = isset($attributes['show']) ? sanitize_text_field($attributes['show']) : '';
                $card_layout = isset($attributes['cardLayout']) ? sanitize_key($attributes['cardLayout']) : 'default';
                $filter_fields = isset($attributes['filterFields']) ? sanitize_text_field($attributes['filterFields']) : '';
                $outer_padding_x = isset($attributes['outerPaddingX']) ? absint($attributes['outerPaddingX']) : 18;
                $section_gap = isset($attributes['sectionGap']) ? absint($attributes['sectionGap']) : 24;
                return self::safe_dynamic_block('gpo-block-catalog', $attributes, function () use ($limit, $columns, $show, $attributes, $card_layout, $filter_fields, $outer_padding_x, $section_gap) {
                    return do_shortcode('[gestpark_vehicle_catalog limit="' . $limit . '" columns="' . $columns . '" show="' . esc_attr($show) . '"' . self::responsive_show_shortcode_attributes($attributes) . ' card_layout="' . esc_attr($card_layout) . '" filter_fields="' . esc_attr($filter_fields) . '"' . self::responsive_filter_shortcode_attributes($attributes) . ' outer_padding_x="' . $outer_padding_x . '" section_gap="' . $section_gap . '" primary_color="' . esc_attr($attributes['primaryColor'] ?? '') . '" accent_color="' . esc_attr($attributes['accentColor'] ?? '') . '" bg_color="' . esc_attr($attributes['bgColor'] ?? '') . '" text_color="' . esc_attr($attributes['textColor'] ?? '') . '" button_color="' . esc_attr($attributes['buttonColor'] ?? '') . '" button_text_color="' . esc_attr($attributes['buttonTextColor'] ?? '') . '" primary_button_label="' . esc_attr($attributes['primaryButtonLabel'] ?? 'Scheda veicolo') . '"]');
                }, 'Anteprima catalogo non disponibile', 'Importa almeno un veicolo reale oppure verifica la connessione ParkPlatform.');
            },
            'attributes' => self::styled_card_attributes([
                'limit' => ['type' => 'number', 'default' => 12],
                'columns' => ['type' => 'number', 'default' => 3],
                'show' => ['type' => 'string', 'default' => ''],
                'cardLayout' => ['type' => 'string', 'default' => 'default'],
                'filterFields' => ['type' => 'string', 'default' => ''],
                'filterFieldsDesktop' => ['type' => 'string', 'default' => ''],
                'filterFieldsTablet' => ['type' => 'string', 'default' => ''],
                'filterFieldsMobile' => ['type' => 'string', 'default' => ''],
            ]),
            'supports' => self::common_supports(),
        ]);

        register_block_type('gestpark/featured-carousel', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => function ($attributes) {
                $show = isset($attributes['show']) ? sanitize_text_field($attributes['show']) : '';
                $card_layout = isset($attributes['cardLayout']) ? sanitize_key($attributes['cardLayout']) : 'default';
                $outer_padding_x = isset($attributes['outerPaddingX']) ? absint($attributes['outerPaddingX']) : 18;
                $section_gap = isset($attributes['sectionGap']) ? absint($attributes['sectionGap']) : 24;
                return self::safe_dynamic_block('gpo-block-featured-carousel', $attributes, function () use ($show, $attributes, $card_layout, $outer_padding_x, $section_gap) {
                    return do_shortcode('[gestpark_featured_carousel show="' . esc_attr($show) . '"' . self::responsive_show_shortcode_attributes($attributes) . ' card_layout="' . esc_attr($card_layout) . '" autoplay="' . (!empty($attributes['autoplay']) ? 'yes' : 'no') . '" interval="' . absint($attributes['interval'] ?? 5000) . '" items_per_page="' . absint($attributes['itemsPerPage'] ?? 3) . '" show_title="' . (!empty($attributes['showTitle']) ? 'yes' : 'no') . '" section_title="' . esc_attr($attributes['sectionTitle'] ?? 'Veicoli selezionati') . '" outer_padding_x="' . $outer_padding_x . '" section_gap="' . $section_gap . '" primary_color="' . esc_attr($attributes['primaryColor'] ?? '') . '" accent_color="' . esc_attr($attributes['accentColor'] ?? '') . '" bg_color="' . esc_attr($attributes['bgColor'] ?? '') . '" text_color="' . esc_attr($attributes['textColor'] ?? '') . '" button_color="' . esc_attr($attributes['buttonColor'] ?? '') . '" button_text_color="' . esc_attr($attributes['buttonTextColor'] ?? '') . '" primary_button_label="' . esc_attr($attributes['primaryButtonLabel'] ?? 'Scheda veicolo') . '"]');
                }, 'Anteprima carosello non disponibile', 'Importa almeno un veicolo reale oppure verifica che la vetrina ParkPlatform sia attiva.');
            },
            'attributes' => self::styled_card_attributes([
                'show' => ['type' => 'string', 'default' => ''],
                'cardLayout' => ['type' => 'string', 'default' => 'default'],
                'autoplay' => ['type' => 'boolean', 'default' => true],
                'interval' => ['type' => 'number', 'default' => 5000],
                'itemsPerPage' => ['type' => 'number', 'default' => 3],
                'showTitle' => ['type' => 'boolean', 'default' => true],
                'sectionTitle' => ['type' => 'string', 'default' => 'Veicoli selezionati'],
            ]),
            'supports' => self::common_supports(),
        ]);

        register_block_type('gestpark/featured-vehicle', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => function ($attributes) {
                $show = isset($attributes['show']) ? sanitize_text_field($attributes['show']) : '';
                $card_layout = isset($attributes['cardLayout']) ? sanitize_key($attributes['cardLayout']) : 'default';
                $outer_padding_x = isset($attributes['outerPaddingX']) ? absint($attributes['outerPaddingX']) : 18;
                $section_gap = isset($attributes['sectionGap']) ? absint($attributes['sectionGap']) : 24;
                return self::safe_dynamic_block('gpo-block-featured-vehicle', $attributes, function () use ($show, $attributes, $card_layout, $outer_padding_x, $section_gap) {
                    return do_shortcode('[gestpark_featured_vehicle show="' . esc_attr($show) . '"' . self::responsive_show_shortcode_attributes($attributes) . ' card_layout="' . esc_attr($card_layout) . '" outer_padding_x="' . $outer_padding_x . '" section_gap="' . $section_gap . '" primary_color="' . esc_attr($attributes['primaryColor'] ?? '') . '" accent_color="' . esc_attr($attributes['accentColor'] ?? '') . '" bg_color="' . esc_attr($attributes['bgColor'] ?? '') . '" text_color="' . esc_attr($attributes['textColor'] ?? '') . '" button_color="' . esc_attr($attributes['buttonColor'] ?? '') . '" button_text_color="' . esc_attr($attributes['buttonTextColor'] ?? '') . '" primary_button_label="' . esc_attr($attributes['primaryButtonLabel'] ?? 'Scheda veicolo') . '"]');
                }, 'Anteprima veicolo non disponibile', 'Importa almeno un veicolo reale oppure verifica la connessione ParkPlatform.');
            },
            'attributes' => self::styled_card_attributes([
                'show' => ['type' => 'string', 'default' => ''],
                'cardLayout' => ['type' => 'string', 'default' => 'default'],
            ]),
            'supports' => self::common_supports(),
        ]);
    }



    protected static function register_brand_search_blocks() {
        register_block_type('gestpark/brand-carousel', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => function ($attributes) {
                return self::safe_dynamic_block('gpo-block-brand-carousel', $attributes, function () use ($attributes) {
                    return do_shortcode('[gestpark_brand_carousel page_id="' . absint($attributes['pageId'] ?? 0) . '" catalog_ref="' . esc_attr($attributes['catalogRef'] ?? '') . '" logo_size="' . absint($attributes['logoSize'] ?? 96) . '" card_size="' . absint($attributes['cardSize'] ?? 168) . '" autoplay="' . (!empty($attributes['autoplay']) ? 'yes' : 'no') . '" interval="' . absint($attributes['interval'] ?? 6500) . '" speed="' . absint($attributes['speed'] ?? 900) . '" primary_color="' . esc_attr($attributes['primaryColor'] ?? '') . '" accent_color="' . esc_attr($attributes['accentColor'] ?? '') . '" bg_color="' . esc_attr($attributes['bgColor'] ?? '') . '" text_color="' . esc_attr($attributes['textColor'] ?? '') . '"]');
                }, 'Anteprima marchi non disponibile', 'Importa almeno un veicolo reale per popolare il banner marchi.');
            },
            'attributes' => [
                'pageId' => ['type' => 'number', 'default' => 0],
                'catalogRef' => ['type' => 'string', 'default' => 'default'],
                'logoSize' => ['type' => 'number', 'default' => 96],
                'cardSize' => ['type' => 'number', 'default' => 168],
                'autoplay' => ['type' => 'boolean', 'default' => true],
                'interval' => ['type' => 'number', 'default' => 6500],
                'speed' => ['type' => 'number', 'default' => 900],
                'primaryColor' => ['type' => 'string', 'default' => ''],
                'accentColor' => ['type' => 'string', 'default' => ''],
                'bgColor' => ['type' => 'string', 'default' => ''],
                'textColor' => ['type' => 'string', 'default' => ''],
            ],
            'supports' => self::common_supports(),
        ]);

        register_block_type('gestpark/vehicle-search', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => function ($attributes) {
                $content = do_shortcode('[gestpark_vehicle_search page_id="' . absint($attributes['pageId'] ?? 0) . '" catalog_ref="' . esc_attr($attributes['catalogRef'] ?? '') . '" placeholder="' . esc_attr($attributes['placeholder'] ?? 'Cerca veicolo') . '" width="' . absint($attributes['width'] ?? 100) . '" radius="' . absint($attributes['radius'] ?? 999) . '" primary_color="' . esc_attr($attributes['primaryColor'] ?? '') . '" accent_color="' . esc_attr($attributes['accentColor'] ?? '') . '" bg_color="' . esc_attr($attributes['bgColor'] ?? '') . '" text_color="' . esc_attr($attributes['textColor'] ?? '') . '" button_color="' . esc_attr($attributes['buttonColor'] ?? '') . '"' . self::search_shortcode_attributes($attributes) . ']');
                return self::render_dynamic_block('gpo-block-vehicle-search', $attributes, $content);
            },
            'attributes' => [
                'pageId' => ['type' => 'number', 'default' => 0],
                'catalogRef' => ['type' => 'string', 'default' => 'default'],
                'placeholder' => ['type' => 'string', 'default' => 'Cerca veicolo'],
                'width' => ['type' => 'number', 'default' => 100],
                'radius' => ['type' => 'number', 'default' => 999],
                'showOnDesktop' => ['type' => 'boolean', 'default' => true],
                'showOnTablet' => ['type' => 'boolean', 'default' => true],
                'showOnMobile' => ['type' => 'boolean', 'default' => true],
                'mobileMode' => ['type' => 'string', 'default' => 'normal'],
                'primaryColor' => ['type' => 'string', 'default' => ''],
                'accentColor' => ['type' => 'string', 'default' => ''],
                'bgColor' => ['type' => 'string', 'default' => ''],
                'textColor' => ['type' => 'string', 'default' => ''],
                'buttonColor' => ['type' => 'string', 'default' => ''],
            ],
            'supports' => self::common_supports(),
        ]);
    }

    protected static function catalog_pages_for_editor() {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $items = [];
        foreach ($pages as $page) {
            $content = (string) $page->post_content;
            $count = 0;
            if (strpos($content, 'gestpark_vehicle_catalog') !== false || strpos($content, 'gestpark/vehicle-catalog') !== false || strpos($content, 'gestpark_vehicle_grid') !== false || strpos($content, 'gestpark/vehicle-grid') !== false) {
                preg_match_all('/gestpark_vehicle_catalog|gestpark\/vehicle-catalog|gestpark_vehicle_grid|gestpark\/vehicle-grid/', $content, $matches);
                $count = max(1, count($matches[0]));
            }
            if ($count < 1) {
                continue;
            }
            $catalogs = [];
            for ($i = 1; $i <= $count; $i++) {
                $catalogs[] = [
                    'value' => 'catalog-' . $i,
                    'label' => 'Catalogo ' . $i,
                ];
            }
            $items[] = [
                'id' => (int) $page->ID,
                'title' => get_the_title($page),
                'url' => get_permalink($page),
                'catalogs' => $catalogs,
            ];
        }
        return $items;
    }

    protected static function register_single_vehicle_blocks() {
        register_block_type('gestpark/vehicle-hero', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => [__CLASS__, 'render_vehicle_hero'],
            'attributes' => [
                'showImage' => ['type' => 'boolean', 'default' => true],
                'showMeta' => ['type' => 'boolean', 'default' => true],
                'showChips' => ['type' => 'boolean', 'default' => true],
                'showLeadForm' => ['type' => 'boolean', 'default' => false],
                'primaryColor' => ['type' => 'string', 'default' => ''],
                'accentColor' => ['type' => 'string', 'default' => ''],
                'bgColor' => ['type' => 'string', 'default' => ''],
                'textColor' => ['type' => 'string', 'default' => ''],
                'buttonColor' => ['type' => 'string', 'default' => ''],
                'buttonTextColor' => ['type' => 'string', 'default' => ''],
            ],
            'supports' => self::internal_supports(),
        ]);

        register_block_type('gestpark/vehicle-gallery', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => [__CLASS__, 'render_vehicle_gallery'],
            'attributes' => [],
            'supports' => self::internal_supports(),
        ]);

        register_block_type('gestpark/vehicle-specs', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => [__CLASS__, 'render_vehicle_specs'],
            'attributes' => [
                'fields' => ['type' => 'string', 'default' => 'condition,year,fuel,mileage,body_type,transmission,engine_size,power,color,doors,seats,location'],
                'layout' => ['type' => 'string', 'default' => 'grid'],
                'title' => ['type' => 'string', 'default' => 'Scheda tecnica'],
                'primaryColor' => ['type' => 'string', 'default' => ''],
                'accentColor' => ['type' => 'string', 'default' => ''],
                'bgColor' => ['type' => 'string', 'default' => ''],
                'textColor' => ['type' => 'string', 'default' => ''],
            ],
            'supports' => self::internal_supports(),
        ]);

        register_block_type('gestpark/vehicle-description', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => [__CLASS__, 'render_vehicle_description'],
            'attributes' => [
                'title' => ['type' => 'string', 'default' => 'Descrizione'],
                'primaryColor' => ['type' => 'string', 'default' => ''],
                'accentColor' => ['type' => 'string', 'default' => ''],
                'bgColor' => ['type' => 'string', 'default' => ''],
                'textColor' => ['type' => 'string', 'default' => ''],
            ],
            'supports' => self::internal_supports(),
        ]);

        register_block_type('gestpark/vehicle-notes', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => [__CLASS__, 'render_vehicle_notes'],
            'attributes' => [
                'title' => ['type' => 'string', 'default' => 'Note'],
                'primaryColor' => ['type' => 'string', 'default' => ''],
                'accentColor' => ['type' => 'string', 'default' => ''],
                'bgColor' => ['type' => 'string', 'default' => ''],
                'textColor' => ['type' => 'string', 'default' => ''],
            ],
            'supports' => self::internal_supports(),
        ]);

        register_block_type('gestpark/vehicle-accessories', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => [__CLASS__, 'render_vehicle_accessories'],
            'attributes' => [
                'title' => ['type' => 'string', 'default' => 'Accessori'],
                'collapsedByDefault' => ['type' => 'boolean', 'default' => true],
                'primaryColor' => ['type' => 'string', 'default' => ''],
                'accentColor' => ['type' => 'string', 'default' => ''],
                'bgColor' => ['type' => 'string', 'default' => ''],
                'textColor' => ['type' => 'string', 'default' => ''],
            ],
            'supports' => self::internal_supports(),
        ]);

        register_block_type('gestpark/vehicle-contact', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => [__CLASS__, 'render_vehicle_contact'],
            'attributes' => [
                'title' => ['type' => 'string', 'default' => 'Richiedi informazioni'],
                'text' => ['type' => 'string', 'default' => 'Contatta il concessionario per disponibilita, prova su strada e proposta commerciale personalizzata su questo veicolo.'],
                'buttonLabel' => ['type' => 'string', 'default' => 'Invia richiesta'],
                'primaryColor' => ['type' => 'string', 'default' => ''],
                'accentColor' => ['type' => 'string', 'default' => ''],
                'bgColor' => ['type' => 'string', 'default' => ''],
                'textColor' => ['type' => 'string', 'default' => ''],
                'buttonColor' => ['type' => 'string', 'default' => ''],
                'buttonTextColor' => ['type' => 'string', 'default' => ''],
            ],
            'supports' => self::internal_supports(),
        ]);

        register_block_type('gestpark/vehicle-carousel', [
            'editor_script' => 'gpo-blocks',
            'render_callback' => [__CLASS__, 'render_vehicle_carousel'],
            'attributes' => [
                'title' => ['type' => 'string', 'default' => 'Altri veicoli da vedere'],
                'source' => ['type' => 'string', 'default' => 'related_brand'],
                'limit' => ['type' => 'number', 'default' => 6],
                'show' => ['type' => 'string', 'default' => 'image,title,price,chips,neopatentati,primary_button'],
                'showDesktop' => ['type' => 'string', 'default' => ''],
                'showTablet' => ['type' => 'string', 'default' => ''],
                'showMobile' => ['type' => 'string', 'default' => ''],
                'cardLayout' => ['type' => 'string', 'default' => 'default'],
                'primaryColor' => ['type' => 'string', 'default' => ''],
                'accentColor' => ['type' => 'string', 'default' => ''],
                'bgColor' => ['type' => 'string', 'default' => ''],
                'textColor' => ['type' => 'string', 'default' => ''],
                'buttonColor' => ['type' => 'string', 'default' => ''],
                'buttonTextColor' => ['type' => 'string', 'default' => ''],
                'primaryButtonLabel' => ['type' => 'string', 'default' => 'Scheda veicolo'],
            ],
            'supports' => self::common_supports(),
        ]);
    }

    protected static function current_vehicle_id() {
        return GPO_Frontend::current_vehicle_id();
    }

    protected static function wrapper_attrs($class = '', $attributes = []) {
        $args = ['class' => trim('gpo-block ' . $class)];
        $style = self::block_style_string($attributes);
        if ($style !== '') {
            $args['style'] = $style;
        }
        return get_block_wrapper_attributes($args);
    }

    protected static function block_style_string($attributes = []) {
        $style = '';
        $map = [
            'primaryColor' => '--gpo-primary',
            'accentColor' => '--gpo-accent',
            'bgColor' => '--gpo-bg',
            'textColor' => '--gpo-local-text',
            'buttonColor' => '--gpo-button-bg',
            'buttonTextColor' => '--gpo-button-text',
        ];
        foreach ($map as $attr => $css_var) {
            if (!empty($attributes[$attr])) {
                $style .= $css_var . ':' . sanitize_text_field($attributes[$attr]) . ';';
            }
        }
        if (isset($attributes['outerPaddingX'])) {
            $style .= '--gpo-shell-padding-x:' . absint($attributes['outerPaddingX']) . 'px;';
        }
        if (isset($attributes['sectionGap'])) {
            $style .= '--gpo-section-gap:' . absint($attributes['sectionGap']) . 'px;';
        }
        return $style;
    }

    protected static function breakout_blocks() {
        return [
            'gpo-block-catalog',
            'gpo-block-featured-carousel',
            'gpo-block-featured-vehicle',
            'gpo-block-brand-carousel',
            'gpo-block-vehicle-search',
        ];
    }

    protected static function is_breakout_block($class) {
        foreach (self::breakout_blocks() as $wide_block) {
            if (strpos((string) $class, $wide_block) !== false) {
                return true;
            }
        }

        return false;
    }

    protected static function preferred_align_class($class, $attributes = []) {
        if (!empty($attributes['align'])) {
            return '';
        }

        if (self::is_breakout_block($class)) {
            return 'alignwide';
        }

        return '';
    }

    protected static function preferred_layout_class($class) {
        if (self::is_breakout_block($class)) {
            return 'gpo-breakout-block gpo-shell-wide-block';
        }

        return '';
    }

    protected static function responsive_wrapper_classes($attributes = []) {
        $classes = [];

        if (array_key_exists('showOnDesktop', $attributes) && empty($attributes['showOnDesktop'])) {
            $classes[] = 'gpo-hide-desktop';
        }
        if (array_key_exists('showOnTablet', $attributes) && empty($attributes['showOnTablet'])) {
            $classes[] = 'gpo-hide-tablet';
        }
        if (array_key_exists('showOnMobile', $attributes) && empty($attributes['showOnMobile'])) {
            $classes[] = 'gpo-hide-mobile';
        }
        if (($attributes['mobileMode'] ?? 'normal') === 'burger') {
            $classes[] = 'gpo-search-mobile-mode-burger';
        }

        return implode(' ', $classes);
    }

    protected static function render_dynamic_block($class, $attributes, $content) {
        $align_class = self::preferred_align_class($class, $attributes);
        $layout_class = self::preferred_layout_class($class);
        $visibility_class = self::responsive_wrapper_classes($attributes);
        $class_names = trim($class . ' ' . $layout_class . ' ' . $align_class . ' ' . $visibility_class);
        return '<div ' . self::wrapper_attrs($class_names, $attributes) . '>' . $content . '</div>';
    }

    protected static function safe_dynamic_block($class, $attributes, $callback, $fallback_title, $fallback_message) {
        try {
            $content = is_callable($callback) ? (string) call_user_func($callback) : '';
        } catch (Throwable $throwable) {
            if (class_exists('GPO_Logger')) {
                GPO_Logger::add('Anteprima blocco fallita', [
                    'block' => $class,
                    'error' => $throwable->getMessage(),
                ]);
            }
            $content = '';
        }

        if (trim(wp_strip_all_tags($content)) === '') {
            $content = '<div class="gpo-empty-state gpo-empty-state--editor"><h3>' . esc_html($fallback_title) . '</h3><p>' . esc_html($fallback_message) . '</p></div>';
        }

        return self::render_dynamic_block($class, $attributes, $content);
    }

    public static function render_vehicle_hero($attributes) {
        $post_id = self::current_vehicle_id();
        if (!$post_id) {
            return '<div ' . self::wrapper_attrs('gpo-editor-empty') . '>Aggiungi almeno un veicolo o apri questo blocco nella scheda di un veicolo per vedere l’anteprima.</div>';
        }
        $data = GPO_Frontend::vehicle_data($post_id);
        ob_start();
        ?>
        <section <?php echo self::wrapper_attrs('gpo-single-block gpo-single-hero-block', $attributes); ?>>
            <div class="gpo-template-hero <?php echo !empty($attributes['showImage']) ? 'has-media' : 'is-textual'; ?>">
                <?php if (!empty($attributes['showImage'])) : ?>
                    <div class="gpo-template-hero__media"><?php echo GPO_Frontend::gallery_markup($post_id, true); ?></div>
                <?php endif; ?>
                <div class="gpo-template-hero__content">
                    <div class="gpo-single-hero-top">
                        <div>
                            <span class="gpo-kicker">Scheda veicolo</span>
                            <p class="gpo-single-subtitle"><?php echo esc_html(trim($data['brand'] . ' ' . $data['model'])); ?></p>
                        </div>
                        <?php if (!empty($data['badge'])) : ?><span class="gpo-badge"><?php echo esc_html($data['badge']); ?></span><?php endif; ?>
                    </div>
                    <h1><?php echo esc_html(get_the_title($post_id)); ?></h1>
                    <div class="gpo-single-price-row">
                        <div>
                            <?php if (!empty($data['promo_price']) && !empty($data['price']) && $data['promo_price'] !== $data['price']) : ?>
                                <small><?php echo esc_html(GPO_Frontend::format_price_public($data['price'])); ?></small>
                            <?php endif; ?>
                            <strong><?php echo esc_html(GPO_Frontend::format_price_public($data['current_price'])); ?></strong>
                        </div>
                        <?php if (!empty($attributes['showChips'])) : ?>
                            <?php echo GPO_Frontend::quick_info_panel_markup($post_id, 'gpo-quick-info-panel gpo-quick-info-panel--hero', [], $data); ?>
                        <?php endif; ?>
                    </div>
                    <?php echo GPO_Frontend::neopatentati_badge_markup($post_id, 'gpo-neo-badge gpo-neo-badge--hero', $data); ?>
                    <?php if (!empty($attributes['showMeta'])) : ?>
                        <?php echo GPO_Frontend::specs_grid_markup($post_id, ['condition','fuel','neopatentati','body_type','transmission','engine_size'], 'grid'); ?>
                    <?php endif; ?>
                    <?php if (!empty($attributes['showLeadForm'])) : ?>
                        <?php
                        echo GPO_Frontend::lead_form_markup($post_id, [
                            'title' => 'Richiedi informazioni',
                            'text' => 'Compila il modulo per ricevere disponibilita, prova su strada e proposta commerciale su questo veicolo.',
                            'button_label' => 'Invia richiesta',
                            'wrapper_class' => 'gpo-inline-lead-card',
                        ]);
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_vehicle_gallery($attributes) {
        $post_id = self::current_vehicle_id();
        if (!$post_id) {
            return '<div ' . self::wrapper_attrs('gpo-editor-empty') . '>Nessun veicolo disponibile per l’anteprima.</div>';
        }
        return '<section ' . self::wrapper_attrs('gpo-single-block', $attributes) . '>' . GPO_Frontend::gallery_markup($post_id, true) . '</section>';
    }

    public static function render_vehicle_specs($attributes) {
        $post_id = self::current_vehicle_id();
        if (!$post_id) {
            return '<div ' . self::wrapper_attrs('gpo-editor-empty') . '>Nessun veicolo disponibile per l’anteprima.</div>';
        }
        $fields = !empty($attributes['fields']) ? array_map('sanitize_key', array_filter(array_map('trim', explode(',', $attributes['fields'])))) : [];
        if (empty($fields)) {
            $fields = ['condition','year','fuel','mileage','body_type','transmission','engine_size'];
        }
        $title = !empty($attributes['title']) ? $attributes['title'] : 'Scheda tecnica';
        $layout = !empty($attributes['layout']) ? sanitize_key($attributes['layout']) : 'grid';
        return '<section ' . self::wrapper_attrs('gpo-single-block gpo-content-block', $attributes) . '><h2>' . esc_html($title) . '</h2>' . GPO_Frontend::specs_grid_markup($post_id, $fields, $layout) . '</section>';
    }

    public static function render_vehicle_description($attributes) {
        $post_id = self::current_vehicle_id();
        if (!$post_id) {
            return '<div ' . self::wrapper_attrs('gpo-editor-empty') . '>Nessun veicolo disponibile per l’anteprima.</div>';
        }
        $title = !empty($attributes['title']) ? $attributes['title'] : 'Descrizione';
        $content = apply_filters('the_content', get_post_field('post_content', $post_id));
        return '<section ' . self::wrapper_attrs('gpo-single-block gpo-content-block gpo-content-block--description', $attributes) . '><h2>' . esc_html($title) . '</h2>' . $content . '</section>';
    }

    public static function render_vehicle_notes($attributes) {
        $post_id = self::current_vehicle_id();
        if (!$post_id) {
            return '<div ' . self::wrapper_attrs('gpo-editor-empty') . '>Nessun veicolo disponibile per l’anteprima.</div>';
        }
        $title = !empty($attributes['title']) ? $attributes['title'] : 'Note';
        $notes = get_post_meta($post_id, '_gpo_public_notes', true);
        if (!$notes) {
            return '';
        }
        return '<section ' . self::wrapper_attrs('gpo-single-block gpo-content-block gpo-content-block--notes', $attributes) . '><h2>' . esc_html($title) . '</h2><p>' . nl2br(esc_html($notes)) . '</p></section>';
    }

    public static function render_vehicle_accessories($attributes) {
        $post_id = self::current_vehicle_id();
        if (!$post_id) {
            return '<div ' . self::wrapper_attrs('gpo-editor-empty') . '>Nessun veicolo disponibile per l’anteprima.</div>';
        }
        $title = !empty($attributes['title']) ? $attributes['title'] : 'Accessori';
        $items = GPO_Frontend::list_meta_values($post_id, '_gpo_accessories');
        if (empty($items)) {
            return '';
        }
        $open = empty($attributes['collapsedByDefault']) ? ' open' : '';
        $count = count($items);
        return '<section ' . self::wrapper_attrs('gpo-single-block gpo-content-block gpo-content-block--accessories', $attributes) . '><details class="gpo-content-disclosure"' . $open . '><summary class="gpo-content-disclosure__summary"><span class="gpo-content-disclosure__title">' . esc_html($title) . '</span><span class="gpo-content-disclosure__meta">' . esc_html((string) $count) . ' accessori</span></summary><div class="gpo-content-disclosure__body">' . GPO_Frontend::icon_list_markup($items) . '</div></details></section>';
    }

    public static function render_vehicle_contact($attributes) {
        $post_id = self::current_vehicle_id();
        $title = !empty($attributes['title']) ? $attributes['title'] : 'Richiedi informazioni';
        $text = !empty($attributes['text']) ? $attributes['text'] : 'Contatta il concessionario per disponibilita, prova su strada e proposta commerciale personalizzata su questo veicolo.';
        $button_label = !empty($attributes['buttonLabel']) ? $attributes['buttonLabel'] : 'Invia richiesta';
        $content = GPO_Frontend::lead_form_markup($post_id, [
            'title' => $title,
            'text' => $text,
            'button_label' => $button_label,
            'wrapper_class' => 'gpo-side-card',
        ]);

        return '<div ' . self::wrapper_attrs('gpo-single-block', $attributes) . '>' . $content . '</div>';
    }

    public static function render_vehicle_carousel($attributes) {
        $post_id = self::current_vehicle_id();
        $title = !empty($attributes['title']) ? $attributes['title'] : 'Altri veicoli da vedere';
        $source = !empty($attributes['source']) ? sanitize_key($attributes['source']) : 'related_brand';
        $limit = !empty($attributes['limit']) ? absint($attributes['limit']) : 6;
        $show = !empty($attributes['show']) ? sanitize_text_field($attributes['show']) : 'image,title,price,primary_button';
        $card_layout = !empty($attributes['cardLayout']) ? sanitize_key($attributes['cardLayout']) : 'default';
        $responsive_show = self::responsive_show_shortcode_attributes($attributes);
          $shortcode = '[gestpark_featured_carousel limit="' . $limit . '" show="' . esc_attr($show) . '"' . $responsive_show . ' card_layout="' . esc_attr($card_layout) . '" primary_color="' . esc_attr($attributes['primaryColor'] ?? '') . '" accent_color="' . esc_attr($attributes['accentColor'] ?? '') . '" bg_color="' . esc_attr($attributes['bgColor'] ?? '') . '" text_color="' . esc_attr($attributes['textColor'] ?? '') . '" button_color="' . esc_attr($attributes['buttonColor'] ?? '') . '" button_text_color="' . esc_attr($attributes['buttonTextColor'] ?? '') . '" primary_button_label="' . esc_attr($attributes['primaryButtonLabel'] ?? 'Scheda veicolo') . '"]';
          if ($source === 'related_brand' && $post_id) {
              $shortcode = '[gestpark_vehicle_grid limit="' . $limit . '" columns="3" show="' . esc_attr($show) . '"' . $responsive_show . ' card_layout="' . esc_attr($card_layout) . '" primary_color="' . esc_attr($attributes['primaryColor'] ?? '') . '" accent_color="' . esc_attr($attributes['accentColor'] ?? '') . '" bg_color="' . esc_attr($attributes['bgColor'] ?? '') . '" text_color="' . esc_attr($attributes['textColor'] ?? '') . '" button_color="' . esc_attr($attributes['buttonColor'] ?? '') . '" button_text_color="' . esc_attr($attributes['buttonTextColor'] ?? '') . '" primary_button_label="' . esc_attr($attributes['primaryButtonLabel'] ?? 'Scheda veicolo') . '"]';
          }
        return '<section ' . self::wrapper_attrs('gpo-single-block', $attributes) . '><div class="gpo-section-head"><div><span class="gpo-kicker">Approfondisci</span><h2>' . esc_html($title) . '</h2></div></div>' . do_shortcode($shortcode) . '</section>';
    }
}
