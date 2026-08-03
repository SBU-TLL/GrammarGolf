<?php
// Shared problem-set endpoint for all three versions (public / brightspace /
// admin). Reads are open; per-user saves need a session; writing the MASTER
// problem set ("admin" mode) additionally requires admin membership.
require_once dirname(__DIR__) . '/lib/gg_auth.php';
session_start();
$user="dummyUser";
$id=$_GET['id'];
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

if(isset($_SESSION['mail'])){
    list($user,$other)=explode("@",$_SESSION['mail']);
    // Per-user saves live in data/ at the repo root, outside the web root.
    $idFile= dirname(__DIR__) . "/data/$user/$id.json";
}

  
if(isset($_POST["json"]) && isset($_SESSION['mail']) ){

    // Ensure the per-user data directory exists BEFORE writing. Previously the
    // mkdir ran AFTER the file_put_contents, so a new user's first save wrote
    // into a non-existent directory (silently failed) until the dir happened to
    // exist on a later save.
    $idDir = dirname($idFile);
    if(!is_dir($idDir)){
        mkdir($idDir, 0755, true);
    }

    if ($mode=="admin") {
     file_put_contents($idFile,$_POST["json"]);
     file_put_contents($file,$_POST["json"]);
    } else {
      file_put_contents($idFile,$_POST["json"]);
    }

   print($_POST["json"]);
}
else{
  
  if(file_exists($idFile) && $mode!="admin"){
    $file=$idFile;
  }

    $json=file_get_contents($file);
    
    print($json);

}
