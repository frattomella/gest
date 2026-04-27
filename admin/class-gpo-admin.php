<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_gpo_toggle_showcase_vehicle', [__CLASS__, 'handle_toggle_showcase_vehicle']);
        add_action('admin_post_gpo_test_connection', [__CLASS__, 'handle_test_connection']);
        add_action('admin_post_gpo_disconnect_parkplatform', [__CLASS__, 'handle_disconnect_parkplatform']);
        add_action('admin_post_gpo_run_sync', [__CLASS__, 'handle_run_sync']);
        add_action('admin_post_gpo_clear_logs', [__CLASS__, 'handle_clear_logs']);
        add_action('admin_post_gpo_github_refresh', [__CLASS__, 'handle_github_refresh']);
    }

    public static function default_settings() {
        return [
            'api' => GPO_API_Client::default_api_settings(),
            'mapping' => [
                'external_id' => 'id',
                'brand' => 'brand',
                'model' => 'model',
                'version' => 'version',
                'description' => 'description',
                'condition' => 'condition',
                'year' => 'year',
                'price' => 'price',
                'fuel' => 'fuel',
                'mileage' => 'mileage',
                'body_type' => 'body_type',
                'transmission' => 'transmission',
                'engine_size' => 'engine_size',
                'gallery_urls' => 'images',
            ],
            'sync' => [
                'enabled' => 0,
            ],
            'github' => [
                'enabled' => 1,
                'repository' => defined('GPO_GITHUB_REPOSITORY') ? GPO_GITHUB_REPOSITORY : '',
                'branch' => 'main',
                'release_asset' => 'gestpark-online.zip',
                'access_token' => '',
            ],
            'components' => self::default_component_settings(),
            'engagement' => GPO_Engagement::default_settings(),
            'style' => [
                'primary_color' => '#111827',
                'accent_color' => '#12b981',
                'card_bg' => '#ffffff',
                'radius' => '16px',
                'title_font' => 'inherit',
                'body_font' => 'inherit',
                'card_layout' => 'default',
                'single_layout' => 'classic',
                'single_header' => 'default',
                'single_template_id' => 0,
                'card_gap' => '24',
                'card_padding' => '22',
                'content_max_width' => '1280',
                'outer_margin_y' => '0',
                'outer_padding_x' => '18',
                'section_gap' => '24',
                'filter_columns' => '5',
                'fallback_vehicle_image' => '',
                'lead_email' => get_option('admin_email'),
                'lead_success_message' => 'Richiesta inviata correttamente. Ti ricontatteremo al più presto.',
                'filter_fields' => [
                    'search' => '1',
                    'brand' => '1',
                    'condition' => '1',
                    'fuel' => '1',
                    'body_type' => '1',
                    'transmission' => '1',
                    'year' => '1',
                    'min_price' => '1',
                    'max_price' => '1',
                    'max_mileage' => '1',
                    'sort' => '1',
                ],
                'card_elements' => [
                    'image' => '1',
                    'badge' => '1',
                    'brand' => '1',
                    'title' => '1',
                    'price' => '1',
                    'chips' => '1',
                    'year' => '1',
                    'mileage' => '1',
                    'body_type' => '1',
                    'transmission' => '1',
                    'engine_size' => '1',
                'specs' => '1',
                'primary_button' => '1',
            ],
                'single_sections' => [
                    'gallery' => '1',
                    'summary' => '1',
                    'description' => '1',
                    'notes' => '1',
                    'specs' => '1',
                    'accessories' => '1',
                    'contact_box' => '1',
                    'strengths' => '1',
                ],
            ],
        ];
    }

    public static function menu() {
        add_menu_page('gestpark online', 'gestpark online', 'manage_options', 'gestpark-online', [__CLASS__, 'dashboard_page'], 'dashicons-car', 26);
        add_submenu_page('gestpark-online', 'Dashboard', 'Dashboard', 'manage_options', 'gestpark-online', [__CLASS__, 'dashboard_page']);
        add_submenu_page('gestpark-online', 'Veicoli', 'Veicoli', 'edit_posts', 'edit.php?post_type=gpo_vehicle');
        add_submenu_page('gestpark-online', 'Connessioni API', 'Connessioni API', 'manage_options', 'gpo-api', [__CLASS__, 'api_page']);
        add_submenu_page('gestpark-online', 'Configurazione componenti', 'Configurazione componenti', 'manage_options', 'gpo-components', [__CLASS__, 'components_hub_page']);
        add_submenu_page('gestpark-online', 'Engagement', 'Engagement', 'manage_options', 'gpo-engagement', [__CLASS__, 'engagement_hub_page']);
        add_submenu_page('gestpark-online', 'Log e diagnostica', 'Log e diagnostica', 'manage_options', 'gpo-logs', [__CLASS__, 'logs_page']);
        add_submenu_page('gestpark-online', 'Guida rapida', 'Guida rapida', 'manage_options', 'gpo-guide', [__CLASS__, 'guide_page']);
    }

    public static function enqueue_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_vehicle_list = $screen && $screen->base === 'edit' && $screen->post_type === 'gpo_vehicle';
        $is_vehicle_edit = $screen && in_array($screen->base, ['post', 'post-new'], true) && $screen->post_type === 'gpo_vehicle';

        if (strpos((string) $hook, 'gestpark-online') === false && strpos((string) $hook, 'gpo-') === false && !$is_vehicle_list && !$is_vehicle_edit) {
            return;
        }

        wp_enqueue_style('gpo-admin', GPO_PLUGIN_URL . 'admin/assets/gpo-admin.css', [], GPO_VERSION);
        wp_enqueue_script('gpo-admin', GPO_PLUGIN_URL . 'admin/assets/gpo-admin.js', [], GPO_VERSION, true);
        wp_localize_script('gpo-admin', 'gpoAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gpo_admin_nonce'),
            'strings' => [
                'showcaseOn' => 'In vetrina',
                'showcaseOff' => 'Fuori vetrina',
                'showcaseError' => 'Non sono riuscito ad aggiornare la vetrina. Riprova.',
                'remove' => 'Rimuovi',
                'edit' => 'Modifica',
                'newPromo' => 'Nuova promo',
            ],
        ]);
        wp_enqueue_media();
        wp_add_inline_script('jquery-core', "jQuery(function($){var frame;$(document).on('click','.gpo-media-upload',function(e){e.preventDefault();var target=$($(this).data('target'));if(!target.length){return;}frame=wp.media({title:'Seleziona immagine fallback',button:{text:'Usa questa immagine'},multiple:false});frame.on('select',function(){var attachment=frame.state().get('selection').first().toJSON();target.val(attachment.url).trigger('change');});frame.open();});$(document).on('click','.gpo-media-clear',function(e){e.preventDefault();var target=$($(this).data('target'));if(target.length){target.val('').trigger('change');}});});");
    }

    public static function register_settings() {
        register_setting('gpo_api_group', 'gpo_settings', [__CLASS__, 'sanitize_settings']);
    }

    public static function sanitize_settings($input) {
        $defaults = self::default_settings();
        $input = is_array($input) ? $input : [];
        $existing = self::get_settings();
        $output = array_replace_recursive($defaults, $existing);

        if (!empty($input['api']) && is_array($input['api'])) {
            $text_fields = [
                'connection_mode',
                'manual_format',
                'endpoint',
                'detail_endpoint',
                'login_endpoint',
                'auth_method',
                'items_path',
                'gestpark_base_url',
                'gestpark_login_path',
                'gestpark_list_path',
                'gestpark_mainphoto_path',
                'gestpark_detail_path',
            ];

            foreach ($text_fields as $key) {
                if (isset($input['api'][$key])) {
                    $output['api'][$key] = sanitize_text_field((string) $input['api'][$key]);
                }
            }

            foreach (['gestpark_username', 'gestpark_password', 'token', 'api_key'] as $key) {
                if (isset($input['api'][$key])) {
                    $output['api'][$key] = self::clean_secret_value($input['api'][$key]);
                }
            }

            if (isset($input['api']['timeout'])) {
                $output['api']['timeout'] = max(5, absint($input['api']['timeout']));
            }

            $output['api']['prefer_mainphoto'] = !empty($input['api']['prefer_mainphoto']) ? 1 : 0;
            $output['api']['include_details'] = !empty($input['api']['include_details']) ? 1 : 0;
            $output['api'] = GPO_API_Client::normalize_api_settings($output['api']);
        }

        if (!empty($input['mapping']) && is_array($input['mapping'])) {
            foreach ($input['mapping'] as $key => $value) {
                $output['mapping'][$key] = sanitize_text_field($value);
            }
        }

        if (!empty($input['sync']) && is_array($input['sync'])) {
            foreach ($input['sync'] as $key => $value) {
                $output['sync'][$key] = !empty($value) ? 1 : 0;
            }
        }

        if (!empty($input['github']) && is_array($input['github'])) {
            $output['github']['enabled'] = !empty($input['github']['enabled']) ? 1 : 0;

            foreach (['repository', 'branch', 'release_asset'] as $key) {
                if (isset($input['github'][$key])) {
                    $output['github'][$key] = sanitize_text_field((string) $input['github'][$key]);
                }
            }

            if (isset($input['github']['access_token'])) {
                $output['github']['access_token'] = self::clean_secret_value($input['github']['access_token']);
            }

            if (class_exists('GPO_GitHub_Updater')) {
                GPO_GitHub_Updater::clear_cache();
            }
        }

        if (array_key_exists('components', $input)) {
            $output['components'] = self::sanitize_components(
                $input['components'] ?? [],
                $defaults['components']
            );
        }

        if (array_key_exists('engagement', $input)) {
            $engagement = is_array($input['engagement']) ? $input['engagement'] : [];
            $output['engagement']['promo_color'] = sanitize_hex_color((string) ($engagement['promo_color'] ?? '')) ?: $defaults['engagement']['promo_color'];
            $output['engagement']['rules'] = GPO_Engagement::sanitize_rules($engagement['rules'] ?? []);
        }

        if (!empty($input['style']) && is_array($input['style'])) {
            foreach (['primary_color', 'accent_color', 'card_bg', 'radius', 'title_font', 'body_font', 'card_layout', 'single_layout', 'single_header', 'single_template_id', 'card_gap', 'card_padding', 'content_max_width', 'outer_margin_y', 'outer_padding_x', 'section_gap', 'filter_columns'] as $key) {
                if (isset($input['style'][$key])) {
                    $output['style'][$key] = sanitize_text_field($input['style'][$key]);
                }
            }
            if (isset($input['style']['fallback_vehicle_image'])) {
                $output['style']['fallback_vehicle_image'] = esc_url_raw($input['style']['fallback_vehicle_image']);
            }
            if (isset($input['style']['lead_email'])) {
                $email = sanitize_email((string) $input['style']['lead_email']);
                $output['style']['lead_email'] = $email ?: get_option('admin_email');
            }
            if (isset($input['style']['lead_success_message'])) {
                $output['style']['lead_success_message'] = sanitize_text_field((string) $input['style']['lead_success_message']);
            }

            $output['style']['card_elements'] = [];
            foreach (array_keys($defaults['style']['card_elements']) as $key) {
                $output['style']['card_elements'][$key] = !empty($input['style']['card_elements'][$key]) ? '1' : '0';
            }

            $output['style']['single_sections'] = [];
            foreach (array_keys($defaults['style']['single_sections']) as $key) {
                $output['style']['single_sections'][$key] = !empty($input['style']['single_sections'][$key]) ? '1' : '0';
            }

            $output['style']['filter_fields'] = [];
            foreach (array_keys($defaults['style']['filter_fields']) as $key) {
                $output['style']['filter_fields'][$key] = !empty($input['style']['filter_fields'][$key]) ? '1' : '0';
            }
        }

        return $output;
    }

    protected static function clean_secret_value($value) {
        $value = is_scalar($value) ? (string) $value : '';
        $value = str_replace(["\r", "\n", "\t"], '', $value);

        return trim($value);
    }

    protected static function default_component_settings() {
        return [
            'featured_vehicle' => [
                'vehicle_id' => 0,
                'start_date' => '',
                'start_time' => '',
                'end_date' => '',
                'end_time' => '',
                'queue' => [],
            ],
            'search_bar' => [
                'notes' => '',
            ],
            'brand_banner' => [
                'mode' => 'inventory',
                'selected_brands' => [],
            ],
            'catalog_filters' => [
                'notes' => '',
            ],
            'showcase_carousel' => [
                'vehicle_ids' => [],
                'start_date' => '',
                'start_time' => '',
                'end_date' => '',
                'end_time' => '',
                'queue' => [],
            ],
            'lead_requests' => [
                'recipient_email' => '',
                'whatsapp_number' => '',
            ],
            'vehicle_carousel' => [
                'notes' => '',
            ],
            'vehicle_grid' => [
                'notes' => '',
            ],
        ];
    }

    protected static function sanitize_date($value) {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    protected static function sanitize_time($value) {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
    }

    protected static function sanitize_whatsapp_number($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        return is_string($digits) ? $digits : '';
    }

    protected static function sanitize_vehicle_ids($items) {
        return array_values(array_filter(array_map('absint', (array) $items)));
    }

    protected static function sanitize_brand_keys($items) {
        return array_values(array_filter(array_map(function ($value) {
            $value = strtolower(remove_accents((string) $value));
            $value = str_replace(['&', '.', "'"], ' ', $value);
            $value = preg_replace('/\s+/', '-', trim($value));
            return sanitize_title($value);
        }, (array) $items)));
    }

    protected static function sanitize_featured_queue($rows) {
        $rows = is_array($rows) ? $rows : [];
        $sanitized = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $vehicle_id = absint($row['vehicle_id'] ?? 0);
            if ($vehicle_id < 1) {
                continue;
            }

            $sanitized[] = [
                'order' => max(1, absint($row['order'] ?? count($sanitized) + 1)),
                'vehicle_id' => $vehicle_id,
                'label' => sanitize_text_field((string) ($row['label'] ?? '')),
                'start_date' => self::sanitize_date($row['start_date'] ?? ''),
                'start_time' => self::sanitize_time($row['start_time'] ?? ''),
                'end_date' => self::sanitize_date($row['end_date'] ?? ''),
                'end_time' => self::sanitize_time($row['end_time'] ?? ''),
            ];
        }

        usort($sanitized, function ($left, $right) {
            return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
        });

        return $sanitized;
    }

    protected static function sanitize_showcase_queue($rows) {
        $rows = is_array($rows) ? $rows : [];
        $sanitized = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $vehicle_ids = self::sanitize_vehicle_ids($row['vehicle_ids'] ?? []);
            if (empty($vehicle_ids)) {
                continue;
            }

            $sanitized[] = [
                'order' => max(1, absint($row['order'] ?? count($sanitized) + 1)),
                'label' => sanitize_text_field((string) ($row['label'] ?? '')),
                'vehicle_ids' => $vehicle_ids,
                'start_date' => self::sanitize_date($row['start_date'] ?? ''),
                'start_time' => self::sanitize_time($row['start_time'] ?? ''),
                'end_date' => self::sanitize_date($row['end_date'] ?? ''),
                'end_time' => self::sanitize_time($row['end_time'] ?? ''),
            ];
        }

        usort($sanitized, function ($left, $right) {
            return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
        });

        return $sanitized;
    }

    protected static function sanitize_components($input, $defaults) {
        $input = is_array($input) ? $input : [];
        $output = $defaults;

        $featured = isset($input['featured_vehicle']) && is_array($input['featured_vehicle']) ? $input['featured_vehicle'] : [];
        $output['featured_vehicle']['vehicle_id'] = absint($featured['vehicle_id'] ?? 0);
        $output['featured_vehicle']['start_date'] = self::sanitize_date($featured['start_date'] ?? '');
        $output['featured_vehicle']['start_time'] = self::sanitize_time($featured['start_time'] ?? '');
        $output['featured_vehicle']['end_date'] = self::sanitize_date($featured['end_date'] ?? '');
        $output['featured_vehicle']['end_time'] = self::sanitize_time($featured['end_time'] ?? '');
        $output['featured_vehicle']['queue'] = self::sanitize_featured_queue($featured['queue'] ?? []);

        $brand_banner = isset($input['brand_banner']) && is_array($input['brand_banner']) ? $input['brand_banner'] : [];
        $mode = sanitize_key((string) ($brand_banner['mode'] ?? 'inventory'));
        if (!in_array($mode, ['inventory', 'all', 'manual'], true)) {
            $mode = 'inventory';
        }
        $output['brand_banner']['mode'] = $mode;
        $output['brand_banner']['selected_brands'] = self::sanitize_brand_keys($brand_banner['selected_brands'] ?? []);

        $showcase = isset($input['showcase_carousel']) && is_array($input['showcase_carousel']) ? $input['showcase_carousel'] : [];
        $output['showcase_carousel']['vehicle_ids'] = self::sanitize_vehicle_ids($showcase['vehicle_ids'] ?? []);
        $output['showcase_carousel']['start_date'] = self::sanitize_date($showcase['start_date'] ?? '');
        $output['showcase_carousel']['start_time'] = self::sanitize_time($showcase['start_time'] ?? '');
        $output['showcase_carousel']['end_date'] = self::sanitize_date($showcase['end_date'] ?? '');
        $output['showcase_carousel']['end_time'] = self::sanitize_time($showcase['end_time'] ?? '');
        $output['showcase_carousel']['queue'] = self::sanitize_showcase_queue($showcase['queue'] ?? []);

        $lead_requests = isset($input['lead_requests']) && is_array($input['lead_requests']) ? $input['lead_requests'] : [];
        $output['lead_requests']['recipient_email'] = sanitize_email((string) ($lead_requests['recipient_email'] ?? ''));
        $output['lead_requests']['whatsapp_number'] = self::sanitize_whatsapp_number($lead_requests['whatsapp_number'] ?? '');

        foreach (['search_bar', 'catalog_filters', 'vehicle_carousel', 'vehicle_grid'] as $key) {
            $current = isset($input[$key]) && is_array($input[$key]) ? $input[$key] : [];
            $output[$key]['notes'] = sanitize_text_field((string) ($current['notes'] ?? ''));
        }

        return $output;
    }

    protected static function get_settings() {
        $settings = get_option('gpo_settings', []);
        $settings = array_replace_recursive(self::default_settings(), is_array($settings) ? $settings : []);
        $settings['api'] = GPO_API_Client::normalize_api_settings(isset($settings['api']) ? $settings['api'] : []);

        return $settings;
    }

    protected static function get_logo_url($name) {
        return GPO_PLUGIN_URL . 'admin/assets/' . $name;
    }

    protected static function nav_items() {
        return [
            'dashboard' => ['label' => 'Dashboard', 'url' => admin_url('admin.php?page=gestpark-online')],
            'api' => ['label' => 'Connessioni', 'url' => admin_url('admin.php?page=gpo-api')],
            'components' => ['label' => 'Componenti', 'url' => admin_url('admin.php?page=gpo-components')],
            'engagement' => ['label' => 'Engagement', 'url' => admin_url('admin.php?page=gpo-engagement')],
            'vehicles' => ['label' => 'Veicoli', 'url' => admin_url('edit.php?post_type=gpo_vehicle')],
            'logs' => ['label' => 'Log', 'url' => admin_url('admin.php?page=gpo-logs')],
            'guide' => ['label' => 'Guida', 'url' => admin_url('admin.php?page=gpo-guide')],
        ];
    }

    protected static function render_page_start($current, $title, $description, $actions = [], $badges = []) {
        echo '<div class="wrap gpo-admin-wrap">';
        echo '<div class="gpo-admin-shell">';
        self::render_page_nav($current);
        echo '<section class="gpo-admin-stage">';
        echo '<header class="gpo-page-header">';
        echo '<div class="gpo-page-header__copy">';
        if (!empty($badges)) {
            echo '<div class="gpo-page-badges">';
            foreach ($badges as $badge) {
                echo '<span class="gpo-page-badge">' . esc_html($badge) . '</span>';
            }
            echo '</div>';
        }
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p>' . esc_html($description) . '</p>';
        echo '</div>';
        if (!empty($actions)) {
            echo '<div class="gpo-page-actions">';
            foreach ($actions as $action) {
                $class = !empty($action['variant']) && $action['variant'] === 'secondary' ? 'button button-secondary' : 'button button-primary';
                echo '<a class="' . esc_attr($class) . '" href="' . esc_url($action['url']) . '"';
                if (!empty($action['target'])) {
                    echo ' target="' . esc_attr($action['target']) . '"';
                }
                echo '>' . esc_html($action['label']) . '</a>';
            }
            echo '</div>';
        }
        echo '</header>';
    }

    protected static function render_page_end() {
        echo '</section>';
        echo '</div>';
        echo '</div>';
    }

    protected static function render_page_nav($current) {
        echo '<nav class="gpo-admin-nav">';
        echo '<div class="gpo-admin-nav__brand">';
        echo '<img class="gpo-admin-nav__logo" src="' . esc_url(self::get_logo_url('gestpark.png')) . '" alt="GestPark online" />';
        echo '<span class="gpo-admin-nav__caption">Dealer workspace</span>';
        echo '</div>';
        echo '<div class="gpo-admin-nav__links">';
        foreach (self::nav_items() as $slug => $item) {
            $active = $slug === $current ? ' is-active' : '';
            echo '<a class="gpo-admin-nav__link' . esc_attr($active) . '" href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a>';
        }
        echo '</div>';
        echo '</nav>';
    }

    protected static function render_setting_field($args) {
        $defaults = [
            'label' => '',
            'name' => '',
            'value' => '',
            'type' => 'text',
            'description' => '',
            'options' => [],
            'placeholder' => '',
            'classes' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        echo '<label class="gpo-field ' . esc_attr($args['classes']) . '">';
        echo '<span class="gpo-field__label">' . esc_html($args['label']) . '</span>';

        if ($args['type'] === 'select') {
            echo '<select name="' . esc_attr($args['name']) . '">';
            foreach ($args['options'] as $key => $label) {
                echo '<option value="' . esc_attr($key) . '" ' . selected($args['value'], $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        } elseif ($args['type'] === 'checkbox') {
            echo '<span class="gpo-field__toggle">';
            echo '<input type="checkbox" name="' . esc_attr($args['name']) . '" value="1" ' . checked(!empty($args['value']), true, false) . ' />';
            echo '<span>' . esc_html($args['description']) . '</span>';
            echo '</span>';
        } else {
            $input_type = in_array($args['type'], ['text', 'password', 'email', 'url', 'number'], true) ? $args['type'] : 'text';
            echo '<input type="' . esc_attr($input_type) . '" name="' . esc_attr($args['name']) . '" value="' . esc_attr((string) $args['value']) . '" placeholder="' . esc_attr($args['placeholder']) . '" />';
        }

        if ($args['description'] && $args['type'] !== 'checkbox') {
            echo '<span class="gpo-field__description">' . esc_html($args['description']) . '</span>';
        }

        echo '</label>';
    }

    protected static function configured_lead_email() {
        $raw_settings = get_option('gpo_settings', []);
        $raw_settings = is_array($raw_settings) ? $raw_settings : [];

        $lead_request_settings = $raw_settings['components']['lead_requests'] ?? null;
        if (is_array($lead_request_settings) && array_key_exists('recipient_email', $lead_request_settings)) {
            return sanitize_email((string) ($lead_request_settings['recipient_email'] ?? ''));
        }

        return sanitize_email((string) ($raw_settings['style']['lead_email'] ?? ''));
    }

    protected static function configured_whatsapp_number() {
        $raw_settings = get_option('gpo_settings', []);
        $raw_settings = is_array($raw_settings) ? $raw_settings : [];

        $lead_request_settings = $raw_settings['components']['lead_requests'] ?? null;
        if (is_array($lead_request_settings) && array_key_exists('whatsapp_number', $lead_request_settings)) {
            return self::sanitize_whatsapp_number($lead_request_settings['whatsapp_number'] ?? '');
        }

        return '';
    }

    public static function vehicle_page_header_options() {
        $options = [
            'default' => 'Header predefinito del tema',
        ];

        if (function_exists('wp_is_block_theme') && wp_is_block_theme() && function_exists('get_block_templates')) {
            $templates = get_block_templates([], 'wp_template_part');
            foreach ((array) $templates as $template) {
                $area = is_object($template) ? (string) ($template->area ?? '') : (string) ($template['area'] ?? '');
                $slug = is_object($template) ? sanitize_key((string) ($template->slug ?? '')) : sanitize_key((string) ($template['slug'] ?? ''));
                $title = is_object($template) ? (string) ($template->title ?? '') : (string) ($template['title'] ?? '');
                if ($area !== 'header' || $slug === '' || isset($options[$slug])) {
                    continue;
                }

                $options[$slug] = $title !== ''
                    ? $title
                    : ucwords(str_replace(['-', '_'], ' ', $slug));
            }

            return $options;
        }

        $theme_dirs = array_unique(array_filter([
            trailingslashit(get_stylesheet_directory()),
            trailingslashit(get_template_directory()),
        ]));

        foreach ($theme_dirs as $theme_dir) {
            foreach ((array) glob($theme_dir . 'header*.php') as $file) {
                $basename = wp_basename($file, '.php');
                if ($basename === 'header') {
                    continue;
                }

                $slug = sanitize_key((string) preg_replace('/^header-?/', '', $basename));
                if ($slug === '' || isset($options[$slug])) {
                    continue;
                }

                $options[$slug] = ucwords(str_replace(['-', '_'], ' ', $slug));
            }
        }

        return $options;
    }

    protected static function vehicle_options() {
        $posts = get_posts([
            'post_type' => 'gpo_vehicle',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $options = [0 => 'Nessun veicolo selezionato'];
        foreach ($posts as $post) {
            $brand = trim((string) get_post_meta($post->ID, '_gpo_brand', true));
            $label = $brand ? $brand . ' · ' . $post->post_title : $post->post_title;
            $options[(int) $post->ID] = $label;
        }

        return $options;
    }

    protected static function brand_options() {
        $brands = class_exists('GPO_Frontend') && method_exists('GPO_Frontend', 'brand_library')
            ? GPO_Frontend::brand_library()
            : [];

        $options = [];
        foreach ($brands as $brand) {
            $options[$brand['key']] = $brand['name'];
        }

        return $options;
    }

    protected static function vehicle_records() {
        $posts = get_posts([
            'post_type' => 'gpo_vehicle',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $records = [];
        foreach ($posts as $post) {
            $brand = trim((string) get_post_meta($post->ID, '_gpo_brand', true));
            $year = trim((string) get_post_meta($post->ID, '_gpo_year', true));
            $price = trim((string) get_post_meta($post->ID, '_gpo_price', true));
            $condition = trim((string) get_post_meta($post->ID, '_gpo_condition', true));
            $label = $brand ? $brand . ' · ' . $post->post_title : $post->post_title;
            $formatted_price = '';
            if ($price !== '') {
                $formatted_price = class_exists('GPO_Frontend') && method_exists('GPO_Frontend', 'format_price_public')
                    ? GPO_Frontend::format_price_public((float) $price)
                    : '€ ' . number_format_i18n((float) $price, 0);
            }

            $meta = array_values(array_filter([
                $year,
                $condition,
                $formatted_price,
            ]));

            $records[] = [
                'id' => (int) $post->ID,
                'label' => $label,
                'title' => $post->post_title,
                'meta' => implode(' · ', $meta),
                'search' => strtolower(remove_accents(trim($label . ' ' . implode(' ', $meta)))),
            ];
        }

        return $records;
    }

    protected static function datetime_row_markup($prefix, $values = []) {
        $values = wp_parse_args(is_array($values) ? $values : [], [
            'start_date' => '',
            'start_time' => '',
            'end_date' => '',
            'end_time' => '',
        ]);

        echo '<div class="gpo-field-grid gpo-field-grid--quad">';
        echo '<label class="gpo-field"><span class="gpo-field__label">Data inizio</span><input type="date" name="' . esc_attr($prefix . '[start_date]') . '" value="' . esc_attr($values['start_date']) . '" /></label>';
        echo '<label class="gpo-field"><span class="gpo-field__label">Ora inizio</span><input type="time" name="' . esc_attr($prefix . '[start_time]') . '" value="' . esc_attr($values['start_time']) . '" /></label>';
        echo '<label class="gpo-field"><span class="gpo-field__label">Data fine</span><input type="date" name="' . esc_attr($prefix . '[end_date]') . '" value="' . esc_attr($values['end_date']) . '" /></label>';
        echo '<label class="gpo-field"><span class="gpo-field__label">Ora fine</span><input type="time" name="' . esc_attr($prefix . '[end_time]') . '" value="' . esc_attr($values['end_time']) . '" /></label>';
        echo '</div>';
    }

    protected static function render_vehicle_picker($args) {
        $defaults = [
            'label' => 'Veicoli',
            'name' => '',
            'selected' => [],
            'multiple' => true,
            'records' => [],
            'description' => '',
            'placeholder' => 'Cerca un veicolo per marca, modello o prezzo',
            'empty_label' => 'Nessun veicolo selezionato',
            'classes' => '',
        ];
        $args = wp_parse_args($args, $defaults);
        $selected = array_values(array_filter(array_map('absint', (array) $args['selected'])));
        if (empty($args['multiple'])) {
            $selected = empty($selected) ? [] : [absint($selected[0])];
        }

        echo '<div class="gpo-field ' . esc_attr($args['classes']) . '">';
        echo '<span class="gpo-field__label">' . esc_html($args['label']) . '</span>';
        echo '<div class="gpo-vehicle-picker" data-input-name="' . esc_attr($args['name']) . '" data-multiple="' . (!empty($args['multiple']) ? '1' : '0') . '" data-selected=\'' . esc_attr(wp_json_encode($selected)) . '\' data-empty-label="' . esc_attr($args['empty_label']) . '">';
        echo '<div class="gpo-vehicle-picker__toolbar">';
        echo '<input type="search" class="gpo-vehicle-picker__search" placeholder="' . esc_attr($args['placeholder']) . '" />';
        echo '<span class="gpo-vehicle-picker__count">' . esc_html(count($selected) > 0 ? count($selected) . ' selezionati' : $args['empty_label']) . '</span>';
        echo '</div>';
        echo '<div class="gpo-vehicle-picker__selected"></div>';
        echo '<div class="gpo-vehicle-picker__inputs"></div>';
        echo '<div class="gpo-vehicle-picker__list" role="listbox">';
        foreach ((array) $args['records'] as $record) {
            $record_id = (int) ($record['id'] ?? 0);
            if ($record_id < 1) {
                continue;
            }
            echo '<button type="button" class="gpo-vehicle-picker__option" data-id="' . esc_attr((string) $record_id) . '" data-label="' . esc_attr((string) ($record['label'] ?? '')) . '" data-search="' . esc_attr((string) ($record['search'] ?? '')) . '">';
            echo '<span class="gpo-vehicle-picker__option-title">' . esc_html((string) ($record['label'] ?? '')) . '</span>';
            if (!empty($record['meta'])) {
                echo '<span class="gpo-vehicle-picker__option-meta">' . esc_html((string) $record['meta']) . '</span>';
            }
            echo '</button>';
        }
        echo '</div></div>';
        if ($args['description']) {
            echo '<span class="gpo-field__description">' . esc_html($args['description']) . '</span>';
        }
        echo '</div>';
    }

    protected static function schedule_window_summary($row) {
        $start_date = (string) ($row['start_date'] ?? '');
        $start_time = (string) ($row['start_time'] ?? '');
        $end_date = (string) ($row['end_date'] ?? '');
        $end_time = (string) ($row['end_time'] ?? '');

        if ($start_date === '' && $end_date === '' && $start_time === '' && $end_time === '') {
            return 'Sempre disponibile';
        }

        $parts = [];
        if ($start_date !== '') {
            $parts[] = 'Dal ' . $start_date . ($start_time !== '' ? ' ' . $start_time : '');
        }
        if ($end_date !== '') {
            $parts[] = 'Al ' . $end_date . ($end_time !== '' ? ' ' . $end_time : '');
        }

        return implode(' · ', $parts);
    }

    protected static function schedule_status($row) {
        $has_window = !empty($row['start_date']) || !empty($row['start_time']) || !empty($row['end_date']) || !empty($row['end_time']);
        if (!$has_window) {
            return ['label' => 'Sempre pronta', 'class' => 'is-neutral'];
        }

        $timestamp = current_time('timestamp');
        $start = GPO_Engagement::combine_datetime((string) ($row['start_date'] ?? ''), (string) ($row['start_time'] ?? ''));
        $end = GPO_Engagement::combine_datetime((string) ($row['end_date'] ?? ''), (string) ($row['end_time'] ?? ''), true);

        if ($start && $timestamp < $start) {
            return ['label' => 'Programmato', 'class' => 'is-future'];
        }

        if ($end && $timestamp > $end) {
            return ['label' => 'Concluso', 'class' => 'is-past'];
        }

        return ['label' => 'Attivo ora', 'class' => 'is-active'];
    }

    protected static function render_schedule_row_header($title, $summary, $status) {
        echo '<div class="gpo-data-grid-row__header">';
        echo '<div class="gpo-data-grid-row__title-wrap">';
        echo '<strong class="gpo-data-grid-row__title">' . esc_html($title) . '</strong>';
        echo '<span class="gpo-data-grid-row__summary">' . esc_html($summary) . '</span>';
        echo '</div>';
        echo '<span class="gpo-inline-status ' . esc_attr($status['class']) . '">' . esc_html($status['label']) . '</span>';
        echo '</div>';
    }

    protected static function render_featured_schedule_row($index, $row, $records) {
        $display_index = is_numeric($index) ? ((int) $index + 1) : $index;
        $row = wp_parse_args(is_array($row) ? $row : [], [
            'order' => is_numeric($index) ? ((int) $index + 1) : '',
            'label' => '',
            'vehicle_id' => 0,
            'start_date' => '',
            'start_time' => '',
            'end_date' => '',
            'end_time' => '',
        ]);
        $status = self::schedule_status($row);

        echo '<div class="gpo-data-grid-row gpo-schedule-row" data-kind="featured">';
        self::render_schedule_row_header($row['label'] !== '' ? $row['label'] : 'Slot evidenza #' . $display_index, self::schedule_window_summary($row), $status);
        echo '<div class="gpo-data-grid-row__body">';
        echo '<div class="gpo-field-grid gpo-field-grid--triple">';
        self::render_setting_field(['label' => 'Ordine', 'name' => 'gpo_settings[components][featured_vehicle][queue][' . $index . '][order]', 'value' => $row['order'], 'description' => 'Priorita del subentro.']);
        self::render_setting_field(['label' => 'Etichetta interna', 'name' => 'gpo_settings[components][featured_vehicle][queue][' . $index . '][label]', 'value' => $row['label'], 'placeholder' => 'Es. Weekend premium', 'description' => 'Nome operativo dello slot.']);
        self::render_vehicle_picker(['label' => 'Veicolo da mettere in evidenza', 'name' => 'gpo_settings[components][featured_vehicle][queue][' . $index . '][vehicle_id]', 'selected' => [$row['vehicle_id']], 'multiple' => false, 'records' => $records, 'description' => 'Ricerca rapida e selezione singola del veicolo.']);
        echo '</div>';
        self::datetime_row_markup('gpo_settings[components][featured_vehicle][queue][' . $index . ']', $row);
        echo '<div class="gpo-data-grid-row__actions"><button type="button" class="button button-secondary gpo-row-remove">Rimuovi slot</button></div>';
        echo '</div></div>';
    }

    protected static function render_showcase_schedule_row($index, $row, $records) {
        $display_index = is_numeric($index) ? ((int) $index + 1) : $index;
        $row = wp_parse_args(is_array($row) ? $row : [], [
            'order' => is_numeric($index) ? ((int) $index + 1) : '',
            'label' => '',
            'vehicle_ids' => [],
            'start_date' => '',
            'start_time' => '',
            'end_date' => '',
            'end_time' => '',
        ]);
        $status = self::schedule_status($row);

        echo '<div class="gpo-data-grid-row gpo-schedule-row" data-kind="showcase">';
        self::render_schedule_row_header($row['label'] !== '' ? $row['label'] : 'Slot vetrina #' . $display_index, self::schedule_window_summary($row), $status);
        echo '<div class="gpo-data-grid-row__body">';
        echo '<div class="gpo-field-grid gpo-field-grid--triple">';
        self::render_setting_field(['label' => 'Ordine', 'name' => 'gpo_settings[components][showcase_carousel][queue][' . $index . '][order]', 'value' => $row['order'], 'description' => 'Priorita del gruppo in coda.']);
        self::render_setting_field(['label' => 'Etichetta interna', 'name' => 'gpo_settings[components][showcase_carousel][queue][' . $index . '][label]', 'value' => $row['label'], 'placeholder' => 'Es. Promo fine mese', 'description' => 'Nome operativo dello slot.']);
        self::render_vehicle_picker(['label' => 'Veicoli del gruppo', 'name' => 'gpo_settings[components][showcase_carousel][queue][' . $index . '][vehicle_ids][]', 'selected' => $row['vehicle_ids'], 'multiple' => true, 'records' => $records, 'description' => 'Puoi selezionare piu veicoli e riordinarli in futuro.']);
        echo '</div>';
        self::datetime_row_markup('gpo_settings[components][showcase_carousel][queue][' . $index . ']', $row);
        echo '<div class="gpo-data-grid-row__actions"><button type="button" class="button button-secondary gpo-row-remove">Rimuovi slot</button></div>';
        echo '</div></div>';
    }

    protected static function promotion_target_label($rule) {
        $map = ['vehicle' => 'Veicolo singolo', 'vehicles' => 'Veicoli selezionati', 'brands' => 'Marche selezionate'];
        return $map[$rule['target_type'] ?? 'vehicle'] ?? 'Veicolo singolo';
    }

    protected static function render_promo_rule_card($index, $rule, $records, $brand_options) {
        $rule = wp_parse_args(is_array($rule) ? $rule : [], [
            'id' => '',
            'title' => '',
            'target_type' => 'vehicle',
            'vehicle_id' => 0,
            'vehicle_ids' => [],
            'brands' => [],
            'discount_type' => 'fixed',
            'value' => '',
            'start_date' => '',
            'start_time' => '',
            'end_date' => '',
            'end_time' => '',
            'promo_text' => '',
            'active' => 1,
        ]);
        $status = !empty($rule['active']) ? ['label' => 'Attiva', 'class' => 'is-active'] : ['label' => 'Disattiva', 'class' => 'is-past'];
        $title = $rule['title'] !== '' ? $rule['title'] : 'Nuova promo';

        echo '<div class="gpo-admin-rule-card gpo-promo-rule gpo-promo-card" data-rule-target="' . esc_attr($rule['target_type']) . '">';
        echo '<input type="hidden" name="gpo_settings[engagement][rules][' . $index . '][id]" value="' . esc_attr($rule['id']) . '" />';
        echo '<div class="gpo-promo-card__summary">';
        echo '<div class="gpo-promo-card__headline">';
        echo '<strong class="gpo-promo-card__title">' . esc_html($title) . '</strong>';
        echo '<span class="gpo-promo-card__meta">' . esc_html(self::promotion_target_label($rule)) . ' · ' . esc_html(self::schedule_window_summary($rule)) . '</span>';
        echo '</div>';
        echo '<div class="gpo-promo-card__summary-actions">';
        echo '<span class="gpo-inline-status ' . esc_attr($status['class']) . '">' . esc_html($status['label']) . '</span>';
        echo '<label class="gpo-mini-toggle"><input type="checkbox" name="gpo_settings[engagement][rules][' . $index . '][active]" value="1" ' . checked(!empty($rule['active']), true, false) . ' /><span>Attiva</span></label>';
        echo '<button type="button" class="button button-secondary gpo-card-toggle">Modifica</button>';
        echo '<button type="button" class="button-link-delete gpo-row-remove">Elimina</button>';
        echo '</div></div>';
        echo '<div class="gpo-promo-card__body">';
        echo '<div class="gpo-field-grid gpo-field-grid--triple">';
        self::render_setting_field(['label' => 'Titolo promo', 'name' => 'gpo_settings[engagement][rules][' . $index . '][title]', 'value' => $rule['title'], 'placeholder' => 'Es. Super Weekend', 'description' => 'Nome commerciale della promozione.']);
        self::render_setting_field(['label' => 'Target promo', 'name' => 'gpo_settings[engagement][rules][' . $index . '][target_type]', 'value' => $rule['target_type'], 'type' => 'select', 'options' => ['vehicle' => 'Veicolo singolo', 'vehicles' => 'Veicoli selezionati', 'brands' => 'Marche selezionate'], 'description' => 'Definisce come la promo viene agganciata ai veicoli.']);
        self::render_setting_field(['label' => 'Tipo sconto', 'name' => 'gpo_settings[engagement][rules][' . $index . '][discount_type]', 'value' => $rule['discount_type'], 'type' => 'select', 'options' => ['fixed' => 'Importo fisso', 'percent' => 'Percentuale'], 'description' => 'Solo uso informativo/commerciale.']);
        echo '</div>';
        echo '<div class="gpo-field-grid gpo-field-grid--triple">';
        self::render_setting_field(['label' => 'Valore sconto', 'name' => 'gpo_settings[engagement][rules][' . $index . '][value]', 'value' => $rule['value'], 'placeholder' => '1000 o 12', 'description' => 'Valore numerico della promo.']);
        self::render_setting_field(['label' => 'Testo promo breve', 'name' => 'gpo_settings[engagement][rules][' . $index . '][promo_text]', 'value' => $rule['promo_text'], 'placeholder' => 'Prezzo speciale fino a domenica', 'description' => 'Testo mostrato nelle card e nella scheda veicolo.']);
        echo '<div class="gpo-field"><span class="gpo-field__label">Stato rapido</span><div class="gpo-inline-status ' . esc_attr($status['class']) . '">' . esc_html($status['label']) . '</div><span class="gpo-field__description">Puoi attivare o disattivare la promo anche dal riepilogo in alto.</span></div>';
        echo '</div>';
        echo '<div class="gpo-field-grid gpo-field-grid--target">';
        self::render_vehicle_picker(['label' => 'Veicolo singolo', 'name' => 'gpo_settings[engagement][rules][' . $index . '][vehicle_id]', 'selected' => [$rule['vehicle_id']], 'multiple' => false, 'records' => $records, 'description' => 'Usato quando il target e Veicolo singolo.', 'classes' => 'gpo-rule-target gpo-rule-target--vehicle']);
        echo '<div class="gpo-rule-target gpo-rule-target--vehicles">';
        self::render_vehicle_picker(['label' => 'Veicoli selezionati', 'name' => 'gpo_settings[engagement][rules][' . $index . '][vehicle_ids][]', 'selected' => $rule['vehicle_ids'], 'multiple' => true, 'records' => $records, 'description' => 'Usato quando il target e Veicoli selezionati.']);
        echo '</div>';
        echo '<label class="gpo-field gpo-rule-target gpo-rule-target--brands"><span class="gpo-field__label">Marche selezionate</span><select multiple size="8" name="gpo_settings[engagement][rules][' . $index . '][brands][]" class="gpo-multi-select">';
        foreach ($brand_options as $brand_key => $brand_label) {
            echo '<option value="' . esc_attr($brand_key) . '" ' . selected(in_array($brand_key, (array) ($rule['brands'] ?? []), true), true, false) . '>' . esc_html($brand_label) . '</option>';
        }
        echo '</select><span class="gpo-field__description">Usato quando il target e Marche selezionate.</span></label>';
        echo '</div>';
        self::datetime_row_markup('gpo_settings[engagement][rules][' . $index . ']', $rule);
        echo '</div></div>';
    }

    protected static function component_section_start($key, $title, $description, $summary = '') {
        echo '<details class="gpo-admin-accordion gpo-component-section" data-section="' . esc_attr($key) . '"' . ($key === 'featured_vehicle' ? ' open' : '') . '>';
        echo '<summary class="gpo-admin-accordion__summary">';
        echo '<div class="gpo-admin-accordion__copy">';
        echo '<h2>' . esc_html($title) . '</h2>';
        echo '<p>' . esc_html($description) . '</p>';
        echo '</div>';
        if ($summary !== '') {
            echo '<span class="gpo-status-pill">' . esc_html($summary) . '</span>';
        }
        echo '</summary>';
        echo '<div class="gpo-admin-accordion__content">';
    }

    protected static function component_section_end() {
        echo '</div></details>';
    }

    public static function dashboard_page() {
        $stats = wp_count_posts('gpo_vehicle');
        $last_sync = get_option('gpo_last_sync_result', []);
        $settings = self::get_settings();
        $parkplatform = self::parkplatform_state($settings['api']);
        $updates = self::plugin_update_state();
        $featured_ids = method_exists('GPO_Frontend', 'active_showcase_vehicle_ids')
            ? GPO_Frontend::active_showcase_vehicle_ids(24)
            : [];

        self::render_page_start(
            'dashboard',
            'GestPark dashboard',
            'Controlla connessione ParkPlatform, stato inventario e aggiornamenti plugin da un unico pannello pulito.',
            [
                ['label' => 'Sincronizza adesso', 'url' => admin_url('admin-post.php?action=gpo_run_sync&_wpnonce=' . wp_create_nonce('gpo_run_sync'))],
                ['label' => 'Configura componenti', 'url' => admin_url('admin.php?page=gpo-components'), 'variant' => 'secondary'],
                ['label' => 'Apri connessione API', 'url' => admin_url('admin.php?page=gpo-api'), 'variant' => 'secondary'],
            ],
            [$parkplatform['badge_label'], 'Versione ' . GPO_VERSION]
        );

        self::admin_notices_from_query();

        echo '<section class="gpo-kpi-grid">';
        self::metric_card('Veicoli pubblicati', (int) ($stats->publish ?? 0), 'Archivio disponibile sul sito');
        self::metric_card('Veicoli in vetrina', count($featured_ids), 'Selezionati per showcase e caroselli');
        self::metric_card('Ultima sincronizzazione', !empty($last_sync['time']) ? $last_sync['time'] : 'Mai', 'Ultimo import eseguito');
        self::metric_card('Aggiornamento plugin', $updates['headline'], $updates['description']);
        echo '</section>';

        echo '<section class="gpo-surface-grid">';
        echo '<article class="gpo-surface gpo-connection-status gpo-connection-status--' . esc_attr($parkplatform['state']) . '">';
        echo '<div class="gpo-surface__eyebrow">Stato ParkPlatform</div>';
        echo '<div class="gpo-connection-status__head">';
        echo '<div>';
        echo '<h2>' . esc_html($parkplatform['title']) . '</h2>';
        echo '<p>' . esc_html($parkplatform['message']) . '</p>';
        echo '</div>';
        echo '<span class="gpo-status-pill">' . esc_html($parkplatform['badge_label']) . '</span>';
        echo '</div>';
        echo '<div class="gpo-list-chips">';
        echo '<span class="gpo-chip">' . esc_html($parkplatform['summary']['surface_label']) . '</span>';
        echo '<span class="gpo-chip">' . esc_html($parkplatform['summary']['detail_label']) . '</span>';
        if ($parkplatform['last_check_label']) {
            echo '<span class="gpo-chip">' . esc_html($parkplatform['last_check_label']) . '</span>';
        }
        echo '</div>';
        echo '<div class="gpo-inline-actions">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin-post.php?action=gpo_test_connection&_wpnonce=' . wp_create_nonce('gpo_test_connection'))) . '">Verifica connessione</a>';
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=gpo-api')) . '">Gestisci account</a>';
        if ($parkplatform['configured']) {
            echo '<a class="button button-secondary" href="' . esc_url(self::disconnect_parkplatform_url()) . '">Scollega</a>';
        }
        echo '</div>';
        if ($parkplatform['last_message']) {
            echo '<p class="gpo-status-note">' . esc_html($parkplatform['last_message']) . '</p>';
        }
        echo '</article>';

        echo '<article class="gpo-surface">';
        echo '<div class="gpo-surface__eyebrow">Publishing stack</div>';
        echo '<h2>Blocchi pronti per il sito</h2>';
        echo '<p>Catalogo, carosello vetrina, banner marchi, ricerca e veicolo in evidenza restano disponibili in Gutenberg e shortcode con lo stesso layer dati.</p>';
        echo '<ul class="gpo-inline-code-list">';
        echo '<li><code>[gestpark_vehicle_catalog]</code></li>';
        echo '<li><code>[gestpark_featured_carousel]</code></li>';
        echo '<li><code>[gestpark_featured_vehicle]</code></li>';
        echo '<li><code>[gestpark_brand_carousel]</code></li>';
        echo '</ul>';
        echo '</article>';

        echo '<article class="gpo-surface">';
        echo '<div class="gpo-surface__eyebrow">Operativita</div>';
        echo '<h2>Controlli rapidi</h2>';
        echo '<p>Ultimo sync: <strong>' . esc_html(!empty($last_sync['time']) ? $last_sync['time'] : 'Mai eseguito') . '</strong></p>';
        echo '<p>Sorgente dati: <strong>' . esc_html(!empty($last_sync['source']) ? strtoupper((string) $last_sync['source']) : 'N.D.') . '</strong></p>';
        echo '<div class="gpo-inline-actions">';
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=gpo-components')) . '">Configurazione componenti</a>';
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=gpo-engagement')) . '">Apri Engagement</a>';
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=gpo-logs')) . '">Apri log e diagnostica</a>';
        echo '</div>';
        echo '</article>';
        echo '</section>';

        echo '<section class="gpo-surface-grid gpo-surface-grid--compact">';
        echo '<article class="gpo-surface gpo-update-mini">';
        echo '<div class="gpo-surface__eyebrow">Aggiornamenti plugin</div>';
        echo '<h2>' . esc_html($updates['mini_title']) . '</h2>';
        echo '<p>' . esc_html($updates['mini_body']) . '</p>';
        echo '<div class="gpo-inline-actions">';
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin-post.php?action=gpo_github_refresh&_wpnonce=' . wp_create_nonce('gpo_github_refresh'))) . '">Verifica aggiornamenti</a>';
        if (!empty($updates['repository_url'])) {
            echo '<a class="button button-secondary" href="' . esc_url($updates['repository_url']) . '" target="_blank">Apri repository</a>';
        }
        echo '</div>';
        echo '</article>';
        echo '</section>';

        self::render_page_end();
    }

    protected static function parkplatform_state($api) {
        $summary = GPO_API_Client::connection_summary($api);
        $last_check = get_option('gpo_last_connection_check', []);
        $last_status = sanitize_key((string) ($last_check['status'] ?? ''));
        $configured = !empty($summary['ready']);

        if (!$configured) {
            $state = 'disconnected';
            $title = 'Account ParkPlatform non collegato';
            $message = 'Inserisci email e password dell’account API ParkPlatform per attivare login JWT, sync e aggiornamento inventario.';
            $badge = 'Non collegato';
        } elseif ($last_status === 'success') {
            $state = 'connected';
            $title = 'Account ParkPlatform collegato';
            $message = 'Le credenziali risultano valide e il plugin è pronto a leggere vetrina, foto e dettaglio veicoli.';
            $badge = 'Collegato';
        } elseif ($last_status === 'error') {
            $state = 'disconnected';
            $title = 'Connessione ParkPlatform da correggere';
            $message = 'Le credenziali sono presenti ma l’ultimo controllo non è andato a buon fine. Conviene verificare subito il collegamento.';
            $badge = 'Verifica richiesta';
        } else {
            $state = 'pending';
            $title = 'Account ParkPlatform configurato';
            $message = 'Le credenziali sono salvate. Esegui un test per confermare il collegamento prima della sincronizzazione.';
            $badge = 'Da verificare';
        }

        $last_check_label = '';
        if (!empty($last_check['time'])) {
            $last_check_label = 'Ultimo check: ' . sanitize_text_field((string) $last_check['time']);
        }

        return [
            'state' => $state,
            'title' => $title,
            'message' => $message,
            'badge_label' => $badge,
            'summary' => $summary,
            'configured' => $configured,
            'last_check_label' => $last_check_label,
            'last_message' => !empty($last_check['message']) ? sanitize_text_field((string) $last_check['message']) : '',
        ];
    }

    protected static function plugin_update_state() {
        $summary = class_exists('GPO_GitHub_Updater') ? GPO_GitHub_Updater::summary() : [
            'enabled' => false,
            'repository' => '',
            'repository_url' => '',
            'branch' => 'main',
            'asset_name' => 'gestpark-online.zip',
        ];
        $transient = get_site_transient('update_plugins');
        $plugin_file = defined('GPO_PLUGIN_BASENAME') ? GPO_PLUGIN_BASENAME : plugin_basename(GPO_PLUGIN_FILE);
        $update = (!empty($transient->response[$plugin_file]) && is_object($transient->response[$plugin_file])) ? $transient->response[$plugin_file] : null;

        if (!$summary['enabled']) {
            return [
                'headline' => 'GitHub non configurato',
                'description' => 'Collega il repository per distribuire gli update del plugin.',
                'mini_title' => 'Aggiornamenti non configurati',
                'mini_body' => 'Salva il repository GitHub e usa il pulsante di verifica per far controllare nuove release a WordPress.',
                'repository_url' => '',
            ];
        }

        if ($update && !empty($update->new_version)) {
            return [
                'headline' => 'Disponibile ' . sanitize_text_field((string) $update->new_version),
                'description' => 'WordPress ha rilevato una release più recente del plugin.',
                'mini_title' => 'Aggiornamento disponibile',
                'mini_body' => 'Versione installata ' . GPO_VERSION . '. È stata rilevata la versione ' . sanitize_text_field((string) $update->new_version) . '.',
                'repository_url' => $summary['repository_url'],
            ];
        }

        return [
            'headline' => 'Plugin aggiornato',
            'description' => 'La build installata coincide con l’ultima release rilevata.',
            'mini_title' => 'Nessun aggiornamento in attesa',
            'mini_body' => 'Versione corrente ' . GPO_VERSION . '. Se hai appena pubblicato una release, usa la verifica manuale qui sotto.',
            'repository_url' => $summary['repository_url'],
        ];
    }

    protected static function disconnect_parkplatform_url() {
        return admin_url('admin-post.php?action=gpo_disconnect_parkplatform&_wpnonce=' . wp_create_nonce('gpo_disconnect_parkplatform'));
    }

    protected static function metric_card($title, $value, $description = '') {
        echo '<div class="gpo-kpi-card">';
        echo '<p class="gpo-kpi-card__title">' . esc_html($title) . '</p>';
        echo '<p class="gpo-kpi-card__value">' . esc_html((string) $value) . '</p>';
        if ($description) {
            echo '<p class="gpo-kpi-card__desc">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    public static function api_page() {
        $settings = self::get_settings();
        $api = $settings['api'];
        $parkplatform = self::parkplatform_state($api);

        self::render_page_start(
            'api',
            'Connessioni ParkPlatform API',
            'Collega il plugin al tuo account ParkPlatform API con un flusso semplice: email, password, verifica connessione e sincronizzazione veicoli.',
            [
                ['label' => 'Test connessione', 'url' => admin_url('admin-post.php?action=gpo_test_connection&_wpnonce=' . wp_create_nonce('gpo_test_connection'))],
                ['label' => 'Sincronizza adesso', 'url' => admin_url('admin-post.php?action=gpo_run_sync&_wpnonce=' . wp_create_nonce('gpo_run_sync')), 'variant' => 'secondary'],
            ],
            ['ParkPlatform API', $parkplatform['badge_label']]
        );
        self::admin_notices_from_query();
        echo '<form class="gpo-api-shell" method="post" action="options.php">';
        settings_fields('gpo_api_group');
        echo '<input type="hidden" name="gpo_settings[api][connection_mode]" value="' . esc_attr(GPO_API_Client::MODE_GESTPARK_AUTO) . '" />';
        echo '<input type="hidden" name="gpo_settings[api][manual_format]" value="' . esc_attr(GPO_API_Client::MANUAL_FORMAT_GESTPARK) . '" />';
        echo '<input type="hidden" name="gpo_settings[api][auth_method]" value="bearer" />';

        echo '<section class="gpo-surface-grid">';
        echo '<article class="gpo-surface gpo-connection-status gpo-connection-status--' . esc_attr($parkplatform['state']) . '">';
        echo '<div class="gpo-surface__eyebrow">Stato collegamento</div>';
        echo '<div class="gpo-connection-status__head">';
        echo '<div>';
        echo '<h2>' . esc_html($parkplatform['title']) . '</h2>';
        echo '<p>' . esc_html($parkplatform['message']) . '</p>';
        echo '</div>';
        echo '<span class="gpo-status-pill">' . esc_html($parkplatform['badge_label']) . '</span>';
        echo '</div>';
        echo '<div class="gpo-list-chips">';
        echo '<span class="gpo-chip">' . esc_html($parkplatform['summary']['surface_label']) . '</span>';
        echo '<span class="gpo-chip">' . esc_html($parkplatform['summary']['detail_label']) . '</span>';
        if ($parkplatform['last_check_label']) {
            echo '<span class="gpo-chip">' . esc_html($parkplatform['last_check_label']) . '</span>';
        }
        echo '</div>';
        if ($parkplatform['last_message']) {
            echo '<p class="gpo-status-note">' . esc_html($parkplatform['last_message']) . '</p>';
        }
        echo '</article>';
        echo '<article class="gpo-surface">';
        echo '<div class="gpo-surface__eyebrow">Flusso dati</div>';
        echo '<h2>Solo ParkPlatform</h2>';
        echo '<p>Il plugin usa un solo provider: login JWT su ParkPlatform API, lista veicoli con thumbnail e dettaglio completo per galleria, optional e dati tecnici. Non ci sono piu provider alternativi o mapping da gestire qui.</p>';
        echo '<ul class="gpo-flow-list">';
        echo '<li><strong>Login:</strong> <code>/api/auth/login</code></li>';
        echo '<li><strong>Lista:</strong> <code>/api/vetrina/mainphoto</code></li>';
        echo '<li><strong>Dettaglio:</strong> <code>/api/vetrina/{idGestionale}</code></li>';
        echo '</ul>';
        echo '</article>';
        echo '</section>';

        echo '<section class="gpo-surface-grid gpo-surface-grid--connections">';
        echo '<article class="gpo-surface gpo-connection-panel">';
        echo '<div class="gpo-surface__eyebrow">Credenziali account</div>';
        echo '<h2>Collega account ParkPlatform API</h2>';
        echo '<p>Usa l email dell account abilitato alle API ParkPlatform. Il plugin ottiene il token JWT in automatico e aggiorna l inventario del sito senza configurazioni dispersive.</p>';
        echo '<div class="gpo-field-grid">';
        self::render_setting_field(['label' => 'Base URL API ParkPlatform', 'name' => 'gpo_settings[api][gestpark_base_url]', 'value' => $api['gestpark_base_url'], 'description' => 'Host API usato per login, lista e dettaglio.']);
        self::render_setting_field(['label' => 'Email account API', 'name' => 'gpo_settings[api][gestpark_username]', 'value' => $api['gestpark_username'], 'description' => 'Il login API richiede un indirizzo email valido nel campo Username.', 'placeholder' => 'nome@dominio.it']);
        self::render_setting_field(['label' => 'Password account API', 'name' => 'gpo_settings[api][gestpark_password]', 'value' => $api['gestpark_password'], 'type' => 'password', 'description' => 'La password viene usata solo per ottenere il token JWT.']);
        self::render_setting_field(['label' => 'Path login', 'name' => 'gpo_settings[api][gestpark_login_path]', 'value' => $api['gestpark_login_path'], 'description' => 'Di default: /api/auth/login']);
        self::render_setting_field(['label' => 'Path lista veicoli', 'name' => 'gpo_settings[api][gestpark_list_path]', 'value' => $api['gestpark_list_path'], 'description' => 'Endpoint lista leggera senza foto originali.']);
        self::render_setting_field(['label' => 'Path lista con thumbnail', 'name' => 'gpo_settings[api][gestpark_mainphoto_path]', 'value' => $api['gestpark_mainphoto_path'], 'description' => 'Endpoint consigliato per il catalogo con foto principale.']);
        self::render_setting_field(['label' => 'Path dettaglio veicolo', 'name' => 'gpo_settings[api][gestpark_detail_path]', 'value' => $api['gestpark_detail_path'], 'description' => 'Usa il placeholder {idGestionale}.']);
        self::render_setting_field(['label' => 'Usa lista con thumbnail', 'name' => 'gpo_settings[api][prefer_mainphoto]', 'value' => !empty($api['prefer_mainphoto']), 'type' => 'checkbox', 'description' => 'Consigliato: migliora resa di catalogo e ricerca.']);
        self::render_setting_field(['label' => 'Completa ogni veicolo con dettaglio', 'name' => 'gpo_settings[api][include_details]', 'value' => !empty($api['include_details']), 'type' => 'checkbox', 'description' => 'Recupera galleria immagini, optional e dati tecnici completi.']);
        self::render_setting_field(['label' => 'Timeout richieste', 'name' => 'gpo_settings[api][timeout]', 'value' => $api['timeout'], 'description' => 'Secondi massimi di attesa per login e fetch.']);
        echo '</div>';
        echo '</article>';

        echo '<article class="gpo-surface gpo-connection-panel">';
        echo '<div class="gpo-surface__eyebrow">Azioni disponibili</div>';
        echo '<h2>Verifica e manutenzione</h2>';
        echo '<p>Da qui puoi testare subito il collegamento, lanciare una sincronizzazione reale oppure scollegare l account se vuoi ripartire da zero.</p>';
        echo '<div class="gpo-inline-actions">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin-post.php?action=gpo_test_connection&_wpnonce=' . wp_create_nonce('gpo_test_connection'))) . '">Verifica connessione</a>';
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin-post.php?action=gpo_run_sync&_wpnonce=' . wp_create_nonce('gpo_run_sync'))) . '">Sincronizza adesso</a>';
        if ($parkplatform['configured']) {
            echo '<a class="button button-secondary" href="' . esc_url(self::disconnect_parkplatform_url()) . '">Scollega account</a>';
        }
        echo '</div>';
        echo '<div class="gpo-list-chips">';
        echo '<span class="gpo-chip">Timeout ' . absint($api['timeout']) . 's</span>';
        echo '<span class="gpo-chip">JWT automatico</span>';
        echo '<span class="gpo-chip">Sync inventario e immagini</span>';
        echo '</div>';
        echo '</article>';
        echo '</section>';

        echo '<div class="gpo-form-submit">';
        submit_button('Salva configurazione API', 'primary', 'submit', false);
        echo '</div>';
        echo '</form>';
        self::render_page_end();
        return;

        echo '<section class="gpo-surface-grid gpo-surface-grid--connections">';
        echo '<article class="gpo-surface gpo-connection-panel">';
        echo '<div class="gpo-surface__eyebrow">Percorso consigliato</div>';
        echo '<h2>Account ParkPlatform API</h2>';
        echo '<p>I veicoli arrivano da GestPark dentro ParkPlatform. Il plugin effettua login JWT su <code>/api/auth/login</code>, legge la lista vetrina su <code>/api/vetrina/mainphoto</code> e recupera il dettaglio singolo su <code>/api/vetrina/{idGestionale}</code>.</p>';
        echo '<div class="gpo-field-grid">';
        self::render_setting_field(['label' => 'Base URL API ParkPlatform', 'name' => 'gpo_settings[api][gestpark_base_url]', 'value' => $api['gestpark_base_url'], 'description' => 'Host API usato per login, lista e dettaglio.']);
        self::render_setting_field(['label' => 'Email account API', 'name' => 'gpo_settings[api][gestpark_username]', 'value' => $api['gestpark_username'], 'description' => 'L endpoint di login accetta solo un indirizzo email valido nel campo Username.', 'placeholder' => 'nome@dominio.it']);
        self::render_setting_field(['label' => 'Password account API', 'name' => 'gpo_settings[api][gestpark_password]', 'value' => $api['gestpark_password'], 'type' => 'password', 'description' => 'La password viene usata solo per ottenere il token JWT.']);
        self::render_setting_field(['label' => 'Path login', 'name' => 'gpo_settings[api][gestpark_login_path]', 'value' => $api['gestpark_login_path'], 'description' => 'Di default: /api/auth/login']);
        self::render_setting_field(['label' => 'Path lista veicoli', 'name' => 'gpo_settings[api][gestpark_list_path]', 'value' => $api['gestpark_list_path'], 'description' => 'Endpoint lista leggera senza foto originali.']);
        self::render_setting_field(['label' => 'Path lista con thumbnail', 'name' => 'gpo_settings[api][gestpark_mainphoto_path]', 'value' => $api['gestpark_mainphoto_path'], 'description' => 'Endpoint consigliato per il catalogo con foto principale.']);
        self::render_setting_field(['label' => 'Path dettaglio veicolo', 'name' => 'gpo_settings[api][gestpark_detail_path]', 'value' => $api['gestpark_detail_path'], 'description' => 'Usa il placeholder {idGestionale}.']);
        self::render_setting_field(['label' => 'Usa lista con thumbnail', 'name' => 'gpo_settings[api][prefer_mainphoto]', 'value' => !empty($api['prefer_mainphoto']), 'type' => 'checkbox', 'description' => 'Consigliato: migliora resa di catalogo e ricerca.']);
        self::render_setting_field(['label' => 'Completa ogni veicolo con dettaglio', 'name' => 'gpo_settings[api][include_details]', 'value' => !empty($api['include_details']), 'type' => 'checkbox', 'description' => 'Recupera galleria immagini, optional e dati tecnici completi.']);
        echo '</div>';
        echo '</article>';

        echo '<article class="gpo-surface gpo-connection-panel">';
        echo '<div class="gpo-surface__eyebrow">Fallback manuale</div>';
        echo '<h2>Endpoint e token</h2>';
        echo '<p>Quando preferisci gestire tutto manualmente puoi incollare token JWT ed endpoint ParkPlatform API, oppure mantenere un feed JSON generico gia presente.</p>';
        echo '<div class="gpo-field-grid">';
        self::render_setting_field(['label' => 'Formato manuale', 'name' => 'gpo_settings[api][manual_format]', 'value' => $api['manual_format'], 'type' => 'select', 'options' => ['gestpark' => 'ParkPlatform API manuale', 'generic' => 'JSON generico legacy'], 'description' => 'Scegli come interpretare endpoint e token.']);
        self::render_setting_field(['label' => 'Endpoint lista', 'name' => 'gpo_settings[api][endpoint]', 'value' => $api['endpoint'], 'description' => 'Per ParkPlatform usa /api/vetrina oppure /api/vetrina/mainphoto.', 'classes' => 'gpo-field--manual-common']);
        self::render_setting_field(['label' => 'Endpoint dettaglio', 'name' => 'gpo_settings[api][detail_endpoint]', 'value' => $api['detail_endpoint'], 'description' => 'Per ParkPlatform usa il placeholder {idGestionale}.', 'classes' => 'gpo-field--manual-gestpark']);
        self::render_setting_field(['label' => 'Endpoint login manuale', 'name' => 'gpo_settings[api][login_endpoint]', 'value' => $api['login_endpoint'], 'description' => 'Riferimento rapido per ottenere il token fuori dal plugin.', 'classes' => 'gpo-field--manual-gestpark']);
        self::render_setting_field(['label' => 'Autenticazione', 'name' => 'gpo_settings[api][auth_method]', 'value' => $api['auth_method'], 'type' => 'select', 'options' => ['bearer' => 'Bearer token', 'x_api_key' => 'X-API-Key', 'none' => 'Nessuna'], 'description' => 'GestPark usa Bearer token JWT.']);
        self::render_setting_field(['label' => 'Bearer token', 'name' => 'gpo_settings[api][token]', 'value' => $api['token'], 'type' => 'password', 'description' => 'Incolla il token ottenuto via login GestPark.']);
        self::render_setting_field(['label' => 'API key', 'name' => 'gpo_settings[api][api_key]', 'value' => $api['api_key'], 'description' => 'Usato solo da feed generici alternativi.', 'classes' => 'gpo-field--manual-generic']);
        self::render_setting_field(['label' => 'Items path JSON', 'name' => 'gpo_settings[api][items_path]', 'value' => $api['items_path'], 'description' => 'Solo per feed annidati, ad esempio data.vehicles.', 'classes' => 'gpo-field--manual-generic']);
        self::render_setting_field(['label' => 'Timeout richieste', 'name' => 'gpo_settings[api][timeout]', 'value' => $api['timeout'], 'description' => 'Secondi massimi di attesa per login e fetch.']);
        echo '</div>';
        echo '</article>';
        echo '</section>';

        echo '<section class="gpo-surface-grid">';
        echo '<article class="gpo-surface">';
        echo '<div class="gpo-surface__eyebrow">Flow tecnico</div>';
        echo '<h2>Come lavora il plugin</h2>';
        echo '<ol class="gpo-flow-list">';
        echo '<li>Login JWT con username e password oppure token manuale gia disponibile.</li>';
        echo '<li>Recupero lista veicoli con marchio, modello, prezzo e thumbnail principale.</li>';
        echo '<li>Dettaglio singolo veicolo per galleria completa, optional e dati tecnici estesi.</li>';
        echo '</ol>';
        echo '</article>';
        echo '<article class="gpo-surface">';
        echo '<div class="gpo-surface__eyebrow">Mappatura</div>';
        echo '<h2>Quando serve davvero</h2>';
        echo '<p>Con ParkPlatform API la mappatura principale e gia integrata nel plugin. La pagina <strong>Mappatura campi</strong> rimane utile solo per il fallback manuale JSON legacy.</p>';
        echo '</article>';
        echo '</section>';

        echo '<div class="gpo-form-submit">';
        submit_button('Salva configurazione API', 'primary', 'submit', false);
        echo '</div>';
        echo '</form>';
        self::render_page_end();
        return;
        echo '<div class="wrap gpo-admin-wrap"><h1>Connessioni API</h1>';
        self::admin_notices_from_query();
        echo '<p><strong>Flusso consigliato:</strong> scegli il template, aprilo nellâ€™editor, usa il veicolo demo per rifinire layout e contenuti e poi assegna quel template a tutte le schede veicolo.</p>';
        if (GPO_API_Client::uses_gestpark($settings['api'])) {
            echo '<div class="notice notice-info inline"><p>Con GestPark automatico o manuale la mappatura base e gia integrata nel plugin. Modifica questi campi solo se stai usando il fallback JSON legacy.</p></div>';
        }
        if (GPO_API_Client::uses_gestpark($settings['api'])) {
            echo '<div class="notice notice-info inline"><p>Con GestPark automatico o manuale la mappatura base e gia integrata nel plugin. Modifica questi campi solo se stai usando il fallback JSON legacy.</p></div>';
        }
        echo '<p class="description">Le informazioni visibili nelle card e nei cataloghi si gestiscono dai blocchi Gutenberg, non da questa dashboard.</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('gpo_api_group');
        echo '<table class="form-table"><tbody>';
        self::input_row('Endpoint API', 'gpo_settings[api][endpoint]', $settings['api']['endpoint'], 'URL dell’endpoint che restituisce i veicoli in JSON.');
        self::select_row('Autenticazione', 'gpo_settings[api][auth_method]', $settings['api']['auth_method'], ['none' => 'Nessuna', 'bearer' => 'Bearer Token', 'x_api_key' => 'X-API-Key']);
        self::input_row('Bearer token', 'gpo_settings[api][token]', $settings['api']['token']);
        self::input_row('API key', 'gpo_settings[api][api_key]', $settings['api']['api_key']);
        self::input_row('Percorso elementi', 'gpo_settings[api][items_path]', $settings['api']['items_path'], 'Esempio: data.vehicles se il JSON è annidato. Lascia vuoto se l’endpoint restituisce direttamente l’array.');
        self::input_row('Timeout', 'gpo_settings[api][timeout]', $settings['api']['timeout']);
        echo '</tbody></table>';
        submit_button('Salva configurazione API');
        echo '</form>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin-post.php?action=gpo_test_connection&_wpnonce=' . wp_create_nonce('gpo_test_connection'))) . '">Test connessione</a> ';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin-post.php?action=gpo_run_sync&_wpnonce=' . wp_create_nonce('gpo_run_sync'))) . '">Sincronizza adesso</a></p>';
        echo '</div>';
    }

    public static function mapping_page() {
        $settings = self::get_settings();
        $required = ['condition', 'year', 'price', 'fuel', 'mileage', 'body_type', 'transmission', 'engine_size'];
        echo '<div class="wrap gpo-admin-wrap"><h1>Mappatura campi</h1>';
        echo '<p>I campi obbligatori sono evidenziati e servono per pubblicare correttamente i veicoli sul web. Ogni singolo valore può essere collegato a una propria API o a un percorso JSON dedicato.</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('gpo_api_group');
        echo '<table class="form-table"><tbody>';
        foreach (GPO_CPT::fields() as $key => $label) {
            $is_required = in_array($key, $required, true);
            self::input_row(
                $label . ($is_required ? ' *' : ''),
                'gpo_settings[mapping][' . $key . ']',
                $settings['mapping'][$key] ?? '',
                $is_required ? 'Campo obbligatorio da mappare.' : 'Nome campo JSON oppure percorso annidato, ad esempio data.vehicle.brand'
            );
        }
        self::input_row('Descrizione', 'gpo_settings[mapping][description]', $settings['mapping']['description'] ?? 'description', 'Campo descrittivo da usare nel contenuto del post.');
        echo '</tbody></table>';
        submit_button('Salva mappatura');
        echo '</form>';
        echo '</div>';
    }

    public static function showcase_page() {
        $query = new WP_Query([
            'post_type' => 'gpo_vehicle',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        echo '<div class="wrap gpo-admin-wrap"><h1>Vetrina</h1>';
        echo '<p>Da questa dashboard puoi selezionare i veicoli già importati e programmare la vetrina generale.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gpo_save_showcase');
        echo '<input type="hidden" name="action" value="gpo_save_showcase" />';
        echo '<table class="widefat striped"><thead><tr><th>Veicolo</th><th>In vetrina</th><th>Ordine</th><th>Dal</th><th>Al</th><th>Badge</th></tr></thead><tbody>';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                echo '<tr>';
                echo '<td><strong>' . esc_html(get_the_title()) . '</strong><br><small>' . esc_html(get_post_meta($post_id, '_gpo_external_id', true)) . '</small></td>';
                echo '<td><input type="checkbox" name="showcase[' . esc_attr($post_id) . '][featured]" value="1" ' . checked(get_post_meta($post_id, '_gpo_featured', true), '1', false) . ' /></td>';
                echo '<td><input type="number" name="showcase[' . esc_attr($post_id) . '][order]" value="' . esc_attr(get_post_meta($post_id, '_gpo_featured_order', true)) . '" style="width:80px;" /></td>';
                echo '<td><input type="datetime-local" name="showcase[' . esc_attr($post_id) . '][from]" value="' . esc_attr(get_post_meta($post_id, '_gpo_featured_from', true)) . '" /></td>';
                echo '<td><input type="datetime-local" name="showcase[' . esc_attr($post_id) . '][to]" value="' . esc_attr(get_post_meta($post_id, '_gpo_featured_to', true)) . '" /></td>';
                echo '<td><input type="text" name="showcase[' . esc_attr($post_id) . '][badge]" value="' . esc_attr(get_post_meta($post_id, '_gpo_badge', true)) . '" /></td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        }
        echo '</tbody></table>';
        submit_button('Salva programmazione vetrina');
        echo '</form></div>';
    }

    public static function style_page() {
        $settings = self::get_settings();
        $site_editor_url = function_exists('wp_is_block_theme') && wp_is_block_theme()
            ? admin_url('site-editor.php')
            : '';

        echo '<div class="wrap gpo-admin-wrap"><h1>Aspetto</h1>';
        echo '<p>Qui definisci il layout generale del catalogo e della scheda veicolo. Puoi anche selezionare un template veicolo modificabile direttamente nell’editor di WordPress, così da spostare box, caroselli, annunci e blocchi senza toccare codice.</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('gpo_api_group');
        echo '<table class="form-table"><tbody>';
        self::input_row('Colore principale', 'gpo_settings[style][primary_color]', $settings['style']['primary_color']);
        self::input_row('Colore accento', 'gpo_settings[style][accent_color]', $settings['style']['accent_color']);
        self::input_row('Sfondo card', 'gpo_settings[style][card_bg]', $settings['style']['card_bg']);
        self::input_row('Raggio bordi', 'gpo_settings[style][radius]', $settings['style']['radius']);
        self::input_row('Font titoli', 'gpo_settings[style][title_font]', $settings['style']['title_font'], 'Inserisci un font CSS, ad esempio Inter, Arial, sans-serif');
        self::input_row('Font testi', 'gpo_settings[style][body_font]', $settings['style']['body_font'], 'Inserisci un font CSS, ad esempio Inter, Arial, sans-serif');
        self::select_row('Layout fallback scheda veicolo', 'gpo_settings[style][single_layout]', $settings['style']['single_layout'], ['classic' => 'Classic', 'reversed' => 'Reversed', 'stacked' => 'Stacked']);
        if (false) {
        echo '<tr><th scope="row"><label>Template veicolo da editor</label></th><td><select name="gpo_settings[style][single_template_id]">';
        echo '<option value="0">Usa il template fallback del plugin</option>';
        foreach ($templates as $template_post) {
            echo '<option value="' . esc_attr($template_post->ID) . '" ' . selected($selected_template_id, $template_post->ID, false) . '>' . esc_html($template_post->post_title) . '</option>';
        }
        echo '</select><p class="description">Seleziona un template costruito con l’editor di WordPress. Tutti i veicoli useranno questo layout globale.</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('post-new.php?post_type=gpo_template')) . '">Crea nuovo template veicolo</a>';
        if ($selected_template_id > 0) {
            echo ' <a class="button button-secondary" href="' . esc_url(get_edit_post_link($selected_template_id)) . '">Modifica template selezionato</a>';
        }
        if ($template_preview_url) {
            echo ' <a class="button button-secondary" target="_blank" href="' . esc_url($template_preview_url) . '">Anteprima con veicolo reale</a>';
        }
        if ($template_preview_edit_url) {
            echo ' <a class="button button-secondary" href="' . esc_url($template_preview_edit_url) . '">Apri veicolo usato per l anteprima</a>';
        }
        echo '</p></td></tr>';
        }
        echo '<tr><th scope="row"><label>Template single veicolo</label></th><td>';
        echo '<p class="description">Il plugin non usa piu un template separato da questa dashboard. Per comporre la scheda veicolo usa il template single del tema nel WordPress Editor.</p>';
        if ($site_editor_url) {
            echo '<p><a class="button button-secondary" href="' . esc_url($site_editor_url) . '">Apri Site Editor</a></p>';
        }
        echo '</td></tr>';
        echo '<tr><th scope="row"><label>Immagine fallback veicolo</label></th><td>';
        echo '<input id="gpo-fallback-vehicle-image" class="regular-text" type="text" name="gpo_settings[style][fallback_vehicle_image]" value="' . esc_attr((string) ($settings['style']['fallback_vehicle_image'] ?? '')) . '" /> ';
        echo '<a href="#" class="button gpo-media-upload" data-target="#gpo-fallback-vehicle-image">Carica o scegli immagine</a> ';
        echo '<a href="#" class="button gpo-media-clear" data-target="#gpo-fallback-vehicle-image">Rimuovi</a>';
        echo '<p class="description">Se un veicolo non ha foto, verrà mostrata questa immagine. Se lasci vuoto, verrà usata l\'immagine standard inclusa nel plugin.</p>';
        echo '</td></tr>';
        self::input_row('Gap card catalogo', 'gpo_settings[style][card_gap]', $settings['style']['card_gap'], 'Valore in px, senza unità. Esempio: 24');
        self::input_row('Padding interno card', 'gpo_settings[style][card_padding]', $settings['style']['card_padding'], 'Valore in px, senza unità. Esempio: 22');
        self::input_row('Email richieste veicolo', 'gpo_settings[style][lead_email]', $settings['style']['lead_email'] ?? get_option('admin_email'), 'Le richieste inviate dalla scheda veicolo saranno recapitate a questo indirizzo.');
        self::input_row('Messaggio conferma invio', 'gpo_settings[style][lead_success_message]', $settings['style']['lead_success_message'] ?? 'Richiesta inviata correttamente. Ti ricontatteremo al più presto.', 'Messaggio mostrato al cliente dopo l invio del modulo.');
        self::input_row('Larghezza massima contenuto', 'gpo_settings[style][content_max_width]', $settings['style']['content_max_width'], 'Valore in px. Controlla il contenitore generale in modo responsive.');
        self::input_row('Margine verticale sezioni', 'gpo_settings[style][outer_margin_y]', $settings['style']['outer_margin_y'], 'Valore in px. Spazio sopra e sotto ai moduli.');
        self::input_row('Padding laterale contenitore', 'gpo_settings[style][outer_padding_x]', $settings['style']['outer_padding_x'], 'Valore in px. Spazio laterale adattabile a ogni risoluzione.');
        self::input_row('Spazio verticale tra moduli', 'gpo_settings[style][section_gap]', $settings['style']['section_gap'], 'Valore in px. Distanza tra gruppi, sezioni e blocchi.');
        self::input_row('Colonne pannello filtri', 'gpo_settings[style][filter_columns]', $settings['style']['filter_columns'], 'Numero massimo di colonne su desktop per il pannello filtri.');
        echo '</tbody></table>';

        submit_button('Salva aspetto');
        echo '</form>';

        echo '<div class="gpo-admin-panel" style="margin-top:24px;"><h2>Come funziona il template veicolo nell’editor</h2>';
        echo '<p>Apri o crea un contenuto del tipo <strong>Template veicolo</strong> e usa i blocchi GestPark: Hero veicolo, Galleria veicolo, Scheda tecnica, Descrizione, Note, Accessori, Contatto e Carosello veicoli. Puoi spostare i blocchi, inserirli dentro colonne o gruppi, aggiungere annunci, CTA, banner o qualsiasi blocco Gutenberg del tema.</p>';
        if ($site_editor_url) {
            echo '<p><a class="button button-secondary" href="' . esc_url($site_editor_url) . '">Apri Site Editor</a></p>';
        }
        echo '<p><strong>Nota:</strong> il frontend non usa piu un template separato del plugin. Questa sezione resta solo come riferimento ai blocchi dinamici disponibili.</p>';
        echo '<p><code>[gestpark_featured_carousel show="title,primary_button" card_layout="minimal"]</code></p>';
        echo '<p><code>[gestpark_vehicle_catalog show="image,title,price,primary_button" card_layout="compact"]</code></p>';
        echo '</div>';
        echo '</div>';
    }


    public static function updates_page() {
        $settings = self::get_settings();
        $github = isset($settings['github']) && is_array($settings['github']) ? $settings['github'] : [];
        $summary = class_exists('GPO_GitHub_Updater') ? GPO_GitHub_Updater::summary() : [
            'enabled' => false,
            'repository' => '',
            'repository_url' => '',
            'branch' => 'main',
            'asset_name' => 'gestpark-online.zip',
            'has_token' => false,
        ];

        $actions = [
            ['label' => 'Forza controllo aggiornamenti', 'url' => admin_url('admin-post.php?action=gpo_github_refresh&_wpnonce=' . wp_create_nonce('gpo_github_refresh'))],
        ];

        if (!empty($summary['repository_url'])) {
            $actions[] = ['label' => 'Apri repository', 'url' => $summary['repository_url'], 'variant' => 'secondary', 'target' => '_blank'];
        }

        self::render_page_start(
            'updates',
            'Aggiornamenti GitHub',
            'Collega il plugin a un repository GitHub e usa le release per distribuire aggiornamenti WordPress senza ricreare manualmente la zip a ogni modifica.',
            $actions,
            [$summary['enabled'] ? 'Aggiornamenti attivi' : 'Aggiornamenti non configurati']
        );

        self::admin_notices_from_query();

        echo '<form class="gpo-api-shell" method="post" action="options.php">';
        settings_fields('gpo_api_group');

        echo '<section class="gpo-surface-grid gpo-surface-grid--connections">';
        echo '<article class="gpo-surface gpo-connection-panel">';
        echo '<div class="gpo-surface__eyebrow">Configurazione plugin</div>';
        echo '<h2>Repository e release asset</h2>';
        echo '<p>Il plugin controlla GitHub per leggere l ultima release pubblicata, confronta il tag con la versione installata e propone l aggiornamento direttamente dentro WordPress.</p>';
        echo '<div class="gpo-field-grid">';
        self::render_setting_field(['label' => 'Abilita aggiornamenti GitHub', 'name' => 'gpo_settings[github][enabled]', 'value' => !empty($github['enabled']), 'type' => 'checkbox', 'description' => 'Attiva il controllo automatico delle release GitHub per questo plugin.']);
        self::render_setting_field(['label' => 'Repository GitHub', 'name' => 'gpo_settings[github][repository]', 'value' => $github['repository'] ?? '', 'description' => 'Formato consigliato: owner/repository oppure URL completo GitHub.', 'placeholder' => 'francesco/gestpark-online']);
        self::render_setting_field(['label' => 'Branch principale', 'name' => 'gpo_settings[github][branch]', 'value' => $github['branch'] ?? 'main', 'description' => 'Branch usato per lo sviluppo e come riferimento nella documentazione della release.', 'placeholder' => 'main']);
        self::render_setting_field(['label' => 'Nome file zip release', 'name' => 'gpo_settings[github][release_asset]', 'value' => $github['release_asset'] ?? 'gestpark-online.zip', 'description' => 'Il workflow GitHub generera questo asset e WordPress scarichera proprio quel file.', 'placeholder' => 'gestpark-online.zip']);
        self::render_setting_field(['label' => 'Token GitHub opzionale', 'name' => 'gpo_settings[github][access_token]', 'value' => $github['access_token'] ?? '', 'type' => 'password', 'description' => 'Serve solo se il repository e privato oppure se vuoi evitare limiti API piu stretti.']);
        echo '</div>';
        echo '</article>';

        echo '<article class="gpo-surface gpo-connection-panel">';
        echo '<div class="gpo-surface__eyebrow">Stato corrente</div>';
        echo '<h2>Cosa usera WordPress</h2>';
        echo '<ul class="gpo-flow-list">';
        echo '<li><strong>Repository:</strong> ' . esc_html($summary['repository'] ?: 'non configurato') . '</li>';
        echo '<li><strong>Branch:</strong> ' . esc_html($summary['branch'] ?: 'main') . '</li>';
        echo '<li><strong>Asset zip:</strong> ' . esc_html($summary['asset_name'] ?: 'gestpark-online.zip') . '</li>';
        echo '<li><strong>Repository privato:</strong> ' . esc_html($summary['has_token'] ? 'token configurato' : 'no, accesso pubblico o token mancante') . '</li>';
        echo '<li><strong>Versione plugin installata:</strong> ' . esc_html((string) GPO_VERSION) . '</li>';
        echo '</ul>';
        echo '</article>';
        echo '</section>';

        echo '<section class="gpo-surface-grid">';
        echo '<article class="gpo-surface">';
        echo '<div class="gpo-surface__eyebrow">Workflow consigliato</div>';
        echo '<h2>Come pubblicare senza zip manuale</h2>';
        echo '<ol class="gpo-flow-list">';
        echo '<li>Lavora nel repository GitHub del plugin invece di creare zip locali.</li>';
        echo '<li>Quando vuoi distribuire un update, aggiorna il numero versione nel plugin.</li>';
        echo '<li>Crea un tag Git come <code>v' . esc_html((string) GPO_VERSION) . '</code> o successivo e fai push.</li>';
        echo '<li>GitHub Actions crea la release e allega automaticamente il file zip del plugin.</li>';
        echo '<li>WordPress rileva il nuovo tag e mostra l aggiornamento nel pannello plugin.</li>';
        echo '</ol>';
        echo '</article>';

        echo '<article class="gpo-surface">';
        echo '<div class="gpo-surface__eyebrow">Sviluppo locale</div>';
        echo '<h2>Niente reinstallazioni durante il lavoro</h2>';
        echo '<p>Per sviluppo locale conviene usare il repository direttamente dentro <code>wp-content/plugins</code> oppure una junction Windows verso quella cartella. GitHub serve per distribuire gli aggiornamenti, non per obbligarti a reinstallare il plugin a ogni modifica.</p>';
        echo '<p>Trovi i file gia pronti in <code>.github/workflows/release-plugin.yml</code> e <code>docs/github-updates.md</code>.</p>';
        echo '</article>';
        echo '</section>';

        submit_button('Salva configurazione GitHub');
        echo '</form>';

        self::render_page_end();
    }

    public static function components_page() {
        $settings = self::get_settings();
        $components = isset($settings['components']) && is_array($settings['components']) ? $settings['components'] : [];
        $components = wp_parse_args($components, self::default_component_settings());
        $vehicle_options = self::vehicle_options();
        $brand_options = self::brand_options();
        $featured_queue_rows = max(3, count($components['featured_vehicle']['queue'] ?? []) + 1);
        $showcase_queue_rows = max(3, count($components['showcase_carousel']['queue'] ?? []) + 1);

        $featured_summary = !empty($components['featured_vehicle']['vehicle_id']) && isset($vehicle_options[$components['featured_vehicle']['vehicle_id']])
            ? $vehicle_options[$components['featured_vehicle']['vehicle_id']]
            : 'Fallback automatico';
        $brand_mode_labels = [
            'inventory' => 'Solo marchi in stock',
            'all' => 'Tutti i marchi generali',
            'manual' => 'Marchi scelti manualmente',
        ];
        $brand_summary = $brand_mode_labels[$components['brand_banner']['mode'] ?? 'inventory'] ?? 'Solo marchi in stock';
        $showcase_summary = !empty($components['showcase_carousel']['vehicle_ids'])
            ? count((array) $components['showcase_carousel']['vehicle_ids']) . ' veicoli attivi'
            : 'Fallback automatico';

        self::render_page_start(
            'components',
            'Configurazione componenti',
            'Gestisci da un unico pannello i sette componenti principali del plugin, le pianificazioni future e i marchi da mostrare nelle sezioni pubbliche del sito.',
            [
                ['label' => 'Apri Engagement', 'url' => admin_url('admin.php?page=gpo-engagement')],
                ['label' => 'Sincronizza adesso', 'url' => admin_url('admin-post.php?action=gpo_run_sync&_wpnonce=' . wp_create_nonce('gpo_run_sync')), 'variant' => 'secondary'],
            ],
            ['7 componenti centrali', 'Programmazione vetrina']
        );
        self::admin_notices_from_query();

        echo '<form class="gpo-api-shell" method="post" action="options.php">';
        settings_fields('gpo_api_group');

        self::component_section_start(
            'featured_vehicle',
            'Veicolo in evidenza',
            'Configura il veicolo principale da mettere in risalto sul sito e pianifica eventuali sostituzioni future.',
            $featured_summary
        );
        echo '<div class="gpo-field-grid">';
        self::render_setting_field([
            'label' => 'Veicolo principale',
            'name' => 'gpo_settings[components][featured_vehicle][vehicle_id]',
            'value' => $components['featured_vehicle']['vehicle_id'] ?? 0,
            'type' => 'select',
            'options' => $vehicle_options,
            'description' => 'Se non imposti nulla, il plugin usera il miglior fallback disponibile in automatico.',
        ]);
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact">';
        echo '<div class="gpo-surface__eyebrow">Logica runtime</div>';
        echo '<h2>Rotazione automatica</h2>';
        echo '<p>Quando la finestra del veicolo attuale termina, il plugin verifica la coda programmata in ordine crescente e promuove automaticamente il primo slot attivo.</p>';
        echo '</div>';
        echo '</div>';
        self::datetime_row_markup('gpo_settings[components][featured_vehicle]', $components['featured_vehicle']);

        echo '<div class="gpo-admin-rule-list">';
        echo '<h3>Coda futura veicoli in evidenza</h3>';
        echo '<p class="gpo-field__description">Usa lordine per definire la priorita di subentro. Compila solo le righe che ti servono.</p>';
        for ($i = 0; $i < $featured_queue_rows; $i++) {
            $row = $components['featured_vehicle']['queue'][$i] ?? [];
            echo '<div class="gpo-admin-rule-card">';
            echo '<div class="gpo-field-grid gpo-field-grid--triple">';
            self::render_setting_field([
                'label' => 'Ordine',
                'name' => 'gpo_settings[components][featured_vehicle][queue][' . $i . '][order]',
                'value' => $row['order'] ?? ($i + 1),
                'description' => 'Valore minore = priorita piu alta.',
            ]);
            self::render_setting_field([
                'label' => 'Etichetta interna',
                'name' => 'gpo_settings[components][featured_vehicle][queue][' . $i . '][label]',
                'value' => $row['label'] ?? '',
                'description' => 'Solo per uso interno in dashboard.',
                'placeholder' => 'Es. Weekend usato certificato',
            ]);
            self::render_setting_field([
                'label' => 'Veicolo',
                'name' => 'gpo_settings[components][featured_vehicle][queue][' . $i . '][vehicle_id]',
                'value' => $row['vehicle_id'] ?? 0,
                'type' => 'select',
                'options' => $vehicle_options,
                'description' => 'Seleziona il veicolo che subentrera in evidenza.',
            ]);
            echo '</div>';
            self::datetime_row_markup('gpo_settings[components][featured_vehicle][queue][' . $i . ']', $row);
            echo '</div>';
        }
        echo '</div>';
        self::component_section_end();

        self::component_section_start(
            'search_bar',
            'Search bar',
            'Configura il comportamento del componente di ricerca veicoli inseribile nelle pagine del sito.',
            'Struttura pronta'
        );
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact">';
        echo '<div class="gpo-surface__eyebrow">Roadmap componente</div>';
        echo '<h2>Base pronta per estensioni</h2>';
        echo '<p>La barra di ricerca usa gia il motore live del plugin. In questa iterazione la sezione resta volutamente minimale per lasciare la personalizzazione visuale ai blocchi Gutenberg.</p>';
        echo '</div>';
        self::component_section_end();

        self::component_section_start(
            'brand_banner',
            'Banner marchi',
            'Configura quali marchi mostrare nel banner orizzontale e come presentarli all utente.',
            $brand_summary
        );
        echo '<div class="gpo-field-grid">';
        self::render_setting_field([
            'label' => 'Modalita marchi',
            'name' => 'gpo_settings[components][brand_banner][mode]',
            'value' => $components['brand_banner']['mode'] ?? 'inventory',
            'type' => 'select',
            'options' => [
                'inventory' => 'Mostra solo i marchi presenti nel parco auto disponibile',
                'all' => 'Mostra tutti i marchi generali del plugin',
                'manual' => 'Mostra solo i marchi selezionati manualmente',
            ],
            'description' => 'La modalita manuale usa la libreria generale marchi del plugin, non solo quelli gia presenti nei veicoli.',
            'classes' => 'gpo-brand-mode-field',
        ]);
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact">';
        echo '<div class="gpo-surface__eyebrow">Brand registry</div>';
        echo '<h2>Libreria marchi condivisa</h2>';
        echo '<p>I marchi vengono letti dal registry locale del plugin. Se scegli Tutti, mostriamo lintera libreria; se scegli Manuale, usi la stessa libreria con una selezione esplicita.</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="gpo-brand-picker" data-brand-mode="' . esc_attr($components['brand_banner']['mode'] ?? 'inventory') . '">';
        echo '<div class="gpo-brand-picker__manual">';
        echo '<h3>Marchi selezionati manualmente</h3>';
        echo '<div class="gpo-checkbox-grid">';
        foreach ($brand_options as $key => $label) {
            $checked = in_array($key, (array) ($components['brand_banner']['selected_brands'] ?? []), true);
            echo '<label class="gpo-checkbox-card"><input type="checkbox" name="gpo_settings[components][brand_banner][selected_brands][]" value="' . esc_attr($key) . '" ' . checked($checked, true, false) . ' /> <span>' . esc_html($label) . '</span></label>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        self::component_section_end();

        self::component_section_start(
            'catalog_filters',
            'Catalogo veicoli con filtri',
            'Configura il catalogo veicoli con pannello filtri e risultati consultabili nelle pagine del sito.',
            'Struttura pronta'
        );
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact"><div class="gpo-surface__eyebrow">Gestione dal blocco</div><h2>Configurazione editor-first</h2><p>Il layout, i campi visibili e la disposizione del catalogo restano gestiti principalmente dai blocchi Gutenberg. Questa sezione e pronta per configurazioni centrali future.</p></div>';
        self::component_section_end();

        self::component_section_start(
            'showcase_carousel',
            'Carosello vetrina',
            'Gestisci i veicoli da mostrare nella vetrina dinamica e pianifica le future rotazioni.',
            $showcase_summary
        );
        echo '<div class="gpo-field-grid">';
        echo '<label class="gpo-field"><span class="gpo-field__label">Veicoli attivi in vetrina</span>';
        echo '<select multiple size="8" name="gpo_settings[components][showcase_carousel][vehicle_ids][]" class="gpo-multi-select">';
        foreach ($vehicle_options as $vehicle_id => $label) {
            if ($vehicle_id === 0) {
                continue;
            }
            echo '<option value="' . esc_attr((string) $vehicle_id) . '" ' . selected(in_array($vehicle_id, (array) ($components['showcase_carousel']['vehicle_ids'] ?? []), true), true, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><span class="gpo-field__description">Seleziona i veicoli da mostrare nella vetrina attuale. Se non selezioni nulla, il plugin usera il fallback automatico.</span></label>';
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact">';
        echo '<div class="gpo-surface__eyebrow">Rotazione future</div>';
        echo '<h2>Coda multi-veicolo</h2>';
        echo '<p>Ogni slot puo contenere un gruppo di veicoli diverso. Quando lo slot precedente termina, il carosello passa al primo gruppo futuro attivo in ordine.</p>';
        echo '</div>';
        echo '</div>';
        self::datetime_row_markup('gpo_settings[components][showcase_carousel]', $components['showcase_carousel']);

        echo '<div class="gpo-admin-rule-list">';
        echo '<h3>Coda futura carosello vetrina</h3>';
        echo '<p class="gpo-field__description">Ogni slot puo avere un proprio gruppo di veicoli e una propria finestra temporale.</p>';
        for ($i = 0; $i < $showcase_queue_rows; $i++) {
            $row = $components['showcase_carousel']['queue'][$i] ?? [];
            echo '<div class="gpo-admin-rule-card">';
            echo '<div class="gpo-field-grid gpo-field-grid--triple">';
            self::render_setting_field([
                'label' => 'Ordine',
                'name' => 'gpo_settings[components][showcase_carousel][queue][' . $i . '][order]',
                'value' => $row['order'] ?? ($i + 1),
                'description' => 'Valore minore = priorita piu alta.',
            ]);
            self::render_setting_field([
                'label' => 'Etichetta interna',
                'name' => 'gpo_settings[components][showcase_carousel][queue][' . $i . '][label]',
                'value' => $row['label'] ?? '',
                'description' => 'Nome operativo dello slot.',
                'placeholder' => 'Es. Promo fine mese',
            ]);
            echo '<div class="gpo-field"><span class="gpo-field__label">Veicoli dello slot</span><select multiple size="8" name="gpo_settings[components][showcase_carousel][queue][' . $i . '][vehicle_ids][]" class="gpo-multi-select">';
            foreach ($vehicle_options as $vehicle_id => $label) {
                if ($vehicle_id === 0) {
                    continue;
                }
                echo '<option value="' . esc_attr((string) $vehicle_id) . '" ' . selected(in_array($vehicle_id, (array) ($row['vehicle_ids'] ?? []), true), true, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select><span class="gpo-field__description">Il carosello mostrerà questi veicoli quando lo slot sarà attivo.</span></div>';
            echo '</div>';
            self::datetime_row_markup('gpo_settings[components][showcase_carousel][queue][' . $i . ']', $row);
            echo '</div>';
        }
        echo '</div>';
        self::component_section_end();

        self::component_section_start(
            'vehicle_carousel',
            'Carosello veicoli',
            'Configura il carosello veicoli utilizzabile nelle pagine del sito per mostrare selezioni dinamiche.',
            'Struttura pronta'
        );
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact"><div class="gpo-surface__eyebrow">Componente dinamico</div><h2>Configurazione centralizzata pronta</h2><p>Questa sezione resta volutamente leggera in attesa di regole piu avanzate di selezione, priorita e playlist veicoli per singolo blocco.</p></div>';
        self::component_section_end();

        self::component_section_start(
            'vehicle_grid',
            'Griglia veicoli',
            'Configura la griglia veicoli per la visualizzazione ordinata delle card all interno delle pagine.',
            'Struttura pronta'
        );
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact"><div class="gpo-surface__eyebrow">Uso editoriale</div><h2>Base pronta</h2><p>La resa visuale e i campi visibili continuano a vivere nei blocchi Gutenberg. Qui prepariamo il punto centrale per futuri override di comportamento globale.</p></div>';
        self::component_section_end();

        echo '<div class="gpo-form-submit">';
        submit_button('Salva configurazione componenti', 'primary', 'submit', false);
        echo '</div>';
        echo '</form>';
        self::render_page_end();
    }

    public static function engagement_page() {
        $settings = self::get_settings();
        $engagement = isset($settings['engagement']) && is_array($settings['engagement']) ? $settings['engagement'] : GPO_Engagement::default_settings();
        $vehicle_options = self::vehicle_options();
        $brand_options = self::brand_options();
        $rules = isset($engagement['rules']) && is_array($engagement['rules']) ? array_values($engagement['rules']) : [];
        $rule_rows = max(3, count($rules) + 1);

        self::render_page_start(
            'engagement',
            'Engagement',
            'Gestisci promo commerciali e scontistiche informative da mostrare sui veicoli del sito con regole pianificate, colore globale e priorità centralizzata.',
            [
                ['label' => 'Apri configurazione componenti', 'url' => admin_url('admin.php?page=gpo-components')],
                ['label' => 'Sincronizza adesso', 'url' => admin_url('admin-post.php?action=gpo_run_sync&_wpnonce=' . wp_create_nonce('gpo_run_sync')), 'variant' => 'secondary'],
            ],
            ['Promo programmate', 'Colore globale promo']
        );
        self::admin_notices_from_query();

        echo '<form class="gpo-api-shell" method="post" action="options.php">';
        settings_fields('gpo_api_group');

        echo '<section class="gpo-surface-grid gpo-surface-grid--connections">';
        echo '<article class="gpo-surface">';
        echo '<div class="gpo-surface__eyebrow">Colore promozionale</div>';
        echo '<h2>Identità visiva promo</h2>';
        echo '<p>Questo colore governa badge, prezzo scontato, evidenze promo e richiami commerciali in tutto il frontend del plugin.</p>';
        echo '<label class="gpo-field gpo-field--color">';
        echo '<span class="gpo-field__label">Colore globale promo</span>';
        echo '<input type="color" name="gpo_settings[engagement][promo_color]" value="' . esc_attr($engagement['promo_color'] ?? '#dc2626') . '" class="gpo-color-field" />';
        echo '<span class="gpo-field__description">Colore di default: rosso. Viene applicato a badge, prezzi evidenziati e testi promo.</span>';
        echo '</label>';
        echo '</article>';
        echo '<article class="gpo-surface gpo-surface--accent">';
        echo '<div class="gpo-surface__eyebrow">Priorità applicazione</div>';
        echo '<h2>Ordine di precedenza promo</h2>';
        echo '<ol class="gpo-flow-list">';
        echo '<li>Promo veicolo singolo</li>';
        echo '<li>Promo su veicoli selezionati</li>';
        echo '<li>Promo per marca</li>';
        echo '</ol>';
        echo '<p>Se due promo hanno la stessa priorita, prevale quella salvata piu in alto nella lista.</p>';
        echo '</article>';
        echo '</section>';

        echo '<section class="gpo-admin-rule-list">';
        echo '<h2 class="gpo-page-subtitle">Regole promozionali</h2>';
        echo '<p class="gpo-field__description">Puoi creare promo per singolo veicolo, per gruppi selezionati oppure per una o piu marche. Le promo sono solo informative e commerciali: non attivano alcun acquisto online.</p>';

        for ($i = 0; $i < $rule_rows; $i++) {
            $rule = $rules[$i] ?? [
                'id' => '',
                'title' => '',
                'target_type' => 'vehicle',
                'vehicle_id' => 0,
                'vehicle_ids' => [],
                'brands' => [],
                'discount_type' => 'fixed',
                'value' => '',
                'start_date' => '',
                'start_time' => '',
                'end_date' => '',
                'end_time' => '',
                'promo_text' => '',
                'active' => 1,
            ];
            echo '<div class="gpo-admin-rule-card gpo-promo-rule" data-rule-target="' . esc_attr($rule['target_type']) . '">';
            echo '<input type="hidden" name="gpo_settings[engagement][rules][' . $i . '][id]" value="' . esc_attr($rule['id']) . '" />';
            echo '<div class="gpo-field-grid gpo-field-grid--triple">';
            self::render_setting_field([
                'label' => 'Titolo promo',
                'name' => 'gpo_settings[engagement][rules][' . $i . '][title]',
                'value' => $rule['title'] ?? '',
                'description' => 'Es. Spring Days, Promo pronta consegna, Sconto brand.',
                'placeholder' => 'Titolo promo',
            ]);
            self::render_setting_field([
                'label' => 'Target promo',
                'name' => 'gpo_settings[engagement][rules][' . $i . '][target_type]',
                'value' => $rule['target_type'] ?? 'vehicle',
                'type' => 'select',
                'options' => [
                    'vehicle' => 'Veicolo singolo',
                    'vehicles' => 'Veicoli selezionati',
                    'brands' => 'Marche selezionate',
                ],
                'description' => 'Definisce come il motore promo abbina la regola ai veicoli.',
            ]);
            self::render_setting_field([
                'label' => 'Tipo sconto',
                'name' => 'gpo_settings[engagement][rules][' . $i . '][discount_type]',
                'value' => $rule['discount_type'] ?? 'fixed',
                'type' => 'select',
                'options' => [
                    'fixed' => 'Importo fisso',
                    'percent' => 'Percentuale',
                ],
                'description' => 'Lo sconto viene applicato solo a fini di comunicazione e vetrina.',
            ]);
            echo '</div>';

            echo '<div class="gpo-field-grid gpo-field-grid--triple">';
            self::render_setting_field([
                'label' => 'Valore sconto',
                'name' => 'gpo_settings[engagement][rules][' . $i . '][value]',
                'value' => $rule['value'] ?? '',
                'description' => 'Inserisci il valore numerico. Se percentuale, indica solo il numero senza simbolo %.',
                'placeholder' => '1000 oppure 12',
            ]);
            self::render_setting_field([
                'label' => 'Testo promo breve',
                'name' => 'gpo_settings[engagement][rules][' . $i . '][promo_text]',
                'value' => $rule['promo_text'] ?? '',
                'description' => 'Testo breve mostrato come richiamo commerciale in card e scheda veicolo.',
                'placeholder' => 'Prezzo promo fino a fine mese',
            ]);
            self::render_setting_field([
                'label' => 'Attiva promo',
                'name' => 'gpo_settings[engagement][rules][' . $i . '][active]',
                'value' => !empty($rule['active']),
                'type' => 'checkbox',
                'description' => 'Le promo disattive restano salvate ma non vengono applicate.',
            ]);
            echo '</div>';

            echo '<div class="gpo-field-grid gpo-field-grid--target">';
            self::render_setting_field([
                'label' => 'Veicolo singolo',
                'name' => 'gpo_settings[engagement][rules][' . $i . '][vehicle_id]',
                'value' => $rule['vehicle_id'] ?? 0,
                'type' => 'select',
                'options' => $vehicle_options,
                'description' => 'Usato quando il target e Veicolo singolo.',
                'classes' => 'gpo-rule-target gpo-rule-target--vehicle',
            ]);
            echo '<label class="gpo-field gpo-rule-target gpo-rule-target--vehicles"><span class="gpo-field__label">Veicoli selezionati</span><select multiple size="8" name="gpo_settings[engagement][rules][' . $i . '][vehicle_ids][]" class="gpo-multi-select">';
            foreach ($vehicle_options as $vehicle_id => $label) {
                if ($vehicle_id === 0) {
                    continue;
                }
                echo '<option value="' . esc_attr((string) $vehicle_id) . '" ' . selected(in_array($vehicle_id, (array) ($rule['vehicle_ids'] ?? []), true), true, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select><span class="gpo-field__description">Usato quando il target e Veicoli selezionati.</span></label>';
            echo '<label class="gpo-field gpo-rule-target gpo-rule-target--brands"><span class="gpo-field__label">Marche selezionate</span><select multiple size="8" name="gpo_settings[engagement][rules][' . $i . '][brands][]" class="gpo-multi-select">';
            foreach ($brand_options as $brand_key => $brand_label) {
                echo '<option value="' . esc_attr($brand_key) . '" ' . selected(in_array($brand_key, (array) ($rule['brands'] ?? []), true), true, false) . '>' . esc_html($brand_label) . '</option>';
            }
            echo '</select><span class="gpo-field__description">Usato quando il target e Marche selezionate.</span></label>';
            echo '</div>';

            self::datetime_row_markup('gpo_settings[engagement][rules][' . $i . ']', $rule);
            echo '</div>';
        }

        echo '</section>';
        echo '<div class="gpo-form-submit">';
        submit_button('Salva Engagement', 'primary', 'submit', false);
        echo '</div>';
        echo '</form>';
        self::render_page_end();
    }


    public static function components_hub_page() {
        $settings = self::get_settings();
        $components = isset($settings['components']) && is_array($settings['components']) ? $settings['components'] : [];
        $components = wp_parse_args($components, self::default_component_settings());
        $vehicle_records = self::vehicle_records();
        $brand_options = self::brand_options();
        $lead_email = self::configured_lead_email();
        $lead_whatsapp = self::configured_whatsapp_number();
        $header_options = self::vehicle_page_header_options();
        $selected_header = sanitize_key((string) ($settings['style']['single_header'] ?? 'default'));
        $featured_map = array_column($vehicle_records, 'label', 'id');
        $featured_summary = !empty($components['featured_vehicle']['vehicle_id']) && isset($featured_map[$components['featured_vehicle']['vehicle_id']]) ? $featured_map[$components['featured_vehicle']['vehicle_id']] : 'Fallback automatico';
        $brand_mode_labels = [
            'inventory' => 'Solo marchi in stock',
            'all' => 'Tutti i marchi generali',
            'manual' => 'Marchi scelti manualmente',
        ];
        $brand_summary = $brand_mode_labels[$components['brand_banner']['mode'] ?? 'inventory'] ?? 'Solo marchi in stock';
        $showcase_summary = !empty($components['showcase_carousel']['vehicle_ids']) ? count((array) $components['showcase_carousel']['vehicle_ids']) . ' veicoli in vetrina' : 'Fallback automatico';
        if ($lead_email && $lead_whatsapp) {
            $lead_summary = 'Email e WhatsApp configurati';
        } elseif ($lead_email) {
            $lead_summary = 'Email configurata';
        } elseif ($lead_whatsapp) {
            $lead_summary = 'WhatsApp configurato';
        } else {
            $lead_summary = 'Da configurare';
        }
        $header_summary = $header_options[$selected_header] ?? ($header_options['default'] ?? 'Header predefinito del tema');

        self::render_page_start(
            'components',
            'Configurazione componenti',
            'Gestisci da un unico pannello i componenti più strategici del sito: veicolo in evidenza, banner marchi e carosello vetrina. La selezione veicoli è più rapida e la programmazione è pensata per crescere senza limiti.',
            [
                ['label' => 'Apri Engagement', 'url' => admin_url('admin.php?page=gpo-engagement')],
                ['label' => 'Sincronizza adesso', 'url' => admin_url('admin-post.php?action=gpo_run_sync&_wpnonce=' . wp_create_nonce('gpo_run_sync')), 'variant' => 'secondary'],
            ],
            ['3 componenti chiave', 'Programmazione illimitata']
        );
        self::admin_notices_from_query();

        echo '<form class="gpo-api-shell" method="post" action="options.php">';
        settings_fields('gpo_api_group');

        self::component_section_start('featured_vehicle', 'Veicolo in evidenza', 'Configura il veicolo principale da mettere in risalto sul sito e pianifica eventuali sostituzioni future.', $featured_summary);
        echo '<div class="gpo-surface-grid gpo-surface-grid--connections">';
        echo '<div class="gpo-surface gpo-surface--compact"><div class="gpo-surface__eyebrow">Selezione corrente</div><h2>Veicolo principale</h2>';
        self::render_vehicle_picker([
            'label' => 'Veicolo principale',
            'name' => 'gpo_settings[components][featured_vehicle][vehicle_id]',
            'selected' => [$components['featured_vehicle']['vehicle_id'] ?? 0],
            'multiple' => false,
            'records' => $vehicle_records,
            'description' => 'Ricerca live e selezione diretta del veicolo da mettere in evidenza.',
        ]);
        echo '</div>';
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact"><div class="gpo-surface__eyebrow">Logica runtime</div><h2>Subentro automatico</h2><p>Quando la finestra del veicolo attuale termina, il plugin verifica la griglia di programmazione in ordine crescente e attiva automaticamente il primo slot valido.</p></div>';
        echo '</div>';
        self::datetime_row_markup('gpo_settings[components][featured_vehicle]', $components['featured_vehicle']);
        echo '<div class="gpo-data-grid-card">';
        echo '<div class="gpo-data-grid-card__head"><div><h3>Programmazione evidenza</h3><p class="gpo-field__description">Aggiungi slot illimitati: ogni riga rappresenta un veicolo futuro con la sua finestra temporale.</p></div><button type="button" class="button button-secondary gpo-repeatable__add" data-target="#gpo-featured-queue-grid" data-template="#gpo-featured-queue-template">+ Aggiungi slot</button></div>';
        echo '<div id="gpo-featured-queue-grid" class="gpo-data-grid-collection" data-next-index="' . esc_attr((string) count((array) ($components['featured_vehicle']['queue'] ?? []))) . '">';
        if (!empty($components['featured_vehicle']['queue'])) {
            foreach (array_values((array) $components['featured_vehicle']['queue']) as $i => $row) {
                self::render_featured_schedule_row($i, $row, $vehicle_records);
            }
        } else {
            echo '<div class="gpo-empty-state"><strong>Nessuno slot futuro</strong><span>Puoi partire dal veicolo principale e aggiungere rotazioni solo quando ti servono.</span></div>';
        }
        echo '</div>';
        echo '<template id="gpo-featured-queue-template">';
        ob_start();
        self::render_featured_schedule_row('__INDEX__', [], $vehicle_records);
        $featured_template = ob_get_clean();
        echo str_replace('__INDEX__', '__INDEX__', $featured_template);
        echo '</template>';
        echo '</div>';
        self::component_section_end();

        self::component_section_start('brand_banner', 'Banner marchi', 'Configura quali marchi mostrare nel banner orizzontale e come presentarli all utente.', $brand_summary);
        echo '<div class="gpo-field-grid">';
        self::render_setting_field([
            'label' => 'Modalità marchi',
            'name' => 'gpo_settings[components][brand_banner][mode]',
            'value' => $components['brand_banner']['mode'] ?? 'inventory',
            'type' => 'select',
            'options' => [
                'inventory' => 'Mostra solo i marchi presenti nel parco auto disponibile',
                'all' => 'Mostra tutti i marchi generali del plugin',
                'manual' => 'Mostra solo i marchi selezionati manualmente',
            ],
            'description' => 'La modalità manuale usa la libreria generale marchi del plugin, non solo quelli già presenti nei veicoli.',
            'classes' => 'gpo-brand-mode-field',
        ]);
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact"><div class="gpo-surface__eyebrow">Brand registry</div><h2>Libreria marchi condivisa</h2><p>Se scegli Tutti, mostriamo l intera libreria locale del plugin. Se scegli Manuale, usi la stessa libreria con una selezione esplicita e controllata.</p></div>';
        echo '</div>';
        echo '<div class="gpo-brand-picker" data-brand-mode="' . esc_attr($components['brand_banner']['mode'] ?? 'inventory') . '"><div class="gpo-brand-picker__manual"><h3>Marchi selezionati manualmente</h3><div class="gpo-checkbox-grid">';
        foreach ($brand_options as $key => $label) {
            $checked = in_array($key, (array) ($components['brand_banner']['selected_brands'] ?? []), true);
            echo '<label class="gpo-checkbox-card"><input type="checkbox" name="gpo_settings[components][brand_banner][selected_brands][]" value="' . esc_attr($key) . '" ' . checked($checked, true, false) . ' /> <span>' . esc_html($label) . '</span></label>';
        }
        echo '</div></div></div>';
        self::component_section_end();

        self::component_section_start('showcase_carousel', 'Carosello vetrina', 'Gestisci i veicoli da mostrare nella vetrina dinamica e pianifica le future rotazioni.', $showcase_summary);
        echo '<div class="gpo-surface-grid gpo-surface-grid--connections">';
        echo '<div class="gpo-surface gpo-surface--compact"><div class="gpo-surface__eyebrow">Modalità manuale rapida</div><h2>Veicoli attivi in vetrina</h2>';
        self::render_vehicle_picker([
            'label' => 'Vetrina corrente',
            'name' => 'gpo_settings[components][showcase_carousel][vehicle_ids][]',
            'selected' => $components['showcase_carousel']['vehicle_ids'] ?? [],
            'multiple' => true,
            'records' => $vehicle_records,
            'description' => 'Questa selezione è la stessa alimentata dal checkbox rapido In vetrina nella lista veicoli.',
        ]);
        echo '</div>';
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact"><div class="gpo-surface__eyebrow">Coda dinamica</div><h2>Rotazione per gruppi</h2><p>Ogni slot può contenere un set diverso di veicoli. Se ci sono finestre future attive, la programmazione prevale sulla selezione manuale senza cancellarla.</p></div>';
        echo '</div>';
        self::datetime_row_markup('gpo_settings[components][showcase_carousel]', $components['showcase_carousel']);
        echo '<div class="gpo-data-grid-card">';
        echo '<div class="gpo-data-grid-card__head"><div><h3>Programmazione vetrina</h3><p class="gpo-field__description">Pianifica slot illimitati con gruppi diversi di veicoli, finestre di attivazione e ordine di subentro.</p></div><button type="button" class="button button-secondary gpo-repeatable__add" data-target="#gpo-showcase-queue-grid" data-template="#gpo-showcase-queue-template">+ Aggiungi slot</button></div>';
        echo '<div id="gpo-showcase-queue-grid" class="gpo-data-grid-collection" data-next-index="' . esc_attr((string) count((array) ($components['showcase_carousel']['queue'] ?? []))) . '">';
        if (!empty($components['showcase_carousel']['queue'])) {
            foreach (array_values((array) $components['showcase_carousel']['queue']) as $i => $row) {
                self::render_showcase_schedule_row($i, $row, $vehicle_records);
            }
        } else {
            echo '<div class="gpo-empty-state"><strong>Nessuna rotazione futura</strong><span>Finché non aggiungi slot programmati, la vetrina usa la selezione manuale o il fallback automatico.</span></div>';
        }
        echo '</div>';
        echo '<template id="gpo-showcase-queue-template">';
        ob_start();
        self::render_showcase_schedule_row('__INDEX__', [], $vehicle_records);
        $showcase_template = ob_get_clean();
        echo str_replace('__INDEX__', '__INDEX__', $showcase_template);
        echo '</template>';
        echo '</div>';
        self::component_section_end();

        self::component_section_start('lead_requests', 'Richieste informazioni', 'Configura i recapiti che ricevono le richieste inviate dai visitatori dalle schede veicolo.', $lead_summary);
        echo '<div class="gpo-surface-grid gpo-surface-grid--connections">';
        echo '<div class="gpo-surface gpo-surface--compact">';
        echo '<div class="gpo-surface__eyebrow">Recapito commerciale</div><h2>Email destinataria</h2>';
        self::render_setting_field([
            'label' => 'Email destinataria',
            'name' => 'gpo_settings[components][lead_requests][recipient_email]',
            'value' => $components['lead_requests']['recipient_email'] ?? '',
            'type' => 'email',
            'placeholder' => 'contatti@concessionaria.it',
            'description' => 'Le richieste inviate dalle schede veicolo verranno recapitate a questo indirizzo.',
        ]);
        self::render_setting_field([
            'label' => 'Numero WhatsApp Business',
            'name' => 'gpo_settings[components][lead_requests][whatsapp_number]',
            'value' => $components['lead_requests']['whatsapp_number'] ?? '',
            'type' => 'text',
            'placeholder' => '393XXXXXXXXX',
            'description' => 'Numero WhatsApp che riceverà le richieste dei clienti.',
        ]);
        echo '</div>';
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact"><div class="gpo-surface__eyebrow">Stato attuale</div><h2>' . esc_html(($lead_email || $lead_whatsapp) ? 'Recapiti configurati' : 'Recapiti da completare') . '</h2>';
        echo '<p>' . wp_kses_post(($lead_email ? '&#10003;' : '&#9888;') . ' ' . esc_html($lead_email ? 'Email destinataria configurata.' : 'Email destinataria non configurata.')) . '</p>';
        echo '<p>' . wp_kses_post(($lead_whatsapp ? '&#10003;' : '&#9888;') . ' ' . esc_html($lead_whatsapp ? 'Numero WhatsApp Business configurato.' : 'Numero WhatsApp Business non configurato.')) . '</p>';
        echo '<p>' . esc_html($lead_email ? 'Il modulo delle schede veicolo può inviare le richieste via email al recapito configurato.' : 'Configura almeno l email destinataria per attivare l invio delle richieste informative dal sito.') . '</p></div>';
        echo '</div>';
        self::component_section_end();

        self::component_section_start('vehicle_page', 'Scheda veicolo', 'Scegli quale intestazione usare nella pagina veicolo e mantieni la scheda coerente con la navigazione del sito.', $header_summary);
        echo '<div class="gpo-surface-grid gpo-surface-grid--connections">';
        echo '<div class="gpo-surface gpo-surface--compact">';
        echo '<div class="gpo-surface__eyebrow">Header attivo</div><h2>Intestazione della scheda</h2>';
        self::render_setting_field([
            'label' => 'Header da usare',
            'name' => 'gpo_settings[style][single_header]',
            'value' => $selected_header,
            'type' => 'select',
            'options' => $header_options,
            'description' => 'Nei block theme usiamo il template part header selezionato. Nei temi classici usiamo il file header corrispondente quando esiste.',
        ]);
        echo '</div>';
        echo '<div class="gpo-surface gpo-surface--accent gpo-surface--compact"><div class="gpo-surface__eyebrow">Compatibilità</div><h2>Come viene applicato</h2><p>Se il tema espone più header, la scheda veicolo usa quello scelto. Se l opzione selezionata non è disponibile nel tema attivo, il plugin torna automaticamente all header predefinito.</p></div>';
        echo '</div>';
        self::component_section_end();

        echo '<div class="gpo-form-submit">';
        submit_button('Salva configurazione componenti', 'primary', 'submit', false);
        echo '</div>';
        echo '</form>';
        self::render_page_end();
    }

    public static function engagement_hub_page() {
        $settings = self::get_settings();
        $engagement = isset($settings['engagement']) && is_array($settings['engagement']) ? $settings['engagement'] : GPO_Engagement::default_settings();
        $vehicle_records = self::vehicle_records();
        $brand_options = self::brand_options();
        $rules = isset($engagement['rules']) && is_array($engagement['rules']) ? array_values($engagement['rules']) : [];

        self::render_page_start(
            'engagement',
            'Engagement',
            'Gestisci promo commerciali e scontistiche informative da mostrare sui veicoli del sito con una lista più leggibile, selezione veicoli più intuitiva e una griglia pronta per crescere.',
            [
                ['label' => 'Apri configurazione componenti', 'url' => admin_url('admin.php?page=gpo-components')],
                ['label' => 'Sincronizza adesso', 'url' => admin_url('admin-post.php?action=gpo_run_sync&_wpnonce=' . wp_create_nonce('gpo_run_sync')), 'variant' => 'secondary'],
            ],
            ['Promo programmate', 'Colore globale promo']
        );
        self::admin_notices_from_query();

        echo '<form class="gpo-api-shell" method="post" action="options.php">';
        settings_fields('gpo_api_group');
        echo '<section class="gpo-surface-grid gpo-surface-grid--connections">';
        echo '<article class="gpo-surface"><div class="gpo-surface__eyebrow">Colore promozionale</div><h2>Identità visiva promo</h2><p>Questo colore governa badge, prezzo scontato, evidenze promo e richiami commerciali in tutto il frontend del plugin.</p><label class="gpo-field gpo-field--color"><span class="gpo-field__label">Colore globale promo</span><input type="color" name="gpo_settings[engagement][promo_color]" value="' . esc_attr($engagement['promo_color'] ?? '#dc2626') . '" class="gpo-color-field" /><span class="gpo-field__description">Colore di default: rosso. Viene applicato a badge, prezzi evidenziati e testi promo.</span></label></article>';
        echo '<article class="gpo-surface gpo-surface--accent"><div class="gpo-surface__eyebrow">Priorità applicazione</div><h2>Ordine di precedenza promo</h2><ol class="gpo-flow-list"><li>Promo veicolo singolo</li><li>Promo su veicoli selezionati</li><li>Promo per marca</li></ol><p>Se due promo hanno la stessa priorità, prevale quella salvata più in alto nella lista.</p></article>';
        echo '</section>';

        echo '<section class="gpo-data-grid-card">';
        echo '<div class="gpo-data-grid-card__head"><div><h2 class="gpo-page-subtitle">Regole promozionali</h2><p class="gpo-field__description">Crea promo per singolo veicolo, per gruppi selezionati oppure per una o più marche. Le promo restano informative e commerciali: nessun acquisto online.</p></div><button type="button" class="button button-primary gpo-repeatable__add" data-target="#gpo-promo-grid" data-template="#gpo-promo-template">+ Nuova promo</button></div>';
        echo '<div id="gpo-promo-grid" class="gpo-admin-rule-list" data-next-index="' . esc_attr((string) count($rules)) . '">';
        if (!empty($rules)) {
            foreach ($rules as $i => $rule) {
                self::render_promo_rule_card($i, $rule, $vehicle_records, $brand_options);
            }
        } else {
            echo '<div class="gpo-empty-state"><strong>Nessuna promozione configurata</strong><span>Crea una nuova regola e scegli se applicarla a un veicolo, a un gruppo oppure a una marca.</span></div>';
        }
        echo '</div>';
        echo '<template id="gpo-promo-template">';
        ob_start();
        self::render_promo_rule_card('__INDEX__', [], $vehicle_records, $brand_options);
        $promo_template = ob_get_clean();
        echo str_replace('__INDEX__', '__INDEX__', $promo_template);
        echo '</template>';
        echo '</section>';

        echo '<div class="gpo-form-submit">';
        submit_button('Salva Engagement', 'primary', 'submit', false);
        echo '</div>';
        echo '</form>';
        self::render_page_end();
    }

    public static function logs_page() {
        $logs = GPO_Logger::all();
        echo '<div class="wrap gpo-admin-wrap"><h1>Log e diagnostica</h1>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin-post.php?action=gpo_clear_logs&_wpnonce=' . wp_create_nonce('gpo_clear_logs'))) . '">Svuota log</a></p>';
        if (empty($logs)) {
            echo '<p>Nessun log disponibile.</p></div>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>Data</th><th>Messaggio</th><th>Contesto</th></tr></thead><tbody>';
        foreach ($logs as $log) {
            echo '<tr><td>' . esc_html($log['time']) . '</td><td>' . esc_html($log['message']) . '</td><td><code>' . esc_html(wp_json_encode($log['context'])) . '</code></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function guide_page() {
        echo '<div class="wrap gpo-admin-wrap"><h1>Guida rapida</h1>';
        echo '<ol>';
        echo '<li>Collega il tuo account in <strong>Connessioni API</strong> con email e password ParkPlatform.</li>';
        echo '<li>Esegui <strong>Test connessione</strong> e poi <strong>Sincronizza adesso</strong>.</li>';
        echo '<li>Apri <strong>Configurazione componenti</strong> per definire veicolo in evidenza, carosello vetrina e banner marchi.</li>';
        echo '<li>Usa <strong>Engagement</strong> per pianificare promo, badge e prezzi barrati informativi.</li>';
        echo '<li>Costruisci cataloghi e componenti direttamente dal WordPress Editor con i blocchi GestPark.</li>';
        echo '<li>Controlla dalla <strong>Dashboard</strong> lo stato ParkPlatform e gli aggiornamenti plugin.</li>';
        echo '</ol>';
        echo '</div>';
    }

    protected static function input_row($label, $name, $value, $description = '') {
        echo '<tr><th scope="row"><label>' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</td></tr>';
    }

    protected static function select_row($label, $name, $value, $options) {
        echo '<tr><th scope="row"><label>' . esc_html($label) . '</label></th><td><select name="' . esc_attr($name) . '">';
        foreach ($options as $key => $option_label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select></td></tr>';
    }

    public static function manual_showcase_vehicle_ids() {
        $settings = self::get_settings();
        return array_values(array_filter(array_map('absint', (array) ($settings['components']['showcase_carousel']['vehicle_ids'] ?? []))));
    }

    protected static function set_manual_showcase_vehicle_state($post_id, $enabled) {
        $settings = self::get_settings();
        $ids = array_values(array_filter(array_map('absint', (array) ($settings['components']['showcase_carousel']['vehicle_ids'] ?? []))));

        if ($enabled) {
            if (!in_array($post_id, $ids, true)) {
                $ids[] = $post_id;
            }
        } else {
            $ids = array_values(array_filter($ids, function ($id) use ($post_id) {
                return (int) $id !== (int) $post_id;
            }));
        }

        $settings['components']['showcase_carousel']['vehicle_ids'] = $ids;
        update_option('gpo_settings', $settings);
        update_post_meta($post_id, '_gpo_featured', $enabled ? '1' : '0');
    }

    protected static function admin_notices_from_query() {
        if (empty($_GET['gpo_notice'])) {
            return;
        }
        $notice = sanitize_text_field(wp_unslash($_GET['gpo_notice']));
        $type = $notice === 'error' ? 'notice-error' : 'notice-success';
        $message = !empty($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : 'Operazione completata.';
        echo '<div class="notice ' . esc_attr($type) . '"><p>' . esc_html($message) . '</p></div>';
    }

    public static function handle_toggle_showcase_vehicle() {
        check_ajax_referer('gpo_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permessi insufficienti.'], 403);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $enabled = !empty($_POST['featured']);

        if ($post_id < 1 || get_post_type($post_id) !== 'gpo_vehicle') {
            wp_send_json_error(['message' => 'Veicolo non valido.'], 400);
        }

        self::set_manual_showcase_vehicle_state($post_id, $enabled);

        wp_send_json_success([
            'featured' => $enabled,
            'label' => $enabled ? 'In vetrina' : 'Fuori vetrina',
        ]);
    }

    public static function handle_test_connection() {
        if (!current_user_can('manage_options') || !check_admin_referer('gpo_test_connection')) {
            wp_die('Operazione non consentita.');
        }
        $result = GPO_API_Client::test_connection();
        $notice = is_wp_error($result) ? 'error' : 'success';
        $message = is_wp_error($result) ? $result->get_error_message() : 'Connessione riuscita. Elementi letti: ' . $result['count'];
        update_option('gpo_last_connection_check', [
            'status' => is_wp_error($result) ? 'error' : 'success',
            'time' => current_time('mysql'),
            'message' => $message,
        ]);
        wp_safe_redirect(admin_url('admin.php?page=gpo-api&gpo_notice=' . $notice . '&message=' . rawurlencode($message)));
        exit;
    }

    public static function handle_run_sync() {
        if (!current_user_can('manage_options') || !check_admin_referer('gpo_run_sync')) {
            wp_die('Operazione non consentita.');
        }
        $result = GPO_Sync_Manager::sync();
        $notice = is_wp_error($result) ? 'error' : 'success';
        $message = is_wp_error($result) ? $result->get_error_message() : 'Sincronizzazione completata. Veicoli processati: ' . $result['processed'];
        update_option('gpo_last_connection_check', [
            'status' => is_wp_error($result) ? 'error' : 'success',
            'time' => current_time('mysql'),
            'message' => is_wp_error($result) ? $message : 'Ultima sincronizzazione completata con successo.',
        ]);
        wp_safe_redirect(admin_url('admin.php?page=gestpark-online&gpo_notice=' . $notice . '&message=' . rawurlencode($message)));
        exit;
    }

    public static function handle_disconnect_parkplatform() {
        if (!current_user_can('manage_options') || !check_admin_referer('gpo_disconnect_parkplatform')) {
            wp_die('Operazione non consentita.');
        }

        $settings = self::get_settings();
        $settings['api']['connection_mode'] = GPO_API_Client::MODE_GESTPARK_AUTO;
        $settings['api']['manual_format'] = GPO_API_Client::MANUAL_FORMAT_GESTPARK;
        $settings['api']['gestpark_username'] = '';
        $settings['api']['gestpark_password'] = '';
        $settings['api']['token'] = '';
        $settings['api']['api_key'] = '';
        update_option('gpo_settings', $settings);
        delete_option('gpo_last_connection_check');

        wp_safe_redirect(admin_url('admin.php?page=gpo-api&gpo_notice=success&message=' . rawurlencode('Account ParkPlatform scollegato.')));
        exit;
    }

    public static function handle_clear_logs() {
        if (!current_user_can('manage_options') || !check_admin_referer('gpo_clear_logs')) {
            wp_die('Operazione non consentita.');
        }
        GPO_Logger::clear();
        wp_safe_redirect(admin_url('admin.php?page=gpo-logs'));
        exit;
    }

    public static function handle_github_refresh() {
        if (!current_user_can('manage_options') || !check_admin_referer('gpo_github_refresh')) {
            wp_die('Operazione non consentita.');
        }

        if (class_exists('GPO_GitHub_Updater')) {
            GPO_GitHub_Updater::clear_cache();
        }

        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }

        wp_safe_redirect(admin_url('admin.php?page=gestpark-online&gpo_notice=success&message=' . rawurlencode('Controllo aggiornamenti GitHub eseguito.')));
        exit;
    }

    public static function handle_save_showcase() {
        if (!current_user_can('manage_options') || !check_admin_referer('gpo_save_showcase')) {
            wp_die('Operazione non consentita.');
        }
        $showcase = isset($_POST['showcase']) ? (array) wp_unslash($_POST['showcase']) : [];
        foreach ($showcase as $post_id => $data) {
            $post_id = absint($post_id);
            update_post_meta($post_id, '_gpo_featured', isset($data['featured']) ? '1' : '0');
            update_post_meta($post_id, '_gpo_featured_order', isset($data['order']) ? absint($data['order']) : 0);
            update_post_meta($post_id, '_gpo_featured_from', isset($data['from']) ? sanitize_text_field($data['from']) : '');
            update_post_meta($post_id, '_gpo_featured_to', isset($data['to']) ? sanitize_text_field($data['to']) : '');
            update_post_meta($post_id, '_gpo_badge', isset($data['badge']) ? sanitize_text_field($data['badge']) : '');
        }
        wp_safe_redirect(admin_url('admin.php?page=gpo-showcase'));
        exit;
    }
}
