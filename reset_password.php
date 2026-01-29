<?php
include 'db_connect.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $newpass = $_POST['newpass'];
    
    // Check students first
    $check = $conn->query("SELECT * FROM students WHERE email='$email' AND phone='$phone'");
    if($check->num_rows > 0){
        $conn->query("UPDATE students SET password='$newpass' WHERE email='$email'");
        echo "Password Reset! <a href='login.html'>Login</a>";
    } else {
        echo "Details do not match any student.";
    }
}
?>