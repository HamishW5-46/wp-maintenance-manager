<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/includes/class-wpmh-htaccess.php';

// Remove rules and delete options
WPMH_Htaccess::sync(false);

delete_option('wpmh_enabled');
delete_option('wpmh_cookie_bypass');
delete_option('wpmh_allow_ips');
delete_option('wpmh_use_custom_503');
delete_option('wpmh_custom_503_path');
delete_option('wpmh_allow_xmlrpc');