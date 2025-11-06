<?php
include 'dpconnect.php';
$id = $_GET['id'];

$sql = "DELETE FROM members WHERE member_id=$id";

if ($conn->query($sql)) {
    header("Location: index.php");
    exit;
} else {
    echo "❌ Error: " . $conn->error;
}
?>