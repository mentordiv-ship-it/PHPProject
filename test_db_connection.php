<?php
// Database credentials - same as your config
$host = 'localhost';
$dbname = 'ksv_ticket_system';
$username = 'root';
$password = '';
$port = '3306';

// Test connection
try {
    // Try MySQL connection
    $mysql_dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($mysql_dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<h2 style='color:green'>MySQL Connection Success!</h2>";
    echo "<p>Successfully connected to MySQL database: <strong>$dbname</strong></p>";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT 'Connection Test' AS test_value");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Test query result: " . $row['test_value'] . "</p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color:red'>MySQL Connection Failed!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    
    // Additional diagnostic information
    echo "<h3>Troubleshooting:</h3>";
    echo "<ol>";
    echo "<li>Verify the MySQL service is running in XAMPP</li>";
    echo "<li>Check if the database '$dbname' exists</li>";
    echo "<li>Verify username and password credentials</li>";
    echo "<li>Check if MySQL is listening on the default port 3306</li>";
    echo "</ol>";
}
?>