<?php
require_once 'config/db.php';
logout_user();
header('Location: login.php');
exit;
