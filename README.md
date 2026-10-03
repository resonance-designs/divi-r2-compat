# Divi R2 Compatibility

A reusable WordPress plugin that maps CDN media URLs to their original uploads URLs only during Divi attachment ID lookups. Supports regular-plugin and must-use installation.

## Settings

Open **Settings → Divi R2 Compatibility**. The plugin is disabled by default, including when upgrading the initial unpublished, hardcoded version.

1. Review the detected mapping.
2. Leave both overrides blank for automatic detection, or enter full uploads directory URLs including their paths.
3. Enable compatibility and save. Check the saved mapping preview.
4. Verify a known attachment and a product gallery on the frontend.

Automatic detection reads the original uploads base URL from `wp_upload_dir(null, false)` and the CDN root from Codirun's `codir2me_cdn_url` option. The CDN root is combined with the path of the original uploads URL, matching the inspected Codirun image handler. Custom directories and subdirectory sites are supported. Blank fields remain automatic, so later configuration changes are picked up. No credentials are accessed.

If another plugin already filters WordPress's uploads base URL to the CDN, enter an explicit original uploads URL. If your provider uses a different media path layout, enter both overrides. This plugin does not infer historical GUID domains by scanning attachments. Unknown CDN integrations require a manual CDN uploads URL. Settings are per site, not network-wide.

For Longhair Records, the intended mapping is:

- CDN uploads: `https://cdn.longhairrecords.com/wp-content/uploads/`
- Original uploads: `https://longhairrecords.com/wp-content/uploads/`

These are examples only, not hardcoded defaults.

The mapping matches the exact CDN hostname and port and the uploads directory boundary. HTTP, HTTPS and protocol-relative input URLs are supported. Relative image paths, query strings and fragments are retained. Other URLs pass through. Stored GUIDs and rendered image URLs are not modified. No Redis settings or database indexes are managed.

## Installation

Regular plugin: place this directory in `wp-content/plugins/`, activate it, then configure the settings page. For a ZIP upload, package the directory with the main PHP file at its root.

Must-use plugin: copy only `divi-r2-compat.php` directly into `wp-content/mu-plugins/`. The settings page is still available. Must-use plugins load automatically, but the mapping remains disabled until enabled in settings. Do not install both copies.

No server deployment has been performed from this repository.

## Verification

```sh
php -l divi-r2-compat.php
php tests/lookup-url-test.php
```

The standalone checks use WordPress boundary stubs; they do not replace a live WordPress/Divi integration test. Saving uses the WordPress Settings API, including its capability and nonce checks. The page also checks `manage_options`, escapes displayed values, and retains previous settings when validation fails.

After configuring Longhair Records on the server:

```sh
sudo -u www-data -- wp \
  --path=/var/www/html/longhairrecords \
  --skip-plugins=all-in-one-event-calendar eval '
$url = "https://cdn.longhairrecords.com/wp-content/uploads/2025/09/original-734-300x300.jpeg";
echo "Attachment ID: " . et_get_attachment_id_by_url($url) . PHP_EOL;
'
```

Expected: `Attachment ID: 214199`. The calendar plugin is skipped only for this CLI diagnostic because it interfered with earlier checks. This can populate Divi's file cache. Verify real product images and galleries with all plugins enabled too; CLI and frontend filters may differ. Observe CPU and uptime alerts under normal traffic. This plugin is a compatibility fix, not proof that every outage was caused by this query.

The separately installed `idx_post_type_guid (post_type, guid)` index reduced the incident query's estimated examined rows from 41,185 to 4. This plugin does not install or remove it. No reboot or cache flush is required.

## Rollback

Uncheck Enable compatibility and save. Alternatively deactivate the regular plugin, or rename the must-use PHP file to end in `.php.disabled`. Settings remain in the `divi_r2_compat` option for reinstallation. The database index remains unchanged.

## Detection references

- [WordPress Settings API](https://developer.wordpress.org/plugins/settings/settings-api/)
- [Codirun configuration source](https://plugins.svn.wordpress.org/codirun-codir2me-cdn/trunk/includes/class-codir2me-static-media-cdn.php)
- [Codirun image path construction](https://plugins.svn.wordpress.org/codirun-codir2me-cdn/trunk/includes/class-codir2me-images-handler.php)

The public Codirun source was inspected during implementation; installed versions may differ. Manual overrides provide a fallback.
