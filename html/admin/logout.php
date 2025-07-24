<?php
include_once '../config/database.php';
include_once 'Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->logout();
?>