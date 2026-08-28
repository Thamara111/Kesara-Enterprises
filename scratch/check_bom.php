<?php
$files = ['auth.php', 'database/connection.php', 'my_account.php', 'layouts/head.php'];
foreach ($files as $f) {
    $c = file_get_contents(__DIR__ . '/../' . $f);
    if (substr($c, 0, 3) === "\xEF\xBB\xBF") {
        echo "$f HAS UTF-8 BOM!\n";
    } else {
        echo "$f is clean (no BOM)\n";
    }
}
