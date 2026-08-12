<?php
session_start();
include("peakscinemas_database.php");

// Test database connection
echo "<h2>🔍 Notification Debug Tool</h2>";

if (!$conn) {
    echo "<p style='color:red;'>❌ Database connection failed</p>";
    exit;
} else {
    echo "<p style='color:green;'>✅ Database connected</p>";
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:orange;'>⚠️ No user logged in. Setting test user ID...</p>";
    $_SESSION['user_id'] = 1; // Test with user ID 1
} else {
    echo "<p style='color:green;'>✅ User ID: " . $_SESSION['user_id'] . "</p>";
}

// Check notifications table exists
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($table_check->num_rows > 0) {
    echo "<p style='color:green;'>✅ Notifications table exists</p>";
} else {
    echo "<p style='color:red;'>❌ Notifications table missing</p>";
}

// Count total notifications
$total_result = $conn->query("SELECT COUNT(*) as total FROM notifications");
$total = $total_result->fetch_assoc()['total'];
echo "<p>📊 Total notifications in database: {$total}</p>";

// Count user notifications
$uid = (int)$_SESSION['user_id'];
$user_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE Customer_ID = $uid");
$user_count = $user_result->fetch_assoc()['count'];
echo "<p>👤 Notifications for user {$uid}: {$user_count}</p>";

// Count unread notifications
$unread_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE Customer_ID = $uid AND IsRead = 0");
$unread_count = $unread_result->fetch_assoc()['count'];
echo "<p>🔔 Unread notifications for user {$uid}: {$unread_count}</p>";

// Show recent notifications
echo "<h3>Recent Notifications:</h3>";
$recent = $conn->query("SELECT Notif_ID, Title, Message, Type, IsRead, Created_At FROM notifications WHERE Customer_ID = $uid ORDER BY Created_At DESC LIMIT 5");

if ($recent->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Message</th><th>Type</th><th>Read</th><th>Created</th></tr>";
    while ($row = $recent->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Notif_ID'] . "</td>";
        echo "<td>" . htmlspecialchars($row['Title']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['Message'], 0, 50)) . "...</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . ($row['IsRead'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . $row['Created_At'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange;'>No notifications found for this user.</p>";
}

// Test API endpoint
echo "<h3>🌐 API Test:</h3>";
echo "<p>Testing notifications_api.php...</p>";

// Simulate API call
$api_url = 'http://' . $_SERVER['HTTP_HOST'] . '/PeaksCinema/notifications_api.php';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: ' . $_SERVER['HTTP_COOKIE']
    ]
]);

$api_response = file_get_contents($api_url, false, $context);
if ($api_response) {
    echo "<p style='color:green;'>✅ API Response received:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars($api_response);
    echo "</pre>";
} else {
    echo "<p style='color:red;'>❌ API call failed</p>";
}

// Create test notification if needed
if ($user_count == 0) {
    echo "<h3>🔧 Creating Test Notification:</h3>";
    $test_title = "🎬 Test Notification";
    $test_message = "This is a test notification to verify the system works.";
    $test_type = "general";
    
    $insert = $conn->prepare("INSERT INTO notifications (Customer_ID, Title, Message, Type) VALUES (?, ?, ?, ?)");
    $insert->bind_param("isss", $uid, $test_title, $test_message, $test_type);
    
    if ($insert->execute()) {
        echo "<p style='color:green;'>✅ Test notification created successfully!</p>";
        echo "<p><a href='?refresh=1'>Refresh to see the notification</a></p>";
    } else {
        echo "<p style='color:red;'>❌ Failed to create test notification</p>";
    }
}

echo "<hr>";
echo "<p><a href='home.php'>← Back to Home</a> | <a href='notifications.php'>View All Notifications</a></p>";
?>
