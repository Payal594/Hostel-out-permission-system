<?php
session_start();
include 'db_connect.php';


if (!isset($_SESSION['user_id'])) { 
    header("Location: index.html"); 
    exit(); 
}

$role = $_SESSION['role'];
$uid = $_SESSION['user_id'];


if ($role == 'student') {
    $table = 'students';
} elseif ($role == 'warden') {
    $table = 'wardens';
} elseif ($role == 'teacher') {
    $table = 'teachers';
} else {
    die("Error: Unrecognized user role.");
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
   
    $phone = trim($_POST['phone']);
    
  
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        echo "<script>alert('Error: Phone number must be exactly 10 digits.'); window.history.back();</script>";
        exit();
    }

    
    if ($role == 'student') {
        $hostel = $_POST['hostel'];
        $room = $_POST['room_number'];
        $sql = "UPDATE students SET phone=?, hostel_name=?, room_number=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $phone, $hostel, $room, $uid);

    } elseif ($role == 'warden') {
        $sql = "UPDATE wardens SET phone=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $phone, $uid);

    } elseif ($role == 'teacher') {
        $sql = "UPDATE teachers SET phone=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $phone, $uid);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Profile Updated Successfully!'); window.location.href='profile.php';</script>";
    } else {
        echo "<script>alert('Error updating profile: " . $stmt->error . "');</script>";
    }
}

// 3. Fetch Current Data for Display
$res = mysqli_query($conn, "SELECT * FROM $table WHERE id='$uid'");
if (!$res || mysqli_num_rows($res) == 0) {
    die("Error: Could not fetch user data. Please log in again.");
}
$data = mysqli_fetch_assoc($res);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f0f2f5; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }

        .profile-card {
            background: white; width: 100%; max-width: 500px; padding: 40px;
            border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .header h2 { color: #333; margin-bottom: 5px; }
        .header p { color: #777; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #555; font-weight: 600; font-size: 14px; }
        
        input, select { 
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; transition: 0.3s; 
        }
        input:focus, select:focus { border-color: #333; outline: none; }
        input:disabled { background: #f9f9f9; color: #888; cursor: not-allowed; border-color: #eee; }

        .btn-update {
            width: 100%; padding: 14px; background: #333; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s;
        }
        .btn-update:hover { background: #555; }

        .links { margin-top: 20px; text-align: center; font-size: 14px; }
        .links a { color: #777; text-decoration: none; margin: 0 10px; }
        .links a:hover { color: #333; text-decoration: underline; }
        .forgot-pass { color: #d9534f !important; font-weight: bold; }
    </style>
</head>
<body>

    <div class="profile-card">
        <div class="header">
            <h2>My Profile</h2>
            <p>Role: <?php echo ucfirst($role); ?></p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" value="<?php echo htmlspecialchars($data['fullname']); ?>" disabled>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="text" value="<?php echo htmlspecialchars($data['email']); ?>" disabled>
            </div>

            <div class="form-group">
    <label>Phone Number</label>
    <input type="tel" name="phone" 
           maxlength="10" 
           pattern="[0-9]{10}" 
           title="Please enter exactly 10 digits"
           oninput="this.value = this.value.replace(/[^0-9]/g, '');"
           value="<?php echo isset($data['phone']) ? htmlspecialchars($data['phone']) : ''; ?>" required>
</div>

            <?php if($role == 'student'): ?>
            <div class="form-group">
                <label>Hostel Name</label>
                <select name="hostel" id="hostelSelect" class="form-control" required>
                    <option value="<?php echo htmlspecialchars($data['hostel_name']); ?>" selected>
                        <?php echo htmlspecialchars($data['hostel_name']); ?> (Current)
                    </option>

                    <?php 
                    $userGender = strtolower($data['gender'] ?? ''); 

                    if ($userGender == 'male'): ?>
                        <option value="C V Ramam">C V Ramam</option>
                        <option value="Bhatnagar">Bhatnagar</option>
                        <option value="Tagore">Tagore</option>
                        <option value="Nalanda">Nalanda</option>
                        <option value="Ramanujan">Ramanujan</option>
                        <option value="C V Rao">C V Rao</option>
                    
                    <?php elseif ($userGender == 'female'): ?>
                        <option value="Spoorthi">Spoorthi</option>
                        <option value="Prathiba">Prathiba</option>
                    
                    <?php else: ?>
                        <option value="" disabled>Please set gender in profile</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
    <label>Room Number</label>
    <input type="text" name="room_number" 
           maxlength="3" 
           pattern="[0-9]{3}" 
           title="Please enter exactly 3 digits"
           oninput="this.value = this.value.replace(/[^0-9]/g, '');"
           value="<?php echo htmlspecialchars($data['room_number']); ?>" required>
</div>
            <?php endif; ?>
            
            <button type="submit" class="btn-update">Save Changes</button>
        </form>

        <div class="links">
            <a href="reset_password.html" class="forgot-pass">Change / Forgot Password?</a>
            <br><br>
            <a href="<?php echo htmlspecialchars($role); ?>_dashboard.php">← Back to Dashboard</a>
        </div>
    </div>

</body>
</html>
