<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/includes/class-wpmm-htaccess.php';

// Remove rules and delete options
WPMM_Htaccess::sync(false);

delete_option('wpmm_enabled');
delete_option('wpmm_cookie_bypass');
delete_option('wpmm_allow_ips');
delete_option('wpmm_use_custom_503');
delete_option('wpmm_custom_503_path');
delete_option('wpmm_allow_xmlrpc');
delete_option('wpmm_bypass_token');
