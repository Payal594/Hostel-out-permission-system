<?php
session_start();
include 'db_connect.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.html?role=teacher");
    exit();
}

$year = $_SESSION['assigned_year']; // e.g. "1"

// 2. Handle Actions
if (isset($_GET['action'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    
    // Logic: 
    // Teacher Approve -> Status becomes 'Pending' (So Warden can see it).
    // Teacher Reject -> Status becomes 'Rejected By Teacher'.
    
    if ($action == 'approve') {
        $t_status = 'Approved';
        $main_status = 'Pending';
    } else {
        $t_status = 'Rejected';
        $main_status = 'Rejected By Teacher';
    }
    
    $stmt = $conn->prepare("UPDATE out_permissions SET teacher_approval=?, status=? WHERE request_id=?");
    $stmt->bind_param("ssi", $t_status, $main_status, $id);
    $stmt->execute();
    
    header("Location: teacher_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard</title>
    <style>
        /* 1. RESET & FONTS */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f0f2f5; display: flex; height: 100vh; overflow: hidden; }

        /* 2. SIDEBAR (Orange Theme) */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #ff9966 0%, #ff5e62 100%);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 20px;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar h2 { font-size: 22px; margin-bottom: 30px; text-align: center; font-weight: 700; letter-spacing: 0.5px; }
        
        .user-info { 
            text-align: center; margin-bottom: 30px; padding-bottom: 20px; 
            border-bottom: 1px solid rgba(255,255,255,0.2); 
        }
        .user-info strong { font-size: 1.1rem; display:block; margin-top:5px; }

        .sidebar a {
            color: rgba(255,255,255,0.9); text-decoration: none;
            padding: 12px 15px; margin: 8px 0; border-radius: 8px;
            transition: all 0.3s; display: block; font-weight: 500;
        }
        .sidebar a:hover { background: rgba(255,255,255,0.2); color: white; transform: translateX(5px); }
        .logout-btn { margin-top: auto; background: rgba(0,0,0,0.1); text-align: center; }
        .logout-btn:hover { background: rgba(255,255,255,0.2); }

        /* 3. MAIN CONTENT */
        .main { flex: 1; padding: 40px; overflow-y: auto; }

        /* 4. STATS CARDS */
        .stats-row { display: flex; gap: 20px; margin-bottom: 30px; }
        .stat-card {
            flex: 1; background: white; padding: 25px;
            border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center; border-bottom: 5px solid #ff9966;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 { font-size: 14px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card b { font-size: 36px; color: #ff9966; display: block; margin-top: 10px; }

        /* 5. TABLE STYLING */
        h3.section-title { margin-bottom: 20px; color: #444; font-size: 1.2rem; border-left: 5px solid #ff9966; padding-left: 15px; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        th { background: #ff9966; color: white; padding: 15px; text-align: left; font-size: 14px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #333; font-size: 14px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #fffcfb; }

        .student-name { font-weight: bold; font-size: 1rem; color: #333; }
        .sub-text { font-size: 0.85rem; color: #777; display: block; margin-top: 2px; }

        /* 6. BUTTONS */
        .btn { padding: 8px 16px; text-decoration: none; color: white; border-radius: 6px; font-size: 13px; font-weight: bold; margin-right: 8px; transition: opacity 0.2s; display: inline-block; }
        .btn:hover { opacity: 0.85; }
        .approve { background: #28a745; box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3); }
        .reject { background: #dc3545; box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3); }

        /* 7. EMPTY STATE */
        .empty-state { text-align: center; padding: 50px; background: white; border-radius: 12px; color: #888; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .empty-icon { font-size: 40px; margin-bottom: 10px; display: block; opacity: 0.5; }
    </style>
</head>
<body>
    
    <div class="sidebar">
        <div class="user-info">
            <h2>Teacher Panel</h2>
            <p>Class In-Charge:</p>
            <strong><?php echo $year; ?>st Year</strong>
        </div>
        <a href="teacher_dashboard.php">📊 Dashboard</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="logout.php" class="logout-btn">🚪 Logout</a>
    </div>

    <div class="main">
        
        <div class="stats-row">
            <?php
            // STATS LOGIC
            $pending_q = "SELECT COUNT(*) c FROM out_permissions p JOIN students s ON p.student_id=s.id WHERE s.study_year='$year' AND p.teacher_approval='Pending'";
            $approved_q = "SELECT COUNT(*) c FROM out_permissions p JOIN students s ON p.student_id=s.id WHERE s.study_year='$year' AND p.teacher_approval='Approved'";
            
            $pending = mysqli_fetch_assoc(mysqli_query($conn, $pending_q))['c'];
            $approved = mysqli_fetch_assoc(mysqli_query($conn, $approved_q))['c'];
            ?>
            
            <div class="stat-card">
                <h3>Pending Actions</h3>
                <b><?php echo $pending; ?></b>
            </div>
            <div class="stat-card" style="border-bottom-color: #28a745;">
                <h3>Total Approved</h3>
                <b style="color: #28a745;"><?php echo $approved; ?></b>
            </div>
        </div>

        <h3 class="section-title">Pending Student Requests</h3>

        <?php
        // QUERY: Get requests for THIS year + Pending status
        $q = "SELECT p.*, s.fullname, s.room_number FROM out_permissions p 
              JOIN students s ON p.student_id = s.id 
              WHERE s.study_year = '$year' AND p.teacher_approval = 'Pending'
              ORDER BY p.request_id DESC";
        
        $res = mysqli_query($conn, $q);
        
        if(mysqli_num_rows($res) > 0): 
        ?>
            <table>
                <thead>
                    <tr>
                        <th>Student Details</th>
                        <th>Destination</th>
                        <th>Date & Time</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td>
                            <span class="student-name"><?php echo $row['fullname']; ?></span>
                            <span class="sub-text">Room: <?php echo $row['room_number']; ?></span>
                        </td>
                        <td>
                            <strong><?php echo $row['destination']; ?></strong>
                        </td>
                        <td>
                            <?php echo $row['out_date']; ?>
                            <span class="sub-text"><?php echo $row['out_time']; ?></span>
                        </td>
                        <td style="max-width: 250px; line-height: 1.4;">
                            <?php echo $row['reason']; ?>
                        </td>
                        <td>
                            <a href="?action=approve&id=<?php echo $row['request_id']; ?>" class="btn approve">Approve</a>
                            <a href="?action=reject&id=<?php echo $row['request_id']; ?>" class="btn reject">Reject</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        
        <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">✅</span>
                <p>No pending requests found for <?php echo $year; ?>st Year.</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>