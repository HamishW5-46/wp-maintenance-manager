<?php
defined('ABSPATH') || exit;

class WPMM_Htaccess {

    const MARKER_START = '# BEGIN WPMM_MAINTENANCE';
    const MARKER_END   = '# END WPMM_MAINTENANCE';

    public static function htaccess_path(): string {
        return ABSPATH . '.htaccess';
    }

    public static function can_manage_htaccess(): bool {
        $path = self::htaccess_path();

        // If it doesn't exist, allow the write attempt.
        if (!file_exists($path)) {
            return true;
        }

        // If the file exists but is not readable, we cannot manage it safely.
        if (!is_readable($path)) {
            return false;
        }

        // File exists and is readable; allow write attempt.
        return true;
    }

    /**
     * Sync .htaccess with current settings.
     * @param bool|null $force_enabled if null, use saved option; otherwise force.
     * @return array{ok:bool,message:string}
     */
    public static function sync(?bool $force_enabled = null): array {
        // Absolute emergency kill-switch:
        // If this file exists, maintenance is forcibly disabled.
        if (file_exists(WP_CONTENT_DIR . '/wpmm-disable')) {
            $force_enabled = false;
        }

        $enabled = $force_enabled;
        if ($enabled === null) {
            $enabled = (bool) get_option('wpmm_enabled', false);
        }

        if (!self::can_manage_htaccess()) {
            return [
                'ok' => false,
                'message' => '.htaccess is not writable (or cannot be created). Check file permissions / ownership.',
            ];
        }

        $path = self::htaccess_path();
        $contents = file_exists($path) ? file_get_contents($path) : '';
        if ($contents === false) {
            return ['ok' => false, 'message' => 'Failed to read .htaccess.'];
        }

        // Always remove existing block first
        $contents = self::remove_block($contents);

        $filesystem = self::get_filesystem();

        if ($enabled) {
            $block = self::build_block();
            // Prepend our block so it runs before WP rewrites
            $contents = $block . "\n" . ltrim($contents);
        }

        // Atomic-ish write: write to temp then rename
        $tmp = $path . '.wpmm.tmp';
        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            self::delete_file($tmp);
            return ['ok' => false, 'message' => 'Failed to write temp .htaccess file.'];
        }

        $moved = false;
        if ($filesystem && method_exists($filesystem, 'move')) {
            $moved = $filesystem->move($tmp, $path, true);
        }

        if (!$moved) {
            // Fallback for filesystems that dislike move
            $written = file_put_contents($path, $contents, LOCK_EX);
            self::delete_file($tmp);
            if ($written === false) {
                return ['ok' => false, 'message' => 'Failed to write .htaccess file.'];
            }
        }

        return ['ok' => true, 'message' => $enabled ? 'Maintenance rules added to .htaccess.' : 'Maintenance rules removed from .htaccess.'];
    }

    public static function remove_block(string $contents): string {
        $patterns = [
            '/' . preg_quote(self::MARKER_START, '/') . '.*?' . preg_quote(self::MARKER_END, '/') . '\s*/s',
        ];
        foreach ($patterns as $pattern) {
            $contents = preg_replace($pattern, '', $contents) ?? $contents;
        }
        return $contents;
    }

    public static function build_block(): string {
        $rules = WPMM_Rules::generate();
        return self::MARKER_START . "\n" . $rules . "\n" . self::MARKER_END;
    }

    protected static function get_filesystem() {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            $filesystem_file = ABSPATH . 'wp-admin/includes/file.php';
            if (!is_readable($filesystem_file)) {
                return null;
            }
            require_once $filesystem_file;
        }

        if (!function_exists('WP_Filesystem')) {
            return null;
        }

        if (!$wp_filesystem) {
            $initialized = WP_Filesystem();
            if ($initialized === false) {
                return null;
            }
        }

        return is_object($wp_filesystem) ? $wp_filesystem : null;
    }

    private static function delete_file(string $path): void {
        $filesystem = self::get_filesystem();

        if ($filesystem && method_exists($filesystem, 'delete')) {
            $filesystem->delete($path);
            return;
        }

        if (function_exists('wp_delete_file')) {
            wp_delete_file($path);
            return;
        }
    }
}
