# WP Maintenance Manager

Apache-level maintenance mode for WordPress with true HTTP 503 responses.

This plugin manages maintenance mode at the **Apache `.htaccess` level**, not in PHP, ensuring:
- Real HTTP 503 responses
- No dependency on WordPress loading
- Secure admin bypass via token cookie
- Clean rule insertion and removal

## Features
- Apache rewrite-based maintenance mode
- Optional custom 503 page
- Secure admin bypass cookie
- IPv4 / IPv6 allowlisting
- PHP 7.4 compatible
- No reliance on headers or auth cookies

## Requirements
- Apache 2.4+ (uses `RewriteCond -ipmatch` for IP allowlists)
- WordPress 6.0+
- PHP 7.4+

## FAQ
**What version of Apache is required?**  
Apache 2.4+ (uses `RewriteCond -ipmatch` for IP allowlists).

## Installation
See `readme.txt` or the WordPress.org plugin page for full installation instructions.

## License
GPLv2 or later
