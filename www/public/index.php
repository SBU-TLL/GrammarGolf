<?php
/**
 * PUBLIC version of Grammar Golf — https://<host>/public/
 *
 * Open to everyone: no authentication, no session, no grade passback.
 * Players get the shared problem sets; nothing is saved server-side.
 *
 * (The authenticated version lives in ../brightspace/, the course editor in
 * ../admin/. Each has its own entry point so neither has to branch on role.)
 */
// Stateless: talk to this directory's read-only endpoint, so the public
// version always shows the shared problem sets — never a logged-in user's
// saved work, even if a /brightspace/ session cookie is present.
$gg_api = '/public/problem_set.php';

require __DIR__ . '/../includes/game_view.php';
