=== WP Maintenance Manager ===
Contributors: hamishwright
Tags: maintenance mode, htaccess, apache, 503, admin bypass
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Apache-level maintenance mode with true HTTP 503 responses, secure admin bypass, and no reliance on PHP execution.

== Description ==

WP Maintenance Manager provides a predictable, infrastructure-level maintenance mode for WordPress sites running on Apache.

Instead of intercepting requests in PHP, this plugin writes controlled rules directly to `.htaccess` (above the WordPress rewrite block), ensuring:

- True HTTP 503 responses
- No dependency on WordPress loading
- No reliance on WordPress auth cookies or request headers
- Clean enable/disable with guaranteed rule removal

It is designed for administrators who want reliability, security, and transparency — not visual splash screens or JavaScript hacks.

== Features ==

* True HTTP 503 responses using Apache rewrite rules
* Optional custom 503 ErrorDocument
* Secure admin bypass using a random token cookie (issued on enable)
* IPv4 and IPv6 allowlisting (including /24, /16, /64 where appropriate)
* Optional XML-RPC access during maintenance
* PHP 7.4 compatible (no PHP 8-only functions)
* No reliance on WordPress auth cookies or IP headers
* Clean removal of all rules on disable or plugin deactivation

== How It Works ==

When maintenance mode is enabled, the plugin inserts a clearly marked block into `.htaccess`.

Requests are evaluated in this order:
1. Loopback and internal requests
2. Real files (assets)
3. WordPress internal endpoints
4. Admin bypass cookie (if enabled)
5. IP allowlist (if configured)
6. All other traffic receives a 503 response

Disabling maintenance removes the block entirely.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/wp-maintenance-manager/`
2. Activate the plugin through the WordPress Plugins menu
3. Go to **Settings → Maintenance (htaccess)**
4. Configure options and apply rules

The plugin requires write access to `.htaccess`.

== Frequently Asked Questions ==

= Does this work with Nginx? =
No. This plugin is Apache-only by design.

= Will this break my site if WordPress is down? =
No. That is the point. The rules execute before WordPress loads.

= Is the admin bypass secure? =
Yes. It uses a randomly generated token stored server-side and issued as a secure, HttpOnly cookie.

= Does this rely on X-Forwarded-For or Cloudflare headers? =
No. Header trust is intentionally avoided.

= What happens on deactivation? =
All maintenance rules are forcibly removed from `.htaccess`.

== Changelog ==

= 0.3.0 =
* Initial public release
* Secure admin bypass via token cookie
* PHP 7.4 compatibility
* Hardened rewrite rules
* Clean enable/disable behaviour

== Upgrade Notice ==

= 0.3.0 =
Initial public release.
