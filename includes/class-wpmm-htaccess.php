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

        // If it doesn't exist, we can create it if the directory is writable.
        if (!file_exists($path)) {
            return is_writable(ABSPATH);
        }

        return is_readable($path) && is_writable($path);
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

        if ($enabled) {
            $block = self::build_block();
            // Prepend our block so it runs before WP rewrites
            $contents = $block . "\n" . ltrim($contents);
        }

        // Atomic-ish write: write to temp then rename
        $tmp = $path . '.wpmm.tmp';
        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            @unlink($tmp);
            return ['ok' => false, 'message' => 'Failed to write temp .htaccess file.'];
        }

        if (!@rename($tmp, $path)) {
            // Fallback for filesystems that dislike rename
            $written = file_put_contents($path, $contents, LOCK_EX);
            @unlink($tmp);
            if ($written === false) {
                return ['ok' => false, 'message' => 'Failed to write .htaccess file.'];
            }
        }

        return ['ok' => true, 'message' => $enabled ? 'Maintenance rules added to .htaccess.' : 'Maintenance rules removed from .htaccess.'];
    }

    public static function remove_block(string $contents): string {
        $pattern = '/' . preg_quote(self::MARKER_START, '/') . '.*?' . preg_quote(self::MARKER_END, '/') . '\s*/s';
        return preg_replace($pattern, '', $contents) ?? $contents;
    }

    public static function build_block(): string {
        $rules = WPMM_Rules::generate();
        return self::MARKER_START . "\n" . $rules . "\n" . self::MARKER_END;
    }
}