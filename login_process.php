<?php
session_start();
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    if ($role == "student") {
        $sql = "SELECT * FROM students WHERE email = ? AND password = ?";
        $dest = "student_dashboard.php";
    } elseif ($role == "warden") {
        $sql = "SELECT * FROM wardens WHERE email = ? AND password = ?";
        $dest = "warden_dashboard.php";
    } else {
        $sql = "SELECT * FROM teachers WHERE email = ? AND password = ?";
        $dest = "teacher_dashboard.php";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['fullname'] = $row['fullname'];
        $_SESSION['role'] = $role;

        if($role == 'warden') $_SESSION['gender'] = $row['gender'];
        if($role == 'teacher') $_SESSION['assigned_year'] = $row['assigned_year'];

        header("Location: " . $dest);
    } else {
        echo "<script>alert('Invalid Credentials!'); window.history.back();</script>";
    }
}
?>