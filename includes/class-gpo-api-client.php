<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_API_Client {
    const MODE_GESTPARK_AUTO = 'gestpark_auto';
    const MODE_MANUAL = 'manual';

    const MANUAL_FORMAT_GESTPARK = 'gestpark';
    const MANUAL_FORMAT_GENERIC = 'generic';

    public static function default_api_settings() {
        return [
            'connection_mode' => self::MODE_GESTPARK_AUTO,
            'manual_format' => self::MANUAL_FORMAT_GESTPARK,
            'endpoint' => 'https://parkplatformapi.cedisoft.com/api/vetrina/mainphoto',
            'detail_endpoint' => 'https://parkplatformapi.cedisoft.com/api/vetrina/{idGestionale}',
            'login_endpoint' => 'https://parkplatformapi.cedisoft.com/api/auth/login',
            'auth_method' => 'bearer',
            'token' => '',
            'api_key' => '',
            'items_path' => '',
            'timeout' => 20,
            'gestpark_base_url' => 'https://parkplatformapi.cedisoft.com',
            'gestpark_username' => '',
            'gestpark_password' => '',
            'gestpark_login_path' => '/api/auth/login',
            'gestpark_list_path' => '/api/vetrina',
            'gestpark_mainphoto_path' => '/api/vetrina/mainphoto',
            'gestpark_detail_path' => '/api/vetrina/{idGestionale}',
            'prefer_mainphoto' => 1,
            'include_details' => 1,
        ];
    }

    public static function api_settings() {
        $settings = get_option('gpo_settings', []);
        $api = isset($settings['api']) && is_array($settings['api']) ? $settings['api'] : [];

        return self::normalize_api_settings($api);
    }

    public static function normalize_api_settings($api) {
        $defaults = self::default_api_settings();
        $api = array_replace($defaults, is_array($api) ? $api : []);

        if (empty($api['connection_mode']) && !empty($api['endpoint'])) {
            $api['connection_mode'] = self::MODE_MANUAL;
        }

        if (empty($api['manual_format'])) {
            $api['manual_format'] = self::looks_like_gestpark_endpoint($api['endpoint']) ? self::MANUAL_FORMAT_GESTPARK : self::MANUAL_FORMAT_GENERIC;
        }

        if (!in_array($api['connection_mode'], [self::MODE_GESTPARK_AUTO, self::MODE_MANUAL], true)) {
            $api['connection_mode'] = self::MODE_GESTPARK_AUTO;
        }

        if (!in_array($api['manual_format'], [self::MANUAL_FORMAT_GESTPARK, self::MANUAL_FORMAT_GENERIC], true)) {
            $api['manual_format'] = self::MANUAL_FORMAT_GESTPARK;
        }

        if (!in_array($api['auth_method'], ['none', 'bearer', 'x_api_key'], true)) {
            $api['auth_method'] = 'bearer';
        }

        $api['timeout'] = max(5, absint($api['timeout']));
        $api['prefer_mainphoto'] = !empty($api['prefer_mainphoto']) ? 1 : 0;
        $api['include_details'] = !empty($api['include_details']) ? 1 : 0;

        foreach (['endpoint', 'detail_endpoint', 'login_endpoint', 'gestpark_base_url', 'gestpark_login_path', 'gestpark_list_path', 'gestpark_mainphoto_path', 'gestpark_detail_path'] as $key) {
            $api[$key] = trim((string) $api[$key]);
        }

        return $api;
    }

    public static function uses_gestpark($api = null) {
        $api = $api ?: self::api_settings();

        return $api['connection_mode'] === self::MODE_GESTPARK_AUTO
            || ($api['connection_mode'] === self::MODE_MANUAL && $api['manual_format'] === self::MANUAL_FORMAT_GESTPARK);
    }

    public static function uses_generic_manual($api = null) {
        $api = $api ?: self::api_settings();

        return $api['connection_mode'] === self::MODE_MANUAL && $api['manual_format'] === self::MANUAL_FORMAT_GENERIC;
    }

    public static function connection_summary($api = null) {
        $api = $api ?: self::api_settings();

        if ($api['connection_mode'] === self::MODE_GESTPARK_AUTO) {
            $ready = !empty($api['gestpark_username']) && !empty($api['gestpark_password']);

            return [
                'mode_label' => 'Automatico ParkPlatform API',
                'status_label' => $ready ? 'Pronto al login JWT' : 'Credenziali mancanti',
                'description' => 'Accedi con l account email abilitato alle API ParkPlatform. Il plugin recupera token JWT, lista veicoli, foto principali e dettaglio completo dei dati importati da GestPark.',
                'ready' => $ready,
                'surface_label' => $api['prefer_mainphoto'] ? 'Lista con thumbnail' : 'Lista veicoli base',
                'detail_label' => $api['include_details'] ? 'Dettaglio completo attivo' : 'Solo lista veicoli',
            ];
        }

        if ($api['manual_format'] === self::MANUAL_FORMAT_GESTPARK) {
            $ready = !empty($api['endpoint']) && !empty($api['token']);

            return [
                'mode_label' => 'Manuale ParkPlatform API',
                'status_label' => $ready ? 'Endpoint e token presenti' : 'Completa token o endpoint',
                'description' => 'Inserisci manualmente gli endpoint ParkPlatform API e il JWT Bearer ottenuto dal login API.',
                'ready' => $ready,
                'surface_label' => $api['endpoint'] ? 'Endpoint lista configurato' : 'Lista non configurata',
                'detail_label' => $api['include_details'] ? 'Dettaglio per galleria e optional' : 'Dettaglio disattivato',
            ];
        }

        $ready = !empty($api['endpoint']);

        return [
            'mode_label' => 'Manuale JSON',
            'status_label' => $ready ? 'Endpoint generico configurato' : 'Endpoint mancante',
            'description' => 'Mantieni la compatibilita con feed JSON personalizzati, mappando i campi dal pannello dedicato.',
            'ready' => $ready,
            'surface_label' => $api['items_path'] ? 'Array annidato tramite items path' : 'Array diretto',
            'detail_label' => 'Mappatura campi manuale',
        ];
    }

    public static function fetch_remote_data($context = 'sync') {
        $api = self::api_settings();

        if (self::uses_gestpark($api)) {
            return self::fetch_gestpark_data($api, $context);
        }

        return self::fetch_generic_data($api);

        $settings = get_option('gpo_settings', []);
        $api = isset($settings['api']) ? $settings['api'] : [];
        $endpoint = isset($api['endpoint']) ? trim($api['endpoint']) : '';

        if (!$endpoint) {
            return new WP_Error('gpo_missing_endpoint', 'Endpoint API mancante.');
        }

        $headers = [
            'Accept' => 'application/json',
        ];

        $auth_method = isset($api['auth_method']) ? $api['auth_method'] : 'none';
        if ($auth_method === 'bearer' && !empty($api['token'])) {
            $headers['Authorization'] = 'Bearer ' . $api['token'];
        }
        if ($auth_method === 'x_api_key' && !empty($api['api_key'])) {
            $headers['X-API-Key'] = $api['api_key'];
        }

        $args = [
            'timeout' => !empty($api['timeout']) ? absint($api['timeout']) : 20,
            'headers' => $headers,
        ];

        $response = wp_remote_get(esc_url_raw($endpoint), $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('gpo_bad_response', 'Risposta API non valida: ' . $code);
        }

        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('gpo_bad_json', 'Il body API non contiene JSON valido.');
        }

        $path = isset($api['items_path']) ? trim($api['items_path']) : '';
        if (!$path) {
            return is_array($json) ? $json : [];
        }

        $parts = explode('.', $path);
        $data = $json;
        foreach ($parts as $part) {
            if (is_array($data) && array_key_exists($part, $data)) {
                $data = $data[$part];
            } else {
                return new WP_Error('gpo_bad_path', 'Il percorso degli elementi API non è valido.');
            }
        }

        return is_array($data) ? $data : [];
    }

    public static function test_connection() {
        $result = self::fetch_remote_data('test');
        if (is_wp_error($result)) {
            GPO_Logger::add('Test connessione API fallito', ['errore' => $result->get_error_message()]);
            return $result;
        }

        GPO_Logger::add('Test connessione API completato', ['elementi_ricevuti' => count($result)]);
        return [
            'success' => true,
            'count'   => count($result),
        ];
    }

    protected static function fetch_generic_data($api) {
        $endpoint = $api['endpoint'];
        if (!$endpoint) {
            return new WP_Error('gpo_missing_endpoint', 'Endpoint API mancante.');
        }

        $result = self::request_json('GET', $endpoint, [
            'timeout' => $api['timeout'],
            'headers' => self::build_auth_headers($api),
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return self::extract_items($result, $api['items_path']);
    }

    protected static function fetch_gestpark_data($api, $context) {
        $base_url = rtrim($api['gestpark_base_url'], '/');
        $token = self::resolve_gestpark_token($api, $base_url);
        if (is_wp_error($token)) {
            return $token;
        }

        $list_endpoint = $api['connection_mode'] === self::MODE_GESTPARK_AUTO
            ? ($api['prefer_mainphoto'] ? $api['gestpark_mainphoto_path'] : $api['gestpark_list_path'])
            : $api['endpoint'];

        $list_url = self::build_url($base_url, $list_endpoint);
        if (!$list_url) {
            return new WP_Error('gpo_missing_gestpark_list', 'Endpoint lista GestPark mancante.');
        }

        $list_result = self::request_json('GET', $list_url, [
            'timeout' => $api['timeout'],
            'headers' => self::build_auth_headers($api, $token),
        ]);

        if (is_wp_error($list_result)) {
            return $list_result;
        }

        $items = self::extract_items($list_result, '');
        if (is_wp_error($items)) {
            return $items;
        }

        $normalized = [];
        $detail_url = self::gestpark_detail_endpoint($api, $base_url);

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $detail = [];
            $external_id = isset($item['idGestionale']) ? $item['idGestionale'] : '';

            if ($context === 'sync' && !empty($api['include_details']) && $detail_url && $external_id !== '') {
                $response = self::request_json('GET', str_replace('{idGestionale}', rawurlencode((string) $external_id), $detail_url), [
                    'timeout' => $api['timeout'],
                    'headers' => self::build_auth_headers($api, $token),
                ]);

                if (is_wp_error($response)) {
                    GPO_Logger::add('Dettaglio veicolo GestPark non disponibile', [
                        'external_id' => $external_id,
                        'errore' => $response->get_error_message(),
                    ]);
                } elseif (is_array($response)) {
                    $detail = $response;
                }
            }

            $normalized[] = self::normalize_gestpark_item($item, $detail);
        }

        return $normalized;
    }

    protected static function resolve_gestpark_token($api, $base_url) {
        if ($api['connection_mode'] === self::MODE_MANUAL && !empty($api['token'])) {
            return trim((string) $api['token']);
        }

        if ($api['connection_mode'] === self::MODE_MANUAL) {
            return new WP_Error('gpo_missing_manual_token', 'Inserisci il bearer token GestPark per la connessione manuale.');
        }

        if (empty($api['gestpark_username']) || empty($api['gestpark_password'])) {
            return new WP_Error('gpo_missing_credentials', 'Inserisci username e password del tuo account GestPark.');
        }

        $login_url = self::build_url($base_url, $api['gestpark_login_path']);
        if (!$login_url) {
            return new WP_Error('gpo_missing_login_endpoint', 'Endpoint login GestPark mancante.');
        }

        $login_response = self::request_json_with_meta('POST', $login_url, [
            'timeout' => $api['timeout'],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'Username' => $api['gestpark_username'],
                'Password' => $api['gestpark_password'],
            ],
        ]);

        if (is_wp_error($login_response)) {
            if (strpos($login_response->get_error_message(), 'Username: The Username field is not a valid e-mail address.') !== false) {
                return new WP_Error('gpo_invalid_login_email', 'Login API rifiutato: il campo Username deve essere un indirizzo email valido, non un nome utente semplice.');
            }
            return $login_response;
        }

        $response = $login_response['json'];
        $token = self::extract_login_token($response);
        if (!$token) {
            return new WP_Error('gpo_missing_login_token', self::describe_missing_login_token($response, $login_response));
        }

        return $token;
    }

    protected static function extract_login_token($response) {
        if (!is_array($response)) {
            return '';
        }

        $candidates = [
            isset($response['token']) ? $response['token'] : '',
            isset($response['user']['token']) ? $response['user']['token'] : '',
            isset($response['accessToken']) ? $response['accessToken'] : '',
            isset($response['user']['accessToken']) ? $response['user']['accessToken'] : '',
            isset($response['jwt']) ? $response['jwt'] : '',
            isset($response['user']['jwt']) ? $response['user']['jwt'] : '',
            isset($response['jwtToken']) ? $response['jwtToken'] : '',
            isset($response['user']['jwtToken']) ? $response['user']['jwtToken'] : '',
            isset($response['data']['token']) ? $response['data']['token'] : '',
            isset($response['result']['token']) ? $response['result']['token'] : '',
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    protected static function describe_missing_login_token($response, $meta = []) {
        if (!is_array($response)) {
            return 'Login ParkPlatform API eseguito ma token JWT non presente nella risposta.';
        }

        $parts = [];
        if (!empty($meta['code'])) {
            $parts[] = 'status: ' . absint($meta['code']);
        }
        $parts[] = 'campi top-level: ' . self::implode_safe(array_keys($response));

        if (!empty($response['user']) && is_array($response['user'])) {
            $user = $response['user'];
            $parts[] = 'user.id: ' . self::scalar_debug(isset($user['id']) ? $user['id'] : null);
            $parts[] = 'user.name: ' . self::scalar_debug(isset($user['name']) ? $user['name'] : null);
            $parts[] = 'user.nominativo: ' . self::scalar_debug(isset($user['nominativo']) ? $user['nominativo'] : null);
            $parts[] = 'user.roles: ' . self::implode_safe(isset($user['roles']) && is_array($user['roles']) ? $user['roles'] : []);
            $parts[] = 'user.token: ' . (!empty($user['token']) ? 'presente' : 'assente');
        } else {
            $parts[] = 'user: assente o non valido';
        }

        if (array_key_exists('errorMessage', $response)) {
            $parts[] = 'errorMessage: ' . self::scalar_debug($response['errorMessage']);
        } else {
            $parts[] = 'errorMessage: campo assente';
        }

        if (!empty($meta['headers']) && is_array($meta['headers'])) {
            $header_keys = array_keys($meta['headers']);
            $parts[] = 'header response: ' . self::implode_safe($header_keys);
            $parts[] = 'authorization header: ' . (isset($meta['headers']['authorization']) ? 'presente' : 'assente');
            $parts[] = 'x-auth-token header: ' . (isset($meta['headers']['x-auth-token']) ? 'presente' : 'assente');
            $parts[] = 'set-cookie: ' . (isset($meta['headers']['set-cookie']) ? 'presente' : 'assente');
        }

        if (!empty($response['user']['roles']) && is_array($response['user']['roles']) && !in_array('ApiUser', $response['user']['roles'], true)) {
            $parts[] = 'ipotesi: l account non sembra avere il ruolo ApiUser';
        }

        return 'Login ParkPlatform API eseguito ma token JWT non presente nella risposta. Debug login: ' . implode(' | ', $parts);
    }

    protected static function implode_safe($values) {
        $values = is_array($values) ? $values : [];
        $values = array_filter(array_map('sanitize_text_field', array_map('strval', $values)), 'strlen');

        return !empty($values) ? implode(', ', $values) : 'nessuno';
    }

    protected static function scalar_debug($value) {
        if ($value === null) {
            return 'null';
        }

        if ($value === '') {
            return 'vuoto';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return sanitize_text_field((string) $value);
        }

        return 'non-scalare';
    }

    protected static function normalize_gestpark_item($list_item, $detail_item) {
        $vehicle = array_replace_recursive((array) $list_item, (array) $detail_item);
        $year = self::extract_year(isset($vehicle['immatricolazione']) ? $vehicle['immatricolazione'] : '');
        if (!$year) {
            $year = self::extract_year(isset($vehicle['dataArrivo']) ? $vehicle['dataArrivo'] : '');
        }

        $gallery = !empty($detail_item['listaFoto']) && is_array($detail_item['listaFoto'])
            ? $detail_item['listaFoto']
            : (isset($list_item['listaFoto']) && is_array($list_item['listaFoto']) ? $list_item['listaFoto'] : []);

        return [
            'id' => isset($vehicle['idGestionale']) ? (string) $vehicle['idGestionale'] : '',
            'brand' => isset($vehicle['marca']) ? $vehicle['marca'] : '',
            'model' => isset($vehicle['modello']) ? $vehicle['modello'] : '',
            'version' => isset($vehicle['versione']) ? $vehicle['versione'] : '',
            'description' => self::build_vehicle_description($vehicle),
            'condition' => self::infer_condition($vehicle),
            'year' => $year,
            'price' => isset($vehicle['prezzo']) ? (string) $vehicle['prezzo'] : '',
            'fuel' => isset($vehicle['descrizioneAlimentazione']) ? $vehicle['descrizioneAlimentazione'] : '',
            'mileage' => isset($vehicle['kmPercorsi']) ? (string) $vehicle['kmPercorsi'] : '',
            'body_type' => isset($vehicle['tipoCarrozzeria']) ? $vehicle['tipoCarrozzeria'] : '',
            'transmission' => isset($vehicle['tipoCambio']) ? $vehicle['tipoCambio'] : '',
            'engine_size' => isset($vehicle['cilindrata']) ? (string) $vehicle['cilindrata'] : '',
            'power' => self::format_power(isset($vehicle['potenzakW']) ? $vehicle['potenzakW'] : ''),
            'color' => isset($vehicle['coloreCarrozzeria']) ? $vehicle['coloreCarrozzeria'] : '',
            'doors' => isset($vehicle['numeroPorte']) ? (string) $vehicle['numeroPorte'] : '',
            'seats' => isset($vehicle['numeroPosti']) ? (string) $vehicle['numeroPosti'] : '',
            'plate' => isset($vehicle['targa']) ? $vehicle['targa'] : '',
            'vin' => isset($vehicle['telaio']) ? $vehicle['telaio'] : '',
            'location' => '',
            'status' => self::infer_status($vehicle),
            'public_notes' => self::build_public_notes($vehicle),
            'internal_notes' => self::build_internal_notes($vehicle),
            'specs_list' => self::build_specs_list($vehicle),
            'accessories_list' => self::build_accessories_list($vehicle),
            'gestpark_images' => self::normalize_gestpark_images($gallery, isset($vehicle['idGestionale']) ? $vehicle['idGestionale'] : ''),
            'raw_payload' => $vehicle,
        ];
    }

    protected static function build_vehicle_description($vehicle) {
        $chunks = array_filter([
            trim(implode(' ', array_filter([
                isset($vehicle['marca']) ? $vehicle['marca'] : '',
                isset($vehicle['modello']) ? $vehicle['modello'] : '',
                isset($vehicle['versione']) ? $vehicle['versione'] : '',
            ]))),
            self::extract_year(isset($vehicle['immatricolazione']) ? $vehicle['immatricolazione'] : ''),
            isset($vehicle['descrizioneAlimentazione']) ? $vehicle['descrizioneAlimentazione'] : '',
            isset($vehicle['tipoCambio']) ? $vehicle['tipoCambio'] : '',
            !empty($vehicle['kmPercorsi']) ? number_format_i18n((float) $vehicle['kmPercorsi'], 0) . ' km' : '',
        ]);

        return $chunks ? implode(' - ', $chunks) . '.' : 'Veicolo importato da GestPark.';
    }

    protected static function infer_condition($vehicle) {
        $mileage = isset($vehicle['kmPercorsi']) ? (int) $vehicle['kmPercorsi'] : 0;

        if ($mileage <= 0) {
            return 'Nuovo';
        }

        if ($mileage <= 1000) {
            return 'Km0';
        }

        return 'Usato';
    }

    protected static function infer_status($vehicle) {
        if (!empty($vehicle['dataArrivo'])) {
            $arrival = strtotime((string) $vehicle['dataArrivo']);
            if ($arrival && $arrival > time()) {
                return 'In arrivo';
            }
        }

        return 'Disponibile';
    }

    protected static function build_public_notes($vehicle) {
        $flags = [];

        if (!empty($vehicle['prezzoTrattabile'])) {
            $flags[] = 'Prezzo trattabile';
        }

        if (!empty($vehicle['ivaDeducibile'])) {
            $flags[] = 'IVA deducibile';
        }

        if (!empty($vehicle['noFumatori'])) {
            $flags[] = 'Veicolo non fumatori';
        }

        return implode("\n", $flags);
    }

    protected static function build_internal_notes($vehicle) {
        $notes = ['Importato automaticamente da GestPark.'];

        if (isset($vehicle['prezzoDealer']) && $vehicle['prezzoDealer'] !== '') {
            $notes[] = 'Prezzo dealer: ' . $vehicle['prezzoDealer'];
        }

        if (!empty($vehicle['incidentato'])) {
            $notes[] = 'Veicolo segnalato come incidentato.';
        }

        if (!empty($vehicle['dataArrivo'])) {
            $notes[] = 'Data arrivo: ' . $vehicle['dataArrivo'];
        }

        return implode("\n", $notes);
    }

    protected static function build_specs_list($vehicle) {
        $specs = [];
        $map = [
            'descrizioneAlimentazione' => 'Alimentazione',
            'cilindrata' => 'Cilindrata',
            'potenzakW' => 'Potenza',
            'tipoCambio' => 'Cambio',
            'trazione' => 'Trazione',
            'tipoCarrozzeria' => 'Carrozzeria',
            'numeroPorte' => 'Porte',
            'numeroPosti' => 'Posti',
            'coloreCarrozzeria' => 'Colore',
            'emissioniCo2' => 'CO2',
            'consumoUrbano' => 'Consumo urbano',
            'consumoAutostradale' => 'Consumo autostradale',
            'consumoMisto' => 'Consumo misto',
            'targa' => 'Targa',
            'telaio' => 'Telaio',
        ];

        foreach ($map as $key => $label) {
            if (!isset($vehicle[$key]) || $vehicle[$key] === '' || $vehicle[$key] === null) {
                continue;
            }

            $value = $vehicle[$key];
            if ($key === 'potenzakW') {
                $value = self::format_power($value);
            } elseif (in_array($key, ['cilindrata', 'emissioniCo2'], true)) {
                $value = $value . ($key === 'cilindrata' ? ' cc' : ' g/km');
            } elseif (in_array($key, ['consumoUrbano', 'consumoAutostradale', 'consumoMisto'], true)) {
                $value = $value . ' l/100km';
            }

            $specs[] = $label . ': ' . $value;
        }

        if (!empty($vehicle['prezzoTrattabile'])) {
            $specs[] = 'Prezzo trattabile';
        }

        if (!empty($vehicle['ivaDeducibile'])) {
            $specs[] = 'IVA deducibile';
        }

        return $specs;
    }

    protected static function build_accessories_list($vehicle) {
        $items = [];
        $optionals = isset($vehicle['optionals']) && is_array($vehicle['optionals']) ? $vehicle['optionals'] : [];

        foreach ($optionals as $optional) {
            if (!is_array($optional) || empty($optional['descrizione'])) {
                continue;
            }

            $label = trim((string) $optional['descrizione']);
            if (!empty($optional['diSerie'])) {
                $label .= ' (di serie)';
            }
            $items[] = $label;
        }

        return $items;
    }

    protected static function normalize_gestpark_images($images, $external_id) {
        $gallery = [];

        foreach ((array) $images as $index => $image) {
            if (!is_array($image) || empty($image['immagine'])) {
                continue;
            }

            $gallery[] = [
                'reference' => !empty($image['idGestionaleImmagine']) ? (string) $image['idGestionaleImmagine'] : (string) $external_id . '-' . ($index + 1),
                'is_primary' => !empty($image['principale']),
                'position' => isset($image['posizione']) ? (int) $image['posizione'] : ($index + 1),
                'mime' => self::image_mime_from_type(isset($image['tipoFoto']) ? $image['tipoFoto'] : ''),
                'filename' => strtolower(trim((string) $external_id)) . '-' . ($index + 1) . '.jpg',
                'image' => (string) $image['immagine'],
            ];
        }

        usort($gallery, function ($left, $right) {
            return ($left['position'] ?? 0) <=> ($right['position'] ?? 0);
        });

        return $gallery;
    }

    protected static function build_auth_headers($api, $token_override = '') {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($token_override !== '') {
            $headers['Authorization'] = 'Bearer ' . $token_override;
            return $headers;
        }

        if ($api['auth_method'] === 'bearer' && !empty($api['token'])) {
            $headers['Authorization'] = 'Bearer ' . $api['token'];
        }

        if ($api['auth_method'] === 'x_api_key' && !empty($api['api_key'])) {
            $headers['X-API-Key'] = $api['api_key'];
        }

        return $headers;
    }

    protected static function request_json($method, $url, $args = []) {
        $meta = self::request_json_with_meta($method, $url, $args);
        if (is_wp_error($meta)) {
            return $meta;
        }

        return $meta['json'];
    }

    protected static function request_json_with_meta($method, $url, $args = []) {
        $request_args = wp_parse_args($args, [
            'timeout' => 20,
            'data_format' => 'body',
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $request_args['method'] = strtoupper((string) $method);

        if (isset($request_args['body']) && is_array($request_args['body'])) {
            $request_args['body'] = wp_json_encode($request_args['body']);
            if (empty($request_args['headers']['Content-Type'])) {
                $request_args['headers']['Content-Type'] = 'application/json';
            }
        }

        $response = wp_remote_request(esc_url_raw($url), $request_args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        $headers = self::normalize_response_headers(wp_remote_retrieve_headers($response));

        if ($code < 200 || $code >= 300) {
            return new WP_Error('gpo_bad_response', self::extract_error_message($json, $code, $body));
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('gpo_bad_json', 'La risposta API non contiene JSON valido.');
        }

        return [
            'code' => $code,
            'json' => $json,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    protected static function normalize_response_headers($headers) {
        if (is_object($headers) && method_exists($headers, 'getAll')) {
            $headers = $headers->getAll();
        }

        if (!is_array($headers)) {
            return [];
        }

        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = $value;
        }

        return $normalized;
    }

    protected static function extract_items($payload, $path = '') {
        if (!$path) {
            return is_array($payload) ? $payload : [];
        }

        $parts = explode('.', $path);
        $data = $payload;

        foreach ($parts as $part) {
            if (is_array($data) && array_key_exists($part, $data)) {
                $data = $data[$part];
                continue;
            }

            return new WP_Error('gpo_bad_path', 'Il percorso degli elementi API non e valido.');
        }

        return is_array($data) ? $data : [];
    }

    protected static function gestpark_detail_endpoint($api, $base_url) {
        $path = $api['connection_mode'] === self::MODE_GESTPARK_AUTO
            ? $api['gestpark_detail_path']
            : $api['detail_endpoint'];

        return self::build_url($base_url, $path);
    }

    protected static function build_url($base_url, $path) {
        $path = trim((string) $path);
        if (!$path) {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $base_url = rtrim((string) $base_url, '/');
        if (!$base_url) {
            return '';
        }

        return $base_url . '/' . ltrim($path, '/');
    }

    protected static function extract_error_message($json, $code, $body) {
        if (is_array($json)) {
            if (!empty($json['errors']) && is_array($json['errors'])) {
                $chunks = [];

                foreach ($json['errors'] as $field => $messages) {
                    if (is_array($messages)) {
                        $messages = implode(' ', array_map('sanitize_text_field', $messages));
                    }

                    $chunks[] = sanitize_text_field((string) $field) . ': ' . sanitize_text_field((string) $messages);
                }

                if (!empty($chunks)) {
                    return 'Risposta API non valida: ' . $code . ' - errore validazione richiesta (' . implode(' | ', $chunks) . ')';
                }
            }

            foreach (['errorMessage', 'message', 'error', 'detail'] as $key) {
                if (!empty($json[$key]) && is_string($json[$key])) {
                    return 'Risposta API non valida: ' . $code . ' - ' . $json[$key];
                }
            }
        }

        $body = trim((string) $body);
        if ($body !== '') {
            return 'Risposta API non valida: ' . $code . ' - ' . wp_html_excerpt(wp_strip_all_tags($body), 140, '...');
        }

        return 'Risposta API non valida: ' . $code;
    }

    protected static function extract_year($value) {
        $timestamp = $value ? strtotime((string) $value) : false;

        return $timestamp ? gmdate('Y', $timestamp) : '';
    }

    protected static function format_power($kw) {
        if ($kw === '' || $kw === null) {
            return '';
        }

        $kw_value = (float) $kw;
        if (!$kw_value) {
            return (string) $kw;
        }

        $cv = (int) round($kw_value * 1.35962);

        return rtrim(rtrim(number_format($kw_value, 0, '.', ''), '0'), '.') . ' kW / ' . $cv . ' CV';
    }

    protected static function image_mime_from_type($type) {
        $type = strtolower((string) $type);

        if ($type === 'png') {
            return 'image/png';
        }

        return 'image/jpeg';
    }

    protected static function looks_like_gestpark_endpoint($endpoint) {
        $endpoint = strtolower((string) $endpoint);

        return strpos($endpoint, 'parkplatformapi') !== false
            || strpos($endpoint, '/api/vetrina') !== false
            || strpos($endpoint, '{idgestionale}') !== false;
    }
}
