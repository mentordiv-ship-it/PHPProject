<?php
// This is an alternative connection method for XAMPP that uses mysqli instead of PDO
// You can test this file to see if it works better with your XAMPP setup

// Database credentials
$host = 'localhost'; 
$dbname = 'ksv_ticket_system';
$username = 'root';
$password = '';

echo "<h1>Alternative Connection Test</h1>";

// First, test connecting to MySQL server without specifying database
echo "<h2>Test 1: Basic MySQL Server Connection</h2>";
try {
    $mysqli = new mysqli($host, $username, $password);
    
    if ($mysqli->connect_error) {
        echo "<p style='color:red'>Failed to connect to MySQL: " . $mysqli->connect_error . "</p>";
    } else {
        echo "<p style='color:green'>Successfully connected to MySQL server!</p>";
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Exception: " . $e->getMessage() . "</p>";
}

// Second, test if database exists
echo "<h2>Test 2: Check If Database Exists</h2>";
try {
    $mysqli = new mysqli($host, $username, $password);
    
    if ($mysqli->connect_error) {
        echo "<p style='color:red'>Failed to connect to MySQL: " . $mysqli->connect_error . "</p>";
    } else {
        $result = $mysqli->query("SHOW DATABASES LIKE '$dbname'");
        
        if ($result->num_rows > 0) {
            echo "<p style='color:green'>Database '$dbname' exists!</p>";
        } else {
            echo "<p style='color:red'>Database '$dbname' does NOT exist!</p>";
            echo "<p>You need to create this database. Please follow these steps:</p>";
            echo "<ol>";
            echo "<li>Open phpMyAdmin (from XAMPP Control Panel)</li>";
            echo "<li>Click 'New' in the left sidebar</li>";
            echo "<li>Enter database name: $dbname</li>";
            echo "<li>Select collation: utf8mb4_unicode_ci</li>";
            echo "<li>Click 'Create'</li>";
            echo "<li>After creating the database, import the sql/mysql_database.sql file</li>";
            echo "</ol>";
        }
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Exception: " . $e->getMessage() . "</p>";
}

// Third, try to connect to the specific database
echo "<h2>Test 3: Connect to Specific Database</h2>";
try {
    $mysqli = new mysqli($host, $username, $password, $dbname);
    
    if ($mysqli->connect_error) {
        echo "<p style='color:red'>Failed to connect to database: " . $mysqli->connect_error . "</p>";
    } else {
        echo "<p style='color:green'>Successfully connected to '$dbname' database!</p>";
        
        // Test a basic query
        $result = $mysqli->query("SHOW TABLES");
        echo "<p>Tables in the database:</p>";
        echo "<ul>";
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_array()) {
                echo "<li>" . $row[0] . "</li>";
            }
        } else {
            echo "<li>No tables found. You need to import the schema.</li>";
        }
        echo "</ul>";
        
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Exception: " . $e->getMessage() . "</p>";
}

// Display some common XAMPP issues and solutions
echo "<h2>Common XAMPP MySQL Connection Issues:</h2>";
echo "<ul>";
echo "<li><strong>MySQL not running</strong> - Check XAMPP Control Panel to ensure MySQL service is started</li>";
echo "<li><strong>Wrong username/password</strong> - Default is username: 'root' with empty password</li>";
echo "<li><strong>Missing database</strong> - Create the database using phpMyAdmin</li>";
echo "<li><strong>Missing tables</strong> - Import the SQL file to create the schema</li>";
echo "<li><strong>Wrong port</strong> - Default MySQL port is 3306</li>";
echo "<li><strong>PHP extensions</strong> - Make sure mysqli and pdo_mysql extensions are enabled in php.ini</li>";
echo "</ul>";

?>