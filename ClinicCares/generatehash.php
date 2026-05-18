<?php
echo "<pre>";

$password = "password";
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "PASSWORD: " . $password . PHP_EOL;
echo "HASH: " . $hash . PHP_EOL;

echo "</pre>";