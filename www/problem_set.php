<?php
session_start();
#include("auth.php");
$user="dummyUser";
$id=$_GET['id'];
$file="./problem_sets/problem_$id.json";
$mode= $_GET['mode'] ?? "Guest";
// Default so the guest path's file_exists($idFile) has a defined value
// (guests have no session, so $idFile is otherwise never set).
$idFile="";
// $file=file_get_contents($fileName);
// $fileJSON=json_decode($file);

if(isset($_SESSION['mail'])){
    list($user,$other)=explode("@",$_SESSION['mail']);
    $idFile=  "../data/$user/$id.json";   
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
