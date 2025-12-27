# wp-maintenance-manager
Safely toggles server-level maintenance mode via .htaccess with admin bypass

This is Apache-only. On nginx-only hosting, .htaccess does nothing.

If the site is behind Cloudflare/proxy, IP allowlists can be misleading because REMOTE_ADDR becomes the proxy. The cookie bypass is why you won’t get locked out.

The rule set explicitly allows:
- /wp-admin
- /wp-login.php
- /wp-json
- /wp-cron.php
- /wp-admin/admin-ajax.php
- loopback (127.0.0.1, ::1)
- real files (assets)

That covers the “don’t break internal services” requirement.
