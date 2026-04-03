<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_GitHub_Updater {
    const RELEASE_TRANSIENT = 'gpo_github_release_payload';
    const RELEASE_TTL = 900;

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'inject_update']);
        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 20, 3);
        add_filter('http_request_args', [__CLASS__, 'filter_http_args'], 20, 2);
        add_filter('upgrader_post_install', [__CLASS__, 'fix_install_directory'], 20, 3);
        add_action('upgrader_process_complete', [__CLASS__, 'clear_cache_after_upgrade'], 20, 2);
    }

    public static function clear_cache() {
        delete_site_transient(self::RELEASE_TRANSIENT);
        delete_site_transient('update_plugins');
    }

    public static function settings() {
        $defaults = class_exists('GPO_Admin') ? GPO_Admin::default_settings() : ['github' => []];
        $settings = get_option('gpo_settings', []);
        $settings = array_replace_recursive($defaults, is_array($settings) ? $settings : []);

        return isset($settings['github']) && is_array($settings['github']) ? $settings['github'] : [];
    }

    public static function summary() {
        $settings = self::settings();

        return [
            'enabled' => self::is_enabled(),
            'repository' => self::repository(),
            'repository_url' => self::repository_url(),
            'branch' => !empty($settings['branch']) ? sanitize_text_field($settings['branch']) : 'main',
            'asset_name' => self::asset_name(),
            'has_token' => !empty($settings['access_token']),
        ];
    }

    public static function is_enabled() {
        $settings = self::settings();

        return !empty($settings['enabled']) && self::repository() !== '';
    }

    public static function repository() {
        $settings = self::settings();
        $repository = trim((string) ($settings['repository'] ?? ''));

        if ($repository === '') {
            return '';
        }

        $repository = preg_replace('~^https?://github\.com/~i', '', $repository);
        $repository = preg_replace('~\.git$~i', '', $repository);
        $repository = trim($repository, '/');

        if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $repository)) {
            return '';
        }

        return $repository;
    }

    public static function repository_url() {
        $repository = self::repository();

        if ($repository === '') {
            return '';
        }

        return 'https://github.com/' . $repository;
    }

    public static function asset_name() {
        $settings = self::settings();
        $asset_name = trim((string) ($settings['release_asset'] ?? ''));

        if ($asset_name !== '') {
            return sanitize_file_name($asset_name);
        }

        return dirname(plugin_basename(GPO_PLUGIN_FILE)) . '.zip';
    }

    protected static function plugin_slug() {
        return dirname(plugin_basename(GPO_PLUGIN_FILE));
    }

    protected static function plugin_file() {
        return plugin_basename(GPO_PLUGIN_FILE);
    }

    protected static function current_version() {
        return ltrim((string) GPO_VERSION, 'vV');
    }

    protected static function token() {
        $settings = self::settings();
        return trim((string) ($settings['access_token'] ?? ''));
    }

    protected static function api_headers($binary = false) {
        $headers = [
            'User-Agent' => 'gestpark-online/' . self::current_version() . '; ' . home_url('/'),
            'Accept' => $binary ? 'application/octet-stream' : 'application/vnd.github+json',
        ];

        $token = self::token();
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    protected static function latest_release($force = false) {
        if (!self::is_enabled()) {
            return new WP_Error('gpo_github_disabled', 'Aggiornamenti GitHub non configurati.');
        }

        if (!$force) {
            $cached = get_site_transient(self::RELEASE_TRANSIENT);
            if (is_array($cached) && !empty($cached['version'])) {
                return $cached;
            }
        }

        $url = 'https://api.github.com/repos/' . self::repository() . '/releases/latest';
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => self::api_headers(false),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('gpo_github_http', 'GitHub ha risposto con HTTP ' . $code . '.');
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($payload) || empty($payload['tag_name'])) {
            return new WP_Error('gpo_github_payload', 'Risposta GitHub non valida.');
        }

        $asset = self::match_asset(isset($payload['assets']) && is_array($payload['assets']) ? $payload['assets'] : []);
        $release = [
            'tag' => sanitize_text_field((string) $payload['tag_name']),
            'version' => ltrim(sanitize_text_field((string) $payload['tag_name']), 'vV'),
            'name' => sanitize_text_field((string) ($payload['name'] ?? $payload['tag_name'])),
            'body' => (string) ($payload['body'] ?? ''),
            'html_url' => esc_url_raw((string) ($payload['html_url'] ?? self::repository_url())),
            'published_at' => sanitize_text_field((string) ($payload['published_at'] ?? '')),
            'zipball_url' => esc_url_raw((string) ($payload['zipball_url'] ?? '')),
            'asset_api_url' => !empty($asset['url']) ? esc_url_raw((string) $asset['url']) : '',
            'asset_name' => !empty($asset['name']) ? sanitize_file_name((string) $asset['name']) : '',
        ];
        $release['package_url'] = $release['asset_api_url'] ?: $release['zipball_url'];

        set_site_transient(self::RELEASE_TRANSIENT, $release, self::RELEASE_TTL);

        return $release;
    }

    protected static function match_asset($assets) {
        if (empty($assets)) {
            return [];
        }

        $preferred = self::asset_name();
        foreach ($assets as $asset) {
            if (!is_array($asset) || empty($asset['name'])) {
                continue;
            }

            if (sanitize_file_name((string) $asset['name']) === $preferred) {
                return $asset;
            }
        }

        foreach ($assets as $asset) {
            if (!is_array($asset) || empty($asset['name'])) {
                continue;
            }

            if (substr(strtolower((string) $asset['name']), -4) === '.zip') {
                return $asset;
            }
        }

        return [];
    }

    public static function inject_update($transient) {
        if (!self::is_enabled() || empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        $plugin_file = self::plugin_file();
        if (!isset($transient->checked[$plugin_file])) {
            return $transient;
        }

        $release = self::latest_release();
        if (is_wp_error($release) || empty($release['version']) || empty($release['package_url'])) {
            return $transient;
        }

        if (version_compare($release['version'], self::current_version(), '<=')) {
            if (!isset($transient->no_update[$plugin_file])) {
                $transient->no_update[$plugin_file] = (object) [
                    'slug' => self::plugin_slug(),
                    'plugin' => $plugin_file,
                    'new_version' => self::current_version(),
                    'url' => $release['html_url'] ?? self::repository_url(),
                    'package' => '',
                ];
            }

            return $transient;
        }

        $transient->response[$plugin_file] = (object) [
            'slug' => self::plugin_slug(),
            'plugin' => $plugin_file,
            'new_version' => $release['version'],
            'package' => $release['package_url'],
            'url' => $release['html_url'] ?? self::repository_url(),
        ];

        return $transient;
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug)) {
            return $result;
        }

        $slug = sanitize_key((string) $args->slug);
        if ($slug !== sanitize_key(self::plugin_slug())) {
            return $result;
        }

        $release = self::latest_release();
        if (is_wp_error($release)) {
            return $result;
        }

        $headers = get_file_data(GPO_PLUGIN_FILE, [
            'Name' => 'Plugin Name',
            'Description' => 'Description',
            'Author' => 'Author',
        ]);

        return (object) [
            'name' => $headers['Name'] ?: 'gestpark online',
            'slug' => self::plugin_slug(),
            'version' => $release['version'],
            'author' => $headers['Author'],
            'homepage' => $release['html_url'] ?: self::repository_url(),
            'download_link' => $release['package_url'],
            'sections' => [
                'description' => wpautop(esc_html($headers['Description'])),
                'changelog' => wpautop(esc_html((string) ($release['body'] ?? 'Nessun changelog disponibile.'))),
            ],
        ];
    }

    public static function filter_http_args($args, $url) {
        if (strpos((string) $url, 'https://api.github.com/repos/' . self::repository() . '/') !== 0) {
            return $args;
        }

        $headers = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : [];
        $binary = strpos((string) $url, '/releases/assets/') !== false || strpos((string) $url, '/zipball/') !== false;
        $args['headers'] = array_merge($headers, self::api_headers($binary));

        return $args;
    }

    public static function fix_install_directory($response, $hook_extra, $result) {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== self::plugin_file()) {
            return $response;
        }

        if (empty($result['destination'])) {
            return $response;
        }

        $expected = trailingslashit(WP_PLUGIN_DIR) . self::plugin_slug();
        $current = untrailingslashit((string) $result['destination']);

        if ($current === untrailingslashit($expected)) {
            return $result;
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            return $result;
        }

        if ($wp_filesystem->exists($expected)) {
            $wp_filesystem->delete($expected, true);
        }

        if (function_exists('move_dir')) {
            $moved = move_dir($current, $expected, true);
            if (is_wp_error($moved)) {
                return $moved;
            }
        } else {
            $moved = $wp_filesystem->move($current, $expected, true);
            if (!$moved) {
                return new WP_Error('gpo_github_move_failed', 'Impossibile spostare il plugin aggiornato nella cartella finale.');
            }
        }

        $result['destination'] = $expected;
        $result['destination_name'] = basename($expected);

        return $result;
    }

    public static function clear_cache_after_upgrade($upgrader, $hook_extra) {
        if (empty($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
            return;
        }

        if (!empty($hook_extra['plugins']) && is_array($hook_extra['plugins']) && in_array(self::plugin_file(), $hook_extra['plugins'], true)) {
            self::clear_cache();
            return;
        }

        if (!empty($hook_extra['plugin']) && $hook_extra['plugin'] === self::plugin_file()) {
            self::clear_cache();
        }
    }
}
