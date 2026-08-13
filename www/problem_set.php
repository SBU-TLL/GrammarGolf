<?php
// Shared problem-set endpoint for all three versions (public / brightspace /
// admin). Reads are open; per-user saves need a session; writing the MASTER
// problem set ("admin" mode) additionally requires admin membership.
require_once dirname(__DIR__) . '/lib/gg_auth.php';
session_start();
$user="dummyUser";
// $id is interpolated into two file paths below and arrives straight from the
// query string, so confine it to characters that cannot escape the directory.
$id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['id'] ?? ''));
$file=__DIR__ . "/problem_sets/problem_$id.json";

// SECURITY: mode=admin arrives from the query string, i.e. from the client, and
// it decides whether a POST overwrites the shared master problem set. Honour it
// only for real admins (roster in the root .env); for anyone else fall back to
// the normal per-user mode, so a student cannot rewrite course content.
$mode = ($_GET['mode'] ?? "Guest") === "admin" && gg_is_admin() ? "admin" : "Guest";
// Default so the guest path's file_exists($idFile) has a defined value
// (guests have no session, so $idFile is otherwise never set).
$idFile="";
// $file=file_get_contents($fileName);
// $fileJSON=json_decode($file);

// Identity may arrive as any of several Shibboleth attributes or from an LTI
// launch; gg_netid() normalises them. Keyed on mail alone this went guest-mode
// (and silently never saved) whenever the IdP released a netID but no mail.
// Sanitised because on an LTI launch it derives from a POSTed email address,
// i.e. from outside — and it names a directory.
$netid = gg_netid();
if ($netid !== null) {
    $user = preg_replace('/[^A-Za-z0-9_.-]/', '', $netid);
    if ($user !== '' && $user[0] !== '.' && !str_contains($user, '..')) {
        // Per-user saves live in data/ at the repo root, outside the web root.
        $idFile = dirname(__DIR__) . "/data/$user/$id.json";
    }
}


/** Path relative to the app root, so errors are actionable without leaking layout. */
function gg_relpath(string $path): string
{
    $root = dirname(__DIR__) . '/';
    return str_starts_with($path, $root) ? substr($path, strlen($root)) : basename($path);
}

/** Report a save that did not happen. Silence here looks exactly like success. */
function gg_save_failed(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

if(isset($_POST["json"])){

    // Every failure below used to be silent: the response echoed the submitted
    // JSON back whatever happened, so the browser — and the user — saw a
    // successful save even when nothing reached the disk.

    if (($_GET['mode'] ?? '') === "admin" && $mode !== "admin") {
        gg_save_failed(403,
            "Not saved. '" . (gg_netid() ?? 'nobody') . "' is not a GrammarGolf administrator,\n"
          . "so the shared problem set was left unchanged.\n"
          . "Add the netID to GRAMMARGOLF_ADMINS in the project's root .env file.\n");
    }
    if ($idFile === "") {
        gg_save_failed(403,
            "Not saved. This request carries no identity, so there is no user to save for.\n");
    }

    // Ensure the per-user data directory exists BEFORE writing. Previously the
    // mkdir ran AFTER the file_put_contents, so a new user's first save wrote
    // into a non-existent directory (silently failed) until the dir happened to
    // exist on a later save.
    $idDir = dirname($idFile);
    if (!is_dir($idDir) && !@mkdir($idDir, 0755, true) && !is_dir($idDir)) {
        gg_save_failed(500,
            "Not saved. The server could not create:\n  " . gg_relpath($idDir) . "\n\n"
          . "Its parent directory must be writable by the web server user.\n");
    }

    // Admin mode also overwrites the shared master copy inside the web root.
    $targets = $mode == "admin" ? [$idFile, $file] : [$idFile];
    $failed = [];
    foreach ($targets as $path) {
        if (@file_put_contents($path, $_POST["json"]) === false) {
            $failed[] = gg_relpath($path);
        }
    }
    if ($failed) {
        gg_save_failed(500,
            "Not saved. The server could not write:\n  " . implode("\n  ", $failed) . "\n\n"
          . "These paths must be writable by the web server user.\n");
    }

   print($_POST["json"]);
}
else{

  if($idFile !== "" && file_exists($idFile) && $mode!="admin"){
    $file=$idFile;
  }

  // Ids travel in URLs (/public/?problem_id=N) and the numbering has gaps, so a
  // wrong one is easy to land on. Say so plainly. This used to return HTTP 200
  // with a PHP warning quoting the absolute server path, which the client could
  // not parse — the game just broke.
  if(!file_exists($file)){
      http_response_code(404);
      header('Content-Type: text/plain; charset=utf-8');
      exit("No problem set with id '$id'.\n");
  }

    $json=file_get_contents($file);

    print($json);

}
