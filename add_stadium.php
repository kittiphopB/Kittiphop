<?php
session_start();
include("dpconnect.php");

// 📂 โฟลเดอร์เก็บไฟล์รูป
$uploadDir = "uploads/fields/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 🔹 ลบสนามกีฬา
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);

    $oldImg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image_path FROM sports_fields WHERE field_id='$id'"));
    if ($oldImg && $oldImg['image_path'] && file_exists($uploadDir . $oldImg['image_path'])) {
        unlink($uploadDir . $oldImg['image_path']);
    }

    mysqli_query($conn, "DELETE FROM sports_fields WHERE field_id='$id'");
    header("Location: sports_fields.php"); 
    exit();
}

// 🔹 เพิ่ม/แก้ไขสนามกีฬา
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 🚩🚩 1. ตรวจสอบว่าเป็นการเพิ่มหลายสนามหรือไม่ (ถ้าฟอร์มส่งข้อมูลแบบ Array มา) 🚩🚩
    if (isset($_POST['field_names']) && is_array($_POST['field_names'])) {
        
        $field_names = $_POST['field_names'];
        $sport_types = $_POST['sport_types'];
        $open_times = $_POST['open_times'];
        $close_times = $_POST['close_times'];
        $base_prices = $_POST['base_prices'];

        $successful_adds = 0;
        $error_messages = [];

        // ดึง ID ล่าสุดก่อนเริ่มลูป
        $res = mysqli_query($conn, "SELECT field_id FROM sports_fields ORDER BY field_id DESC LIMIT 1");
        $lastId = 0;
        if ($row = mysqli_fetch_assoc($res)) {
            $lastId = intval(substr($row['field_id'], 1));
        }

        foreach ($field_names as $index => $name) {
            
            // ตรวจสอบความถูกต้องของข้อมูลพื้นฐาน
            if (
                !empty($name) && 
                isset($sport_types[$index]) && 
                isset($open_times[$index]) && 
                isset($close_times[$index]) && 
                isset($base_prices[$index]) &&
                intval($base_prices[$index]) > 0
            ) {
                // 1. Clean Data
                $field_name = mysqli_real_escape_string($conn, trim($name));
                $sport_type = mysqli_real_escape_string($conn, $sport_types[$index]);
                $open_time = mysqli_real_escape_string($conn, $open_times[$index]);
                $close_time = mysqli_real_escape_string($conn, $close_times[$index]);
                $base_price = intval($base_prices[$index]);

                // 2. Generate New Field ID
                $field_id = "F" . str_pad($lastId + 1 + $successful_adds, 3, "0", STR_PAD_LEFT);
                
                // 3. INSERT
                $sql = "INSERT INTO sports_fields (field_id, field_name, sport_type, open_time, close_time, base_price, is_active)
                        VALUES ('$field_id', '$field_name', '$sport_type', '$open_time', '$close_time', '$base_price', 1)"; 
                
                if (mysqli_query($conn, $sql)) {
                    $successful_adds++;
                } else {
                    $error_messages[] = "ไม่สามารถเพิ่มสนาม $field_name ได้: " . mysqli_error($conn);
                }
            } else if (!empty($name)) {
                $error_messages[] = "ข้อมูลสำหรับสนาม $name ไม่ครบถ้วนหรือราคาไม่ถูกต้อง";
            }
        }
        
        // 4. Redirect
        if ($successful_adds > 0) {
             $_SESSION['success_msg'] = "✅ เพิ่มสนามกีฬาสำเร็จจำนวน **{$successful_adds}** สนาม";
        } 
        if (!empty($error_messages)) {
             $_SESSION['error_msg'] = "⚠️ มีข้อผิดพลาดบางส่วน: " . implode(" | ", $error_messages);
        } else if ($successful_adds == 0) {
             $_SESSION['error_msg'] = "❌ ไม่พบข้อมูลสนามกีฬาที่ถูกต้องในการบันทึก";
        }
        header("Location: sports_fields.php");
        exit();

    } else { 
        // --------------------------------------------------------------------------------------
        // 🚩🚩 2. ส่วนนี้คือตรรกะเดิมสำหรับการเพิ่ม/แก้ไข 1 สนามเท่านั้น 🚩🚩
        // --------------------------------------------------------------------------------------
        
        $field_id    = $_POST['field_id'] ?? '';
        $field_name  = mysqli_real_escape_string($conn, $_POST['field_name']);
        $sport_type  = mysqli_real_escape_string($conn, $_POST['sport_type']);
        $open_time   = mysqli_real_escape_string($conn, $_POST['open_time']);
        $close_time  = mysqli_real_escape_string($conn, $_POST['close_time']);
        $base_price  = intval($_POST['base_price']);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $image_path  = "";

        // โค้ดจัดการการอัปโหลดไฟล์รูปเดิมของคุณ
        if (isset($_FILES['field_image']) && $_FILES['field_image']['error'] == 0) {
            $ext = pathinfo($_FILES['field_image']['name'], PATHINFO_EXTENSION);
            $newName = "field_" . time() . "." . $ext;
            $target = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['field_image']['tmp_name'], $target)) {
                $image_path = $newName;
            }
        }

        // ตรวจสอบว่าเป็นการเพิ่มหรือแก้ไข
        if ($field_id == "") {
            // โค้ดเดิมสำหรับเพิ่ม 1 สนาม
            $res = mysqli_query($conn, "SELECT field_id FROM sports_fields ORDER BY field_id DESC LIMIT 1");
            if ($row = mysqli_fetch_assoc($res)) {
                $lastId = intval(substr($row['field_id'], 1));
                $field_id = "F" . str_pad($lastId + 1, 3, "0", STR_PAD_LEFT);
            } else {
                $field_id = "F001";
            }

            $sql = "INSERT INTO sports_fields (field_id, field_name, sport_type, open_time, close_time, base_price, is_active, image_path)
                    VALUES ('$field_id', '$field_name', '$sport_type', '$open_time', '$close_time', '$base_price', '$is_active', '$image_path')";
            $_SESSION['success_msg'] = "✅ เพิ่มสนามกีฬา **{$field_name}** สำเร็จ";
        } else {
            // โค้ดเดิมสำหรับแก้ไข (UPDATE) 1 สนาม
            if ($image_path != "") {
                $sql = "UPDATE sports_fields SET 
                            field_name='$field_name', sport_type='$sport_type', open_time='$open_time', 
                            close_time='$close_time', base_price='$base_price', is_active='$is_active', 
                            image_path='$image_path'
                        WHERE field_id='$field_id'";
            } else {
                $sql = "UPDATE sports_fields SET 
                            field_name='$field_name', sport_type='$sport_type', open_time='$open_time', 
                            close_time='$close_time', base_price='$base_price', is_active='$is_active'
                        WHERE field_id='$field_id'";
            }
            $_SESSION['success_msg'] = "✅ แก้ไขสนามกีฬา **{$field_name}** สำเร็จ";
        }

        mysqli_query($conn, $sql) or die(mysqli_error($conn));
        header("Location: sports_fields.php"); 
        exit();
    }
}

// 🔹 ดึงข้อมูลสนามกีฬา
$result = mysqli_query($conn, "SELECT * FROM sports_fields ORDER BY created_at DESC");

// 🔹 ถ้าเป็นการแก้ไข
$edit_id = $_GET['edit'] ?? '';
$editData = [
    "field_id" => "",
    "field_name" => "",
    "sport_type" => "",
    "open_time" => "",
    "close_time" => "",
    "base_price" => "",
    "is_active" => 1,
    "image_path" => ""
];
if ($edit_id != "") {
    $res = mysqli_query($conn, "SELECT * FROM sports_fields WHERE field_id='$edit_id' LIMIT 1");
    $editData = mysqli_fetch_assoc($res);
}

// 💡 จัดการแสดงข้อความแจ้งเตือน
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg']);
unset($_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสนามกีฬา - Stadium booking</title>
    <style>
        /* ... (โค้ด CSS เดิมของคุณทั้งหมด) ... */
        body { font-family: Arial, sans-serif; margin: 0; }
        .navbar { background: #4285f4; padding: 20px; color: #fff; }
        .navbar h2 { margin: 0; display: inline-block; }
        .navbar a { color: #fff; margin: 0 20px; text-decoration: none; font-weight: bold; }
        .container { padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: center; vertical-align: middle; }
        th { background: #f4f4f4; }
        img { max-width: 120px; border-radius: 5px; }
        a.button, button { background: #4285f4; color: #fff; padding: 5px 10px;
                            border: none; border-radius: 5px; text-decoration: none; }
        a.button:hover, button:hover { background: #357ae8; }
        .form-box { margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-radius: 8px; }
        input, select { padding: 8px; margin: 5px; border: 1px solid #ccc; border-radius: 5px; width: 90%; }
        /* สไตล์ที่ปรับปรุงใหม่ */
        .form-box {
            background: #f0f4f8;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 700px;
            margin: 20px auto;
        }
        .form-box h3 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        .form-group input[type="text"], 
        .form-group input[type="number"], 
        .form-group input[type="time"],
        .form-group select {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #4285f4;
            outline: none;
        }
        .time-group {
            flex-direction: row;
            justify-content: space-between;
        }
        .time-group > div {
            flex: 1;
            margin-right: 15px;
        }
        .time-group > div:last-child {
            margin-right: 0;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
            margin-top: 5px;
        }
        .switch input { display: none; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 28px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #4CAF50; }
        input:checked + .slider:before { transform: translateX(22px); }
        .form-actions {
            text-align: right;
            margin-top: 20px;
        }
        .submit-button {
            background: #4285f4;
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .submit-button:hover {
            background: #357ae8;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        table th, table td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #4285f4;
            color: #fff;
            font-weight: bold;
        }
        table tbody tr:hover {
            background-color: #f5f5f5;
        }
        .field-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        .action-buttons a {
            font-size: 1.2em;
            margin: 0 5px;
            text-decoration: none;
            color: #555;
            transition: transform 0.2s;
            display: inline-block;
        }
        .action-buttons a:hover {
            transform: scale(1.2);
        }
        .edit-button:hover { color: #4285f4; }
        .delete-button:hover { color: #d9534f; }
        .current-image {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9em;
            color: #777;
        }
        .current-image img {
            width: 50px;
            height: 50px;
            border: 1px solid #eee;
        }
        /* สไตล์สำหรับข้อความแจ้งเตือน */
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center;}
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
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
        
        <?php if ($success_message): ?>
            <div class="message success"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if ($edit_id): ?>
        <div class="form-box">
            <h3>แก้ไขสนามกีฬา</h3>
            <form method="POST" action="sports_fields.php" enctype="multipart/form-data">
                <input type="hidden" name="field_id" value="<?= htmlspecialchars($editData['field_id']) ?>">
                
                <div class="form-group">
                    <label>ชื่อสนามกีฬา</label>
                    <input type="text" name="field_name" placeholder="ชื่อสนามกีฬา" value="<?= htmlspecialchars($editData['field_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>ประเภทกีฬา</label>
                    <select name="sport_type" required>
                        <option value="">--เลือกประเภทกีฬา--</option>
                        <option value="football"   <?= $editData['sport_type']=="football"?"selected":"" ?>>ฟุตบอล</option>
                        <option value="basketball" <?= $editData['sport_type']=="basketball"?"selected":"" ?>>บาสเกตบอล</option>
                        <option value="tennis"     <?= $editData['sport_type']=="tennis"?"selected":"" ?>>เทนนิส</option>
                        <option value="badminton"  <?= $editData['sport_type']=="badminton"?"selected":"" ?>>แบดมินตัน</option>
                    </select>
                </div>
                
                <div class="form-group time-group">
                    <div>
                        <label>เวลาเปิด</label>
                        <input type="time" name="open_time" value="<?= htmlspecialchars($editData['open_time']) ?>" required>
                    </div>
                    <div>
                        <label>เวลาปิด</label>
                        <input type="time" name="close_time" value="<?= htmlspecialchars($editData['close_time']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>ราคาพื้นฐาน (บาท)</label>
                    <input type="number" name="base_price" placeholder="ราคาพื้นฐาน (บาท)" value="<?= htmlspecialchars($editData['base_price']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>สถานะ</label>
                    <label class="switch">
                        <input type="checkbox" name="is_active" value="1" <?= $editData['is_active'] ? "checked":"" ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label>รูปภาพ</label>
                    <input type="file" name="field_image" accept="image/*">
                    <?php if ($editData['image_path']): ?>
                        <div class="current-image">
                            <img src="uploads/fields/<?= htmlspecialchars($editData['image_path']) ?>" alt="รูปสนามปัจจุบัน" class="field-image">
                            <span>รูปภาพปัจจุบัน</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-button">บันทึกการแก้ไข</button>
                    <a href="sports_fields.php" style="background: #6c757d; color: white;" class="submit-button">ยกเลิก</a>
                </div>
            </form>
        </div>
        <?php endif; ?>


        <?php if (!$edit_id): ?>
        <div class="form-box" style="max-width: 90%; margin-bottom: 40px; border: 2px dashed #4285f4; margin: 20px auto;">
            <h3 style="color: #4285f4;">🚀 เพิ่มสนามกีฬาใหม่หลายรายการ</h3>
            
            <form id="multiAddForm" method="POST" action="sports_fields.php">
                
                <div id="field-list-container">
                    </div>
                
                <button type="button" onclick="addFieldRow()" style="background: #357ae8; margin-top: 15px; width: 100%;">+ เพิ่มแถวสนามกีฬา</button>

                <div class="form-actions">
                    <button type="submit" class="submit-button" style="background: #008000; width: 100%;">บันทึกทั้งหมด</button>
                </div>
            </form>
            
            <script>
                let fieldCount = 0;
                const container = document.getElementById('field-list-container');
                
                function addFieldRow(initialName = '', initialPrice = '') {
                    fieldCount++;
                    const row = document.createElement('div');
                    row.className = 'field-row';
                    row.style.border = '1px solid #ccc';
                    row.style.padding = '15px';
                    row.style.marginBottom = '15px';
                    row.style.borderRadius = '8px';
                    row.style.backgroundColor = '#fff';

                    row.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                             <h4 style="margin: 0; color: #555;">สนามที่ ${fieldCount}</h4>
                             <button type="button" onclick="this.closest('.field-row').remove(); updateFieldRowTitles();" style="background: #d9534f; padding: 5px 10px;">ลบ</button>
                        </div>
                        
                        <div class="form-group">
                            <label>ชื่อสนาม</label>
                            <input type="text" name="field_names[]" placeholder="ชื่อสนามกีฬา" value="${initialName}" required>
                        </div>
                        <div class="form-group">
                            <label>ประเภทกีฬา</label>
                            <select name="sport_types[]" required>
                                <option value="">--เลือกประเภทกีฬา--</option>
                                <option value="football">ฟุตบอล</option>
                                <option value="basketball">บาสเกตบอล</option>
                                <option value="tennis">เทนนิส</option>
                                <option value="badminton">แบดมินตัน</option>
                            </select>
                        </div>
                        <div class="form-group time-group">
                            <div>
                                <label>เวลาเปิด</label>
                                <input type="time" name="open_times[]" value="09:00" required>
                            </div>
                            <div>
                                <label>เวลาปิด</label>
                                <input type="time" name="close_times[]" value="21:00" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>ราคาพื้นฐาน (บาท)</label>
                            <input type="number" name="base_prices[]" placeholder="ราคาต่อชั่วโมง" min="1" required>
                        </div>
                    `;
                    container.appendChild(row);
                }

                function updateFieldRowTitles() {
                    let currentCount = 1;
                    document.querySelectorAll('.field-row h4').forEach(h4 => {
                        h4.textContent = `สนามที่ ${currentCount++}`;
                    });
                    fieldCount = currentCount - 1; 
                }
                
                // เพิ่มแถวเริ่มต้น 2 แถว เมื่อโหลดหน้า
                document.addEventListener('DOMContentLoaded', () => {
                    addFieldRow(); 
                    addFieldRow(); 
                });
            </script>
        </div>
        <?php endif; ?>


        <h3 style="margin-top: 40px;">รายชื่อสนามกีฬา</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>จัดการ</th>
                        <th>รหัส</th>
                        <th>ชื่อสนามกีฬา</th>
                        <th>ประเภทกีฬา</th>
                        <th>เวลาเปิด-ปิด</th>
                        <th>ราคา</th>
                        <th>สถานะ</th>
                        <th>รูปสนาม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td class="action-buttons">
                            <a href="sports_fields.php?edit=<?= htmlspecialchars($row['field_id']) ?>" class="edit-button" title="แก้ไข">✏️</a>
                            <a href="sports_fields.php?delete=<?= htmlspecialchars($row['field_id']) ?>" class="delete-button" title="ลบ" onclick="return confirm('ต้องการลบสนามกีฬานี้หรือไม่?');">🗑️</a>
                        </td>
                        <td><?= htmlspecialchars($row['field_id']) ?></td>
                        <td><?= htmlspecialchars($row['field_name']) ?></td>
                        <td><?= htmlspecialchars($row['sport_type']) ?></td>
                        <td><?= htmlspecialchars($row['open_time']) ?> - <?= htmlspecialchars($row['close_time']) ?></td>
                        <td><?= number_format($row['base_price']) ?> บาท</td>
                        <td><?= $row['is_active'] ? "✅ เปิดใช้งาน" : "❌ ปิดใช้งาน" ?></td>
                        <td>
                            <?php if ($row['image_path']): ?>
                                <img src="uploads/fields/<?= htmlspecialchars($row['image_path']) ?>" alt="รูปสนาม" class="field-image">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

