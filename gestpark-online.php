<?php
/**
 * Plugin Name: gestpark online
 * Plugin URI: https://example.com/
 * Description: Plugin WordPress per importare veicoli da API esterne, gestire vetrine, promozioni e visualizzazione veicoli con supporto Gutenberg ed Elementor.
 * Version: 0.2.5
 * Author: OpenAI
 * Update URI: https://gestpark-online.local/plugin
 * Text Domain: gestpark-online
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GPO_VERSION', '0.2.5');
define('GPO_PLUGIN_FILE', __FILE__);
define('GPO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GPO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GPO_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once GPO_PLUGIN_DIR . 'includes/class-gpo-logger.php';
require_once GPO_PLUGIN_DIR . 'includes/class-gpo-core.php';
require_once GPO_PLUGIN_DIR . 'includes/class-gpo-cpt.php';
require_once GPO_PLUGIN_DIR . 'includes/class-gpo-api-client.php';
require_once GPO_PLUGIN_DIR . 'includes/class-gpo-image-manager.php';
require_once GPO_PLUGIN_DIR . 'includes/class-gpo-sync-manager.php';
require_once GPO_PLUGIN_DIR . 'includes/class-gpo-github-updater.php';
require_once GPO_PLUGIN_DIR . 'includes/class-gpo-frontend.php';
require_once GPO_PLUGIN_DIR . 'includes/class-gpo-blocks.php';
require_once GPO_PLUGIN_DIR . 'admin/class-gpo-admin.php';
require_once GPO_PLUGIN_DIR . 'elementor/class-gpo-elementor.php';

register_activation_hook(__FILE__, ['GPO_Core', 'activate']);
register_deactivation_hook(__FILE__, ['GPO_Core', 'deactivate']);

function gpo_boot_plugin() {
    $core = new GPO_Core();
    $core->boot();
}

gpo_boot_plugin();
