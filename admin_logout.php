<?php
// 1. เริ่ม Session 
session_start();

// 2. เคลียร์ Session ที่เกี่ยวข้องกับ Admin
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);

// 3. นำผู้ใช้กลับไปยังหน้า Login (ซึ่งรองรับการ Login ทั้ง Admin และ Member แล้ว)
header("Location: login.php");
exit();
?>