<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_CPT {
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_types']);
        add_action('init', [__CLASS__, 'register_taxonomies']);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post_gpo_vehicle', [__CLASS__, 'save_vehicle_meta']);
        add_filter('manage_gpo_vehicle_posts_columns', [__CLASS__, 'columns']);
        add_action('manage_gpo_vehicle_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
    }

    public static function register_post_types() {
        register_post_type('gpo_vehicle', [
            'labels' => [
                'name'          => 'Veicoli',
                'singular_name' => 'Veicolo',
                'add_new_item'  => 'Aggiungi veicolo',
                'edit_item'     => 'Modifica veicolo',
                'menu_name'     => 'Veicoli',
            ],
            'public'       => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'veicoli'],
            'menu_icon'    => 'dashicons-car',
        ]);

        register_post_type('gpo_template', [
            'labels' => [
                'name' => 'Template veicolo',
                'singular_name' => 'Template veicolo',
                'add_new_item' => 'Aggiungi template veicolo',
                'edit_item' => 'Modifica template veicolo',
                'menu_name' => 'Template veicolo',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'revisions'],
            'map_meta_cap' => true,
            'menu_icon' => 'dashicons-layout',
        ]);
    }

    public static function register_post_type() {
        self::register_post_types();
    }

    public static function register_taxonomies() {
        $taxonomies = [
            'gpo_brand' => 'Marca',
            'gpo_fuel' => 'Alimentazione',
            'gpo_body' => 'Carrozzeria',
            'gpo_transmission' => 'Cambio',
            'gpo_condition' => 'Condizione',
        ];

        foreach ($taxonomies as $slug => $label) {
            register_taxonomy($slug, 'gpo_vehicle', [
                'labels' => [
                    'name' => $label,
                    'singular_name' => $label,
                ],
                'public' => true,
                'show_in_rest' => true,
                'hierarchical' => false,
                'show_admin_column' => true,
            ]);
        }
    }

    public static function register_meta_boxes() {
        add_meta_box('gpo_vehicle_data', 'Dati veicolo', [__CLASS__, 'render_vehicle_data_metabox'], 'gpo_vehicle', 'normal', 'high');
        add_meta_box('gpo_vehicle_notes', 'Note, specifiche e accessori', [__CLASS__, 'render_notes_metabox'], 'gpo_vehicle', 'normal', 'default');
        add_meta_box('gpo_vehicle_showcase', 'Vetrina e promozioni', [__CLASS__, 'render_showcase_metabox'], 'gpo_vehicle', 'side', 'high');
        add_meta_box('gpo_template_help', 'Come usare questo template', [__CLASS__, 'render_template_help_metabox'], 'gpo_template', 'side', 'high');
    }

    public static function render_template_help_metabox($post) {
        echo '<p>Questo template viene modificato con l’editor di WordPress e può essere assegnato come scheda generale del veicolo dal pannello <strong>gestpark online → Aspetto</strong>.</p>';
        echo '<p>All’interno dell’editor puoi spostare liberamente box, colonne, gruppi, caroselli e qualsiasi blocco Gutenberg. I blocchi GestPark leggono i dati del veicolo corrente in modo dinamico.</p>';
        echo '<p><strong>Suggerimento:</strong> usa i blocchi GestPark Hero, Scheda tecnica, Accessori, Contatto e Carosello per costruire un layout completamente personalizzato e responsive.</p>';
        if (class_exists('GPO_Frontend')) {
            $preview_url = GPO_Frontend::template_preview_vehicle_link($post->ID);
            $preview_vehicle_id = GPO_Frontend::current_vehicle_id();
            $preview_edit_url = $preview_vehicle_id ? get_edit_post_link($preview_vehicle_id) : '';
            if ($preview_url) {
                echo '<p><a class="button button-secondary" target="_blank" href="' . esc_url($preview_url) . '">Anteprima con veicolo reale</a></p>';
            }
            if ($preview_edit_url) {
                echo '<p><a class="button" href="' . esc_url($preview_edit_url) . '">Apri veicolo usato per l anteprima</a></p>';
            }
        }
    }

    public static function fields() {
        return [
            'condition'     => 'Condizione',
            'year'          => 'Anno',
            'price'         => 'Prezzo',
            'fuel'          => 'Alimentazione',
            'mileage'       => 'Chilometraggio',
            'body_type'     => 'Carrozzeria',
            'transmission'  => 'Cambio',
            'engine_size'   => 'Cilindrata',
            'brand'         => 'Marca',
            'model'         => 'Modello',
            'version'       => 'Versione',
            'power'         => 'Potenza',
            'color'         => 'Colore',
            'doors'         => 'Porte',
            'seats'         => 'Posti',
            'plate'         => 'Targa',
            'vin'           => 'Telaio',
            'location'      => 'Sede',
            'status'        => 'Stato veicolo',
            'price_promo'   => 'Prezzo promo',
            'promo_start'   => 'Inizio promo',
            'promo_end'     => 'Fine promo',
            'external_id'   => 'ID esterno',
            'gallery_urls'  => 'URL galleria',
        ];
    }

    public static function render_vehicle_data_metabox($post) {
        wp_nonce_field('gpo_save_vehicle', 'gpo_vehicle_nonce');
        $fields = self::fields();
        echo '<table class="form-table"><tbody>';
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, '_gpo_' . $key, true);
            $lock  = get_post_meta($post->ID, '_gpo_lock_' . $key, true);
            echo '<tr>';
            echo '<th><label for="gpo_' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
            echo '<td>';
            if ($key === 'gallery_urls') {
                echo '<textarea style="width:100%;min-height:90px;" id="gpo_' . esc_attr($key) . '" name="gpo_meta[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
                echo '<p class="description">Inserisci un URL per riga. Questo campo viene usato anche in fase di test senza import automatico immagini.</p>';
            } else {
                $type = in_array($key, ['price', 'price_promo']) ? 'number' : 'text';
                echo '<input style="width:100%;" type="' . esc_attr($type) . '" id="gpo_' . esc_attr($key) . '" name="gpo_meta[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" />';
            }
            if (!in_array($key, ['external_id', 'gallery_urls'], true)) {
                echo '<p><label><input type="checkbox" name="gpo_lock[' . esc_attr($key) . ']" value="1" ' . checked($lock, '1', false) . ' /> Mantieni questo campo locale e non sovrascriverlo alla prossima sincronizzazione</label></p>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public static function render_notes_metabox($post) {
        $internal_notes = get_post_meta($post->ID, '_gpo_internal_notes', true);
        $public_notes   = get_post_meta($post->ID, '_gpo_public_notes', true);
        $specs          = get_post_meta($post->ID, '_gpo_specs', true);
        $accessories    = get_post_meta($post->ID, '_gpo_accessories', true);

        echo '<p><strong>Note interne</strong></p>';
        echo '<textarea style="width:100%;min-height:90px;" name="gpo_internal_notes">' . esc_textarea($internal_notes) . '</textarea>';
        echo '<p><strong>Note pubbliche</strong></p>';
        echo '<textarea style="width:100%;min-height:90px;" name="gpo_public_notes">' . esc_textarea($public_notes) . '</textarea>';
        echo '<p><strong>Specifiche aggiuntive</strong> <span class="description">(una per riga)</span></p>';
        echo '<textarea style="width:100%;min-height:90px;" name="gpo_specs">' . esc_textarea($specs) . '</textarea>';
        echo '<p><strong>Accessori</strong> <span class="description">(uno per riga)</span></p>';
        echo '<textarea style="width:100%;min-height:90px;" name="gpo_accessories">' . esc_textarea($accessories) . '</textarea>';
    }

    public static function render_showcase_metabox($post) {
        $featured      = get_post_meta($post->ID, '_gpo_featured', true);
        $featured_from = get_post_meta($post->ID, '_gpo_featured_from', true);
        $featured_to   = get_post_meta($post->ID, '_gpo_featured_to', true);
        $featured_order = get_post_meta($post->ID, '_gpo_featured_order', true);
        $badge         = get_post_meta($post->ID, '_gpo_badge', true);
        echo '<p><label><input type="checkbox" name="gpo_featured" value="1" ' . checked($featured, '1', false) . ' /> Veicolo in vetrina</label></p>';
        echo '<p><label>Data inizio vetrina<br><input type="datetime-local" name="gpo_featured_from" value="' . esc_attr($featured_from) . '" /></label></p>';
        echo '<p><label>Data fine vetrina<br><input type="datetime-local" name="gpo_featured_to" value="' . esc_attr($featured_to) . '" /></label></p>';
        echo '<p><label>Ordine vetrina<br><input type="number" name="gpo_featured_order" value="' . esc_attr($featured_order) . '" /></label></p>';
        echo '<p><label>Badge personalizzato<br><input type="text" style="width:100%;" name="gpo_badge" value="' . esc_attr($badge) . '" /></label></p>';
    }

    public static function save_vehicle_meta($post_id) {
        if (!isset($_POST['gpo_vehicle_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gpo_vehicle_nonce'])), 'gpo_save_vehicle')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = self::fields();
        $meta_values = isset($_POST['gpo_meta']) ? (array) wp_unslash($_POST['gpo_meta']) : [];
        $locks = isset($_POST['gpo_lock']) ? (array) wp_unslash($_POST['gpo_lock']) : [];

        foreach ($fields as $key => $label) {
            $value = isset($meta_values[$key]) ? $meta_values[$key] : '';
            update_post_meta($post_id, '_gpo_' . $key, is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_textarea_field($value));
            update_post_meta($post_id, '_gpo_lock_' . $key, isset($locks[$key]) ? '1' : '0');
        }

        update_post_meta($post_id, '_gpo_internal_notes', isset($_POST['gpo_internal_notes']) ? sanitize_textarea_field(wp_unslash($_POST['gpo_internal_notes'])) : '');
        update_post_meta($post_id, '_gpo_public_notes', isset($_POST['gpo_public_notes']) ? sanitize_textarea_field(wp_unslash($_POST['gpo_public_notes'])) : '');
        update_post_meta($post_id, '_gpo_specs', isset($_POST['gpo_specs']) ? sanitize_textarea_field(wp_unslash($_POST['gpo_specs'])) : '');
        update_post_meta($post_id, '_gpo_accessories', isset($_POST['gpo_accessories']) ? sanitize_textarea_field(wp_unslash($_POST['gpo_accessories'])) : '');
        update_post_meta($post_id, '_gpo_featured', isset($_POST['gpo_featured']) ? '1' : '0');
        update_post_meta($post_id, '_gpo_featured_from', isset($_POST['gpo_featured_from']) ? sanitize_text_field(wp_unslash($_POST['gpo_featured_from'])) : '');
        update_post_meta($post_id, '_gpo_featured_to', isset($_POST['gpo_featured_to']) ? sanitize_text_field(wp_unslash($_POST['gpo_featured_to'])) : '');
        update_post_meta($post_id, '_gpo_featured_order', isset($_POST['gpo_featured_order']) ? absint($_POST['gpo_featured_order']) : 0);
        update_post_meta($post_id, '_gpo_badge', isset($_POST['gpo_badge']) ? sanitize_text_field(wp_unslash($_POST['gpo_badge'])) : '');
    }

    public static function columns($columns) {
        $new = [];
        $new['cb'] = $columns['cb'];
        $new['title'] = 'Veicolo';
        $new['brand'] = 'Marca';
        $new['year'] = 'Anno';
        $new['price'] = 'Prezzo';
        $new['condition'] = 'Condizione';
        $new['featured'] = 'Vetrina';
        $new['date'] = 'Data';
        return $new;
    }

    public static function column_content($column, $post_id) {
        switch ($column) {
            case 'brand':
                echo esc_html(get_post_meta($post_id, '_gpo_brand', true));
                break;
            case 'year':
                echo esc_html(get_post_meta($post_id, '_gpo_year', true));
                break;
            case 'price':
                echo esc_html(get_post_meta($post_id, '_gpo_price_promo', true) ?: get_post_meta($post_id, '_gpo_price', true));
                break;
            case 'condition':
                echo esc_html(get_post_meta($post_id, '_gpo_condition', true));
                break;
            case 'featured':
                echo get_post_meta($post_id, '_gpo_featured', true) === '1' ? 'Sì' : 'No';
                break;
        }
    }
}
