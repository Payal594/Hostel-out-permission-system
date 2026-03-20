<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'];
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $newpass = $_POST['newpass'];
    
    
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        echo "<script>alert('Error: Phone number must be exactly 10 digits.'); window.history.back();</script>";
        exit();
    }

  
    if ($role == 'student') {
        $table = 'students';
    } elseif ($role == 'warden') {
        $table = 'wardens';
    } elseif ($role == 'teacher') {
        $table = 'teachers';
    } else {
        die("Invalid role selected.");
    }

    
    $checkSql = "SELECT id FROM $table WHERE email = ? AND phone = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ss", $email, $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        
        $updateSql = "UPDATE $table SET password = ? WHERE email = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ss", $newpass, $email);
        
        if ($updateStmt->execute()) {
            echo "
            <div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
                <h2 style='color: green;'>Password Reset Successful!</h2>
                <p>Your password has been updated securely.</p>
                <a href='login.html?role=$role' style='display:inline-block; padding:10px 20px; background:#333; color:white; text-decoration:none; border-radius:5px;'>Click here to Login</a>
            </div>";
        } else {
            echo "<script>alert('System error. Could not update password.'); window.history.back();</script>";
        }
    } else {
       
        echo "<script>alert('Error: The email and phone number do not match any $role account.'); window.history.back();</script>";
    }
}
?>
