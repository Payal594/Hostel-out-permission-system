<?php
$servername = "sql101.infinityfree.com";
$username = "if0_40950643";
$password = "lDudOcmFtJOr";
$dbname = "if0_40950643_hostel";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>