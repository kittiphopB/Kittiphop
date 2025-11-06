<?php
// 1. เริ่ม Session 
session_start();

// 2. เคลียร์ Session ที่เกี่ยวข้องกับสมาชิก
unset($_SESSION['member_id']);
unset($_SESSION['member_name']);

// 3. นำผู้ใช้กลับไปยังหน้า Login
header("Location: login.php");
exit();
?>