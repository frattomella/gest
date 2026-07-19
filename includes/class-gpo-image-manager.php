<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Image_Manager {
    public static function sideload_gallery($post_id, $image_urls) {
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_ids = [];
        foreach ((array) $image_urls as $url) {
            $url = esc_url_raw(trim((string) $url));
            if (!$url) {
                continue;
            }

            $existing = self::find_attachment_by_source($url);
            if ($existing) {
                $attachment_ids[] = $existing;
                continue;
            }

            $tmp = download_url($url);
            if (is_wp_error($tmp)) {
                GPO_Logger::add('Download immagine fallito', ['url' => $url]);
                continue;
            }

            $file_array = [
                'name'     => wp_basename(parse_url($url, PHP_URL_PATH)),
                'tmp_name' => $tmp,
            ];

            $attachment_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($attachment_id)) {
                @unlink($tmp);
                GPO_Logger::add('Sideload immagine fallito', ['url' => $url, 'errore' => $attachment_id->get_error_message()]);
                continue;
            }

            update_post_meta($attachment_id, '_gpo_source_url', $url);
            $attachment_ids[] = $attachment_id;
        }

        if (!empty($attachment_ids)) {
            set_post_thumbnail($post_id, $attachment_ids[0]);
            update_post_meta($post_id, '_gpo_gallery_ids', $attachment_ids);
        }

        return $attachment_ids;
    }

    public static function sideload_base64_gallery($post_id, $images, $external_id = '', &$changed = null, &$error_count = null) {
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $images = array_values(array_filter((array) $images, function ($image) {
            return is_array($image) && !empty($image['image']);
        }));
        $attachment_ids = [];
        $primary_id = 0;
        $changed = false;
        $error_count = 0;

        if (empty($images)) {
            $current_ids = array_values(array_filter(array_map('absint', (array) get_post_meta($post_id, '_gpo_gallery_ids', true))));
            if (!empty($current_ids)) {
                update_post_meta($post_id, '_gpo_gallery_ids', []);
                $changed = true;
            }
            if (get_post_thumbnail_id($post_id)) {
                delete_post_thumbnail($post_id);
                $changed = true;
            }
            return [];
        }

        foreach ($images as $index => $image) {
            $reference = !empty($image['reference']) ? sanitize_text_field((string) $image['reference']) : sanitize_text_field((string) $external_id . '-' . ($index + 1));
            $existing = self::find_attachment_by_reference($reference);
            if ($existing) {
                $source_modified = sanitize_text_field((string) ($image['source_modified'] ?? ''));
                $stored_modified = (string) get_post_meta($existing, '_gpo_source_modified', true);
                $source_size = isset($image['source_size']) ? absint($image['source_size']) : 0;
                $stored_size = absint(get_post_meta($existing, '_gpo_source_size', true));
                $same_source_revision = $source_modified !== ''
                    && $source_modified === $stored_modified
                    && (!$source_size || $source_size === $stored_size);

                if ($same_source_revision) {
                    $stored_hash = (string) get_post_meta($existing, '_gpo_source_hash', true);
                    $changed = self::update_attachment_source_data($existing, $image, $stored_hash) || $changed;
                    $attachment_ids[] = $existing;
                    if (!empty($image['is_primary'])) {
                        $primary_id = $existing;
                    }
                    continue;
                }
            }

            $payload = self::decode_base64_payload((string) $image['image']);
            if (!$payload) {
                $error_count++;
                GPO_Logger::add('Decodifica immagine base64 fallita', ['reference' => $reference]);
                if ($existing) {
                    $attachment_ids[] = $existing;
                    if (!empty($image['is_primary'])) {
                        $primary_id = $existing;
                    }
                }
                continue;
            }
            $payload_hash = hash('sha256', $payload);

            if ($existing) {
                $stored_hash = (string) get_post_meta($existing, '_gpo_source_hash', true);
                if ($stored_hash === '') {
                    $attached_file = get_attached_file($existing);
                    if ($attached_file && is_readable($attached_file)) {
                        $stored_hash = (string) hash_file('sha256', $attached_file);
                    }
                }

                if (!hash_equals($stored_hash, $payload_hash)) {
                    if (!self::replace_attachment_payload($existing, $payload, $image)) {
                        $error_count++;
                        GPO_Logger::add('Aggiornamento immagine fallito', ['reference' => $reference]);
                        $attachment_ids[] = $existing;
                        if (!empty($image['is_primary'])) {
                            $primary_id = $existing;
                        }
                        continue;
                    }
                    $changed = true;
                }

                $changed = self::update_attachment_source_data($existing, $image, $payload_hash) || $changed;
                $attachment_ids[] = $existing;
                if (!empty($image['is_primary'])) {
                    $primary_id = $existing;
                }
                continue;
            }

            $filename = !empty($image['filename']) ? sanitize_file_name((string) $image['filename']) : sanitize_file_name((string) $external_id . '-' . ($index + 1) . '.jpg');
            if (!$filename) {
                $filename = 'gestpark-' . md5($reference) . '.jpg';
            }

            $upload = wp_upload_bits($filename, null, $payload);
            if (!empty($upload['error'])) {
                $error_count++;
                GPO_Logger::add('Upload immagine base64 fallito', ['reference' => $reference, 'errore' => $upload['error']]);
                continue;
            }

            $mime = !empty($image['mime']) ? sanitize_text_field((string) $image['mime']) : 'image/jpeg';
            $attachment_id = wp_insert_attachment([
                'post_mime_type' => $mime,
                'post_title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
                'post_status' => 'inherit',
            ], $upload['file'], $post_id);

            if (is_wp_error($attachment_id) || !$attachment_id) {
                $error_count++;
                GPO_Logger::add('Creazione attachment base64 fallita', ['reference' => $reference]);
                continue;
            }

            $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            if (!is_wp_error($metadata) && !empty($metadata)) {
                wp_update_attachment_metadata($attachment_id, $metadata);
            }

            update_post_meta($attachment_id, '_gpo_source_reference', $reference);
            self::update_attachment_source_data($attachment_id, $image, $payload_hash);
            $attachment_ids[] = (int) $attachment_id;
            $changed = true;

            if (!empty($image['is_primary'])) {
                $primary_id = (int) $attachment_id;
            }
        }

        if (!empty($attachment_ids)) {
            if (!$primary_id) {
                $primary_id = (int) $attachment_ids[0];
            }
            if ((int) get_post_thumbnail_id($post_id) !== $primary_id) {
                set_post_thumbnail($post_id, $primary_id);
                $changed = true;
            }

            $attachment_ids = array_values(array_unique(array_map('absint', $attachment_ids)));
            $current_ids = array_values(array_filter(array_map('absint', (array) get_post_meta($post_id, '_gpo_gallery_ids', true))));
            if ($current_ids !== $attachment_ids) {
                update_post_meta($post_id, '_gpo_gallery_ids', $attachment_ids);
                $changed = true;
            }
        }

        return $attachment_ids;
    }

    protected static function replace_attachment_payload($attachment_id, $payload, $image) {
        $file = get_attached_file($attachment_id);
        if (!$file || !is_writable(dirname($file))) {
            return false;
        }

        $written = file_put_contents($file, $payload, LOCK_EX);
        if ($written === false) {
            return false;
        }

        $mime = !empty($image['mime']) ? sanitize_text_field((string) $image['mime']) : 'image/jpeg';
        $attachment = get_post($attachment_id);
        if ($attachment && (string) $attachment->post_mime_type !== $mime) {
            wp_update_post(['ID' => $attachment_id, 'post_mime_type' => $mime]);
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $file);
        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return true;
    }

    protected static function update_attachment_source_data($attachment_id, $image, $payload_hash = '') {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id || !is_array($image)) {
            return false;
        }

        $caption = sanitize_text_field((string) ($image['caption'] ?? ''));
        $description = sanitize_textarea_field((string) ($image['description'] ?? ''));
        $attachment = get_post($attachment_id);
        $postarr = ['ID' => $attachment_id];

        if ($caption !== '' && $attachment && (string) $attachment->post_title !== $caption) {
            $postarr['post_title'] = $caption;
        }
        if ($attachment && (string) $attachment->post_excerpt !== $caption) {
            $postarr['post_excerpt'] = $caption;
        }
        if ($attachment && (string) $attachment->post_content !== $description) {
            $postarr['post_content'] = $description;
        }
        $changed = false;
        if (count($postarr) > 1) {
            wp_update_post(wp_slash($postarr));
            $changed = true;
        }

        $source_meta = [
            '_gpo_source_hash' => sanitize_text_field((string) $payload_hash),
            '_gpo_source_position' => isset($image['position']) ? absint($image['position']) : 0,
            '_gpo_source_primary' => !empty($image['is_primary']) ? '1' : '0',
            '_gpo_source_type' => sanitize_text_field((string) ($image['source_type'] ?? '')),
            '_gpo_source_size' => isset($image['source_size']) ? absint($image['source_size']) : 0,
            '_gpo_source_modified' => sanitize_text_field((string) ($image['source_modified'] ?? '')),
        ];

        foreach ($source_meta as $meta_key => $meta_value) {
            if ((string) get_post_meta($attachment_id, $meta_key, true) !== (string) $meta_value) {
                update_post_meta($attachment_id, $meta_key, $meta_value);
                $changed = true;
            }
        }

        return $changed;
    }

    protected static function find_attachment_by_source($url) {
        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'meta_key'       => '_gpo_source_url',
            'meta_value'     => $url,
            'fields'         => 'ids',
            'posts_per_page' => 1,
        ]);

        return !empty($query->posts) ? (int) $query->posts[0] : 0;
    }

    protected static function find_attachment_by_reference($reference) {
        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'meta_key'       => '_gpo_source_reference',
            'meta_value'     => $reference,
            'fields'         => 'ids',
            'posts_per_page' => 1,
        ]);

        return !empty($query->posts) ? (int) $query->posts[0] : 0;
    }

    protected static function decode_base64_payload($payload) {
        $payload = trim((string) $payload);
        if ($payload === '') {
            return '';
        }

        if (strpos($payload, 'base64,') !== false) {
            $parts = explode('base64,', $payload, 2);
            $payload = $parts[1];
        }

        $decoded = base64_decode($payload, true);

        return $decoded !== false ? $decoded : '';
    }
}
