<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Sync_Manager {
    const LOCK_OPTION = 'gpo_sync_process_lock';
    const LOCK_TTL = 7200;

    public static function run_scheduled_sync() {
        $settings = self::settings();
        if (empty($settings['sync']['enabled'])) {
            return;
        }
        $connection = GPO_API_Client::connection_summary(isset($settings['api']) ? $settings['api'] : []);
        if (empty($connection['ready'])) {
            return;
        }
        self::sync('cron');
    }

    public static function sync($context = 'manual') {
        $lock_token = self::acquire_lock();
        if (is_wp_error($lock_token)) {
            return $lock_token;
        }

        $started_at = microtime(true);
        $stats = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deleted' => 0,
            'errors' => 0,
        ];
        GPO_Logger::add('Sincronizzazione avviata', ['modalita' => sanitize_key((string) $context)]);

        try {
            $settings = self::settings();
            $mapping = self::effective_mapping($settings);
            if (is_wp_error($mapping)) {
                return self::failed_sync($mapping, $stats, $started_at, $settings);
            }

            $items = GPO_API_Client::fetch_remote_data();
            if (is_wp_error($items)) {
                return self::failed_sync($items, $stats, $started_at, $settings);
            }

            $remote_items = [];
            $snapshot_valid = true;
            foreach ((array) $items as $item) {
                if (!is_array($item)) {
                    $stats['errors']++;
                    $snapshot_valid = false;
                    continue;
                }

                $external_id = sanitize_text_field((string) self::extract_value($item, isset($mapping['external_id']) ? $mapping['external_id'] : 'id'));
                if ($external_id === '') {
                    $stats['errors']++;
                    $snapshot_valid = false;
                    continue;
                }
                $remote_items[$external_id] = $item;
            }

            if (!empty($items) && empty($remote_items)) {
                $error = new WP_Error('gpo_invalid_remote_snapshot', 'La risposta ParkPlatform non contiene identificativi idGestionale validi. I veicoli locali non sono stati modificati.');
                return self::failed_sync($error, $stats, $started_at, $settings);
            }

            $local_vehicles = self::load_imported_vehicle_map();
            foreach ($remote_items as $external_id => $item) {
                $post_id = isset($local_vehicles[$external_id]) ? absint($local_vehicles[$external_id]) : 0;
                $is_new = !$post_id;
                $post_changed = false;

                if ($is_new) {
                    $post_id = wp_insert_post(wp_slash([
                        'post_type' => 'gpo_vehicle',
                        'post_status' => 'publish',
                        'post_title' => self::build_title($item, $mapping),
                        'post_content' => wp_kses_post((string) self::extract_value($item, isset($mapping['description']) ? $mapping['description'] : '')),
                    ]));
                } else {
                    $post_result = self::sync_post_record($post_id, $item, $mapping);
                    if (is_wp_error($post_result)) {
                        $stats['errors']++;
                        GPO_Logger::add('Errore aggiornamento veicolo', ['idGestionale' => $external_id, 'errore' => $post_result->get_error_message()]);
                        continue;
                    }
                    $post_changed = $post_result;
                }

                if (is_wp_error($post_id) || !$post_id) {
                    $stats['errors']++;
                    GPO_Logger::add('Errore creazione veicolo', ['idGestionale' => $external_id]);
                    continue;
                }

                $meta_changed = self::update_meta_if_changed($post_id, '_gpo_external_id', $external_id);
                $changed_fields = self::sync_core_fields($post_id, $item, $mapping);
                $taxonomy_changed = self::sync_taxonomies($post_id, $changed_fields, $is_new);
                $extra_changed = self::is_gestpark_item($item)
                    ? self::sync_gestpark_extras($post_id, $item, $stats['errors'])
                    : self::sync_url_gallery($post_id, $item, $mapping);
                $vehicle_changed = $is_new || $post_changed || $meta_changed || !empty($changed_fields) || $taxonomy_changed || $extra_changed;

                if ($vehicle_changed) {
                    self::update_meta_if_changed($post_id, '_gpo_last_sync', current_time('mysql'));
                }

                if ($is_new) {
                    $stats['created']++;
                } elseif ($vehicle_changed) {
                    $stats['updated']++;
                } else {
                    $stats['unchanged']++;
                }
            }

            if (self::is_gestpark_sync($settings) && $snapshot_valid) {
                self::reconcile_removed_vehicles($local_vehicles, array_keys($remote_items), $stats);
            } elseif (self::is_gestpark_sync($settings) && !$snapshot_valid) {
                GPO_Logger::add('Riconciliazione veicoli saltata', ['errore' => 'Snapshot ParkPlatform incompleto']);
            }

            return self::complete_sync($stats, $started_at, $settings);
        } catch (Throwable $exception) {
            $error = new WP_Error('gpo_sync_exception', 'Sincronizzazione interrotta: ' . $exception->getMessage());
            return self::failed_sync($error, $stats, $started_at, isset($settings) ? $settings : self::settings());
        } finally {
            self::release_lock($lock_token);
        }
    }

    protected static function acquire_lock() {
        $token = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('gpo-sync-', true);
        $lock = get_option(self::LOCK_OPTION, []);

        if (is_array($lock) && !empty($lock['started']) && (time() - absint($lock['started'])) > self::LOCK_TTL) {
            delete_option(self::LOCK_OPTION);
        }

        $created = add_option(self::LOCK_OPTION, ['token' => $token, 'started' => time()], '', false);
        if (!$created) {
            return new WP_Error('gpo_sync_locked', 'Una sincronizzazione e gia in corso. Attendi il completamento prima di avviarne un altra.');
        }

        return $token;
    }

    protected static function release_lock($token) {
        $lock = get_option(self::LOCK_OPTION, []);
        if (is_array($lock) && isset($lock['token']) && hash_equals((string) $lock['token'], (string) $token)) {
            delete_option(self::LOCK_OPTION);
        }
    }

    protected static function failed_sync($error, $stats, $started_at, $settings) {
        $stats['errors'] = max(1, absint($stats['errors']));
        $result = self::sync_result('error', $stats, $started_at, $settings, $error->get_error_message());
        update_option('gpo_last_sync_result', $result, false);
        GPO_Logger::add('Sincronizzazione fallita', [
            'durata_secondi' => $result['duration'],
            'errore' => $error->get_error_message(),
        ]);

        return $error;
    }

    protected static function complete_sync($stats, $started_at, $settings) {
        $result = self::sync_result($stats['errors'] > 0 ? 'partial' : 'success', $stats, $started_at, $settings);
        update_option('gpo_last_sync_result', $result, false);
        GPO_Logger::add('Sincronizzazione completata', [
            'durata_secondi' => $result['duration'],
            'creati' => $stats['created'],
            'aggiornati' => $stats['updated'],
            'invariati' => $stats['unchanged'],
            'eliminati' => $stats['deleted'],
            'errori' => $stats['errors'],
        ]);

        return $result;
    }

    protected static function sync_result($status, $stats, $started_at, $settings, $message = '') {
        return array_merge($stats, [
            'success' => $status !== 'error',
            'status' => $status,
            'time' => current_time('mysql'),
            'duration' => round(max(0, microtime(true) - $started_at), 3),
            'processed' => absint($stats['created']) + absint($stats['updated']) + absint($stats['unchanged']),
            'source' => self::is_gestpark_sync($settings) ? 'gestpark' : 'manual',
            'message' => sanitize_text_field((string) $message),
        ]);
    }

    protected static function load_imported_vehicle_map() {
        $query = new WP_Query([
            'post_type' => 'gpo_vehicle',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [[
                'key' => '_gpo_external_id',
                'compare' => 'EXISTS',
            ]],
        ]);
        $vehicles = [];

        foreach ((array) $query->posts as $post_id) {
            $external_id = sanitize_text_field((string) get_post_meta($post_id, '_gpo_external_id', true));
            if ($external_id !== '') {
                $vehicles[$external_id] = absint($post_id);
            }
        }

        return $vehicles;
    }

    protected static function sync_post_record($post_id, $item, $mapping) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('gpo_missing_local_vehicle', 'Il veicolo locale non e piu disponibile.');
        }

        $desired = [
            'post_title' => self::build_title($item, $mapping),
            'post_content' => wp_kses_post((string) self::extract_value($item, isset($mapping['description']) ? $mapping['description'] : '')),
            'post_status' => 'publish',
        ];
        $update = ['ID' => absint($post_id)];

        foreach ($desired as $field => $value) {
            if ((string) $post->{$field} !== (string) $value) {
                $update[$field] = $value;
            }
        }

        if (count($update) === 1) {
            return false;
        }

        $result = wp_update_post(wp_slash($update), true);

        return is_wp_error($result) ? $result : true;
    }

    protected static function reconcile_removed_vehicles($local_vehicles, $remote_ids, &$stats) {
        $remote_lookup = array_fill_keys(array_map('strval', (array) $remote_ids), true);

        foreach ((array) $local_vehicles as $external_id => $post_id) {
            if (isset($remote_lookup[(string) $external_id]) || get_post_status($post_id) === 'trash') {
                continue;
            }

            $trashed = wp_trash_post($post_id);
            if ($trashed) {
                $stats['deleted']++;
            } else {
                $stats['errors']++;
                GPO_Logger::add('Errore rimozione veicolo', ['idGestionale' => $external_id]);
            }
        }
    }

    protected static function build_title($item, $mapping) {
        $brand = self::extract_value($item, isset($mapping['brand']) ? $mapping['brand'] : '');
        $model = self::extract_value($item, isset($mapping['model']) ? $mapping['model'] : '');
        $version = self::extract_value($item, isset($mapping['version']) ? $mapping['version'] : '');
        $title = trim(implode(' ', array_filter([$brand, $model, $version])));
        return $title ?: 'Veicolo importato';
    }

    protected static function extract_value($item, $path) {
        if (!$path) {
            return '';
        }

        $parts = explode('.', $path);
        $value = $item;
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return '';
            }
        }
        return $value;
    }

    protected static function find_vehicle_by_external_id($external_id) {
        $query = new WP_Query([
            'post_type'      => 'gpo_vehicle',
            'post_status'    => 'any',
            'meta_key'       => '_gpo_external_id',
            'meta_value'     => sanitize_text_field((string) $external_id),
            'fields'         => 'ids',
            'posts_per_page' => 1,
        ]);
        return !empty($query->posts) ? (int) $query->posts[0] : 0;
    }

    protected static function assign_taxonomy($post_id, $taxonomy, $value) {
        $desired = $value !== '' ? [(string) $value] : [];
        $current = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'names']);
        if (is_wp_error($current)) {
            return false;
        }

        sort($desired, SORT_NATURAL | SORT_FLAG_CASE);
        sort($current, SORT_NATURAL | SORT_FLAG_CASE);
        if ($current === $desired) {
            return false;
        }

        wp_set_object_terms($post_id, $desired, $taxonomy, false);

        return true;
    }

    protected static function settings() {
        $defaults = class_exists('GPO_Admin') ? GPO_Admin::default_settings() : [
            'api' => GPO_API_Client::default_api_settings(),
            'mapping' => [],
            'sync' => ['enabled' => 1, 'interval' => 5],
        ];

        $settings = get_option('gpo_settings', []);
        $settings = array_replace_recursive($defaults, is_array($settings) ? $settings : []);
        $settings['api'] = GPO_API_Client::normalize_api_settings(isset($settings['api']) ? $settings['api'] : []);

        return $settings;
    }

    protected static function effective_mapping($settings) {
        if (self::is_gestpark_sync($settings)) {
            return [
                'external_id' => 'id',
                'vehicle_type' => 'vehicle_type',
                'brand' => 'brand',
                'model' => 'model',
                'version' => 'version',
                'description' => 'description',
                'condition' => 'condition',
                'year' => 'year',
                'registration_date' => 'registration_date',
                'arrival_date' => 'arrival_date',
                'price' => 'price',
                'dealer_price' => 'dealer_price',
                'price_negotiable' => 'price_negotiable',
                'vat_deductible' => 'vat_deductible',
                'fuel' => 'fuel',
                'mileage' => 'mileage',
                'accidented' => 'accidented',
                'non_smoker' => 'non_smoker',
                'company_vehicle' => 'company_vehicle',
                'classic_vehicle' => 'classic_vehicle',
                'body_type' => 'body_type',
                'transmission' => 'transmission',
                'engine_size' => 'engine_size',
                'cylinders' => 'cylinders',
                'power' => 'power',
                'power_kw' => 'power_kw',
                'gears' => 'gears',
                'drive' => 'drive',
                'color' => 'color',
                'doors' => 'doors',
                'seats' => 'seats',
                'co2_emissions' => 'co2_emissions',
                'emission_standard' => 'emission_standard',
                'urban_consumption' => 'urban_consumption',
                'highway_consumption' => 'highway_consumption',
                'combined_consumption' => 'combined_consumption',
                'plate' => 'plate',
                'vin' => 'vin',
                'location' => 'location',
                'status' => 'status',
                'gallery_urls' => 'gallery_urls',
            ];
        }

        $mapping = isset($settings['mapping']) && is_array($settings['mapping']) ? $settings['mapping'] : [];
        $required = ['condition', 'year', 'price', 'fuel', 'mileage', 'body_type', 'transmission', 'engine_size'];

        foreach ($required as $key) {
            if (empty($mapping[$key])) {
                return new WP_Error('gpo_missing_mapping', 'Mappatura incompleta: manca il campo obbligatorio ' . $key);
            }
        }

        return $mapping;
    }

    protected static function is_gestpark_sync($settings) {
        return GPO_API_Client::uses_gestpark(isset($settings['api']) ? $settings['api'] : []);
    }

    protected static function is_gestpark_item($item) {
        return isset($item['raw_payload']) && isset($item['gestpark_images']);
    }

    protected static function sync_core_fields($post_id, $item, $mapping) {
        $changed_fields = [];

        foreach (GPO_CPT::fields() as $meta_key => $label) {
            if ($meta_key === 'external_id') {
                continue;
            }

            if (empty($mapping[$meta_key])) {
                continue;
            }

            if (get_post_meta($post_id, '_gpo_lock_' . $meta_key, true) === '1') {
                continue;
            }

            $value = self::extract_value($item, $mapping[$meta_key]);
            if (is_array($value)) {
                $value = implode("\n", array_map('sanitize_text_field', $value));
            }

            $value = sanitize_textarea_field((string) $value);
            if (self::update_meta_if_changed($post_id, '_gpo_' . $meta_key, $value)) {
                $changed_fields[] = $meta_key;
            }
        }

        return $changed_fields;
    }

    protected static function sync_taxonomies($post_id, $changed_fields, $force = false) {
        $targets = [
            'brand' => ['gpo_brand', '_gpo_brand'],
            'fuel' => ['gpo_fuel', '_gpo_fuel'],
            'body_type' => ['gpo_body', '_gpo_body_type'],
            'transmission' => ['gpo_transmission', '_gpo_transmission'],
            'condition' => ['gpo_condition', '_gpo_condition'],
        ];
        $changed = false;

        foreach ($targets as $field => $target) {
            if (!$force && !in_array($field, (array) $changed_fields, true)) {
                continue;
            }
            $changed = self::assign_taxonomy($post_id, $target[0], (string) get_post_meta($post_id, $target[1], true)) || $changed;
        }

        return $changed;
    }

    protected static function sync_url_gallery($post_id, $item, $mapping) {
        $image_field = isset($mapping['gallery_urls']) ? $mapping['gallery_urls'] : '';
        if (!$image_field || get_post_meta($post_id, '_gpo_lock_gallery_urls', true) === '1') {
            return false;
        }

        $images = self::extract_value($item, $image_field);
        if (is_string($images)) {
            $images = preg_split('/\r\n|\r|\n|,/', $images);
        }

        if (is_array($images) && !empty($images)) {
            $urls = implode("\n", array_map('esc_url_raw', $images));
            if ((string) get_post_meta($post_id, '_gpo_gallery_urls', true) === $urls) {
                return false;
            }
            GPO_Image_Manager::sideload_gallery($post_id, $images);
            self::update_meta_if_changed($post_id, '_gpo_gallery_urls', $urls);
            return true;
        }

        return false;
    }

    protected static function sync_gestpark_extras($post_id, $item, &$error_count) {
        if (isset($item['gestpark_detail_complete']) && !$item['gestpark_detail_complete']) {
            return false;
        }

        $changed = false;
        $changed = self::update_unlocked_meta($post_id, 'public_notes', sanitize_textarea_field((string) ($item['public_notes'] ?? ''))) || $changed;
        $changed = self::update_unlocked_meta($post_id, 'internal_notes', sanitize_textarea_field((string) ($item['internal_notes'] ?? ''))) || $changed;

        $specs = !empty($item['specs_list']) && is_array($item['specs_list'])
            ? implode("\n", array_map('sanitize_text_field', $item['specs_list']))
            : '';
        $changed = self::update_unlocked_meta($post_id, 'specs', sanitize_textarea_field($specs)) || $changed;

        if (!empty($item['gestpark_optionals_present'])) {
            $accessories_list = !empty($item['accessories_list']) && is_array($item['accessories_list']) ? $item['accessories_list'] : [];
            natcasesort($accessories_list);
            $accessories = implode("\n", array_map('sanitize_text_field', $accessories_list));
            $changed = self::update_unlocked_meta($post_id, 'accessories', sanitize_textarea_field($accessories)) || $changed;

            $optionals = !empty($item['gestpark_optionals']) && is_array($item['gestpark_optionals']) ? $item['gestpark_optionals'] : [];
            usort($optionals, function ($a, $b) {
                $left = sprintf('%010d|%s', absint($a['optional_id'] ?? 0), strtolower((string) ($a['description'] ?? '')));
                $right = sprintf('%010d|%s', absint($b['optional_id'] ?? 0), strtolower((string) ($b['description'] ?? '')));
                return strcmp($left, $right);
            });
            $changed = self::update_meta_if_changed($post_id, '_gpo_optionals', self::stable_json($optionals)) || $changed;
        }

        $raw_payload = !empty($item['raw_payload']) && is_array($item['raw_payload']) ? self::stable_json($item['raw_payload']) : '';
        $changed = self::update_meta_if_changed($post_id, '_gpo_raw_payload', $raw_payload) || $changed;

        if (!empty($item['gestpark_images_present']) && get_post_meta($post_id, '_gpo_lock_gallery_urls', true) !== '1') {
            $images_changed = false;
            $image_errors = 0;
            GPO_Image_Manager::sideload_base64_gallery($post_id, isset($item['gestpark_images']) ? $item['gestpark_images'] : [], isset($item['id']) ? $item['id'] : '', $images_changed, $image_errors);
            $error_count += absint($image_errors);
            $changed = $images_changed || $changed;
            $changed = self::update_meta_if_changed($post_id, '_gpo_gallery_urls', '') || $changed;
        }

        return $changed;
    }

    protected static function update_unlocked_meta($post_id, $meta_key, $value) {
        if (get_post_meta($post_id, '_gpo_lock_' . $meta_key, true) === '1') {
            return false;
        }

        return self::update_meta_if_changed($post_id, '_gpo_' . $meta_key, $value);
    }

    protected static function update_meta_if_changed($post_id, $meta_key, $value) {
        $current = get_post_meta($post_id, $meta_key, true);
        if ((string) $current === (string) $value) {
            return false;
        }

        update_post_meta($post_id, $meta_key, $value);

        return true;
    }

    protected static function stable_json($value) {
        $normalize = function ($item) use (&$normalize) {
            if (!is_array($item)) {
                return $item;
            }

            $keys = array_keys($item);
            $is_list = empty($keys) || $keys === range(0, count($keys) - 1);
            if (!$is_list) {
                ksort($item);
            }
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child);
            }
            return $item;
        };

        return wp_json_encode($normalize($value));
    }


    public static function import_demo_vehicles() {
        return new WP_Error('gpo_demo_removed', 'Il flusso demo e stato disattivato. Usa solo la sincronizzazione reale delle API.');
    }

    protected static function upsert_demo_vehicle($item) {
        $external_id = isset($item['external_id']) ? sanitize_text_field((string) $item['external_id']) : '';
        if (!$external_id) {
            return 0;
        }

        $post_id = self::find_vehicle_by_external_id($external_id);
        $postarr = [
            'post_type' => 'gpo_vehicle',
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($item['title']),
            'post_content' => wp_kses_post($item['description']),
            'post_excerpt' => sanitize_text_field($item['excerpt']),
        ];

        if ($post_id) {
            $postarr['ID'] = $post_id;
            wp_update_post(wp_slash($postarr));
        } else {
            $post_id = wp_insert_post(wp_slash($postarr));
        }

        if (is_wp_error($post_id) || !$post_id) {
            GPO_Logger::add('Import demo fallito', ['external_id' => $external_id]);
            return 0;
        }

        $meta_map = [
            'external_id' => $external_id,
            'condition' => $item['condition'],
            'year' => $item['year'],
            'price' => $item['price'],
            'fuel' => $item['fuel'],
            'mileage' => $item['mileage'],
            'body_type' => $item['body_type'],
            'transmission' => $item['transmission'],
            'engine_size' => $item['engine_size'],
            'brand' => $item['brand'],
            'model' => $item['model'],
            'version' => $item['version'],
            'power' => $item['power'],
            'color' => $item['color'],
            'doors' => $item['doors'],
            'seats' => $item['seats'],
            'location' => $item['location'],
            'status' => $item['status'],
            'price_promo' => $item['price_promo'],
            'promo_start' => $item['promo_start'],
            'promo_end' => $item['promo_end'],
            'gallery_urls' => implode("
", $item['gallery_urls']),
        ];

        foreach ($meta_map as $meta_key => $value) {
            update_post_meta($post_id, '_gpo_' . $meta_key, sanitize_textarea_field((string) $value));
        }

        update_post_meta($post_id, '_gpo_internal_notes', sanitize_textarea_field($item['internal_notes']));
        update_post_meta($post_id, '_gpo_public_notes', sanitize_textarea_field($item['public_notes']));
        update_post_meta($post_id, '_gpo_specs', sanitize_textarea_field(implode("
", $item['specs'])));
        update_post_meta($post_id, '_gpo_accessories', sanitize_textarea_field(implode("
", $item['accessories'])));
        update_post_meta($post_id, '_gpo_featured', !empty($item['featured']) ? '1' : '0');
        update_post_meta($post_id, '_gpo_featured_order', absint($item['featured_order']));
        update_post_meta($post_id, '_gpo_featured_from', sanitize_text_field($item['featured_from']));
        update_post_meta($post_id, '_gpo_featured_to', sanitize_text_field($item['featured_to']));
        update_post_meta($post_id, '_gpo_badge', sanitize_text_field($item['badge']));
        update_post_meta($post_id, '_gpo_last_sync', current_time('mysql'));

        self::assign_taxonomy($post_id, 'gpo_brand', $item['brand']);
        self::assign_taxonomy($post_id, 'gpo_fuel', $item['fuel']);
        self::assign_taxonomy($post_id, 'gpo_body', $item['body_type']);
        self::assign_taxonomy($post_id, 'gpo_transmission', $item['transmission']);
        self::assign_taxonomy($post_id, 'gpo_condition', $item['condition']);

        return (int) $post_id;
    }

    protected static function demo_vehicles() {
        $now = current_time('timestamp');

        return [
            [
                'external_id' => 'demo-bmw-320d',
                'title' => 'BMW Serie 3 Touring 320d M Sport',
                'excerpt' => 'Station wagon premium con cambio automatico e allestimento sportivo.',
                'description' => 'Veicolo demo importato per testare il catalogo, la vetrina e le modifiche locali del plugin.',
                'condition' => 'Usato',
                'year' => '2021',
                'price' => '28900',
                'price_promo' => '27900',
                'promo_start' => date_i18n('Y-m-d\TH:i', strtotime('-2 days', $now)),
                'promo_end' => date_i18n('Y-m-d\TH:i', strtotime('+5 days', $now)),
                'fuel' => 'Diesel',
                'mileage' => '48200',
                'body_type' => 'Station Wagon',
                'transmission' => 'Automatico',
                'engine_size' => '1995',
                'brand' => 'BMW',
                'model' => 'Serie 3',
                'version' => 'Touring 320d M Sport',
                'power' => '190 CV',
                'color' => 'Nero metallizzato',
                'doors' => '5',
                'seats' => '5',
                'location' => 'Roma',
                'status' => 'Disponibile',
                'gallery_urls' => [
                    'https://images.unsplash.com/photo-1555215695-3004980ad54e?auto=format&fit=crop&w=1200&q=80',
                ],
                'internal_notes' => 'Demo: veicolo già in promozione e già attivo in vetrina.',
                'public_notes' => 'Garanzia 12 mesi inclusa e consegna rapida.',
                'specs' => ['Unico proprietario', 'Tagliandi certificati', 'IVA esposta'],
                'accessories' => ['Navigatore', 'Apple CarPlay', 'Sensori parcheggio', 'Telecamera posteriore'],
                'featured' => true,
                'featured_order' => 1,
                'featured_from' => date_i18n('Y-m-d\TH:i', strtotime('-1 day', $now)),
                'featured_to' => date_i18n('Y-m-d\TH:i', strtotime('+7 days', $now)),
                'badge' => 'Occasione del mese',
            ],
            [
                'external_id' => 'demo-audi-a4',
                'title' => 'Audi A4 Avant 35 TDI Business',
                'excerpt' => 'Familiare elegante con consumi contenuti e dotazione completa.',
                'description' => 'Veicolo demo ideale per verificare più elementi contemporaneamente nella vetrina.',
                'condition' => 'Usato',
                'year' => '2020',
                'price' => '26400',
                'price_promo' => '25900',
                'promo_start' => date_i18n('Y-m-d\TH:i', strtotime('-1 day', $now)),
                'promo_end' => date_i18n('Y-m-d\TH:i', strtotime('+10 days', $now)),
                'fuel' => 'Diesel',
                'mileage' => '61800',
                'body_type' => 'Station Wagon',
                'transmission' => 'Automatico',
                'engine_size' => '1968',
                'brand' => 'Audi',
                'model' => 'A4',
                'version' => 'Avant 35 TDI Business',
                'power' => '163 CV',
                'color' => 'Grigio Daytona',
                'doors' => '5',
                'seats' => '5',
                'location' => 'Milano',
                'status' => 'Disponibile',
                'gallery_urls' => [
                    'https://images.unsplash.com/photo-1544636331-e26879cd4d9b?auto=format&fit=crop&w=1200&q=80',
                ],
                'internal_notes' => 'Demo: secondo veicolo visibile subito in vetrina.',
                'public_notes' => 'Finanziamento personalizzabile con anticipo ridotto.',
                'specs' => ['Cronologia tagliandi', 'No fumatori', 'Pronta consegna'],
                'accessories' => ['Cruise control', 'Virtual cockpit', 'Clima automatico trizona'],
                'featured' => true,
                'featured_order' => 2,
                'featured_from' => date_i18n('Y-m-d\TH:i', strtotime('-3 hours', $now)),
                'featured_to' => date_i18n('Y-m-d\TH:i', strtotime('+14 days', $now)),
                'badge' => 'In vetrina',
            ],
            [
                'external_id' => 'demo-fiat-500e',
                'title' => 'Fiat 500e La Prima',
                'excerpt' => 'Compatta elettrica perfetta per provare la vetrina programmata nel futuro.',
                'description' => 'Questo veicolo demo entra in vetrina da domani, così puoi verificare la programmazione automatica.',
                'condition' => 'Nuovo',
                'year' => '2024',
                'price' => '31900',
                'price_promo' => '29900',
                'promo_start' => date_i18n('Y-m-d\TH:i', strtotime('+1 day', $now)),
                'promo_end' => date_i18n('Y-m-d\TH:i', strtotime('+12 days', $now)),
                'fuel' => 'Elettrica',
                'mileage' => '15',
                'body_type' => 'City Car',
                'transmission' => 'Automatico',
                'engine_size' => '0',
                'brand' => 'Fiat',
                'model' => '500e',
                'version' => 'La Prima',
                'power' => '118 CV',
                'color' => 'Rose Gold',
                'doors' => '3',
                'seats' => '4',
                'location' => 'Torino',
                'status' => 'In arrivo',
                'gallery_urls' => [
                    'https://images.unsplash.com/photo-1617469767053-d3b523a0b982?auto=format&fit=crop&w=1200&q=80',
                ],
                'internal_notes' => 'Demo: vetrina futura, utile per capire la programmazione.',
                'public_notes' => 'Auto nuova con incentivo e wallbox inclusa.',
                'specs' => ['Autonomia urbana elevata', 'Ricarica rapida', 'Connettività avanzata'],
                'accessories' => ['Keyless', 'Caricatore wireless', 'Fari full LED'],
                'featured' => true,
                'featured_order' => 3,
                'featured_from' => date_i18n('Y-m-d\TH:i', strtotime('+1 day', $now)),
                'featured_to' => date_i18n('Y-m-d\TH:i', strtotime('+20 days', $now)),
                'badge' => 'Da domani in vetrina',
            ],
            [
                'external_id' => 'demo-peugeot-3008',
                'title' => 'Peugeot 3008 1.5 BlueHDi Allure',
                'excerpt' => 'SUV demo con vetrina già scaduta, utile per controllare la logica temporale.',
                'description' => 'Questo veicolo demo aveva una programmazione vetrina già conclusa.',
                'condition' => 'Usato',
                'year' => '2019',
                'price' => '21900',
                'price_promo' => '20900',
                'promo_start' => date_i18n('Y-m-d\TH:i', strtotime('-10 days', $now)),
                'promo_end' => date_i18n('Y-m-d\TH:i', strtotime('-1 day', $now)),
                'fuel' => 'Diesel',
                'mileage' => '79200',
                'body_type' => 'SUV',
                'transmission' => 'Manuale',
                'engine_size' => '1499',
                'brand' => 'Peugeot',
                'model' => '3008',
                'version' => '1.5 BlueHDi Allure',
                'power' => '130 CV',
                'color' => 'Bianco Perla',
                'doors' => '5',
                'seats' => '5',
                'location' => 'Bologna',
                'status' => 'Disponibile',
                'gallery_urls' => [
                    'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?auto=format&fit=crop&w=1200&q=80',
                ],
                'internal_notes' => 'Demo: vetrina scaduta per testare esclusione automatica.',
                'public_notes' => 'Ottimo SUV familiare con costi di gestione contenuti.',
                'specs' => ['Cronologia manutenzione disponibile', 'Revisionata', 'Garanzia 12 mesi'],
                'accessories' => ['Mirror Screen', 'Sensori anteriori e posteriori', 'Cerchi in lega'],
                'featured' => true,
                'featured_order' => 4,
                'featured_from' => date_i18n('Y-m-d\TH:i', strtotime('-12 days', $now)),
                'featured_to' => date_i18n('Y-m-d\TH:i', strtotime('-2 days', $now)),
                'badge' => 'Promo scaduta',
            ],
            [
                'external_id' => 'demo-volkswagen-golf',
                'title' => 'Volkswagen Golf 1.5 eTSI Style',
                'excerpt' => 'Compatta mild hybrid utile per provare modifiche manuali fuori dalla vetrina.',
                'description' => 'Veicolo demo non in vetrina, pensato per testare modifiche, filtri e resa catalogo.',
                'condition' => 'Km0',
                'year' => '2024',
                'price' => '30900',
                'price_promo' => '',
                'promo_start' => '',
                'promo_end' => '',
                'fuel' => 'Ibrida benzina',
                'mileage' => '120',
                'body_type' => 'Berlina',
                'transmission' => 'Automatico',
                'engine_size' => '1498',
                'brand' => 'Volkswagen',
                'model' => 'Golf',
                'version' => '1.5 eTSI Style',
                'power' => '150 CV',
                'color' => 'Blu Lapislazzuli',
                'doors' => '5',
                'seats' => '5',
                'location' => 'Verona',
                'status' => 'Disponibile',
                'gallery_urls' => [
                    'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1200&q=80',
                ],
                'internal_notes' => 'Demo: lasciata fuori vetrina per testare l attivazione manuale.',
                'public_notes' => 'Ideale per vedere il rendering nel catalogo standard.',
                'specs' => ['Pronta consegna', 'Garanzia ufficiale', 'Infotainment evoluto'],
                'accessories' => ['Lane assist', 'Adaptive cruise control', 'Apple CarPlay e Android Auto'],
                'featured' => false,
                'featured_order' => 0,
                'featured_from' => '',
                'featured_to' => '',
                'badge' => '',
            ],
        ];
    }

    public static function purge_legacy_demo_vehicles() {
        $ids = get_posts([
            'post_type' => 'gpo_vehicle',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_gpo_is_template_demo',
                    'value' => '1',
                ],
                [
                    'key' => '_gpo_external_id',
                    'value' => 'demo-',
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        $deleted = 0;

        foreach ((array) $ids as $post_id) {
            $post_id = absint($post_id);
            if ($post_id > 0 && wp_delete_post($post_id, true)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            GPO_Logger::add('Contenuti demo rimossi', ['veicoli' => $deleted]);
        }

        return $deleted;
    }

}
