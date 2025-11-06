<?php
// 🟢 ส่วนสำคัญ: ต้องไม่มีช่องว่างหรือข้อความใดๆ ก่อนบรรทัดนี้
session_start();

// 🟢 กำหนด Timezone เพื่อให้การคำนวณเวลาถูกต้องทั้ง PHP และ MySQL
date_default_timezone_set('Asia/Bangkok'); 

// เชื่อมต่อฐานข้อมูล
include("dpconnect.php"); // สมมติว่าไฟล์นี้มี $conn

// =============================
// 🚫 ตรวจสอบสิทธิ์การเข้าถึง
// =============================
if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$success_message = "";
$error_message = "";
$cancellation_limit_seconds = 5 * 60; // 5 นาที (300 วินาที)

// =============================
// 🔹 1. ยกเลิกอัตโนมัติเมื่อเกิน 5 นาที (PHP Logic)
// =============================
$sql_check_timeout = "SELECT booking_id, created_at FROM bookings 
                      WHERE member_id = '$member_id' AND status = 'PENDING_PAYMENT'";
$result_timeout = mysqli_query($conn, $sql_check_timeout);

$current_time = time(); 
$cancelled_count = 0;

if ($result_timeout) {
    while ($row = mysqli_fetch_assoc($result_timeout)) {
        $created_at_ts = strtotime($row['created_at']); // แปลงเป็น PHP Timestamp
        
        $time_elapsed_seconds = $current_time - $created_at_ts;
        
        if ($time_elapsed_seconds > $cancellation_limit_seconds) {
            $booking_id_to_timeout = mysqli_real_escape_string($conn, $row['booking_id']);
            
            // อัปเดตสถานะเป็น CANCELLED_TIMEOUT
            $sql_update_timeout = "UPDATE bookings SET status = 'CANCELLED_TIMEOUT', updated_at = NOW() 
                                 WHERE booking_id = '$booking_id_to_timeout' AND status = 'PENDING_PAYMENT'";
            if (mysqli_query($conn, $sql_update_timeout)) {
                $cancelled_count++;
            }
        }
    }
}

// 🚩 หากมีการยกเลิกอัตโนมัติ จะแจ้งเตือนทันที
if ($cancelled_count > 0) {
    $_SESSION['message'] = "⚠️ มีรายการจองจำนวน $cancelled_count รายการ ถูกยกเลิกอัตโนมัติ เนื่องจากเลยกำหนดเวลาชำระเงิน 5 นาที";
    $_SESSION['message_type'] = 'error';
    header("Location: my_bookings.php");
    exit();
}

// =============================
// 🔹 2. ฟังก์ชันแสดง Badge สถานะ
// =============================
function getStatusBadge($status) {
    $color = 'secondary';
    $text = $status;
    switch ($status) {
        case 'PENDING_PAYMENT': $color = 'warning'; $text = 'รอชำระเงิน'; break;
        case 'PAID_PENDING_REVIEW': $color = 'info'; $text = 'รอตรวจสอบสลิป'; break;
        case 'PAID_CONFIRMED': $color = 'success'; $text = 'จองสำเร็จ/ชำระเงินสำเร็จ'; break;
        case 'CANCELLED_BY_MEMBER': $color = 'danger'; $text = 'ยกเลิกโดยสมาชิก'; break;
        case 'CANCELLED_TIMEOUT': $color = 'danger'; $text = 'ยกเลิกโดยระบบ (หมดเวลา)'; break;
    }
    return "<span class='badge badge-{$color}'>{$text}</span>";
}

// =============================
// 🔹 3. ยกเลิกด้วยตนเอง (Member Action)
// =============================
if (isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['booking_id'])) {
    $booking_id_to_cancel = mysqli_real_escape_string($conn, $_GET['booking_id']);
    
    $sql_check = "SELECT status, created_at FROM bookings WHERE booking_id = '$booking_id_to_cancel' AND member_id = '$member_id'";
    $result_check = mysqli_query($conn, $sql_check);

    if ($result_check && mysqli_num_rows($result_check) > 0) {
        $booking_data = mysqli_fetch_assoc($result_check);
        $created_at_ts = strtotime($booking_data['created_at']);
        $current_ts = time();
        $time_elapsed_seconds = $current_ts - $created_at_ts;

        // เงื่อนไขการยกเลิก: ต้องเป็น PENDING_PAYMENT และยังไม่เกิน 5 นาที
        if ($booking_data['status'] == 'PENDING_PAYMENT' && $time_elapsed_seconds < $cancellation_limit_seconds) {
            
            // 🎯 กำหนดสถานะเป็น 'CANCELLED_BY_MEMBER'
            $sql_update = "UPDATE bookings SET status = 'CANCELLED_BY_MEMBER', updated_at = NOW() WHERE booking_id = '$booking_id_to_cancel'";
            
            if (mysqli_query($conn, $sql_update)) {
                // 🟢 สำเร็จ: ตั้งค่าข้อความแจ้งเตือน
                $_SESSION['message'] = "✅ การจองรหัส #$booking_id_to_cancel ถูกยกเลิกเรียบร้อยแล้ว";
                $_SESSION['message_type'] = 'success';
            } else {
                // 🔴 ล้มเหลว: แสดงข้อผิดพลาด SQL เพื่อ Debug
                $_SESSION['message'] = "❌ ไม่สามารถยกเลิกการจองได้: " . mysqli_error($conn) . " | SQL: " . $sql_update; 
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = "❌ การจองรหัส #$booking_id_to_cancel ไม่สามารถยกเลิกได้ เนื่องจากเลยกำหนดเวลา 5 นาที หรือสถานะไม่ถูกต้อง";
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = "❌ ไม่พบรายการจองที่คุณต้องการยกเลิก";
        $_SESSION['message_type'] = 'error';
    }

    // 🎯 Redirect เพื่อให้แสดงผลสถานะที่อัปเดตและข้อความแจ้งเตือน
    header("Location: my_bookings.php");
    exit();
}

// =============================
// 🔹 4. ดึงและแสดงข้อความหลัง Redirect
// =============================
if (isset($_SESSION['message'])) {
    if ($_SESSION['message_type'] == 'success') $success_message = $_SESSION['message'];
    else $error_message = $_SESSION['message'];
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// =============================
// 🔹 5. ดึงรายการจองของสมาชิกทั้งหมด
// =============================
$sql_bookings = "SELECT 
                        b.booking_id, 
                        b.booking_date, 
                        b.total_price, 
                        b.status, 
                        b.created_at, 
                        GROUP_CONCAT(DISTINCT sf.field_name ORDER BY sf.field_name SEPARATOR ', ') AS fields
                    FROM bookings b
                    JOIN booking_items bi ON b.booking_id = bi.booking_id
                    JOIN sports_fields sf ON bi.field_code = sf.field_id
                    WHERE b.member_id = '$member_id'
                    GROUP BY b.booking_id
                    ORDER BY b.booking_date DESC, b.created_at DESC";
$result_bookings = mysqli_query($conn, $sql_bookings);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายการจองของฉัน - Stadium booking</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap');
body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
.navbar { background: #4285f4; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
.navbar h2 { margin: 0; }
.navbar a { color: #fff; margin-left: 20px; text-decoration: none; }
.container { padding: 30px; max-width: 1200px; margin: auto; }
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
th { background-color: #f8f9fa; }
.badge { padding: 5px 10px; border-radius: 5px; font-size: 0.85em; color: #fff; display: inline-block; text-align: center;}
.badge-warning { background-color: #ffc107; color: #212529; }
.badge-success { background-color: #28a745; }
.badge-danger { background-color: #dc3545; }
.badge-info { background-color: #17a2b8; }
.badge-secondary { background-color: #6c757d; }
.message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
.message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.action-cell { 
    display: flex; 
    flex-direction: column; 
    gap: 5px;
    min-width: 150px; 
}
.action-cell a {
    white-space: nowrap; 
}
/* สไตล์สำหรับ Modal (Popup) */
#cancellation-modal {
    display: none; /* เริ่มต้นซ่อนไว้ */
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0,0,0,0.6); 
    z-index: 1000; 
    justify-content: center; 
    align-items: center;
}
#cancellation-modal .modal-content {
    background: #fff; 
    padding: 30px; 
    border-radius: 10px; 
    box-shadow: 0 5px 15px rgba(0,0,0,0.3); 
    max-width: 400px; 
    width: 90%; 
    text-align: center;
}
#cancellation-modal button {
    border: none; 
    cursor: pointer; 
    padding: 10px 20px; 
    font-weight: bold;
    font-size: 1em; /* ปรับขนาดปุ่มให้ดูดีขึ้น */
}
</style>
</head>
<body>
<div class="navbar">
    <h2>Stadium booking</h2>
    <div>
        <a href="index.php">หน้าหลัก</a>
        <a href="book_field.php">จองสนามกีฬา</a>
        <a href="my_bookings.php">รายการจองของฉัน</a>
        <a href="profile.php">ข้อมูลส่วนตัว</a>
        <a href="logout.php">ออกจากระบบ</a>
    </div>
</div>

<div class="container">
    <h3>📄 รายการจองของฉัน</h3>

    <?php if ($success_message): ?><div class="message success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="message error"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <?php if ($result_bookings && mysqli_num_rows($result_bookings) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>รหัสจอง</th>
                <th>วันที่จอง</th>
                <th>สนามกีฬา</th>
                <th>รวมราคา</th>
                <th>สถานะ</th>
                <th>การดำเนินการ</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = mysqli_fetch_assoc($result_bookings)): 
            $created_at_ts = strtotime($row['created_at']); // PHP Timestamp
            $current_ts = time();
            $time_elapsed_seconds = $current_ts - $created_at_ts;
            
            // ตรวจสอบสถานะการยกเลิกโดยใช้เงื่อนไขจาก PHP
            $is_pending_payment_and_in_time = ($row['status'] == 'PENDING_PAYMENT' && $time_elapsed_seconds < $cancellation_limit_seconds);
            $is_pending_payment_but_timeout = ($row['status'] == 'PENDING_PAYMENT' && $time_elapsed_seconds >= $cancellation_limit_seconds);
        ?>
            <tr>
                <td><?= htmlspecialchars($row['booking_id']) ?></td>
                <td><?= date('d/m/Y', strtotime($row['booking_date'])) ?></td>
                <td><?= htmlspecialchars($row['fields']) ?></td>
                <td><?= number_format($row['total_price'], 2) ?> บาท</td>
                <td><?= getStatusBadge($row['status']) ?></td>
                <td class="action-cell">
                    <?php if ($is_pending_payment_and_in_time): ?>
                        <a href="#" 
                            class="badge badge-danger open-cancel-modal" 
                            id="cancel-row-<?= $row['booking_id'] ?>"
                            data-booking-id="<?= $row['booking_id'] ?>"
                            data-created-at="<?= $created_at_ts ?>" style="text-decoration:none;">
                            ยกเลิก (กำลังนับถอยหลัง...)
                        </a>
                        <a href="payment.php?booking_id=<?= $row['booking_id'] ?>" 
                            class="badge badge-success" 
                            style="text-decoration:none;">ชำระเงิน</a>
                    <?php elseif ($is_pending_payment_but_timeout): ?>
                        <span class="badge badge-secondary" style="pointer-events: none;">เลยกำหนดเวลายกเลิก</span>
                        <a href="payment.php?booking_id=<?= $row['booking_id'] ?>" 
                            class="badge badge-warning" 
                            style="text-decoration:none;">ชำระเงิน</a>
                    <?php elseif ($row['status'] == 'PAID_PENDING_REVIEW'): ?>
                        <span class="badge badge-info" style="pointer-events: none;">รอเจ้าหน้าที่ตรวจสอบ</span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align:center; padding: 20px; background: #fff; border-radius: 8px;">คุณยังไม่มีรายการจองใด ๆ</p>
    <?php endif; ?>
</div>

<div id="cancellation-modal">
    <div class="modal-content">
        <h4 style="margin-top: 0; color: #dc3545;">⚠️ ยืนยันการยกเลิกการจอง</h4>
        <p>คุณแน่ใจหรือไม่ว่าต้องการยกเลิกการจองรหัส <strong id="modal-booking-id">#...</strong>?</p>
        <div style="display: flex; justify-content: space-around; margin-top: 20px;">
            <button id="confirm-cancel-btn" class="badge badge-danger">ใช่, ยกเลิก</button>
            <button id="close-modal-btn" class="badge badge-secondary">ไม่, เก็บไว้</button>
        </div>
    </div>
</div>

<script>
/**
 * ฟังก์ชันสำหรับเริ่มการนับถอยหลังที่ปุ่ม "ยกเลิก"
 * @param {string} bookingId - รหัสการจอง
 * @param {number} createdTimestamp - Timestamp ที่สร้างรายการจอง (วินาที)
 */
function startCountdown(bookingId, createdTimestamp) {
    const rowId = `cancel-row-${bookingId}`;
    const button = document.getElementById(rowId);
    if (!button) return;

    const limitSeconds = 5 * 60; // 5 นาที (300 วินาที)
    const createdTimeMs = createdTimestamp * 1000;

    function updateCountdown() {
        const now = new Date().getTime(); // เวลาปัจจุบันใน milliseconds (อ้างอิงเบราว์เซอร์)
        
        // คำนวณเวลาที่ผ่านไปในหน่วยวินาที
        const elapsedSeconds = Math.floor((now - createdTimeMs) / 1000); 
        const timeLeftSeconds = limitSeconds - elapsedSeconds;

        if (timeLeftSeconds <= 0) {
            // เมื่อหมดเวลา: ปิดการทำงานปุ่มและเปลี่ยนข้อความ
            button.textContent = 'หมดเวลายกเลิก';
            button.href = '#';
            button.onclick = null;
            button.style.pointerEvents = 'none'; // ปิดการคลิก
            button.style.opacity = '0.7';
            // 🚩 หากหมดเวลาแล้ว ควรเปลี่ยน class badge ด้วย
            button.classList.remove('badge-danger');
            button.classList.add('badge-secondary');

            // 🎯 เนื่องจากหมดเวลาแล้ว ต้องเอา class ที่ใช้เปิด modal ออกด้วย
            button.classList.remove('open-cancel-modal'); 
            return;
        }

        const minutes = Math.floor(timeLeftSeconds / 60);
        const seconds = timeLeftSeconds % 60;
        const timeString = `${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;
        button.textContent = `ยกเลิก (เหลือ ${timeString})`;

        // เรียกตัวเองซ้ำทุก 1 วินาที
        setTimeout(updateCountdown, 1000);
    }

    updateCountdown();
}

document.addEventListener('DOMContentLoaded', () => {
    // ------------------------------------
    // 1. จัดการการนับถอยหลัง
    // ------------------------------------
    const countdownElements = document.querySelectorAll('[data-created-at]');
    const limitSeconds = 5 * 60;
    
    countdownElements.forEach(element => {
        const bookingId = element.getAttribute('data-booking-id');
        const createdTs = parseInt(element.getAttribute('data-created-at'));
        
        // ดึงเวลาปัจจุบันในหน่วยวินาที (อ้างอิงเบราว์เซอร์)
        const now = new Date().getTime() / 1000; 
        
        // ตรวจสอบว่ารายการจองนี้ยังอยู่ในช่วง 5 นาทีหรือไม่ ก่อนเริ่มนับถอยหลัง
        if ((now - createdTs) < limitSeconds) {
            startCountdown(bookingId, createdTs);
        } else {
            // จัดการเมื่อเวลาหมด (กรณีที่ PHP ผ่านไปนานกว่า 5 นาทีแล้ว)
            element.textContent = 'หมดเวลายกเลิก';
            element.href = '#';
            element.onclick = null;
            element.style.pointerEvents = 'none';
            element.style.opacity = '0.7';
            element.classList.remove('badge-danger', 'open-cancel-modal'); // ลบ class ที่ใช้เปิด modal
            element.classList.add('badge-secondary');
        }
    });

    // ------------------------------------
    // 2. จัดการ Modal (Popup)
    // ------------------------------------
    const modal = document.getElementById('cancellation-modal');
    const confirmBtn = document.getElementById('confirm-cancel-btn');
    const closeBtn = document.getElementById('close-modal-btn');
    const modalBookingIdDisplay = document.getElementById('modal-booking-id');
    
    // 🟢 จัดการการเปิด Modal เมื่อคลิกปุ่ม "ยกเลิก"
    document.querySelectorAll('.open-cancel-modal').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault(); // ป้องกันไม่ให้ลิงก์ทำงานทันที
            const bookingId = this.getAttribute('data-booking-id');
            const createdTs = this.getAttribute('data-created-at');
            
            const now = new Date().getTime() / 1000;
            
            // ตรวจสอบเงื่อนไขยกเลิกอีกครั้ง (เผื่อกรณีเวลาใกล้หมดมาก)
            if ((now - createdTs) < limitSeconds) {
                // กำหนด URL และข้อความใน Modal
                modalBookingIdDisplay.textContent = `#${bookingId}`;
                confirmBtn.setAttribute('data-booking-id', bookingId);
                
                // แสดง Modal
                modal.style.display = 'flex'; 
            } else {
                 // ถ้าหมดเวลาแล้วขณะที่พยายามคลิก ให้โหลดหน้าซ้ำเพื่อให้ PHP จัดการ
                 window.location.reload(); 
            }
        });
    });

    // 🟢 จัดการการปิด Modal
    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    // ปิดเมื่อคลิกนอก Modal
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    // 🟢 จัดการการยืนยันการยกเลิก (เมื่อผู้ใช้กด "ใช่, ยกเลิก")
    confirmBtn.addEventListener('click', function() {
        const bookingId = this.getAttribute('data-booking-id');
        // Redirect ไปยัง URL สำหรับการยกเลิกใน PHP
        window.location.href = `my_bookings.php?action=cancel&booking_id=${bookingId}`;
    });
});
</script>
</body>
</html>