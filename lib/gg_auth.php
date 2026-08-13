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
 * Stop, instead of bouncing between the app and the login service forever.
 *
 * Reached only when the browser has already been through login and come back
 * with no usable identity. The user gets a short message; the detail that
 * actually diagnoses it -- which Shibboleth variables arrived, names only,
 * never values -- goes to the server error log, where it belongs.
 */
function gg_auth_dead_end(): void
{
    $found = [];
    foreach (array_merge(GG_SHIB_MARKERS, GG_SHIB_NETID_ATTRS, GG_SHIB_OTHER_ATTRS) as $attr) {
        foreach (gg_env_keys($attr) as $key) {
            if (!empty($_SERVER[$key])) {
                $found[] = $key;
            }
        }
        // Headers are reported but never trusted (see gg_env_keys). Naming them
        // matters: environment empty + headers full means the SP is running
        // with ShibUseHeaders On, so the server config needs changing, not this.
        $header = 'HTTP_' . strtoupper(str_replace('-', '_', $attr));
        if (!empty($_SERVER[$header])) {
            $found[] = $header;
        }
    }

    error_log(sprintf(
        'GrammarGolf: login produced no identity for %s. Shibboleth variables present: %s. '
        . 'None at all means this vhost exports no Shibboleth session (needs AuthType shibboleth '
        . '+ ShibRequestSetting requireSession false); HTTP_-prefixed only means ShibUseHeaders is on.',
        $_SERVER['REQUEST_URI'] ?? '?',
        $found ? implode(', ', $found) : '(none)'
    ));

    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Sign-in did not complete.\n\n"
       . "You signed in successfully, but this application did not receive your\n"
       . "identity, so it has stopped rather than sending you round again.\n\n"
       . "Please report this to tll@stonybrook.edu -- the details are in the\n"
       . "server error log.\n");
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
