<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gpo_get_brand_slug')) {
    function gpo_get_brand_slug($brand) {
        $brand = trim((string) $brand);
        $map = [
            'Mercedes-Benz' => 'mercedes',
            'Mercedes Benz' => 'mercedes',
            'Mercedes' => 'mercedes',
            'Alfa Romeo' => 'alfaromeo',
            'Land Rover' => 'landrover',
            'Range Rover' => 'rangerover',
            'DS Automobiles' => 'ds',
            'DS' => 'ds',
            'Citroen' => 'citroen',
            'Citroën' => 'citroen',
            'DR Automobiles' => 'dr',
            'Skoda' => 'skoda',
            'Škoda' => 'skoda',
            'Volkswagen' => 'volkswagen',
            'VW' => 'volkswagen',
        ];

        if (isset($map[$brand])) {
            return $map[$brand];
        }

        $normalized = remove_accents($brand);
        return strtolower(str_replace([' ', '-', '.', '&', "'", '’'], '', $normalized));
    }
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

    protected static function empty_state_markup($title, $message, $class = 'gpo-empty-state') {
        return '<div class="' . esc_attr($class) . '"><h3>' . esc_html($title) . '</h3><p>' . esc_html($message) . '</p></div>';
    }

    public static function assets() {
        wp_register_style('gpo-public', GPO_PLUGIN_URL . 'public/assets/css/gpo-public.css', [], gpo_asset_version('public/assets/css/gpo-public.css'));
        wp_register_script('gpo-carousel', GPO_PLUGIN_URL . 'public/assets/js/gpo-carousel.js', [], gpo_asset_version('public/assets/js/gpo-carousel.js'), true);
        wp_register_script('gpo-filters', GPO_PLUGIN_URL . 'public/assets/js/gpo-filters.js', [], gpo_asset_version('public/assets/js/gpo-filters.js'), true);
        wp_register_script('gpo-live-search', GPO_PLUGIN_URL . 'public/assets/js/gpo-live-search.js', [], gpo_asset_version('public/assets/js/gpo-live-search.js'), true);
        wp_register_script('gpo-vehicle-gallery', GPO_PLUGIN_URL . 'public/assets/js/gpo-vehicle-gallery.js', [], gpo_asset_version('public/assets/js/gpo-vehicle-gallery.js'), true);

        $settings = self::display_settings();
        $style = $settings['style'];
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
        $css .= '.gpo-brand-carousel.is-enhanced .gpo-brand-track{animation:none!important;transform:none!important;will-change:auto;padding-inline:0!important;}' .
            '.gpo-brand-carousel.is-enhanced .gpo-brand-viewport{overflow-x:auto!important;overflow-y:hidden!important;scroll-behavior:auto!important;scrollbar-width:none;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;}' .
            '.gpo-brand-carousel.is-enhanced .gpo-brand-viewport::-webkit-scrollbar{display:none;}' .
            '.gpo-brand-carousel.is-enhanced .gpo-brand-run{gap:clamp(14px,1.6vw,22px)!important;}' .
            '.gpo-share-actions{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;width:100%;max-width:100%;}' .
            '.gpo-share-action{width:100%;max-width:100%;min-width:0;box-sizing:border-box;}' .
            '.gpo-share-action>span:last-child{min-width:0;white-space:normal;overflow-wrap:anywhere;}' .
            '.gpo-share-action--whatsapp{justify-content:flex-start;}' .
            '.gpo-catalog-shell--layout-marketplace-sidebar .gpo-catalog-layout{display:grid;grid-template-columns:minmax(280px,320px) minmax(0,1fr);gap:24px;align-items:start;}' .
            '.gpo-catalog-shell--layout-marketplace-sidebar .gpo-catalog-layout__sidebar,.gpo-catalog-shell--layout-marketplace-sidebar .gpo-catalog-layout__results{min-width:0;}' .
            '.gpo-catalog-shell--layout-marketplace-sidebar .gpo-filter-panel{margin-bottom:0;}' .
            '.gpo-filter-panel--marketplace-sidebar .gpo-filter-toggle{display:flex;}' .
            '.gpo-filter-panel--marketplace-sidebar .gpo-filter-panel__body{display:grid;gap:18px;}' .
            '.gpo-filter-panel--marketplace-sidebar .gpo-filter-grid{grid-template-columns:1fr;}' .
            '.gpo-filter-panel--marketplace-sidebar .gpo-filter-actions{flex-direction:column;align-items:stretch;}' .
            '.gpo-results-head--marketplace{padding:18px 20px;border:1px solid var(--gpo-border);border-radius:calc(var(--gpo-radius) + 4px);background:#fff;box-shadow:var(--gpo-shadow);margin-bottom:18px;}' .
            '.gpo-results-head__summary{display:flex;flex-wrap:wrap;align-items:center;gap:10px;}' .
            '.gpo-results-head__view-chip{display:inline-flex;align-items:center;padding:7px 11px;border-radius:999px;background:rgba(15,23,42,.06);color:#334155;font-size:.78rem;font-weight:700;letter-spacing:.01em;}' .
            '.gpo-results-head__controls{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-left:auto;}' .
            '.gpo-sort-wrap--limit select{min-width:96px;}' .
            '@media (max-width:980px){.gpo-share-actions{grid-template-columns:1fr;}.gpo-catalog-shell--layout-marketplace-sidebar .gpo-catalog-layout{grid-template-columns:1fr;}.gpo-results-head__controls{width:100%;margin-left:0;}}';
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

    public static function component_settings($key = null) {
        $settings = self::display_settings();
        $components = isset($settings['components']) && is_array($settings['components'])
            ? $settings['components']
            : [];

        if ($key === null) {
            return $components;
        }

        return isset($components[$key]) && is_array($components[$key]) ? $components[$key] : [];
    }

    public static function brand_library() {
        $brands = [];
        foreach (self::brand_registry_local() as $key => $entry) {
            $brands[] = [
                'key' => $key,
                'name' => $entry['name'],
                'logo' => self::brand_logo_url_local($entry['name']),
                'has_local_logo' => self::brand_logo_has_local_asset($entry['name']),
            ];
        }

        usort($brands, function ($left, $right) {
            return strnatcasecmp($left['name'], $right['name']);
        });

        return $brands;
    }

    public static function promotion_context($post_id) {
        if (!class_exists('GPO_Engagement')) {
            return null;
        }

        return GPO_Engagement::promotion_for_vehicle($post_id);
    }

    protected static function default_card_elements() {
        return [
            'image',
            'badge',
            'brand',
            'title',
            'price',
            'chips',
            'neopatentati',
            'body_type',
            'transmission',
            'engine_size',
            'specs',
            'primary_button',
        ];
    }

    protected static function default_filter_fields() {
        return [
            'brand',
            'condition',
            'fuel',
            'body_type',
            'transmission',
            'year',
            'min_price',
            'max_price',
            'max_mileage',
            'sort',
        ];
    }

    protected static function lead_email() {
        $raw_settings = get_option('gpo_settings', []);
        $raw_settings = is_array($raw_settings) ? $raw_settings : [];

        $lead_request_settings = $raw_settings['components']['lead_requests'] ?? null;
        if (is_array($lead_request_settings) && array_key_exists('recipient_email', $lead_request_settings)) {
            return sanitize_email((string) ($lead_request_settings['recipient_email'] ?? ''));
        }

        return sanitize_email((string) ($raw_settings['style']['lead_email'] ?? ''));
    }

    protected static function has_configured_lead_email() {
        return self::lead_email() !== '';
    }

    protected static function whatsapp_contact_number() {
        $settings = self::component_settings('lead_requests');
        $raw = trim((string) ($settings['whatsapp_number'] ?? ''));
        if ($raw === '') {
            return '';
        }

        $normalized = preg_replace('/[^0-9+]/', '', $raw);
        if (strpos($normalized, '00') === 0) {
            $normalized = '+' . substr($normalized, 2);
        }

        return (string) $normalized;
    }

    protected static function whatsapp_chat_url($post_id = 0) {
        $number = self::whatsapp_contact_number();
        if ($number === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $number);
        if ($digits === '') {
            return '';
        }

        $post_id = absint($post_id);
        $title = $post_id ? trim(wp_strip_all_tags(get_the_title($post_id))) : 'questo veicolo';
        $url = $post_id ? get_permalink($post_id) : '';
        $message = trim('Buongiorno, vorrei ricevere maggiori informazioni su ' . $title . ($url ? ' ' . $url : ''));

        return 'https://wa.me/' . rawurlencode($digits) . '?text=' . rawurlencode($message);
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

    protected static function promotion_copy_text($promotion) {
        if (!is_array($promotion)) {
            return '';
        }

        $text = trim((string) ($promotion['promo_text'] ?? ''));
        if ($text !== '' && strtolower(remove_accents($text)) === 'promo attiva') {
            $text = '';
        }

        return $text;
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

    protected static function selected_single_header() {
        $settings = self::display_settings();
        $selected = sanitize_key((string) ($settings['style']['single_header'] ?? 'default'));
        $options = class_exists('GPO_Admin') && method_exists('GPO_Admin', 'vehicle_page_header_options')
            ? GPO_Admin::vehicle_page_header_options()
            : ['default' => 'Header predefinito del tema'];

        return isset($options[$selected]) ? $selected : 'default';
    }

    public static function render_single_header() {
        $selected = self::selected_single_header();

        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            $slug = $selected === 'default' ? 'header' : $selected;
            if (function_exists('block_template_part')) {
                block_template_part($slug);
                return;
            }
        }

        if ($selected !== 'default') {
            get_header($selected);
            return;
        }

        get_header();
    }

    public static function back_button_markup($post_id = 0) {
        $post_id = absint($post_id);
        $fallback = $post_id ? get_post_type_archive_link('gpo_vehicle') : '';
        if (!$fallback) {
            $fallback = home_url('/');
        }

        $referer = wp_get_referer();
        if (!$referer) {
            $referer = $fallback;
        }

        return '<a class="gpo-back-link" href="' . esc_url($referer) . '" onclick="if(window.history.length > 1){ window.history.back(); return false; }"><span aria-hidden="true">' . self::icon_markup('chevron-left') . '</span><span>Torna indietro</span></a>';
    }

    public static function lead_form_markup($post_id, $args = []) {
        $post_id = absint($post_id);
        $post_title = $post_id ? get_the_title($post_id) : '';
        $can_submit = self::has_configured_lead_email();
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
        if (!$can_submit) {
            echo '<div class="gpo-lead-notice is-warning" role="status"><p>Le richieste online non sono ancora attive. Configura un indirizzo email destinatario in GestPark Online per iniziare a ricevere contatti dalle schede veicolo.</p></div>';
        }
        echo '<form class="gpo-lead-form" action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
        echo '<input type="hidden" name="action" value="gpo_vehicle_lead" />';
        echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $post_id) . '" />';
        echo '<input type="hidden" name="redirect_to" value="' . esc_url($redirect_url) . '" />';
        wp_nonce_field('gpo_vehicle_lead_' . $post_id, 'gpo_vehicle_lead_nonce');
        echo '<fieldset class="gpo-lead-form__fieldset"' . ($can_submit ? '' : ' disabled aria-disabled="true"') . '>';
        echo '<div class="gpo-lead-grid">';
        echo '<label><span>Nome</span><input type="text" name="gpo_lead[first_name]" required autocomplete="given-name" /></label>';
        echo '<label><span>Cognome</span><input type="text" name="gpo_lead[last_name]" required autocomplete="family-name" /></label>';
        echo '<label><span>Email</span><input type="email" name="gpo_lead[email]" required autocomplete="email" /></label>';
        echo '<label><span>Cellulare</span><input type="tel" name="gpo_lead[phone]" required autocomplete="tel" /></label>';
        echo '</div>';
        echo '<label class="gpo-lead-message"><span>Richiesta</span><textarea name="gpo_lead[message]" rows="5" required placeholder="Vorrei ricevere maggiori informazioni su ' . esc_attr($post_title ?: 'questo veicolo') . '."></textarea></label>';
        echo '<label class="gpo-lead-honeypot" aria-hidden="true"><span>Lascia vuoto</span><input type="text" name="gpo_lead[company]" tabindex="-1" autocomplete="off" /></label>';
        echo '<button class="gpo-button gpo-lead-submit" type="submit"' . ($can_submit ? '' : ' disabled') . '>' . esc_html($args['button_label']) . '</button>';
        echo '</fieldset>';
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
        if ($recipient === '') {
            wp_safe_redirect(add_query_arg([
                'gpo_lead' => 'error',
                'gpo_lead_message' => 'Le richieste online non sono ancora attive. Configura un indirizzo email destinatario in GestPark Online e riprova.',
            ], $redirect));
            exit;
        }

        $vehicle_title = $post_id ? get_the_title($post_id) : 'Veicolo';
        $vehicle_url = $post_id ? get_permalink($post_id) : '';
        $subject = sprintf('Richiesta informazioni - %s', wp_specialchars_decode((string) $vehicle_title, ENT_QUOTES));
        $body = "Nuova richiesta informazioni dal sito web\n\n";
        $body .= "Veicolo\n";
        $body .= "Titolo: " . $vehicle_title . "\n";
        if ($vehicle_url) {
            $body .= "Scheda: " . $vehicle_url . "\n";
        }
        $body .= "\nContatto\n";
        $body .= "Nome: " . trim($first_name . ' ' . $last_name) . "\n";
        $body .= "Email: " . $email . "\n";
        $body .= "Cellulare: " . $phone . "\n";
        $body .= "\nMessaggio\n" . $message . "\n";

        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . trim($first_name . ' ' . $last_name) . ' <' . $email . '>'];
        $sent = wp_mail($recipient, $subject, $body, $headers);

        wp_safe_redirect(add_query_arg([
            'gpo_lead' => $sent ? 'success' : 'error',
            'gpo_lead_message' => $sent ? self::lead_success_message() : 'Non e stato possibile inoltrare la richiesta. Verifica la configurazione dell indirizzo destinatario e riprova.',
        ], $redirect));
        exit;
    }

    public static function vehicle_grid_shortcode($atts) {
        wp_enqueue_style('gpo-public');
        $atts = shortcode_atts([
            'featured' => 'no',
            'limit' => 12,
            'columns' => 3,
            'filters' => 'no',
            'desktop_layout' => 'standard',
            'orderby' => 'date',
            'order' => 'DESC',
            'show' => '',
            'show_desktop' => '',
            'show_tablet' => '',
            'show_mobile' => '',
            'card_layout' => 'default',
            'card_gap' => self::display_settings()['style']['card_gap'] ?? '24',
            'card_padding' => self::display_settings()['style']['card_padding'] ?? '22',
            'content_max_width' => self::display_settings()['style']['content_max_width'] ?? '1280',
            'outer_margin_y' => self::display_settings()['style']['outer_margin_y'] ?? '0',
            'outer_padding_x' => self::display_settings()['style']['outer_padding_x'] ?? '18',
            'section_gap' => self::display_settings()['style']['section_gap'] ?? '24',
            'filter_fields' => '',
            'filter_fields_desktop' => '',
            'filter_fields_tablet' => '',
            'filter_fields_mobile' => '',
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
            'button_text_color' => '',
            'primary_button_label' => 'Scheda veicolo',
        ], $atts, 'gestpark_vehicle_grid');

        $query = self::build_vehicle_query($atts);
        $display = self::resolve_card_display($atts);
        $desktop_layout = sanitize_key($atts['desktop_layout'] ?? 'standard');
        if (!in_array($desktop_layout, ['standard', 'marketplace-sidebar'], true)) {
            $desktop_layout = 'standard';
        }
        $display['context'] = !empty($atts['context'])
            ? sanitize_key($atts['context'])
            : (($atts['filters'] ?? 'no') === 'yes' ? 'catalog' : 'default');
        $wrapper_style = self::wrapper_style($atts);
        $catalog_classes = ['gpo-catalog-shell', 'gpo-catalog-shell--layout-' . $desktop_layout];
        $is_marketplace_sidebar = ($atts['filters'] === 'yes' && $desktop_layout === 'marketplace-sidebar');

        ob_start();
        echo '<div class="' . esc_attr(implode(' ', $catalog_classes)) . '" style="' . esc_attr($wrapper_style) . '">';
        if ($is_marketplace_sidebar) {
            wp_enqueue_script('gpo-filters');
            echo '<div class="gpo-catalog-layout gpo-catalog-layout--marketplace-sidebar">';
            echo '<aside class="gpo-catalog-layout__sidebar">';
            echo self::render_filter_form($atts['filter_fields'], [
                'desktop' => $atts['filter_fields_desktop'] ?? '',
                'tablet' => $atts['filter_fields_tablet'] ?? '',
                'mobile' => $atts['filter_fields_mobile'] ?? '',
            ], [
                'layout' => $desktop_layout,
                'omit_sort' => true,
            ]);
            echo '</aside>';
            echo '<div class="gpo-catalog-layout__results">';
            self::render_results_header($query, false, [
                'layout' => $desktop_layout,
                'show_limit_control' => true,
                'current_limit' => absint($atts['limit']),
            ]);
        } else {
            if ($atts['filters'] === 'yes') {
                wp_enqueue_script('gpo-filters');
                echo self::render_filter_form($atts['filter_fields'], [
                    'desktop' => $atts['filter_fields_desktop'] ?? '',
                    'tablet' => $atts['filter_fields_tablet'] ?? '',
                    'mobile' => $atts['filter_fields_mobile'] ?? '',
                ], [
                    'layout' => $desktop_layout,
                ]);
            }
            self::render_results_header($query, false, [
                'layout' => $desktop_layout,
                'current_limit' => absint($atts['limit']),
            ]);
        }
        echo '<div class="gpo-grid columns-' . absint($atts['columns']) . '">';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                self::render_card(get_the_ID(), $display);
            }
        } else {
            echo '<div class="gpo-empty-state"><h3>Nessun veicolo disponibile</h3><p>Non sono presenti veicoli che corrispondono ai filtri selezionati. Aggiorna i criteri di ricerca oppure verifica l inventario pubblicato.</p></div>';
        }
        echo '</div>';
        if ($is_marketplace_sidebar) {
            echo '</div></div>';
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function featured_vehicle_shortcode($atts) {
        wp_enqueue_style('gpo-public');
        $atts = shortcode_atts([
            'layout' => 'hero',
            'show' => '',
            'show_desktop' => '',
            'show_tablet' => '',
            'show_mobile' => '',
            'card_layout' => 'default',
            'card_gap' => self::display_settings()['style']['card_gap'] ?? '24',
            'card_padding' => self::display_settings()['style']['card_padding'] ?? '22',
            'content_max_width' => self::display_settings()['style']['content_max_width'] ?? '1280',
            'outer_margin_y' => self::display_settings()['style']['outer_margin_y'] ?? '0',
            'outer_padding_x' => self::display_settings()['style']['outer_padding_x'] ?? '18',
            'section_gap' => self::display_settings()['style']['section_gap'] ?? '24',
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
            'button_text_color' => '',
            'primary_button_label' => 'Scheda veicolo',
        ], $atts, 'gestpark_featured_vehicle');
        $display = self::resolve_card_display($atts);

        $featured_id = self::active_featured_vehicle_id();
        ob_start();
        if (!empty($featured_id)) {
            echo '<div class="gpo-featured-single" style="' . esc_attr(self::wrapper_style($atts)) . '">';
            if ($atts['layout'] === 'hero') {
                $display['hero'] = true;
            }
            self::render_card($featured_id, $display);
            echo '</div>';
        } else {
            echo self::empty_state_markup(
                'Nessun veicolo disponibile',
                'Non sono presenti veicoli in evidenza al momento. Verifica la connessione dati o aggiorna l inventario pubblicato.'
            );
        }
        return ob_get_clean();
    }

    public static function featured_carousel_shortcode($atts) {
        wp_enqueue_style('gpo-public');
        wp_enqueue_script('gpo-carousel');
        $atts = shortcode_atts([
            'limit' => 12,
            'autoplay' => 'yes',
            'interval' => 5000,
            'source' => 'showcase',
            'vehicle_id' => 0,
            'show' => '',
            'show_desktop' => '',
            'show_tablet' => '',
            'show_mobile' => '',
            'card_layout' => 'default',
            'items_per_page' => 3,
            'show_title' => 'yes',
            'section_title' => 'Veicoli selezionati',
            'card_gap' => self::display_settings()['style']['card_gap'] ?? '24',
            'card_padding' => self::display_settings()['style']['card_padding'] ?? '22',
            'content_max_width' => self::display_settings()['style']['content_max_width'] ?? '1280',
            'outer_margin_y' => self::display_settings()['style']['outer_margin_y'] ?? '0',
            'outer_padding_x' => self::display_settings()['style']['outer_padding_x'] ?? '18',
            'section_gap' => self::display_settings()['style']['section_gap'] ?? '24',
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
            'button_text_color' => '',
            'primary_button_label' => 'Scheda veicolo',
        ], $atts, 'gestpark_featured_carousel');
        $display = self::resolve_card_display($atts);
        $items_per_page = max(1, min(4, absint($atts['items_per_page'] ?? 3)));
        $show_title = ($atts['show_title'] ?? 'yes') !== 'no';
        $section_title = trim((string) ($atts['section_title'] ?? 'Veicoli selezionati'));
        if ($section_title === '') {
            $section_title = 'Veicoli selezionati';
        }

        $source = sanitize_key($atts['source'] ?? 'showcase');
        if (!in_array($source, ['showcase', 'related_brand'], true)) {
            $source = 'showcase';
        }
        $vehicle_id = absint($atts['vehicle_id'] ?? 0);
        $ids = ($source === 'related_brand' && $vehicle_id > 0)
            ? self::related_brand_vehicle_ids($vehicle_id, absint($atts['limit']))
            : self::active_showcase_vehicle_ids(absint($atts['limit']));
        $display['context'] = ($source === 'showcase') ? 'showcase' : 'vehicle-carousel';
        ob_start();
        echo '<div class="gpo-carousel-shell" style="' . esc_attr(self::wrapper_style($atts) . '--gpo-carousel-items-per-page:' . $items_per_page . ';') . '">';
        echo '<div class="gpo-section-head' . ($show_title ? '' : ' is-title-hidden') . '">';
        if ($show_title) {
            echo '<div><span class="gpo-kicker">Vetrina</span><h2>' . esc_html($section_title) . '</h2></div>';
        }
        echo '<div class="gpo-carousel-nav"><button class="gpo-carousel-prev" type="button" aria-label="Precedente">' . self::icon_markup('chevron-left') . '</button><button class="gpo-carousel-next" type="button" aria-label="Successivo">' . self::icon_markup('chevron-right') . '</button></div></div>';
        echo '<div class="gpo-carousel" data-gpo-carousel="1" data-autoplay="' . esc_attr($atts['autoplay']) . '" data-interval="' . absint($atts['interval']) . '" data-loop="yes"><div class="gpo-carousel-track">';
        if (!empty($ids)) {
            foreach ($ids as $post_id) {
                echo '<div class="gpo-carousel-slide">';
                self::render_card($post_id, $display);
                echo '</div>';
            }
        } else {
            echo self::empty_state_markup(
                'Nessun veicolo disponibile',
                'Non sono presenti veicoli in vetrina al momento. Verifica la connessione dati o aggiorna l inventario pubblicato.'
            );
        }
        echo '</div><div class="gpo-carousel-dots" aria-hidden="true"></div></div></div>';
        return ob_get_clean();
    }

    public static function vehicle_filters_shortcode() {
        wp_enqueue_style('gpo-public');
        wp_enqueue_script('gpo-filters');
        return self::render_filter_form('');
    }

    public static function vehicle_catalog_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 12,
            'columns' => 3,
            'desktop_layout' => 'standard',
            'orderby' => 'date',
            'order' => 'DESC',
            'show' => '',
            'card_layout' => 'default',
            'card_gap' => self::display_settings()['style']['card_gap'] ?? '24',
            'card_padding' => self::display_settings()['style']['card_padding'] ?? '22',
            'content_max_width' => self::display_settings()['style']['content_max_width'] ?? '1280',
            'outer_margin_y' => self::display_settings()['style']['outer_margin_y'] ?? '0',
            'outer_padding_x' => self::display_settings()['style']['outer_padding_x'] ?? '18',
            'section_gap' => self::display_settings()['style']['section_gap'] ?? '24',
            'filter_fields' => '',
            'filter_fields_desktop' => '',
            'filter_fields_tablet' => '',
            'filter_fields_mobile' => '',
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
            'button_text_color' => '',
            'primary_button_label' => 'Scheda veicolo',
        ], $atts, 'gestpark_vehicle_catalog');

        return self::vehicle_grid_shortcode([
            'limit' => $atts['limit'],
            'columns' => $atts['columns'],
            'desktop_layout' => $atts['desktop_layout'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'filters' => 'yes',
            'show' => $atts['show'],
            'show_desktop' => $atts['show_desktop'] ?? '',
            'show_tablet' => $atts['show_tablet'] ?? '',
            'show_mobile' => $atts['show_mobile'] ?? '',
            'card_layout' => $atts['card_layout'],
            'card_gap' => $atts['card_gap'],
            'card_padding' => $atts['card_padding'],
            'content_max_width' => $atts['content_max_width'],
            'outer_margin_y' => $atts['outer_margin_y'],
            'outer_padding_x' => $atts['outer_padding_x'],
            'section_gap' => $atts['section_gap'],
            'filter_fields' => $atts['filter_fields'],
            'filter_fields_desktop' => $atts['filter_fields_desktop'] ?? '',
            'filter_fields_tablet' => $atts['filter_fields_tablet'] ?? '',
            'filter_fields_mobile' => $atts['filter_fields_mobile'] ?? '',
            'context' => 'catalog',
        ]);
    }

    protected static function wrapper_style($atts = []) {
        $settings = self::display_settings();
        $style_defaults = isset($settings['style']) && is_array($settings['style']) ? $settings['style'] : [];
        $merged = wp_parse_args($atts, [
            'card_gap' => $style_defaults['card_gap'] ?? 24,
            'card_padding' => $style_defaults['card_padding'] ?? 22,
            'content_max_width' => $style_defaults['content_max_width'] ?? 1280,
            'outer_margin_y' => $style_defaults['outer_margin_y'] ?? 0,
            'outer_padding_x' => $style_defaults['outer_padding_x'] ?? 18,
            'section_gap' => $style_defaults['section_gap'] ?? 24,
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
            'button_text_color' => '',
        ]);

        return '--gpo-card-gap:' . absint($merged['card_gap']) . 'px;' .
            '--gpo-card-padding:' . absint($merged['card_padding']) . 'px;' .
            '--gpo-content-max-width:' . absint($merged['content_max_width']) . 'px;' .
            '--gpo-shell-margin-y:' . absint($merged['outer_margin_y']) . 'px;' .
            '--gpo-shell-padding-x:' . absint($merged['outer_padding_x']) . 'px;' .
            '--gpo-section-gap:' . absint($merged['section_gap']) . 'px;' .
            (!empty($merged['primary_color']) ? '--gpo-primary:' . sanitize_text_field($merged['primary_color']) . ';' : '') .
            (!empty($merged['accent_color']) ? '--gpo-accent:' . sanitize_text_field($merged['accent_color']) . ';' : '') .
            (!empty($merged['bg_color']) ? '--gpo-bg:' . sanitize_text_field($merged['bg_color']) . ';' : '') .
            (!empty($merged['text_color']) ? '--gpo-local-text:' . sanitize_text_field($merged['text_color']) . ';' : '') .
            (!empty($merged['button_color']) ? '--gpo-button-bg:' . sanitize_text_field($merged['button_color']) . ';' : '') .
            (!empty($merged['button_text_color']) ? '--gpo-button-text:' . sanitize_text_field($merged['button_text_color']) . ';' : '');
    }

    protected static function resolve_card_display($atts = []) {
        $visible = self::default_card_elements();
        if (!empty($atts['show'])) {
            $visible = self::parse_show_string($atts['show']);
        }
        $visible_desktop = self::device_visible_elements($atts, 'desktop', $visible);
        $visible_tablet = self::device_visible_elements($atts, 'tablet', $visible);
        $visible_mobile = self::device_visible_elements($atts, 'mobile', $visible);
        return [
            'layout' => sanitize_key($atts['card_layout'] ?? 'default'),
            'visible' => $visible,
            'visible_desktop' => $visible_desktop,
            'visible_tablet' => $visible_tablet,
            'visible_mobile' => $visible_mobile,
            'hero' => !empty($atts['hero']),
            'primary_button_label' => sanitize_text_field($atts['primary_button_label'] ?? 'Scheda veicolo'),
        ];
    }

    public static function single_display() {
        $settings = self::display_settings();
        $sections = [
            'gallery',
            'summary',
            'description',
            'notes',
            'specs',
            'accessories',
            'contact_box',
            'strengths',
        ];
        return [
            'layout' => sanitize_key($settings['style']['single_layout'] ?? 'classic'),
            'visible' => $sections,
        ];
    }

    protected static function parse_show_string($show) {
        $items = array_filter(array_map('trim', explode(',', (string) $show)));
        return array_values(array_unique(array_map('sanitize_key', $items)));
    }

    protected static function truthy_value($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim(remove_accents((string) $value)));
        return in_array($normalized, ['1', 'true', 'yes', 'si', 'on'], true);
    }

    public static function is_neopatentati_vehicle($post_id = 0, $data = []) {
        $value = '';

        if (isset($data['neopatentati']) && $data['neopatentati'] !== '') {
            $value = $data['neopatentati'];
        } elseif (isset($data['neo_patentati']) && $data['neo_patentati'] !== '') {
            $value = $data['neo_patentati'];
        }

        $post_id = absint($post_id);
        if ($value === '' && $post_id) {
            $value = get_post_meta($post_id, '_gpo_neopatentati', true);
        }
        if ($value === '' && $post_id) {
            $value = get_post_meta($post_id, '_gpo_neo_patentati', true);
        }

        return self::truthy_value($value);
    }

    protected static function quick_info_items($post_id, $data = []) {
        $data = is_array($data) ? $data : [];

        $condition = $data['condition'] ?? get_post_meta($post_id, '_gpo_condition', true);
        $year = $data['year'] ?? get_post_meta($post_id, '_gpo_year', true);
        $mileage_raw = $data['mileage'] ?? get_post_meta($post_id, '_gpo_mileage', true);

        return array_values(array_filter([
            [
                'key' => 'condition',
                'label' => 'Condizione',
                'value' => $condition,
                'icon' => 'car',
            ],
            [
                'key' => 'year',
                'label' => 'Anno',
                'value' => $year,
                'icon' => 'calendar',
            ],
            [
                'key' => 'mileage',
                'label' => 'KM',
                'value' => self::format_number($mileage_raw, ' km'),
                'icon' => 'road',
            ],
        ], function ($item) {
            return !empty($item['value']);
        }));
    }

    public static function quick_info_panel_markup($post_id, $class = 'gpo-quick-info-panel', $item_classes = [], $data = []) {
        $items = self::quick_info_items($post_id, $data);
        if (empty($items)) {
            return '';
        }

        $html = '<div class="' . esc_attr(trim($class)) . '">';
        foreach ($items as $item) {
            $item_class = $item_classes[$item['key']] ?? '';
            $html .= '<span class="' . esc_attr(trim('gpo-quick-info-panel__item ' . $item_class)) . '">';
            $html .= '<span class="gpo-quick-info-panel__icon" aria-hidden="true">' . self::icon_markup($item['icon']) . '</span>';
            $html .= '<span class="gpo-quick-info-panel__copy">';
            $html .= '<small>' . esc_html($item['label']) . '</small>';
            $html .= '<strong>' . esc_html($item['value']) . '</strong>';
            $html .= '</span>';
            $html .= '</span>';
        }
        $html .= '</div>';

        return $html;
    }

    public static function neopatentati_badge_markup($post_id, $class = 'gpo-neo-badge', $data = []) {
        if (!self::is_neopatentati_vehicle($post_id, $data)) {
            return '';
        }

        return '<span class="' . esc_attr(trim($class)) . '"><span class="gpo-neo-badge__icon" aria-hidden="true">' . self::icon_markup('check-circle') . '</span><span>Neopatentati</span></span>';
    }

    protected static function single_technical_items($post_id, $data = []) {
        $data = is_array($data) ? $data : [];

        return array_values(array_filter([
            [
                'label' => 'Alimentazione',
                'value' => (string) ($data['fuel'] ?? get_post_meta($post_id, '_gpo_fuel', true)),
                'icon' => 'fuel',
            ],
            [
                'label' => 'Carrozzeria',
                'value' => (string) ($data['body_type'] ?? get_post_meta($post_id, '_gpo_body_type', true)),
                'icon' => 'car',
            ],
            [
                'label' => 'Cambio',
                'value' => (string) ($data['transmission'] ?? get_post_meta($post_id, '_gpo_transmission', true)),
                'icon' => 'gear',
            ],
            [
                'label' => 'Cilindrata',
                'value' => self::format_engine_size($data['engine_size'] ?? get_post_meta($post_id, '_gpo_engine_size', true)),
                'icon' => 'engine',
            ],
            [
                'label' => 'Potenza',
                'value' => (string) ($data['power'] ?? get_post_meta($post_id, '_gpo_power', true)),
                'icon' => 'bolt',
            ],
            [
                'label' => 'Colore',
                'value' => (string) ($data['color'] ?? get_post_meta($post_id, '_gpo_color', true)),
                'icon' => 'palette',
            ],
            [
                'label' => 'Porte',
                'value' => (string) ($data['doors'] ?? get_post_meta($post_id, '_gpo_doors', true)),
                'icon' => 'door',
            ],
            [
                'label' => 'Posti',
                'value' => (string) ($data['seats'] ?? get_post_meta($post_id, '_gpo_seats', true)),
                'icon' => 'users',
            ],
            [
                'label' => 'Sede',
                'value' => (string) ($data['location'] ?? get_post_meta($post_id, '_gpo_location', true)),
                'icon' => 'pin',
            ],
        ], function ($item) {
            return trim((string) ($item['value'] ?? '')) !== '';
        }));
    }

    public static function single_technical_badges_markup($post_id, $data = []) {
        $items = self::single_technical_items($post_id, $data);
        if (empty($items)) {
            return '';
        }

        $html = '<div class="gpo-single-tech-grid">';
        foreach ($items as $item) {
            $html .= '<span class="gpo-single-tech-badge">';
            $html .= '<span class="gpo-single-tech-badge__icon" aria-hidden="true">' . self::icon_markup($item['icon']) . '</span>';
            $html .= '<span class="gpo-single-tech-badge__copy">';
            $html .= '<small>' . esc_html($item['label']) . '</small>';
            $html .= '<strong>' . esc_html($item['value']) . '</strong>';
            $html .= '</span>';
            $html .= '</span>';
        }
        $html .= '</div>';

        return $html;
    }

    public static function single_strengths_card_markup($post_id, $data = []) {
        $data = is_array($data) ? $data : [];
        $items = [];

        if (!empty($data['condition'])) {
            $items[] = 'Condizione veicolo: ' . $data['condition'];
        }
        if (!empty($data['fuel'])) {
            $items[] = 'Alimentazione: ' . $data['fuel'];
        }
        if (!empty($data['transmission'])) {
            $items[] = 'Cambio: ' . $data['transmission'];
        }
        if (!empty($data['location'])) {
            $items[] = 'Disponibile in sede a ' . $data['location'];
        }
        if (!empty($data['neopatentati'])) {
            $items[] = 'Compatibile anche con neopatentati';
        }
        if (!empty($data['promotion'])) {
            $items[] = 'Promozione commerciale attiva sul prezzo esposto';
        }

        $items = array_values(array_unique(array_filter($items)));
        if (count($items) < 3) {
            $items[] = 'Contatto diretto con il concessionario per dettagli e disponibilita.';
        }
        if (count($items) < 4) {
            $items[] = 'Valutazione commerciale e prova su strada su richiesta.';
        }

        return '<div class="gpo-side-card gpo-side-card--strengths"><h3>Punti di Forza</h3>' . self::icon_list_markup(array_slice($items, 0, 4)) . '</div>';
    }

    public static function share_actions_markup($post_id) {
        $post_id = absint($post_id);
        $url = $post_id ? get_permalink($post_id) : '';
        if (!$url) {
            return '';
        }

        $title = trim(wp_strip_all_tags(get_the_title($post_id)));
        $whatsapp_url = 'https://wa.me/?text=' . rawurlencode(trim($title . ' ' . $url));
        $contact_whatsapp_url = self::whatsapp_chat_url($post_id);

        $html = '<div class="gpo-share-stack">';
        $html .= '<div class="gpo-share-actions" aria-label="Azioni di condivisione">';
        $html .= '<button type="button" class="gpo-share-action" data-gpo-copy-link="' . esc_url($url) . '" data-gpo-copy-label="Link copiato">';
        $html .= '<span class="gpo-share-action__icon" aria-hidden="true">' . self::icon_markup('copy') . '</span>';
        $html .= '<span>COPIA LINK</span>';
        $html .= '</button>';
        $html .= '<a class="gpo-share-action gpo-share-action--whatsapp" href="' . esc_url($whatsapp_url) . '" target="_blank" rel="noopener noreferrer">';
        $html .= '<span class="gpo-share-action__icon" aria-hidden="true">' . self::icon_markup('whatsapp') . '</span>';
        $html .= '<span>CONDIVIDI SU WHATSAPP</span>';
        $html .= '</a>';
        $html .= '</div>';
        if ($contact_whatsapp_url) {
            $html .= '<a class="gpo-share-cta gpo-share-cta--whatsapp" href="' . esc_url($contact_whatsapp_url) . '" target="_blank" rel="noopener noreferrer">';
            $html .= '<span class="gpo-share-action__icon" aria-hidden="true">' . self::icon_markup('whatsapp') . '</span>';
            $html .= '<span>CONTATTACI ORA</span>';
            $html .= '</a>';
        } else {
            $html .= '<a class="gpo-share-cta gpo-share-cta--fallback" href="#richiesta-info">';
            $html .= '<span class="gpo-share-action__icon" aria-hidden="true">' . self::icon_markup('whatsapp') . '</span>';
            $html .= '<span>CONTATTACI ORA</span>';
            $html .= '</a>';
        }
        $html .= '</div>';

        return $html;
    }

    protected static function device_visible_elements($atts, $device, $fallback) {
        $underscored = 'show_' . $device;
        $camel = 'show' . ucfirst($device);
        $raw = '';

        if (isset($atts[$underscored])) {
            $raw = (string) $atts[$underscored];
        } elseif (isset($atts[$camel])) {
            $raw = (string) $atts[$camel];
        }

        $parsed = self::parse_show_string($raw);
        return !empty($parsed) ? $parsed : $fallback;
    }

    protected static function visibility_classes($display, $key) {
        $desktop = !empty($display['visible_desktop']) ? $display['visible_desktop'] : ($display['visible'] ?? []);
        $tablet = !empty($display['visible_tablet']) ? $display['visible_tablet'] : ($display['visible'] ?? []);
        $mobile = !empty($display['visible_mobile']) ? $display['visible_mobile'] : ($display['visible'] ?? []);

        return implode(' ', array_filter([
            'gpo-device-field',
            in_array($key, $desktop, true) ? 'gpo-show-desktop' : 'gpo-hide-desktop',
            in_array($key, $tablet, true) ? 'gpo-show-tablet' : 'gpo-hide-tablet',
            in_array($key, $mobile, true) ? 'gpo-show-mobile' : 'gpo-hide-mobile',
        ]));
    }

    protected static function is_visible($display, $key) {
        return in_array($key, $display['visible_desktop'] ?? [], true)
            || in_array($key, $display['visible_tablet'] ?? [], true)
            || in_array($key, $display['visible_mobile'] ?? [], true)
            || in_array($key, $display['visible'] ?? [], true);
    }

    protected static function build_vehicle_query($atts = []) {
        $limit = max(1, absint($atts['limit'] ?? 12));
        $requested_limit = absint(self::request_value('gpo_limit'));
        if ($requested_limit > 0) {
            $limit = max(6, min(48, $requested_limit));
        }
        $meta_query = [
            self::exclude_demo_vehicle_meta_clause(),
        ];

        if (($atts['featured'] ?? 'no') === 'yes') {
            $meta_query[] = ['key' => '_gpo_featured', 'value' => '1'];
        }

        $condition = self::request_values('gpo_condition');
        $fuel = self::request_values('gpo_fuel');
        $body = self::request_values('gpo_body_type');
        $transmission = self::request_values('gpo_transmission');
        $brand = self::request_values('gpo_brand');
        $brand_key = self::request_values('gpo_brand_key');
        $search = self::request_value('gpo_search');
        $min_price = self::request_value('gpo_min_price');
        $max_price = self::request_value('gpo_max_price');
        $max_mileage = self::request_value('gpo_max_mileage');
        $year = self::request_values('gpo_year');

        if (!empty($condition)) {
            $meta_query[] = ['key' => '_gpo_condition', 'value' => $condition, 'compare' => 'IN'];
        }
        if (!empty($fuel)) {
            $meta_query[] = ['key' => '_gpo_fuel', 'value' => $fuel, 'compare' => 'IN'];
        }
        if (!empty($body)) {
            $meta_query[] = ['key' => '_gpo_body_type', 'value' => $body, 'compare' => 'IN'];
        }
        if (!empty($transmission)) {
            $meta_query[] = ['key' => '_gpo_transmission', 'value' => $transmission, 'compare' => 'IN'];
        }
        $brand_meta_query = self::brand_filters_meta_clause($brand_key, $brand);
        if (!empty($brand_meta_query)) {
            $meta_query[] = $brand_meta_query;
        }
        if (!empty($year)) {
            $meta_query[] = ['key' => '_gpo_year', 'value' => $year, 'compare' => 'IN'];
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

    protected static function brand_filters_meta_clause($brand_keys = [], $brands = []) {
        $clauses = [];

        foreach ((array) $brand_keys as $brand_key) {
            $brand_key = sanitize_text_field((string) $brand_key);
            if ($brand_key === '') {
                continue;
            }
            $clauses[] = self::brand_meta_query($brand_key);
        }

        foreach ((array) $brands as $brand) {
            $brand = sanitize_text_field((string) $brand);
            if ($brand === '') {
                continue;
            }
            $clauses[] = ['key' => '_gpo_brand', 'value' => $brand];
        }

        if (empty($clauses)) {
            return [];
        }

        if (count($clauses) === 1) {
            return $clauses[0];
        }

        return array_merge(['relation' => 'OR'], $clauses);
    }

    protected static function render_results_header($query, $compact = false, $options = []) {
        $found = absint($query->found_posts);
        $sort = self::request_value('gpo_sort') ?: 'date_desc';
        $layout = sanitize_key($options['layout'] ?? 'standard');
        $show_limit_control = !empty($options['show_limit_control']);
        $current_limit = max(6, min(48, absint(self::request_value('gpo_limit') ?: ($options['current_limit'] ?? 12))));
        $classes = ['gpo-results-head'];
        if ($compact) {
            $classes[] = 'gpo-results-head-compact';
        }
        if ($layout === 'marketplace-sidebar') {
            $classes[] = 'gpo-results-head--marketplace';
        }

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
        echo '<div class="gpo-results-head__summary"><strong>' . esc_html((string) $found) . '</strong> veicoli trovati</div>';
        echo '<div class="gpo-results-head__controls">';
        if ($show_limit_control) {
            echo '<div class="gpo-sort-wrap gpo-sort-wrap--limit"><label for="gpo-limit">Mostra</label><select id="gpo-limit" name="gpo_limit" form="gpo-filter-form">';
            foreach ([12, 24, 36, 48] as $limit_option) {
                echo '<option value="' . esc_attr((string) $limit_option) . '" ' . selected($current_limit, $limit_option, false) . '>' . esc_html((string) $limit_option) . '</option>';
            }
            echo '</select></div>';
        }
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
        echo '</select></div></div></div>';
    }

    protected static function render_filter_form($override_fields = '', $responsive_visibility = [], $options = []) {
        $action = get_permalink() ?: home_url('/');
        $layout = sanitize_key($options['layout'] ?? 'standard');
        $omit_sort = !empty($options['omit_sort']);
        $catalog_values = [
            'condition' => self::distinct_meta_values('_gpo_condition'),
            'fuel' => self::distinct_meta_values('_gpo_fuel'),
            'body_type' => self::distinct_meta_values('_gpo_body_type'),
            'transmission' => self::distinct_meta_values('_gpo_transmission'),
            'brand' => self::distinct_meta_values('_gpo_brand'),
            'year' => self::distinct_meta_values('_gpo_year', true),
        ];
        $visible_filters = self::resolve_filter_fields($override_fields);
        $device_visibility = [
            'desktop' => self::device_filter_fields($responsive_visibility, 'desktop', $visible_filters),
            'tablet' => self::device_filter_fields($responsive_visibility, 'tablet', $visible_filters),
            'mobile' => self::device_filter_fields($responsive_visibility, 'mobile', $visible_filters),
        ];
        $panel_body_id = function_exists('wp_unique_id') ? wp_unique_id('gpo-filter-panel-') : 'gpo-filter-panel-body';

        ob_start();
        echo '<form id="gpo-filter-form" class="gpo-filter-panel' . ($layout === 'marketplace-sidebar' ? ' gpo-filter-panel--marketplace-sidebar' : '') . '" data-gpo-filter-panel="1" method="get" action="' . esc_url($action) . '">';
        foreach (self::request_values('gpo_brand_key') as $brand_key_value) {
            echo '<input type="hidden" name="gpo_brand_key[]" value="' . esc_attr($brand_key_value) . '" />';
        }
        if (self::request_value('gpo_catalog_ref') !== '') {
            echo '<input type="hidden" name="gpo_catalog_ref" value="' . esc_attr(self::request_value('gpo_catalog_ref')) . '" />';
        }
        if (self::request_value('gpo_search') !== '') {
            echo '<input type="hidden" name="gpo_search" value="' . esc_attr(self::request_value('gpo_search')) . '" />';
        }
        echo '<button class="gpo-filter-toggle" type="button" aria-expanded="true" aria-controls="' . esc_attr($panel_body_id) . '">';
        echo '<span class="gpo-filter-toggle__copy"><strong>Filtri catalogo</strong><small>Affina il parco auto per marca, prezzo e caratteristiche.</small></span>';
        echo '<span class="gpo-filter-toggle__icon" aria-hidden="true">' . self::icon_markup('chevron-right') . '</span>';
        echo '</button>';
        echo '<div class="gpo-filter-panel__body" id="' . esc_attr($panel_body_id) . '">';
        echo '<div class="gpo-filter-grid">';
        if (in_array('condition', $visible_filters, true)) { self::render_filter_select('Condizione', 'gpo_condition', $catalog_values['condition'], self::filter_visibility_classes($device_visibility, 'condition')); }
        if (in_array('brand', $visible_filters, true)) { self::render_filter_select('Marca', 'gpo_brand', $catalog_values['brand'], self::filter_visibility_classes($device_visibility, 'brand')); }
        if (in_array('fuel', $visible_filters, true)) { self::render_filter_select('Alimentazione', 'gpo_fuel', $catalog_values['fuel'], self::filter_visibility_classes($device_visibility, 'fuel')); }
        if (in_array('body_type', $visible_filters, true)) { self::render_filter_select('Carrozzeria', 'gpo_body_type', $catalog_values['body_type'], self::filter_visibility_classes($device_visibility, 'body_type')); }
        if (in_array('transmission', $visible_filters, true)) { self::render_filter_select('Cambio', 'gpo_transmission', $catalog_values['transmission'], self::filter_visibility_classes($device_visibility, 'transmission')); }
        if (in_array('year', $visible_filters, true)) { self::render_filter_select('Anno', 'gpo_year', $catalog_values['year'], self::filter_visibility_classes($device_visibility, 'year')); }
        if (in_array('min_price', $visible_filters, true)) { self::render_filter_input('Prezzo min', 'gpo_min_price', self::request_value('gpo_min_price'), 'number', '0', self::filter_visibility_classes($device_visibility, 'min_price')); }
        if (in_array('max_price', $visible_filters, true)) { self::render_filter_input('Prezzo max', 'gpo_max_price', self::request_value('gpo_max_price'), 'number', '50000', self::filter_visibility_classes($device_visibility, 'max_price')); }
        if (in_array('max_mileage', $visible_filters, true)) { self::render_filter_input('KM max', 'gpo_max_mileage', self::request_value('gpo_max_mileage'), 'number', '60000', self::filter_visibility_classes($device_visibility, 'max_mileage')); }
        if (!$omit_sort && in_array('sort', $visible_filters, true)) {
            echo '<label class="' . esc_attr(trim('gpo-filter-control gpo-filter-control--select ' . self::filter_visibility_classes($device_visibility, 'sort'))) . '"><span class="gpo-filter-control__label">Ordina per</span><select class="gpo-filter-control__field" name="gpo_sort">';
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
        echo '<div class="gpo-filter-actions">';
        echo '<button class="gpo-button" type="submit">Applica filtri</button>';
        echo '<a class="gpo-button gpo-button-secondary" href="' . esc_url(self::filter_reset_url($action)) . '">Reset</a>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        return ob_get_clean();
    }

    protected static function resolve_filter_fields($override_fields = '') {
        $fields = [];
        if (!empty($override_fields)) {
            $fields = self::parse_show_string($override_fields);
        } else {
            $fields = self::default_filter_fields();
        }

        return array_values(array_filter($fields, function ($field) {
            return $field !== 'search';
        }));
    }

    protected static function device_filter_fields($visibility, $device, $fallback) {
        $raw = '';

        if (isset($visibility[$device])) {
            $raw = (string) $visibility[$device];
        } elseif (isset($visibility['filter_fields_' . $device])) {
            $raw = (string) $visibility['filter_fields_' . $device];
        }

        $parsed = array_values(array_filter(self::parse_show_string($raw), function ($field) {
            return $field !== 'search';
        }));

        return !empty($parsed) ? $parsed : $fallback;
    }

    protected static function filter_visibility_classes($visibility, $key) {
        $desktop = $visibility['desktop'] ?? [];
        $tablet = $visibility['tablet'] ?? [];
        $mobile = $visibility['mobile'] ?? [];

        return implode(' ', array_filter([
            'gpo-device-field',
            in_array($key, $desktop, true) ? 'gpo-show-desktop' : 'gpo-hide-desktop',
            in_array($key, $tablet, true) ? 'gpo-show-tablet' : 'gpo-hide-tablet',
            in_array($key, $mobile, true) ? 'gpo-show-mobile' : 'gpo-hide-mobile',
        ]));
    }

    protected static function render_filter_select($label, $name, $values, $extra_class = '') {
        $current = self::request_values($name);
        echo '<div class="' . esc_attr(trim('gpo-filter-control gpo-filter-control--multi-select ' . $extra_class)) . '" data-gpo-filter-multi="' . esc_attr($name) . '">';
        echo '<span class="gpo-filter-control__label">' . esc_html($label) . '</span>';
        echo '<div class="gpo-filter-multi-select">';
        echo '<select class="gpo-filter-control__field gpo-filter-control__picker" data-gpo-filter-picker="' . esc_attr($name) . '" aria-label="' . esc_attr($label) . '">';
        echo '<option value="">Seleziona</option>';
        foreach ($values as $value) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($value) . '</option>';
        }
        if (empty($values)) {
            echo '<option value="" disabled>Nessun valore disponibile</option>';
        }
        echo '</select>';
        echo '<div class="gpo-filter-control__values" data-gpo-filter-values="' . esc_attr($name) . '">';
        foreach ($current as $value) {
            echo self::filter_hidden_value_input_markup($name, $value);
        }
        echo '</div>';
        echo '<div class="gpo-filter-selected-chips" data-gpo-filter-chips="' . esc_attr($name) . '"' . (empty($current) ? ' hidden' : '') . '>';
        foreach ($current as $value) {
            echo self::filter_selected_chip_markup($name, $value);
        }
        echo '</div>';
        echo '</div></div>';
    }

    protected static function render_filter_input($label, $name, $value, $type = 'text', $placeholder = '', $extra_class = '') {
        echo '<label class="' . esc_attr(trim('gpo-filter-control gpo-filter-control--input ' . $extra_class)) . '"><span class="gpo-filter-control__label">' . esc_html($label) . '</span><input class="gpo-filter-control__field" type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" /></label>';
    }

    protected static function filter_hidden_value_input_markup($name, $value) {
        return '<input type="hidden" name="' . esc_attr($name) . '[]" value="' . esc_attr($value) . '" data-gpo-filter-value="' . esc_attr($value) . '" />';
    }

    protected static function filter_selected_chip_markup($name, $value) {
        return '<button class="gpo-filter-selected-chip" type="button" data-gpo-filter-chip-remove="' . esc_attr($name) . '" data-value="' . esc_attr($value) . '"><span class="gpo-filter-selected-chip__text">' . esc_html($value) . '</span><span class="gpo-filter-selected-chip__remove" aria-hidden="true">&times;</span></button>';
    }

    protected static function active_filter_chips_markup($action) {
        $chips = [];
        $definitions = [
            'gpo_condition' => 'Condizione',
            'gpo_brand' => 'Marca',
            'gpo_fuel' => 'Alimentazione',
            'gpo_body_type' => 'Carrozzeria',
            'gpo_transmission' => 'Cambio',
            'gpo_year' => 'Anno',
        ];

        foreach ($definitions as $key => $label) {
            foreach (self::request_values($key) as $value) {
                $chips[] = [
                    'key' => $key,
                    'value' => $value,
                    'label' => $label . ': ' . $value,
                ];
            }
        }

        foreach (self::request_values('gpo_brand_key') as $brand_key) {
            $entry = self::brand_entry_local($brand_key);
            $chips[] = [
                'key' => 'gpo_brand_key',
                'value' => $brand_key,
                'label' => 'Marca: ' . ($entry['name'] ?? $brand_key),
            ];
        }

        if (self::request_value('gpo_min_price') !== '') {
            $chips[] = [
                'key' => 'gpo_min_price',
                'value' => null,
                'label' => 'Prezzo da ' . self::format_price_public((float) self::request_value('gpo_min_price')),
            ];
        }

        if (self::request_value('gpo_max_price') !== '') {
            $chips[] = [
                'key' => 'gpo_max_price',
                'value' => null,
                'label' => 'Prezzo fino a ' . self::format_price_public((float) self::request_value('gpo_max_price')),
            ];
        }

        if (self::request_value('gpo_max_mileage') !== '') {
            $chips[] = [
                'key' => 'gpo_max_mileage',
                'value' => null,
                'label' => 'KM max ' . self::format_number(self::request_value('gpo_max_mileage'), ' km'),
            ];
        }

        if (empty($chips)) {
            return '';
        }

        ob_start();
        echo '<div class="gpo-active-filters" aria-label="Filtri attivi">';
        echo '<span class="gpo-active-filters__label">Filtri attivi</span>';
        echo '<div class="gpo-active-filters__list">';
        foreach ($chips as $chip) {
            echo '<a class="gpo-active-filter" href="' . esc_url(self::filter_chip_remove_url($action, $chip['key'], $chip['value'])) . '">';
            echo '<span class="gpo-active-filter__text">' . esc_html($chip['label']) . '</span>';
            echo '<span class="gpo-active-filter__remove" aria-hidden="true">&times;</span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }

    protected static function filter_chip_remove_url($action, $key, $value = null) {
        $args = self::current_request_query_args();

        if (!array_key_exists($key, $args)) {
            return add_query_arg($args, $action);
        }

        if ($value !== null && is_array($args[$key])) {
            $args[$key] = array_values(array_filter($args[$key], function ($current) use ($value) {
                return (string) $current !== (string) $value;
            }));

            if (empty($args[$key])) {
                unset($args[$key]);
            }
        } else {
            unset($args[$key]);
        }

        return add_query_arg($args, $action);
    }

    protected static function filter_reset_url($action) {
        $args = [];

        if (self::request_value('gpo_catalog_ref') !== '') {
            $args['gpo_catalog_ref'] = self::request_value('gpo_catalog_ref');
        }

        return add_query_arg($args, $action);
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
        $data = self::vehicle_data($post_id);
        $price = $data['price'] ?? get_post_meta($post_id, '_gpo_price', true);
        $promotion = $data['promotion'] ?? self::promotion_context($post_id);
        $promo_price = $data['promo_price'] ?? ($promotion['discounted_price'] ?? get_post_meta($post_id, '_gpo_price_promo', true));
        $current_price = $data['current_price'] ?? ($promo_price ?: $price);
        $badge = $data['badge'] ?? get_post_meta($post_id, '_gpo_badge', true);
        $is_featured = get_post_meta($post_id, '_gpo_featured', true) === '1';
        $permalink = get_permalink($post_id);
        $specs = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($post_id, '_gpo_specs', true))));

        $meta = [
            'year' => ['label' => 'Anno', 'value' => $data['year'] ?? get_post_meta($post_id, '_gpo_year', true), 'icon' => 'calendar'],
            'mileage' => ['label' => 'KM', 'value' => self::format_number($data['mileage'] ?? get_post_meta($post_id, '_gpo_mileage', true), ' km'), 'icon' => 'road'],
            'body_type' => ['label' => 'Carrozzeria', 'value' => $data['body_type'] ?? get_post_meta($post_id, '_gpo_body_type', true), 'icon' => 'car'],
            'transmission' => ['label' => 'Cambio', 'value' => $data['transmission'] ?? get_post_meta($post_id, '_gpo_transmission', true), 'icon' => 'gear'],
            'engine_size' => ['label' => 'Cilindrata', 'value' => self::format_engine_size($data['engine_size'] ?? get_post_meta($post_id, '_gpo_engine_size', true)), 'icon' => 'engine'],
        ];

        $layout = $display['layout'] ?? 'default';
        $hero = !empty($display['hero']);
        $context = sanitize_key($display['context'] ?? 'default');

        echo '<article class="gpo-card gpo-card-layout-' . esc_attr($layout) . ' ' . ($hero ? 'gpo-card-hero' : '') . '" data-gpo-card-url="' . esc_url($permalink) . '">';

        if (self::is_visible($display, 'image')) {
            echo '<div class="' . esc_attr('gpo-card-media ' . self::visibility_classes($display, 'image')) . '">';
            if (has_post_thumbnail($post_id)) {
                echo '<a class="gpo-card-image" href="' . esc_url($permalink) . '">' . get_the_post_thumbnail($post_id, 'large') . '</a>';
            } else {
                echo '<a class="gpo-card-image gpo-card-image-placeholder" href="' . esc_url($permalink) . '">' . self::fallback_vehicle_image_markup('gpo-fallback-image') . '</a>';
            }
            if (self::is_visible($display, 'badge')) {
                echo '<div class="gpo-card-overlay">';
                if ($badge) {
                    echo '<span class="gpo-badge">' . esc_html($badge) . '</span>';
                } elseif ($is_featured && !in_array($context, ['showcase', 'catalog'], true)) {
                    echo '<span class="gpo-badge">In vetrina</span>';
                }
                if ($promotion) {
                    echo '<span class="gpo-badge gpo-badge--promo">' . esc_html($promotion['badge']) . '</span>';
                }
                echo '</div>';
            }
            echo '</div>';
        }

        echo '<div class="gpo-card-body">';
        echo '<div class="gpo-card-topline">';
        echo '<div>';
        if (self::is_visible($display, 'brand')) {
            echo '<p class="' . esc_attr('gpo-card-brand ' . self::visibility_classes($display, 'brand')) . '">' . esc_html(trim(get_post_meta($post_id, '_gpo_brand', true) . ' ' . get_post_meta($post_id, '_gpo_model', true))) . '</p>';
        }
        if (self::is_visible($display, 'title')) {
            echo '<h3 class="' . esc_attr('gpo-card-title ' . self::visibility_classes($display, 'title')) . '"><a href="' . esc_url($permalink) . '">' . esc_html(get_the_title($post_id)) . '</a></h3>';
        }
        echo '</div>';
        if (self::is_visible($display, 'price')) {
            echo '<div class="' . esc_attr('gpo-price-box ' . ($promotion ? 'is-promoted ' : '') . self::visibility_classes($display, 'price')) . '">';
            if ($promo_price && $price && $promo_price !== $price) {
                echo '<span class="gpo-price-old">' . esc_html(self::format_price($price)) . '</span>';
            }
            echo '<strong class="gpo-price-current' . ($promotion ? ' gpo-price-current--promo' : '') . '">' . esc_html(self::format_price($current_price)) . '</strong>';
            $promo_copy = self::promotion_copy_text($promotion);
            if ($promotion && $promo_copy !== '') {
                echo '<span class="gpo-promo-copy">' . esc_html($promo_copy) . '</span>';
            }
            echo '</div>';
        }
        echo '</div>';

        if (self::is_visible($display, 'chips')) {
            echo self::quick_info_panel_markup($post_id, 'gpo-quick-info-panel ' . self::visibility_classes($display, 'chips'), [
                'year' => self::visibility_classes($display, 'year'),
                'mileage' => self::visibility_classes($display, 'mileage'),
            ], $data);
        }

        if (self::is_visible($display, 'neopatentati')) {
            echo self::neopatentati_badge_markup($post_id, 'gpo-neo-badge ' . self::visibility_classes($display, 'neopatentati'), $data);
        }

        $has_meta = false;
        foreach ($meta as $meta_key => $meta_item) {
            if (self::is_visible($display, 'chips') && in_array($meta_key, ['year', 'mileage'], true)) {
                continue;
            }
            if (self::is_visible($display, $meta_key) && $meta_item['value'] !== '') {
                $has_meta = true;
                break;
            }
        }
        if ($has_meta) {
            echo '<div class="gpo-card-meta-grid">';
            foreach ($meta as $meta_key => $meta_item) {
                if (self::is_visible($display, 'chips') && in_array($meta_key, ['year', 'mileage'], true)) {
                    continue;
                }
                if (!self::is_visible($display, $meta_key) || $meta_item['value'] === '') {
                    continue;
                }
                echo '<span class="' . esc_attr('gpo-card-meta-item ' . self::visibility_classes($display, $meta_key)) . '">';
                echo '<span class="gpo-card-meta-item__icon" aria-hidden="true">' . self::icon_markup($meta_item['icon']) . '</span>';
                echo '<span class="gpo-card-meta-item__copy"><small>' . esc_html($meta_item['label']) . '</small><strong>' . esc_html($meta_item['value']) . '</strong></span>';
                echo '</span>';
            }
            echo '</div>';
        }

        if (self::is_visible($display, 'specs') && !empty($specs)) {
            echo '<ul class="' . esc_attr('gpo-spec-list ' . self::visibility_classes($display, 'specs')) . '">';
            foreach (array_slice($specs, 0, 3) as $item) {
                echo '<li>' . esc_html($item) . '</li>';
            }
            echo '</ul>';
        }

        if (self::is_visible($display, 'primary_button')) {
            echo '<div class="gpo-card-actions">';
            echo '<a class="' . esc_attr('gpo-button ' . self::visibility_classes($display, 'primary_button')) . '" href="' . esc_url($permalink) . '">' . esc_html($display['primary_button_label'] ?? 'Scheda veicolo') . '</a>';
            echo '</div>';
        }
        echo '</div></article>';
    }

    public static function active_featured_vehicle_id() {
        $component = self::component_settings('featured_vehicle');
        $queue = isset($component['queue']) && is_array($component['queue']) ? $component['queue'] : [];

        foreach ($queue as $row) {
            $vehicle_id = absint($row['vehicle_id'] ?? 0);
            if ($vehicle_id > 0 && self::schedule_has_window($row) && GPO_Engagement::window_is_active($row['start_date'] ?? '', $row['start_time'] ?? '', $row['end_date'] ?? '', $row['end_time'] ?? '')) {
                return $vehicle_id;
            }
        }

        $base_vehicle = absint($component['vehicle_id'] ?? 0);
        if ($base_vehicle > 0 && GPO_Engagement::window_is_active($component['start_date'] ?? '', $component['start_time'] ?? '', $component['end_date'] ?? '', $component['end_time'] ?? '')) {
            return $base_vehicle;
        }

        foreach ($queue as $row) {
            $vehicle_id = absint($row['vehicle_id'] ?? 0);
            if ($vehicle_id > 0 && !self::schedule_has_window($row)) {
                return $vehicle_id;
            }
        }

        $legacy = self::get_current_featured_ids(1);
        if (!empty($legacy)) {
            return absint($legacy[0]);
        }

        $latest = self::latest_real_vehicle_ids(1);
        return !empty($latest) ? absint($latest[0]) : 0;
    }

    public static function active_showcase_vehicle_ids($limit = 12) {
        $limit = max(1, absint($limit));
        $component = self::component_settings('showcase_carousel');
        $queue = isset($component['queue']) && is_array($component['queue']) ? $component['queue'] : [];

        foreach ($queue as $row) {
            $vehicle_ids = array_values(array_filter(array_map('absint', (array) ($row['vehicle_ids'] ?? []))));
            if (!empty($vehicle_ids) && self::schedule_has_window($row) && GPO_Engagement::window_is_active($row['start_date'] ?? '', $row['start_time'] ?? '', $row['end_date'] ?? '', $row['end_time'] ?? '')) {
                return array_slice($vehicle_ids, 0, $limit);
            }
        }

        $base_ids = array_values(array_filter(array_map('absint', (array) ($component['vehicle_ids'] ?? []))));
        if (!empty($base_ids) && GPO_Engagement::window_is_active($component['start_date'] ?? '', $component['start_time'] ?? '', $component['end_date'] ?? '', $component['end_time'] ?? '')) {
            return array_slice($base_ids, 0, $limit);
        }

        foreach ($queue as $row) {
            $vehicle_ids = array_values(array_filter(array_map('absint', (array) ($row['vehicle_ids'] ?? []))));
            if (!empty($vehicle_ids) && !self::schedule_has_window($row)) {
                return array_slice($vehicle_ids, 0, $limit);
            }
        }

        $legacy = self::get_current_featured_ids($limit);
        if (!empty($legacy)) {
            return $legacy;
        }

        return self::latest_real_vehicle_ids($limit);
    }

    protected static function related_brand_vehicle_ids($post_id, $limit = 6) {
        $post_id = absint($post_id);
        $limit = max(1, absint($limit));
        $brand = trim((string) get_post_meta($post_id, '_gpo_brand', true));

        if ($post_id < 1 || $brand === '') {
            return array_slice(array_values(array_filter(self::latest_real_vehicle_ids($limit + 1), function ($candidate_id) use ($post_id) {
                return absint($candidate_id) !== $post_id;
            })), 0, $limit);
        }

        $query = new WP_Query([
            'post_type' => 'gpo_vehicle',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'post__not_in' => [$post_id],
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'AND',
                self::exclude_demo_vehicle_meta_clause(),
                self::brand_meta_query($brand),
            ],
        ]);

        $ids = array_map('absint', (array) $query->posts);
        if (count($ids) >= $limit) {
            return array_slice($ids, 0, $limit);
        }

        $fallback = array_values(array_filter(self::latest_real_vehicle_ids($limit + 4), function ($candidate_id) use ($post_id, $ids) {
            $candidate_id = absint($candidate_id);
            return $candidate_id !== $post_id && !in_array($candidate_id, $ids, true);
        }));

        return array_slice(array_merge($ids, $fallback), 0, $limit);
    }

    protected static function schedule_has_window($row) {
        return !empty($row['start_date']) || !empty($row['start_time']) || !empty($row['end_date']) || !empty($row['end_time']);
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
            $use_plugin_template = apply_filters('gpo_use_plugin_single_template', true, $template);
            if ($use_plugin_template && file_exists($custom)) {
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
        return 0;
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
        $data['neopatentati'] = self::is_neopatentati_vehicle($post_id, $data);
        $data['badge'] = get_post_meta($post_id, '_gpo_badge', true);
        $data['price'] = get_post_meta($post_id, '_gpo_price', true);
        $data['promotion'] = self::promotion_context($post_id);
        $data['promo_price'] = $data['promotion']['discounted_price'] ?? get_post_meta($post_id, '_gpo_price_promo', true);
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
        $gallery_payload = !empty($items) ? wp_json_encode(array_map(function ($item) {
            return [
                'large' => $item['large'],
                'full' => $item['full'],
                'alt' => $item['alt'],
                'caption' => $item['caption'],
            ];
        }, $items)) : '';
        if (wp_script_is('gpo-vehicle-gallery', 'registered')) {
            wp_enqueue_script('gpo-vehicle-gallery');
        }

        ob_start();
        if ($with_panel) {
            echo '<div class="gpo-single-gallery-panel' . (empty($items) ? ' is-empty' : '') . '" data-gpo-gallery="1"' . ($gallery_payload ? ' data-gpo-gallery-items="' . esc_attr($gallery_payload) . '"' : '') . '>';
        }

        if (!empty($items)) {
            $current = $items[0];
            $count = count($items);
            $visible_thumb_limit = 10;
            $thumb_items = array_slice($items, 0, min($count, $visible_thumb_limit));
            $remaining_items = max(0, $count - $visible_thumb_limit);

            echo '<div class="gpo-single-stage">';
            echo '<button type="button" class="gpo-single-stage__nav prev" aria-label="Foto precedente">' . self::icon_markup('chevron-left') . '</button>';
            echo '<button type="button" class="gpo-single-main" aria-label="Apri galleria immagini">';
            echo '<span class="gpo-single-main__media"><img class="gpo-single-main__image" src="' . esc_url($current['large']) . '" alt="' . esc_attr($current['alt']) . '" loading="eager" /></span>';
            echo '</button>';
            echo '<button type="button" class="gpo-single-stage__nav next" aria-label="Foto successiva">' . self::icon_markup('chevron-right') . '</button>';
            echo '</div>';

            echo '<div class="gpo-single-gallery-bar">';
            echo '<div class="gpo-single-gallery-count"><strong>1 / ' . esc_html((string) $count) . '</strong><small>' . esc_html($current['caption']) . '</small></div>';
            echo '</div>';

            echo '<div class="gpo-single-thumbs" role="list">';
            foreach ($thumb_items as $index => $item) {
                $is_more_tile = $remaining_items > 0 && $index === count($thumb_items) - 1;
                echo '<button type="button" class="gpo-single-thumb' . ($index === 0 ? ' is-active' : '') . ($is_more_tile ? ' gpo-single-thumb--more' : '') . '" data-index="' . esc_attr((string) $index) . '" data-large-src="' . esc_url($item['large']) . '" data-full-src="' . esc_url($item['full']) . '" data-alt="' . esc_attr($item['alt']) . '" data-caption="' . esc_attr($item['caption']) . '" aria-label="Seleziona foto ' . esc_attr((string) ($index + 1)) . '"' . ($index === 0 ? ' aria-current="true"' : '') . '>';
                echo '<img src="' . esc_url($item['thumb']) . '" alt="' . esc_attr($item['alt']) . '" loading="lazy" />';
                if ($is_more_tile) {
                    echo '<span class="gpo-single-thumb__more">+' . esc_html((string) $remaining_items) . ' foto</span>';
                }
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
            'neopatentati' => ['Neopatentati', self::is_neopatentati_vehicle($post_id) ? 'Si' : ''],
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
        $values = self::request_values($key);

        return $values[0] ?? '';
    }

    protected static function request_values($key) {
        if (!isset($_GET[$key])) {
            return [];
        }

        $raw = wp_unslash($_GET[$key]);
        $items = is_array($raw) ? $raw : [$raw];
        $items = array_map(function ($value) {
            return sanitize_text_field((string) $value);
        }, $items);

        return array_values(array_unique(array_filter($items, function ($value) {
            return $value !== '';
        })));
    }

    protected static function current_request_query_args() {
        $args = [];

        foreach ((array) $_GET as $key => $raw) {
            if (is_array($raw)) {
                $values = array_values(array_unique(array_filter(array_map(function ($value) {
                    return sanitize_text_field((string) wp_unslash($value));
                }, $raw), function ($value) {
                    return $value !== '';
                })));

                if (!empty($values)) {
                    $args[sanitize_key((string) $key)] = $values;
                }
                continue;
            }

            $value = sanitize_text_field((string) wp_unslash($raw));
            if ($value !== '') {
                $args[sanitize_key((string) $key)] = $value;
            }
        }

        return $args;
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
        $slug = gpo_get_brand_slug($entry['name']);

        foreach (['svg', 'png', 'webp', 'jpg', 'jpeg'] as $extension) {
            $relative = 'public/assets/brands/' . $slug . '.' . $extension;
            if (file_exists(GPO_PLUGIN_DIR . $relative)) {
                return GPO_PLUGIN_URL . $relative;
            }
        }

        return '';
    }

    protected static function brand_logo_has_local_asset($brand) {
        $entry = self::brand_entry_local($brand);
        $slug = gpo_get_brand_slug($entry['name']);

        foreach (['svg', 'png', 'webp', 'jpg', 'jpeg'] as $extension) {
            if (file_exists(GPO_PLUGIN_DIR . 'public/assets/brands/' . $slug . '.' . $extension)) {
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

    protected static function configured_brand_carousel_items() {
        $component = self::component_settings('brand_banner');
        $mode = sanitize_key((string) ($component['mode'] ?? 'inventory'));

        if ($mode === 'all') {
            return self::brand_library();
        }

        if ($mode === 'manual') {
            $selected = array_values(array_filter(array_map('sanitize_title', (array) ($component['selected_brands'] ?? []))));
            if (empty($selected)) {
                return [];
            }

            $library = [];
            foreach (self::brand_library() as $brand) {
                $library[$brand['key']] = $brand;
            }

            $items = [];
            foreach ($selected as $key) {
                if (!empty($library[$key])) {
                    $items[] = $library[$key];
                }
            }
            return $items;
        }

        return self::brand_inventory_summary();
    }

    protected static function icon_markup($type) {
        if ($type === 'clear') {
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        }

        if ($type === 'calendar') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="3"></rect><line x1="16" y1="3" x2="16" y2="7"></line><line x1="8" y1="3" x2="8" y2="7"></line><line x1="3" y1="11" x2="21" y2="11"></line></svg>';
        }

        if ($type === 'road') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6l4 18H5L9 3Z"></path><path d="M12 7v3"></path><path d="M12 13v3"></path></svg>';
        }

        if ($type === 'car') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16l1.2-5a2 2 0 0 1 1.94-1.53h7.72A2 2 0 0 1 17.8 11L19 16"></path><path d="M4 16h16v3H4z"></path><circle cx="7.5" cy="19.5" r="1.5"></circle><circle cx="16.5" cy="19.5" r="1.5"></circle></svg>';
        }

        if ($type === 'gear') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.2a1 1 0 0 0-.7-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.2a1 1 0 0 0 .9-.7 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2H9a1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.2a1 1 0 0 0 .7.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1V9c0 .4.2.7.6.8H20a2 2 0 1 1 0 4h-.2a1 1 0 0 0-.9.7Z"></path></svg>';
        }

        if ($type === 'engine') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h3l2-2h6l2 2h3v6h-3l-2 2H9l-2-2H4z"></path><path d="M10 12h4"></path></svg>';
        }

        if ($type === 'fuel') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 5h8v14H7z"></path><path d="M15 8h2l2 2v6a2 2 0 0 1-2 2h-2"></path><path d="M9 9h4"></path></svg>';
        }

        if ($type === 'palette') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18c1.2 0 2-.8 2-1.8 0-.7-.4-1.2-.4-1.9 0-.8.8-1.3 1.7-1.3H17A4 4 0 0 0 21 12a9 9 0 0 0-9-9Z"></path><circle cx="7.5" cy="10.5" r=".8"></circle><circle cx="11" cy="7.5" r=".8"></circle><circle cx="16.2" cy="9.2" r=".8"></circle></svg>';
        }

        if ($type === 'door') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h9l3 2v14H6z"></path><path d="M9 12h.01"></path></svg>';
        }

        if ($type === 'users') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path><circle cx="9.5" cy="7.5" r="3.5"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M15 4.2a3.5 3.5 0 0 1 0 6.6"></path></svg>';
        }

        if ($type === 'pin') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s6-5.33 6-11a6 6 0 1 0-12 0c0 5.67 6 11 6 11Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>';
        }

        if ($type === 'bolt') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 5 14h6l-1 8 8-12h-6l1-8Z"></path></svg>';
        }

        if ($type === 'copy') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
        }

        if ($type === 'whatsapp') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 2.2a9.73 9.73 0 0 0-8.44 14.58L2 22l5.38-1.5A9.79 9.79 0 1 0 12 2.2Zm0 17.77a8.07 8.07 0 0 1-4.11-1.13l-.3-.18-3.2.89.86-3.12-.2-.32a8.1 8.1 0 1 1 6.95 3.86Zm4.45-6.04c-.24-.12-1.42-.7-1.64-.77-.22-.08-.38-.12-.54.12s-.62.77-.76.92c-.14.16-.28.18-.52.06-.24-.12-1.01-.37-1.92-1.2-.71-.63-1.2-1.42-1.34-1.66-.14-.24-.02-.37.1-.49.1-.1.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.3-.74-1.78-.2-.47-.4-.4-.54-.41h-.46c-.16 0-.42.06-.64.3s-.84.82-.84 2 .86 2.3.98 2.46c.12.16 1.68 2.56 4.08 3.6.57.24 1.02.38 1.36.48.58.18 1.1.16 1.52.1.46-.06 1.42-.58 1.62-1.14.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28Z"/></svg>';
        }

        if ($type === 'check-circle') {
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="m8.5 12.2 2.3 2.3 4.7-5.1"></path></svg>';
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
        $slug = gpo_get_brand_slug($brand);
        $local_file = GPO_PLUGIN_DIR . 'public/assets/brands/' . $slug . '.png';
        if (file_exists($local_file)) {
            return GPO_PLUGIN_URL . 'public/assets/brands/' . $slug . '.png';
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

    protected static function brand_marquee_duration($atts = []) {
        $interval = max(1500, absint($atts['interval'] ?? 6500));
        $speed = max(300, absint($atts['speed'] ?? 900));
        $seconds = ($interval / 400) + ($speed / 60);
        return max(18, min(90, round($seconds, 1)));
    }

    protected static function brand_carousel_item_markup($brand, $target_url, $catalog_ref, $duplicate = false) {
        $url = add_query_arg([
            'gpo_brand_key' => $brand['key'],
            'gpo_catalog_ref' => sanitize_text_field($catalog_ref),
        ], $target_url);

        $classes = 'gpo-brand-item';
        if (empty($brand['has_local_logo'])) {
            $classes .= ' gpo-brand-item--text-only';
        }

        $attrs = [
            'class' => $classes,
            'href' => esc_url($url),
            'aria-label' => esc_attr($brand['name']),
            'draggable' => 'false',
        ];

        if ($duplicate) {
            $attrs['aria-hidden'] = 'true';
            $attrs['tabindex'] = '-1';
        }

        $html = '<a';
        foreach ($attrs as $key => $value) {
            $html .= ' ' . $key . '="' . $value . '"';
        }
        $html .= '>';

        if (!empty($brand['has_local_logo'])) {
            $html .= '<span class="gpo-brand-item__visual"><img src="' . esc_url($brand['logo']) . '" alt="' . esc_attr($brand['name']) . '" loading="lazy" decoding="async" draggable="false" /></span>';
        } else {
            $html .= '<span class="gpo-brand-item__meta">';
            $html .= '<strong class="gpo-brand-name">' . esc_html($brand['name']) . '</strong>';
            $html .= '</span>';
        }
        $html .= '</a>';

        return $html;
    }

    public static function brand_carousel_shortcode($atts) {
        wp_enqueue_style('gpo-public');
        wp_enqueue_script('gpo-live-search');
        $atts = shortcode_atts([
            'page_id' => 0,
            'catalog_ref' => 'default',
            'logo_size' => 96,
            'card_size' => 168,
            'autoplay' => 'yes',
            'interval' => 6500,
            'speed' => 900,
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
        ], $atts, 'gestpark_brand_carousel');

        $brands = self::configured_brand_carousel_items();
        if (empty($brands)) {
            return '<div class="gpo-empty-state"><p>Nessuna marca disponibile.</p></div>';
        }
        $target_url = self::catalog_target_url($atts['page_id']);
        $autoplay = ($atts['autoplay'] === 'yes') ? 'yes' : 'no';
        $style = self::wrapper_style([
            'primary_color' => $atts['primary_color'],
            'accent_color' => $atts['accent_color'],
            'bg_color' => $atts['bg_color'],
            'text_color' => $atts['text_color'],
        ]) .
            '--gpo-brand-logo-size:' . max(72, absint($atts['logo_size'])) . 'px;' .
            '--gpo-brand-card-size:' . max(120, absint($atts['card_size'])) . 'px;' .
            '--gpo-brand-speed:' . max(300, absint($atts['speed'])) . 'ms;' .
            '--gpo-brand-marquee-duration:' . self::brand_marquee_duration($atts) . 's;';

        ob_start();
        echo '<div class="gpo-brand-carousel-shell" style="' . esc_attr($style) . '">';
        echo '<div class="gpo-brand-carousel" data-autoplay="' . esc_attr($autoplay) . '" data-interval="' . absint($atts['interval']) . '" data-speed="' . absint($atts['speed']) . '">';
        echo '<div class="gpo-brand-viewport"><div class="gpo-brand-track">';
        echo '<div class="gpo-brand-run">';
        foreach ($brands as $brand) {
            echo self::brand_carousel_item_markup($brand, $target_url, $atts['catalog_ref']);
        }
        echo '</div>';
        if ($autoplay === 'yes') {
            echo '<div class="gpo-brand-run gpo-brand-run--duplicate" aria-hidden="true">';
            foreach ($brands as $brand) {
                echo self::brand_carousel_item_markup($brand, $target_url, $atts['catalog_ref'], true);
            }
            echo '</div>';
        }
        echo '</div></div>';
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
            'radius' => 999,
            'search_align' => 'left',
            'show_on_desktop' => 'yes',
            'show_on_tablet' => 'yes',
            'show_on_mobile' => 'yes',
            'mobile_mode' => 'normal',
            'primary_color' => '',
            'accent_color' => '',
            'bg_color' => '',
            'text_color' => '',
            'button_color' => '',
        ], $atts, 'gestpark_vehicle_search');
        $target_url = self::catalog_target_url($atts['page_id']);
        $mobile_mode = sanitize_key($atts['mobile_mode'] ?? 'normal');
        if (!in_array($mobile_mode, ['normal', 'burger'], true)) {
            $mobile_mode = 'normal';
        }
        $search_align = sanitize_key($atts['search_align'] ?? 'left');
        if (!in_array($search_align, ['left', 'center', 'right'], true)) {
            $search_align = 'left';
        }
        $shell_id = wp_unique_id('gpo-search-');
        $style = self::wrapper_style([
            'primary_color' => $atts['primary_color'],
            'accent_color' => $atts['accent_color'],
            'bg_color' => $atts['bg_color'],
            'text_color' => $atts['text_color'],
            'button_color' => $atts['button_color'],
        ]) . '--gpo-search-width:' . max(20, min(100, absint($atts['width']))) . '%;--gpo-search-radius:' . max(36, absint($atts['radius'])) . 'px;--gpo-shell-margin-y:0px;--gpo-shell-padding-x:0px;';
        ob_start();
        echo '<div class="gpo-vehicle-search-shell is-align-' . esc_attr($search_align) . ($mobile_mode === 'burger' ? ' gpo-vehicle-search-shell--mobile-menu-source' : '') . '" style="' . esc_attr($style) . '" data-gpo-mobile-mode="' . esc_attr($mobile_mode) . '" data-gpo-show-mobile="' . esc_attr(($atts['show_on_mobile'] ?? 'yes') === 'no' ? 'no' : 'yes') . '" data-gpo-mobile-search-id="' . esc_attr($shell_id) . '" data-gpo-search-align="' . esc_attr($search_align) . '">';
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
                'originalPrice' => !empty($data['promotion']['formatted_original']) ? $data['promotion']['formatted_original'] : '',
                'promoBadge' => !empty($data['promotion']['badge']) ? $data['promotion']['badge'] : '',
                'promoText' => self::promotion_copy_text($data['promotion'] ?? []),
                'neopatentati' => !empty($data['neopatentati']),
                'brand' => $data['brand'] ?? '',
                'subtitle' => $subtitle,
                'thumb' => self::vehicle_thumbnail_url($id, 'thumbnail'),
            ];
        }
        wp_reset_postdata();
        wp_send_json_success(['results' => $results]);
    }

}
