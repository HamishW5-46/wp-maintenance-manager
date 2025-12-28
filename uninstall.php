<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

// No .htaccess interaction during uninstall.

delete_option('wpmm_enabled');
delete_option('wpmm_cookie_bypass');
delete_option('wpmm_allow_ips');
delete_option('wpmm_use_custom_503');
delete_option('wpmm_custom_503_path');
delete_option('wpmm_allow_xmlrpc');
delete_option('wpmm_bypass_token');
