<?php
/**
 * DDEV-only stand-in for the production "/shib/" login-trigger app (not part
 * of this repo). sourceFiles/index.php redirects here with ?shibtarget=<url>
 * when no Shibboleth cn is present; forward into the real login flow against
 * the bundled test IdP, then return to the requested page.
 */
$target = $_GET['shibtarget'] ?? '/';
$host = $_SERVER['HTTP_HOST'];
if (!is_string($target) || $target === '') {
    $target = '/';
}
if (preg_match('#^https?://#i', $target)) {
    $t = parse_url($target);
    $hostNoPort = preg_replace('/:\d+$/', '', $host);
    if (($t['host'] ?? '') !== $hostNoPort) {
        $target = '/';
    }
} elseif ($target[0] !== '/') {
    $target = '/';
}
$abs = preg_match('#^https?://#i', $target) ? $target : 'https://' . $host . $target;
header('Location: /Shibboleth.sso/Login?target=' . rawurlencode($abs));
http_response_code(302);
