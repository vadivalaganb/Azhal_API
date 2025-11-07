<?php
// ----------------- Disable Caching -----------------
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Database credentials
$host = 'localhost';
$user = 'superadmin';
$password = 'azhal_it_solutions@$321';
$dbname = 'azhal_it_solutions';

// $host = "localhost";
// $user = "root";
// $password = "";    
// $dbname = "azhal_db";

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
