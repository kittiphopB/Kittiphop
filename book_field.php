<?php
session_start();
include("dpconnect.php"); // เชื่อมต่อฐานข้อมูล

// 1. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$success_message = "";
$error_message = "";

// 4. ฟังก์ชันสำหรับตรวจสอบช่วงเวลาที่ถูกจองแล้ว (สำหรับแสดงผล)
function getBookedSlots($conn, $field_id, $booking_date) {
    $booked_slots = [];
    $sql = "SELECT item.start_time 
            FROM booking_items item
            JOIN bookings b ON item.booking_id = b.booking_id
            WHERE item.field_code = '$field_id'
            AND b.booking_date = '$booking_date' 
            AND b.status IN ('PENDING_PAYMENT', 'PAID_CONFIRMED', 'PAID_PENDING_REVIEW') 
            ORDER BY item.start_time ASC";
    
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        error_log("SQL Error in getBookedSlots: " . mysqli_error($conn) . " SQL: " . $sql);
        return [];
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $booked_slots[] = date('H:i', strtotime($row['start_time'])); 
    }
    return $booked_slots;
}

// 🚩 ฟังก์ชัน: ตรวจสอบความซ้ำซ้อนของการจอง (Concurrency Check)
function checkSlotAvailabilityBeforeBooking($conn, $field_id, $booking_date, $selected_slots) {
    if (empty($selected_slots)) return true; 

    $slot_starts = array_map(function($slot) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $slot . ":00") . "'";
    }, $selected_slots);
    $slot_list = implode(", ", $slot_starts);

    $sql = "SELECT COUNT(*) AS count 
            FROM booking_items item
            JOIN bookings b ON item.booking_id = b.booking_id
            WHERE item.field_code = '$field_id'
            AND b.booking_date = '$booking_date' 
            AND b.status IN ('PENDING_PAYMENT', 'PAID_CONFIRMED', 'PAID_PENDING_REVIEW') 
            AND item.start_time IN ($slot_list)";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        error_log("SQL Error in checkSlotAvailabilityBeforeBooking: " . mysqli_error($conn) . " SQL: " . $sql);
        return false;
    }

    $row = mysqli_fetch_assoc($result);
    
    return $row['count'] == 0;
}


// 2. จัดการการจอง (Booking Submission)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_booking'])) {
    $field_id = mysqli_real_escape_string($conn, $_POST['field_id']);
    $booking_date = mysqli_real_escape_string($conn, $_POST['booking_date']);
    $selected_slots_json = $_POST['selected_slots'] ?? '[]';
    $selected_slots = json_decode($selected_slots_json, true);

    if (empty($selected_slots)) {
        $error_message = "❌ กรุณาเลือกช่วงเวลาที่ต้องการจอง!"; 
    } else {
        if (!checkSlotAvailabilityBeforeBooking($conn, $field_id, $booking_date, $selected_slots)) {
            $error_message = "❌ การจองล้มเหลว! ช่วงเวลาที่คุณเลือกอาจถูกจองไปแล้วโดยผู้ใช้อื่น โปรดตรวจสอบตารางเวลาอีกครั้ง";
        } else {
            $field_query = mysqli_query($conn, "SELECT price_per_hour, sport_type FROM sports_fields WHERE field_id='$field_id'");
            
            if (!$field_query || mysqli_num_rows($field_query) == 0) {
                $error_message = "❌ ไม่พบข้อมูลสนามกีฬาที่เลือก.";
                goto end_of_post;
            }

            $field_data = mysqli_fetch_assoc($field_query);
            $price_per_hour = $field_data['price_per_hour'];
            $sport_type = $field_data['sport_type']; 

            $total_hours = count($selected_slots);
            $total_price = $total_hours * $price_per_hour;

            mysqli_begin_transaction($conn);

            try {
                // 2.1. สร้างรายการจองหลัก (bookings)
                // 🚩 สถานะเริ่มต้นคือ PENDING_PAYMENT
                $sql_booking = "INSERT INTO bookings (member_id, booking_date, total_price, status, created_at) 
                                 VALUES ('$member_id', '$booking_date', '$total_price', 'PENDING_PAYMENT', NOW())";
                
                if (!mysqli_query($conn, $sql_booking)) {
                    throw new Exception("Error creating booking: " . mysqli_error($conn));
                }
                $booking_id = mysqli_insert_id($conn);

                // 2.2. สร้างรายการจองย่อย (booking_items)
                $sql_items = "INSERT INTO booking_items (booking_id, field_code, sport_type, use_date, start_time, end_time, price) VALUES ";
                $values = [];

                sort($selected_slots); 

                foreach ($selected_slots as $slot_start) {
                    $start_time = mysqli_real_escape_string($conn, $slot_start . ":00"); 
                    $end_timestamp = strtotime($slot_start . ":00") + 3600; 
                    $end_time = date('H:i:s', $end_timestamp);
                    
                    $values[] = "('$booking_id', '$field_id', '$sport_type', '$booking_date', '$start_time', '$end_time', '$price_per_hour')";
                }

                if (!empty($values)) {
                    $sql_items .= implode(", ", $values);
                    if (!mysqli_query($conn, $sql_items)) {
                        throw new Exception("Error creating booking items: " . mysqli_error($conn));
                    }
                }

                // 2.3. Commit Transaction
                mysqli_commit($conn);
                $success_message = "✅ การจองสำเร็จ! รหัสการจอง: **$booking_id** (ระบบจะยกเลิกอัตโนมัติหากไม่ชำระเงิน)";
                
                // 🔴 เปลี่ยนเส้นทางไปยัง payment.php ทันที
                header("Location: payment.php?booking_id={$booking_id}");
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conn);
                
                $error_detail = $e->getMessage();
                if (strpos($error_detail, 'Duplicate entry') !== false) {
                    $error_message = "❌ การจองล้มเหลว! ช่วงเวลาที่คุณเลือกถูกจองไปแล้ว กรุณาลองเลือกช่วงเวลาอื่น";
                } else {
                    $error_message = "❌ เกิดข้อผิดพลาดในการจอง: " . $error_detail;
                }
            }
        }
    }
}
end_of_post:

// 3. ดึงรายการสนามกีฬาที่เปิดใช้งาน
$fields_result = mysqli_query($conn, "SELECT field_id, field_name, open_time, close_time, price_per_hour FROM sports_fields WHERE is_active = 1 ORDER BY field_name ASC");

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จองสนามกีฬา - Stadium booking</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap');
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .navbar { background: #4285f4; color: #fff; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; font-size: 24px; }
        .navbar a { color: #fff; margin-left: 20px; text-decoration: none; font-weight: 500; opacity: 0.9; transition: opacity 0.3s; }
        .navbar a:hover { opacity: 1; }
        .container { padding: 30px; max-width: 1000px; margin: auto; }
        h3 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        .booking-form, .slots-display {
            background-color: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .form-row {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 15px;
        }
        .form-row label {
            font-weight: 600;
            color: #555;
            flex-basis: 120px;
        }
        .form-row select, .form-row input[type="date"] {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        .slot-button {
            background: #e9ecef;
            color: #333;
            padding: 12px 5px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: background-color 0.2s, transform 0.1s;
            text-align: center;
        }
        .slot-button:hover:not(.booked):not(.disabled) {
            background: #dee2e6;
        }
        .slot-button.selected {
            background: #28a745; 
            color: #fff;
            box-shadow: 0 2px 5px rgba(40, 167, 69, 0.5);
        }
        .slot-button.booked {
            background: #dc3545; 
            color: #fff;
            cursor: not-allowed;
            opacity: 0.7;
            text-decoration: line-through;
        }
        .slot-button.disabled {
            background: #f8f9fa; 
            color: #adb5bd;
            cursor: not-allowed;
        }
        .summary-box {
            background: #f8f9fa;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 1.1em;
            text-align: right;
            font-weight: 600;
        }
        .summary-box span { color: #007bff; font-size: 1.2em; }
        .submit-button-container { text-align: right; margin-top: 20px; }
        .submit-booking {
            background: #007bff;
            color: #fff;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .submit-booking:hover { background: #0056b3; }
        .submit-booking:disabled { background: #ccc; cursor: not-allowed; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* 🚩 Custom Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 100; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto; 
            padding: 30px;
            border: 1px solid #888;
            width: 80%; 
            max-width: 400px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            text-align: center;
        }
        .modal-content h4 {
            color: #dc3545; 
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        .modal-content button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
            transition: background-color 0.2s;
        }
        .modal-content button:hover {
            background-color: #0056b3;
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
            
            <a href="logout.php">ออกจากระบบ</a>
        </div>
    </div>

    <div class="container">
        <h3>📅 จองสนามกีฬา</h3>

        <?php if ($success_message): ?>
            <div class="message success"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="booking-form">
            <form id="slotForm" method="GET" action="book_field.php" onchange="document.getElementById('slotForm').submit();">
                <div class="form-row">
                    <label for="field_id">เลือกสนามกีฬา</label>
                    <select id="field_id" name="field_id" required>
                        <option value="">-- กรุณาเลือกสนาม --</option>
                        <?php 
                        mysqli_data_seek($fields_result, 0);
                        while($field = mysqli_fetch_assoc($fields_result)): ?>
                            <option value="<?= htmlspecialchars($field['field_id']) ?>" 
                                <?= (isset($_GET['field_id']) && $_GET['field_id'] == $field['field_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($field['field_name']) ?> (<?= number_format($field['price_per_hour']) ?> บาท/ชม.)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="booking_date">เลือกวันที่</label>
                    <input type="date" id="booking_date" name="booking_date" 
                        value="<?= htmlspecialchars($_GET['booking_date'] ?? date('Y-m-d')) ?>" 
                        min="<?= date('Y-m-d') ?>" required>
                </div>
            </form>
        </div>

        <?php
        if (isset($_GET['field_id']) && isset($_GET['booking_date'])):
            $selected_field_id = mysqli_real_escape_string($conn, $_GET['field_id']);
            $selected_date = mysqli_real_escape_string($conn, $_GET['booking_date']);
            
            $field_data_query = mysqli_query($conn, "SELECT field_name, open_time, close_time, price_per_hour FROM sports_fields WHERE field_id='$selected_field_id'");
            $field_info = mysqli_fetch_assoc($field_data_query);

            if ($field_info):
                $open_time = strtotime($selected_date . ' ' . $field_info['open_time']);
                $close_time = strtotime($selected_date . ' ' . $field_info['close_time']);
                $price_per_hour = $field_info['price_per_hour'];
                $booked_slots = getBookedSlots($conn, $selected_field_id, $selected_date);
        ?>
        <div class="slots-display">
            <h4>ตารางเวลาสำหรับ **<?= htmlspecialchars($field_info['field_name']) ?>** (<?= number_format($price_per_hour) ?> บาท/ชม.)</h4>
            <p>ในวันที่ **<?= date('d/m/Y', strtotime($selected_date)) ?>** (เวลาเปิด-ปิด: <?= date('H:i', strtotime($field_info['open_time'])) ?> - <?= date('H:i', strtotime($field_info['close_time'])) ?>)</p>
            <hr>
            
            <form method="POST" action="book_field.php" id="bookingForm">
                <input type="hidden" name="field_id" value="<?= htmlspecialchars($selected_field_id) ?>">
                <input type="hidden" name="booking_date" value="<?= htmlspecialchars($selected_date) ?>">
                <input type="hidden" name="selected_slots" id="selected_slots" value="[]">
                <input type="hidden" name="submit_booking" value="1">

                <div class="slots-grid" id="slots-grid-container">
                    <?php
                    for ($time = $open_time; $time < $close_time; $time += 3600) { 
                        $start_hour = date('H:i', $time);
                        $end_hour = date('H:i', $time + 3600);
                        $slot_key = date('H:i', $time); 
                        $is_booked = in_array($slot_key, $booked_slots);
                        $is_past = (strtotime($selected_date) < strtotime(date('Y-m-d'))) || 
                                       (strtotime($selected_date) == strtotime(date('Y-m-d')) && $time < time());
                        
                        $class = '';
                        $text = "";
                        if ($is_booked) {
                            $class = 'booked';
                            $text = "จองแล้ว";
                        } elseif ($is_past) {
                            $class = 'disabled';
                            $text = "หมดเวลา";
                        } else {
                            $class = 'available';
                            $text = $start_hour . " - " . $end_hour;
                        }
                    ?>
                        <button type="button" 
                            class="slot-button <?= $class ?>" 
                            data-slot="<?= $slot_key ?>" 
                            data-price="<?= $price_per_hour ?>"
                            <?= ($is_booked || $is_past) ? 'disabled' : '' ?>
                            onclick="toggleSlot(this)">
                            <?= $text ?>
                        </button>
                    <?php
                    }
                    ?>
                </div>

                <div class="summary-box">
                    จำนวนชั่วโมงที่เลือก: <span id="total_hours">0</span> ชั่วโมง<br>
                    ราคารวม: <span id="total_price">0</span> บาท
                </div>

                <div class="submit-button-container">
                    <button type="submit" class="submit-booking" id="submitButton" disabled>ยืนยันการจอง</button>
                </div>
            </form>
        </div>
        <?php
            else:
                echo "<div class='message error'>ไม่พบข้อมูลสนามกีฬาที่เลือก</div>";
            endif;
        endif;
        ?>

    </div>
    
    <div id="customAlertModal" class="modal">
        <div class="modal-content">
            <h4>❌ ข้อผิดพลาด!</h4>
            <p id="alertMessage"></p>
            <button onclick="closeCustomAlert()">ตกลง</button>
        </div>
    </div>

    <script>
        const selectedSlots = new Set();
        const selectedSlotsHidden = document.getElementById('selected_slots');
        const totalHoursSpan = document.getElementById('total_hours');
        const totalPriceSpan = document.getElementById('total_price');
        const submitButton = document.getElementById('submitButton');
        
        const customAlertModal = document.getElementById('customAlertModal');
        const alertMessageElement = document.getElementById('alertMessage');

        const defaultPrice = <?= isset($price_per_hour) ? $price_per_hour : 0 ?>;
        
        function showCustomAlert(message) {
            alertMessageElement.textContent = message;
            customAlertModal.style.display = 'block';
        }
        
        function closeCustomAlert() {
            customAlertModal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == customAlertModal) {
                closeCustomAlert();
            }
        }
        
        function toggleSlot(button) {
            const slot = button.getAttribute('data-slot');

            if (button.classList.contains('selected')) {
                button.classList.remove('selected');
                selectedSlots.delete(slot);
            } else {
                button.classList.add('selected');
                selectedSlots.add(slot);
            }
            
            updateSummary();
        }

        function updateSummary() {
            const totalHours = selectedSlots.size;
            const totalPrice = totalHours * defaultPrice;

            totalHoursSpan.textContent = totalHours;
            totalPriceSpan.textContent = totalPrice.toLocaleString('th-TH'); 
            
            selectedSlotsHidden.value = JSON.stringify(Array.from(selectedSlots));
            
            submitButton.disabled = totalHours === 0;
            if (submitButton.disabled === false) {
                submitButton.textContent = 'ยืนยันการจอง';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            totalPriceSpan.textContent = (0).toLocaleString('th-TH'); 
            
            const allSlotButtons = document.querySelectorAll('.slot-button.selected');
            allSlotButtons.forEach(button => {
                selectedSlots.add(button.getAttribute('data-slot'));
            });

            updateSummary();

            const bookingForm = document.getElementById('bookingForm');
            if (bookingForm) {
                bookingForm.onsubmit = function() {
                    if (selectedSlots.size === 0) {
                        // 🔴 ใช้ Modal Pop-up แทน alert()
                        showCustomAlert('กรุณาเลือกช่วงเวลาที่ต้องการจอง!');
                        return false; 
                    }
                    
                    submitButton.disabled = true;
                    submitButton.textContent = 'กำลังดำเนินการ... โปรดรอสักครู่...';
                    
                    return true;
                };
            }
        });
    </script>
</body>
</html>