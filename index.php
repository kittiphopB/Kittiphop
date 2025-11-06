<?php
session_start();
include("dpconnect.php");

// 🔹 รับค่าจากฟอร์มค้นหา
$search_query = $_GET['search'] ?? '';
$sport_type_filter = $_GET['sport_type'] ?? '';

// 🔹 สร้างคำสั่ง SQL พื้นฐาน
$sql = "SELECT * FROM sports_fields WHERE is_active = 1";
$params = []; // สำหรับเก็บเงื่อนไข WHERE

// 🔹 1. เงื่อนไขการค้นหาด้วยชื่อสนาม (ถ้ามี)
if (!empty($search_query)) {
    $sql .= " AND field_name LIKE ?";
    $params[] = "%" . $search_query . "%";
}

// 🔹 2. เงื่อนไขการกรองด้วยประเภทกีฬา (ถ้ามี)
if (!empty($sport_type_filter)) {
    $sql .= " AND sport_type = ?";
    $params[] = $sport_type_filter;
}

// 🔹 3. การจัดเรียง
$sql .= " ORDER BY sport_type, field_name";


// 🔹 ดึงรายการประเภทกีฬาที่ไม่ซ้ำกันทั้งหมดจากฐานข้อมูล (สำหรับ Dropdown)
$result_types = mysqli_query($conn, "SELECT DISTINCT sport_type FROM sports_fields WHERE is_active = 1 ORDER BY sport_type");


// 🔹 ดึงข้อมูลสนามกีฬาที่เปิดใช้งานอยู่ด้วย Prepared Statement
$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        
        // **✅ โค้ดแก้ไขปัญหา mysqli_stmt_bind_param() Argument #3 (วิธีที่ 1)**
        // เตรียมตัวแปรสำหรับส่งแบบอ้างอิง
        $bind_params = [$types]; // อาร์เรย์เริ่มต้นด้วย string types
        
        // สร้างการอ้างอิง (Reference) ไปยังตัวแปรใน $params
        foreach ($params as $key => $value) {
            $bind_params[] = &$params[$key]; 
        }
        
        // เพิ่ม $stmt เป็นอาร์กิวเมนต์แรกของ call_user_func_array
        array_unshift($bind_params, $stmt); 

        // เรียกใช้ฟังก์ชัน mysqli_stmt_bind_param
        // บรรทัดนี้คือบรรทัดที่มีปัญหาเดิม แต่ถูกเรียกด้วยการเตรียมตัวแปรที่แก้ไขแล้ว
        call_user_func_array('mysqli_stmt_bind_param', $bind_params); 
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    // กรณี prepare statement ล้มเหลว ให้ใช้ query ธรรมดา (สำรอง)
    $result = mysqli_query($conn, "SELECT * FROM sports_fields WHERE is_active = 1 ORDER BY sport_type, field_name");
}

// กำหนดชื่อผู้ใช้สำหรับ Navbar
$current_user_name = '';
if (isset($_SESSION['member_id'])) {
    $current_user_name = $_SESSION['member_name'] ?? 'Member';
} elseif (isset($_SESSION['admin_id'])) {
    $current_user_name = $_SESSION['admin_name'] . ' (Admin)' ?? 'Admin';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - Stadium booking</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .navbar { background: #4285f4; color: #fff; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; font-size: 24px; }
        .navbar a { color: #fff; margin-left: 20px; text-decoration: none; font-weight: 500; opacity: 0.9; transition: opacity 0.3s; }
        .navbar a:hover { opacity: 1; }
        .container { padding: 30px; max-width: 1200px; margin: auto; }
        h1 { color: #343a40; margin-bottom: 30px; }
        .stadium-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        .stadium-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .stadium-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .stadium-card img { width: 100%; height: 200px; object-fit: cover; }
        .stadium-info { padding: 15px; }
        .stadium-info h4 { margin: 0 0 10px 0; color: #343a40; font-size: 1.3em; }
        .stadium-info p { margin: 5px 0; font-size: 0.95em; color: #555; }
        .price { font-weight: 600; color: #dc3545; font-size: 1.1em !important; margin-top: 10px !important; }
        .book-button { display: block; background-color: #4285f4; color: white; text-align: center; padding: 12px; text-decoration: none; font-weight: 600; transition: background-color 0.3s; }
        .book-button:hover { background-color: #0d47a1; }
        
        /* 🎨 SEARCH BAR STYLES */
        .search-form {
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap; /* รองรับการขึ้นบรรทัดใหม่ในหน้าจอเล็ก */
            gap: 15px; 
            align-items: flex-end;
            background: #fff;
            padding: 20px 30px; 
            border-radius: 10px; 
            box-shadow: 0 6px 15px rgba(0,0,0,0.1); 
            border-left: 5px solid #4285f4; 
        }
        .search-field {
            flex-grow: 1;
            min-width: 180px; /* กำหนดความกว้างขั้นต่ำ */
        }
        .search-form label {
            display: block;
            font-weight: 600;
            color: #444;
            margin-bottom: 5px;
            font-size: 0.95em;
        }
        .search-form input, .search-form select {
            width: 100%;
            padding: 12px; 
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box; 
            font-size: 1em;
            transition: border-color 0.3s;
        }
        .search-form input:focus, .search-form select:focus {
            border-color: #4285f4;
            outline: none;
        }
        .search-button, .clear-button {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
            transition: background-color 0.3s, transform 0.1s;
            height: 45px; 
        }
        .search-button {
            background-color: #4285f4;
            color: white;
        }
        .search-button:hover {
            background-color: #0d47a1;
        }
        .clear-button {
            background-color: #e9ecef;
            color: #555;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ccc;
        }
        .clear-button:hover {
            background-color: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Stadium Booking</h2>
        <div>
            <?php if (isset($_SESSION['admin_id'])): ?>
                <a href="admin_dashboard.php">หน้าหลักแอดมิน</a>
                <a href="admin_payments.php">ตรวจสอบชำระเงิน</a>
                <a href="sports_fields.php">จัดการสนามกีฬา</a>
                <a href="admin_profile.php">ข้อมูลส่วนตัว</a>
                <a href="admin_logout.php">ออกจากระบบ (<?= htmlspecialchars($current_user_name) ?>)</a>
            <?php elseif (isset($_SESSION['member_id'])): ?>
                <a href="index.php">หน้าหลัก</a>
                <a href="my_bookings.php">รายการจองของฉัน</a>
                <a href="profile.php">ข้อมูลส่วนตัว</a>
                <a href="logout.php">ออกจากระบบ (<?= htmlspecialchars($current_user_name) ?>)</a>
            <?php else: ?>
                <a href="login.php">เข้าสู่ระบบ / ลงทะเบียน</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <h1>สนามกีฬาที่เปิดให้จอง</h1>
        
        <form method="GET" action="index.php" class="search-form">
            
            <div class="search-field" style="max-width: 400px;">
                <label for="search">ค้นหาชื่อสนามกีฬา</label>
                <input type="text" id="search" name="search" placeholder="กรอกชื่อสนาม..." 
                       value="<?= htmlspecialchars($search_query) ?>">
            </div>
            
            <div class="search-field" style="width: 200px;">
                <label for="sport_type">ประเภทกีฬา</label>
                <select id="sport_type" name="sport_type">
                    <option value="">-- ทุกประเภทกีฬา --</option>
                    <?php 
                    mysqli_data_seek($result_types, 0); // รีเซ็ตตัวชี้
                    while ($row_type = mysqli_fetch_assoc($result_types)): 
                    ?>
                        <?php 
                        $type = htmlspecialchars($row_type['sport_type']);
                        $selected = ($sport_type_filter == $type) ? 'selected' : '';
                        ?>
                        <option value="<?= $type ?>" <?= $selected ?>><?= $type ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <button type="submit" class="search-button">
                ค้นหา
            </button>
            <?php if (!empty($search_query) || !empty($sport_type_filter)): ?>
                <a href="index.php" class="clear-button">
                    ล้าง
                </a>
            <?php endif; ?>
        </form>
        <div class="stadium-grid">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <div class="stadium-card">
                        <?php if ($row['image_path']): ?>
                            <img src="uploads/fields/<?= htmlspecialchars($row['image_path']) ?>" alt="<?= htmlspecialchars($row['field_name']) ?>">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/600x400.png?text=No+Image+Available" alt="ไม่มีรูปภาพ">
                        <?php endif; ?>
                        <div class="stadium-info">
                            <h4><?= htmlspecialchars($row['field_name']) ?></h4>
                            <p>ประเภท: <?= htmlspecialchars($row['sport_type']) ?></p>
                            <p>เวลาทำการ: <?= htmlspecialchars($row['open_time']) ?> - <?= htmlspecialchars($row['close_time']) ?></p>
                            <p class="price">ราคา: <?= number_format($row['price_per_hour']) ?> บาท/ชั่วโมง</p>
                        </div>
                        <a href="book_field.php?field_id=<?= htmlspecialchars($row['field_id']) ?>" class="book-button">จองสนามนี้</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; width: 100%; grid-column: 1 / -1;">
                    <?php if (!empty($search_query) || !empty($sport_type_filter)): ?>
                        ไม่พบสนามกีฬาที่ตรงกับเงื่อนไขการค้นหา
                    <?php else: ?>
                        ไม่มีสนามกีฬาที่เปิดใช้งานในขณะนี้
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>