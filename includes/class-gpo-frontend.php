<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Frontend {
    protected static $template_vehicle_id = 0;
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_shortcode('gestpark_vehicle_grid', [__CLASS__, 'vehicle_grid_shortcode']);
        add_shortcode('gestpark_featured_vehicle', [__CLASS__, 'featured_vehicle_shortcode']);
        add_shortcode('gestpark_featured_carousel', [__CLASS__, 'featured_carousel_shortcode']);
        add_shortcode('gestpark_vehicle_filters', [__CLASS__, 'vehicle_filters_shortcode']);
        add_shortcode('gestpark_vehicle_catalog', [__CLASS__, 'vehicle_catalog_shortcode']);
        add_shortcode('gestpark_brand_carousel', [__CLASS__, 'brand_carousel_shortcode']);
        add_shortcode('gestpark_vehicle_search', [__CLASS__, 'vehicle_search_shortcode']);
        add_action('wp_ajax_gpo_live_search', [__CLASS__, 'ajax_live_search']);
        add_action('wp_ajax_nopriv_gpo_live_search', [__CLASS__, 'ajax_live_search']);
        add_action('admin_post_gpo_vehicle_lead', [__CLASS__, 'handle_vehicle_lead_submission']);
        add_action('admin_post_nopriv_gpo_vehicle_lead', [__CLASS__, 'handle_vehicle_lead_submission']);
        add_filter('template_include', [__CLASS__, 'single_template']);
    }

    public static function assets() {
        wp_register_style('gpo-public', GPO_PLUGIN_URL . 'public/assets/css/gpo-public.css', [], gpo_asset_version('public/assets/css/gpo-public.css'));
        wp_register_script('gpo-carousel', GPO_PLUGIN_URL . 'public/assets/js/gpo-carousel.js', [], gpo_asset_version('public/assets/js/gpo-carousel.js'), true);
        wp_register_script('gpo-live-search', GPO_PLUGIN_URL . 'public/assets/js/gpo-live-search.js', [], gpo_asset_version('public/assets/js/gpo-live-search.js'), true);
        wp_register_script('gpo-vehicle-gallery', GPO_PLUGIN_URL . 'public/assets/js/gpo-vehicle-gallery.js', [], gpo_asset_version('public/assets/js/gpo-vehicle-gallery.js'), true);

        $settings = self::display_settings();
        $style = $settings['style'];
        $css = ':root{' .
            '--gpo-primary:' . esc_attr($style['primary_color'] ?? '#111827') . ';' .
            '--gpo-accent:' . esc_attr($style['accent_color'] ?? '#dc2626') . ';' .
            '--gpo-bg:' . esc_attr($style['card_bg'] ?? '#ffffff') . ';' .
            '--gpo-radius:' . esc_attr($style['radius'] ?? '16px') . ';' .
            '--gpo-title-font:' . esc_attr($style['title_font'] ?? 'inherit') . ';' .
            '--gpo-body-font:' . esc_attr($style['body_font'] ?? 'inherit') . ';' .
            '--gpo-card-gap:' . absint($style['card_gap'] ?? 24) . 'px;' .
            '--gpo-card-padding:' . absint($style['card_padding'] ?? 22) . 'px;' .
            '--gpo-content-max-width:' . absint($style['content_max_width'] ?? 1280) . 'px;' .
            '--gpo-shell-margin-y:' . absint($style['outer_margin_y'] ?? 32) . 'px;' .
            '--gpo-shell-padding-x:' . absint($style['outer_padding_x'] ?? 18) . 'px;' .
            '--gpo-section-gap:' . absint($style['section_gap'] ?? 24) . 'px;' .
            '--gpo-filter-columns:' . max(2, min(6, absint($style['filter_columns'] ?? 5))) . ';' .
            '--gpo-muted:#6b7280;' .
            '--gpo-border:#e5e7eb;' .
            '--gpo-surface:#f8fafc;' .
            '--gpo-shadow:0 16px 40px rgba(15,23,42,.08);' .
        '}';
        wp_add_inline_style('gpo-public', $css);
        wp_localize_script('gpo-live-search', 'gpoSearchData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gpo_live_search'),
        ]);
    }

    public static function display_settings() {
        $defaults = GPO_Admin::default_settings();
        $settings = wp_parse_args(get_option('gpo_settings', []), $defaults);

        if (empty($settings['style']['card_elements']) || !is_array($settings['style']['card_elements'])) {
            $settings['style']['card_elements'] = $defaults['style']['card_elements'];
        }
        if (empty($settings['style']['single_sections']) || !is_array($settings['style']['single_sections'])) {
            $settings['style']['single_sections'] = $defaults['style']['single_sections'];
        }
        if (empty($settings['style']['filter_fields']) || !is_array($settings['style']['filter_fields'])) {
            $settings['style']['filter_fields'] = $defaults['style']['filter_fields'];
        }

        return $settings;
    }

    protected static function lead_email() {
        $settings = self::display_settings();
        $email = sanitize_email((string) ($settings['style']['lead_email'] ?? ''));
        if ($email) {
            return $email;
        }

        return sanitize_email((string) get_option('admin_email'));
    }

    protected static function lead_success_message() {
        $settings = self::display_settings();
        $message = trim((string) ($settings['style']['lead_success_message'] ?? ''));
        if ($message !== '') {
            return $message;
        }

        return 'Richiesta inviata correttamente. Ti ricontatteremo al più presto.';
    }

    protected static function current_request_url() {
        if (!empty($_SERVER['REQUEST_URI'])) {
            return home_url(wp_unslash((string) $_SERVER['REQUEST_URI']));
        }

        return home_url('/');
    }

    public static function lead_notice_markup() {
        $status = isset($_GET['gpo_lead']) ? sanitize_key(wp_unslash($_GET['gpo_lead'])) : '';
        if ($status === '') {
            return '';
        }

        $message = isset($_GET['gpo_lead_message']) ? sanitize_text_field(wp_unslash($_GET['gpo_lead_message'])) : '';
        if ($message === '') {
            $message = $status === 'success'
                ? self::lead_success_message()
                : 'Non è stato possibile inviare la richiesta. Controlla i campi e riprova.';
        }

        $class = $status === 'success' ? 'is-success' : 'is-error';
        return '<div class="gpo-lead-notice ' . esc_attr($class) . '" role="status"><p>' . esc_html($message) . '</p></div>';
    }

    protected static function preferred_menu_location() {
        $locations = (array) get_nav_menu_locations();
        if (empty($locations)) {
            return '';
        }

        foreach (['primary', 'main', 'header', 'menu-1'] as $candidate) {
            if (!empty($locations[$candidate])) {
                return $candidate;
            }
        }

        $keys = array_keys($locations);
        return (string) ($keys[0] ?? '');
    }

    public static function site_navigation_markup() {
        $menu_location = self::preferred_menu_location();
        $menu_markup = '';

        if ($menu_location && has_nav_menu($menu_location)) {
            $menu_markup = wp_nav_menu([
                'theme_location' => $menu_location,
                'container' => false,
                'menu_class' => 'gpo-site-nav__menu',
                'echo' => false,
                'fallback_cb' => false,
            ]);
        }

        $logo = function_exists('get_custom_logo') ? get_custom_logo() : '';
        $brand = $logo ?: '<span class="gpo-site-nav__brand-text">' . esc_html(get_bloginfo('name')) . '</span>';
        $catalog_url = get_post_type_archive_link('gpo_vehicle') ?: home_url('/');

        ob_start();
        echo '<div class="gpo-site-nav-shell">';
        echo '<div class="gpo-site-nav">';
        echo '<a class="gpo-site-nav__brand" href="' . esc_url(home_url('/')) . '">' . $brand . '</a>';
        if ($menu_markup) {
            echo '<nav class="gpo-site-nav__menu-wrap" aria-label="Navigazione sito">' . $menu_markup . '</nav>';
        } else {
            echo '<nav class="gpo-site-nav__menu-wrap" aria-label="Navigazione sito"><ul class="gpo-site-nav__menu"><li><a href="' . esc_url(home_url('/')) . '">Home</a></li><li><a href="' . esc_url($catalog_url) . '">Veicoli</a></li></ul></nav>';
        }
        echo '<a class="gpo-site-nav__cta" href="' . esc_url($catalog_url) . '">Torna al catalogo</a>';
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function lead_form_markup($post_id, $args = []) {
        $post_id = absint($post_id);
        $post_title = $post_id ? get_the_title($post_id) : '';
        $defaults = [
            'title' => 'Richiedi informazioni',
            'text' => 'Compila il modulo per ricevere disponibilità, proposta commerciale e ulteriori dettagli su questo veicolo.',
            'button_label' => 'Invia richiesta',
            'wrapper_class' => 'gpo-side-card',
        ];
        $args = wp_parse_args($args, $defaults);

        $redirect_url = $post_id ? get_permalink($post_id) : self::current_request_url();
        if (!$redirect_url) {
            $redirect_url = self::current_request_url();
        }

        ob_start();
        echo '<aside class="' . esc_attr($args['wrapper_class']) . '" id="richiesta-info">';
        echo '<h3>' . esc_html($args['title']) . '</h3>';
        echo '<p>' . esc_html($args['text']) . '</p>';
        echo self::lead_notice_markup();
        echo '<form class="gpo-lead-form" action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
        echo '<input type="hidden" name="action" value="gpo_vehicle_lead" />';
        echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $post_id) . '" />';
        echo '<input type="hidden" name="redirect_to" value="' . esc_url($redirect_url) . '" />';
        wp_nonce_field('gpo_vehicle_lead_' . $post_id, 'gpo_vehicle_lead_nonce');
        echo '<div class="gpo-lead-grid">';
        echo '<label><span>Nome</span><input type="text" name="gpo_lead[first_name]" required autocomplete="given-name" /></label>';
        echo '<label><span>Cognome</span><input type="text" name="gpo_lead[last_name]" required autocomplete="family-name" /></label>';
        echo '<label><span>Email</span><input type="email" name="gpo_lead[email]" required autocomplete="email" /></label>';
        echo '<label><span>Cellulare</span><input type="tel" name="gpo_lead[phone]" required autocomplete="tel" /></label>';
        echo '</div>';
        echo '<label class="gpo-lead-message"><span>Richiesta</span><textarea name="gpo_lead[message]" rows="5" required placeholder="Vorrei ricevere maggiori informazioni su ' . esc_attr($post_title ?: 'questo veicolo') . '."></textarea></label>';
        echo '<label class="gpo-lead-honeypot" aria-hidden="true"><span>Lascia vuoto</span><input type="text" name="gpo_lead[company]" tabindex="-1" autocomplete="off" /></label>';
        echo '<button class="gpo-button gpo-lead-submit" type="submit">' . esc_html($args['button_label']) . '</button>';
        echo '</form>';
        echo '</aside>';
        return ob_get_clean();
    }

    public static function handle_vehicle_lead_submission() {
        $post_id = absint($_POST['post_id'] ?? 0);
        $redirect = !empty($_POST['redirect_to']) ? esc_url_raw(wp_unslash((string) $_POST['redirect_to'])) : '';
        if (!$redirect) {
            $redirect = $post_id ? get_permalink($post_id) : home_url('/');
        }

        $nonce = isset($_POST['gpo_vehicle_lead_nonce']) ? sanitize_text_field(wp_unslash($_POST['gpo_vehicle_lead_nonce'])) : '';
        if (!$post_id || !wp_verify_nonce($nonce, 'gpo_vehicle_lead_' . $post_id)) {
            wp_safe_redirect(add_query_arg([
                'gpo_lead' => 'error',
                'gpo_lead_message' => 'Sessione non valida. Ricarica la pagina e riprova.',
            ], $redirect));
            exit;
        }

        $lead = isset($_POST['gpo_lead']) && is_array($_POST['gpo_lead']) ? wp_unslash($_POST['gpo_lead']) : [];
        $first_name = sanitize_text_field((string) ($lead['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($lead['last_name'] ?? ''));
        $email = sanitize_email((string) ($lead['email'] ?? ''));
        $phone = sanitize_text_field((string) ($lead['phone'] ?? ''));
        $message = sanitize_textarea_field((string) ($lead['message'] ?? ''));
        $honeypot = trim((string) ($lead['company'] ?? ''));

        if ($honeypot !== '' || $first_name === '' || $last_name === '' || !$email || $phone === '' || $message === '') {
            wp_safe_redirect(add_query_arg([
                'gpo_lead' => 'error',
                'gpo_lead_message' => 'Compila correttamente tutti i campi del modulo.',
            ], $redirect));
            exit;
        }

        $recipient = self::lead_email();
        $vehicle_title = $post_id ? get_the_title($post_id) : 'Veicolo';
        $vehicle_url = $post_id ? get_permalink($post_id) : '';
        $subject = sprintf('Nuova richiesta veicolo: %s', wp_specialchars_decode((string) $vehicle_title, ENT_QUOTES));
        $body = "Nuova richiesta dal sito web\n\n";
        $body .= "Veicolo: " . $vehicle_title . "\n";
        if ($vehicle_url) {
            $body .= "URL veicolo: " . $vehicle_url . "\n";
        }
        $body .= "Nome: " . trim($first_name . ' ' . $last_name) . "\n";
        $body .= "Email: " . $email . "\n";
        $body .= "Cellulare: " . $phone . "\n\n";
        $body .= "Richiesta:\n" . $message . "\n";

        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . trim($first_name . ' ' . $last_name) . ' <' . $email . '>'];
        $sent = wp_mail($recipient, $subject, $body, $headers);

        wp_safe_redirect(add_query_arg([
            'gpo_lead' => $sent ? 'success' : 'error',
            'gpo_lead_message' => $sent ? self::lead_success_message() : 'Invio email non riuscito. Controlla la configurazione del destinatario nel plugin.',
        ], $redirect));
        exit;
    }

    public static function vehicle_grid_shortcode($atts) {
        wp_enqueue_style('gpo-public');
        $settings = self::display_settings();
        $atts = shortcode_atts([
            'featured' => 'no',
            'limit' => 12,
            'columns' => 3,
            'filters' => 'no',
            'orderby' => 'date',
            'order' => 'DESC',
            'show' => '',
            'card_layout' => $settings['style']['card_layout'] ?? 'default',
            'card_gap' => $settings['style']['card_gap'] ?? '24',
            'card_padding' => $settings['style']['card_padding'] ?? '22',
            'content_max_width' => $settings['style']['content_max_width'] ?? '1280',
            'outer_margin_y' => $settings['style']['outer_margin_y'] ?? '32',
            'outer_padding_x' => $settings['style']['outer_padding_x'] ?? '18',
            'section_gap' => $settings['style']['section_gap'] ?? '24',
            'filter_fields' => '',
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
            'button_text_color' => '',
            'primary_button_label' => 'Scheda veicolo',
            'secondary_button_label' => 'Richiedi info',
        ], $atts, 'gestpark_vehicle_grid');

        $query = self::build_vehicle_query($atts);
        $display = self::resolve_card_display($atts);
        $wrapper_style = self::wrapper_style($atts);

        ob_start();
        echo '<div class="gpo-catalog-shell" style="' . esc_attr($wrapper_style) . '">';
        if ($atts['filters'] === 'yes') {
            echo self::render_filter_form($atts['filter_fields']);
        }
        self::render_results_header($query, false);
        echo '<div class="gpo-grid columns-' . absint($atts['columns']) . '">';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                self::render_card(get_the_ID(), $display);
            }
        } else {
            echo '<div class="gpo-empty-state"><h3>Nessun veicolo disponibile</h3><p>Modifica i filtri oppure aggiungi nuovi veicoli dal plugin.</p></div>';
        }
        echo '</div></div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function featured_vehicle_shortcode($atts) {
        wp_enqueue_style('gpo-public');
        $settings = self::display_settings();
        $atts = shortcode_atts([
            'layout' => 'hero',
            'show' => '',
            'card_layout' => $settings['style']['card_layout'] ?? 'default',
            'card_gap' => $settings['style']['card_gap'] ?? '24',
            'card_padding' => $settings['style']['card_padding'] ?? '22',
            'content_max_width' => $settings['style']['content_max_width'] ?? '1280',
            'outer_margin_y' => $settings['style']['outer_margin_y'] ?? '32',
            'outer_padding_x' => $settings['style']['outer_padding_x'] ?? '18',
            'section_gap' => $settings['style']['section_gap'] ?? '24',
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
            'button_text_color' => '',
            'primary_button_label' => 'Scheda veicolo',
            'secondary_button_label' => 'Richiedi info',
        ], $atts, 'gestpark_featured_vehicle');
        $display = self::resolve_card_display($atts);

        $collection = self::featured_vehicle_collection(1);
        $ids = $collection['ids'];
        ob_start();
        if (!empty($ids)) {
            echo '<div class="gpo-featured-single" style="' . esc_attr(self::wrapper_style($atts)) . '">';
            if ($atts['layout'] === 'hero') {
                $display['hero'] = true;
            }
            self::render_card($ids[0], $display);
            echo '</div>';
        } else {
            echo '<div class="gpo-empty-state"><h3>Nessun veicolo disponibile</h3><p>Sincronizza almeno un veicolo reale da ParkPlatform oppure seleziona una vetrina attiva.</p></div>';
        }
        return ob_get_clean();
    }

    public static function featured_carousel_shortcode($atts) {
        wp_enqueue_style('gpo-public');
        wp_enqueue_script('gpo-carousel');
        $settings = self::display_settings();
        $atts = shortcode_atts([
            'limit' => 12,
            'autoplay' => 'yes',
            'interval' => 5000,
            'show' => '',
            'card_layout' => $settings['style']['card_layout'] ?? 'default',
            'card_gap' => $settings['style']['card_gap'] ?? '24',
            'card_padding' => $settings['style']['card_padding'] ?? '22',
            'content_max_width' => $settings['style']['content_max_width'] ?? '1280',
            'outer_margin_y' => $settings['style']['outer_margin_y'] ?? '32',
            'outer_padding_x' => $settings['style']['outer_padding_x'] ?? '18',
            'section_gap' => $settings['style']['section_gap'] ?? '24',
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
            'button_text_color' => '',
            'primary_button_label' => 'Scheda veicolo',
            'secondary_button_label' => 'Richiedi info',
        ], $atts, 'gestpark_featured_carousel');
        $display = self::resolve_card_display($atts);

        $collection = self::featured_vehicle_collection(absint($atts['limit']));
        $ids = $collection['ids'];
        ob_start();
        echo '<div class="gpo-carousel-shell" style="' . esc_attr(self::wrapper_style($atts)) . '">';
        echo '<div class="gpo-section-head"><div><span class="gpo-kicker">Vetrina</span><h2>Veicoli selezionati</h2></div><div class="gpo-carousel-nav"><button class="gpo-carousel-prev" type="button" aria-label="Precedente">' . self::icon_markup('chevron-left') . '</button><button class="gpo-carousel-next" type="button" aria-label="Successivo">' . self::icon_markup('chevron-right') . '</button></div></div>';
        echo '<div class="gpo-carousel" data-gpo-carousel="1" data-autoplay="' . esc_attr($atts['autoplay']) . '" data-interval="' . absint($atts['interval']) . '" data-loop="yes"><div class="gpo-carousel-track">';
        if (!empty($ids)) {
            foreach ($ids as $post_id) {
                echo '<div class="gpo-carousel-slide">';
                self::render_card($post_id, $display);
                echo '</div>';
            }
        } else {
            echo '<div class="gpo-empty-state"><h3>Nessun veicolo disponibile</h3><p>Sincronizza almeno un veicolo reale da ParkPlatform oppure seleziona una vetrina attiva.</p></div>';
        }
        echo '</div><div class="gpo-carousel-dots" aria-hidden="true"></div></div></div>';
        return ob_get_clean();
    }

    public static function vehicle_filters_shortcode() {
        wp_enqueue_style('gpo-public');
        return self::render_filter_form('');
    }

    public static function vehicle_catalog_shortcode($atts) {
        $settings = self::display_settings();
        $atts = shortcode_atts([
            'limit' => 12,
            'columns' => 3,
            'orderby' => 'date',
            'order' => 'DESC',
            'show' => '',
            'card_layout' => $settings['style']['card_layout'] ?? 'default',
            'card_gap' => $settings['style']['card_gap'] ?? '24',
            'card_padding' => $settings['style']['card_padding'] ?? '22',
            'content_max_width' => $settings['style']['content_max_width'] ?? '1280',
            'outer_margin_y' => $settings['style']['outer_margin_y'] ?? '32',
            'outer_padding_x' => $settings['style']['outer_padding_x'] ?? '18',
            'section_gap' => $settings['style']['section_gap'] ?? '24',
            'filter_fields' => '',
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
            'button_text_color' => '',
            'primary_button_label' => 'Scheda veicolo',
            'secondary_button_label' => 'Richiedi info',
        ], $atts, 'gestpark_vehicle_catalog');

        return self::vehicle_grid_shortcode([
            'limit' => $atts['limit'],
            'columns' => $atts['columns'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'filters' => 'yes',
            'show' => $atts['show'],
            'card_layout' => $atts['card_layout'],
            'card_gap' => $atts['card_gap'],
            'card_padding' => $atts['card_padding'],
            'content_max_width' => $atts['content_max_width'],
            'outer_margin_y' => $atts['outer_margin_y'],
            'outer_padding_x' => $atts['outer_padding_x'],
            'section_gap' => $atts['section_gap'],
            'filter_fields' => $atts['filter_fields'],
        ]);
    }

    protected static function wrapper_style($atts = []) {
        return '--gpo-card-gap:' . absint($atts['card_gap'] ?? 24) . 'px;' .
            '--gpo-card-padding:' . absint($atts['card_padding'] ?? 22) . 'px;' .
            '--gpo-content-max-width:' . absint($atts['content_max_width'] ?? 1280) . 'px;' .
            '--gpo-shell-margin-y:' . absint($atts['outer_margin_y'] ?? 32) . 'px;' .
            '--gpo-shell-padding-x:' . absint($atts['outer_padding_x'] ?? 18) . 'px;' .
            '--gpo-section-gap:' . absint($atts['section_gap'] ?? 24) . 'px;' .
            (!empty($atts['primary_color']) ? '--gpo-primary:' . sanitize_text_field($atts['primary_color']) . ';' : '') .
            (!empty($atts['accent_color']) ? '--gpo-accent:' . sanitize_text_field($atts['accent_color']) . ';' : '') .
            (!empty($atts['bg_color']) ? '--gpo-bg:' . sanitize_text_field($atts['bg_color']) . ';' : '') .
            (!empty($atts['text_color']) ? '--gpo-local-text:' . sanitize_text_field($atts['text_color']) . ';' : '') .
            (!empty($atts['button_color']) ? '--gpo-button-bg:' . sanitize_text_field($atts['button_color']) . ';' : '') .
            (!empty($atts['button_text_color']) ? '--gpo-button-text:' . sanitize_text_field($atts['button_text_color']) . ';' : '');
    }

    protected static function resolve_card_display($atts = []) {
        $settings = self::display_settings();
        $visible = [];
        foreach (($settings['style']['card_elements'] ?? []) as $key => $value) {
            if ((string) $value === '1') {
                $visible[] = $key;
            }
        }
        if (!empty($atts['show'])) {
            $visible = self::parse_show_string($atts['show']);
        }
        return [
            'layout' => sanitize_key($atts['card_layout'] ?? ($settings['style']['card_layout'] ?? 'default')),
            'visible' => $visible,
            'hero' => !empty($atts['hero']),
            'primary_button_label' => sanitize_text_field($atts['primary_button_label'] ?? 'Scheda veicolo'),
            'secondary_button_label' => sanitize_text_field($atts['secondary_button_label'] ?? 'Richiedi info'),
        ];
    }

    public static function single_display() {
        $settings = self::display_settings();
        $sections = [];
        foreach (($settings['style']['single_sections'] ?? []) as $key => $value) {
            if ((string) $value === '1') {
                $sections[] = $key;
            }
        }
        return [
            'layout' => sanitize_key($settings['style']['single_layout'] ?? 'classic'),
            'visible' => $sections,
        ];
    }

    protected static function parse_show_string($show) {
        $items = array_filter(array_map('trim', explode(',', (string) $show)));
        return array_values(array_unique(array_map('sanitize_key', $items)));
    }

    protected static function is_visible($display, $key) {
        return in_array($key, $display['visible'], true);
    }

    protected static function build_vehicle_query($atts = []) {
        $limit = max(1, absint($atts['limit'] ?? 12));
        $meta_query = [
            self::exclude_demo_vehicle_meta_clause(),
        ];

        if (($atts['featured'] ?? 'no') === 'yes') {
            $meta_query[] = ['key' => '_gpo_featured', 'value' => '1'];
        }

        $condition = self::request_value('gpo_condition');
        $fuel = self::request_value('gpo_fuel');
        $body = self::request_value('gpo_body_type');
        $transmission = self::request_value('gpo_transmission');
        $brand = self::request_value('gpo_brand');
        $brand_key = self::request_value('gpo_brand_key');
        $search = self::request_value('gpo_search');
        $min_price = self::request_value('gpo_min_price');
        $max_price = self::request_value('gpo_max_price');
        $max_mileage = self::request_value('gpo_max_mileage');
        $year = self::request_value('gpo_year');

        if ($condition !== '') {
            $meta_query[] = ['key' => '_gpo_condition', 'value' => $condition];
        }
        if ($fuel !== '') {
            $meta_query[] = ['key' => '_gpo_fuel', 'value' => $fuel];
        }
        if ($body !== '') {
            $meta_query[] = ['key' => '_gpo_body_type', 'value' => $body];
        }
        if ($transmission !== '') {
            $meta_query[] = ['key' => '_gpo_transmission', 'value' => $transmission];
        }
        if ($brand_key !== '') {
            $meta_query[] = self::brand_meta_query($brand_key);
        } elseif ($brand !== '') {
            $meta_query[] = ['key' => '_gpo_brand', 'value' => $brand];
        }
        if ($year !== '') {
            $meta_query[] = ['key' => '_gpo_year', 'value' => $year];
        }
        if ($min_price !== '' || $max_price !== '') {
            $range = ['key' => '_gpo_price', 'type' => 'NUMERIC'];
            if ($min_price !== '' && $max_price !== '') {
                $range['value'] = [absint($min_price), absint($max_price)];
                $range['compare'] = 'BETWEEN';
            } elseif ($min_price !== '') {
                $range['value'] = absint($min_price);
                $range['compare'] = '>=';
            } else {
                $range['value'] = absint($max_price);
                $range['compare'] = '<=';
            }
            $meta_query[] = $range;
        }
        if ($max_mileage !== '') {
            $meta_query[] = [
                'key' => '_gpo_mileage',
                'value' => absint($max_mileage),
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }

        $orderby = sanitize_key($atts['orderby'] ?? 'date');
        $order = strtoupper(sanitize_text_field($atts['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $sort = self::request_value('gpo_sort');
        if ($sort === 'price_asc') {
            $orderby = 'price';
            $order = 'ASC';
        } elseif ($sort === 'price_desc') {
            $orderby = 'price';
            $order = 'DESC';
        } elseif ($sort === 'year_desc') {
            $orderby = 'year';
            $order = 'DESC';
        } elseif ($sort === 'mileage_asc') {
            $orderby = 'mileage';
            $order = 'ASC';
        }
        $args = [
            'post_type' => 'gpo_vehicle',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => $orderby,
            'order' => $order,
            's' => $search,
        ];

        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }
            $args['meta_query'] = $meta_query;
        }

        if ($orderby === 'price') {
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_gpo_price';
        }
        if ($orderby === 'year') {
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_gpo_year';
        }
        if ($orderby === 'mileage') {
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_gpo_mileage';
        }

        return new WP_Query($args);
    }

    protected static function render_results_header($query, $compact = false) {
        $found = absint($query->found_posts);
        $sort = self::request_value('gpo_sort') ?: 'date_desc';
        echo '<div class="gpo-results-head' . ($compact ? ' gpo-results-head-compact' : '') . '">';
        echo '<div><strong>' . esc_html((string) $found) . '</strong> veicoli trovati</div>';
        echo '<div class="gpo-sort-wrap"><label for="gpo-sort">Ordina per</label><select id="gpo-sort" name="gpo_sort" form="gpo-filter-form">';
        $options = [
            'date_desc' => 'Più recenti',
            'price_asc' => 'Prezzo crescente',
            'price_desc' => 'Prezzo decrescente',
            'year_desc' => 'Anno più recente',
            'mileage_asc' => 'Meno chilometri',
        ];
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($sort, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></div></div>';
    }

    protected static function render_filter_form($override_fields = '') {
        $action = get_permalink() ?: home_url('/');
        $catalog_values = [
            'condition' => self::distinct_meta_values('_gpo_condition'),
            'fuel' => self::distinct_meta_values('_gpo_fuel'),
            'body_type' => self::distinct_meta_values('_gpo_body_type'),
            'transmission' => self::distinct_meta_values('_gpo_transmission'),
            'brand' => self::distinct_meta_values('_gpo_brand'),
            'year' => self::distinct_meta_values('_gpo_year', true),
        ];
        $visible_filters = self::resolve_filter_fields($override_fields);

        ob_start();
        echo '<form id="gpo-filter-form" class="gpo-filter-panel" method="get" action="' . esc_url($action) . '">';
        if (self::request_value('gpo_brand_key') !== '') {
            echo '<input type="hidden" name="gpo_brand_key" value="' . esc_attr(self::request_value('gpo_brand_key')) . '" />';
        }
        if (self::request_value('gpo_catalog_ref') !== '') {
            echo '<input type="hidden" name="gpo_catalog_ref" value="' . esc_attr(self::request_value('gpo_catalog_ref')) . '" />';
        }
        echo '<div class="gpo-filter-grid">';
        if (in_array('search', $visible_filters, true)) { self::render_filter_input('Cerca veicolo', 'gpo_search', self::request_value('gpo_search'), 'text', 'Marca o modello'); }
        if (in_array('condition', $visible_filters, true)) { self::render_filter_select('Condizione', 'gpo_condition', $catalog_values['condition']); }
        if (in_array('brand', $visible_filters, true)) { self::render_filter_select('Marca', 'gpo_brand', $catalog_values['brand']); }
        if (in_array('fuel', $visible_filters, true)) { self::render_filter_select('Alimentazione', 'gpo_fuel', $catalog_values['fuel']); }
        if (in_array('body_type', $visible_filters, true)) { self::render_filter_select('Carrozzeria', 'gpo_body_type', $catalog_values['body_type']); }
        if (in_array('transmission', $visible_filters, true)) { self::render_filter_select('Cambio', 'gpo_transmission', $catalog_values['transmission']); }
        if (in_array('year', $visible_filters, true)) { self::render_filter_select('Anno', 'gpo_year', $catalog_values['year']); }
        if (in_array('min_price', $visible_filters, true)) { self::render_filter_input('Prezzo min', 'gpo_min_price', self::request_value('gpo_min_price'), 'number', '0'); }
        if (in_array('max_price', $visible_filters, true)) { self::render_filter_input('Prezzo max', 'gpo_max_price', self::request_value('gpo_max_price'), 'number', '50000'); }
        if (in_array('max_mileage', $visible_filters, true)) { self::render_filter_input('KM max', 'gpo_max_mileage', self::request_value('gpo_max_mileage'), 'number', '60000'); }
        if (in_array('sort', $visible_filters, true)) {
            echo '<label><span>Ordina per</span><select name="gpo_sort">';
            $sort = self::request_value('gpo_sort') ?: 'date_desc';
            $options = [
                'date_desc' => 'Più recenti',
                'price_asc' => 'Prezzo crescente',
                'price_desc' => 'Prezzo decrescente',
                'year_desc' => 'Anno più recente',
                'mileage_asc' => 'Meno chilometri',
            ];
            foreach ($options as $value => $label) {
                echo '<option value="' . esc_attr($value) . '" ' . selected($sort, $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></label>';
        }
        echo '</div>';
        echo '<div class="gpo-filter-actions"><button class="gpo-button" type="submit">Applica filtri</button><a class="gpo-button gpo-button-secondary" href="' . esc_url($action) . '">Reset</a></div>';
        echo '</form>';
        return ob_get_clean();
    }

    protected static function resolve_filter_fields($override_fields = '') {
        if (!empty($override_fields)) {
            return self::parse_show_string($override_fields);
        }
        $settings = self::display_settings();
        $visible = [];
        foreach (($settings['style']['filter_fields'] ?? []) as $key => $value) {
            if ((string) $value === '1') {
                $visible[] = $key;
            }
        }
        return $visible;
    }

    protected static function render_filter_select($label, $name, $values) {
        echo '<label><span>' . esc_html($label) . '</span><select name="' . esc_attr($name) . '"><option value="">Tutti</option>';
        $current = self::request_value($name);
        foreach ($values as $value) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($value) . '</option>';
        }
        echo '</select></label>';
    }

    protected static function render_filter_input($label, $name, $value, $type = 'text', $placeholder = '') {
        echo '<label><span>' . esc_html($label) . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" /></label>';
    }

    public static function fallback_vehicle_image_url() {
        if (class_exists('GPO_Admin') && method_exists('GPO_Admin', 'default_settings')) {
            $settings = wp_parse_args(get_option('gpo_settings', []), GPO_Admin::default_settings());
            $custom = trim((string) ($settings['style']['fallback_vehicle_image'] ?? ''));
            if ($custom) {
                return esc_url($custom);
            }
        }
        return esc_url(GPO_PLUGIN_URL . 'public/assets/images/no_vehicle.png');
    }

    public static function fallback_vehicle_image_markup($class = 'gpo-fallback-image') {
        return '<img class="' . esc_attr($class) . '" src="' . self::fallback_vehicle_image_url() . '" alt="Immagine veicolo non disponibile" />';
    }

    public static function vehicle_thumbnail_url($post_id, $size = 'thumbnail') {
        $post_id = absint($post_id);

        $thumbnail = get_the_post_thumbnail_url($post_id, $size);
        if ($thumbnail) {
            return esc_url($thumbnail);
        }

        $gallery = (array) get_post_meta($post_id, '_gpo_gallery_ids', true);
        if (!empty($gallery[0])) {
            $image = wp_get_attachment_image_url(absint($gallery[0]), $size);
            if ($image) {
                return esc_url($image);
            }
        }

        return self::fallback_vehicle_image_url();
    }

    protected static function exclude_demo_vehicle_meta_clause() {
        return [
            'relation' => 'OR',
            [
                'key' => '_gpo_is_template_demo',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_gpo_is_template_demo',
                'value' => '1',
                'compare' => '!=',
            ],
        ];
    }

    protected static function distinct_meta_values($key, $numeric_sort = false) {
        global $wpdb;
        $values = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} demo ON demo.post_id = p.ID AND demo.meta_key = '_gpo_is_template_demo'
            WHERE pm.meta_key = %s
            AND pm.meta_value <> ''
            AND p.post_type = 'gpo_vehicle'
            AND p.post_status = 'publish'
            AND (demo.meta_value IS NULL OR demo.meta_value <> '1')",
            $key
        ));
        $values = array_filter(array_map('trim', array_map('wp_strip_all_tags', $values)));
        if ($numeric_sort) {
            sort($values, SORT_NUMERIC);
            $values = array_reverse($values);
        } else {
            natcasesort($values);
        }
        return array_values(array_unique($values));
    }

    public static function render_card($post_id, $display = []) {
        $price = get_post_meta($post_id, '_gpo_price', true);
        $promo_price = get_post_meta($post_id, '_gpo_price_promo', true);
        $current_price = $promo_price ?: $price;
        $badge = get_post_meta($post_id, '_gpo_badge', true);
        $is_featured = get_post_meta($post_id, '_gpo_featured', true) === '1';
        $specs = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($post_id, '_gpo_specs', true))));
        $chips = [
            get_post_meta($post_id, '_gpo_condition', true),
            get_post_meta($post_id, '_gpo_fuel', true),
            get_post_meta($post_id, '_gpo_transmission', true),
        ];

        $meta = [
            'year' => ['Anno', get_post_meta($post_id, '_gpo_year', true)],
            'mileage' => ['KM', self::format_number(get_post_meta($post_id, '_gpo_mileage', true), ' km')],
            'body_type' => ['Carrozzeria', get_post_meta($post_id, '_gpo_body_type', true)],
            'transmission' => ['Cambio', get_post_meta($post_id, '_gpo_transmission', true)],
            'engine_size' => ['Cilindrata', self::format_engine_size(get_post_meta($post_id, '_gpo_engine_size', true))],
        ];

        $layout = $display['layout'] ?? 'default';
        $hero = !empty($display['hero']);

        echo '<article class="gpo-card gpo-card-layout-' . esc_attr($layout) . ' ' . ($hero ? 'gpo-card-hero' : '') . '">';

        if (self::is_visible($display, 'image')) {
            echo '<div class="gpo-card-media">';
            if (has_post_thumbnail($post_id)) {
                echo '<a class="gpo-card-image" href="' . esc_url(get_permalink($post_id)) . '">' . get_the_post_thumbnail($post_id, 'large') . '</a>';
            } else {
                echo '<a class="gpo-card-image gpo-card-image-placeholder" href="' . esc_url(get_permalink($post_id)) . '">' . self::fallback_vehicle_image_markup('gpo-fallback-image') . '</a>';
            }
            if (self::is_visible($display, 'badge')) {
                echo '<div class="gpo-card-overlay">';
                if ($badge) {
                    echo '<span class="gpo-badge">' . esc_html($badge) . '</span>';
                } elseif ($is_featured) {
                    echo '<span class="gpo-badge">In vetrina</span>';
                }
                if ($promo_price && $price) {
                    echo '<span class="gpo-badge gpo-badge-soft">Promo attiva</span>';
                }
                echo '</div>';
            }
            echo '</div>';
        }

        echo '<div class="gpo-card-body">';
        echo '<div class="gpo-card-topline">';
        echo '<div>';
        if (self::is_visible($display, 'brand')) {
            echo '<p class="gpo-card-brand">' . esc_html(trim(get_post_meta($post_id, '_gpo_brand', true) . ' ' . get_post_meta($post_id, '_gpo_model', true))) . '</p>';
        }
        if (self::is_visible($display, 'title')) {
            echo '<h3 class="gpo-card-title"><a href="' . esc_url(get_permalink($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a></h3>';
        }
        echo '</div>';
        if (self::is_visible($display, 'price')) {
            echo '<div class="gpo-price-box">';
            if ($promo_price && $price && $promo_price !== $price) {
                echo '<span class="gpo-price-old">' . esc_html(self::format_price($price)) . '</span>';
            }
            echo '<strong class="gpo-price-current">' . esc_html(self::format_price($current_price)) . '</strong>';
            echo '</div>';
        }
        echo '</div>';

        if (self::is_visible($display, 'chips')) {
            echo '<div class="gpo-chip-row">';
            foreach ($chips as $chip) {
                if ($chip) {
                    echo '<span class="gpo-chip">' . esc_html($chip) . '</span>';
                }
            }
            echo '</div>';
        }

        $has_meta = false;
        foreach ($meta as $meta_key => $meta_item) {
            if (self::is_visible($display, $meta_key) && $meta_item[1] !== '') {
                $has_meta = true;
                break;
            }
        }
        if ($has_meta) {
            echo '<div class="gpo-meta-grid">';
            foreach ($meta as $meta_key => $meta_item) {
                if (!self::is_visible($display, $meta_key) || $meta_item[1] === '') {
                    continue;
                }
                echo '<div><strong>' . esc_html($meta_item[0]) . '</strong><span>' . esc_html($meta_item[1]) . '</span></div>';
            }
            echo '</div>';
        }

        if (self::is_visible($display, 'specs') && !empty($specs)) {
            echo '<ul class="gpo-spec-list">';
            foreach (array_slice($specs, 0, 3) as $item) {
                echo '<li>' . esc_html($item) . '</li>';
            }
            echo '</ul>';
        }

        if (self::is_visible($display, 'primary_button') || self::is_visible($display, 'secondary_button')) {
            echo '<div class="gpo-card-actions">';
            if (self::is_visible($display, 'primary_button')) {
                echo '<a class="gpo-button" href="' . esc_url(get_permalink($post_id)) . '">' . esc_html($display['primary_button_label'] ?? 'Scheda veicolo') . '</a>';
            }
            if (self::is_visible($display, 'secondary_button')) {
                echo '<a class="gpo-link" href="' . esc_url(get_permalink($post_id)) . '">' . esc_html($display['secondary_button_label'] ?? 'Richiedi info') . '</a>';
            }
            echo '</div>';
        }
        echo '</div></article>';
    }

    protected static function featured_vehicle_collection($limit = 12) {
        $ids = self::get_current_featured_ids($limit);
        if (!empty($ids)) {
            return [
                'ids' => $ids,
                'source' => 'featured',
            ];
        }

        return [
            'ids' => self::latest_real_vehicle_ids($limit),
            'source' => 'latest',
        ];
    }

    protected static function get_current_featured_ids($limit = 12) {
        $query = new WP_Query([
            'post_type' => 'gpo_vehicle',
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'fields' => 'ids',
            'meta_key' => '_gpo_featured_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => [
                self::exclude_demo_vehicle_meta_clause(),
                ['key' => '_gpo_featured', 'value' => '1'],
            ],
        ]);
        $ids = [];
        foreach ($query->posts as $post_id) {
            if (self::is_currently_featured($post_id)) {
                $ids[] = $post_id;
            }
            if (count($ids) >= $limit) {
                break;
            }
        }
        return $ids;
    }

    protected static function latest_real_vehicle_ids($limit = 12) {
        $query = new WP_Query([
            'post_type' => 'gpo_vehicle',
            'post_status' => 'publish',
            'posts_per_page' => max(1, absint($limit)),
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                self::exclude_demo_vehicle_meta_clause(),
            ],
        ]);

        return array_map('absint', (array) $query->posts);
    }

    public static function single_template($template) {
        if (is_singular('gpo_vehicle')) {
            $custom = GPO_PLUGIN_DIR . 'public/templates/single-gpo_vehicle.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }
        return $template;
    }

    public static function set_template_vehicle_context($post_id) {
        self::$template_vehicle_id = absint($post_id);
    }

    public static function clear_template_vehicle_context() {
        self::$template_vehicle_id = 0;
    }

    public static function current_single_template_id() {
        if (current_user_can('edit_posts') && isset($_GET['gpo_preview_template'])) {
            $preview_id = absint(wp_unslash($_GET['gpo_preview_template']));
            if ($preview_id > 0 && get_post_type($preview_id) === 'gpo_template') {
                return $preview_id;
            }
        }

        $settings = self::display_settings();
        return absint($settings['style']['single_template_id'] ?? 0);
    }

    public static function template_preview_vehicle_link($template_id = 0) {
        $vehicle_id = self::current_vehicle_id();
        if (!$vehicle_id) {
            return '';
        }

        $url = get_permalink($vehicle_id);
        if (!$url) {
            return '';
        }

        if ($template_id > 0) {
            $url = add_query_arg('gpo_preview_template', absint($template_id), $url);
        }

        return $url;
    }

    public static function current_vehicle_id() {
        if (self::$template_vehicle_id) {
            return self::$template_vehicle_id;
        }
        if (is_singular('gpo_vehicle')) {
            return get_queried_object_id();
        }

        $preview = get_posts([
            'post_type' => 'gpo_vehicle',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'meta_query' => [
                self::exclude_demo_vehicle_meta_clause(),
            ],
        ]);

        if (!empty($preview)) {
            return absint($preview[0]);
        }

        return 0;
    }

    public static function vehicle_data($post_id) {
        $post_id = absint($post_id);
        $data = [];
        foreach (GPO_CPT::fields() as $key => $label) {
            $data[$key] = get_post_meta($post_id, '_gpo_' . $key, true);
        }
        $data['badge'] = get_post_meta($post_id, '_gpo_badge', true);
        $data['price'] = get_post_meta($post_id, '_gpo_price', true);
        $data['promo_price'] = get_post_meta($post_id, '_gpo_price_promo', true);
        $data['current_price'] = $data['promo_price'] ?: $data['price'];
        return $data;
    }

    protected static function gallery_attachment_ids($post_id) {
        $post_id = absint($post_id);
        $ids = [];
        $thumbnail_id = get_post_thumbnail_id($post_id);

        if ($thumbnail_id) {
            $ids[] = absint($thumbnail_id);
        }

        $gallery = (array) get_post_meta($post_id, '_gpo_gallery_ids', true);
        foreach ($gallery as $attachment_id) {
            $attachment_id = absint($attachment_id);
            if ($attachment_id > 0) {
                $ids[] = $attachment_id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected static function gallery_items($post_id) {
        $items = [];

        foreach (self::gallery_attachment_ids($post_id) as $index => $attachment_id) {
            $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            $large = wp_get_attachment_image_url($attachment_id, 'large');
            $full = wp_get_attachment_image_url($attachment_id, 'full');

            if (!$thumb || !$large) {
                continue;
            }

            $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
            if ($alt === '') {
                $alt = get_the_title($post_id) ?: 'Foto veicolo';
            }

            $caption = trim((string) wp_get_attachment_caption($attachment_id));
            if ($caption === '') {
                $caption = 'Foto ' . ($index + 1);
            }

            $items[] = [
                'id' => $attachment_id,
                'thumb' => $thumb,
                'large' => $large,
                'full' => $full ?: $large,
                'alt' => $alt,
                'caption' => $caption,
            ];
        }

        return $items;
    }

    public static function gallery_markup($post_id, $with_panel = false) {
        $post_id = absint($post_id);
        $items = self::gallery_items($post_id);
        if (wp_script_is('gpo-vehicle-gallery', 'registered')) {
            wp_enqueue_script('gpo-vehicle-gallery');
        }

        ob_start();
        if ($with_panel) {
            echo '<div class="gpo-single-gallery-panel' . (empty($items) ? ' is-empty' : '') . '" data-gpo-gallery="1">';
        }

        if (!empty($items)) {
            $current = $items[0];
            $count = count($items);

            echo '<div class="gpo-single-stage">';
            echo '<button type="button" class="gpo-single-stage__nav prev" aria-label="Foto precedente">' . self::icon_markup('chevron-left') . '</button>';
            echo '<button type="button" class="gpo-single-main" aria-label="Apri galleria immagini">';
            echo '<span class="gpo-single-main__media"><img class="gpo-single-main__image" src="' . esc_url($current['large']) . '" alt="' . esc_attr($current['alt']) . '" loading="eager" /></span>';
            echo '<span class="gpo-single-main__zoom"><span class="gpo-single-main__zoom-icon">' . self::icon_markup('zoom') . '</span><span>Clicca per ingrandire</span></span>';
            echo '</button>';
            echo '<button type="button" class="gpo-single-stage__nav next" aria-label="Foto successiva">' . self::icon_markup('chevron-right') . '</button>';
            echo '</div>';

            echo '<div class="gpo-single-gallery-bar">';
            echo '<div class="gpo-single-gallery-count"><strong>1 / ' . esc_html((string) $count) . '</strong><small>' . esc_html($current['caption']) . '</small></div>';
            echo '<button type="button" class="gpo-single-gallery-expand">' . self::icon_markup('zoom') . '<span>Apri fullscreen</span></button>';
            echo '</div>';

            echo '<div class="gpo-single-thumbs" role="list">';
            foreach ($items as $index => $item) {
                echo '<button type="button" class="gpo-single-thumb' . ($index === 0 ? ' is-active' : '') . '" data-index="' . esc_attr((string) $index) . '" data-large-src="' . esc_url($item['large']) . '" data-full-src="' . esc_url($item['full']) . '" data-alt="' . esc_attr($item['alt']) . '" data-caption="' . esc_attr($item['caption']) . '" aria-label="Seleziona foto ' . esc_attr((string) ($index + 1)) . '"' . ($index === 0 ? ' aria-current="true"' : '') . '>';
                echo '<img src="' . esc_url($item['thumb']) . '" alt="' . esc_attr($item['alt']) . '" loading="lazy" />';
                echo '</button>';
            }
            echo '</div>';

            echo '<div class="gpo-gallery-lightbox" hidden aria-hidden="true">';
            echo '<button type="button" class="gpo-gallery-lightbox__backdrop" data-gpo-gallery-close="1" aria-label="Chiudi galleria"></button>';
            echo '<div class="gpo-gallery-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Galleria immagini veicolo">';
            echo '<button type="button" class="gpo-gallery-lightbox__close" data-gpo-gallery-close="1" aria-label="Chiudi">' . self::icon_markup('clear') . '</button>';
            echo '<button type="button" class="gpo-gallery-lightbox__nav prev" aria-label="Foto precedente">' . self::icon_markup('chevron-left') . '</button>';
            echo '<figure class="gpo-gallery-lightbox__figure">';
            echo '<img class="gpo-gallery-lightbox__image" src="' . esc_url($current['full']) . '" alt="' . esc_attr($current['alt']) . '" />';
            echo '<figcaption class="gpo-gallery-lightbox__caption"><strong class="gpo-gallery-lightbox__counter">1 / ' . esc_html((string) $count) . '</strong><span>' . esc_html($current['caption']) . '</span></figcaption>';
            echo '</figure>';
            echo '<button type="button" class="gpo-gallery-lightbox__nav next" aria-label="Foto successiva">' . self::icon_markup('chevron-right') . '</button>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="gpo-single-main gpo-single-main--fallback">' . self::fallback_vehicle_image_markup('gpo-fallback-image') . '</div>';
        }

        if ($with_panel) {
            echo '</div>';
        }

        return ob_get_clean();
    }

    public static function list_meta_values($post_id, $meta_key) {
        return array_filter(array_map('trim', preg_split('/
|
|
/', (string) get_post_meta($post_id, $meta_key, true))));
    }

    public static function icon_list_markup($items) {
        if (empty($items)) {
            return '';
        }
        $html = '<ul class="gpo-icon-list">';
        foreach ($items as $item) {
            $html .= '<li>' . esc_html($item) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    public static function specs_grid_markup($post_id, $fields = [], $layout = 'grid') {
        $map = [
            'condition' => ['Condizione', get_post_meta($post_id, '_gpo_condition', true)],
            'year' => ['Anno', get_post_meta($post_id, '_gpo_year', true)],
            'fuel' => ['Alimentazione', get_post_meta($post_id, '_gpo_fuel', true)],
            'mileage' => ['Chilometraggio', get_post_meta($post_id, '_gpo_mileage', true) ? number_format_i18n((float) get_post_meta($post_id, '_gpo_mileage', true), 0) . ' km' : ''],
            'body_type' => ['Carrozzeria', get_post_meta($post_id, '_gpo_body_type', true)],
            'transmission' => ['Cambio', get_post_meta($post_id, '_gpo_transmission', true)],
            'engine_size' => ['Cilindrata', get_post_meta($post_id, '_gpo_engine_size', true) ? get_post_meta($post_id, '_gpo_engine_size', true) . ' cc' : ''],
            'power' => ['Potenza', get_post_meta($post_id, '_gpo_power', true)],
            'color' => ['Colore', get_post_meta($post_id, '_gpo_color', true)],
            'doors' => ['Porte', get_post_meta($post_id, '_gpo_doors', true)],
            'seats' => ['Posti', get_post_meta($post_id, '_gpo_seats', true)],
            'location' => ['Sede', get_post_meta($post_id, '_gpo_location', true)],
        ];
        if (empty($fields)) {
            $fields = array_keys($map);
        }
        $class = $layout === 'table' ? 'gpo-spec-table' : 'gpo-meta-grid';
        $html = '<div class="' . esc_attr($class) . '">';
        foreach ($fields as $key) {
            if (empty($map[$key][1])) {
                continue;
            }
            $html .= '<div><strong>' . esc_html($map[$key][0]) . '</strong><span>' . esc_html($map[$key][1]) . '</span></div>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function format_price_public($price) {
        return self::format_price($price);
    }

    public static function is_currently_featured($post_id) {
        if (get_post_meta($post_id, '_gpo_featured', true) !== '1') {
            return false;
        }
        $now = current_time('timestamp');
        $from = get_post_meta($post_id, '_gpo_featured_from', true);
        $to = get_post_meta($post_id, '_gpo_featured_to', true);
        if ($from && strtotime($from) > $now) {
            return false;
        }
        if ($to && strtotime($to) < $now) {
            return false;
        }
        return true;
    }

    protected static function format_price($price) {
        if ($price === '' || $price === null) {
            return 'Prezzo su richiesta';
        }
        return '€ ' . number_format_i18n((float) $price, 0);
    }

    protected static function format_number($value, $suffix = '') {
        if ($value === '' || $value === null) {
            return '';
        }
        return number_format_i18n((float) $value, 0) . $suffix;
    }

    protected static function format_engine_size($value) {
        if ($value === '' || $value === null) {
            return '';
        }
        return trim((string) $value) . ' cc';
    }

    protected static function request_value($key) {
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }

    public static function ensure_template_preview_vehicle() {
        return 0;
    }

    protected static function brand_registry_local() {
        return [
            'abarth' => ['name' => 'Abarth', 'aliases' => [], 'primary' => '#111827', 'accent' => '#ef4444'],
            'alfa-romeo' => ['name' => 'Alfa Romeo', 'aliases' => ['Alfa'], 'primary' => '#0f172a', 'accent' => '#166534'],
            'alpine' => ['name' => 'Alpine', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#38bdf8'],
            'aston-martin' => ['name' => 'Aston Martin', 'aliases' => [], 'primary' => '#111827', 'accent' => '#84cc16'],
            'audi' => ['name' => 'Audi', 'aliases' => [], 'primary' => '#111827', 'accent' => '#9ca3af'],
            'bentley' => ['name' => 'Bentley', 'aliases' => [], 'primary' => '#1f2937', 'accent' => '#d4af37'],
            'bmw' => ['name' => 'BMW', 'aliases' => [], 'primary' => '#0f172a', 'accent' => '#3b82f6'],
            'byd' => ['name' => 'BYD', 'aliases' => [], 'primary' => '#7f1d1d', 'accent' => '#ef4444'],
            'cadillac' => ['name' => 'Cadillac', 'aliases' => [], 'primary' => '#111827', 'accent' => '#c084fc'],
            'chevrolet' => ['name' => 'Chevrolet', 'aliases' => [], 'primary' => '#111827', 'accent' => '#f59e0b'],
            'chrysler' => ['name' => 'Chrysler', 'aliases' => [], 'primary' => '#1f2937', 'accent' => '#94a3b8'],
            'citroen' => ['name' => 'Citroen', 'aliases' => ['Citroën'], 'primary' => '#0f172a', 'accent' => '#ef4444'],
            'cupra' => ['name' => 'Cupra', 'aliases' => [], 'primary' => '#0f172a', 'accent' => '#c084fc'],
            'dacia' => ['name' => 'Dacia', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#10b981'],
            'daihatsu' => ['name' => 'Daihatsu', 'aliases' => [], 'primary' => '#7f1d1d', 'accent' => '#ef4444'],
            'dfsk' => ['name' => 'DFSK', 'aliases' => [], 'primary' => '#0f172a', 'accent' => '#f97316'],
            'dodge' => ['name' => 'Dodge', 'aliases' => [], 'primary' => '#111827', 'accent' => '#ef4444'],
            'dr' => ['name' => 'DR', 'aliases' => ['DR Automobiles'], 'primary' => '#0f172a', 'accent' => '#22c55e'],
            'ds' => ['name' => 'DS Automobiles', 'aliases' => ['DS'], 'primary' => '#111827', 'accent' => '#a855f7'],
            'evo' => ['name' => 'EVO', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#14b8a6'],
            'ferrari' => ['name' => 'Ferrari', 'aliases' => [], 'primary' => '#111827', 'accent' => '#f59e0b'],
            'fiat' => ['name' => 'Fiat', 'aliases' => [], 'primary' => '#7f1d1d', 'accent' => '#ef4444'],
            'ford' => ['name' => 'Ford', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#60a5fa'],
            'honda' => ['name' => 'Honda', 'aliases' => [], 'primary' => '#7f1d1d', 'accent' => '#f87171'],
            'hyundai' => ['name' => 'Hyundai', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#60a5fa'],
            'iveco' => ['name' => 'Iveco', 'aliases' => [], 'primary' => '#111827', 'accent' => '#3b82f6'],
            'jaecoo' => ['name' => 'Jaecoo', 'aliases' => [], 'primary' => '#111827', 'accent' => '#06b6d4'],
            'jaguar' => ['name' => 'Jaguar', 'aliases' => [], 'primary' => '#111827', 'accent' => '#94a3b8'],
            'jeep' => ['name' => 'Jeep', 'aliases' => [], 'primary' => '#0f172a', 'accent' => '#84cc16'],
            'kia' => ['name' => 'Kia', 'aliases' => [], 'primary' => '#7f1d1d', 'accent' => '#f97316'],
            'kgm' => ['name' => 'KGM', 'aliases' => ['SsangYong', 'Ssang Yong'], 'primary' => '#082f49', 'accent' => '#22c55e'],
            'lamborghini' => ['name' => 'Lamborghini', 'aliases' => [], 'primary' => '#111827', 'accent' => '#facc15'],
            'lancia' => ['name' => 'Lancia', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#60a5fa'],
            'land-rover' => ['name' => 'Land Rover', 'aliases' => ['LandRover'], 'primary' => '#0f172a', 'accent' => '#22c55e'],
            'lexus' => ['name' => 'Lexus', 'aliases' => [], 'primary' => '#111827', 'accent' => '#e5e7eb'],
            'lotus' => ['name' => 'Lotus', 'aliases' => [], 'primary' => '#111827', 'accent' => '#84cc16'],
            'lynk-co' => ['name' => 'Lynk & Co', 'aliases' => ['Lynk and Co'], 'primary' => '#111827', 'accent' => '#38bdf8'],
            'maserati' => ['name' => 'Maserati', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#60a5fa'],
            'mazda' => ['name' => 'Mazda', 'aliases' => [], 'primary' => '#111827', 'accent' => '#f87171'],
            'mclaren' => ['name' => 'McLaren', 'aliases' => [], 'primary' => '#111827', 'accent' => '#fb923c'],
            'mercedes-benz' => ['name' => 'Mercedes-Benz', 'aliases' => ['Mercedes', 'Mercedes Benz'], 'primary' => '#0f172a', 'accent' => '#d1d5db'],
            'mg' => ['name' => 'MG', 'aliases' => [], 'primary' => '#7f1d1d', 'accent' => '#ef4444'],
            'mini' => ['name' => 'MINI', 'aliases' => [], 'primary' => '#111827', 'accent' => '#e5e7eb'],
            'mitsubishi' => ['name' => 'Mitsubishi', 'aliases' => [], 'primary' => '#7f1d1d', 'accent' => '#ef4444'],
            'nissan' => ['name' => 'Nissan', 'aliases' => [], 'primary' => '#111827', 'accent' => '#f87171'],
            'omoda' => ['name' => 'Omoda', 'aliases' => [], 'primary' => '#0f172a', 'accent' => '#06b6d4'],
            'opel' => ['name' => 'Opel', 'aliases' => [], 'primary' => '#111827', 'accent' => '#facc15'],
            'peugeot' => ['name' => 'Peugeot', 'aliases' => [], 'primary' => '#111827', 'accent' => '#94a3b8'],
            'polestar' => ['name' => 'Polestar', 'aliases' => [], 'primary' => '#0f172a', 'accent' => '#d1d5db'],
            'porsche' => ['name' => 'Porsche', 'aliases' => [], 'primary' => '#111827', 'accent' => '#d97706'],
            'range-rover' => ['name' => 'Range Rover', 'aliases' => [], 'primary' => '#0f172a', 'accent' => '#22c55e'],
            'renault' => ['name' => 'Renault', 'aliases' => [], 'primary' => '#111827', 'accent' => '#facc15'],
            'rolls-royce' => ['name' => 'Rolls-Royce', 'aliases' => ['Rolls Royce'], 'primary' => '#111827', 'accent' => '#cbd5e1'],
            'seat' => ['name' => 'SEAT', 'aliases' => [], 'primary' => '#7f1d1d', 'accent' => '#ef4444'],
            'skoda' => ['name' => 'Skoda', 'aliases' => ['Skoda Auto', 'Škoda'], 'primary' => '#0f172a', 'accent' => '#22c55e'],
            'smart' => ['name' => 'smart', 'aliases' => [], 'primary' => '#111827', 'accent' => '#facc15'],
            'subaru' => ['name' => 'Subaru', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#60a5fa'],
            'suzuki' => ['name' => 'Suzuki', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#ef4444'],
            'tesla' => ['name' => 'Tesla', 'aliases' => [], 'primary' => '#111827', 'accent' => '#ef4444'],
            'toyota' => ['name' => 'Toyota', 'aliases' => [], 'primary' => '#111827', 'accent' => '#ef4444'],
            'volkswagen' => ['name' => 'Volkswagen', 'aliases' => ['VW'], 'primary' => '#082f49', 'accent' => '#60a5fa'],
            'volvo' => ['name' => 'Volvo', 'aliases' => [], 'primary' => '#082f49', 'accent' => '#d1d5db'],
        ];
    }

    protected static function brand_entry_local($brand) {
        $registry = self::brand_registry_local();
        $needle = self::normalize_brand_key($brand);

        foreach ($registry as $key => $entry) {
            $aliases = array_merge([$entry['name'], $key], $entry['aliases'] ?? []);
            foreach ($aliases as $alias) {
                if (self::normalize_brand_key($alias) === $needle) {
                    $entry['key'] = $key;
                    return $entry;
                }
            }
        }

        return [
            'key' => $needle,
            'name' => trim((string) $brand) !== '' ? trim((string) $brand) : ucfirst(str_replace('-', ' ', $needle)),
            'aliases' => [],
            'primary' => '#0f172a',
            'accent' => '#22c55e',
        ];
    }

    protected static function brand_monogram_local($label) {
        $label = trim((string) $label);
        if ($label === '') {
            return 'GP';
        }

        preg_match_all('/[A-Z0-9]/', strtoupper(remove_accents($label)), $matches);
        $letters = implode('', array_slice($matches[0], 0, 4));
        if ($letters !== '') {
            return $letters;
        }

        $parts = array_filter(preg_split('/\s+/', strtoupper(remove_accents($label))));
        return implode('', array_slice(array_map(function ($part) {
            return substr($part, 0, 1);
        }, $parts), 0, 3));
    }

    protected static function brand_logo_data_uri_local($brand) {
        $entry = self::brand_entry_local($brand);
        $label = $entry['name'];
        $monogram = self::brand_monogram_local($label);
        $primary = $entry['primary'] ?? '#0f172a';
        $accent = $entry['accent'] ?? '#22c55e';
        $escaped_label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $escaped_monogram = htmlspecialchars($monogram, ENT_QUOTES, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="240" viewBox="0 0 512 240" role="img" aria-labelledby="title desc">'
            . '<title>' . $escaped_label . '</title>'
            . '<desc>Logo locale incluso nel plugin per ' . $escaped_label . '</desc>'
            . '<defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1">'
            . '<stop offset="0%" stop-color="' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') . '"/>'
            . '<stop offset="100%" stop-color="' . htmlspecialchars($primary, ENT_QUOTES, 'UTF-8') . '"/>'
            . '</linearGradient></defs>'
            . '<rect width="512" height="240" rx="40" fill="#ffffff"/>'
            . '<rect x="10" y="10" width="492" height="220" rx="34" fill="url(#g)" opacity="0.12"/>'
            . '<rect x="28" y="28" width="456" height="184" rx="30" fill="#ffffff" stroke="' . htmlspecialchars($primary, ENT_QUOTES, 'UTF-8') . '" stroke-opacity="0.12"/>'
            . '<circle cx="96" cy="120" r="44" fill="url(#g)"/>'
            . '<text x="96" y="129" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="28" font-weight="700" fill="#ffffff">' . $escaped_monogram . '</text>'
            . '<text x="164" y="110" font-family="Segoe UI, Arial, sans-serif" font-size="34" font-weight="700" fill="' . htmlspecialchars($primary, ENT_QUOTES, 'UTF-8') . '">' . $escaped_label . '</text>'
            . '<text x="164" y="144" font-family="Segoe UI, Arial, sans-serif" font-size="16" fill="#64748b">Logo locale incluso nel plugin</text>'
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    protected static function brand_logo_url_local($brand) {
        $entry = self::brand_entry_local($brand);
        $key = $entry['key'];

        foreach (['svg', 'png', 'webp', 'jpg', 'jpeg'] as $extension) {
            $relative = 'public/assets/images/brands/' . $key . '.' . $extension;
            if (file_exists(GPO_PLUGIN_DIR . $relative)) {
                return GPO_PLUGIN_URL . $relative;
            }
        }

        return self::brand_logo_data_uri_local($brand);
    }

    protected static function brand_logo_has_local_asset($brand) {
        $entry = self::brand_entry_local($brand);
        $key = $entry['key'];

        foreach (['svg', 'png', 'webp', 'jpg', 'jpeg'] as $extension) {
            if (file_exists(GPO_PLUGIN_DIR . 'public/assets/images/brands/' . $key . '.' . $extension)) {
                return true;
            }
        }

        return false;
    }

    protected static function brand_meta_query($brand_key) {
        $entry = self::brand_entry_local($brand_key);
        $values = array_values(array_unique(array_filter(array_merge(
            [$entry['name'], $brand_key],
            $entry['aliases'] ?? []
        ))));

        if (count($values) === 1) {
            return ['key' => '_gpo_brand', 'value' => $values[0]];
        }

        $query = ['relation' => 'OR'];
        foreach ($values as $value) {
            $query[] = ['key' => '_gpo_brand', 'value' => $value];
        }

        return $query;
    }

    protected static function brand_inventory_summary() {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT pm.meta_value AS brand, COUNT(*) AS total
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} demo ON demo.post_id = p.ID AND demo.meta_key = '_gpo_is_template_demo'
            WHERE pm.meta_key = '_gpo_brand'
            AND pm.meta_value <> ''
            AND p.post_type = 'gpo_vehicle'
            AND p.post_status = 'publish'
            AND (demo.meta_value IS NULL OR demo.meta_value <> '1')
            GROUP BY pm.meta_value",
            ARRAY_A
        );

        $brands = [];
        foreach ((array) $rows as $row) {
            $raw = trim((string) ($row['brand'] ?? ''));
            if ($raw === '') {
                continue;
            }

            $entry = self::brand_entry_local($raw);
            $key = $entry['key'];

            if (!isset($brands[$key])) {
                $brands[$key] = [
                    'key' => $key,
                    'name' => $entry['name'],
                    'count' => 0,
                    'logo' => self::brand_logo_url_local($entry['name']),
                    'has_local_logo' => self::brand_logo_has_local_asset($entry['name']),
                ];
            }

            $brands[$key]['count'] += absint($row['total'] ?? 0);
        }

        uasort($brands, function ($left, $right) {
            return strnatcasecmp($left['name'], $right['name']);
        });

        return array_values($brands);
    }

    protected static function icon_markup($type) {
        if ($type === 'clear') {
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        }

        if ($type === 'chevron-left') {
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>';
        }

        if ($type === 'chevron-right') {
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
        }

        if ($type === 'zoom') {
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v4"></path><path d="M9 21H5a2 2 0 0 1-2-2v-4"></path><path d="M21 9V5a2 2 0 0 0-2-2h-4"></path><path d="M3 15v4a2 2 0 0 0 2 2h4"></path></svg>';
        }

        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
    }


    public static function brand_registry() {
        return [
            'abarth' => ['name' => 'Abarth', 'domain' => 'abarth.com'],
            'alfa-romeo' => ['name' => 'Alfa Romeo', 'domain' => 'alfaromeo.it'],
            'audi' => ['name' => 'Audi', 'domain' => 'audi.com'],
            'bmw' => ['name' => 'BMW', 'domain' => 'bmw.com'],
            'byd' => ['name' => 'BYD', 'domain' => 'byd.com'],
            'citroen' => ['name' => 'Citroën', 'domain' => 'citroen.com'],
            'cupra' => ['name' => 'Cupra', 'domain' => 'cupraofficial.com'],
            'dacia' => ['name' => 'Dacia', 'domain' => 'dacia.com'],
            'ds' => ['name' => 'DS', 'domain' => 'dsautomobiles.com'],
            'fiat' => ['name' => 'Fiat', 'domain' => 'fiat.com'],
            'ford' => ['name' => 'Ford', 'domain' => 'ford.com'],
            'honda' => ['name' => 'Honda', 'domain' => 'honda.com'],
            'hyundai' => ['name' => 'Hyundai', 'domain' => 'hyundai.com'],
            'iveco' => ['name' => 'Iveco', 'domain' => 'iveco.com'],
            'jaguar' => ['name' => 'Jaguar', 'domain' => 'jaguar.com'],
            'jeep' => ['name' => 'Jeep', 'domain' => 'jeep.com'],
            'kia' => ['name' => 'Kia', 'domain' => 'kia.com'],
            'lamborghini' => ['name' => 'Lamborghini', 'domain' => 'lamborghini.com'],
            'land-rover' => ['name' => 'Land Rover', 'domain' => 'landrover.com'],
            'lexus' => ['name' => 'Lexus', 'domain' => 'lexus.com'],
            'maserati' => ['name' => 'Maserati', 'domain' => 'maserati.com'],
            'mazda' => ['name' => 'Mazda', 'domain' => 'mazda.com'],
            'mercedes-benz' => ['name' => 'Mercedes-Benz', 'domain' => 'mercedes-benz.com'],
            'mg' => ['name' => 'MG', 'domain' => 'mgmotor.eu'],
            'mini' => ['name' => 'MINI', 'domain' => 'mini.com'],
            'mitsubishi' => ['name' => 'Mitsubishi', 'domain' => 'mitsubishi-motors.com'],
            'nissan' => ['name' => 'Nissan', 'domain' => 'nissan-global.com'],
            'opel' => ['name' => 'Opel', 'domain' => 'opel.com'],
            'peugeot' => ['name' => 'Peugeot', 'domain' => 'peugeot.com'],
            'porsche' => ['name' => 'Porsche', 'domain' => 'porsche.com'],
            'renault' => ['name' => 'Renault', 'domain' => 'renault.com'],
            'seat' => ['name' => 'SEAT', 'domain' => 'seat.com'],
            'skoda' => ['name' => 'Škoda', 'domain' => 'skoda-auto.com'],
            'smart' => ['name' => 'smart', 'domain' => 'smart.com'],
            'subaru' => ['name' => 'Subaru', 'domain' => 'subaru.com'],
            'suzuki' => ['name' => 'Suzuki', 'domain' => 'global-suzuki.com'],
            'tesla' => ['name' => 'Tesla', 'domain' => 'tesla.com'],
            'toyota' => ['name' => 'Toyota', 'domain' => 'toyota.com'],
            'volkswagen' => ['name' => 'Volkswagen', 'domain' => 'vw.com'],
            'volvo' => ['name' => 'Volvo', 'domain' => 'volvocars.com'],
        ];
    }

    protected static function normalize_brand_key($brand) {
        $brand = strtolower(remove_accents((string) $brand));
        $brand = str_replace(['&', '.', "'"], ' ', $brand);
        $brand = preg_replace('/\s+/', '-', trim($brand));
        return sanitize_title($brand);
    }

    protected static function known_brand_logo($brand) {
        $key = self::normalize_brand_key($brand);
        $local_file = GPO_PLUGIN_DIR . 'public/assets/images/brands/' . $key . '.png';
        if (file_exists($local_file)) {
            return GPO_PLUGIN_URL . 'public/assets/images/brands/' . $key . '.png';
        }
        $registry = self::brand_registry();
        if (isset($registry[$key]['domain'])) {
            return 'https://logo.clearbit.com/' . $registry[$key]['domain'] . '?size=256';
        }
        return '';
    }

    protected static function catalog_target_url($page_id) {
        $page_id = absint($page_id);
        if ($page_id > 0) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }
        return home_url('/');
    }

    public static function brand_carousel_shortcode($atts) {
        wp_enqueue_style('gpo-public');
        wp_enqueue_script('gpo-live-search');
        $atts = shortcode_atts([
            'page_id' => 0,
            'catalog_ref' => 'default',
            'logo_size' => 96,
            'autoplay' => 'yes',
            'interval' => 4500,
            'speed' => 450,
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
        ], $atts, 'gestpark_brand_carousel');

        $brands = self::brand_inventory_summary();
        if (empty($brands)) {
            return '<div class="gpo-empty-state"><p>Nessuna marca disponibile.</p></div>';
        }
        $target_url = self::catalog_target_url($atts['page_id']);
        $style = self::wrapper_style([
            'primary_color' => $atts['primary_color'],
            'accent_color' => $atts['accent_color'],
            'bg_color' => $atts['bg_color'],
            'text_color' => $atts['text_color'],
        ]) . '--gpo-brand-logo-size:' . max(72, absint($atts['logo_size'])) . 'px;--gpo-brand-speed:' . max(150, absint($atts['speed'])) . 'ms;';

        ob_start();
        echo '<div class="gpo-brand-carousel-shell" style="' . esc_attr($style) . '">';
        echo '<div class="gpo-brand-carousel" data-autoplay="' . esc_attr($atts['autoplay']) . '" data-interval="' . absint($atts['interval']) . '" data-speed="' . absint($atts['speed']) . '" data-loop="yes">';
        echo '<button type="button" class="gpo-brand-nav prev" aria-label="Marchio precedente">' . self::icon_markup('chevron-left') . '</button>';
        echo '<div class="gpo-brand-viewport"><div class="gpo-brand-track">';
        foreach ($brands as $brand) {
            $url = add_query_arg([
                'gpo_brand_key' => $brand['key'],
                'gpo_catalog_ref' => sanitize_text_field($atts['catalog_ref']),
            ], $target_url);
            $classes = 'gpo-brand-item';
            if (empty($brand['has_local_logo'])) {
                $classes .= ' gpo-brand-item--text-only';
            }
            echo '<a class="' . esc_attr($classes) . '" href="' . esc_url($url) . '" aria-label="' . esc_attr($brand['name']) . '">';
            if (!empty($brand['has_local_logo'])) {
                echo '<span class="gpo-brand-item__visual"><img src="' . esc_url($brand['logo']) . '" alt="' . esc_attr($brand['name']) . '" loading="lazy" /></span>';
            }
            if (empty($brand['has_local_logo'])) {
                echo '<span class="gpo-brand-item__meta"><strong class="gpo-brand-name">' . esc_html($brand['name']) . '</strong></span>';
            }
            echo '</a>';
        }
        echo '</div></div>';
        echo '<button type="button" class="gpo-brand-nav next" aria-label="Marchio successivo">' . self::icon_markup('chevron-right') . '</button>';
        echo '</div></div>';
        return ob_get_clean();
    }

    public static function vehicle_search_shortcode($atts) {
        wp_enqueue_style('gpo-public');
        wp_enqueue_script('gpo-live-search');
        $atts = shortcode_atts([
            'page_id' => 0,
            'catalog_ref' => 'default',
            'placeholder' => 'Cerca veicolo',
            'width' => 100,
            'radius' => 24,
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
        ], $atts, 'gestpark_vehicle_search');
        $target_url = self::catalog_target_url($atts['page_id']);
        $style = self::wrapper_style([
            'primary_color' => $atts['primary_color'],
            'accent_color' => $atts['accent_color'],
            'bg_color' => $atts['bg_color'],
            'text_color' => $atts['text_color'],
            'button_color' => $atts['button_color'],
        ]) . '--gpo-search-width:' . max(20, min(100, absint($atts['width']))) . '%;--gpo-search-radius:' . max(18, absint($atts['radius'])) . 'px;';
        ob_start();
        echo '<div class="gpo-vehicle-search-shell" style="' . esc_attr($style) . '">';
        echo '<form class="gpo-vehicle-search" data-target-url="' . esc_url($target_url) . '" data-catalog-ref="' . esc_attr($atts['catalog_ref']) . '" action="' . esc_url($target_url) . '" method="get" autocomplete="off">';
        echo '<span class="gpo-search-icon" aria-hidden="true">' . self::icon_markup('search') . '</span>';
        echo '<input type="text" name="gpo_search" class="gpo-search-input" placeholder="' . esc_attr($atts['placeholder']) . '" />';
        echo '<button type="button" class="gpo-search-clear" hidden aria-label="Cancella ricerca">' . self::icon_markup('clear') . '</button>';
        echo '<input type="hidden" name="gpo_catalog_ref" value="' . esc_attr($atts['catalog_ref']) . '" />';
        echo '<div class="gpo-search-results" hidden aria-live="polite"></div>';
        echo '</form></div>';
        return ob_get_clean();
    }

    public static function ajax_live_search() {
        check_ajax_referer('gpo_live_search', 'nonce');
        $term = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        if (mb_strlen($term) < 2) {
            wp_send_json_success(['results' => []]);
        }
        $query = new WP_Query([
            'post_type' => 'gpo_vehicle',
            'post_status' => 'publish',
            'posts_per_page' => 6,
            's' => $term,
            'meta_query' => [
                self::exclude_demo_vehicle_meta_clause(),
            ],
        ]);
        $results = [];
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $data = self::vehicle_data($id);
            $subtitle = trim(implode(' ', array_filter([
                $data['brand'] ?? '',
                $data['model'] ?? '',
            ])));
            if (!empty($data['year'])) {
                $subtitle .= ($subtitle ? ' · ' : '') . $data['year'];
            }
            $results[] = [
                'title' => get_the_title(),
                'url' => get_permalink(),
                'price' => self::format_price_public($data['current_price'] ?? ''),
                'brand' => $data['brand'] ?? '',
                'subtitle' => $subtitle,
                'thumb' => self::vehicle_thumbnail_url($id, 'thumbnail'),
            ];
        }
        wp_reset_postdata();
        wp_send_json_success(['results' => $results]);
    }

}
