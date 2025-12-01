<?php
$f = $argv[1] ?? __DIR__ . '/../public/view_applicants.php';
$lines = @file($f);
if ($lines === false) { echo "Could not read file: $f\n"; exit(2); }
foreach ($lines as $i => $l) {
    printf('%4d: %s', $i+1, rtrim($l, "\r\n").PHP_EOL);
}
