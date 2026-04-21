<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Engagement {
    public static function default_settings() {
        return [
            'promo_color' => '#dc2626',
            'rules' => [],
        ];
    }

    public static function settings() {
        $defaults = self::default_settings();
        $settings = get_option('gpo_settings', []);
        $engagement = is_array($settings) && !empty($settings['engagement']) && is_array($settings['engagement'])
            ? $settings['engagement']
            : [];

        return wp_parse_args($engagement, $defaults);
    }

    public static function promo_color() {
        $color = sanitize_hex_color((string) (self::settings()['promo_color'] ?? ''));
        return $color ?: '#dc2626';
    }

    public static function sanitize_rules($rules) {
        $rules = is_array($rules) ? $rules : [];
        $sanitized = [];

        foreach ($rules as $rule) {
            $item = self::sanitize_rule($rule);
            if ($item) {
                $sanitized[] = $item;
            }
        }

        return $sanitized;
    }

    protected static function sanitize_rule($rule) {
        if (!is_array($rule)) {
            return null;
        }

        $target_type = sanitize_key((string) ($rule['target_type'] ?? 'vehicle'));
        if (!in_array($target_type, ['vehicle', 'vehicles', 'brands'], true)) {
            $target_type = 'vehicle';
        }

        $discount_type = sanitize_key((string) ($rule['discount_type'] ?? 'fixed'));
        if (!in_array($discount_type, ['fixed', 'percent'], true)) {
            $discount_type = 'fixed';
        }

        $vehicle_id = absint($rule['vehicle_id'] ?? 0);
        $vehicle_ids = array_values(array_filter(array_map('absint', (array) ($rule['vehicle_ids'] ?? []))));
        $brands = array_values(array_filter(array_map([__CLASS__, 'sanitize_brand_key'], (array) ($rule['brands'] ?? []))));
        $title = sanitize_text_field((string) ($rule['title'] ?? ''));
        $promo_text = sanitize_text_field((string) ($rule['promo_text'] ?? ''));
        $value = isset($rule['value']) ? max(0, (float) str_replace(',', '.', (string) $rule['value'])) : 0;
        $active = !empty($rule['active']) ? 1 : 0;

        if ($discount_type === 'percent') {
            $value = min(100, $value);
        }

        if ($target_type === 'vehicle' && $vehicle_id < 1) {
            return null;
        }

        if ($target_type === 'vehicles' && empty($vehicle_ids)) {
            return null;
        }

        if ($target_type === 'brands' && empty($brands)) {
            return null;
        }

        if ($value <= 0) {
            return null;
        }

        return [
            'id' => sanitize_key((string) ($rule['id'] ?? uniqid('promo_', false))),
            'title' => $title,
            'target_type' => $target_type,
            'vehicle_id' => $vehicle_id,
            'vehicle_ids' => $vehicle_ids,
            'brands' => $brands,
            'discount_type' => $discount_type,
            'value' => $value,
            'start_date' => self::sanitize_date((string) ($rule['start_date'] ?? '')),
            'start_time' => self::sanitize_time((string) ($rule['start_time'] ?? '')),
            'end_date' => self::sanitize_date((string) ($rule['end_date'] ?? '')),
            'end_time' => self::sanitize_time((string) ($rule['end_time'] ?? '')),
            'promo_text' => $promo_text,
            'active' => $active,
        ];
    }

    public static function promotion_for_vehicle($post_id) {
        $post_id = absint($post_id);
        if ($post_id < 1) {
            return null;
        }

        $original_price = (float) get_post_meta($post_id, '_gpo_price', true);
        if ($original_price <= 0) {
            return null;
        }

        $rule = self::active_rule_for_vehicle($post_id);
        if (!$rule) {
            return null;
        }

        $discounted = self::apply_discount($original_price, $rule);
        if ($discounted === null || $discounted >= $original_price) {
            return null;
        }

        $discount_label = $rule['discount_type'] === 'percent'
            ? '-' . number_format_i18n((float) $rule['value'], 0) . '%'
            : '- ' . GPO_Frontend::format_price_public((float) $rule['value']);

        $title = trim((string) $rule['title']);
        $badge = $title !== '' ? $title : $discount_label;

        return [
            'rule_id' => $rule['id'],
            'priority' => self::rule_priority($rule),
            'target_type' => $rule['target_type'],
            'title' => $title !== '' ? $title : 'Promo attiva',
            'badge' => $badge,
            'promo_text' => trim((string) $rule['promo_text']),
            'original_price' => $original_price,
            'discounted_price' => $discounted,
            'formatted_original' => GPO_Frontend::format_price_public($original_price),
            'formatted_discounted' => GPO_Frontend::format_price_public($discounted),
            'discount_label' => $discount_label,
            'color' => self::promo_color(),
        ];
    }

    public static function active_rule_for_vehicle($post_id) {
        $post_id = absint($post_id);
        if ($post_id < 1) {
            return null;
        }

        $rules = self::settings()['rules'] ?? [];
        $best = null;

        foreach ((array) $rules as $index => $rule) {
            if (empty($rule['active']) || !self::is_rule_active_now($rule) || !self::rule_matches_vehicle($rule, $post_id)) {
                continue;
            }

            $candidate = [
                'rule' => $rule,
                'priority' => self::rule_priority($rule),
                'index' => $index,
            ];

            if ($best === null) {
                $best = $candidate;
                continue;
            }

            if ($candidate['priority'] > $best['priority']) {
                $best = $candidate;
                continue;
            }

            if ($candidate['priority'] === $best['priority'] && $candidate['index'] < $best['index']) {
                $best = $candidate;
            }
        }

        return $best ? $best['rule'] : null;
    }

    public static function window_is_active($start_date = '', $start_time = '', $end_date = '', $end_time = '', $timestamp = null) {
        $timestamp = $timestamp ?: current_time('timestamp');
        $start = self::combine_datetime($start_date, $start_time);
        $end = self::combine_datetime($end_date, $end_time, true);

        if ($start && $timestamp < $start) {
            return false;
        }

        if ($end && $timestamp > $end) {
            return false;
        }

        return true;
    }

    public static function combine_datetime($date, $time = '', $end_of_day = false) {
        $date = self::sanitize_date((string) $date);
        $time = self::sanitize_time((string) $time);

        if ($date === '') {
            return null;
        }

        if ($time === '') {
            $time = $end_of_day ? '23:59' : '00:00';
        }

        try {
            $zone = wp_timezone();
            $dt = new DateTimeImmutable($date . ' ' . $time . ':00', $zone);
            return $dt->getTimestamp();
        } catch (Exception $e) {
            return null;
        }
    }

    protected static function apply_discount($price, $rule) {
        $price = (float) $price;
        $value = (float) ($rule['value'] ?? 0);
        if ($price <= 0 || $value <= 0) {
            return null;
        }

        if (($rule['discount_type'] ?? 'fixed') === 'percent') {
            $discounted = $price - (($price * $value) / 100);
        } else {
            $discounted = $price - $value;
        }

        return max(0, round($discounted, 2));
    }

    protected static function is_rule_active_now($rule) {
        return self::window_is_active(
            (string) ($rule['start_date'] ?? ''),
            (string) ($rule['start_time'] ?? ''),
            (string) ($rule['end_date'] ?? ''),
            (string) ($rule['end_time'] ?? '')
        );
    }

    protected static function rule_matches_vehicle($rule, $post_id) {
        $target_type = $rule['target_type'] ?? 'vehicle';
        if ($target_type === 'vehicle') {
            return absint($rule['vehicle_id'] ?? 0) === $post_id;
        }

        if ($target_type === 'vehicles') {
            return in_array($post_id, array_map('absint', (array) ($rule['vehicle_ids'] ?? [])), true);
        }

        if ($target_type === 'brands') {
            $brand_key = self::sanitize_brand_key((string) get_post_meta($post_id, '_gpo_brand', true));
            return in_array($brand_key, (array) ($rule['brands'] ?? []), true);
        }

        return false;
    }

    protected static function rule_priority($rule) {
        $map = [
            'brands' => 1,
            'vehicles' => 2,
            'vehicle' => 3,
        ];

        return $map[$rule['target_type'] ?? 'brands'] ?? 0;
    }

    protected static function sanitize_date($value) {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    protected static function sanitize_time($value) {
        $value = trim((string) $value);
        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
    }

    protected static function sanitize_brand_key($value) {
        $value = strtolower(remove_accents((string) $value));
        $value = str_replace(['&', '.', "'"], ' ', $value);
        $value = preg_replace('/\s+/', '-', trim($value));
        return sanitize_title($value);
    }
}
