<?php
defined('ABSPATH') || exit;

/** Stable release updates for this public repository; no credentials needed. */
final class TMS_GitHub_Updates {
    const REPO = 'diegojover/tms-shiptimize-fix';
    const SLUG = 'tms-shiptimize-fix';

    public static function parse_release($release) {
        if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease'])) { return false; }
        $tag = $release['tag_name'] ?? '';
        if (!is_string($tag) || !preg_match('/^v([0-9]+\.[0-9]+\.[0-9]+)$/D', $tag, $match)) { return false; }
        $package = 'https://github.com/' . self::REPO . '/releases/download/' . $tag . '/' . self::SLUG . '.zip';
        foreach (($release['assets'] ?? array()) as $asset) {
            if (is_array($asset) && ($asset['name'] ?? '') === self::SLUG . '.zip'
                && ($asset['browser_download_url'] ?? '') === $package
                && ($asset['state'] ?? '') === 'uploaded' && ($asset['size'] ?? 0) > 0) {
                return array(
                    'id' => 'https://github.com/' . self::REPO,
                    'slug' => self::SLUG, 'version' => $match[1],
                    'url' => 'https://github.com/' . self::REPO . '/releases/tag/' . $tag,
                    'package' => $package, 'requires_php' => '8.1', 'requires' => '6.5',
                    'autoupdate' => false,
                );
            }
        }
        return false;
    }

    public static function check($update, $plugin_data, $plugin_file, $locales) {
        if ($plugin_file !== self::SLUG . '/' . self::SLUG . '.php') { return $update; }
        $key = 'tms_github_release_v1';
        $cached = get_site_transient($key);
        if (is_array($cached) && array_key_exists('release', $cached)) { return $cached['release']; }
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPO . '/releases/latest', array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'),
        ));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_site_transient($key, array('release' => false), 15 * MINUTE_IN_SECONDS);
            return false;
        }
        $release = self::parse_release(json_decode(wp_remote_retrieve_body($response), true));
        set_site_transient($key, array('release' => $release), $release ? 6 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS);
        return $release;
    }
}

add_filter('update_plugins_github.com', array('TMS_GitHub_Updates', 'check'), 10, 4);
