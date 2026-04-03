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

    public static function sideload_base64_gallery($post_id, $images, $external_id = '') {
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_ids = [];
        $primary_id = 0;

        foreach ((array) $images as $index => $image) {
            if (!is_array($image) || empty($image['image'])) {
                continue;
            }

            $reference = !empty($image['reference']) ? sanitize_text_field((string) $image['reference']) : sanitize_text_field((string) $external_id . '-' . ($index + 1));
            $existing = self::find_attachment_by_reference($reference);
            if ($existing) {
                $attachment_ids[] = $existing;
                if (!empty($image['is_primary'])) {
                    $primary_id = $existing;
                }
                continue;
            }

            $payload = self::decode_base64_payload((string) $image['image']);
            if (!$payload) {
                GPO_Logger::add('Decodifica immagine base64 fallita', ['reference' => $reference]);
                continue;
            }

            $filename = !empty($image['filename']) ? sanitize_file_name((string) $image['filename']) : sanitize_file_name((string) $external_id . '-' . ($index + 1) . '.jpg');
            if (!$filename) {
                $filename = 'gestpark-' . md5($reference) . '.jpg';
            }

            $upload = wp_upload_bits($filename, null, $payload);
            if (!empty($upload['error'])) {
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
                GPO_Logger::add('Creazione attachment base64 fallita', ['reference' => $reference]);
                continue;
            }

            $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            if (!is_wp_error($metadata) && !empty($metadata)) {
                wp_update_attachment_metadata($attachment_id, $metadata);
            }

            update_post_meta($attachment_id, '_gpo_source_reference', $reference);
            $attachment_ids[] = (int) $attachment_id;

            if (!empty($image['is_primary'])) {
                $primary_id = (int) $attachment_id;
            }
        }

        if (!empty($attachment_ids)) {
            if (!$primary_id) {
                $primary_id = (int) $attachment_ids[0];
            }
            set_post_thumbnail($post_id, $primary_id);
            update_post_meta($post_id, '_gpo_gallery_ids', array_values(array_unique(array_map('absint', $attachment_ids))));
        }

        return $attachment_ids;
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
