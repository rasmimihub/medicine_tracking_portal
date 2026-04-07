<?php
// config.php
// START SESSION: Required for tracking whether an admin or supervisor is currently logged in
session_start();

// DATABASE CREDENTIALS: Set up the local connection to MySQL
$db_host = 'localhost';
$db_name = 'medicine_tracking';
$db_user = 'root';
$db_pass = ''; // Default XAMPP has no password

try {
    // CONNECT TO DATABASE: Using PDO (PHP Data Objects) for security against SQL injection attacks
    // We bind variables to statements instead of inserting them directly into SQL strings.
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    
    // Set the PDO error mode to exception so we can catch and handle errors cleanly
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    // Stop the whole application if the database is unreachable
    die("ERROR: Could not connect to the database. " . $e->getMessage());
}

// TRANSACTION LOGGER FUNCTION (AUDIT TRAIL): 
// This crucial function is called whenever stock physically changes (Addition, Reduction, Creation, etc.)
// It inserts a permanent, unalterable row into the 'transactions' audit log table.
function logTransaction($pdo, $medicine_id, $type, $quantity, $user_id) {
    // Prepare statement prevents SQL injection
    $stmt = $pdo->prepare("INSERT INTO transactions (medicine_id, type, quantity, user_id) VALUES (?, ?, ?, ?)");
    // Execute the query using the supplied parameters
    $stmt->execute([$medicine_id, $type, $quantity, $user_id]);
}
?>
