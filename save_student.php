<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    include 'db_connect.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
   
        $fullname    = trim($_POST['Fullname']); 
        $phone       = $_POST['phone'];
        $email       = trim($_POST['email']);
        $gender      = $_POST['gender'];
        $password    = $_POST['password'];
        $year        = $_POST['year'];
        $hostel      = $_POST['hostel'];
        $room        = $_POST['room_number'];
        $parentName  = $_POST['parentName'];
        $parentPhone = $_POST['parentPhone'];

     
        $checkEmail = "SELECT email FROM students WHERE email = ?";
        $stmt = $conn->prepare($checkEmail);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            
            echo "
            <div style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px; padding: 20px; border: 1px solid #ddd; border-radius: 10px; display: inline-block; width: 100%; max-width: 400px; position: relative; left: 50%; transform: translateX(-50%);'>
                <h2 style='color: #e74c3c;'>Account Already Exists!</h2>
                <p>The email <b>$email</b> is already registered in our system.</p>
                <hr style='border: 0; border-top: 1px solid #eee;'>
                <div style='margin-top: 20px;'>
                    <a href='SIGNUP_Student.html' style='background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Try Again</a>
                    <a href='login.html?role=student' style='background-color: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a>
                </div>
            </div>";
            exit(); 
        } else {
           
            $sql = "INSERT INTO students (fullname, phone, email, gender, password, study_year, hostel_name, room_number, parent_name, parent_phone) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                throw new Exception("SQL Prepare Error: " . $conn->error);
            }

            
            $stmt->bind_param("ssssssssss", $fullname, $phone, $email, $gender, $password, $year, $hostel, $room, $parentName, $parentPhone);

            if ($stmt->execute()) {
                echo "<script>alert('Account Created Successfully!'); window.location.href='login.html?role=student';</script>";
            } else {
                throw new Exception("Execution Error: " . $stmt->error);
            }
        }
    }
} catch (Exception $e) {
    echo "<div style='color:red; text-align:center;'>";
    echo "<h2>Server Crash Caught!</h2>";
    echo "<p>Reason: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
