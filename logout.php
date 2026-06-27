<?php
require_once 'includes/auth.php';
auth_start();
session_destroy();
session_start();
flash('info','Berhasil logout.');
header('Location: login.php');
exit;
