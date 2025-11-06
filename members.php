<?php
session_start();
include("dpconnect.php");

// 🔹 ลบสมาชิก
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM members WHERE member_id=$id";
    mysqli_query($conn, $sql);
    header("Location: members.php");
    exit();
}

// 🔹 เพิ่ม/แก้ไขสมาชิก
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $member_id = $_POST['member_id'] ?? '';
    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $gender    = mysqli_real_escape_string($conn, $_POST['gender']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $phone     = mysqli_real_escape_string($conn, $_POST['phone']);

    if ($member_id == "") {
        // ✅ เพิ่มสมาชิกใหม่
        $password = mysqli_real_escape_string($conn, $_POST['password']);
        $hashed   = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO members (username, first_name, last_name, gender, email, password_hash, phone)
                VALUES ('$username', '$firstname', '$lastname', '$gender', '$email', '$hashed', '$phone')";
    } else {
        // ✅ แก้ไขข้อมูลสมาชิก
        $sql = "UPDATE members SET 
                    username='$username',
                    first_name='$firstname',
                    last_name='$lastname',
                    gender='$gender',
                    email='$email',
                    phone='$phone'
                WHERE member_id=$member_id";
    }

    mysqli_query($conn, $sql);
    header("Location: members.php");
    exit();
}

// 🔹 ดึงข้อมูลสมาชิก (ล่าสุดอยู่ด้านบน)
$result = mysqli_query($conn, "SELECT * FROM members ORDER BY member_id DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสมาชิก - Stadium booking</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; }
        .navbar { background: #4285f4; padding: 20px; color: #fff; }
        .navbar h2 { margin: 0; display: inline-block; }
        .navbar a { color: #fff; margin: 0 20px; text-decoration: none; font-weight: bold; }
        .container { padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: center; }
        th { background: #f4f4f4; }
        a.button, button { background: #4285f4; color: #fff; padding: 5px 10px;
                           border: none; border-radius: 5px; text-decoration: none; }
        a.button:hover, button:hover { background: #357ae8; }
        .form-box { margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-radius: 8px; }
        input, select { padding: 8px; margin: 5px; border: 1px solid #ccc; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Stadium booking</h2>
        <div style="float:right;">
            <a href="index.php">หน้าหลัก</a>
            <a href="members.php">จัดการสมาชิก</a>
             <a href="sports_fields.php">จัดการสนามกีฬา</a>
            <a href="logout.php">ออกจากระบบ</a>
        </div>
    </div>

    <div class="container">

        <!-- 🔹 ฟอร์มเพิ่ม/แก้ไขข้อมูลสมาชิก (ย้ายขึ้นมาด้านบน) -->
        <div class="form-box">
            <h3><?= isset($_GET['edit']) ? "แก้ไขสมาชิก" : "เพิ่มสมาชิกใหม่" ?></h3>
            <?php
            $edit_id = $_GET['edit'] ?? '';
            $editData = [
                "member_id" => "",
                "username" => "",
                "first_name" => "",
                "last_name" => "",
                "gender" => "",
                "email" => "",
                "phone" => ""
            ];
            if ($edit_id != "") {
                $res = mysqli_query($conn, "SELECT * FROM members WHERE member_id=$edit_id LIMIT 1");
                $editData = mysqli_fetch_assoc($res);
            }
            ?>
            <form method="POST" action="members.php">
                <input type="hidden" name="member_id" value="<?= $editData['member_id'] ?>">
                <input type="text" name="username" placeholder="ชื่อผู้ใช้" value="<?= $editData['username'] ?>" required>
                <input type="text" name="firstname" placeholder="ชื่อ" value="<?= $editData['first_name'] ?>" required>
                <input type="text" name="lastname" placeholder="นามสกุล" value="<?= $editData['last_name'] ?>" required>
                <select name="gender" required>
                    <option value="">--เลือกเพศ--</option>
                    <option value="male"   <?= $editData['gender']=="male"?"selected":"" ?>>ชาย</option>
                    <option value="female" <?= $editData['gender']=="female"?"selected":"" ?>>หญิง</option>
                    <option value="other"  <?= $editData['gender']=="other"?"selected":"" ?>>อื่น ๆ</option>
                </select>
                <input type="email" name="email" placeholder="อีเมล" value="<?= $editData['email'] ?>" required>
                <input type="text" name="phone" placeholder="เบอร์โทร" value="<?= $editData['phone'] ?>">
                
                <?php if ($edit_id == ""): ?>
                <input type="password" name="password" placeholder="รหัสผ่าน" required>
                <?php endif; ?>
                
                <button type="submit"><?= $edit_id != "" ? "บันทึกการแก้ไข" : "เพิ่มสมาชิก" ?></button>
            </form>
        </div>

        <!-- 🔹 ตารางสมาชิก -->
        <h3>รายชื่อสมาชิก</h3>
        <table>
            <tr>
                <th>จัดการ</th>
                <th>ID</th>
                <th>ชื่อผู้ใช้</th>
                <th>ชื่อ</th>
                <th>นามสกุล</th>
                <th>เพศ</th>
                <th>อีเมล</th>
                <th>เบอร์โทร</th>
            </tr>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td>
                    <a class="button" href="members.php?edit=<?= $row['member_id'] ?>">แก้ไข</a> |
                    <a class="button" href="members.php?delete=<?= $row['member_id'] ?>" 
                       onclick="return confirm('ต้องการลบสมาชิกนี้หรือไม่?');">ลบ</a>
                </td>
                <td><?= $row['member_id'] ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['first_name']) ?></td>
                <td><?= htmlspecialchars($row['last_name']) ?></td>
                <td><?= $row['gender'] ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>