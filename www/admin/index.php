<?php
/**
 * ADMIN version of Grammar Golf — https://<host>/admin/
 *
 * Course-authoring tools. Requires a Shibboleth login AND membership in the
 * admin roster (GRAMMARGOLF_ADMINS in the project's root .env). Everyone else
 * gets 403 — including authenticated students.
 */
require_once dirname(__DIR__, 2) . '/lib/gg_auth.php';
gg_require_admin();

$netid = htmlspecialchars(gg_netid(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Grammar Golf — Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        body { font-family: system-ui, sans-serif; max-width: 680px; margin: 40px auto; padding: 0 16px; color: #222; }
        h1 { margin-bottom: 4px; }
        p.sub { color: #666; margin-top: 0; }
        ul { line-height: 1.9; }
        a { color: #900; font-weight: 600; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Grammar Golf — Admin</h1>
    <p class="sub">Signed in as <strong><?= $netid ?></strong></p>
    <ul>
        <li><a href="courseEdit.php">Course Editor</a> — edit problem sets (expressions, notes, title)</li>
    </ul>
    <p class="sub">
        Play the game: <a href="/public/">public version</a> ·
        <a href="/brightspace/">Brightspace version</a>
    </p>
</body>
</html>
