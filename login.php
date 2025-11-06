<?php
session_start();
include("dpconnect.php"); 

// ตรวจสอบว่าล็อกอินอยู่แล้วหรือไม่ และส่งไปยังหน้าหลักที่เหมาะสม
if (isset($_SESSION['admin_id'])) {
    // 🌟 FIX 1: Admin ล็อกอินอยู่แล้ว ส่งไปหน้า Dashboard
    header("Location: admin_dashboard.php");
    exit();
}
if (isset($_SESSION['member_id'])) {
    header("Location: index.php");
    exit();
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password']; 
    
    $logged_in = false;
    
    // 1. ตรวจสอบการเข้าสู่ระบบแบบ ADMIN (ตรวจสอบก่อนเสมอ)
    $sql_admin = "SELECT admin_id, password_hash, first_name FROM admins WHERE email = '$email'";
    $result_admin = mysqli_query($conn, $sql_admin);
    
    if ($result_admin && mysqli_num_rows($result_admin) > 0) {
        $admin_data = mysqli_fetch_assoc($result_admin);
        
        if (password_verify($password, $admin_data['password_hash'])) {
            // ล็อกอิน Admin สำเร็จ
            $_SESSION['admin_id'] = $admin_data['admin_id'];
            $_SESSION['admin_name'] = $admin_data['first_name'];
            $logged_in = true;
            
            // 🌟 FIX 2: ล็อกอินสำเร็จ ส่งไปหน้า Dashboard
            header("Location: admin_dashboard.php");
            exit();
        }
    }
    
    // 2. ตรวจสอบการเข้าสู่ระบบแบบ MEMBER (ถ้า Admin ล็อกอินไม่สำเร็จ)
    if (!$logged_in) {
        $sql_member = "SELECT member_id, password_hash, first_name FROM members WHERE email = '$email'";
        $result_member = mysqli_query($conn, $sql_member);

        if ($result_member && mysqli_num_rows($result_member) > 0) {
            $member_data = mysqli_fetch_assoc($result_member);

            if (password_verify($password, $member_data['password_hash'])) {
                // ล็อกอิน Member สำเร็จ
                $_SESSION['member_id'] = $member_data['member_id'];
                $_SESSION['member_name'] = $member_data['first_name'];
                $logged_in = true;
                
                header("Location: index.php");
                exit();
            }
        }
    }
    
    // ถ้าไม่มีใครล็อกอินได้เลย
    if (!$logged_in) {
        $error_message = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-color: #f4f7f9; 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 380px;
            text-align: center;
        }
        h3 {
            color: #4285f4;
            margin-bottom: 25px;
            font-size: 1.8em;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 0.9em;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus { border-color: #4285f4; outline: none; }
        .submit-button {
            background-color: #4285f4;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.3s;
        }
        .submit-button:hover { background: #0d47a1; }
        .register-link { margin-top: 15px; font-size: 0.9em; }
        .register-link a { color: #4285f4; text-decoration: none; font-weight: 600; }
        .error-message { 
            padding: 10px; 
            border-radius: 6px; 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
            margin-bottom: 15px; 
            font-size: 0.95em;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h3>เข้าสู่ระบบ</h3>

        <?php if ($error_message): ?>
            <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            
            <div class="form-group">
                <label for="email">อีเมล</label>
                <input type="email" id="email" name="email" required placeholder="name@example.com">
            </div>

            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="submit-button">เข้าสู่ระบบ</button>
        </form>

        <div class="register-link">
            ยังไม่มีบัญชีใช่ไหม? <a href="register.php">ลงทะเบียน</a>
        </div>
    </div>
</body>
</html>