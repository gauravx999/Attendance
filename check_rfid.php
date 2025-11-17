<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// ✅ Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "❌ Database connection failed"]);
    exit;
}

// ✅ Get RFID tag from GET or POST
$rfid_tag = isset($_REQUEST['rfid_tag']) ? trim(strtolower($_REQUEST['rfid_tag'])) : '';
if (empty($rfid_tag)) {
    echo json_encode(["status" => "error", "message" => "⚠️ No RFID tag received"]);
    exit;
}

// ✅ Check if user exists
$stmt = $conn->prepare("SELECT id, name FROM users WHERE rfid_tag = ?");
$stmt->bind_param("s", $rfid_tag);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "not_found", "message" => "RFID not recognized."]);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$userId = $user['id'];
$userName = $user['name'];

$today = date('Y-m-d');

// ✅ Check attendance record for today
$stmt = $conn->prepare("SELECT id, check_in_time, check_out_time FROM attendance WHERE user_id = ? AND DATE(check_in_time) = ?");
$stmt->bind_param("is", $userId, $today);
$stmt->execute();
$attResult = $stmt->get_result();

if ($attResult->num_rows === 0) {
    // 🟢 Check-in (first scan of the day)
    $stmt = $conn->prepare("INSERT INTO attendance (user_id, check_in_time, status) VALUES (?, NOW(), 'Present')");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    echo json_encode(["status" => "check_in", "message" => "✅ Checked in: $userName"]);
} else {
    // 🔵 Check-out (second scan of the day)
    $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW(), status = 'Completed' WHERE user_id = ? AND DATE(check_in_time) = ?");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();

    echo json_encode(["status" => "check_out", "message" => "👋 Checked out: $userName"]);
}

$stmt->close();
$conn->close();
?>
