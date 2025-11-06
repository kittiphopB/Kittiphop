<?php
session_start();
include("dpconnect.php");

// 🚩 1. ตรวจสอบสิทธิ์ Member
if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$success_message = "";
$error_message = "";

// 2. จัดการการอัปเดตข้อมูลส่วนตัว (แก้ไขชื่อ, นามสกุล, เบอร์โทรศัพท์, เพศ)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $new_first_name = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
    $new_last_name = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');
    $new_phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    // ✅ เพิ่ม: รับค่า gender
    $new_gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? ''); 

    if (empty($new_first_name) || empty($new_last_name)) {
        $error_message = "ชื่อและนามสกุลต้องไม่เป็นค่าว่าง";
    } else {
        // ✅ แก้ไข: เพิ่มการอัปเดตคอลัมน์ gender
        $sql_update_profile = "UPDATE members SET 
                                first_name = '$new_first_name', 
                                last_name = '$new_last_name', 
                                phone = '$new_phone',
                                gender = '$new_gender' 
                                WHERE member_id = '$member_id'";

        if (mysqli_query($conn, $sql_update_profile)) {
            // อัปเดต Session Name ทันที
            $_SESSION['member_name'] = $new_first_name;
            $success_message = "อัปเดตข้อมูลส่วนตัวสำเร็จแล้ว";
        } else {
            $error_message = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . mysqli_error($conn);
        }
    }
}

// 3. จัดการการเปลี่ยนรหัสผ่าน (ส่วนเดิม)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // ... (โค้ดตรวจสอบและเปลี่ยนรหัสผ่านเดิม) ...
    if (empty($new_password) || empty($confirm_password) || empty($current_password)) {
        $error_message = "กรุณากรอกรหัสผ่านให้ครบทุกช่อง";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน";
    } elseif (strlen($new_password) < 6) {
        $error_message = "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        $sql_check_pass = "SELECT password_hash FROM members WHERE member_id = '$member_id'";
        $result_check_pass = mysqli_query($conn, $sql_check_pass);
        $row_check_pass = mysqli_fetch_assoc($result_check_pass);
        $stored_hash = $row_check_pass['password_hash'];

        if (password_verify($current_password, $stored_hash)) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_update_pass = "UPDATE members SET password_hash = '$new_password_hash' WHERE member_id = '$member_id'";

            if (mysqli_query($conn, $sql_update_pass)) {
                $success_message = "เปลี่ยนรหัสผ่านสำเร็จแล้ว";
            } else {
                $error_message = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน: " . mysqli_error($conn);
            }
        } else {
            $error_message = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
        }
    }
}


// 4. ดึงข้อมูล Member ล่าสุดอีกครั้ง
// ✅ แก้ไข: เพิ่มคอลัมน์ gender ในการดึงข้อมูล
$sql_member = "SELECT first_name, last_name, email, phone, gender FROM members WHERE member_id = '$member_id'";
$result_member = mysqli_query($conn, $sql_member);
$member_data = mysqli_fetch_assoc($result_member);
$current_user_name = $member_data['first_name'] ?? '';

// ฟังก์ชันแปลงค่าเพศจาก DB เป็นภาษาไทยเพื่อแสดงผล
function getGenderThai($gender) {
    switch (strtoupper($gender)) {
        case 'MALE': return 'ชาย';
        case 'FEMALE': return 'หญิง';
        case 'OTHER': return 'อื่น ๆ';
        default: return '-';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ข้อมูลส่วนตัว - <?= htmlspecialchars($current_user_name) ?></title>
    <style>
        /* (CSS styles - ใช้โค้ด CSS เดิม) */
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .navbar { background: #4285f4; color: #fff; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; font-size: 24px; }
        .navbar a { color: #fff; margin-left: 20px; text-decoration: none; font-weight: 500; opacity: 0.9; transition: opacity 0.3s; }
        .navbar a:hover { opacity: 1; }
        .container { padding: 30px; max-width: 900px; margin: auto; }
        h1 { color: #4285f4; margin-bottom: 30px; border-bottom: 2px solid #ddd; padding-bottom: 10px;}
        
        .profile-card {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .profile-details p {
            margin: 15px 0;
            font-size: 1.1em;
            border-bottom: 1px dashed #eee;
            padding-bottom: 10px;
        }
        .profile-details strong {
            display: inline-block;
            width: 150px;
            color: #555;
            font-weight: 600;
        }

        .change-password-section h3 {
            color: #dc3545;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #343a40;
        }
        .form-group input, .form-group select { /* ✅ เพิ่ม select */
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .form-row { display: flex; gap: 20px; }
        .form-row > .form-group { flex: 1; }
        .submit-btn {
            background-color: #4285f4;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .submit-btn.red-btn { background-color: #dc3545; }
        .submit-btn:hover { background-color: #0d47a1; }
        .submit-btn.red-btn:hover { background-color: #c82333; }
        
        .alert-success { 
            background: #d4edda; color: #155724; border: 1px solid #c3e6cb; 
            padding: 15px; border-radius: 8px; margin-bottom: 20px;
        }
        .alert-error { 
            background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; 
            padding: 15px; border-radius: 8px; margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Stadium Booking</h2>
        <div>
            <a href="index.php">หน้าหลัก</a>
            <a href="my_bookings.php">รายการจองของฉัน</a>
            <a href="profile.php" style="font-weight: 700;">ข้อมูลส่วนตัว</a> 
            <a href="logout.php">ออกจากระบบ (<?= htmlspecialchars($current_user_name) ?>)</a>
        </div>
    </div>

    <div class="container">
        <h1>ข้อมูลส่วนตัว - <?= htmlspecialchars($current_user_name) ?></h1>

        <?php if ($success_message): ?>
            <div class="alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="profile-card">
            <h2>แก้ไขข้อมูลส่วนตัว</h2>
            <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">ชื่อ</label>
                        <input type="text" id="first_name" name="first_name" 
                               value="<?= htmlspecialchars($member_data['first_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">นามสกุล</label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?= htmlspecialchars($member_data['last_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">เบอร์โทรศัพท์</label>
                        <input type="text" id="phone" name="phone" 
                               value="<?= htmlspecialchars($member_data['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">เพศ</label>
                        <select id="gender" name="gender" required>
                            <option value="" disabled>-- เลือกเพศ --</option>
                            <option value="MALE" <?= ($member_data['gender'] ?? '') == 'MALE' ? 'selected' : '' ?>>ชาย</option>
                            <option value="FEMALE" <?= ($member_data['gender'] ?? '') == 'FEMALE' ? 'selected' : '' ?>>หญิง</option>
                            <option value="OTHER" <?= ($member_data['gender'] ?? '') == 'OTHER' ? 'selected' : '' ?>>อื่น ๆ</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">อีเมล (ไม่สามารถแก้ไขได้)</label>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($member_data['email'] ?? '') ?>" disabled>
                </div>
                
                <button type="submit" class="submit-btn">บันทึกข้อมูลส่วนตัว</button>
            </form>
        </div>
        
        <hr style="border: 0; height: 1px; background: #ccc; margin: 40px 0;">

        <div class="profile-card change-password-section">
            <h3>เปลี่ยนรหัสผ่าน</h3>
            <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">รหัสผ่านปัจจุบัน</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">รหัสผ่านใหม่</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="submit-btn red-btn">เปลี่ยนรหัสผ่าน</button>
            </form>
        </div>
    </div>
</body>
</html>