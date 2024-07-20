<?php
require_once 'password.php';
die($_POST['password'] === $developer_password ? 'OK' : 'Wrong password')
?>