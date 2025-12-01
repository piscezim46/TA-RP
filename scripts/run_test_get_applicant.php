<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
$_GET['applicant_id'] = 1;
ob_start();
include __DIR__ . '/../public/get_applicant.php';
$s = ob_get_clean();
// print first 2000 chars to keep output small
echo substr($s, 0, 2000);
