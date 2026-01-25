<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header("Location: login.html?role=student"); exit(); }

$sid = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Fetch Info
$res = mysqli_query($conn, "SELECT gender FROM students WHERE id='$sid'");
$student_data = mysqli_fetch_assoc($res);
$gender = $student_data['gender'];

// Fetch Warden Info
$w_res = mysqli_query($conn, "SELECT fullname, phone FROM wardens WHERE gender='$gender' LIMIT 1");
$warden = mysqli_fetch_assoc($w_res);
$w_name = $warden ? $warden['fullname'] : "Not Assigned";
$w_phone = $warden ? $warden['phone'] : "--";

// Handle Form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $out_date = $_POST['out_date'];
    $out_time = $_POST['out_time'];
    $in_date = $_POST['in_date'];
    $destination = $_POST['destination']; // NEW FIELD
    $reason = $_POST['reason'];
    $type = $_POST['type'];

    $dayOfWeek = date('w', strtotime($out_date));
    $timeStr = date('H:i', strtotime($out_time));
    $isWorkingDay = ($dayOfWeek != 0);
    $isCollegeHours = ($timeStr >= '09:00' && $timeStr <= '15:30');

    $teacher_approval = ($isWorkingDay && $isCollegeHours) ? "Pending" : "Not Required";
    $status = ($teacher_approval == "Pending") ? "Pending Teacher" : "Pending";

    // Updated Query to include 'destination'
    $stmt = $conn->prepare("INSERT INTO out_permissions (student_id, out_date, out_time, in_date, destination, reason, request_type, teacher_approval, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssss", $sid, $out_date, $out_time, $in_date, $destination, $reason, $type, $teacher_approval, $status);
    $stmt->execute();
    echo "<script>alert('Request Submitted!'); window.location.href='student_dashboard.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <style>
        /* 1. RESET */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f0f2f5; display: flex; height: 100vh; overflow: hidden; }

        /* 2. SIDEBAR */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 20px;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar h2 { font-size: 22px; margin-bottom: 30px; text-align: center; }
        .user-info { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar a {
            color: rgba(255,255,255,0.9); text-decoration: none;
            padding: 12px 15px; margin: 5px 0; border-radius: 8px;
            transition: all 0.3s; display: block; font-weight: 500;
        }
        .sidebar a:hover { background: rgba(255,255,255,0.2); color: white; padding-left: 20px; }
        .logout-btn { margin-top: auto; background: rgba(0,0,0,0.1); text-align: center; }

        /* 3. MAIN CONTENT */
        .main { flex: 1; padding: 40px; overflow-y: auto; }

        /* 4. CARDS & FORMS */
        .info-card {
            background: white; padding: 25px; border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px;
            border-left: 5px solid #667eea;
            display: flex; justify-content: space-between; align-items: center;
        }
        
        .form-card {
            background: white; padding: 30px; border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px;
        }

        .form-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        input, select, textarea {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;
        }
        button {
            padding: 12px 25px; background: #667eea; color: white; border: none;
            border-radius: 8px; font-weight: bold; cursor: pointer; float: right;
        }
        button:hover { background: #5a6fd6; }

        /* 5. TABLE */
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        th { background: #667eea; color: white; padding: 15px; text-align: left; font-size: 14px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .pending { background: #fff3cd; color: #856404; }
        .approved { background: #d4edda; color: #155724; }
        .rejected { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="user-info">
            <h2>Student Panel</h2>
            <p><?php echo $fullname; ?></p>
        </div>
        <a href="student_dashboard.php">📊 Dashboard</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="logout.php" class="logout-btn">🚪 Logout</a>
    </div>

    <div class="main">
        <div class="info-card">
            <div><h3>👮 Assigned Warden</h3><p><?php echo $w_name; ?></p></div>
            <span>📞 <?php echo $w_phone; ?></span>
        </div>

        <div class="form-card">
            <h3 style="margin-bottom:20px;">New Out Request</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Request Type</label>
                        <select name="type"><option>Normal</option><option>Urgent</option></select>
                    </div>
                    <div class="form-group">
                        <label>Where are you going?</label>
                        <input type="text" name="destination" placeholder="e.g. Home, Market, Hospital" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label>Out Date</label><input type="date" name="out_date" id="outDate" required></div>
                    <div class="form-group"><label>Out Time</label><input type="time" name="out_time" required></div>
                    <div class="form-group"><label>Return Date</label><input type="date" name="in_date" id="inDate" required></div>
                </div>
                
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" placeholder="Detailed reason..." required style="height:80px;"></textarea>
                </div>

                <button type="submit">Submit Request</button>
                <div style="clear:both"></div>
            </form>
        </div>

        <h3>Request History</h3>
        <table>
            <tr><th>Where To</th><th>Out</th><th>Return</th><th>Teacher</th><th>Status</th></tr>
            <?php
            $res = mysqli_query($conn, "SELECT * FROM out_permissions WHERE student_id='$sid' ORDER BY request_id DESC");
            while($r = mysqli_fetch_assoc($res)){
                $statusClass = (strpos($r['status'],'Approved')!==false)?'approved':((strpos($r['status'],'Rejected')!==false)?'rejected':'pending');
                echo "<tr>
                    <td><b>{$r['destination']}</b></td>
                    <td>{$r['out_date']} ({$r['out_time']})</td>
                    <td>{$r['in_date']}</td>
                    <td>{$r['teacher_approval']}</td>
                    <td><span class='badge $statusClass'>{$r['status']}</span></td>
                </tr>";
            }
            ?>
        </table>
    </div>
    <script>
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('outDate').setAttribute('min', today);
        document.getElementById('inDate').setAttribute('min', today);
    </script>
</body>
</html>