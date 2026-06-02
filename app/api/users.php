<?php
header('Content-Type: application/json');
$users = json_decode(file_get_contents('../../data/users.json'), true);
echo json_encode([
    "status" => "success",
    "project" => "geminy.me",
    "total_users" => count($users),
    "api_version" => "1.0"
]);
