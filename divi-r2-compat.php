<?php
/**
 * Plugin Name: Divi R2 Compatibility
 * Description: Configurable CDN-to-origin URL mapping for Divi attachment lookups.
 * Version: 1.1.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Divi_R2_Compat {
    const OPTION = 'divi_r2_compat';

    public static function settings() {
        $value = get_option(self::OPTION, array());
        return array_merge(array('enabled' => false, 'cdn' => '', 'origin' => ''), is_array($value) ? $value : array());
    }

    // Accept full HTTP(S) directory URLs only. Never use credentials or query strings.
    public static function base_url($value) {
        if (!is_string($value) || preg_match('/[\x00-\x20\x7f\\\\]/', trim($value))) {
            return '';
        }
        $value = trim($value);
        $parts = wp_parse_url($value);
        if (!$parts || empty($parts['host']) || empty($parts['scheme']) ||
            !in_array(strtolower($parts['scheme']), array('http', 'https'), true) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return '';
        }
        $path = $parts['path'] ?? '';
        if (preg_match('~(?:^|/)\.{1,2}(?:/|$)~', rawurldecode($path))) {
            return '';
        }
        $clean = esc_url_raw($value, array('http', 'https'));
        return $clean === '' ? '' : rtrim($clean, '/') . '/';
    }

    public static function mapping($settings = null) {
        $settings = $settings === null ? self::settings() : $settings;
        $uploads = wp_upload_dir(null, false);
        $origin = self::base_url($settings['origin'] !== '' ? $settings['origin'] : ($uploads['baseurl'] ?? ''));
        $cdn = self::base_url($settings['cdn']);
        if ($settings['cdn'] === '') {
            $root = self::base_url(get_option('codir2me_cdn_url', ''));
            $path = $origin ? wp_parse_url($origin, PHP_URL_PATH) : '';
            $cdn = $root && $path ? self::base_url($root . ltrim($path, '/')) : '';
        }
        return array('origin' => $origin, 'cdn' => $cdn);
    }

    public static function rewrite($url) {
        $settings = self::settings();
        if (empty($settings['enabled']) || !is_string($url)) {
            return $url;
        }
        $mapping = self::mapping($settings);
        if (!$mapping['origin'] || !$mapping['cdn']) {
            return $url;
        }
        $source = wp_parse_url($mapping['cdn']);
        $input = wp_parse_url($url);
        if (!$input || empty($input['host']) ||
            (isset($input['scheme']) && !in_array(strtolower($input['scheme']), array('http', 'https'), true)) ||
            isset($input['user']) || isset($input['pass']) ||
            strcasecmp($input['host'], $source['host']) !== 0 ||
            ($input['port'] ?? null) !== ($source['port'] ?? null)) {
            return $url;
        }
        $path = $input['path'] ?? '';
        if (strpos($path, $source['path']) !== 0 ||
            preg_match('~(?:^|/)\.{1,2}(?:/|$)~', rawurldecode($path))) {
            return $url;
        }
        $result = $mapping['origin'] . substr($path, strlen($source['path']));
        if (isset($input['query'])) {
            $result .= '?' . $input['query'];
        }
        if (isset($input['fragment'])) {
            $result .= '#' . $input['fragment'];
        }
        return $result;
    }

    public static function sanitize($input) {
        $old = self::settings();
        if (!is_array($input)) {
            add_settings_error(self::OPTION, 'invalid', 'Settings were not saved: invalid submission.');
            return $old;
        }
        $new = array('enabled' => !empty($input['enabled']), 'cdn' => '', 'origin' => '');
        foreach (array('cdn', 'origin') as $key) {
            $raw = $input[$key] ?? '';
            if (!is_string($raw) || (trim($raw) !== '' && !self::base_url($raw))) {
                add_settings_error(self::OPTION, 'invalid_' . $key, 'Settings were not saved. Enter full HTTP(S) directory URLs without credentials, query strings, or fragments.');
                return $old;
            }
            $new[$key] = trim($raw) === '' ? '' : self::base_url($raw);
        }
        $map = self::mapping($new);
        if ($new['enabled'] && (!$map['cdn'] || !$map['origin'] || $map['cdn'] === $map['origin'])) {
            add_settings_error(self::OPTION, 'mapping', 'Settings were not saved. A distinct CDN uploads URL and original uploads URL are required. Supply overrides if automatic detection is unavailable.');
            return $old;
        }
        return $new;
    }

    public static function register() {
        register_setting(self::OPTION, self::OPTION, array('sanitize_callback' => array(__CLASS__, 'sanitize'), 'type' => 'array'));
    }

    public static function menu() {
        add_options_page('Divi R2 Compatibility', 'Divi R2 Compatibility', 'manage_options', self::OPTION, array(__CLASS__, 'page'));
    }

    public static function page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = self::settings();
        $mapping = self::mapping($settings);
        ?>
        <div class="wrap">
            <h1>Divi R2 Compatibility</h1>
            <p>Help Divi find attachment IDs for images served from a CDN. Public image URLs and stored media records stay unchanged.</p>
            <?php settings_errors(self::OPTION); ?>
            <?php if (!function_exists('et_get_attachment_id_by_url')) : ?>
                <div class="notice notice-warning inline"><p>Divi's attachment lookup is not available in this request. The mapping only runs when Divi invokes its lookup filter.</p></div>
            <?php endif; ?>
            <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
                <?php settings_fields(self::OPTION); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Enable compatibility</th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>> Translate CDN URLs during Divi attachment lookups</label>
                    </td></tr>
                    <?php foreach (array('cdn' => 'CDN uploads URL override', 'origin' => 'Original uploads URL override') as $key => $label) : ?>
                    <tr><th scope="row"><label for="divi-r2-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td>
                        <input class="large-text" type="url" id="divi-r2-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($settings[$key]); ?>" placeholder="<?php echo esc_attr($key === 'cdn' ? 'https://cdn.example.com/wp-content/uploads/' : 'https://example.com/wp-content/uploads/'); ?>" aria-describedby="divi-r2-<?php echo esc_attr($key); ?>-help">
                        <p class="description" id="divi-r2-<?php echo esc_attr($key); ?>-help"><?php echo esc_html($key === 'cdn' ? 'Leave blank to use Codirun’s configured CDN base plus the original uploads URL path. Otherwise enter the full CDN uploads directory URL.' : 'Leave blank to use WordPress’s current uploads base URL, including custom upload paths. Override if another plugin changes it to a CDN URL or your stored GUIDs use a different origin.'); ?></p>
                    </td></tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2>Current saved mapping</h2>
            <p>Status: <strong><?php echo !empty($settings['enabled']) ? 'Enabled' : 'Disabled'; ?></strong></p>
            <p>CDN uploads: <code><?php echo esc_html($mapping['cdn'] ?: 'Not detected — enter an override above.'); ?></code></p>
            <p>Original uploads: <code><?php echo esc_html($mapping['origin'] ?: 'Not detected — enter an override above.'); ?></code></p>
            <?php if ($mapping['cdn'] && $mapping['origin']) : ?>
                <p>Example lookup:</p>
                <p><code><?php echo esc_html($mapping['cdn'] . '2025/09/example.jpg'); ?></code><br>becomes<br><code><?php echo esc_html($mapping['origin'] . '2025/09/example.jpg'); ?></code></p>
            <?php endif; ?>
            <p>Blank overrides are detected at runtime and follow configuration changes. Save to update this preview. Check a known attachment and a product gallery after enabling. This plugin does not require R2 credentials.</p>
        </div>
        <?php
    }
}

add_filter('et_get_attachment_id_by_url_guid', array('Divi_R2_Compat', 'rewrite'));
add_action('admin_init', array('Divi_R2_Compat', 'register'));
add_action('admin_menu', array('Divi_R2_Compat', 'menu'));
