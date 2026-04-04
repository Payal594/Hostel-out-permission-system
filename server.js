const express = require('express');
const mysql = require('mysql2/promise');
const path = require('path');
const session = require('express-session');
const fs = require('fs');

const app = express();
const port = process.env.PORT || 3000;

app.use(express.urlencoded({ extended: true }));
app.use(express.static(__dirname));


app.use(session({
    secret: 'my_super_secret_hostel_key', 
    resave: false,
    saveUninitialized: false
}));

const db = mysql.createPool({
    
    uri: 'mysql://2yqVkh9W4Uk13A9.root:l6oi5GAdsaups4rv@gateway01.ap-southeast-1.prod.aws.tidbcloud.com:4000/test?ssl={"rejectUnauthorized":true}',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

db.getConnection()
    .then(() => console.log('✅ Successfully connected to TiDB Cloud Database!'))
    .catch(err => console.error('❌ Database connection failed:', err));



app.post('/register-student', async (req, res) => {
    const { fullname, phone, email, gender, password, study_year, hostel_name, room_number, parent_name, parent_phone } = req.body;
    try {
        const query = `INSERT INTO students (fullname, phone, email, gender, password, study_year, hostel_name, room_number, parent_name, parent_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;
        await db.execute(query, [fullname, phone, email, gender, password, study_year, hostel_name, room_number, parent_name, parent_phone]);
        res.redirect('/login.html?role=student');
    } catch (error) {
        console.error('Database Error:', error);
        res.send("<h2>Error creating account. Email or phone might already exist.</h2><a href='/SIGNUP_Student.html'>Go back</a>");
    }
});


app.post('/login', async (req, res) => {
   
    const { role, email, password } = req.body;

    
    let tableName = '';
    if (role === 'student') tableName = 'students';
    else if (role === 'warden') tableName = 'wardens';
    else if (role === 'teacher') tableName = 'teachers';

    try {
        
        const [rows] = await db.execute(`SELECT * FROM ${tableName} WHERE email = ? AND password = ?`, [email, password]);

       
        if (rows.length > 0) {
            const user = rows[0];
            
           
            req.session.isLoggedIn = true;
            req.session.userId = user.id;
            req.session.userRole = role;
            req.session.userEmail = user.email;

            // 6. Send them to their dashboard!
            res.redirect(`/${role}_dashboard`);
        } else {
            // No match found. Send them back to the login page with an error.
            res.redirect(`/login.html?role=${role}&error=true`);
        }
    } catch (error) {
        console.error('Login Error:', error);
        res.status(500).send("Server Error during login.");
    }
});

// --- ROUTE 6: WARDEN APPROVAL / REJECTION ---
app.post('/update-request-status', async (req, res) => {
    // 1. Security check: Only Wardens can do this!
    if (!req.session.isLoggedIn || req.session.userRole !== 'warden') {
        return res.redirect('/login.html?role=warden');
    }

    try {
        // Grab the hidden data sent by the button we just clicked
        const { request_id, status } = req.body;

        // Update the database!
        await db.execute(`UPDATE out_permissions SET status = ? WHERE request_id = ?`, [status, request_id]);

        // Refresh the Warden Dashboard instantly
        res.redirect('/warden_dashboard');

    } catch (error) {
        console.error("Update Status Error:", error);
        res.status(500).send("Error updating request status.");
    }
});

// --- ROUTE 7: SECURE LOGOUT ---
app.get('/logout', (req, res) => {
    // Remember the role before we destroy the session
    const role = req.session.userRole || 'student'; 
    
    // Destroy the VIP wristband!
    req.session.destroy((err) => {
        if (err) console.error("Logout error:", err);
        // Send them to their specific login page
        res.redirect(`/login.html?role=${role}`);
    });
});

// ==========================================
// --- ROUTE 8: VIEW PROFILE PAGE (SSR) ---
// ==========================================
app.get('/profile', async (req, res) => {
    if (!req.session.isLoggedIn) return res.redirect('/index.html');
    
    try {
        const { userId, userRole } = req.session;
        let tableName = userRole === 'student' ? 'students' : (userRole === 'warden' ? 'wardens' : 'teachers');
        
        const [rows] = await db.execute(`SELECT * FROM ${tableName} WHERE id = ?`, [userId]);
        const user = rows[0];

        const htmlFilePath = path.join(__dirname, 'profile.html');
        let rawHtml = fs.readFileSync(htmlFilePath, 'utf8');

        // Inject standard info
        rawHtml = rawHtml.replace('{{ROLE_CAPITALIZED}}', userRole.charAt(0).toUpperCase() + userRole.slice(1));
        rawHtml = rawHtml.replace('{{DASHBOARD_LINK}}', `/${userRole}_dashboard`);
        rawHtml = rawHtml.replace('{{FULLNAME}}', user.fullname);
        rawHtml = rawHtml.replace('{{EMAIL}}', user.email);
        rawHtml = rawHtml.replace('{{PHONE}}', user.phone || '');

        // Inject Student-specific info (Hostel and Room)
        if (userRole === 'student') {
            rawHtml = rawHtml.replace('{{STUDENT_FIELDS_STYLE}}', 'block');
            rawHtml = rawHtml.replace('{{ROOM_NUMBER}}', user.room_number || '');
            
            const hostels = user.gender === 'Male' 
                ? ["C V Ramam", "Bhatnagar", "Tagore", "Nalanda", "Ramanujan", "C V Rao"] 
                : ["Spoorthi", "Prathiba"];
            
            let hostelOptions = '';
            hostels.forEach(h => {
                let selected = (user.hostel_name === h) ? 'selected' : '';
                hostelOptions += `<option value="${h}" ${selected}>${h}</option>`;
            });
            rawHtml = rawHtml.replace('{{HOSTEL_OPTIONS}}', hostelOptions);
        } else {
            // Hide student fields for Wardens/Teachers
            rawHtml = rawHtml.replace('{{STUDENT_FIELDS_STYLE}}', 'none');
            rawHtml = rawHtml.replace('{{ROOM_NUMBER}}', '');
            rawHtml = rawHtml.replace('{{HOSTEL_OPTIONS}}', '');
        }

        res.send(rawHtml);
    } catch (err) {
        console.error(err);
        res.status(500).send("Error loading profile");
    }
});

// ==========================================
// --- ROUTE 9: SAVE PROFILE UPDATES ---
// ==========================================
app.post('/update-profile', async (req, res) => {
    if (!req.session.isLoggedIn) return res.redirect('/index.html');
    
    const { phone, hostel_name, room_number } = req.body;
    const { userId, userRole } = req.session;
    
    try {
        if (userRole === 'student') {
            await db.execute('UPDATE students SET phone=?, hostel_name=?, room_number=? WHERE id=?', [phone, hostel_name, room_number, userId]);
        } else if (userRole === 'warden') {
            await db.execute('UPDATE wardens SET phone=? WHERE id=?', [phone, userId]);
        } else if (userRole === 'teacher') {
            await db.execute('UPDATE teachers SET phone=? WHERE id=?', [phone, userId]);
        }
        res.redirect('/profile'); // Refresh the page to show changes!
    } catch (err) {
        console.error(err);
        res.status(500).send("Error updating profile");
    }
});

// ==========================================
// --- ROUTE 10: RESET PASSWORD ---
// ==========================================
app.post('/reset-password', async (req, res) => {
    const { role, email, phone, newpass } = req.body;
    let tableName = role === 'student' ? 'students' : (role === 'warden' ? 'wardens' : 'teachers');
    
    try {
        // Check if the email and phone match exactly
        const [rows] = await db.execute(`SELECT id FROM ${tableName} WHERE email=? AND phone=?`, [email, phone]);
        
        if (rows.length > 0) {
            // Match found! Update the password.
            await db.execute(`UPDATE ${tableName} SET password=? WHERE id=?`, [newpass, rows[0].id]);
            res.redirect(`/login.html?role=${role}`); // Send them to login
        } else {
            // No match found. Send them back to the reset page with an error.
            res.redirect(`/reset_password.html?error=notfound`);
        }
    } catch (err) {
        console.error(err);
        res.status(500).send("Database error during reset");
    }
});

// ==========================================
// --- ROUTE 11: PROMOTE STUDENTS ---
// ==========================================
app.post('/promote-students', async (req, res) => {
    // Security check: Only Wardens can do this!
    if (!req.session.isLoggedIn || req.session.userRole !== 'warden') {
        return res.redirect('/login.html?role=warden');
    }

    try {
        // Increase everyone's study year by 1 (as long as they are year 1, 2, or 3)
        await db.execute('UPDATE students SET study_year = study_year + 1 WHERE study_year < 4');
        res.redirect('/warden_dashboard');
    } catch (err) {
        console.error(err);
        res.status(500).send("Error promoting students");
    }
});

// ==========================================
// --- ROUTE 12: TEACHER DASHBOARD ---
// ==========================================
app.get('/teacher_dashboard', async (req, res) => {
    if (!req.session.isLoggedIn || req.session.userRole !== 'teacher') {
        return res.redirect('/login.html?role=teacher');
    }

    try {
        const teacherId = req.session.userId;

        // 1. Fetch Teacher Data
        const [teacherRows] = await db.execute(`SELECT * FROM teachers WHERE id = ?`, [teacherId]);
        const teacher = teacherRows[0];

        // 2. Fetch Pending Requests for this teacher's assigned year
        const query = `
            SELECT p.*, s.fullname as student_name, s.room_number 
            FROM out_permissions p 
            JOIN students s ON p.student_id = s.id 
            WHERE p.teacher_approval = 'Pending' AND s.study_year = ? 
            ORDER BY p.request_id DESC
        `;
        const [requests] = await db.execute(query, [teacher.assigned_year]);

        // 3. Build HTML Rows
        let requestsHtml = '';
        if (requests.length === 0) {
            requestsHtml = '<tr><td colspan="5" style="text-align:center; padding: 30px; color:#888;">No pending requests found.</td></tr>';
        } else {
            for (let i = 0; i < requests.length; i++) {
                let req = requests[i];
                let outDate = new Date(req.out_date).toLocaleDateString();
                
                requestsHtml += `<tr>
                    <td><span style="font-weight:bold; color:#333;">${req.student_name}</span><br><span style="font-size:0.85rem; color:#777;">Room: ${req.room_number}</span></td>
                    <td><strong>${req.destination}</strong></td>
                    <td>${outDate}<br><span style="font-size:0.85rem; color:#777;">${req.out_time}</span></td>
                    <td>${req.reason}</td>
                    <td>
                        <form action="/update-teacher-status" method="POST" style="display:inline-block;">
                            <input type="hidden" name="request_id" value="${req.request_id}">
                            <input type="hidden" name="status" value="Approved">
                            <button type="submit" style="background:#28a745; border:none; color:white; padding:8px 16px; border-radius:6px; font-weight:bold; cursor:pointer; margin-right:5px;">Approve</button>
                        </form>
                        <form action="/update-teacher-status" method="POST" style="display:inline-block;">
                            <input type="hidden" name="request_id" value="${req.request_id}">
                            <input type="hidden" name="status" value="Rejected">
                            <button type="submit" style="background:#dc3545; border:none; color:white; padding:8px 16px; border-radius:6px; font-weight:bold; cursor:pointer;">Reject</button>
                        </form>
                    </td>
                </tr>`;
            }
        }

        // 4. Inject into HTML
        const htmlFilePath = path.join(__dirname, 'teacher_dashboard.html');
        let rawHtml = fs.readFileSync(htmlFilePath, 'utf8');

        rawHtml = rawHtml.replace('{{TEACHER_YEAR}}', teacher.assigned_year);
        rawHtml = rawHtml.replace('{{PENDING_COUNT}}', requests.length);
        rawHtml = rawHtml.replace('{{REQUESTS_TABLE}}', requestsHtml);

        res.send(rawHtml);

    } catch (error) {
        console.error("Teacher Dashboard Error:", error);
        res.status(500).send("Error loading teacher dashboard.");
    }
});

// ==========================================
// --- ROUTE 13: TEACHER APPROVAL ---
// ==========================================
app.post('/update-teacher-status', async (req, res) => {
    if (!req.session.isLoggedIn || req.session.userRole !== 'teacher') return res.redirect('/login.html?role=teacher');

    try {
        const { request_id, status } = req.body;
        
        // If teacher rejects, the whole request is rejected. If they approve, it passes to the Warden!
        const mainStatus = status === 'Rejected' ? 'Rejected By Teacher' : 'Pending Warden';

        await db.execute(`UPDATE out_permissions SET teacher_approval = ?, status = ? WHERE request_id = ?`, [status, mainStatus, request_id]);
        
        res.redirect('/teacher_dashboard');
    } catch (error) {
        console.error("Update Teacher Status Error:", error);
        res.status(500).send("Error updating request status.");
    }
});

app.listen(port, () => {
    console.log(`🚀 Server is running at http://localhost:${port}`);
});


app.get('/student_dashboard', async (req, res) => {
    if (!req.session.isLoggedIn || req.session.userRole !== 'student') {
        return res.redirect('/login.html?role=student');
    }

    try {
        const studentId = req.session.userId;

        const [studentRows] = await db.execute(`SELECT * FROM students WHERE id = ?`, [studentId]);
        const student = studentRows[0];

        const [wardenRows] = await db.execute(`SELECT * FROM wardens WHERE gender = ? LIMIT 1`, [student.gender]);
        const warden = wardenRows.length > 0 ? wardenRows[0] : { fullname: "Not Assigned", phone: "--" };

       
        const [historyRows] = await db.execute(`SELECT * FROM out_permissions WHERE student_id = ? ORDER BY request_id DESC`, [studentId]);
        
        let historyHtml = '';
        if (historyRows.length === 0) {
            historyHtml = '<tr><td colspan="5" style="text-align:center;">No out-pass requests found.</td></tr>';
        } else {
            
            for (let i = 0; i < historyRows.length; i++) {
    let req = historyRows[i];
    
    let outDate = new Date(req.out_date).toLocaleDateString();
    let inDate = new Date(req.in_date).toLocaleDateString();
    
    // Status colors
    let statusColor = '#ffc107'; // Yellow/Orange for Pending
    if (req.status === 'Approved') statusColor = '#28a745'; // Green
    if (req.status.includes('Rejected') || req.status.includes('Cancelled')) statusColor = '#dc3545'; // Red

    // --- NEW CANCEL BUTTON LOGIC ---
    let actionBtn = '';
    // Only show the cancel button if the status has the word 'Pending' in it
    if (req.status.includes('Pending')) {
        actionBtn = `
            <form action="/cancel-request" method="POST" style="margin:0;">
                <input type="hidden" name="request_id" value="${req.request_id}">
                <button type="submit" style="background:#dc3545; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer; float:none; font-size: 0.85rem;">Cancel</button>
            </form>
        `;
    } else {
        // If it's already approved, rejected, or cancelled, just show a dash
        actionBtn = `<span style="color:#888;">-</span>`;
    }

    historyHtml += `<tr>
        <td><b>${req.destination}</b></td>
        <td>${outDate} (${req.out_time})</td>
        <td>${inDate}</td>
        <td>${req.teacher_approval}</td>
        <td style="color: ${statusColor}; font-weight: bold;">${req.status}</td>
        <td>${actionBtn}</td> </tr>`;
}
        }

     
        const htmlFilePath = path.join(__dirname, 'student_dashboard.html');
        let rawHtml = fs.readFileSync(htmlFilePath, 'utf8');

      
        rawHtml = rawHtml.replace('{{FULLNAME}}', student.fullname);
        rawHtml = rawHtml.replace('{{WARDEN_NAME}}', warden.fullname);
        rawHtml = rawHtml.replace('{{WARDEN_PHONE}}', warden.phone);
        rawHtml = rawHtml.replace('{{HISTORY_TABLE}}', historyHtml);

        
        res.send(rawHtml);

    } catch (error) {
        console.error("Dashboard Error:", error);
        res.status(500).send("Error loading dashboard.");
    }
});

// --- ROUTE 1.5: WARDEN REGISTRATION ---
app.post('/register-warden', async (req, res) => {
    // Grab data from the HTML form
    const { fullname, phone, email, gender, password } = req.body;
    
    try {
        const query = `INSERT INTO wardens (fullname, phone, email, gender, password) VALUES (?, ?, ?, ?, ?)`;
        await db.execute(query, [fullname, phone, email, gender, password]);
        
        // On success, redirect to the warden login page
        res.redirect('/login.html?role=warden');
    } catch (error) {
        console.error('Database Error:', error);
        res.send("<h2>Error creating warden account. Email might already exist.</h2><a href='/SIGNUP_Warden.html'>Go back</a>");
    }
});

// --- ROUTE 4: VANILLA SSR WARDEN DASHBOARD ---
app.get('/warden_dashboard', async (req, res) => {
    if (!req.session.isLoggedIn || req.session.userRole !== 'warden') {
        return res.redirect('/login.html?role=warden');
    }

    try {
        const wardenId = req.session.userId;

        const [wardenRows] = await db.execute(`SELECT * FROM wardens WHERE id = ?`, [wardenId]);
        const warden = wardenRows[0];

        const query = `
            SELECT p.*, s.fullname as student_name, s.room_number 
            FROM out_permissions p 
            JOIN students s ON p.student_id = s.id 
            WHERE s.gender = ? 
            ORDER BY p.request_id DESC
        `;
        const [requests] = await db.execute(query, [warden.gender]);

        // --- NEW: Setting up our counters ---
        let todayTotal = 0;
        let todayPending = 0;
        let todayAccepted = 0;
        
        // Get today's date in a clean format to compare against
        const todayStr = new Date().toLocaleDateString();

        let requestsHtml = '';
        if (requests.length === 0) {
            requestsHtml = '<tr><td colspan="7" style="text-align:center;">No requests found.</td></tr>';
        } else {
            for (let i = 0; i < requests.length; i++) {
                let req = requests[i];
                let outDate = new Date(req.out_date).toLocaleDateString();
                let inDate = new Date(req.in_date).toLocaleDateString();
                let createdDate = new Date(req.created_at).toLocaleDateString();
                
                // --- NEW: Count if the request was made today ---
                if (createdDate === todayStr) {
                    todayTotal++;
                    if (req.status.includes('Pending')) todayPending++;
                    if (req.status === 'Approved') todayAccepted++;
                }
                // Decide the color!
                let statusColor = '#ffc107'; 
                if (req.status === 'Approved') statusColor = '#28a745';
                if (req.status === 'Rejected') statusColor = '#dc3545';

                let actionButtons = '';

if (req.status === 'Pending Warden') {
    // ONLY show buttons if it is the Warden's turn to approve
    actionButtons = `
        <form action="/update-request-status" method="POST" style="margin-bottom: 5px;">
            <input type="hidden" name="request_id" value="${req.request_id}">
            <input type="hidden" name="status" value="Approved">
            <button type="submit" style="background:#28a745; border:none; color:white; padding:6px 12px; border-radius:4px; cursor:pointer; width:100%;">Approve</button>
        </form>
        <form action="/update-request-status" method="POST">
            <input type="hidden" name="request_id" value="${req.request_id}">
            <input type="hidden" name="status" value="Rejected">
            <button type="submit" style="background:#dc3545; border:none; color:white; padding:6px 12px; border-radius:4px; cursor:pointer; width:100%;">Reject</button>
        </form>
    `;
} else if (req.status === 'Pending Teacher') {
    // Show a nice badge letting the Warden know the Teacher has it
    actionButtons = `<span style="color:#ff9966; font-weight:bold; font-size: 0.9rem;">⏳ Waiting for Teacher</span>`;
} else {
    // It's already Approved, Rejected, or Rejected by Teacher
    actionButtons = `<span style="color:#888; font-style:italic;">Action Taken</span>`;
}
                
                requestsHtml += `<tr>
                    <td><b>${req.student_name}</b><br><small>Room: ${req.room_number}</small></td>
                    <td>${req.destination}</td>
                    <td>${outDate} (${req.out_time})</td>
                    <td>${inDate}</td>
                    <td>${req.reason}</td>
                    <td style="color: ${statusColor}; font-weight: bold;">${req.status}</td>
                    <td>${actionButtons}</td>
                </tr>`;
            }
        }

        const htmlFilePath = path.join(__dirname, 'warden_dashboard.html');
        let rawHtml = fs.readFileSync(htmlFilePath, 'utf8');

        // --- NEW: Inject the numbers into the HTML ---
        rawHtml = rawHtml.replace('{{FULLNAME}}', warden.fullname);
        rawHtml = rawHtml.replace('{{REQUESTS_TABLE}}', requestsHtml);
        rawHtml = rawHtml.replace('{{TODAY_TOTAL}}', todayTotal);
        rawHtml = rawHtml.replace('{{TODAY_PENDING}}', todayPending);
        rawHtml = rawHtml.replace('{{TODAY_ACCEPTED}}', todayAccepted);

        res.send(rawHtml);

    } catch (error) {
        console.error("Warden Dashboard Error:", error);
        res.status(500).send("Error loading warden dashboard.");
    }
});

// --- ROUTE 5: SUBMIT OUT-PASS REQUEST ---
app.post('/submit-request', async (req, res) => {
    // 1. Make sure only logged-in students can submit requests
    if (!req.session.isLoggedIn || req.session.userRole !== 'student') {
        return res.redirect('/login.html?role=student');
    }

    try {
        const studentId = req.session.userId;
        const { request_type, destination, out_date, out_time, in_date, reason } = req.body;

        // --- THE NEW LOGIC ENGINE ---
        
        // Combine date and time to create a JavaScript Date object
        const requestDateTime = new Date(`${out_date}T${out_time}`);
        
        // Get the Day of the Week (0 = Sunday, 1 = Monday ... 6 = Saturday)
        const dayOfWeek = requestDateTime.getDay();
        
        // Get the Hour of the Day (0 to 23 format)
        const requestHour = parseInt(out_time.split(':')[0]); 

        // Define your public holidays (Format: YYYY-MM-DD)
        const publicHolidays = ['2026-08-15', '2026-10-02', '2026-01-26']; 

        // Assume the teacher NEEDS to approve it, unless one of our rules is triggered
        let teacherApprovalNeeded = true;

        if (dayOfWeek === 0) { 
            // Rule 1: It is Sunday
            teacherApprovalNeeded = false;
        } else if (requestHour < 9 || requestHour >= 16) { 
            // Rule 2: It is outside working hours (Before 9 AM or 4 PM and later)
            teacherApprovalNeeded = false;
        } else if (publicHolidays.includes(out_date)) {
            // Rule 3: It is a public holiday
            teacherApprovalNeeded = false;
        }

        // Assign the correct text statuses based on our logic
        let teacherStatus = teacherApprovalNeeded ? 'Pending' : 'Not Required';
        let mainStatus = teacherApprovalNeeded ? 'Pending Teacher' : 'Pending Warden';

        // --- END OF LOGIC ENGINE ---

        // Insert the new request AND our new calculated statuses into the TiDB database
        const query = `
            INSERT INTO out_permissions 
            (student_id, destination, out_date, out_time, in_date, reason, request_type, teacher_approval, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        `;
        
        await db.execute(query, [studentId, destination, out_date, out_time, in_date, reason, request_type, teacherStatus, mainStatus]);

        // Send the student right back to their dashboard
        res.redirect('/student_dashboard');

    } catch (error) {
        console.error("Submit Request Error:", error);
        res.status(500).send("Error submitting request. Check your terminal.");
    }
});
