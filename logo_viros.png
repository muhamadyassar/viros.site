<?php
require_once 'includes/auth.php';
auth_start();
if (is_logged_in()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
