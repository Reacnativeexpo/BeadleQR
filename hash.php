<?php
$password = "admin123";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "Plain Password: " . $password . "<br>";
echo "Hashed Password: " . $hashedPassword;
?>