<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Test authentication with the superadmin credentials
$username = "superadmin";
$password = "admin123";

echo "Testing authentication for user: $username\n";

$user = fetchOne("SELECT * FROM users WHERE username = ?", [$username]);

if ($user) {
    echo "User found in database.\n";
    echo "User ID: " . $user['id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Stored password hash: " . $user['password'] . "\n\n";
    
    echo "Testing password_verify function:\n";
    $result = password_verify($password, $user['password']);
    echo "Password verification result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n\n";
    
    // Let's create a new hash and compare
    echo "Creating a new hash for the same password:\n";
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    echo "New hash: $newHash\n";
    echo "Verification with new hash: " . (password_verify($password, $newHash) ? 'SUCCESS' : 'FAILED') . "\n\n";
    
    // Generate a correct hash for the password and update the database
    echo "Updating the database with a new hash for this password...\n";
    $correctHash = password_hash($password, PASSWORD_DEFAULT);
    update('users', ['password' => $correctHash], 'id', $user['id']);
    echo "Database updated. Please try logging in again with the same credentials.\n";
} else {
    echo "User not found in database.\n";
}
?>