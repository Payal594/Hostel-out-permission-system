<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $gender = $_POST['gender'];
    $password = $_POST['password'];
    $year = $_POST['year'];
    $hostel = $_POST['hostel'];
    $room = $_POST['room_number'];
    $parentName = $_POST['parentName'];
    $parentPhone = $_POST['parentPhone'];

    $sql = "INSERT INTO students (fullname, phone, email, gender, password, study_year, hostel_name, room_number, parent_name, parent_phone) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $fullname, $phone, $email, $gender, $password, $year, $hostel, $room, $parentName, $parentPhone);

    if ($stmt->execute()) {
        echo "<script>alert('Account Created!'); window.location.href='login.html?role=student';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>