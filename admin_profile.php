<?php
session_start();
include("dpconnect.php");

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$success_message = "";
$error_message = "";

// 2. จัดการการอัปเดตข้อมูล
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    // ✅ เพิ่มการรับค่า 'gender' 
    $gender = mysqli_real_escape_string($conn, $_POST['gender']); 

    // ตรวจสอบว่ามีข้อมูล 'password' ถูกส่งมาด้วยหรือไม่ (ถ้าไม่ได้กรอก จะไม่เปลี่ยนรหัสผ่าน)
    $password_clause = "";
    if (!empty($_POST['password'])) {
        $new_password = $_POST['password'];
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $password_clause = ", password_hash = '$hashed_password'";
    }

    // 🔹 คำสั่ง UPDATE (รวมคอลัมน์ gender)
    $sql_update = "UPDATE admins SET 
                    first_name = '$first_name', 
                    last_name = '$last_name', 
                    phone = '$phone',
                    gender = '$gender' " // ✅ เพิ่มการอัปเดตเพศ
                    . $password_clause . 
                    " WHERE admin_id = '$admin_id'";
    
    if (mysqli_query($conn, $sql_update)) {
        // อัปเดต Session name ทันที
        $_SESSION['admin_name'] = $first_name; 
        $success_message = "✅ อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้ว!";
    } else {
        $error_message = "❌ เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . mysqli_error($conn);
    }
}


// 3. ดึงข้อมูล Admin ปัจจุบัน (รวมคอลัมน์ gender)
$sql_admin = "SELECT admin_id, email, first_name, last_name, phone, gender FROM admins WHERE admin_id = '$admin_id'";
$result_admin = mysqli_query($conn, $sql_admin);

if (!$result_admin || mysqli_num_rows($result_admin) == 0) {
    header("Location: logout.php"); 
    exit();
}

$admin_data = mysqli_fetch_assoc($result_admin);
$current_user_name = $_SESSION['admin_name'] ?? $admin_data['first_name'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ข้อมูลส่วนตัว - Admin</title>
    <style>
        /* 🚨 สไตล์ Dashboard Profile */
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');
        body { font-family: 'Sarabun', sans-serif; background-color: #e9ecef; margin: 0; padding: 0; }
        .navbar { 
            background: #2c3e50; /* สีเข้มสำหรับ Admin */
            color: #fff; 
            padding: 15px 25px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .navbar h2 { margin: 0; font-size: 24px; font-weight: 700; }
        .navbar a { 
            color: #bdc3c7; 
            margin-left: 25px; 
            text-decoration: none; 
            font-weight: 500; 
            transition: color 0.3s; 
        }
        .navbar a:hover { color: #fff; }
        .navbar a[style*="font-weight: 700"] { color: #fff; border-bottom: 2px solid #3498db; padding-bottom: 5px; }

        .container { 
            padding: 30px; 
            max-width: 700px; /* จำกัดขนาดให้ดูดีขึ้น */
            margin: 30px auto; 
        }
        h1 { 
            color: #34495e; 
            margin-bottom: 30px; 
            font-size: 28px; 
            border-bottom: 2px solid #ccc;
            padding-bottom: 10px;
        }

        .profile-form {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #34495e;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-group input[disabled] {
            background-color: #f5f5f5;
            color: #7f8c8d;
        }

        .submit-button {
            background: #2ecc71;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .submit-button:hover {
            background-color: #27ae60;
        }

        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }

    </style>
</head>
<body>
    <div class="navbar">
        <h2>Admin Dashboard</h2>
        <div>
            <a href="admin_bookings.php">รายการจองทั้งหมด</a> 
            <a href="sports_fields.php">จัดการสนามกีฬา</a>
            <a href="admin_profile.php" style="font-weight: 700;">ข้อมูลส่วนตัว</a>
            <a href="logout.php">ออกจากระบบ (<?= htmlspecialchars($current_user_name) ?>)</a>
        </div>
    </div>

    <div class="container">
        <h1>ข้อมูลส่วนตัว (Admin)</h1>

        <?php if ($success_message): ?>
            <div class="alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="admin_profile.php" class="profile-form">
            
            <div class="form-group">
                <label for="email">อีเมล (ไม่สามารถแก้ไขได้)</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin_data['email']) ?>" disabled>
            </div>

            <div class="form-group">
                <label for="first_name">ชื่อจริง</label>
                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($admin_data['first_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="last_name">นามสกุล</label>
                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($admin_data['last_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="gender">เพศ</label>
                <select id="gender" name="gender" required>
                    <option value="">-- เลือกเพศ --</option>
                    <option value="Male" <?= ($admin_data['gender'] == 'Male' ? 'selected' : '') ?>>ชาย</option>
                    <option value="Female" <?= ($admin_data['gender'] == 'Female' ? 'selected' : '') ?>>หญิง</option>
                    <option value="Other" <?= ($admin_data['gender'] == 'Other' ? 'selected' : '') ?>>อื่น ๆ</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="phone">เบอร์โทรศัพท์</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($admin_data['phone']) ?>" required>
            </div>

            <div class="form-group password-group">
                <label for="password">รหัสผ่านใหม่ (เว้นว่างหากไม่ต้องการเปลี่ยน)</label>
                <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่านใหม่">
            </div>

            <button type="submit" class="submit-button">บันทึกการเปลี่ยนแปลง</button>
        </form>

    </div>
</body>
</html>