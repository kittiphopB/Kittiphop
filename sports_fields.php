<?php
session_start();
include("dpconnect.php");

// 🚩 1. โค้ดตรวจสอบสิทธิ์: อนุญาตเฉพาะ Admin เท่านั้น
if (!isset($_SESSION['admin_id'])) {
    // ถ้าไม่มี session ของ admin ให้ redirect ไปที่หน้า login.php
    header("Location: login.php");
    exit();
}
// ----------------------------------------------------

// 📂 โฟลเดอร์เก็บไฟล์รูป
$uploadDir = "uploads/fields/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 🔹 ลบสนามกีฬา
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    // ดึง path รูปเก่าเพื่อลบไฟล์
    $oldImg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image_path FROM sports_fields WHERE field_id='$id'"));
    if ($oldImg && ($oldImg['image_path'] ?? '') && file_exists($uploadDir . $oldImg['image_path'])) {
        unlink($uploadDir . $oldImg['image_path']);
    }
    mysqli_query($conn, "DELETE FROM sports_fields WHERE field_id='$id'");
    header("Location: sports_fields.php");
    exit();
}

// 🔹 เพิ่ม/แก้ไขสนามกีฬา
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 💡 ปรับปรุงการรับค่า field_id ให้ปลอดภัยและรองรับ Null Coalescing Operator
    $field_id = mysqli_real_escape_string($conn, $_POST['field_id'] ?? ''); 
    $field_name = mysqli_real_escape_string($conn, $_POST['field_name'] ?? '');
    $sport_type = mysqli_real_escape_string($conn, $_POST['sport_type'] ?? '');
    $open_time = mysqli_real_escape_string($conn, $_POST['open_time'] ?? '00:00');
    $close_time = mysqli_real_escape_string($conn, $_POST['close_time'] ?? '00:00');
    $price_per_hour = intval($_POST['price_per_hour'] ?? 0); 
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $image_path = "";

    // โค้ดจัดการการอัปโหลดไฟล์
    if (isset($_FILES['field_image']) && $_FILES['field_image']['error'] == 0) {
        $ext = pathinfo($_FILES['field_image']['name'], PATHINFO_EXTENSION);
        $newName = "field_" . time() . "." . $ext;
        $target = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['field_image']['tmp_name'], $target)) {
            $image_path = $newName;
        }
    }

    // 🌟 LOGIC FIX: ตรวจสอบว่าเป็นการเพิ่มใหม่ (field_id ว่าง) หรือแก้ไข
    if (empty($field_id)) { 
        // 1. Logic สำหรับเพิ่มใหม่: สร้าง Field ID (F001, F002,...)
        $res = mysqli_query($conn, "SELECT field_id FROM sports_fields ORDER BY field_id DESC LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            $lastId = intval(substr($row['field_id'], 1));
            $field_id = "F" . str_pad($lastId + 1, 3, "0", STR_PAD_LEFT);
        } else {
            $field_id = "F001";
        }
        
        // 2. INSERT (ใช้ price_per_hour)
        $sql = "INSERT INTO sports_fields (field_id, field_name, sport_type, open_time, close_time, price_per_hour, is_active, image_path) 
                VALUES ('$field_id', '$field_name', '$sport_type', '$open_time', '$close_time', '$price_per_hour', '$is_active', '$image_path')";
    } else {
        // Logic สำหรับแก้ไข (field_id ไม่ว่าง)
        
        // อัปเดตข้อมูล
        if ($image_path != "") {
            // ลบรูปเก่าก่อนอัปเดต
            $oldImg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image_path FROM sports_fields WHERE field_id='$field_id'"));
            if ($oldImg && ($oldImg['image_path'] ?? '') && file_exists($uploadDir . $oldImg['image_path'])) {
                unlink($uploadDir . $oldImg['image_path']);
            }
            // UPDATE พร้อมรูปใหม่
            $sql = "UPDATE sports_fields SET field_name='$field_name', sport_type='$sport_type', open_time='$open_time', close_time='$close_time', price_per_hour='$price_per_hour', is_active='$is_active', image_path='$image_path' WHERE field_id='$field_id'";
        } else {
            // UPDATE ไม่มีรูปใหม่
            $sql = "UPDATE sports_fields SET field_name='$field_name', sport_type='$sport_type', open_time='$open_time', close_time='$close_time', price_per_hour='$price_per_hour', is_active='$is_active' WHERE field_id='$field_id'";
        }
    }
    
    mysqli_query($conn, $sql) or die("❌ SQL Error: " . mysqli_error($conn)); 
    header("Location: sports_fields.php");
    exit();
}

// 🔹 ดึงข้อมูลสนามกีฬา
$result = mysqli_query($conn, "SELECT *, price_per_hour FROM sports_fields ORDER BY created_at DESC");

// ดึงจำนวนรายการรอตรวจสอบ เพื่อแสดงใน Navbar
$payments_pending_count = 0;
try {
    $sql_pending_payments = "SELECT COUNT(payment_id) AS total FROM payments WHERE status = 'PENDING_REVIEW'";
    $result_pending = mysqli_query($conn, $sql_pending_payments);
    if ($result_pending) {
        $payments_pending_count = mysqli_fetch_assoc($result_pending)['total'];
    }
} catch (Exception $e) {
    // ไม่ต้องทำอะไรหากดึงข้อมูลไม่ได้
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสนามกีฬา - Admin Panel</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        
        /* 💡 สไตล์ Navbar เหมือน admin_dashboard.php */
        .navbar { background: #343a40; color: #fff; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; font-size: 24px; }
        .navbar a { color: #fff; margin-left: 20px; text-decoration: none; font-weight: 500; opacity: 0.9; transition: opacity 0.3s; }
        .navbar a:hover { opacity: 1; }
        
        .container { padding: 30px; max-width: 1200px; margin: auto; }
        
        .stadium-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        .stadium-header h3 { color: #333; margin: 0; font-size: 1.5em; }
        
        .add-stadium-button {
            background: #007bff; /* เปลี่ยนสีเป็นสีหลักของ Admin Theme */
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .add-stadium-button:hover { background: #0056b3; }
        
        .table-container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        table thead tr {
            background-color: #495057; /* เปลี่ยนสี Header ให้เข้ากับ Navbar */
            color: #fff;
        }
        table th, table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        table tbody tr:hover { background-color: #f5f8fa; }
        table td { vertical-align: middle; }
        .field-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .action-buttons button {
            font-size: 1.0em;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 600;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }
        .edit-button { background-color: #ffc107; color: #343a40; }
        .delete-button { background-color: #dc3545; color: #fff; }
        .edit-button:hover { background-color: #e0a800; }
        .delete-button:hover { background-color: #c82333; }
        
        /* Modal Styles (สำหรับ Add/Edit) */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            position: relative;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
        }
        
        /* Custom Confirm Modal Specific Styles (สำหรับยืนยันการลบ) */
        #customConfirmModal .confirm-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 30px;
            border-radius: 12px;
            max-width: 400px;
            text-align: center;
            position: relative;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
        }
        #customConfirmModal h4 {
            color: #dc3545;
            margin-bottom: 20px;
        }
        .confirm-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        .btn-confirm {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-yes { background-color: #dc3545; color: white; }
        .btn-no { background-color: #6c757d; color: white; }

        .close-button {
            color: #aaa;
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-button:hover, .close-button:focus {
            color: #333;
            text-decoration: none;
        }
        .form-row {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .form-field {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .form-field label {
            flex-basis: 150px;
            text-align: right;
            font-weight: 600;
            color: #555;
        }
        .form-field input, .form-field select {
            flex-grow: 1;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
        .form-field input[type="file"] {
            border: none;
            padding: 0;
            cursor: pointer;
        }
        .form-field input[type="checkbox"] {
            margin: 0;
            width: auto;
            transform: scale(1.5);
        }
        .form-actions { margin-top: 25px; text-align: right; }
        .submit-button {
            background: #28a745;
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .submit-button:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Admin Panel</h2>
        <div>
            <a href="admin_dashboard.php">หน้าหลักแอดมิน</a>
            <a href="admin_payments.php">ตรวจสอบชำระเงิน (<?= $payments_pending_count ?>)</a>
            <a href="sports_fields.php">จัดการสนามกีฬา</a>
            <a href="admin_profile.php">ข้อมูลส่วนตัว</a>
            <a href="admin_logout.php">ออกจากระบบ</a>
        </div>
    </div>

    <div class="container">
        <div class="stadium-header">
            <h3>⚽ จัดการสนามกีฬาและราคา</h3>
            <button class="add-stadium-button" onclick="openAddModal()">+ เพิ่มสนามกีฬาใหม่</button>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>จัดการ</th>
                        <th>รหัส</th>
                        <th>ชื่อสนามกีฬา</th>
                        <th>ประเภทกีฬา</th>
                        <th>เวลาเปิด-ปิด</th>
                        <th>ราคาพื้นฐาน/ชม.</th>
                        <th>สถานะ</th>
                        <th>รูปสนาม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    while($row = mysqli_fetch_assoc($result)): 
                    ?>
                    <tr 
                        data-field-id="<?= htmlspecialchars($row['field_id'] ?? '') ?>"
                        data-field-name="<?= htmlspecialchars($row['field_name'] ?? '') ?>"
                        data-sport-type="<?= htmlspecialchars($row['sport_type'] ?? '') ?>"
                        data-open-time="<?= htmlspecialchars($row['open_time'] ?? '') ?>"
                        data-close-time="<?= htmlspecialchars($row['close_time'] ?? '') ?>"
                        data-price-per-hour="<?= htmlspecialchars($row['price_per_hour'] ?? '') ?>" 
                        data-is-active="<?= htmlspecialchars($row['is_active'] ?? '') ?>"
                        data-image-path="<?= htmlspecialchars($row['image_path'] ?? '') ?>"
                    >
                        <td class="action-buttons">
                            <button class="edit-button" onclick="openEditModal(this)">แก้ไข</button>
                            <button class="delete-button" onclick="deleteStadium('<?= htmlspecialchars($row['field_id'] ?? '') ?>')">ลบ</button>
                        </td>
                        <td><?= htmlspecialchars($row['field_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['field_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['sport_type'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['open_time'] ?? '') ?> - <?= htmlspecialchars($row['close_time'] ?? '') ?></td>
                        <td><?= number_format($row['price_per_hour'] ?? 0) ?> บาท</td> 
                        <td><?= ($row['is_active'] ?? 0) ? "✅ เปิดใช้งาน" : "❌ ปิดใช้งาน" ?></td>
                        <td>
                            <?php if ($row['image_path'] ?? ''): ?>
                                <img src="uploads/fields/<?= htmlspecialchars($row['image_path'] ?? '') ?>" alt="รูปสนาม" class="field-image">
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

    <div id="stadiumModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">เพิ่มสนามกีฬาใหม่</h3>
            <form id="stadiumForm" method="POST" action="sports_fields.php" enctype="multipart/form-data">
                <input type="hidden" id="field_id_input" name="field_id" value="">
                <div class="form-row">
                    <div class="form-field">
                        <label for="field_name">ชื่อสนามกีฬา</label>
                        <input type="text" id="field_name" name="field_name" placeholder="ชื่อสนามกีฬา" required>
                    </div>
                    <div class="form-field">
                        <label for="sport_type">ประเภทกีฬา</label>
                        <select id="sport_type" name="sport_type" required>
                            <option value="football">ฟุตบอล</option>
                            <option value="basketball">บาสเกตบอล</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="open_time">เวลาเปิด</label>
                        <input type="time" id="open_time" name="open_time" value="09:00" required>
                    </div>
                    <div class="form-field">
                        <label for="close_time">เวลาปิด</label>
                        <input type="time" id="close_time" name="close_time" value="21:00" required>
                    </div>
                    <div class="form-field">
                        <label for="price_per_hour">ราคาพื้นฐาน/ชั่วโมง</label>
                        <input type="number" id="price_per_hour" name="price_per_hour" placeholder="บาท" min="1" required>
                    </div>
                    <div class="form-field">
                        <label for="is_active">สถานะ</label>
                        <div>
                            <input type="checkbox" id="is_active" name="is_active" value="1" checked> เปิดใช้งาน
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="field_image">รูปสนาม</label>
                        <input type="file" id="field_image" name="field_image" accept="image/*">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" id="submitButton" class="submit-button">เพิ่มสนามกีฬา</button>
                </div>
            </form>
        </div>
    </div>

    <div id="customConfirmModal" class="modal-overlay">
        <div class="confirm-content">
            <h4>⚠️ ยืนยันการลบข้อมูล</h4>
            <p id="confirmMessage" style="color: #333; font-weight: 500;">ต้องการลบสนามกีฬานี้หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้</p>
            <div class="confirm-actions">
                <button class="btn-confirm btn-yes" onclick="handleConfirm(true)">ใช่, ลบเลย</button>
                <button class="btn-confirm btn-no" onclick="handleConfirm(false)">ไม่, ยกเลิก</button>
            </div>
        </div>
    </div>
    <script>
        // Global variable for custom confirmation callback
        let confirmCallback = null;

        // ------------------------------------------
        // Logic for Custom Confirm Modal
        // ------------------------------------------
        /**
         * แสดง Modal ยืนยันการลบ
         * @param {string} message ข้อความที่ต้องการแสดงใน Modal
         * @param {function(boolean): void} callback ฟังก์ชันที่จะถูกเรียกเมื่อผู้ใช้กดยืนยัน (true) หรือยกเลิก (false)
         */
        function showCustomConfirm(message, callback) {
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('customConfirmModal').style.display = 'block';
            confirmCallback = callback;
        }

        /**
         * จัดการการตอบสนองจาก Modal ยืนยันการลบ
         * @param {boolean} isConfirmed True ถ้ายืนยัน, False ถ้ายกเลิก
         */
        function handleConfirm(isConfirmed) {
            document.getElementById('customConfirmModal').style.display = 'none';
            if (confirmCallback) {
                confirmCallback(isConfirmed);
            }
        }
        
        // ------------------------------------------
        // Logic for Deletion (Using Custom Confirm)
        // ------------------------------------------
        function deleteStadium(fieldId) {
            // เรียกใช้ Custom Confirm Modal แทน confirm()
            showCustomConfirm(
                'ต้องการลบสนามกีฬานี้หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้', 
                (confirmed) => {
                    if (confirmed) {
                        // ถ้าผู้ใช้กดยืนยัน ให้ไปหน้าลบ
                        window.location.href = 'sports_fields.php?delete=' + fieldId;
                    }
                }
            );
        }

        // ------------------------------------------
        // Logic for Add/Edit Modal (คงเดิม)
        // ------------------------------------------
        function openAddModal() {
            document.getElementById('stadiumForm').reset();
            document.getElementById('modalTitle').innerText = 'เพิ่มสนามกีฬาใหม่';
            document.getElementById('submitButton').innerText = 'เพิ่มสนามกีฬา';
            document.getElementById('field_id_input').value = '';
            document.getElementById('is_active').checked = true; 
            document.getElementById('open_time').value = '09:00';
            document.getElementById('close_time').value = '21:00';
            document.getElementById('stadiumModal').style.display = 'block';
        }

        function openEditModal(button) {
            const row = button.closest('tr');
            const fieldId = row.dataset.fieldId ?? '';
            const fieldName = row.dataset.fieldName ?? '';
            const sportType = row.dataset.sportType ?? '';
            const openTime = row.dataset.openTime ?? '';
            const closeTime = row.dataset.closeTime ?? '';
            const pricePerHour = row.dataset.pricePerHour ?? ''; 
            const isActive = row.dataset.isActive ?? '0';

            document.getElementById('modalTitle').innerText = 'แก้ไขสนามกีฬา';
            document.getElementById('submitButton').innerText = 'บันทึกการแก้ไข';
            
            document.getElementById('field_id_input').value = fieldId;
            document.getElementById('field_name').value = fieldName;
            document.getElementById('sport_type').value = sportType;
            document.getElementById('open_time').value = openTime;
            document.getElementById('close_time').value = closeTime;
            document.getElementById('price_per_hour').value = pricePerHour; 
            document.getElementById('is_active').checked = (isActive == '1');
            
            document.getElementById('stadiumModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('stadiumModal').style.display = 'none';
        }

    </script>
</body>
</html>