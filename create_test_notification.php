<?php
session_start();
include("peakscinemas_database.php");

// Create a test notification for debugging
if (!isset($_SESSION['user_id'])) {
    // Set a test user ID (you may need to adjust this to a real user ID in your database)
    $_SESSION['user_id'] = 1;
}

$uid = (int)$_SESSION['user_id'];

// Create test notification
$title = "🎬 Test Notification";
$message = "This is a test notification to verify the system is working correctly!";
$type = "general";

$stmt = $conn->prepare("INSERT INTO notifications (Customer_ID, Title, Message, Type) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $uid, $title, $message, $type);

if ($stmt->execute()) {
    $notif_id = $conn->insert_id;
    echo "<h2>✅ Test Notification Created!</h2>";
    echo "<p><strong>Notification ID:</strong> {$notif_id}</p>";
    echo "<p><strong>User ID:</strong> {$uid}</p>";
    echo "<p><strong>Title:</strong> {$title}</p>";
    echo "<p><strong>Message:</strong> {$message}</p>";
    echo "<p><strong>Type:</strong> {$type}</p>";
    
    echo "<hr>";
    echo "<h3>🔍 Next Steps:</h3>";
    echo "<ol>";
    echo "<li><a href='home.php'>Go to Home Page</a> and check the notification bell 🔔</li>";
    echo "<li>The notification should appear in the dropdown</li>";
    echo "<li>You should see a red badge with '1' on the bell icon</li>";
    echo "<li><a href='notifications.php'>View All Notifications</a> to see the full list</li>";
    echo "</ol>";
    
    echo "<hr>";
    echo "<p><a href='debug_notifications.php'>← Debug Tool</a> | <a href='home.php'>Home Page →</a></p>";
} else {
    echo "<h2>❌ Failed to Create Test Notification</h2>";
    echo "<p>Error: " . $conn->error . "</p>";
}
?>
