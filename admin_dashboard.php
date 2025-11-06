<?php
session_start();
include("dpconnect.php"); 

// *** 🚩 1. การตรวจสอบสิทธิ์ Admin ***
// ถ้าไม่มี session ของ admin ให้ redirect ไปที่หน้า login.php
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php"); 
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? "Admin";
$payments_pending_count = 0;
$total_members = 0;
$total_bookings = 0;

// 2. ดึงข้อมูลสถิติที่สำคัญสำหรับ Dashboard
try {
    // 2.1 นับรายการชำระเงินที่รอการตรวจสอบ (สถานะ PAID_PENDING_REVIEW)
    // หมายเหตุ: ใช้ payments.status = 'PENDING_REVIEW' หากตาราง payments มีคอลัมน์ status
    // หากใช้ตาราง bookings: status = 'PAID_PENDING_REVIEW'
    $sql_pending_payments = "SELECT COUNT(payment_id) AS total FROM payments WHERE status = 'PENDING_REVIEW'";
    $result_pending = mysqli_query($conn, $sql_pending_payments);
    if ($result_pending) {
        $payments_pending_count = mysqli_fetch_assoc($result_pending)['total'];
    }

    // 2.2 นับจำนวนสมาชิกทั้งหมด
    $sql_members = "SELECT COUNT(member_id) AS total FROM members";
    $result_members = mysqli_query($conn, $sql_members);
    if ($result_members) {
        $total_members = mysqli_fetch_assoc($result_members)['total'];
    }
    
    // 2.3 นับจำนวนการจองทั้งหมด (ที่สถานะไม่ใช่ CANCELLED)
    $sql_bookings = "SELECT COUNT(booking_id) AS total FROM bookings WHERE status NOT LIKE 'CANCELLED%'";
    $result_bookings = mysqli_query($conn, $sql_bookings);
    if ($result_bookings) {
        $total_bookings = mysqli_fetch_assoc($result_bookings)['total'];
    }

} catch (Exception $e) {
    // ในกรณีที่เกิดข้อผิดพลาดในการดึงข้อมูลสถิติ
    error_log("Dashboard Stats Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .navbar { background: #343a40; color: #fff; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; font-size: 24px; }
        .navbar a { color: #fff; margin-left: 20px; text-decoration: none; font-weight: 500; opacity: 0.9; transition: opacity 0.3s; }
        .navbar a:hover { opacity: 1; }
        .container { padding: 30px; max-width: 1200px; margin: auto; }
        h1 { color: #333; margin-bottom: 30px; }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .stat-card {
            background-color: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
            border-left: 5px solid #007bff;
        }
        .stat-card h3 { margin-top: 0; font-size: 1.1em; color: #6c757d; }
        .stat-card .number { font-size: 3em; font-weight: 700; color: #333; line-height: 1; }
        
        /* สไตล์พิเศษสำหรับ Pending Payments */
        .stat-pending {
            border-left-color: #ffc107;
            background-color: #fff8e1;
        }
        .stat-pending .number { color: #ffc107; }

        .tools-section h2 { border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; color: #333; }
        .tool-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .tool-link-card {
            display: block;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-decoration: none;
            color: #343a40;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
            border: 1px solid #dee2e6;
        }
        .tool-link-card:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
            border-color: #adb5bd;
        }
        .tool-link-card span { font-size: 1.2em; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Admin Panel</h2>
        <div>
            <a href="index.php">หน้าหลักเว็บไซต์</a>
            <a href="admin_bookings.php" style="font-weight: 700;">รายการจองทั้งหมด(<?= $payments_pending_count ?>)</a>
            <a href="sports_fields.php">จัดการสนามกีฬา</a>
            <a href="admin_profile.php">ข้อมูลส่วนตัว</a>
            <a href="admin_logout.php">ออกจากระบบ</a>
        </div>
    </div>

    <div class="container">
        <h1>👋 ยินดีต้อนรับ, <?= htmlspecialchars($admin_name) ?>!</h1>

        <div class="stat-grid">
            <div class="stat-card stat-pending">
                <h3>รายการชำระเงินที่รอตรวจสอบ</h3>
                <div class="number"><?= number_format($payments_pending_count) ?></div>
                <p style="color: #6c757d;">(คลิกที่เมนูเพื่อดำเนินการ)</p>
            </div>
            
            <div class="stat-card">
                <h3>จำนวนสมาชิกทั้งหมด</h3>
                <div class="number"><?= number_format($total_members) ?></div>
            </div>
            
            <div class="stat-card">
                <h3>รายการจองทั้งหมด</h3>
                <div class="number"><?= number_format($total_bookings) ?></div>
            </div>
            
        </div>

        <div class="tools-section">
            <h2>🛠️ เครื่องมือจัดการ</h2>
            <div class="tool-links">
                
                <a href="admin_payments.php" class="tool-link-card">
                    <span>💳</span> ตรวจสอบและอนุมัติการชำระเงิน
                </a>

                <a href="sports_fields.php" class="tool-link-card">
                    <span>⚽</span> จัดการสนามกีฬาและราคา
                </a>
                
                <a href="index.php" class="tool-link-card">
                    <span>🌐</span> ดูหน้าหลักเว็บไซต์
                </a>
                
            </div>
        </div>
    </div>
</body>
</html>