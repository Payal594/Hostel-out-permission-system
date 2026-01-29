<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $gender = $_POST['gender'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("INSERT INTO wardens (fullname, phone, email, gender, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $fullname, $phone, $email, $gender, $password);

    if ($stmt->execute()) {
        echo "<script>alert('Warden Created!'); window.location.href='login.html?role=warden';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>