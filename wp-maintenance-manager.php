<?php
/**
 * Plugin Name: WP Maintenance Manager
 * Description: Toggles Apache-level maintenance mode via .htaccess with admin cookie bypass, IP allowlist, loopback safety, and optional custom 503 page.
 * Version: 0.3.1
 * Author: hamishwright
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

define('WPMM_VERSION', '0.3.1');
define('WPMM_PLUGIN_FILE', __FILE__);
define('WPMM_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once WPMM_PLUGIN_DIR . 'includes/class-wpmm-htaccess.php';
require_once WPMM_PLUGIN_DIR . 'includes/class-wpmm-rules.php';
require_once WPMM_PLUGIN_DIR . 'includes/class-wpmm-admin.php';

register_activation_hook(__FILE__, function () {
    // No filesystem interaction during activation.
});

register_deactivation_hook(__FILE__, function () {
    // No filesystem interaction during deactivation.
});

// Boot admin UI
add_action('plugins_loaded', function () {
    if (is_admin()) {
        new WPMM_Admin();
    }
});
