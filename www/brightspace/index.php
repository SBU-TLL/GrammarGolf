<?php
/**
 * BRIGHTSPACE (authenticated) version of Grammar Golf — https://<host>/brightspace/
 *
 * Reached either as an LTI launch from Brightspace (the LMS POSTs lis_person_*)
 * or directly in a browser with a Shibboleth session. Progress is saved per
 * user and, for LTI launches, the score is posted back to the LMS.
 *
 * Anonymous browsers are sent to the Shibboleth login. LTI launch POSTs carry
 * their own identity, so they are handled first and never redirected.
 */
session_start();

$gg_head_extra = '';
$gg_body_extra = '';

if (array_key_exists('lis_person_name_given', $_POST)) {
    // --- LTI launch from the LMS -------------------------------------------
    $_SESSION['mail']      = $_POST['lis_person_contact_email_primary'] ?? null;
    $_SESSION['givenName'] = $_POST['lis_person_name_given'];
    $_SESSION['nickname']  = $_POST['lis_person_name_given'];
    $_SESSION['sn']        = $_POST['lis_person_name_family'] ?? null;

    // Hand the launch payload to grading.js so it can post the grade back.
    $JSON_POST = json_encode($_POST);
    $gg_head_extra = <<<EOT
    <script src="/grading.js"></script>
    <script>var ses = $JSON_POST;</script>

EOT;
} elseif (isset($_SERVER['sn'])) {
    // --- Shibboleth session (mod_shib populated $_SERVER) -------------------
    $_SESSION['mail']      = $_SERVER['mail'] ?? null;
    $_SESSION['givenName'] = $_SERVER['givenName'] ?? null;
    $_SESSION['nickname']  = $_SERVER['nickname'] ?? null;
    $_SESSION['sn']        = $_SERVER['sn'];
} elseif (!isset($_SERVER['cn'])) {
    // --- No identity at all: send the browser to log in ---------------------
    $target = 'https://' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: /shib/?shibtarget=' . rawurlencode($target));
    exit;
}

require __DIR__ . '/../includes/game_view.php';
