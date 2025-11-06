<?php
session_start();
include("dpconnect.php"); 

// *** 🚩 1. การตรวจสอบสิทธิ์ Admin ***
// ถ้าไม่มี session ของ admin ให้ redirect ไปที่หน้า login.php
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php"); 
    exit();
}
$admin_id = $_SESSION['admin_id'] ?? 0; // ดึง Admin ID สำหรับใช้บันทึกในฐานข้อมูล

// 2. ดึงจำนวนรายการรอตรวจสอบ เพื่อแสดงใน Navbar
$payments_pending_count = 0;
try {
    $sql_pending_payments_count = "SELECT COUNT(payment_id) AS total FROM payments WHERE status = 'PENDING_REVIEW'";
    $result_pending_count = mysqli_query($conn, $sql_pending_payments_count);
    if ($result_pending_count) {
        $payments_pending_count = mysqli_fetch_assoc($result_pending_count)['total'];
    }
} catch (Exception $e) {
    // 
}


// 3. การจัดการคำสั่ง Approve/Reject
if (isset($_POST['action']) && isset($_POST['payment_id'])) {
    $payment_id = mysqli_real_escape_string($conn, $_POST['payment_id']);
    $action = $_POST['action'];
    $new_payment_status = '';
    $new_booking_status = '';

    if ($action == 'approve') {
        $new_payment_status = 'REVIEWED';
        $new_booking_status = 'PAID_CONFIRMED';
    } elseif ($action == 'reject') {
        $new_payment_status = 'REJECTED';
        $new_booking_status = 'CANCELLED_REJECTED'; 
    }

    if ($new_payment_status) {
        // 3.1 ดึง booking_id ที่เกี่ยวข้องก่อน
        $booking_id_result = mysqli_query($conn, "SELECT booking_id FROM payments WHERE payment_id = '$payment_id'");
        $booking_id_row = mysqli_fetch_assoc($booking_id_result);
        $booking_id = $booking_id_row['booking_id'];
        
        // 3.2 อัปเดตสถานะการชำระเงิน (payments)
        $sql_update_payment = "UPDATE payments SET status = '$new_payment_status', reviewed_by = '$admin_id', updated_at = NOW() WHERE payment_id = '$payment_id'";
        mysqli_query($conn, $sql_update_payment) or die(mysqli_error($conn));

        // 3.3 อัปเดตสถานะการจอง (bookings)
        $sql_update_booking = "UPDATE bookings SET status = '$new_booking_status' WHERE booking_id = '$booking_id'";
        mysqli_query($conn, $sql_update_booking) or die(mysqli_error($conn));

        // 3.4 ถ้าถูกปฏิเสธ (REJECTED) ต้องคืนสถานะของสนามให้ว่าง (ในระบบนี้การตั้งสถานะ booking เป็น CANCELLED_REJECTED ถือว่าเพียงพอ)
    }
    
    // Redirect กลับไปหน้าเดิมเพื่อป้องกันการ submit ซ้ำ
    header("Location: admin_payments.php");
    exit();
}

// 4. ดึงรายการชำระเงินที่รอตรวจสอบ
$sql_payments = "
    SELECT 
        p.payment_id,
        p.booking_id,
        p.amount,
        p.slip_path,
        p.transfer_name,
        p.payment_date,
        p.payment_time,
        p.created_at,
        b.total_price,  /* 🌟 FIX: เปลี่ยนจาก b.total_amount เป็น b.total_price */
        m.first_name,
        m.last_name,
        m.email
    FROM 
        payments p
    INNER JOIN 
        bookings b ON p.booking_id = b.booking_id
    INNER JOIN 
        members m ON b.member_id = m.member_id
    WHERE 
        p.status = 'PENDING_REVIEW'
    ORDER BY 
        p.created_at ASC
";
$result_payments = mysqli_query($conn, $sql_payments);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin - ตรวจสอบชำระเงิน</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .navbar { background: #343a40; color: #fff; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; font-size: 24px; }
        .navbar a { color: #fff; margin-left: 20px; text-decoration: none; font-weight: 500; opacity: 0.9; transition: opacity 0.3s; }
        .navbar a:hover { opacity: 1; }
        .container { padding: 30px; max-width: 1300px; margin: auto; }
        h1 { color: #333; margin-bottom: 30px; }
        
        .table-container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        table thead tr {
            background-color: #4285f4;
            color: #fff;
        }
        table th, table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            white-space: nowrap; 
        }
        table tbody tr:hover { background-color: #f5f8fa; }
        table td { vertical-align: middle; }
        
        .action-form { display: flex; gap: 5px; }
        .approve-btn { 
            background-color: #28a745; 
            color: white; 
            border: none; 
            padding: 8px 12px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
        }
        .reject-btn { 
            background-color: #dc3545; 
            color: white; 
            border: none; 
            padding: 8px 12px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
        }
        .slip-img { 
            max-width: 150px; 
            max-height: 150px; 
            object-fit: contain;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .slip-img:hover {
            transform: scale(1.05);
        }
        .text-pending { color: #ffc107; font-weight: 700; }
        .text-danger { color: #dc3545; }
        .text-success { color: #28a745; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Admin Panel - ตรวจสอบชำระเงิน</h2>
        <div>
            <a href="admin_dashboard.php">หน้าหลักแอดมิน</a>
            <a href="admin_bookings.php" style="font-weight: 700;">รายการจองทั้งหมด</a>
            <a href="sports_fields.php">จัดการสนามกีฬา</a>
            <a href="admin_profile.php">ข้อมูลส่วนตัว</a>
            <a href="admin_logout.php">ออกจากระบบ</a>
        </div>
    </div>

    <div class="container">
        <h1>รายการชำระเงินที่รอการตรวจสอบ (<?= $payments_pending_count ?> รายการ)</h1>
        
        <?php if (mysqli_num_rows($result_payments) > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>เลขที่ใบเสร็จ</th>
                        <th>เลขที่จอง</th>
                        <th>ผู้จอง</th>
                        <th>อีเมล</th>
                        <th>ยอดชำระจริง</th>
                        <th>จำนวนเงินที่ต้องชำระ</th>
                        <th>สลิป</th>
                        <th>ชื่อผู้โอน</th>
                        <th>วันที่/เวลาโอน</th>
                        <th>วันที่ส่งสลิป</th>
                        <th>การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result_payments)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['payment_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['booking_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                        <td class="text-pending"><?= number_format($row['amount'] ?? 0, 2) ?> บาท</td>
                        <td><?= number_format($row['total_price'] ?? 0, 2) ?> บาท</td> <td>
                            <?php if (!empty($row['slip_path'] ?? '')): ?>
                                <a href="uploads/slips/<?= htmlspecialchars($row['slip_path'] ?? '') ?>" target="_blank">
                                    <img src="uploads/slips/<?= htmlspecialchars($row['slip_path'] ?? '') ?>" alt="สลิป" class="slip-img">
                                </a>
                            <?php else: ?>
                                ไม่มีสลิป
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['transfer_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars(($row['payment_date'] ?? '') . ' ' . ($row['payment_time'] ?? '')) ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($row['created_at'] ?? '')) ?></td>
                        <td>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="payment_id" value="<?= htmlspecialchars($row['payment_id'] ?? '') ?>">
                                <button type="submit" name="action" value="approve" class="approve-btn" onclick="return confirm('ยืนยันอนุมัติการชำระเงินนี้หรือไม่?')">อนุมัติ</button>
                                <button type="submit" name="action" value="reject" class="reject-btn" onclick="return confirm('ยืนยันปฏิเสธการชำระเงินนี้หรือไม่? การจองจะถูกยกเลิก')">ปฏิเสธ</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div style="background-color: #e9ecef; padding: 20px; border-radius: 8px; text-align: center; color: #6c757d;">
                <p>✅ ไม่มีรายการชำระเงินที่รอการตรวจสอบในขณะนี้</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>