<?php
/**
 * Plugin Name: WP Maintenance Manager
 * Description: Toggles Apache-level maintenance mode via .htaccess with admin cookie bypass, IP allowlist, loopback safety, and optional custom 503 page.
 * Version: 0.1.0
 * Author: Hamish Wright
 */

defined('ABSPATH') || exit;

define('WPMM_VERSION', '0.1.0');
define('WPMM_PLUGIN_FILE', __FILE__);
define('WPMM_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once WPMM_PLUGIN_DIR . 'includes/class-wpmm-htaccess.php';
require_once WPMM_PLUGIN_DIR . 'includes/class-wpmm-rules.php';
require_once WPMM_PLUGIN_DIR . 'includes/class-wpmm-admin.php';

register_activation_hook(__FILE__, function () {
    // On activation: do nothing destructive. User can enable via UI.
    // But we will ensure our markers are cleaned if someone re-activated.
    WPMM_Htaccess::sync(false);
});

register_deactivation_hook(__FILE__, function () {
    // Always remove our block on deactivation.
    WPMM_Htaccess::sync(false);
});

// Boot admin UI
add_action('plugins_loaded', function () {
    if (is_admin()) {
        new WPMM_Admin();
    }
});
