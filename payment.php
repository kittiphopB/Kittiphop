<?php
session_start();
// ตรวจสอบให้แน่ใจว่าไฟล์นี้เชื่อมต่อฐานข้อมูลได้
include("dpconnect.php"); 

// 1. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$success_message = "";
$error_message = "";
$booking_id = $_GET['booking_id'] ?? null;
$booking_details = null;

// กำหนดเวลานับถอยหลัง (5 นาที = 300 วินาที)
$expiry_seconds = 5 * 60; 

// ตรวจสอบว่ามีการส่ง booking_id มาหรือไม่
if (!$booking_id) {
    $error_message = "❌ ไม่พบรหัสการจอง! กรุณาเข้าสู่ระบบหน้าประวัติการจอง";
    goto render_page; // ข้ามไปแสดงผลหน้าเว็บเลย
}

// 2. ดึงข้อมูลการจอง
$sql_booking = "SELECT b.booking_id, b.total_price, b.status, b.created_at, m.first_name, m.last_name
                FROM bookings b
                JOIN members m ON b.member_id = m.member_id
                WHERE b.booking_id = " . mysqli_real_escape_string($conn, $booking_id) . "
                AND b.member_id = " . mysqli_real_escape_string($conn, $member_id); // ป้องกันผู้ใช้อื่นเข้าถึง
                
$result_booking = mysqli_query($conn, $sql_booking);

if (mysqli_num_rows($result_booking) == 0) {
    $error_message = "❌ ไม่พบการจอง หรือคุณไม่มีสิทธิ์เข้าถึงการจองนี้";
    goto render_page;
}

$booking_details = mysqli_fetch_assoc($result_booking);

// 🚩 โค้ดใหม่สำหรับการจัดการเวลานับถอยหลัง
$payment_expiry_timestamp = 0; 
$countdown_expired = false;

if ($booking_details['status'] == 'PENDING_PAYMENT') {
    // ตรวจสอบและตั้งค่าเวลาหมดอายุใน Session ถ้ายังไม่มี
    if (!isset($_SESSION['payment_expiry'][$booking_id])) {
        // ตั้งค่าเวลาหมดอายุ: เวลาปัจจุบัน + 300 วินาที
        $_SESSION['payment_expiry'][$booking_id] = time() + $expiry_seconds;
    }
    
    $payment_expiry_timestamp = $_SESSION['payment_expiry'][$booking_id];
    
    // ตรวจสอบว่าหมดเวลาแล้วหรือไม่
    if (time() > $payment_expiry_timestamp) {
        $countdown_expired = true;
        // ⚠️ ในสถานการณ์จริง ควรมีการอัปเดตสถานะการจองในฐานข้อมูลเป็น 'CANCELLED_TIMEOUT' ตรงนี้ด้วย
        // ตัวอย่าง: mysqli_query($conn, "UPDATE bookings SET status = 'CANCELLED_TIMEOUT' WHERE booking_id = '$booking_id'");
        
        $error_message = "❌ หมดเวลาในการชำระเงิน (เกิน $expiry_seconds วินาที)! รายการจองอาจถูกยกเลิก กรุณาตรวจสอบสถานะและจองใหม่";
    }
} else {
    // ถ้าสถานะไม่ใช่ PENDING_PAYMENT ให้ลบเวลาหมดอายุใน Session ออก
    if (isset($_SESSION['payment_expiry'][$booking_id])) {
        unset($_SESSION['payment_expiry'][$booking_id]);
    }
}


// ตรวจสอบสถานะการจองที่สามารถชำระเงินได้
if ($booking_details['status'] != 'PENDING_PAYMENT' && $booking_details['status'] != 'PAID_PENDING_REVIEW') {
    // ถ้าสถานะเป็น PAID_CONFIRMED ก็ไม่ต้องทำอะไร
    if ($booking_details['status'] != 'PAID_CONFIRMED') {
         $error_message = "⚠️ การจองนี้อยู่ในสถานะ **{$booking_details['status']}** ไม่สามารถทำรายการชำระเงินได้";
    }
    goto render_page;
}

// 3. จัดการการส่งฟอร์มชำระเงิน
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_payment'])) {
    
    // ตรวจสอบซ้ำอีกครั้งว่าหมดเวลาแล้วหรือไม่ก่อนประมวลผลการชำระเงิน
    if ($countdown_expired) {
        $error_message = "❌ หมดเวลาในการชำระเงินแล้ว ไม่สามารถส่งหลักฐานได้";
        goto render_page;
    }
    
    // ตรวจสอบซ้ำอีกครั้งว่าสถานะยังเป็น PENDING_PAYMENT
    if ($booking_details['status'] != 'PENDING_PAYMENT') {
        $error_message = "❌ การจองนี้ถูกชำระเงินและอยู่ในสถานะรอการตรวจสอบแล้ว";
        goto render_page;
    }
    
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
    $payment_time = mysqli_real_escape_string($conn, $_POST['payment_time']);
    $transfer_name = mysqli_real_escape_string($conn, $_POST['transfer_name']);
    // 🚩 เพิ่ม: รับค่าชื่อธนาคาร
    $transfer_bank = mysqli_real_escape_string($conn, $_POST['transfer_bank']); 
    
    // ตรวจสอบไฟล์ที่อัปโหลด
    if (empty($_FILES['slip_image']['name'])) {
        $error_message = "❌ กรุณาแนบหลักฐานการโอนเงิน (สลิป)";
    } elseif ($payment_amount < $booking_details['total_price']) {
        $error_message = "❌ ยอดชำระเงินไม่ถูกต้อง! ต้องชำระอย่างน้อย " . number_format($booking_details['total_price']) . " บาท";
    } else {
        $target_dir = "uploads/slips/";
        $file_extension = pathinfo($_FILES["slip_image"]["name"], PATHINFO_EXTENSION);
        // สร้างชื่อไฟล์ที่ไม่ซ้ำกัน
        $new_file_name = "slip_" . $booking_id . "_" . time() . "." . $file_extension; 
        $target_file = $target_dir . $new_file_name;
        $uploadOk = 1;
        $imageFileType = strtolower($file_extension);

        // ตรวจสอบว่าเป็นไฟล์ภาพหรือไม่
        $check = @getimagesize($_FILES["slip_image"]["tmp_name"]);
        if($check === false) {
            $error_message = "❌ ไฟล์ที่อัปโหลดไม่ใช่ไฟล์รูปภาพ";
            $uploadOk = 0;
        }

        // ตรวจสอบขนาดไฟล์ (เช่น 5MB)
        if ($_FILES["slip_image"]["size"] > 5000000) {
            $error_message = "❌ ขนาดไฟล์ใหญ่เกินไป (สูงสุด 5MB)";
            $uploadOk = 0;
        }

        // อนุญาตเฉพาะบางนามสกุลไฟล์
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
            $error_message = "❌ อนุญาตเฉพาะไฟล์ JPG, JPEG, และ PNG เท่านั้น";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
             // ตรวจสอบและสร้างโฟลเดอร์ถ้าไม่มี
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            if (move_uploaded_file($_FILES["slip_image"]["tmp_name"], $target_file)) {
                
                // ✅ เพิ่มโค้ด: สร้าง Payment ID ที่ไม่ซ้ำกัน ก่อนเริ่ม Transaction
                $payment_id = uniqid('PAY_', true); 
                
                mysqli_begin_transaction($conn);
                try {
                    // 3.1. บันทึกข้อมูลการชำระเงิน
                    // 🚩 แก้ไข: เพิ่ม payment_id ในคอลัมน์และค่า
                    $sql_payment = "INSERT INTO payments (payment_id, booking_id, amount, payment_date, payment_time, slip_path, transfer_name, transfer_bank, status, created_at)
                                    VALUES ('$payment_id', '$booking_id', '$payment_amount', '$payment_date', '$payment_time', '$new_file_name', '$transfer_name', '$transfer_bank', 'PENDING_REVIEW', NOW())";
                    
                    if (!mysqli_query($conn, $sql_payment)) {
                        // ข้อผิดพลาดที่นี่ควรจะถูกแก้ไขแล้ว แต่ยังคงต้องมีการจัดการข้อผิดพลาด
                        throw new Exception("Error recording payment: " . mysqli_error($conn));
                    }

                    // 3.2. อัปเดตสถานะการจองเป็นรอการตรวจสอบ
                    $sql_update_booking = "UPDATE bookings SET status = 'PAID_PENDING_REVIEW' WHERE booking_id = '$booking_id'";
                    
                    if (!mysqli_query($conn, $sql_update_booking)) {
                         throw new Exception("Error updating booking status: " . mysqli_error($conn));
                    }

                    mysqli_commit($conn);
                    $success_message = "✅ บันทึกหลักฐานการชำระเงินสำเร็จ! สถานะการจองถูกเปลี่ยนเป็น **รอการตรวจสอบ**";
                    $booking_details['status'] = 'PAID_PENDING_REVIEW'; // อัปเดตสถานะบนหน้าเว็บทันที
                    
                    // ลบเวลาหมดอายุใน Session เมื่อชำระเงินสำเร็จ
                    if (isset($_SESSION['payment_expiry'][$booking_id])) {
                         unset($_SESSION['payment_expiry'][$booking_id]);
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    // ลบไฟล์ที่อัปโหลดไปแล้วหากเกิดข้อผิดพลาดในการบันทึกฐานข้อมูล
                    if (file_exists($target_file)) { unlink($target_file); }
                    $error_message = "❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
                }

            } else {
                $error_message = "❌ ขออภัย, เกิดข้อผิดพลาดในการอัปโหลดไฟล์ของคุณ.";
            }
        }
    }
}

render_page:
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ชำระเงิน - Stadium booking</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap');
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .navbar { background: #4285f4; color: #fff; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; font-size: 24px; }
        .navbar a { color: #fff; margin-left: 20px; text-decoration: none; font-weight: 500; opacity: 0.9; transition: opacity 0.3s; }
        .navbar a:hover { opacity: 1; }
        .container { padding: 30px; max-width: 800px; margin: auto; }
        h3 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        .payment-box {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .details-box {
            background: #fff; /* เปลี่ยนเป็นสีขาวเพื่อให้ QR Code โดดเด่น */
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex; /* ใช้ flexbox จัดเรียงรายการและ QR Code */
            gap: 30px;
            align-items: flex-start;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .booking-summary {
            flex-grow: 1;
        }
        .qr-code-section {
            flex-shrink: 0;
            text-align: center;
            /* border-left: 1px solid #ddd; */ /* ลบเส้นแบ่งเพื่อความสะอาด */
            padding-left: 20px;
            background: #f8f9fa; /* พื้นหลังสีเทาอ่อนสำหรับ QR */
            padding: 15px;
            border-radius: 8px;
        }
        .qr-code-section img {
            max-width: 180px; /* กำหนดขนาดภาพ QR Code */
            height: auto;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .details-box p { margin: 5px 0; font-size: 1.1em; }
        
        /* สถานะสี */
        .status-pending-payment { color: #ffc107; font-weight: 700; }
        .status-paid-confirmed { color: #28a745; font-weight: 700; }
        .status-paid-pending-review { color: #007bff; font-weight: 700; }
        .status-cancelled-timeout { color: #dc3545; font-weight: 700; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; color: #555; margin-bottom: 5px; }
        .form-group input[type="text"], 
        .form-group input[type="number"], 
        .form-group input[type="date"], 
        .form-group input[type="time"],
        .form-group input[type="file"],
        .form-group select { /* 🚩 เพิ่ม select */
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .submit-button {
            background: #007bff;
            color: #fff;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: background-color 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        .submit-button:hover { background: #0056b3; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* สไตล์สำหรับตัวนับถอยหลัง */
        #countdown-timer-display {
            font-size: 1.5em;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 15px;
            padding: 10px;
            border: 2px solid #ffc107;
            border-radius: 8px;
            background-color: #fff3cd;
            text-align: center;
        }
        .countdown-expired {
            color: #fff !important;
            background-color: #dc3545 !important;
            border-color: #a71d2a !important;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Stadium booking</h2>
        <div>
            <a href="index.php">หน้าหลัก</a>
            <a href="my_bookings.php">รายการจองของฉัน</a>
            <a href="book_field.php">จองสนามกีฬา</a>
            <a href="logout.php">ออกจากระบบ</a>
        </div>
    </div>

    <div class="container">
        <h3>💰 ยืนยันการชำระเงิน</h3>

        <?php if ($success_message): ?>
            <div class="message success"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if ($booking_details): ?>
            <div class="details-box">
                
                <div class="booking-summary">
                    <h4>สรุปรายการจอง #<?= htmlspecialchars($booking_details['booking_id']) ?></h4>
                    <p><strong>ชื่อผู้จอง:</strong> <?= htmlspecialchars($booking_details['first_name'] . ' ' . $booking_details['last_name']) ?></p>
                    <p><strong>ยอดรวมที่ต้องชำระ:</strong> <span style="font-size: 1.2em; color: #dc3545;"><?= number_format($booking_details['total_price'], 2) ?> บาท</span></p>
                    <p><strong>สถานะปัจจุบัน:</strong> 
                        <span class="status-<?= strtolower(str_replace('_', '-', $booking_details['status'])) ?>">
                            <?= htmlspecialchars(str_replace('_', ' ', $booking_details['status'])) ?>
                        </span>
                    </p>
                    <p style="color: #6c757d; font-size: 0.9em; margin-top: 10px;">*กรุณาโอนเงินเข้าบัญชี XXX-X-XXXXX-X (ชื่อบัญชี: สนามกีฬา)</p>
                </div>
                
                <div class="qr-code-section">
                    <p style="font-weight: 600; margin-bottom: 5px; color: #007bff;">สแกนเพื่อชำระเงิน</p>
                    <img src="images/qr.jpeg" alt="QR Code สำหรับชำระเงิน">
                </div>
                
            </div>

            <?php if ($booking_details['status'] == 'PENDING_PAYMENT' && !$countdown_expired): // แสดงตัวนับถอยหลังและฟอร์มเฉพาะเมื่อสถานะรอชำระเงินและยังไม่หมดเวลา?>
                
                <div id="countdown-timer-display">
                    เหลือเวลาในการชำระเงิน: <span id="time-left">05:00</span>
                </div>
                
                <div class="payment-box">
                    <h4>กรอกข้อมูลการชำระเงิน</h4>
                    <form id="payment-form" method="POST" action="payment.php?booking_id=<?= htmlspecialchars($booking_id) ?>" enctype="multipart/form-data">
                        <input type="hidden" name="submit_payment" value="1">

                        <div class="form-group">
                            <label for="payment_amount">ยอดเงินที่โอน (บาท)</label>
                            <input type="number" id="payment_amount" name="payment_amount" min="<?= $booking_details['total_price'] ?>" step="0.01" required 
                                value="<?= htmlspecialchars($booking_details['total_price']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="transfer_name">ชื่อบัญชีผู้โอน</label>
                            <input type="text" id="transfer_name" name="transfer_name" required placeholder="ชื่อบัญชีที่ใช้โอนเงิน">
                        </div>

                        <div class="form-group">
                            <label for="transfer_bank">ธนาคารที่ใช้โอนเงิน</label>
                            <select id="transfer_bank" name="transfer_bank" required>
                                <option value="">-- กรุณาเลือกธนาคาร --</option>
                                <option value="SCB">ไทยพาณิชย์ (SCB)</option>
                                <option value="KBank">กสิกรไทย (KBANK)</option>
                                <option value="Krungthai">กรุงไทย (KTB)</option>
                                <option value="TMBThanachart">ทีทีบี (ttb)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="payment_date">วันที่โอนเงิน</label>
                            <input type="date" id="payment_date" name="payment_date" max="<?= date('Y-m-d') ?>" required 
                                value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_time">เวลาที่โอนเงิน</label>
                            <input type="time" id="payment_time" name="payment_time" required 
                                value="<?= date('H:i') ?>">
                        </div>

                        <div class="form-group">
                            <label for="slip_image">อัปโหลดหลักฐานการโอนเงิน (สลิป)</label>
                            <input type="file" id="slip_image" name="slip_image" accept=".jpg,.jpeg,.png" required>
                        </div>
                        
                        <button type="submit" class="submit-button" id="submit-payment-btn">ยืนยันและส่งหลักฐานการชำระเงิน</button>
                    </form>
                </div>
            <?php elseif ($booking_details['status'] == 'PENDING_PAYMENT' && $countdown_expired): ?>
                 <div class="message error">❌ หมดเวลาในการชำระเงินแล้ว! รายการจองอาจถูกยกเลิก กรุณาตรวจสอบสถานะ</div>
            <?php elseif ($booking_details['status'] == 'PAID_PENDING_REVIEW'): ?>
                <div class="message success">✅ รายการนี้ได้ถูกบันทึกหลักฐานการชำระเงินแล้ว และกำลังรอการตรวจสอบจากเจ้าหน้าที่</div>
            <?php elseif ($booking_details['status'] == 'PAID_CONFIRMED'): ?>
                <div class="message success">✅ การชำระเงินได้รับการยืนยันแล้ว การจองเสร็จสมบูรณ์</div>
            <?php endif; ?>
            
        <?php endif; ?>

    </div>
    
    <?php if ($booking_details && $booking_details['status'] == 'PENDING_PAYMENT' && !$countdown_expired): ?>
    <script>
        // กำหนดเวลาหมดอายุจาก PHP (เวลาเป็นวินาทีในอนาคต)
        const expiryTimestamp = <?= $payment_expiry_timestamp ?>;
        
        // เวลาปัจจุบันของไคลเอ็นต์
        const currentClientTime = Math.floor(Date.now() / 1000);
        
        // เวลาที่เหลือเป็นวินาที (ใช้ค่าจาก Server/Session เป็นหลัก)
        let distance = expiryTimestamp - currentClientTime; 

        // องค์ประกอบ DOM
        const countdownDisplay = document.getElementById('time-left');
        const countdownContainer = document.getElementById('countdown-timer-display');
        const submitButton = document.getElementById('submit-payment-btn');
        const paymentForm = document.getElementById('payment-form');

        // ฟังก์ชันสำหรับฟอร์แมตเวลา
        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            
            const minStr = String(minutes).padStart(2, '0');
            const secStr = String(remainingSeconds).padStart(2, '0');
            
            return minStr + ":" + secStr;
        }

        // ฟังก์ชันนับถอยหลัง
        const countdownInterval = setInterval(function() {
            // ลดเวลาที่เหลือลง 1 วินาที
            distance--;

            if (distance >= 0) {
                // แสดงเวลาที่เหลือ
                countdownDisplay.innerHTML = formatTime(distance);
                
                // เปลี่ยนสีเมื่อเหลือน้อยกว่า 1 นาที
                if (distance <= 60) {
                     countdownDisplay.style.color = '#dc3545'; // สีแดง
                     countdownContainer.style.borderColor = '#dc3545';
                }
            }

            // ถ้าเวลานับถอยหลังหมด
            if (distance <= 0) {
                clearInterval(countdownInterval);
                countdownDisplay.innerHTML = "หมดเวลาชำระเงิน";
                countdownContainer.classList.add('countdown-expired'); // เพิ่มคลาสสำหรับเปลี่ยนสไตล์
                
                // ปิดการใช้งานปุ่มและฟอร์ม
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = "หมดเวลาการชำระเงินแล้ว";
                    submitButton.style.backgroundColor = '#6c757d'; 
                }
                
                // ปิดการใช้งาน input อื่น ๆ ในฟอร์ม
                if (paymentForm) {
                    const inputs = paymentForm.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        input.disabled = true;
                    });
                }
                
                // อาจจะต้องรีโหลดหน้าเพื่อให้ PHP ตรวจสอบสถานะและแสดงข้อความแจ้งเตือนหมดเวลาอย่างเป็นทางการ
                // setTimeout(() => {
                //     window.location.reload();
                // }, 3000); 
            }
        }, 1000); // อัปเดตทุก 1 วินาที
    </script>
    <?php endif; ?>

</body>
</html>