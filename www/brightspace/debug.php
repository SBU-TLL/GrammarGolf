<?php
// debug.php — place inside /brightspace/
session_start();

echo "<h2>Shibboleth & Server Variable Debugger</h2>";

echo "<h3>1. HTTP Headers (via getallheaders)</h3><pre>";
if (function_exists('getallheaders')) {
    print_r(getallheaders());
} else {
    echo "getallheaders() unavailable\n";
}
echo "</pre>";

echo "<h3>2. Shibboleth / HTTP Keys in \$_SERVER</h3><pre>";
$shib_found = false;
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_') || in_array($key, ['REMOTE_USER', 'cn', 'sn', 'mail', 'givenName', 'eppn'])) {
        echo sprintf("%-30s => %s\n", $key, is_array($value) ? implode(',', $value) : $value);
        $shib_found = true;
    }
}
if (!$shib_found) {
    echo "NO SHIBBOLETH ATTRIBUTES DETECTED IN \$_SERVER\n";
}
echo "</pre>";

echo "<h3>3. Full \$_SERVER Dump</h3><pre>";
print_r($_SERVER);
echo "</pre>";