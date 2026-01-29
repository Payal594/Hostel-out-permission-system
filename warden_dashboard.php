<?php
session_start();
include 'db_connect.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warden') {
    header("Location: login.html?role=warden");
    exit();
}

// 2. GET WARDEN'S GENDER FROM SESSION
// This is critical. We use this variable to filter students in the SQL query below.
$warden_gender = $_SESSION['gender'];

// 3. Handle Actions (Approve / Reject)
if (isset($_GET['action'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    
    // Logic: Warden has final say. Status becomes 'Approved' or 'Rejected'.
    $final_status = ($action == 'approve') ? 'Approved' : 'Rejected';

    $stmt = $conn->prepare("UPDATE out_permissions SET status=? WHERE request_id=?");
    $stmt->bind_param("si", $final_status, $id);
    $stmt->execute();
    
    header("Location: warden_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Warden Dashboard</title>
    <style>
        /* 1. RESET & FONTS */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f0f2f5; display: flex; height: 100vh; overflow: hidden; }

        /* 2. SIDEBAR (Warden Green Theme) */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
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
            text-align: center; border-bottom: 5px solid #11998e;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 { font-size: 14px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card b { font-size: 36px; color: #11998e; display: block; margin-top: 10px; }

        /* 5. TABLE STYLING */
        h3.section-title { margin-bottom: 20px; color: #444; font-size: 1.2rem; border-left: 5px solid #11998e; padding-left: 15px; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        th { background: #11998e; color: white; padding: 15px; text-align: left; font-size: 14px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #333; font-size: 14px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #f9f9f9; }

        .student-name { font-weight: bold; font-size: 1rem; color: #333; }
        .sub-text { font-size: 0.85rem; color: #777; display: block; margin-top: 2px; }
        .phone-link { color: #11998e; font-weight: bold; text-decoration: none; font-size: 0.85rem; }

        /* 6. BUTTONS & BADGES */
        .btn { padding: 8px 16px; text-decoration: none; color: white; border-radius: 6px; font-size: 13px; font-weight: bold; margin-right: 8px; transition: opacity 0.2s; display: inline-block; }
        .btn:hover { opacity: 0.85; }
        .approve { background: #28a745; box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3); }
        .reject { background: #dc3545; box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3); }

        .urgent-badge { background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .normal-badge { background: #e2e6ea; color: #555; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }

        /* 7. EMPTY STATE */
        .empty-state { text-align: center; padding: 50px; background: white; border-radius: 12px; color: #888; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .empty-icon { font-size: 40px; margin-bottom: 10px; display: block; opacity: 0.5; }
    </style>
</head>
<body>
    
    <div class="sidebar">
        <div class="user-info">
            <h2>Warden Panel</h2>
            <p>Managing Wing:</p>
            <strong><?php echo $warden_gender; ?>s</strong>
        </div>
        <a href="warden_dashboard.php">📊 Dashboard</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="promote.php">🎓 Promote Students</a>
        <a href="logout.php" class="logout-btn">🚪 Logout</a>
    </div>

    <div class="main">
        
        <div class="stats-row">
            <?php
            // STATS LOGIC: Only count students matching Warden's Gender
            $pending_q = "SELECT COUNT(*) c FROM out_permissions p JOIN students s ON p.student_id=s.id WHERE s.gender='$warden_gender' AND p.status='Pending'";
            $approved_q = "SELECT COUNT(*) c FROM out_permissions p JOIN students s ON p.student_id=s.id WHERE s.gender='$warden_gender' AND p.status='Approved'";
            
            $pending = mysqli_fetch_assoc(mysqli_query($conn, $pending_q))['c'];
            $approved = mysqli_fetch_assoc(mysqli_query($conn, $approved_q))['c'];
            ?>
            
            <div class="stat-card">
                <h3>Requests Pending</h3>
                <b><?php echo $pending; ?></b>
            </div>
            <div class="stat-card" style="border-bottom-color: #28a745;">
                <h3>Total Approved</h3>
                <b style="color: #28a745;"><?php echo $approved; ?></b>
            </div>
        </div>

        <h3 class="section-title">Manage Student Requests</h3>

        <?php
        // --- THE MAIN LOGIC QUERY ---
        // 1. Join with Students table
        // 2. Filter: Student Gender must match Warden Gender (s.gender = '$warden_gender')
        // 3. Filter: Teacher MUST have Approved OR it wasn't required.
        // 4. Sort: URGENT requests appear at the top.
        
        $q = "SELECT p.*, s.fullname, s.room_number, s.parent_phone 
              FROM out_permissions p 
              JOIN students s ON p.student_id = s.id 
              WHERE s.gender = '$warden_gender' 
              AND (p.teacher_approval = 'Not Required' OR p.teacher_approval = 'Approved')
              ORDER BY 
                CASE WHEN p.request_type = 'Urgent' THEN 1 ELSE 2 END, 
                p.request_id DESC";
        
        $res = mysqli_query($conn, $q);
        
        if(mysqli_num_rows($res) > 0): 
        ?>
            <table>
                <thead>
                    <tr>
                        <th>Student Details</th>
                        <th>Type</th>
                        <th>Destination</th>
                        <th>Date & Time</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td>
                            <span class="student-name"><?php echo $row['fullname']; ?></span>
                            <span class="sub-text">Room: <?php echo $row['room_number']; ?></span>
                            <a href="tel:<?php echo $row['parent_phone']; ?>" class="phone-link">📞 Parent</a>
                        </td>
                        <td>
                            <?php if($row['request_type'] == 'Urgent'): ?>
                                <span class="urgent-badge">URGENT</span>
                            <?php else: ?>
                                <span class="normal-badge">NORMAL</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo $row['destination']; ?></strong>
                        </td>
                        <td>
                            <?php echo $row['out_date']; ?>
                            <span class="sub-text"><?php echo $row['out_time']; ?></span>
                        </td>
                        <td style="max-width: 200px; line-height: 1.4;">
                            <?php echo $row['reason']; ?>
                        </td>
                        <td>
                            <strong style="color: #555;"><?php echo $row['status']; ?></strong>
                        </td>
                        <td>
                            <?php if($row['status'] == 'Pending'): ?>
                                <a href="?action=approve&id=<?php echo $row['request_id']; ?>" class="btn approve">Approve</a>
                                <a href="?action=reject&id=<?php echo $row['request_id']; ?>" class="btn reject">Reject</a>
                            <?php else: ?>
                                <span style="color: #888; font-size: 0.9rem;">Processed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        
        <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">✅</span>
                <p>No active requests found for <?php echo $warden_gender; ?> students.</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>