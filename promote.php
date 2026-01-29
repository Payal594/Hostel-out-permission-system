<?php
session_start();
include 'db_connect.php';

// 1. Security Check (Warden Only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warden') { header("Location: login.html?role=warden"); exit(); }

// 2. Handle Promotion Logic
if (isset($_POST['confirm'])) {
    // Logic: Increase year for everyone. 
    // Ideally, you might delete 4th years first: DELETE FROM students WHERE study_year='4'
    // For now, we just increment everyone. 4th years become "5" (Alumni).
    
    $conn->query("UPDATE students SET study_year = study_year + 1 WHERE study_year < 4");
    
    // Optional: You could mark 4th years as 'Alumni' here if you had a status column.
    
    echo "<script>alert('Success! All students have been promoted to the next year.'); window.location.href='warden_dashboard.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promote Students</title>
    <style>
        /* 1. Base Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }

        body {
            /* Warden Theme Background */
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* 2. The Card */
        .promote-card {
            background: white;
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            text-align: center;
            border-top: 6px solid #dc3545; /* Red top border for warning */
            animation: popIn 0.5s ease;
        }

        /* 3. Icon & Typography */
        .warning-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
        }

        h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }

        p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
            font-size: 1rem;
        }

        .highlight-box {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ffeeba;
            margin-bottom: 30px;
            font-size: 0.9rem;
            text-align: left;
        }
        
        .highlight-box ul {
            margin-left: 20px;
            margin-top: 5px;
        }

        /* 4. Buttons */
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        button, .btn-cancel {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
        }

        .btn-confirm {
            background: #dc3545;
            color: white;
            flex: 1;
        }
        .btn-confirm:hover {
            background: #c82333;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #e2e6ea;
            color: #555;
            flex: 1;
            display: inline-block;
            text-align: center;
        }
        .btn-cancel:hover {
            background: #dbe0e5;
            color: #333;
        }

        /* Animation */
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>

    <div class="promote-card">
        <span class="warning-icon">⚠️</span>
        <h2>Promote All Students?</h2>
        
        <p>This action updates the academic year for <strong>every student</strong> in the database.</p>
        
        <div class="highlight-box">
            <strong>What will happen:</strong>
            <ul>
                <li>1st Year students ➝ <strong>2nd Year</strong></li>
                <li>2nd Year students ➝ <strong>3rd Year</strong></li>
                <li>3rd Year students ➝ <strong>4th Year</strong></li>
            </ul>
        </div>

        <p style="font-size: 0.9rem; color: #dc3545; font-weight: bold;">
            This action cannot be undone. Are you sure?
        </p>

        <form method="POST" class="actions">
            <a href="warden_dashboard.php" class="btn-cancel">Cancel</a>
            
            <button type="submit" name="confirm" class="btn-confirm">Yes, Promote Everyone</button>
        </form>
    </div>

</body>
</html>