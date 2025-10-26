<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Clear user session
clearUserSession();

// Redirect to login page
$_SESSION['success'] = "You have been successfully logged out.";
header("Location: login.php");
exit;
