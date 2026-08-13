<?php
/**
 * Shared identity + authorization helpers for GrammarGolf.
 *
 * Lives OUTSIDE the web root (repo root /lib), so it is never web-servable.
 * Identity comes from Shibboleth (mod_shib populates $_SERVER) or from an LTI
 * launch (which auth.php copies into $_SESSION). The admin roster comes from
 * the root .env (GRAMMARGOLF_ADMINS), which is also outside the web root.
 */

require_once dirname(__DIR__) . '/loadEnv.php';

/**
 * Server variables that indicate mod_shib gave this request a session.
 *
 * Which attribute names actually arrive depends on the SP's attribute-map, and
 * that differs between deployments — so recognise the usual spellings of the
 * netID rather than betting the whole login on one of them. Shib-Session-ID is
 * the reliable "a session exists" marker: mod_shib always exports it.
 */
const GG_SHIB_MARKERS = ['Shib-Session-ID', 'Shib-Application-ID', 'Shib-Identity-Provider'];
const GG_SHIB_NETID_ATTRS = ['cn', 'uid', 'REMOTE_USER', 'eppn'];
const GG_SHIB_OTHER_ATTRS = ['mail', 'sn', 'givenName', 'displayName', 'nickname'];

/** Strip the @scope from a scoped value: "abc123@stonybrook.edu" -> "abc123". */
function gg_unscope(string $value): string
{
    return explode('@', $value)[0];
}

/**
 * Every environment spelling of one attribute.
 *
 * Apache re-prefixes environment variables with REDIRECT_ each time a request
 * is re-dispatched internally — and reaching "/brightspace/" serves
 * "/brightspace/index.php" through exactly such a DirectoryIndex hop, so
 * mod_shib's "cn" can arrive as "REDIRECT_cn". Two levels covers a redirect
 * chained after a rewrite.
 *
 * These are set by Apache itself and cannot be injected by a client, so every
 * spelling is equally trustworthy. (Request headers are deliberately NOT read:
 * mod_shib strips client-supplied copies only on requests it handles, so on any
 * path it does not handle a forged "Cn:" header would be believed.)
 */
function gg_env_keys(string $attr): array
{
    return [$attr, 'REDIRECT_' . $attr, 'REDIRECT_REDIRECT_' . $attr];
}

/** One Shibboleth attribute, however this deployment exports it. */
function gg_shib_attr(string $attr): ?string
{
    foreach (gg_env_keys($attr) as $key) {
        if (!empty($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }
    }
    return null;
}

/** True when mod_shib has put a Shibboleth session on this request. */
function gg_shib_session_active(): bool
{
    foreach (array_merge(GG_SHIB_MARKERS, GG_SHIB_NETID_ATTRS, GG_SHIB_OTHER_ATTRS) as $key) {
        if (gg_shib_attr($key) !== null) {
            return true;
        }
    }
    return false;
}

/** Current user's netID, or null when nobody is authenticated. */
function gg_netid(): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Shibboleth: cn is the netID at SBU; accept the other common spellings too.
    foreach (GG_SHIB_NETID_ATTRS as $key) {
        $value = gg_shib_attr($key);
        if ($value !== null) {
            return gg_unscope($value);
        }
    }
    if (!empty($_SESSION['cn'])) {
        return $_SESSION['cn'];
    }
    // LTI/session fallback: derive from mail.
    foreach ([gg_shib_attr('mail'), $_SESSION['mail'] ?? null] as $mail) {
        if (!empty($mail)) {
            return gg_unscope($mail);
        }
    }
    return null;
}

/**
 * Base URL of the Shibboleth handler that starts a login.
 *
 * Default "/Shibboleth.sso" = the SP runs on this host (how DDEV is set up).
 *
 * Production is different: the TLL runs a CENTRAL SP. One entityID
 * (auth.tll.stonybrook.edu) owns the only AssertionConsumerService registered
 * with the university IdP, session cookies are scoped to .tll.stonybrook.edu so
 * every *.tll app shares the session, and each app host is a RequestMap entry
 * rather than an SP in its own right. Logins there must START at the central
 * handler. Asking the local host to start one makes its SP mint an AuthnRequest
 * naming an ACS URL the IdP holds no metadata for, and the IdP rejects it with
 * HTTP 400 ("unable to identify a compatible way to respond").
 *
 * Set SHIB_HANDLER_URL in the root .env to the central handler in production.
 */
function gg_shib_handler(): string
{
    $handler = trim((string) getenv('SHIB_HANDLER_URL'));
    return $handler !== '' ? rtrim($handler, '/') : '/Shibboleth.sso';
}

/**
 * This app's own canonical base URL, e.g. https://grammargolf.tll.stonybrook.edu
 *
 * Prefer GRAMMARGOLF_BASE_URL from .env over the Host header, which the client
 * controls: when the login is handed to a central SP on another host, the
 * post-login target is where that SP sends the browser, so a spoofed Host would
 * otherwise be an open redirect.
 */
function gg_base_url(): string
{
    $base = trim((string) getenv('GRAMMARGOLF_BASE_URL'));
    if ($base !== '') {
        return rtrim($base, '/');
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/**
 * URL that starts a Shibboleth login and comes back to the current page.
 *
 * It used to point at "/shib/", a separate login-trigger app that exists on the
 * old shared web server but is not deployed alongside this one. In production
 * that URL 404s, which made every authenticated version of the game
 * unreachable while /public/ — the one version that never redirects — worked.
 */
function gg_login_url(?string $target = null): string
{
    $target = $target ?? ($_SERVER['REQUEST_URI'] ?? '/');
    // Same-site paths only: reject absolute URLs and protocol-relative "//host".
    if ($target === '' || $target[0] !== '/' || str_starts_with($target, '//')) {
        $target = '/';
    }

    $handler = gg_shib_handler();
    if ($handler[0] !== '/') {
        // Central SP on another host: a relative target would resolve against
        // THAT host, so send it back here explicitly.
        $target = gg_base_url() . $target;
    }
    return $handler . '/Login?target=' . rawurlencode($target);
}

/**
 * Explain why login produced no identity, instead of bouncing forever.
 *
 * Reached only when the browser has already been through the login service and
 * come back with nothing usable. Reports which Shibboleth variables arrived --
 * names only, never values -- because that single fact separates the two causes.
 */
function gg_auth_dead_end(): void
{
    $all = array_merge(GG_SHIB_MARKERS, GG_SHIB_NETID_ATTRS, GG_SHIB_OTHER_ATTRS);

    $env = $redir = $hdr = [];
    foreach ($all as $key) {
        if (!empty($_SERVER[$key])) {
            $env[] = $key;
        }
        foreach (array_slice(gg_env_keys($key), 1) as $prefixed) {
            if (!empty($_SERVER[$prefixed])) {
                $redir[] = $prefixed;
            }
        }
        // Reported but never trusted — see gg_env_keys(). Worth naming, because
        // "environment empty but headers full" means the SP is running with
        // ShibUseHeaders On and the server config needs changing, not the app.
        if (!empty($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))])) {
            $hdr[] = $key;
        }
    }
    // Anything else that smells like Shibboleth, in case the spelling differs.
    $other = [];
    foreach (array_keys($_SERVER) as $key) {
        if (preg_match('/shib|assurance|affiliation|persistent.?id|targeted.?id/i', (string) $key)) {
            $other[] = $key;
        }
    }

    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Signed in, but this application received no identity.\n";
    echo "Stopping here: redirecting again would loop between the app and the "
       . "login service.\n\n";
    echo 'As environment variables : ' . ($env ? implode(', ', $env) : '(none)') . "\n";
    echo 'After internal redirect  : ' . ($redir ? implode(', ', $redir) : '(none)') . "\n";
    echo 'As request headers       : ' . ($hdr ? implode(', ', $hdr) : '(none)') . "\n";
    echo 'Other Shibboleth-ish keys: ' . ($other ? implode(', ', $other) : '(none)') . "\n\n";

    if ($hdr && !$env && !$redir) {
        echo "Shibboleth IS working, but the SP is exporting through request headers\n";
        echo "(ShibUseHeaders On). This app reads environment variables, because a\n";
        echo "header can be forged on any path mod_shib does not filter. Remove\n";
        echo "ShibUseHeaders from the vhost so attributes arrive as env vars.\n";
    } elseif (!$env && !$redir && !$hdr && !$other) {
        echo "Nothing at all arrived, by either route. mod_shib can be installed and\n";
        echo "the SP can be working while THIS virtual host still exports nothing —\n";
        echo "attributes are only populated for requests mod_shib is told to handle.\n\n";
        echo "In a RequestMap setup the host entry needs an authType, e.g.:\n\n";
        echo "    <Host name=\"" . htmlspecialchars((string) ($_SERVER['HTTP_HOST'] ?? 'this-host'), ENT_NOQUOTES) . "\"\n";
        echo "          authType=\"shibboleth\" requireSession=\"false\"/>\n\n";
        echo "or, as Apache directives on the docroot:\n\n";
        echo "    AuthType shibboleth\n";
        echo "    ShibRequestSetting requireSession false\n";
        echo "    require shibboleth\n\n";
        echo "requireSession must stay false so /public/ and LTI launch POSTs still\n";
        echo "reach the app without a login.\n";
    } else {
        echo "A session exists, but none of the attributes carry a netID. The app\n";
        echo 'looks for: ' . implode(', ', GG_SHIB_NETID_ATTRS) . ", then mail.\n";
        echo "Map one of those in the SP's attribute-map.xml, or send this list to\n";
        echo "the maintainer so the app can read what your IdP actually releases.\n";
    }
    exit;
}

/** Send an unauthenticated browser to log in, then return to this page. */
function gg_redirect_to_login(): void
{
    $here = $_SERVER['REQUEST_URI'] ?? '/';

    // Loop breaker. The marker rides along to the login service and comes back;
    // seeing it here means we have already been round once and still have no
    // identity, so another redirect would spin the browser between the app and
    // the IdP showing a blank page and nothing in the console.
    if (isset($_GET['ggauth'])) {
        gg_auth_dead_end();
    }

    $here .= (str_contains($here, '?') ? '&' : '?') . 'ggauth=1';
    header('Location: ' . gg_login_url($here), true, 302);
    exit;
}

/** Admin roster from the root .env: GRAMMARGOLF_ADMINS="netid1,netid2". */
function gg_admins(): array
{
    $raw = getenv('GRAMMARGOLF_ADMINS');
    if ($raw === false || trim($raw) === '') {
        return []; // fail closed: no roster configured => nobody is an admin
    }
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

function gg_is_admin(): bool
{
    $netid = gg_netid();
    return $netid !== null && in_array($netid, gg_admins(), true);
}

/**
 * Gate a page or endpoint on admin membership.
 * Not authenticated -> send to Shibboleth login. Authenticated but not on the
 * roster -> 403. Fail-closed by design: an unset roster denies everyone.
 */
function gg_require_admin(): void
{
    $netid = gg_netid();

    if ($netid === null) {
        gg_redirect_to_login();
    }

    if (!gg_is_admin()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden - '$netid' is not a GrammarGolf administrator.\n";
        echo "Add the netID to GRAMMARGOLF_ADMINS in the project's root .env file.\n";
        exit;
    }
}
