<?php
include 'dpconnect.php';

$id = $_GET['id'];
$result = $conn->query("SELECT * FROM members WHERE member_id=$id");
$member = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first = $_POST['first_name'];
    $last  = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    $sql = "UPDATE members SET first_name='$first', last_name='$last', email='$email', phone='$phone'
            WHERE member_id=$id";

    if ($conn->query($sql)) {
        header("Location: index.php");
        exit;
    } else {
        echo "❌ Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><title>แก้ไขสมาชิก</title></head>
<body>
  <h2>✏️ แก้ไขสมาชิก</h2>
  <form method="post">
    ชื่อ: <input type="text" name="first_name" value="<?= $member['first_name'] ?>" required><br>
    นามสกุล: <input type="text" name="last_name" value="<?= $member['last_name'] ?>" required><br>
    Email: <input type="email" name="email" value="<?= $member['email'] ?>" required><br>
    เบอร์โทร: <input type="text" name="phone" value="<?= $member['phone'] ?>"><br>
    <button type="submit">บันทึก</button>
  </form>
</body>
</html>