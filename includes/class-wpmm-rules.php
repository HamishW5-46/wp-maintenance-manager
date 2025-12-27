<?php
defined('ABSPATH') || exit;

class WPMM_Rules {

    /**
     * Generate Apache rewrite rules for maintenance mode.
     * Marker wrapper is handled by WPMM_Htaccess.
     */
    public static function generate(): string {
        $custom_503_path = trim((string) get_option('wpmm_custom_503_path', ''));
        $use_custom_503  = (bool) get_option('wpmm_use_custom_503', false);

        // Allowlist IPs (one per line)
        $raw_ips = (string) get_option('wpmm_allow_ips', '');
        $allow_ip_regexes = self::build_ip_allow_regexes($raw_ips);

        // Cookie bypass is always on by default, can be toggled
        $cookie_bypass = (bool) get_option('wpmm_cookie_bypass', true);

        // Maintenance assets path (when custom page enabled)
        $maintenance_dir = self::normalize_path_prefix(dirname($custom_503_path ?: '/maintenance/index.html'));

        // Default ErrorDocument: WordPress front controller
        $error_document = '/index.php';
        if ($use_custom_503) {
            // Must be a site-root absolute path for Apache ErrorDocument
            $error_document = self::normalize_path($custom_503_path);
        }

        // We use 503 response + ErrorDocument so it returns true 503 and displays desired page
        // IMPORTANT: we whitelist internal WP routes so admin/editor/cron doesn’t brick itself.
        $lines = [];

        $lines[] = "RewriteEngine On";

        // --- Allow loopback (internal services, health checks, cron, etc.) ---
        $lines[] = "";
        $lines[] = "# Allow loopback (internal requests)";
        $lines[] = "RewriteCond %{REMOTE_ADDR} ^127\\.0\\.0\\.1$";
        $lines[] = "RewriteRule ^ - [L]";
        $lines[] = "RewriteCond %{REMOTE_ADDR} ^::1$";
        $lines[] = "RewriteRule ^ - [L]";

        // --- Allow real files (assets) ---
        // This avoids images/css/js being replaced by the 503 ErrorDocument HTML.
        $lines[] = "";
        $lines[] = "# Allow real files (assets)";
        $lines[] = "RewriteCond %{REQUEST_FILENAME} -f";
        $lines[] = "RewriteRule ^ - [L]";

        // --- Allow custom maintenance assets directory ---
        // Only needed if you use a custom maintenance page that references /maintenance/* assets.
        if ($use_custom_503 && $maintenance_dir !== '/') {
            $lines[] = "";
            $lines[] = "# Allow maintenance page and its assets";
            $lines[] = "RewriteCond %{REQUEST_URI} ^" . self::escape_for_regex($maintenance_dir) . "/";
            $lines[] = "RewriteRule ^ - [L]";
        }

        // --- Allow internal WP endpoints that should not be blocked ---
        $lines[] = "";
        $lines[] = "# Allow WordPress internal endpoints";
        $lines[] = "RewriteCond %{REQUEST_URI} ^/wp-admin";
        $lines[] = "RewriteRule ^ - [L]";
        $lines[] = "RewriteCond %{REQUEST_URI} ^/wp-login\\.php";
        $lines[] = "RewriteRule ^ - [L]";
        $lines[] = "RewriteCond %{REQUEST_URI} ^/wp-json";
        $lines[] = "RewriteRule ^ - [L]";
        $lines[] = "RewriteCond %{REQUEST_URI} ^/wp-cron\\.php$";
        $lines[] = "RewriteRule ^ - [L]";
        $lines[] = "RewriteCond %{REQUEST_URI} ^/wp-admin/admin-ajax\\.php$";
        $lines[] = "RewriteRule ^ - [L]";

        // Optional: if you use XML-RPC, keep it reachable (many sites block it anyway).
        $allow_xmlrpc = (bool) get_option('wpmm_allow_xmlrpc', false);
        if ($allow_xmlrpc) {
            $lines[] = "RewriteCond %{REQUEST_URI} ^/xmlrpc\\.php$";
            $lines[] = "RewriteRule ^ - [L]";
        }

        // --- Cookie bypass ---
        // Dedicated bypass cookie for admins.
        if ($cookie_bypass) {
            $token = (string) get_option('wpmm_bypass_token', '');
            $lines[] = "";
            if ($token !== '') {
                $lines[] = "# Allow admins with bypass token cookie";
                $lines[] = 'RewriteCond %{HTTP_COOKIE} (^|;\s*)wpmm_bypass=' . $token . '(;|$)';
                $lines[] = "RewriteRule ^ - [L]";
            }
        }

        // --- IP allowlist (optional) ---
        if (!empty($allow_ip_regexes)) {
            $lines[] = "";
            $lines[] = "# Allow listed IPs";
            foreach ($allow_ip_regexes as $regex) {
                // If any matches -> allow
                $lines[] = "RewriteCond %{REMOTE_ADDR} " . $regex;
                $lines[] = "RewriteRule ^ - [L]";
            }
        }

        // --- Maintenance response + custom ErrorDocument ---
        $lines[] = "";
        $lines[] = "# Everyone else gets a 503";
        $lines[] = "RewriteRule ^ - [R=503,L]";
        $lines[] = "ErrorDocument 503 " . $error_document;

        return implode("\n", $lines);
    }

    private static function normalize_path(string $path): string {
        $path = trim($path);
        if ($path === '') return '/index.php';
        if ($path[0] !== '/') $path = '/' . $path;
        return $path;
    }

    private static function normalize_path_prefix(string $path): string {
        $path = self::normalize_path($path);
        // dirname('/maintenance/index.html') returns '/maintenance'
        return rtrim($path, '/');
    }

    private static function escape_for_regex(string $path): string {
        // Escape slashes and dots for Apache regex contexts
        return str_replace(['.', '/'], ['\\.', '\\/'], $path);
    }

    /**
     * Build safe regexes for Apache RewriteCond %{REMOTE_ADDR}
     * Supports:
     * - IPv4 exact
     * - IPv6 exact
     * - IPv6 prefix using CIDR /64-/128 (we’ll turn into regex)
     * - plain IPv6 prefix ending with :: (treated as prefix)
     */
    private static function build_ip_allow_regexes(string $raw): array {
        $lines = preg_split('/\R/', $raw) ?: [];
        $regexes = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;

            // CIDR?
            if (strpos($line, '/') !== false) {
                $regex = self::cidr_to_regex($line);
                if ($regex) $regexes[] = $regex;
                continue;
            }

            // IPv4 exact
            if (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $regexes[] = '^' . preg_quote($line, '/') . '$';
                continue;
            }

            // IPv6 exact
            if (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $regexes[] = '^' . preg_quote($line, '/') . '$';
                continue;
            }

            // IPv6 prefix like 2401:db00:abcd:1234::
            if (strpos($line, ':') !== false && (substr($line, -2) === '::' || substr($line, -1) === ':')) {
                $prefix = rtrim($line, ':');
                $regexes[] = '^' . preg_quote($prefix, '/') . '.*$';
                continue;
            }

            // Last resort: allow user-provided regex if it looks regexy (dangerous)
            // We DO NOT accept raw regex here for safety. Ignore invalid lines.
        }

        // Apache RewriteCond uses regex without delimiters
        // Ensure they're not empty
        return array_values(array_filter($regexes));
    }

    /**
     * Very lightweight CIDR to regex for common cases:
     * - IPv4 /32 -> exact
     * - IPv4 /24, /16, /8 (basic)
     * - IPv6 /64 (common prefix), /128 exact
     *
     * For anything exotic: user should use cookie bypass.
     */
    private static function cidr_to_regex(string $cidr): ?string {
        $cidr = trim($cidr);

        [$ip, $mask] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($ip === null || $mask === null) return null;

        $mask = (int) $mask;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Basic IPv4 masks only
            if ($mask === 32) return '^' . preg_quote($ip, '/') . '$';

            $octets = explode('.', $ip);
            if (count($octets) !== 4) return null;

            if ($mask === 24) return '^' . preg_quote($octets[0] . '.' . $octets[1] . '.' . $octets[2] . '.', '/') . '.*$';
            if ($mask === 16) return '^' . preg_quote($octets[0] . '.' . $octets[1] . '.', '/') . '.*$';
            if ($mask === 8)  return '^' . preg_quote($octets[0] . '.', '/') . '.*$';

            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($mask === 128) return '^' . preg_quote($ip, '/') . '$';
            // Treat /64 as prefix match on first 4 hextets (good enough)
            if ($mask === 64) {
                $expanded = self::expand_ipv6($ip);
                if (!$expanded) return null;
                $hextets = explode(':', $expanded);
                if (count($hextets) !== 8) return null;
                $prefix = implode(':', array_slice($hextets, 0, 4));
                return '^' . preg_quote($prefix, '/') . '.*$';
            }
            return null;
        }

        return null;
    }

    private static function expand_ipv6(string $ip): ?string {
        $packed = @inet_pton($ip);
        if ($packed === false) return null;
        $hex = bin2hex($packed);
        $parts = str_split($hex, 4);
        return implode(':', $parts);
    }
}