<?php
include("dpconnect.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $gender    = mysqli_real_escape_string($conn, $_POST['gender']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $password  = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm   = mysqli_real_escape_string($conn, $_POST['confirm']);
    $phone     = mysqli_real_escape_string($conn, $_POST['phone']);

    if ($password !== $confirm) {
        $message = "รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน!";
    } else {
        // เข้ารหัสรหัสผ่าน
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // ตรวจสอบว่ามี username หรือ email ซ้ำหรือไม่
        $check = "SELECT * FROM members WHERE email='$email' OR username='$username'";
        $result = mysqli_query($conn, $check);

        if (mysqli_num_rows($result) > 0) {
            $message = "อีเมลหรือชื่อผู้ใช้นี้ถูกใช้แล้ว!";
        } else {
            // เพิ่มข้อมูลสมาชิกใหม่
            $sql = "INSERT INTO members (username, first_name, last_name, gender, email, password_hash, phone) 
                    VALUES ('$username', '$firstname', '$lastname', '$gender', '$email', '$hashedPassword', '$phone')";
            if (mysqli_query($conn, $sql)) {
                $message = "สมัครสมาชิกสำเร็จ! <a href='login.php'>เข้าสู่ระบบ</a>";
            } else {
                $message = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สมัครสมาชิก - Stadium booking</title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff; margin: 0; }
        .navbar { background: #4285f4; padding: 20px; color: #fff; }
        .navbar h2 { display: inline-block; margin: 0; }
        .navbar a { color: #fff; margin: 0 20px; text-decoration: none; font-weight: bold; }
        .container { text-align: center; margin-top: 50px; }
        .register-box { display: inline-block; text-align: left; background: #fff; padding: 30px;
                        border-radius: 15px; box-shadow: 0px 3px 10px rgba(0,0,0,0.1); }
        .register-box h3 { text-align: center; margin-bottom: 20px; }
        .form-row { display: flex; justify-content: space-between; }
        .form-row div { margin: 5px; }
        input, select { width: 200px; padding: 10px; margin: 5px 0; border: 1px solid #ccc; border-radius: 20px; }
        button { background: #4285f4; color: #fff; padding: 10px 20px; border: none; border-radius: 7px;
                 cursor: pointer; margin-top: 10px; }
        button:hover { background: #357ae8; }
        .message { margin-top: 15px; font-weight: bold; color: red; text-align: center; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Stadium booking</h2>
        <div style="float:right;">
            <a href="index.php">หน้าหลัก</a>
            <a href="login.php">เข้าสู่ระบบ</a>
            <a href="register.php">สมัครสมาชิก</a>
        </div>
    </div>

    <div class="container">
        <div class="register-box">
            <h3>สมัครสมาชิก</h3>
            <form method="POST" action="register.php">
                <div class="form-row">
                    <div>
                        <label>ชื่อ</label><br>
                        <input type="text" name="firstname" required>
                    </div>
                    <div>
                        <label>นามสกุล</label><br>
                        <input type="text" name="lastname" required>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label>ชื่อผู้ใช้</label><br>
                        <input type="text" name="username" required>
                    </div>
                    <div>
                        <label>อีเมล</label><br>
                        <input type="email" name="email" required>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label>รหัสผ่าน</label><br>
                        <input type="password" name="password" required>
                    </div>
                    <div>
                        <label>ยืนยันรหัสผ่าน</label><br>
                        <input type="password" name="confirm" required>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label>เบอร์โทรศัพท์</label><br>
                        <input type="text" name="phone" required>
                    </div>
                    <div>
                        <label>เพศ</label><br>
                        <select name="gender" required>
                            <option value="">--เลือกเพศ--</option>
                            <option value="male">ชาย</option>
                            <option value="female">หญิง</option>
                            <option value="other">อื่น ๆ</option>
                        </select>
                    </div>
                </div>
                <div style="text-align:center;">
                    <button type="submit">ยืนยันการสมัคร</button>
                </div>
            </form>
            <?php if($message != "") { echo "<p class='message'>$message</p>"; } ?>
        </div>
    </div>
</body>
</html>