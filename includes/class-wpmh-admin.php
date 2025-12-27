<?php
defined('ABSPATH') || exit;

class WPMH_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_wpmh_apply', [$this, 'handle_apply']);
    }

    public function menu() {
        add_options_page(
            'Maintenance (htaccess)',
            'Maintenance (htaccess)',
            'manage_options',
            'wpmh-maintenance',
            [$this, 'page']
        );
    }

    public function register_settings() {
        register_setting('wpmh', 'wpmh_enabled', ['type' => 'boolean', 'default' => false]);
        register_setting('wpmh', 'wpmh_cookie_bypass', ['type' => 'boolean', 'default' => true]);
        register_setting('wpmh', 'wpmh_allow_ips', ['type' => 'string', 'default' => '']);
        register_setting('wpmh', 'wpmh_use_custom_503', ['type' => 'boolean', 'default' => false]);
        register_setting('wpmh', 'wpmh_custom_503_path', ['type' => 'string', 'default' => '/maintenance/index.html']);
        register_setting('wpmh', 'wpmh_allow_xmlrpc', ['type' => 'boolean', 'default' => false});
    }

    public function handle_apply() {
        if (!current_user_can('manage_options')) {
            wp_die('Nope.');
        }
        check_admin_referer('wpmh_apply');

        $enabled = isset($_POST['wpmh_enabled']) ? (bool) $_POST['wpmh_enabled'] : false;
        update_option('wpmh_enabled', $enabled);

        update_option('wpmh_cookie_bypass', isset($_POST['wpmh_cookie_bypass']));
        update_option('wpmh_allow_xmlrpc', isset($_POST['wpmh_allow_xmlrpc']));

        $ips = isset($_POST['wpmh_allow_ips']) ? (string) $_POST['wpmh_allow_ips'] : '';
        update_option('wpmh_allow_ips', $ips);

        $use_custom = isset($_POST['wpmh_use_custom_503']);
        update_option('wpmh_use_custom_503', $use_custom);

        $custom_path = isset($_POST['wpmh_custom_503_path']) ? trim((string) $_POST['wpmh_custom_503_path']) : '';
        update_option('wpmh_custom_503_path', $custom_path);

        $result = WPMH_Htaccess::sync($enabled);

        $redirect = add_query_arg([
            'page' => 'wpmh-maintenance',
            'wpmh_updated' => $result['ok'] ? '1' : '0',
            'wpmh_msg' => rawurlencode($result['message']),
        ], admin_url('options-general.php'));

        wp_safe_redirect($redirect);
        exit;
    }

    public function page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $enabled       = (bool) get_option('wpmh_enabled', false);
        $cookie_bypass = (bool) get_option('wpmh_cookie_bypass', true);
        $allow_ips     = (string) get_option('wpmh_allow_ips', '');
        $use_custom    = (bool) get_option('wpmh_use_custom_503', false);
        $custom_path   = (string) get_option('wpmh_custom_503_path', '/maintenance/index.html');
        $allow_xmlrpc  = (bool) get_option('wpmh_allow_xmlrpc', false);

        $can_manage = WPMH_Htaccess::can_manage_htaccess();

        $updated = isset($_GET['wpmh_updated']) ? (string) $_GET['wpmh_updated'] : null;
        $msg = isset($_GET['wpmh_msg']) ? rawurldecode((string) $_GET['wpmh_msg']) : '';

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
        echo '<input type="hidden" name="action" value="wpmh_apply">';
        wp_nonce_field('wpmh_apply');

        echo '<table class="form-table" role="presentation">';

        echo '<tr><th scope="row">Enable maintenance</th><td>';
        echo '<label><input type="checkbox" name="wpmh_enabled" ' . checked($enabled, true, false) . '> Return 503 to visitors</label>';
        echo '<p class="description">When enabled, rules are written into <code>.htaccess</code> (above the WordPress block) and removed when disabled.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Bypass via logged-in cookie</th><td>';
        echo '<label><input type="checkbox" name="wpmh_cookie_bypass" ' . checked($cookie_bypass, true, false) . '> Allow any logged-in session (wordpress_logged_in_)</label>';
        echo '<p class="description">Recommended if you use Cloudflare/proxies or your IP changes often.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Allowlist IPs</th><td>';
        echo '<textarea name="wpmh_allow_ips" rows="6" cols="60" class="large-text code" placeholder="One per line. Supports IPv4, IPv6, IPv6 prefix (::), and limited CIDR like /32 /24 /16 /8 and IPv6 /64 /128.">' . esc_textarea($allow_ips) . '</textarea>';
        echo '<p class="description">Example: <code>203.0.113.42</code> or <code>2401:db00:abcd:1234::</code> or <code>2401:db00:abcd:1234::/64</code></p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Custom 503 page</th><td>';
        echo '<label><input type="checkbox" name="wpmh_use_custom_503" ' . checked($use_custom, true, false) . '> Use a static file for the 503 page</label>';
        echo '<p><input type="text" name="wpmh_custom_503_path" value="' . esc_attr($custom_path) . '" class="regular-text code"> <span class="description">Path from site root, e.g. <code>/maintenance/index.html</code></span></p>';
        echo '<p class="description">If disabled, Apache uses <code>/index.php</code> as the ErrorDocument (WordPress default behaviour).</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Allow XML-RPC</th><td>';
        echo '<label><input type="checkbox" name="wpmh_allow_xmlrpc" ' . checked($allow_xmlrpc, true, false) . '> Allow <code>/xmlrpc.php</code> during maintenance</label>';
        echo '<p class="description">Most sites should leave this off unless you actively use XML-RPC.</p>';
        echo '</td></tr>';

        echo '</table>';

        submit_button('Apply to .htaccess', 'primary', 'submit', false, ['disabled' => !$can_manage]);
        echo ' <a class="button" href="' . esc_url(admin_url('options-general.php?page=wpmh-maintenance')) . '">Refresh</a>';

        echo '</form>';

        echo '<hr>';
        echo '<h2>Current generated rules</h2>';
        echo '<p class="description">This is what will be inserted between markers when enabled.</p>';
        echo '<pre class="code" style="white-space:pre-wrap; max-width: 100%;">' . esc_html(WPMH_Htaccess::build_block()) . '</pre>';

        echo '</div>';
    }
}