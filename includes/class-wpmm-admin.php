<?php
defined('ABSPATH') || exit;

class WPMM_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_wpmm_apply', [$this, 'handle_apply']);
        add_action('admin_post_wpmm_emergency_disable', [$this, 'handle_emergency_disable']);
    }

    public function menu() {
        add_options_page(
            'Maintenance (htaccess)',
            'Maintenance (htaccess)',
            'manage_options',
            'wpmm-maintenance',
            [$this, 'page']
        );
    }

    public function register_settings() {
        register_setting('wpmm', 'wpmm_enabled', ['type' => 'boolean', 'default' => false]);
        register_setting('wpmm', 'wpmm_cookie_bypass', ['type' => 'boolean', 'default' => true]);
        register_setting('wpmm', 'wpmm_allow_ips', ['type' => 'string', 'default' => '']);
        register_setting('wpmm', 'wpmm_use_custom_503', ['type' => 'boolean', 'default' => false]);
        register_setting('wpmm', 'wpmm_custom_503_path', ['type' => 'string', 'default' => '/maintenance/index.html']);
        register_setting('wpmm', 'wpmm_allow_xmlrpc', ['type' => 'boolean', 'default' => false]);
    }

    public function handle_apply() {
        if (!current_user_can('manage_options')) {
            wp_die('Nope.');
        }
        check_admin_referer('wpmm_apply');

        $post = wp_unslash($_POST);

        $enabled = isset($post['wpmm_enabled']) ? (bool) $post['wpmm_enabled'] : false;
        update_option('wpmm_enabled', $enabled);

        update_option('wpmm_cookie_bypass', isset($post['wpmm_cookie_bypass']));
        update_option('wpmm_allow_xmlrpc', isset($post['wpmm_allow_xmlrpc']));

        $ips = isset($post['wpmm_allow_ips']) ? sanitize_textarea_field($post['wpmm_allow_ips']) : '';
        update_option('wpmm_allow_ips', $ips);

        $use_custom = isset($post['wpmm_use_custom_503']);
        update_option('wpmm_use_custom_503', $use_custom);

        $custom_path = isset($post['wpmm_custom_503_path']) ? trim(sanitize_text_field($post['wpmm_custom_503_path'])) : '';
        update_option('wpmm_custom_503_path', $custom_path);

        $token = (string) get_option('wpmm_bypass_token', '');
        if ($enabled && (bool) get_option('wpmm_cookie_bypass', true) && current_user_can('manage_options')) {
            if ($token === '') {
                $token = wp_generate_password(32, false, false);
                update_option('wpmm_bypass_token', $token);
            }
        }

        $result = WPMM_Htaccess::sync($enabled);

        if ($enabled && (bool) get_option('wpmm_cookie_bypass', true) && current_user_can('manage_options') && $token !== '') {
            if (!headers_sent()) {
                setcookie('wpmm_bypass', $token, [
                    'expires' => time() + (DAY_IN_SECONDS * 30),
                    'path' => '/',
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }

        $redirect = add_query_arg([
            'page' => 'wpmm-maintenance',
            'wpmm_updated' => $result['ok'] ? '1' : '0',
            'wpmm_msg' => rawurlencode($result['message']),
        ], admin_url('options-general.php'));

        wp_safe_redirect($redirect);
        exit;
    }

    public function page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $enabled       = (bool) get_option('wpmm_enabled', false);
        $cookie_bypass = (bool) get_option('wpmm_cookie_bypass', true);
        $allow_ips     = (string) get_option('wpmm_allow_ips', '');
        $use_custom    = (bool) get_option('wpmm_use_custom_503', false);
        $custom_path   = (string) get_option('wpmm_custom_503_path', '/maintenance/index.html');
        $allow_xmlrpc  = (bool) get_option('wpmm_allow_xmlrpc', false);

        $can_manage = WPMM_Htaccess::can_manage_htaccess();

        $get = wp_unslash($_GET);
        $updated = isset($get['wpmm_updated']) ? sanitize_text_field($get['wpmm_updated']) : null;
        $msg = isset($get['wpmm_msg']) ? sanitize_text_field(rawurldecode($get['wpmm_msg'])) : '';

        echo '<div class="wrap">';
        echo '<h1>Maintenance Mode (Apache .htaccess)</h1>';

        if ($updated !== null) {
            $class = $updated === '1' ? 'notice notice-success' : 'notice notice-error';
            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($msg) . '</p></div>';
        }

        if (!$can_manage) {
            echo '<div class="notice notice-error"><p><strong>.htaccess is not writable</strong>. This plugin cannot apply rules until file permissions allow it.</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="wpmm_apply">';
        wp_nonce_field('wpmm_apply');

        echo '<table class="form-table" role="presentation">';

        echo '<tr><th scope="row">Enable maintenance</th><td>';
        echo '<label><input type="checkbox" name="wpmm_enabled" ' . checked($enabled, true, false) . '> Return 503 to visitors</label>';
        echo '<p class="description">When enabled, rules are written into <code>.htaccess</code> (above the WordPress block) and removed when disabled.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Bypass via logged-in cookie</th><td>';
        echo '<label><input type="checkbox" name="wpmm_cookie_bypass" ' . checked($cookie_bypass, true, false) . '> Allow admins with bypass cookie</label>';
        echo '<p class="description">Admins receive a secure bypass cookie when maintenance mode is enabled. Useful when IPs change or the site is behind a proxy/CDN.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Allowlist IPs</th><td>';
        echo '<textarea name="wpmm_allow_ips" rows="6" cols="60" class="large-text code" placeholder="One per line. Supports IPv4, IPv6, IPv6 prefix (::), and limited CIDR like /32 /24 /16 /8 and IPv6 /64 /128.">' . esc_textarea($allow_ips) . '</textarea>';
        echo '<p class="description">Example: <code>203.0.113.42</code> or <code>2401:db00:abcd:1234::</code> or <code>2401:db00:abcd:1234::/64</code></p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Custom 503 page</th><td>';
        echo '<label><input type="checkbox" name="wpmm_use_custom_503" ' . checked($use_custom, true, false) . '> Use a static file for the 503 page</label>';
        echo '<p><input type="text" name="wpmm_custom_503_path" value="' . esc_attr($custom_path) . '" class="regular-text code"> <span class="description">Path from site root, e.g. <code>/maintenance/index.html</code></span></p>';
        echo '<p class="description">If disabled, Apache uses <code>/index.php</code> as the ErrorDocument (WordPress default behaviour).</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Allow XML-RPC</th><td>';
        echo '<label><input type="checkbox" name="wpmm_allow_xmlrpc" ' . checked($allow_xmlrpc, true, false) . '> Allow <code>/xmlrpc.php</code> during maintenance</label>';
        echo '<p class="description">Most sites should leave this off unless you actively use XML-RPC.</p>';
        echo '</td></tr>';

        echo '</table>';

        submit_button('Apply to .htaccess', 'primary', 'submit', false, ['disabled' => !$can_manage]);
        echo ' <a class="button" href="' . esc_url(admin_url('options-general.php?page=wpmm-maintenance')) . '">Refresh</a>';

        echo '</form>';

        echo '<hr>';
        echo '<h2>Emergency controls</h2>';
        echo '<p><strong>Use only if something has gone wrong.</strong> This forcibly removes the maintenance rules from <code>.htaccess</code>.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:1rem;">';
        echo '<input type="hidden" name="action" value="wpmm_emergency_disable">';
        wp_nonce_field('wpmm_emergency_disable');
        submit_button('Emergency Disable Maintenance', 'delete');
        echo '</form>';

        echo '<hr>';
        echo '<h2>Current generated rules</h2>';
        echo '<p class="description">This is what will be inserted between markers when enabled.</p>';
        echo '<pre class="code" style="white-space:pre-wrap; max-width: 100%;">' . esc_html(WPMM_Htaccess::build_block()) . '</pre>';

        echo '</div>';
    }

    public function handle_emergency_disable() {
        if (!current_user_can('manage_options')) {
            wp_die('Nope.');
        }

        check_admin_referer('wpmm_emergency_disable');

        // Force-disable regardless of saved options
        update_option('wpmm_enabled', false);
        $result = WPMM_Htaccess::sync(false);

        $redirect = add_query_arg([
            'page' => 'wpmm-maintenance',
            'wpmm_updated' => $result['ok'] ? '1' : '0',
            'wpmm_msg' => rawurlencode(
                $result['ok']
                    ? 'Emergency disable: maintenance rules forcibly removed.'
                    : 'Emergency disable failed: ' . $result['message']
            ),
        ], admin_url('options-general.php'));

        wp_safe_redirect($redirect);
        exit;
    }
}
