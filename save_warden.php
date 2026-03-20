<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    include 'db_connect.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        
        $fullname = trim($_POST['fullname']);
        $phone    = trim($_POST['phone']);
        $email    = trim($_POST['email']);
        $gender   = $_POST['gender'];
        $password = $_POST['password'];

      
        $checkEmail = "SELECT email FROM wardens WHERE email = ?";
        $stmt = $conn->prepare($checkEmail);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
           
            echo "
            <div style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px; padding: 30px; border: 1px solid #ddd; border-radius: 10px; display: inline-block; width: 100%; max-width: 450px; position: relative; left: 50%; transform: translateX(-50%); box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>
                <h2 style='color: #e67e22;'>Warden Account Exists</h2>
                <p>The email <b>$email</b> is already registered as a warden.</p>
                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                <div>
                    <a href='javascript:history.back()' style='background-color: #95a5a6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Go Back</a>
                    <a href='login.html?role=warden' style='background-color: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Page</a>
                </div>
            </div>";
            exit(); 
        } else {
          
            $sql = "INSERT INTO wardens (fullname, phone, email, gender, password) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                throw new Exception("SQL Prepare Error: " . $conn->error);
            }

            $stmt->bind_param("sssss", $fullname, $phone, $email, $gender, $password);

            if ($stmt->execute()) {
                echo "<script>alert('Warden Account Created!'); window.location.href='login.html?role=warden';</script>";
            } else {
                throw new Exception("Execution Error: " . $stmt->error);
            }
        }
    }
} catch (Exception $e) {
    echo "<div style='color:red; text-align:center; font-family:sans-serif; margin-top:50px;'>";
    echo "<h2>System Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<a href='javascript:history.back()'>Click here to try again</a>";
    echo "</div>";
}
?>
