<?php
session_start();
// ตรวจสอบว่าไฟล์ dpconnect.php มีอยู่จริงและเรียกใช้ได้อย่างถูกต้อง
include("dpconnect.php");

// 🚩 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$success_message = "";
$error_message = "";

// ✅ ฟังก์ชันสำหรับแสดงสถานะในรูปแบบ Badge (แก้ไขแล้ว)
function getStatusBadge($status) {
    $text = $status;
    $color = 'secondary';
    
    // 🎯 แก้ไข: แปลงสถานะที่รับมาให้เป็นตัวพิมพ์ใหญ่ทั้งหมดก่อนเข้า Switch
    $status = strtoupper($status); 
    
    switch ($status) {
        case 'PENDING_PAYMENT':
            $text = '🟡 รอชำระเงิน/อัปโหลดสลิป';
            $color = 'warning'; 
            break;
        case 'PAID_PENDING_REVIEW':
            $text = '🟠 รอตรวจสอบสลิป'; 
            $color = 'info'; 
            break;
        case 'PAID_CONFIRMED':
        case 'CONFIRMED': 
            $text = '🟢 จองสำเร็จ/ชำระเงินเสร็จสิ้น'; 
            $color = 'success'; 
            break;
        case 'CANCELLED_BY_MEMBER':
            $text = '🔴 ยกเลิกโดยผู้จอง'; 
            $color = 'danger'; 
            break;
        case 'CANCELLED_TIMEOUT':
            $text = '⚫ ยกเลิกโดยระบบ (เกินเวลา)';
            $color = 'dark'; 
            break;
        case 'COMPLETED':
            $text = '✅ ใช้บริการเสร็จสิ้น';
            $color = 'primary'; 
            break;
        default:
            // 🚩 ถ้ายังเข้า default แสดงว่าค่าใน DB ผิดปกติมาก
            $text = '❓ ' . $status; // แสดงค่าที่ผิดพลาดออกมาเพื่อ Debug
            $color = 'danger'; 
    }
    return "<span class='badge badge-{$color}'>" . $text . "</span>";
}

// 3. จัดการการเปลี่ยนสถานะการจอง (โดย Admin)
if (isset($_GET['action']) && isset($_GET['booking_id'])) {
    $booking_id_to_action = mysqli_real_escape_string($conn, $_GET['booking_id']);
    $action = $_GET['action'];
    $new_status = "";

    switch ($action) {
        case 'confirm_payment':
            $new_status = 'PAID_CONFIRMED';
            break;
        case 'reject_payment':
            $new_status = 'PENDING_PAYMENT'; 
            break;
        case 'set_complete':
            $new_status = 'COMPLETED'; 
            break;
        default:
            $error_message = "❌ การดำเนินการไม่ถูกต้อง";
            goto skip_update;
    }

    if (!empty($new_status)) {
        $sql_update = "UPDATE bookings SET status = '$new_status' WHERE booking_id = '$booking_id_to_action'";
        if (mysqli_query($conn, $sql_update)) {
            $success_message = "✅ อัปเดตสถานะการจอง #{$booking_id_to_action} เป็น '{$new_status}' สำเร็จ";
        } else {
            $error_message = "❌ ข้อผิดพลาดในการอัปเดตสถานะ: " . mysqli_error($conn);
        }
    }
}
skip_update:


// 4. ดึงรายการจองทั้งหมด
$sql_bookings = "
    SELECT 
        b.booking_id, 
        b.status, 
        b.total_price,
        b.created_at,
        m.first_name AS member_first_name,
        m.last_name AS member_last_name,
        GROUP_CONCAT(sf.field_name SEPARATOR ' / ') AS field_names,
        (
            SELECT p.slip_path
            FROM payments p
            WHERE p.booking_id = b.booking_id
            ORDER BY p.created_at DESC
            LIMIT 1
        ) AS last_slip_path
    FROM bookings b
    LEFT JOIN members m ON b.member_id = m.member_id
    LEFT JOIN booking_items bi ON b.booking_id = bi.booking_id
    LEFT JOIN sports_fields sf ON bi.field_code = sf.field_id
    GROUP BY b.booking_id
    ORDER BY b.created_at DESC
";
$result_bookings = mysqli_query($conn, $sql_bookings);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการจองทั้งหมด - Admin</title>
    <style>
        /* 🚨 สไตล์ Dashboard */
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');
        body { font-family: 'Sarabun', sans-serif; background-color: #e9ecef; margin: 0; padding: 0; }
        .navbar { 
            background: #2c3e50; 
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
            max-width: 1400px; 
            margin: 30px auto; 
        }
        h1 { 
            color: #34495e; 
            margin-bottom: 30px; 
            font-size: 28px; 
        }
        
        /* สไตล์ตารางหลัก */
        table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0;
            background: #fff; 
            border-radius: 10px; 
            overflow: hidden; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
        }
        th, td { 
            padding: 15px; 
            text-align: left; 
            border-bottom: 1px solid #f2f2f2; 
            font-size: 0.95em; 
        }
        th { 
            background-color: #f8f9fa; 
            color: #343a40; 
            font-weight: 700; 
            text-transform: uppercase;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfcfc; }
        
        /* สไตล์ Badge */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px; 
            font-size: 0.8em;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
        }
        .badge-warning { background-color: #f39c12; color: #343a40; } 
        .badge-info { background-color: #e67e22; } 
        .badge-success { background-color: #27ae60; } 
        .badge-danger { background-color: #e74c3c; } 
        .badge-primary { background-color: #3498db; } 
        .badge-dark { background-color: #7f8c8d; } 
        
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }

        .btn {
            display: inline-block;
            padding: 8px 12px; 
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 600;
            margin-right: 5px;
            white-space: nowrap;
            cursor: pointer;
            border: none;
            transition: background-color 0.2s;
        }
        .btn-confirm { background-color: #2ecc71; color: white; }
        .btn-reject { background-color: #e74c3c; color: white; }
        .btn-complete { background-color: #95a5a6; color: white; } 

        .btn-confirm:hover { background-color: #27ae60; }
        .btn-reject:hover { background-color: #c0392b; }
        .btn-complete:hover { background-color: #7f8c8d; }

        .action-cell { min-width: 280px; } 
        .slip-link { 
            color: #3498db; 
            text-decoration: none; 
            font-weight: 600; 
            cursor: pointer;
            transition: color 0.2s; 
        }
        .slip-link:hover { color: #2980b9; }

        /* ------------------- สไตล์ MODAL (ทั่วไป) ------------------- */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
            padding-top: 50px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 80%; 
            max-width: 400px; /* ขนาดเริ่มต้นสำหรับ Modal ยืนยัน */
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            text-align: center;
        }
        
        /* สไตล์ Modal สำหรับสลิป (ขยายขนาด) */
        #slipModal .modal-content {
            max-width: 600px; 
        }
        #slipImage {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 10px auto;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        /* สไตล์ข้อความ/ปุ่มใน Modal */
        .modal-content h3 {
            color: #34495e;
            margin-top: 0;
            font-size: 1.5em;
        }

        .modal-content p {
            font-size: 1.1em;
            margin-bottom: 20px;
        }

        .modal-footer button, .modal-footer a {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            margin: 0 5px;
            text-decoration: none; 
            display: inline-block;
        }

        .modal-footer .btn-cancel {
            background-color: #bdc3c7;
            color: #34495e;
            border: none;
        }

        .modal-footer .btn-confirm-modal {
            background-color: #3498db;
            color: white;
            border: none;
        }
        
        .modal-footer .btn-confirm-modal.confirm {
            background-color: #2ecc71;
        }
        
        .modal-footer .btn-confirm-modal.reject {
            background-color: #e74c3c;
        }
        
        .modal-footer .btn-confirm-modal.complete {
            background-color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Admin Dashboard</h2>
        <div>
            <a href="admin_dashboard.php">หน้าหลักแอดมิน</a>
            <a href="admin_bookings.php" style="font-weight: 700;">รายการจองทั้งหมด</a> 
            <a href="sports_fields.php">จัดการสนามกีฬา</a>
            <a href="admin_profile.php">ข้อมูลส่วนตัว</a>
            <a href="logout.php">ออกจากระบบ (<?= htmlspecialchars($admin_name) ?>)</a>
        </div>
    </div>

    <div class="container">
        <h1>รายการจองสนามกีฬาทั้งหมด</h1>

        <?php if ($success_message): ?>
            <div class="alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if (mysqli_num_rows($result_bookings) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>สถานะ</th>
                        <th>ผู้จอง</th>
                        <th>สนามที่จอง</th>
                        <th>ยอดรวม</th>
                        <th>วันที่จอง</th>
                        <th>หลักฐาน</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result_bookings)): 
                        $booking_id = htmlspecialchars($row['booking_id']);
                    ?>
                        <tr>
                            <td>#<?= $booking_id ?></td>
                            <td><?= getStatusBadge($row['status']) ?></td> 
                            <td><?= htmlspecialchars($row['member_first_name'] . ' ' . $row['member_last_name']) ?></td>
                            <td><?= htmlspecialchars($row['field_names']) ?></td>
                            <td><?= number_format($row['total_price'], 2) ?> บาท</td>
                            <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                            
                            <td>
                                <?php if ($row['last_slip_path']): ?>
                                    <span 
                                        class="slip-link"
                                        onclick="showSlipModal('<?= htmlspecialchars($row['last_slip_path']) ?>')"
                                    >
                                        📄 ตรวจสอบสลิป
                                    </span>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">ไม่มีหลักฐาน</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="action-cell">
                                <?php if ($row['status'] == 'PAID_PENDING_REVIEW'): ?>
                                    <button 
                                        type="button" 
                                        class="btn btn-confirm" 
                                        onclick="showConfirmModal('confirm_payment', '<?= $booking_id ?>', 'ยืนยันการชำระเงินสำหรับ #<?= $booking_id ?> หรือไม่?')"
                                    >
                                        ✅ ยืนยันชำระเงิน
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-reject" 
                                        onclick="showConfirmModal('reject_payment', '<?= $booking_id ?>', 'ปฏิเสธการชำระเงินสำหรับ #<?= $booking_id ?> และเปลี่ยนสถานะกลับไปรอชำระเงินหรือไม่?')"
                                    >
                                        ❌ ปฏิเสธ
                                    </button>
                                <?php elseif ($row['status'] == 'PENDING_PAYMENT'): ?>
                                    <span class="badge badge-warning" style="color: #343a40;">รอสมาชิกส่งหลักฐาน</span>
                                <?php elseif ($row['status'] == 'PAID_CONFIRMED' || $row['status'] == 'CONFIRMED'): ?>
                                <?php else: // CANCELLED_BY_MEMBER, CANCELLED_TIMEOUT, COMPLETED ?>
                                    <span class="badge badge-secondary" style="background-color: #6c757d;">ไม่มีการดำเนินการ</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?> 
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #6c757d; padding: 20px; background: #fff; border-radius: 8px;">ไม่มีรายการจองใด ๆ ในระบบ</p>
        <?php endif; ?>
    </div>
    
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>ยืนยันการดำเนินการ</h3>
            <p id="modalMessage"></p>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeModal('confirmModal')">ยกเลิก</button>
                <a href="#" id="confirmButton" class="btn btn-confirm-modal">ยืนยัน</a>
            </div>
        </div>
    </div>
    <div id="slipModal" class="modal">
        <div class="modal-content">
            <h3>หลักฐานการชำระเงิน</h3>
            <img id="slipImage" src="" alt="หลักฐานการชำระเงิน" onerror="this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22100%22%20height%3D%22100%22%20viewBox%3D%220%200%20100%20100%22%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22%23ccc%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20font-size%3D%2212%22%20fill%3D%22%23666%22%20text-anchor%3D%22middle%22%20dy%3D%22.3em%22%3E%E0%B9%84%E0%B8%A1%E0%B9%88%E0%B8%9E%E0%B8%9A%E0%B8%AA%E0%B8%A5%E0%B8%B4%E0%B8%9B%3C%2Ftext%3E%3C%2Fsvg%3E'; this.alt='ไม่พบรูปภาพสลิป';">
            <div style="text-align: right; margin-top: 15px;">
                   <button type="button" class="btn btn-cancel" onclick="closeModal('slipModal')">ปิด</button>
            </div>
        </div>
    </div>
    <script>
        // ------------------- JAVASCRIPT สำหรับ Modal -------------------
        const confirmModal = document.getElementById('confirmModal');
        const modalMessage = document.getElementById('modalMessage');
        const confirmButton = document.getElementById('confirmButton');
        const slipModal = document.getElementById('slipModal');
        const slipImage = document.getElementById('slipImage');

        // ฟังก์ชันสำหรับแสดง Modal ยืนยันการดำเนินการ
        function showConfirmModal(action, bookingId, message) {
            modalMessage.textContent = message;
            
            const url = `admin_bookings.php?action=${action}&booking_id=${bookingId}`;
            confirmButton.href = url;
            
            confirmButton.classList.remove('confirm', 'reject', 'complete'); 
            
            if (action === 'set_complete') {
                 confirmButton.classList.add('complete');
            } else if (action === 'confirm_payment') {
                confirmButton.classList.add('confirm');
            } else if (action === 'reject_payment') {
                 confirmButton.classList.add('reject');
            }
            
            confirmModal.style.display = 'block'; 
        }
        
        // ฟังก์ชัน: แสดง Modal รูปภาพสลิป
        function showSlipModal(slipPath) {
            // พาธเต็มของรูปภาพ (สมมติว่าสลิปอยู่ใน uploads/slips/)
            const fullPath = `uploads/slips/${slipPath}`; 
            
            // ตั้งค่า src ของแท็ก <img> ใน Modal
            slipImage.src = fullPath;
            slipImage.alt = 'หลักฐานการชำระเงิน: ' + slipPath;
            
            slipModal.style.display = 'block'; // แสดง Modal สลิป
        }


        // ฟังก์ชันสำหรับซ่อน Modal (รองรับหลาย Modal)
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // ปิด Modal เมื่อคลิกนอก Modal
        window.onclick = function(event) {
            if (event.target == confirmModal) {
                closeModal('confirmModal');
            }
            if (event.target == slipModal) {
                closeModal('slipModal');
            }
        }
        // -------------------------------------------------------------
    </script>
</body>
</html>