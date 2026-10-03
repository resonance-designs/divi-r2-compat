<?php
// WordPress boundary stubs: tests run without loading a site or database.
define('ABSPATH', __DIR__);
$options = array();
$uploads = 'https://shop.example/store/media';
$hooks = array();
$errors = array();
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function wp_upload_dir($time = null, $create = true) { global $uploads; return array('baseurl' => $uploads); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function esc_url_raw($url, $protocols = array()) { return filter_var($url, FILTER_VALIDATE_URL) ? $url : ''; }
function add_filter($hook, $callback) { global $hooks; $hooks[$hook] = $callback; }
function add_action($hook, $callback) { add_filter($hook, $callback); }
function add_settings_error($option, $code, $message) { global $errors; $errors[] = $code; }
$can_manage = false;
function current_user_can($capability) { global $can_manage; return $can_manage && $capability === 'manage_options'; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_attr($value); }
function admin_url($path) { return 'https://shop.example/wp-admin/' . $path; }
function checked($value) { if ($value) { echo 'checked="checked"'; } }
function settings_errors($option) {}
function settings_fields($option) { echo '<input name="_wpnonce" value="test-nonce">'; }
function submit_button() { echo '<button>Save Changes</button>'; }
function register_setting($group, $option, $args) { global $registration; $registration = array($group, $option, $args); }
function add_options_page($title, $menu, $cap, $slug, $callback) { global $menu_cap; $menu_cap = $cap; }
require dirname(__DIR__) . '/divi-r2-compat.php';
$count = 0;
function same($expected, $actual, $label) {
    global $count;
    ++$count;
    if ($expected !== $actual) { fwrite(STDERR, "FAIL: $label\n" . var_export($actual, true) . "\n"); exit(1); }
}
function rewrite($url) { global $hooks; return call_user_func($hooks['et_get_attachment_id_by_url_guid'], $url); }
$cdn = 'https://cdn.example/store/media/2025/photo.jpg';
same($cdn, rewrite($cdn), 'disabled until configured');
$options['codir2me_cdn_url'] = 'https://cdn.example';
$options[Divi_R2_Compat::OPTION] = array('enabled' => true);
same('https://shop.example/store/media/2025/photo.jpg', rewrite($cdn), 'Codirun and WordPress autodetection');
same('https://shop.example/store/media/2025/photo.jpg', rewrite(str_replace('https:', 'http:', $cdn)), 'HTTP alias');
same('https://shop.example/store/media/2025/photo.jpg', rewrite(str_replace('https:', '', $cdn)), 'protocol-relative alias');
same('https://shop.example/store/media/2025/photo.jpg', rewrite(str_replace('cdn.example', 'CDN.EXAMPLE', $cdn)), 'host case');
same('https://shop.example/store/media/2025/photo.jpg?v=2#image', rewrite($cdn . '?v=2#image'), 'suffix preservation');
foreach (array(null, false, '', 'https://cdn.example.evil/store/media/a.jpg', 'https://cdn.example/store/media-other/a.jpg', 'https://cdn.example:8443/store/media/a.jpg', 'https://user@cdn.example/store/media/a.jpg', 'ftp://cdn.example/store/media/a.jpg', 'https://cdn.example/store/media/../a.jpg', 'https://cdn.example/store/media/%2e%2e/a.jpg') as $url) {
    same($url, rewrite($url), 'unrelated or unsafe input unchanged');
}
$options[Divi_R2_Compat::OPTION] = array('enabled' => true, 'cdn' => 'https://assets.example/bucket/pictures/', 'origin' => 'https://old.example/custom/');
same('https://old.example/custom/a.jpg', rewrite('https://assets.example/bucket/pictures/a.jpg'), 'manual different paths');
$old = Divi_R2_Compat::settings();
foreach (array('javascript:alert(1)', 'https://user:secret@cdn.example/media/', 'https://cdn.example/media/?token=x', 'https://cdn.example/media/#a', array('bad'), 'https://cdn.example/../media/') as $invalid) {
    same($old, Divi_R2_Compat::sanitize(array('enabled' => 1, 'cdn' => $invalid)), 'invalid settings preserve previous configuration');
}
same(array('enabled' => false, 'cdn' => '', 'origin' => ''), Divi_R2_Compat::sanitize(array()), 'disable and reset overrides');
same(array('enabled' => true, 'cdn' => 'https://cdn.example/media/', 'origin' => 'https://shop.example/files/'), Divi_R2_Compat::sanitize(array('enabled' => 1, 'cdn' => 'https://cdn.example/media', 'origin' => 'https://shop.example/files')), 'normalize saved URLs');
same($old, Divi_R2_Compat::sanitize(array('enabled' => 1, 'cdn' => 'https://same.example/', 'origin' => 'https://same.example/')), 'reject identical mapping');
unset($options['codir2me_cdn_url']);
$options[Divi_R2_Compat::OPTION] = array('enabled' => true);
same($cdn, rewrite($cdn), 'missing autodetection passes through');
$options['codir2me_cdn_url'] = 'https://cdn.example/prefix/';
same('https://shop.example/store/media/a.jpg', rewrite('https://cdn.example/prefix/store/media/a.jpg'), 'Codirun root with prefix');
$uploads = 'https://second.example/wp-content/uploads/sites/2/';
same('https://second.example/wp-content/uploads/sites/2/a.jpg', rewrite('https://cdn.example/prefix/wp-content/uploads/sites/2/a.jpg'), 'uploads changes not cached across sites');
ob_start();
Divi_R2_Compat::page();
same('', ob_get_clean(), 'settings page denied without capability');
Divi_R2_Compat::register();
same(array('Divi_R2_Compat', 'sanitize'), $registration[2]['sanitize_callback'], 'Settings API validation callback');
Divi_R2_Compat::menu();
same('manage_options', $menu_cap, 'admin menu capability');
$can_manage = true;
$options[Divi_R2_Compat::OPTION] = array('enabled' => false, 'cdn' => '\"><script>alert(1)</script>', 'origin' => '');
ob_start();
Divi_R2_Compat::page();
$html = ob_get_clean();
same(false, strpos($html, '<script>'), 'saved values escaped in admin output');
same(true, strpos($html, 'options.php') !== false && strpos($html, '_wpnonce') !== false, 'Settings API form destination and nonce helper');
same(true, strpos($html, 'Original uploads URL override') !== false && strpos($html, 'Current saved mapping') !== false, 'configuration and preview rendered');
echo "$count checks passed.\n";
