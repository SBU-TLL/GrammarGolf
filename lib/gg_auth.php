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

/** Current user's netID, or null when nobody is authenticated. */
function gg_netid(): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Shibboleth: cn is the netID. LTI/session fallback: derive from mail.
    if (!empty($_SERVER['cn'])) {
        return $_SERVER['cn'];
    }
    if (!empty($_SESSION['cn'])) {
        return $_SESSION['cn'];
    }
    foreach ([$_SERVER['mail'] ?? null, $_SESSION['mail'] ?? null] as $mail) {
        if (!empty($mail)) {
            return explode('@', $mail)[0];
        }
    }
    return null;
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
        $target = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /shib/?shibtarget=' . rawurlencode($target));
        exit;
    }

    if (!gg_is_admin()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden - '$netid' is not a GrammarGolf administrator.\n";
        echo "Add the netID to GRAMMARGOLF_ADMINS in the project's root .env file.\n";
        exit;
    }
}
