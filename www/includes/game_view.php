<?php
/**
 * Shared game markup for both playable versions (public/ and brightspace/).
 *
 * The two entry points differ only in how they authenticate, so the UI itself
 * lives here once instead of being duplicated. All asset paths are absolute so
 * the same markup works from any subdirectory.
 *
 * Optional: $gg_head_extra / $gg_body_extra let an entry point inject markup
 * (brightspace uses it for the LTI grade-passback bootstrap).
 */
$gg_head_extra = $gg_head_extra ?? '';
$gg_body_extra = $gg_body_extra ?? '';
// Which problem-set endpoint this version talks to (see scripts/JSON_API.js).
$gg_api = $gg_api ?? '/problem_set.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/images/favicon.png" />
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <title>Grammar Golf</title>
    <script>window.GG_API = <?= json_encode($gg_api) ?>;</script>
    <script src="/scripts/parse.js"></script>
    <script src="/scripts/bracketToString.js"></script>
    <script src="/scripts/JSON_API.js"></script>
    <script src="/scripts/resize.js"></script>
    <script src="/scripts/jsonToDom.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <script src="https://cdn.jsdelivr.net/npm/intro.js@8.0.0-beta.1/intro.min.js"></script>
    <link rel="stylesheet" href="/styles/style.css">
    <link rel="stylesheet" href="/styles/default_introjs.css">
    <link rel="stylesheet" href="/styles/introjs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dragula/3.7.3/dragula.css"
        integrity="sha512-gGkweS4I+MDqo1tLZtHl3Nu3PGY7TU8ldedRnu60fY6etWjQ/twRHRG2J92oDj7GDU2XvX8k6G5mbp0yCoyXCA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
<?= $gg_head_extra ?>
    <script src="/scripts/golf.js"></script>
</head>

<body>

    <div id="stage">
        <div id="sentenceContainer">
            <svg id="lineContainer">

            </svg>
            <div id="problemConstituent" class="block">

            </div>
        </div>
        <div id="menu">

        </div>

    </div>
<?= $gg_body_extra ?>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/dragula/3.6.6/dragula.js'></script>
    <script>dragula([document.getElementById("right")], {
            copy: true, copySortSource: true
        });</script>
</body>

</html>
