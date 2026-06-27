<?php
require_once 'includes/auth.php';
auth_start();
header('Location: ' . (is_logged_in() ? 'dashboard.php' : 'login.php'));
exit;
