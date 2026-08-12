<?php
/**
 * Read-only problem-set endpoint for the PUBLIC version.
 *
 * Deliberately stateless: it never starts a session and never looks at saved
 * user data, so /public/ always serves the shared master problem set — even to
 * someone who happens to be logged into /brightspace/ in the same browser.
 * (The session-aware endpoint that saves progress is ../problem_set.php.)
 */
$id = $_GET['id'] ?? 7;

// Only ever read problem_sets/problem_<id>.json; basename() keeps a crafted id
// from escaping the directory.
$name = basename("problem_$id.json");
$file = dirname(__DIR__) . "/problem_sets/$name";

if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'problem set not found']);
    exit;
}

header('Content-Type: application/json');
readfile($file);
