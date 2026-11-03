<?php
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
function add_filter(...$args) {}
function get_site_transient($key) { return $GLOBALS['cache'] ?? false; }
function set_site_transient($key, $value, $ttl) { $GLOBALS['cache'] = $value; }
function wp_remote_get($url, $args) { ++$GLOBALS['requests']; return $GLOBALS['response']; }
function is_wp_error($value) { return $value instanceof Exception; }
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
require __DIR__ . '/../tms-shiptimize-fix/includes/github-updates.php';
$checks = 0;
function check($actual, $expected, $label) {
    global $checks; ++$checks;
    if ($actual !== $expected) { throw new RuntimeException($label); }
}
$release = array('tag_name' => 'v1.2.0', 'draft'=>false, 'prerelease'=>false, 'assets'=>array(array(
    'name'=>'tms-shiptimize-fix.zip', 'state'=>'uploaded', 'size'=>500000,
    'browser_download_url'=>'https://github.com/diegojover/tms-shiptimize-fix/releases/download/v1.2.0/tms-shiptimize-fix.zip',
)));
check(TMS_GitHub_Updates::parse_release($release)['version'], '1.2.0', 'Stable release accepted');
foreach (array('draft','prerelease') as $flag) {
    $bad = $release; $bad[$flag] = true;
    check(TMS_GitHub_Updates::parse_release($bad), false, 'Ignore ' . $flag);
}
$bad = $release; $bad['tag_name'] = 'v1.2.0-beta';
check(TMS_GitHub_Updates::parse_release($bad), false, 'Ignore beta tag');
$bad = $release; $bad['assets'][0]['browser_download_url'] = 'https://example.com/plugin.zip';
check(TMS_GitHub_Updates::parse_release($bad), false, 'Reject different host');
$bad = $release; $bad['assets'][0]['browser_download_url'] = str_replace('diegojover', 'someone-else', $bad['assets'][0]['browser_download_url']);
check(TMS_GitHub_Updates::parse_release($bad), false, 'Reject different owner');
$bad = $release; $bad['assets'] = array();
check(TMS_GitHub_Updates::parse_release($bad), false, 'Ignore release without installable asset');
check(TMS_GitHub_Updates::parse_release(null), false, 'Bad JSON ignored');
check(TMS_GitHub_Updates::check('existing', array(), 'another/plugin.php', array()), 'existing', 'Other plugins unaffected');
$GLOBALS['requests'] = 0; $GLOBALS['response'] = array('status'=>200,'body'=>json_encode($release));
$result = TMS_GitHub_Updates::check(false, array(), 'tms-shiptimize-fix/tms-shiptimize-fix.php', array());
check($result['version'], '1.2.0', 'HTTP response parsed');
TMS_GitHub_Updates::check(false, array(), 'tms-shiptimize-fix/tms-shiptimize-fix.php', array());
check($GLOBALS['requests'], 1, 'Cached response');
$GLOBALS['cache'] = false; $GLOBALS['response'] = array('status'=>403,'body'=>'rate limited');
check(TMS_GitHub_Updates::check(false,array(),'tms-shiptimize-fix/tms-shiptimize-fix.php',array()),false,'HTTP failure safe');
echo "PASS: $checks updater assertions\n";
