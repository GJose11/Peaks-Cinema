
<?php
date_default_timezone_set('Asia/Manila');
require_once(__DIR__ . "/staff_guard.php");
include(__DIR__ . "/peakscinemas_database.php");

$movie_id     = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
$timeslot_id  = filter_input(INPUT_GET, 'timeslot_id', FILTER_VALIDATE_INT);
$seats_raw    = $_GET['seats'] ?? '[]';
$food_raw     = $_GET['food'] ?? '[]';
$special_requests = $_GET['special_requests'] ?? '';
$customerName = 'Walk-in Customer';

if (!$movie_id || !$timeslot_id) {
    header("Location: staff_dashboard.php");
    exit;
}

$seats = json_decode($seats_raw, true) ?: [];
$seatTotal = array_sum(array_column($seats, 'price'));
$foodOrder = [];

$foodInput = $_POST['foodOrder'] ?? null;
if (is_array($foodInput) && !empty($foodInput)) {
    foreach ($foodInput as $item) {
        $decoded = json_decode($item, true);
        if ($decoded) {
            $foodOrder[] = $decoded;
        }
    }
} else {
    $decodedFood = json_decode($food_raw, true);
    if (is_array($decodedFood)) {
        $foodOrder = $decodedFood;
    }
}

$foodOrder = array_values(array_filter(array_map(static function ($item) {
    if (!is_array($item)) {
        return null;
    }
    $id = (int)($item['id'] ?? 0);
    $qty = max(1, (int)($item['qty'] ?? 1));
    $price = (float)($item['price'] ?? 0);
    $name = trim((string)($item['name'] ?? 'Food Item'));
    if ($id <= 0 || $price < 0) {
        return null;
    }
    return [
        'id' => $id,
        'qty' => $qty,
        'price' => $price,
        'name' => $name,
    ];
}, $foodOrder)));

$foodTotal = 0;
foreach ($foodOrder as $item) {
    $foodTotal += ($item['price'] * $item['qty']);
}

$baseTotal = $seatTotal + $foodTotal;
$discountType = '';
$discountId = '';
$discountAmount = 0.0;
$discountPhoto = '';
$total = $baseTotal;

if (empty($seats) || $total <= 0) {
    header("Location: staff_seats.php?movie_id={$movie_id}&timeslot_id={$timeslot_id}&error=no_seats");
    exit;
}

$paymentColumns = [];
$paymentHasStandardSchema = false;
$colResult = $conn->query("SHOW COLUMNS FROM payment");
if ($colResult) {
    while ($col = $colResult->fetch_assoc()) {
        $paymentColumns[$col['Field']] = true;
    }

    $paymentHasStandardSchema =
        isset($paymentColumns['Ticket_ID']) &&
        isset($paymentColumns['PaymentMethod']) &&
        isset($paymentColumns['AmountPaid']) &&
        isset($paymentColumns['PaymentDate']) &&
        isset($paymentColumns['PaymentStatus']);
}

$stmt = $conn->prepare("
    SELECT t.Date, t.StartTime, t.ScreeningType, ml.MallName, th.TheaterName, m.MovieName, m.MoviePoster
    FROM timeslot t
    JOIN theater th ON th.Theater_ID = t.Theater_ID
    JOIN mall ml ON ml.Mall_ID = th.Mall_ID
    JOIN movie m ON m.Movie_ID = t.Movie_ID
    WHERE t.TimeSlot_ID = ? AND t.Movie_ID = ?
");
$stmt->bind_param("ii", $timeslot_id, $movie_id);
$stmt->execute();
$screening = $stmt->get_result()->fetch_assoc();
if (!$screening) {
    header("Location: staff_dashboard.php");
    exit;
}

$staffName = $_SESSION['staff_name'] ?? 'Staff';
$staffId   = $_SESSION['staff_id'] ?? '-';
$loginTime = $_SESSION['staff_login_time'] ?? date('g:i A');

$methodLabels = [
    'cash' => 'Cash',
    'credit' => 'Credit / Debit Card',
    'paypal' => 'PayPal',
    'gcash' => 'GCash',
    'paymaya' => 'PayMaya',
];
$selectedMethod = $_POST['paymentMethod'] ?? 'cash';
$payError = null;
$payNotice = null;

function ensureWalkInCustomerId(mysqli $conn): ?int
{
    $walkInName = 'Walk-in Customer';
    $walkInEmail = 'staff.walkin@peakscinema.local';

    $lookup = $conn->prepare("SELECT Customer_ID FROM customer WHERE Email = ? LIMIT 1");
    if (!$lookup) {
        return null;
    }
    $lookup->bind_param("s", $walkInEmail);
    $lookup->execute();
    $result = $lookup->get_result();
    if ($result && ($row = $result->fetch_assoc())) {
        return (int)$row['Customer_ID'];
    }

    $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $insert = $conn->prepare("INSERT INTO customer (Name, Email, Password) VALUES (?, ?, ?)");
    if (!$insert) {
        return null;
    }
    $insert->bind_param("sss", $walkInName, $walkInEmail, $password);
    if (!$insert->execute()) {
        return null;
    }

    return (int)$conn->insert_id;
}

$formData = [
    'cash_given' => $_POST['cash_given'] ?? '',
    'discount_type' => $_POST['discountType'] ?? '',
    'discount_id' => $_POST['discountId'] ?? '',
    'discount_amount' => $_POST['discountAmount'] ?? '0',
    'discount_photo' => $_POST['discountPhoto'] ?? '',
    'cardFirstName' => $_POST['cardFirstName'] ?? '',
    'cardLastName' => $_POST['cardLastName'] ?? '',
    'cardNumber' => $_POST['cardNumber'] ?? '',
    'expiryDate' => $_POST['expiryDate'] ?? '',
    'cvv' => $_POST['cvv'] ?? '',
    'paypalFirstName' => $_POST['paypalFirstName'] ?? '',
    'paypalLastName' => $_POST['paypalLastName'] ?? '',
    'paypalPhone' => $_POST['paypalPhone'] ?? '',
    'paypalEmail' => $_POST['paypalEmail'] ?? '',
    'gcashFirstName' => $_POST['gcashFirstName'] ?? '',
    'gcashLastName' => $_POST['gcashLastName'] ?? '',
    'gcashNumber' => $_POST['gcashNumber'] ?? '',
    'paymayaFirstName' => $_POST['paymayaFirstName'] ?? '',
    'paymayaLastName' => $_POST['paymayaLastName'] ?? '',
    'paymayaNumber' => $_POST['paymayaNumber'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($methodLabels[$selectedMethod])) {
        $selectedMethod = 'cash';
    }

    $discountType = in_array(($formData['discount_type'] ?? ''), ['pwd', 'senior'], true) ? $formData['discount_type'] : '';
    $discountId = trim((string)($formData['discount_id'] ?? ''));
    $discountPhoto = trim((string)($formData['discount_photo'] ?? ''));
    $maxDiscount = round($seatTotal * 0.20, 2);
    $postedDiscount = round((float)($formData['discount_amount'] ?? 0), 2);
    $discountAmount = $discountType !== '' ? max(0, min($maxDiscount, $postedDiscount)) : 0.0;
    $total = max(0, round($baseTotal - $discountAmount, 2));

    $cashGiven = (float)($formData['cash_given'] ?: 0);
    $custName = 'Walk-in Customer';
    $seatIds = $_POST['seat_ids'] ?? [];
    $bookingRef = 'SC-' . date('Ymd') . '-' . rand(1000, 9999);
    if (empty($seatIds)) {
        $payError = "No seats found. Please go back and select seats again.";
    } elseif ($discountType !== '' && $discountId === '') {
        $payError = "Please enter the customer's PWD or Senior Citizen ID number.";
    } elseif ($discountType !== '' && $discountPhoto === '') {
        $payError = "Please upload a valid ID photo before applying the discount.";
    } elseif ($selectedMethod === 'cash') {
        if ($cashGiven <= 0) {
            $payError = "Please enter the cash amount given by the customer.";
        } elseif ($cashGiven < $total) {
            $payError = "Insufficient cash. Total due is P" . number_format($total, 2) . ". Cash given: P" . number_format($cashGiven, 2);
        }
    } elseif ($selectedMethod === 'credit') {
        if (trim($formData['cardFirstName']) === '' || trim($formData['cardLastName']) === '' || trim($formData['cardNumber']) === '' || trim($formData['expiryDate']) === '' || trim($formData['cvv']) === '') {
            $payError = "Please complete all required credit or debit card fields.";
        } elseif (strlen(preg_replace('/\D/', '', $formData['cardNumber'])) < 13) {
            $payError = "Please enter a valid credit or debit card number.";
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/[0-9]{2}$/', trim($formData['expiryDate']))) {
            $payError = "Please enter a valid expiry date in MM/YY format.";
        } elseif (!preg_match('/^[0-9]{3,4}$/', trim($formData['cvv']))) {
            $payError = "Please enter a valid CVV.";
        }
    } elseif ($selectedMethod === 'paypal') {
        if (trim($formData['paypalFirstName']) === '' || trim($formData['paypalLastName']) === '') {
            $payError = "Please complete all required PayPal fields.";
        }
    } elseif ($selectedMethod === 'gcash') {
        if (trim($formData['gcashFirstName']) === '' || trim($formData['gcashLastName']) === '') {
            $payError = "Please complete all required GCash fields.";
        }
    } elseif ($selectedMethod === 'paymaya') {
        if (trim($formData['paymayaFirstName']) === '' || trim($formData['paymayaLastName']) === '') {
            $payError = "Please complete all required PayMaya fields.";
        }
    }

    if (!$payError) {
        $conn->begin_transaction();
        try {
            foreach ($seatIds as $seatId) {
                $sid = (int)$seatId;
                $upd = $conn->prepare("UPDATE seats SET SeatAvailability = 'Taken' WHERE Seat_ID = ?");
                $upd->bind_param("i", $sid);
                $upd->execute();
            }

            $paymentIds = [];
            $methodLabel = $methodLabels[$selectedMethod];

            // Staff walk-in sales use one payment row for the whole transaction.
            if ($paymentHasStandardSchema) {
                $paymentDate = date('Y-m-d');
                $paymentStatus = 'Paid';
                $ticketId = 0;

                $ps = $conn->prepare("
                    INSERT INTO payment (Ticket_ID, PaymentMethod, AmountPaid, PaymentDate, PaymentStatus)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $ps->bind_param("isdss", $ticketId, $methodLabel, $total, $paymentDate, $paymentStatus);

                if (!$ps->execute()) {
                    throw new Exception("Payment insert failed: " . $conn->error);
                }

                $paymentIds[] = $conn->insert_id;
            } else {
                throw new Exception("Payment insert failed: unsupported payment table schema.");
            }

            // Save food and drink orders using the same food_orders table as the customer flow.
            if (!empty($foodOrder)) {
                $foodTable = $conn->query("SHOW TABLES LIKE 'food_orders'");
                if ($foodTable && $foodTable->num_rows > 0) {
                    $customerColumnExists = false;
                    $customerAllowsNull = true;
                    $staffColumnExists = false;
                    $staffAllowsNull = true;
                    $foodColumnResult = $conn->query("SHOW COLUMNS FROM food_orders");
                    if ($foodColumnResult) {
                        while ($col = $foodColumnResult->fetch_assoc()) {
                            $field = $col['Field'];
                            if ($field === 'Customer_ID') {
                                $customerColumnExists = true;
                                $customerAllowsNull = strtoupper((string)$col['Null']) === 'YES';
                            }
                            if ($field === 'Staff_ID') {
                                $staffColumnExists = true;
                                $staffAllowsNull = strtoupper((string)$col['Null']) === 'YES';
                            }
                        }
                    }

                    $staffIdInt = isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : null;
                    $walkInCustomerId = $customerColumnExists ? ensureWalkInCustomerId($conn) : null;
                    $includeCustomerId = $customerColumnExists && $walkInCustomerId !== null;
                    $includeStaffId = $staffColumnExists && $staffIdInt !== null && $staffIdInt > 0;
                    $specialRequests = $_POST['special_requests'] ?? $special_requests ?? null;

                    if ($customerColumnExists && !$includeCustomerId && !$customerAllowsNull) {
                        throw new Exception("Food order insert failed: unable to create the walk-in customer reference.");
                    } else {
                        if ($staffColumnExists && !$includeStaffId && !$staffAllowsNull) {
                            throw new Exception("Food order insert failed: missing staff account reference.");
                        }

                        foreach ($foodOrder as $item) {
                            $itemId = (int)($item['id'] ?? 0);
                            $qty = max(1, (int)($item['qty'] ?? 1));
                            $unitPrice = (float)($item['price'] ?? 0);
                            if ($itemId <= 0 || $unitPrice < 0) {
                                continue;
                            }
                            $lineTotal = $unitPrice * $qty;

                            if ($includeCustomerId && $includeStaffId) {
                                $foodInsert = $conn->prepare("
                                    INSERT INTO food_orders (Customer_ID, Staff_ID, TimeSlot_ID, Item_ID, Quantity, UnitPrice, BookingRef, SpecialRequests)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                $foodInsert->bind_param("iiiiidss", $walkInCustomerId, $staffIdInt, $timeslot_id, $itemId, $qty, $lineTotal, $bookingRef, $specialRequests);
                            } elseif ($includeCustomerId) {
                                $foodInsert = $conn->prepare("
                                    INSERT INTO food_orders (Customer_ID, TimeSlot_ID, Item_ID, Quantity, UnitPrice, BookingRef, SpecialRequests)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                ");
                                $foodInsert->bind_param("iiiidss", $walkInCustomerId, $timeslot_id, $itemId, $qty, $lineTotal, $bookingRef, $specialRequests);
                            } elseif ($includeStaffId) {
                                $foodInsert = $conn->prepare("
                                    INSERT INTO food_orders (Staff_ID, TimeSlot_ID, Item_ID, Quantity, UnitPrice, BookingRef, SpecialRequests)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                ");
                                $foodInsert->bind_param("iiiidss", $staffIdInt, $timeslot_id, $itemId, $qty, $lineTotal, $bookingRef, $specialRequests);
                            } else {
                                $foodInsert = $conn->prepare("
                                    INSERT INTO food_orders (TimeSlot_ID, Item_ID, Quantity, UnitPrice, BookingRef, SpecialRequests)
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $foodInsert->bind_param("iiidss", $timeslot_id, $itemId, $qty, $lineTotal, $bookingRef, $specialRequests);
                            }

                            if (!$foodInsert || !$foodInsert->execute()) {
                                throw new Exception("Food order insert failed: " . $conn->error);
                            }
                        }
                    }
                }
            }

            $conn->commit();

            // v3.2 - Record walk-in customer details into the dedicated table
            try {
                $walkInDate = date('Y-m-d');
                $bookingTime = date('H:i:s');
                $staffIdInt = isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : null;
                $foodDetailsJson = !empty($foodOrder) ? json_encode($foodOrder) : null;
                $specialRequests = $_POST['special_requests'] ?? $special_requests ?? null;
                $paymentStatusLabel = 'Paid';
                
                $wiStmt = $conn->prepare("
                    INSERT INTO walk_in_customers (
                        Name,
                        WalkInDate,
                        BookingTime,
                        Status,
                        Staff_ID,
                        BookingRef,
                        PaymentMethod,
                        AmountPaid,
                        PaymentStatus,
                        DiscountType,
                        DiscountAmount,
                        FoodOrderDetails,
                        SpecialRequests
                    )
                    VALUES (?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $wiStmt->bind_param(
                    "sssissdssdss",
                    $customerName,
                    $walkInDate,
                    $bookingTime,
                    $staffIdInt,
                    $bookingRef,
                    $methodLabel,
                    $total,
                    $paymentStatusLabel,
                    $discountType,
                    $discountAmount,
                    $foodDetailsJson,
                    $specialRequests
                );
                if (!$wiStmt || !$wiStmt->execute()) {
                    throw new Exception($conn->error ?: 'Unknown walk-in insert error.');
                }
            } catch (Exception $wiEx) {
                // Non-critical error: Log it but don't fail the receipt display
                error_log("Failed to record walk-in customer: " . $wiEx->getMessage());
            }

            $_SESSION['staff_receipt'] = [
                'customer' => $custName,
                'movie' => $screening['MovieName'],
                'date' => $screening['Date'],
                'time' => $screening['StartTime'],
                'type' => $screening['ScreeningType'],
                'mall' => $screening['MallName'],
                'theater' => $screening['TheaterName'],
                'seats' => $seats,
                'seat_total' => $seatTotal,
                'food_order' => $foodOrder,
                'food_total' => $foodTotal,
                'discount_type' => $discountType,
                'discount_id' => $discountId,
                'discount_amount' => $discountAmount,
                'base_total' => $baseTotal,
                'booking_ref' => $bookingRef,
                'total' => $total,
                'notice' => $payNotice,
                'cash_given' => $selectedMethod === 'cash' ? $cashGiven : null,
                'change' => $selectedMethod === 'cash' ? ($cashGiven - $total) : null,
                'payment_method' => $methodLabel,
                'payment_ids' => $paymentIds,
                'staff' => $_SESSION['staff_name'] ?? 'Staff',
                'special_requests' => $specialRequests,
                'booked_time' => date('F d, Y g:i A'),
                'timestamp' => date('F d, Y g:i A'),
            ];
            header("Location: staff_receipt.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $payError = "Booking failed: " . $e->getMessage();
        }
    }
}

$bills = [50, 100, 200, 500, 1000];
$shown = [];
foreach ($bills as $bill) {
    $suggested = ceil($total / $bill) * $bill;
    if ($suggested >= $total && !in_array($suggested, $shown, true) && count($shown) < 5) {
        $shown[] = $suggested;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Payment - Staff</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Outfit', sans-serif; background: #0f0f0f; color: #F9F9F9; min-height: 100vh; padding-top: 70px; padding-bottom: 60px; }
body::before { content: ''; position: fixed; inset: 0; background: url('movie-background-collage.jpg') center/cover no-repeat; opacity: 0.04; z-index: 0; pointer-events: none; }
body::after { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse at center, transparent 20%, #0f0f0f 75%); z-index: 1; pointer-events: none; }
header, nav, .page-wrapper { position: relative; z-index: 10; }
header { background: #1C1C1C; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; height: 60px; border-bottom: 1px solid rgba(255,255,255,0.06); }
.logo { display: flex; align-items: center; }
.logo img { height: 42px; width: auto; filter: invert(1); display: block; }
nav { display: flex; gap: 4px; }
nav a { color: rgba(249,249,249,0.5); text-decoration: none; font-size: 0.8rem; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: all 0.2s; }
nav a:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }
.page-wrapper { width: 95%; max-width: 980px; margin: 32px auto; display: flex; flex-direction: column; gap: 18px; }
.page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
.page-title { font-size: 1.7rem; font-weight: 800; }
.two-col { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 18px; align-items: start; }
.summary-panel { position: sticky; top: 84px; }
.panel { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; }
.panel-header { padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: space-between; }
.panel-header h2 { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
.panel-body { padding: 20px; }
.info-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.04); gap: 16px; }
.info-row:last-child { border-bottom: none; }
.info-row .lbl { color: rgba(249,249,249,0.4); }
.info-row .val { font-weight: 600; text-align: right; max-width: 60%; }
.seats-list { display: flex; flex-wrap: wrap; gap: 6px; padding: 12px 0; }
.seat-chip { padding: 4px 11px; border-radius: 7px; background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.25); font-size: 0.78rem; font-weight: 700; color: #ff6b6b; }
.total-bar { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; background: rgba(255,77,77,0.06); border-top: 1px solid rgba(255,77,77,0.12); }
.total-lbl { font-size: 0.82rem; color: rgba(249,249,249,0.5); }
.total-val { font-size: 1.3rem; font-weight: 800; color: #ff4d4d; }
.error-box { background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.25); border-left: 3px solid #ff4d4d; border-radius: 8px; padding: 12px 16px; font-size: 0.82rem; color: #ff6b6b; display: flex; align-items: center; gap: 8px; }
.schema-warn { background: rgba(255,193,7,0.08); border: 1px solid rgba(255,193,7,0.2); border-radius: 8px; padding: 10px 14px; font-size: 0.76rem; color: #ffd54f; }
.method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.method-card { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #222; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; cursor: pointer; transition: all 0.2s; }
.method-card:hover { border-color: rgba(255,255,255,0.2); background: #272727; }
.method-card.selected { border-color: #ff4d4d; background: rgba(255,77,77,0.08); }
.method-card input[type="radio"] { display: none; }
.method-logo { width: 38px; height: 24px; object-fit: contain; background: #fff; border-radius: 4px; padding: 2px 4px; flex-shrink: 0; }
.method-name { font-size: 0.8rem; font-weight: 600; color: #F9F9F9; }
.discount-panel { margin-bottom: 16px; }
.discount-type-row { display: flex; gap: 8px; margin-bottom: 14px; }
.disc-btn { flex: 1; padding: 10px 12px; border-radius: 9px; text-align: center; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.5); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: 'Outfit', sans-serif; }
.disc-btn:hover { border-color: rgba(255,77,77,0.3); color: #F9F9F9; }
.disc-btn.active { background: rgba(255,77,77,0.12); border-color: rgba(255,77,77,0.4); color: #ff4d4d; }
.disc-id-wrap { display: none; margin-bottom: 4px; }
.disc-preview { display: none; padding: 10px 14px; background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.2); border-radius: 9px; font-size: 0.8rem; color: #81c784; margin-top: 10px; }
.disc-law { font-size: 0.7rem; color: rgba(249,249,249,0.3); line-height: 1.6; margin-bottom: 14px; }
.id-upload-wrap { display: none; margin-top: 14px; }
.id-upload-label { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 20px; border: 2px dashed rgba(255,255,255,0.15); border-radius: 10px; cursor: pointer; transition: all 0.2s; background: rgba(255,255,255,0.03); text-align: center; }
.id-upload-label:hover { border-color: rgba(255,77,77,0.4); background: rgba(255,77,77,0.04); }
.id-upload-icon { font-size: 1.8rem; }
.id-upload-text { font-size: 0.78rem; color: rgba(249,249,249,0.45); line-height: 1.5; }
.id-upload-text strong { color: #ff6b6b; font-weight: 700; }
.id-upload-input { display: none; }
.id-preview-wrap { display: none; margin-top: 10px; position: relative; border-radius: 10px; overflow: hidden; border: 1px solid rgba(76,175,80,0.3); }
.id-preview-wrap img { width: 100%; max-height: 200px; object-fit: cover; display: block; }
.id-preview-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; }
.id-preview-wrap:hover .id-preview-overlay { opacity: 1; }
.id-preview-change { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); color: #fff; padding: 7px 16px; border-radius: 7px; font-family: 'Outfit', sans-serif; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.id-preview-change:hover { background: rgba(255,255,255,0.25); }
.id-verified-badge { display: inline-flex; align-items: center; gap: 5px; background: rgba(76,175,80,0.1); border: 1px solid rgba(76,175,80,0.25); color: #81c784; font-size: 0.72rem; font-weight: 700; padding: 4px 10px; border-radius: 10px; margin-top: 8px; }
.fields-wrap { margin-top: 20px; display: none; }
.fields-wrap.active { display: block; }
.form-row { display: flex; gap: 12px; }
.form-group { flex: 1; margin-bottom: 14px; }
.form-group label { display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); margin-bottom: 6px; }
.form-group input { width: 100%; padding: 10px 13px; border-radius: 9px; border: 1px solid rgba(255,255,255,0.1); background: #222; color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.88rem; outline: none; transition: border-color 0.2s; }
.form-group input::placeholder { color: rgba(249,249,249,0.2); }
.form-group input:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
.form-group input.valid { border-color: rgba(76,175,80,0.6); }
.form-group input.invalid { border-color: rgba(255,77,77,0.5); background: rgba(255,77,77,0.04); }
.cash-input { font-size: 1.35rem !important; font-weight: 700 !important; text-align: center !important; letter-spacing: 1px !important; }
.err { font-size: 0.68rem; color: #ff6b6b; margin-top: 4px; display: none; }
.form-hint { font-size: 0.75rem; color: rgba(249,249,249,0.3); margin-top: 6px; }
.quick-bills, .quick-requests { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
.bill-btn, .request-btn { padding: 7px 16px; background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 7px; color: rgba(249,249,249,0.7); font-family: 'Outfit', sans-serif; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.15s; }
.bill-btn:hover, .request-btn:hover { border-color: rgba(255,77,77,0.4); color: #F9F9F9; background: #2a2a2a; }
.bill-btn.active, .request-btn.active { background: rgba(255,77,77,0.1); border-color: #ff4d4d; color: #ff4d4d; }
.change-display { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border-radius: 9px; margin-top: 10px; background: #111; border: 1px solid rgba(255,255,255,0.07); transition: all 0.2s; }
.change-display.valid { background: #0d1f0d; border-color: rgba(76,175,80,0.3); }
.change-display.invalid { background: #1f0d0d; border-color: rgba(255,77,77,0.2); }
.change-lbl { font-size: 0.78rem; color: rgba(249,249,249,0.4); }
.change-val { font-size: 1.2rem; font-weight: 800; color: rgba(249,249,249,0.2); transition: color 0.2s; }
.change-display.valid .change-val { color: #66bb6a; }
.change-display.invalid .change-val { color: #ff6b6b; }
.status-box { border-radius: 8px; padding: 11px 14px; font-size: 0.8rem; margin-top: 4px; display: none; }
.status-box.ok { background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.25); color: #81c784; }
.status-box.err { background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.2); color: #ff6b6b; }
.btn-confirm { width: 100%; padding: 15px; background: #2a2a2a; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; color: rgba(249,249,249,0.3); font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: not-allowed; margin-top: 14px; letter-spacing: 0.5px; transition: all 0.25s; }
.btn-confirm.ready { background: #ff4d4d; color: #fff; border-color: #ff4d4d; cursor: pointer; }
.btn-confirm.ready:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(255,77,77,0.3); }
@media (max-width: 900px) {
  .two-col { grid-template-columns: 1fr; }
  .summary-panel { position: static; top: auto; }
}
@media (max-width: 680px) {
  nav { display: none; }
  header { padding: 0 14px; }
  .method-grid { grid-template-columns: 1fr; }
  .discount-type-row { flex-direction: column; }
  .form-row { flex-direction: column; gap: 0; }
  .page-wrapper { width: 92%; }
}
</style>
</head>
<body>
<header>
  <div class="logo"><img src="peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
  <nav>
    <a href="staff_dashboard.php">Dashboard</a>
    <a href="staff_seats.php">Seat Selection</a>
    <a href="staff_payment.php" class="active">Payment</a>
    <a href="staff_receipt.php">Receipt</a>
  </nav>
  <div style="display:flex;align-items:center;gap:10px;">
    <div style="text-align:right;line-height:1.4;">
      <div style="font-size:0.78rem;font-weight:600;color:rgba(249,249,249,0.6);">Staff <?= htmlspecialchars($staffName) ?></div>
      <div style="font-size:0.62rem;color:rgba(249,249,249,0.25);">Staff #<?= htmlspecialchars($staffId) ?> · Since <?= htmlspecialchars($loginTime) ?></div>
    </div>
    <a href="home.php" style="color:rgba(249,249,249,0.45);text-decoration:none;font-size:0.78rem;font-weight:500;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);transition:all 0.2s;">&larr; Customer Site</a>
    <a href="staff_logout.php" style="color:#ff4d4d;text-decoration:none;font-size:0.78rem;font-weight:600;padding:6px 14px;border-radius:6px;border:1px solid rgba(255,77,77,0.3);background:rgba(255,77,77,0.08);transition:all 0.2s;" onclick="return confirm('Log out?')">&rarr; Log Out</a>
  </div>
</header>

<div class="page-wrapper">
  <div>
    <p class="page-label">Step 3 of 3</p>
    <h1 class="page-title">Payment Processing</h1>
  </div>

  <?php if ($payError): ?>
  <div class="error-box">Warning <?= htmlspecialchars($payError) ?></div>
  <?php endif; ?>

  <div class="two-col">
    <div class="panel">
      <div class="panel-header"><h2>Payment Details</h2></div>
      <div class="panel-body">
        <form method="POST" id="payForm" novalidate>
          <?php foreach ($seats as $seat): ?>
          <input type="hidden" name="seat_ids[]" value="<?= (int)$seat['id'] ?>">
          <?php endforeach; ?>
          <?php foreach ($foodOrder as $item): ?>
          <input type="hidden" name="foodOrder[]" value="<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>">
          <?php endforeach; ?>
          <input type="hidden" name="totalPrice" id="totalPriceHidden" value="<?= htmlspecialchars(number_format($total, 2, '.', '')) ?>">
            <input type="hidden" name="discountType" id="discountTypeHidden" value="<?= htmlspecialchars($discountType) ?>">
            <input type="hidden" name="discountId" id="discountIdHidden" value="<?= htmlspecialchars($discountId) ?>">
            <input type="hidden" name="discountAmount" id="discountAmountHidden" value="<?= htmlspecialchars(number_format($discountAmount, 2, '.', '')) ?>">
            <input type="hidden" name="discountPhoto" id="discountPhotoHidden" value="<?= htmlspecialchars($discountPhoto) ?>">
            <input type="hidden" name="special_requests" value="<?= htmlspecialchars($special_requests) ?>">

          <div class="panel discount-panel">
            <div class="panel-header"><h2>PWD / Senior Citizen Discount</h2></div>
            <div class="panel-body">
              <p class="disc-law">
                Qualified PWD and Senior Citizens are entitled to <strong style="color:#ff4d4d;">20% off</strong> on ticket prices under RA 9994 &amp; RA 7277.
                Please present the valid ID at the counter for verification.
              </p>
              <div class="discount-type-row">
                <button type="button" class="disc-btn<?= $discountType === '' ? ' active' : '' ?>" id="dBtn-none" data-discount="none">No Discount</button>
                <button type="button" class="disc-btn<?= $discountType === 'pwd' ? ' active' : '' ?>" id="dBtn-pwd" data-discount="pwd">&#9855; PWD</button>
                <button type="button" class="disc-btn<?= $discountType === 'senior' ? ' active' : '' ?>" id="dBtn-senior" data-discount="senior">&#128116; Senior Citizen</button>
              </div>
              <div class="disc-id-wrap" id="discIdWrap">
                <div class="form-group" style="margin-bottom:12px;">
                  <label>ID Number <span style="color:#ff4d4d;">*</span></label>
                  <input type="text" id="discountIdInput" value="<?= htmlspecialchars($discountId) ?>" placeholder="Enter the customer's PWD / Senior Citizen ID number" oninput="onDiscIdChange(this.value)">
                </div>
                <div class="id-upload-wrap" id="idPhotoWrap">
                  <label style="font-size:0.68rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(249,249,249,0.4);display:block;margin-bottom:6px;">
                    ID Photo <span style="color:#ff4d4d;">*</span>
                    <span style="color:rgba(249,249,249,0.25);font-size:0.6rem;text-transform:none;letter-spacing:0;font-weight:500;margin-left:4px;">(JPG, PNG or WEBP)</span>
                  </label>
                  <label class="id-upload-label" id="idUploadLabel" for="idPhotoInput">
                    <div class="id-upload-icon">📷</div>
                    <div class="id-upload-text">
                      <strong>Click to upload</strong> or drag and drop the ID photo<br>
                      Front of the PWD / Senior Citizen card
                    </div>
                  </label>
                  <input type="file" class="id-upload-input" id="idPhotoInput" accept="image/jpeg,image/png,image/webp" onchange="handleIdPhoto(this)">

                  <div class="id-preview-wrap" id="idPreviewWrap">
                    <img id="idPreviewImg" src="" alt="ID Preview">
                    <div class="id-preview-overlay">
                      <button type="button" class="id-preview-change" onclick="document.getElementById('idPhotoInput').click()">Change Photo</button>
                    </div>
                  </div>
                  <div id="idVerifiedBadge" style="display:none;"></div>
                  <div id="idBlurWarning" style="display:none;margin-top:10px;padding:12px 14px;background:rgba(255,152,0,0.08);border:1px solid rgba(255,152,0,0.25);border-radius:9px;font-size:0.8rem;"></div>
                </div>
              </div>
              <div class="disc-preview" id="discPreview"></div>
              <div id="verifyNotice" style="display:none;margin-top:10px;padding:11px 14px;background:rgba(255,152,0,0.07);border:1px solid rgba(255,152,0,0.2);border-radius:9px;font-size:0.78rem;color:#ffb74d;line-height:1.6;">
                Staff should verify the presented ID before finalizing the discounted sale.
              </div>
            </div>
          </div>

          <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><h2>Select Payment Method</h2></div>
            <div class="panel-body">
              <div class="method-grid">
                <div class="method-card" data-method="cash">
                  <input type="radio" name="paymentMethod" value="cash" id="cash">
                  <span class="method-name">Cash</span>
                </div>
                <div class="method-card" data-method="credit">
                  <input type="radio" name="paymentMethod" value="credit" id="credit">
                  <img src="visa.png" alt="Card" class="method-logo">
                  <span class="method-name">Credit / Debit</span>
                </div>
                <div class="method-card" data-method="paypal">
                  <input type="radio" name="paymentMethod" value="paypal" id="paypal">
                  <img src="paypal.png" alt="PayPal" class="method-logo">
                  <span class="method-name">PayPal</span>
                </div>
                <div class="method-card" data-method="gcash">
                  <input type="radio" name="paymentMethod" value="gcash" id="gcash">
                  <img src="gcash.png" alt="GCash" class="method-logo">
                  <span class="method-name">GCash</span>
                </div>
                <div class="method-card" data-method="paymaya">
                  <input type="radio" name="paymentMethod" value="paymaya" id="paymaya">
                  <img src="paymaya.png" alt="PayMaya" class="method-logo">
                  <span class="method-name">PayMaya</span>
                </div>
              </div>
            </div>
          </div>

          <div id="cashFields" class="fields-wrap">
            <div class="form-group">
              <label>Cash Given by Customer (P)</label>
              <input type="number" name="cash_given" id="cashInput" class="cash-input" value="<?= htmlspecialchars((string)$formData['cash_given']) ?>" placeholder="0.00" min="0" step="0.01" autocomplete="off">
            </div>
            <div class="quick-bills">
              <?php foreach ($shown as $suggested): ?>
              <button type="button" class="bill-btn" data-val="<?= $suggested ?>" onclick="setBill(<?= $suggested ?>)">P<?= number_format($suggested) ?></button>
              <?php endforeach; ?>
            </div>
            <div class="change-display" id="changeBox">
              <span class="change-lbl">Change to Return</span>
              <span class="change-val" id="changeAmt">P-</span>
            </div>
          </div>

          <div id="creditFields" class="fields-wrap">
            <div class="form-row">
              <div class="form-group">
                <label>First Name</label>
                <input type="text" id="cardFirstName" name="cardFirstName" value="<?= htmlspecialchars($formData['cardFirstName']) ?>" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                <div class="err" id="cardFirstNameErr">Letters only</div>
              </div>
              <div class="form-group">
                <label>Last Name</label>
                <input type="text" id="cardLastName" name="cardLastName" value="<?= htmlspecialchars($formData['cardLastName']) ?>" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                <div class="err" id="cardLastNameErr">Letters only</div>
              </div>
            </div>
            <div class="form-group">
              <label>Card Number</label>
              <input type="text" id="cardNumber" name="cardNumber" value="<?= htmlspecialchars($formData['cardNumber']) ?>" placeholder="1234 5678 9012 3456" data-required maxlength="19">
              <div class="err" id="cardNumberErr">Enter a valid card number (13-16 digits)</div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Expiry Date</label>
                <input type="text" id="expiryDate" name="expiryDate" value="<?= htmlspecialchars($formData['expiryDate']) ?>" placeholder="MM/YY" data-required maxlength="5">
                <div class="err" id="expiryDateErr">Format: MM/YY</div>
              </div>
              <div class="form-group">
                <label>CVV</label>
                <input type="text" id="cvv" name="cvv" value="<?= htmlspecialchars($formData['cvv']) ?>" placeholder="123" data-required data-pattern="[0-9]{3,4}" maxlength="4">
                <div class="err" id="cvvErr">3-4 digits</div>
              </div>
            </div>
          </div>
            <div id="paypalFields" class="fields-wrap">
            <div class="form-row">
              <div class="form-group">
                <label>First Name</label>
                <input type="text" id="paypalFirstName" name="paypalFirstName" value="<?= htmlspecialchars($formData['paypalFirstName']) ?>" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                <div class="err" id="paypalFirstNameErr">Letters only</div>
              </div>
              <div class="form-group">
                <label>Last Name</label>
                <input type="text" id="paypalLastName" name="paypalLastName" value="<?= htmlspecialchars($formData['paypalLastName']) ?>" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                <div class="err" id="paypalLastNameErr">Letters only</div>
              </div>
            </div>
            <p class="form-hint">Use this for card-not-present counter transactions processed through PayPal.</p>
          </div>

          <div id="gcashFields" class="fields-wrap">
            <div class="form-row">
              <div class="form-group">
                <label>First Name</label>
                <input type="text" id="gcashFirstName" name="gcashFirstName" value="<?= htmlspecialchars($formData['gcashFirstName']) ?>" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                <div class="err" id="gcashFirstNameErr">Letters only</div>
              </div>
              <div class="form-group">
                <label>Last Name</label>
                <input type="text" id="gcashLastName" name="gcashLastName" value="<?= htmlspecialchars($formData['gcashLastName']) ?>" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                <div class="err" id="gcashLastNameErr">Letters only</div>
              </div>
            </div>
            <p class="form-hint">Use this when the customer chooses to pay via GCash at the counter.</p>
          </div>

          <div id="paymayaFields" class="fields-wrap">
            <div class="form-row">
              <div class="form-group">
                <label>First Name</label>
                <input type="text" id="paymayaFirstName" name="paymayaFirstName" value="<?= htmlspecialchars($formData['paymayaFirstName']) ?>" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                <div class="err" id="paymayaFirstNameErr">Letters only</div>
              </div>
              <div class="form-group">
                <label>Last Name</label>
                <input type="text" id="paymayaLastName" name="paymayaLastName" value="<?= htmlspecialchars($formData['paymayaLastName']) ?>" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                <div class="err" id="paymayaLastNameErr">Letters only</div>
              </div>
            </div>
            <p class="form-hint">Use this when the customer chooses to pay via PayMaya at the counter.</p>
          </div>

            <div id="statusBox" class="status-box"></div>
          <button type="submit" class="btn-confirm" id="confirmBtn" disabled>Select a payment method</button>
        </form>
      </div>
    </div>

    <div class="panel summary-panel">
      <div class="panel-header"><h2>Booking Summary</h2></div>
      <div class="panel-body">
        <div class="info-row"><span class="lbl">Customer</span><span class="val">Walk-in Customer</span></div>
        <div class="info-row"><span class="lbl">Movie</span><span class="val"><?= htmlspecialchars($screening['MovieName']) ?></span></div>
        <div class="info-row"><span class="lbl">Date</span><span class="val"><?= date('F d, Y', strtotime($screening['Date'])) ?></span></div>
        <div class="info-row"><span class="lbl">Time</span><span class="val"><?= date('g:i A', strtotime($screening['StartTime'])) ?> - <?= htmlspecialchars($screening['ScreeningType']) ?></span></div>
        <div class="info-row"><span class="lbl">Venue</span><span class="val"><?= htmlspecialchars($screening['MallName']) ?> - <?= htmlspecialchars($screening['TheaterName']) ?></span></div>
        <div class="info-row"><span class="lbl">Booked Time</span><span class="val"><?= date('F d, Y g:i A') ?></span></div>
        <div class="seats-list">
          <?php foreach ($seats as $seat): ?>
          <span class="seat-chip"><?= htmlspecialchars($seat['label']) ?> - P<?= number_format($seat['price']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($foodOrder)): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:0.72rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(249,249,249,0.45);margin-bottom:10px;">Food Order</div>
          <?php foreach ($foodOrder as $item): ?>
          <div class="info-row">
            <span class="lbl"><?= htmlspecialchars($item['name']) ?> x<?= (int)$item['qty'] ?></span>
            <span class="val">P<?= number_format($item['price'] * $item['qty'], 2) ?></span>
          </div>
          <?php endforeach; ?>
          <div class="info-row"><span class="lbl">Seats Subtotal</span><span class="val">P<?= number_format($seatTotal, 2) ?></span></div>
          <div class="info-row"><span class="lbl">Food &amp; Drinks</span><span class="val">P<?= number_format($foodTotal, 2) ?></span></div>
          </div>
          <?php endif; ?>
          <?php if (!empty(trim($special_requests))): ?>
          <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.06);">
            <div style="font-size:0.72rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(249,249,249,0.45);margin-bottom:10px;">Special Requests</div>
            <div style="font-size:0.8rem;line-height:1.6;color:rgba(249,249,249,0.68);padding:12px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05);">
              <?= nl2br(htmlspecialchars($special_requests)) ?>
            </div>
          </div>
          <?php endif; ?>
          <div class="info-row" id="discountRow" style="<?= $discountAmount > 0 ? '' : 'display:none;' ?>">
          <span class="lbl" id="discountRowLabel" style="color:#81c784;"><?= $discountType === 'senior' ? 'Senior Citizen Discount (20%)' : 'PWD Discount (20%)' ?></span>
          <span class="val" id="discountRowVal" style="color:#81c784;">- P<?= number_format($discountAmount, 2) ?></span>
        </div>
      </div>
      <div class="total-bar"><span class="total-lbl">Total Amount Due</span><span class="total-val" id="grandTotalDisplay">P<?= number_format($total, 2) ?></span></div>
    </div>
  </div>
</div>

<script>
const BASE_TOTAL = <?= json_encode((float)$baseTotal) ?>;
const SEAT_TOTAL = <?= json_encode((float)$seatTotal) ?>;
let currentMethod = <?= json_encode($selectedMethod) ?>;
let currentDiscount = <?= json_encode($discountType !== '' ? $discountType : 'none') ?>;
let discountAmount = <?= json_encode((float)$discountAmount) ?>;

function getGrandTotal() {
  return parseFloat((BASE_TOTAL - discountAmount).toFixed(2));
}

function setDiscount(type) {
  currentDiscount = type;
  ['none', 'pwd', 'senior'].forEach(t => {
    const btn = document.getElementById('dBtn-' + t);
    if (btn) btn.classList.toggle('active', t === type);
  });

  const idWrap = document.getElementById('discIdWrap');
  const photoWrap = document.getElementById('idPhotoWrap');
  const preview = document.getElementById('discPreview');
  const discRow = document.getElementById('discountRow');
  const typeHidden = document.getElementById('discountTypeHidden');
  const amountHidden = document.getElementById('discountAmountHidden');
  const photoHidden = document.getElementById('discountPhotoHidden');
  const verifyNotice = document.getElementById('verifyNotice');
  const blurWarning = document.getElementById('idBlurWarning');

  if (type === 'none') {
    discountAmount = 0;
    typeHidden.value = '';
    amountHidden.value = '0.00';
    if (idWrap) idWrap.style.display = 'none';
    if (photoWrap) photoWrap.style.display = 'none';
    if (preview) preview.style.display = 'none';
    if (discRow) discRow.style.display = 'none';
    if (verifyNotice) verifyNotice.style.display = 'none';
    if (blurWarning) blurWarning.style.display = 'none';
    if (photoHidden) photoHidden.value = '';
  } else {
    if (idWrap) idWrap.style.display = 'block';
    if (photoWrap) photoWrap.style.display = 'block';
    if (verifyNotice) verifyNotice.style.display = 'block';
    typeHidden.value = type;
    onDiscIdChange(document.getElementById('discountIdInput').value);
  }

  updateGrandTotal();
  revalidate();
}

function onDiscIdChange(value) {
  const clean = value.trim();
  const idHidden = document.getElementById('discountIdHidden');
  const amountHidden = document.getElementById('discountAmountHidden');
  const preview = document.getElementById('discPreview');
  const discRow = document.getElementById('discountRow');
  const rowLabel = document.getElementById('discountRowLabel');
  const rowVal = document.getElementById('discountRowVal');
  const label = currentDiscount === 'senior' ? 'Senior Citizen' : 'PWD';

  idHidden.value = clean;

  if (currentDiscount !== 'none' && clean) {
    discountAmount = parseFloat((SEAT_TOTAL * 0.20).toFixed(2));
    amountHidden.value = discountAmount.toFixed(2);
    if (preview) {
      preview.style.display = 'block';
      preview.innerHTML = `✓ ${label} discount applied: <strong>- P${discountAmount.toFixed(2)}</strong> off tickets. New total: <strong>P${getGrandTotal().toFixed(2)}</strong>`;
    }
    if (discRow) discRow.style.display = '';
    if (rowLabel) rowLabel.innerHTML = `${currentDiscount === 'senior' ? '&#128116;' : '&#9855;'} ${label} Discount (20%)`;
    if (rowVal) rowVal.textContent = `- P${discountAmount.toFixed(2)}`;
  } else {
    discountAmount = 0;
    amountHidden.value = '0.00';
    if (preview) preview.style.display = 'none';
    if (discRow) discRow.style.display = 'none';
  }

  updateGrandTotal();
}

function updateGrandTotal() {
  const grand = getGrandTotal();
  const totalDisplay = document.getElementById('grandTotalDisplay');
  const totalHidden = document.getElementById('totalPriceHidden');
  if (totalDisplay) totalDisplay.textContent = 'P' + grand.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (totalHidden) totalHidden.value = grand.toFixed(2);
}

function selectMethod(method) {
  currentMethod = method;
  const radio = document.getElementById(method);
  if (radio) radio.checked = true;
  document.querySelectorAll('.method-card').forEach(card => card.classList.remove('selected'));
  const selectedCardInput = document.querySelector('.method-card input[value="' + method + '"]');
  if (selectedCardInput) selectedCardInput.closest('.method-card').classList.add('selected');
  document.querySelectorAll('.fields-wrap').forEach(wrap => wrap.classList.remove('active'));
  const activeWrap = document.getElementById(method + 'Fields');
  if (activeWrap) activeWrap.classList.add('active');
  revalidate();
}

function validateField(el, showErr) {
  const val = el.value.trim();
  const pattern = el.getAttribute('data-pattern');
  const errEl = document.getElementById(el.id + 'Err');
  let ok = true;

  el.classList.remove('valid', 'invalid');
  if (!val) {
    ok = false;
  } else if (el.id === 'cardNumber') {
    ok = el.value.replace(/\D/g, '').length >= 13;
  } else if (el.id === 'expiryDate') {
    ok = /^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(val);
  } else if (el.type === 'email') {
    ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
  } else if (pattern) {
    ok = new RegExp('^' + pattern + '$').test(val);
  }

  if (val) {
    el.classList.add(ok ? 'valid' : 'invalid');
  }
  if (errEl) {
    errEl.style.display = showErr && !ok ? 'block' : 'none';
  }
  return ok;
}

function onCash(val) {
  const cash = parseFloat(val) || 0;
  const change = cash - getGrandTotal();
  const box = document.getElementById('changeBox');
  const amt = document.getElementById('changeAmt');
  document.querySelectorAll('.bill-btn').forEach(btn => btn.classList.toggle('active', parseFloat(btn.dataset.val) === cash));

  if (!val || cash <= 0) {
    amt.textContent = 'P-';
    box.className = 'change-display';
    return false;
  }

  if (change >= 0) {
    amt.textContent = 'P' + change.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    box.className = 'change-display valid';
    return true;
  }

  amt.textContent = '-P' + Math.abs(change).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' short';
  box.className = 'change-display invalid';
  return false;
}

function handleIdPhoto(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  if (file.size > 5 * 1024 * 1024) {
    alert('Photo too large. Please upload an image under 5MB.');
    input.value = '';
    return;
  }

  const reader = new FileReader();
  reader.onload = function(e) {
    const base64 = e.target.result;
    const previewWrap = document.getElementById('idPreviewWrap');
    const previewImg = document.getElementById('idPreviewImg');
    const uploadLabel = document.getElementById('idUploadLabel');
    const badge = document.getElementById('idVerifiedBadge');
    const photoHidden = document.getElementById('discountPhotoHidden');

    if (previewImg) previewImg.src = base64;
    if (previewWrap) previewWrap.style.display = 'block';
    if (uploadLabel) uploadLabel.style.display = 'none';
    if (badge) {
      badge.style.display = 'block';
      badge.innerHTML = '<span class="id-verified-badge">✓ ID Photo Uploaded</span>';
    }
    if (photoHidden) photoHidden.value = base64;
    const blurWarning = document.getElementById('idBlurWarning');
    if (blurWarning) blurWarning.style.display = 'none';
    revalidate();
  };
  reader.readAsDataURL(file);
}

function setBill(val) {
  const input = document.getElementById('cashInput');
  input.value = val;
  onCash(val);
  revalidate();
}

function revalidate() {
  const statusEl = document.getElementById('statusBox');
  const btn = document.getElementById('confirmBtn');

  if (currentDiscount !== 'none' && !document.getElementById('discountIdInput').value.trim()) {
    statusEl.className = 'status-box err';
    statusEl.textContent = 'Please enter the customer PWD or Senior Citizen ID number.';
    statusEl.style.display = 'block';
    btn.disabled = true;
    btn.className = 'btn-confirm';
    btn.textContent = 'Enter ID number to continue';
    return;
  }

  if (currentDiscount !== 'none' && !document.getElementById('discountPhotoHidden').value) {
    statusEl.className = 'status-box err';
    statusEl.textContent = 'Please upload the ID photo before applying the discount.';
    statusEl.style.display = 'block';
    btn.disabled = true;
    btn.className = 'btn-confirm';
    btn.textContent = 'Upload ID photo to continue';
    return;
  }

  if (currentMethod === 'cash') {
    const ok = onCash(document.getElementById('cashInput').value);
    if (!document.getElementById('cashInput').value.trim()) {
      statusEl.style.display = 'none';
      btn.disabled = true;
      btn.className = 'btn-confirm';
      btn.textContent = 'Enter cash amount to continue';
      return;
    }
    if (ok) {
      statusEl.className = 'status-box ok';
      statusEl.textContent = 'Cash is enough to complete the booking.';
      statusEl.style.display = 'block';
      btn.disabled = false;
      btn.className = 'btn-confirm ready';
      btn.textContent = 'Confirm Payment and Book';
    } else {
      statusEl.className = 'status-box err';
      statusEl.textContent = 'Cash amount is still insufficient.';
      statusEl.style.display = 'block';
      btn.disabled = true;
      btn.className = 'btn-confirm';
      btn.textContent = 'Insufficient cash to continue';
    }
    return;
  }

  const fields = document.querySelectorAll('#' + currentMethod + 'Fields input[data-required]');
  let allOk = fields.length > 0;
  let anyFilled = false;
  fields.forEach(field => {
    if (field.value.trim()) anyFilled = true;
    if (!validateField(field, false)) allOk = false;
  });

  if (!anyFilled) {
    statusEl.style.display = 'none';
    btn.disabled = true;
    btn.className = 'btn-confirm';
    btn.textContent = 'Complete all fields to continue';
  } else if (allOk) {
    statusEl.className = 'status-box ok';
    statusEl.textContent = 'All payment details are valid. Ready to book.';
    statusEl.style.display = 'block';
    btn.disabled = false;
    btn.className = 'btn-confirm ready';
    btn.textContent = 'Confirm Payment and Book';
  } else {
    statusEl.className = 'status-box err';
    statusEl.textContent = 'Please complete the required fields correctly.';
    statusEl.style.display = 'block';
    btn.disabled = true;
    btn.className = 'btn-confirm';
    btn.textContent = 'Complete all fields to continue';
  }
}

document.getElementById('cashInput')?.addEventListener('input', function() {
  onCash(this.value);
  revalidate();
});

document.getElementById('cardNumber')?.addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').replace(/(.{4})/g, '$1 ').trim();
  revalidate();
});

document.getElementById('expiryDate')?.addEventListener('input', function() {
  let value = this.value.replace(/\D/g, '');
  if (value.length >= 2) value = value.slice(0, 2) + '/' + value.slice(2, 4);
  this.value = value;
  revalidate();
});

document.querySelectorAll('input[data-required]').forEach(input => {
  input.addEventListener('input', revalidate);
  input.addEventListener('blur', function() {
    validateField(this, true);
    revalidate();
  });
});

document.getElementById('discountIdInput')?.addEventListener('input', function() {
  onDiscIdChange(this.value);
  revalidate();
});

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.disc-btn[data-discount]').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      setDiscount(this.dataset.discount || 'none');
    });
  });

  document.querySelectorAll('.method-card[data-method]').forEach(card => {
    card.addEventListener('click', function(e) {
      e.preventDefault();
      selectMethod(this.dataset.method || 'cash');
    });
  });

  const label = document.getElementById('idUploadLabel');
  if (label) {
    label.addEventListener('dragover', e => { e.preventDefault(); label.style.borderColor = '#ff4d4d'; });
    label.addEventListener('dragleave', () => { label.style.borderColor = ''; });
    label.addEventListener('drop', e => {
      e.preventDefault();
      label.style.borderColor = '';
      const file = e.dataTransfer.files[0];
      if (file && file.type.startsWith('image/')) {
        const input = document.getElementById('idPhotoInput');
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        handleIdPhoto(input);
      }
    });
  }

  const existingPhoto = document.getElementById('discountPhotoHidden')?.value;
  if (existingPhoto) {
    const previewWrap = document.getElementById('idPreviewWrap');
    const previewImg = document.getElementById('idPreviewImg');
    const uploadLabel = document.getElementById('idUploadLabel');
    const badge = document.getElementById('idVerifiedBadge');
    if (previewImg) previewImg.src = existingPhoto;
    if (previewWrap) previewWrap.style.display = 'block';
    if (uploadLabel) uploadLabel.style.display = 'none';
    if (badge) {
      badge.style.display = 'block';
      badge.innerHTML = '<span class="id-verified-badge">✓ ID Photo Uploaded</span>';
    }
  }
});

document.getElementById('payForm').addEventListener('submit', function(e) {
  if (document.getElementById('confirmBtn').disabled) {
    e.preventDefault();
    return;
  }
  if (currentDiscount !== 'none' && !document.getElementById('discountIdInput').value.trim()) {
    e.preventDefault();
    alert('Please enter the customer PWD or Senior Citizen ID number.');
    return;
  }
  if (currentDiscount !== 'none' && !document.getElementById('discountPhotoHidden').value) {
    e.preventDefault();
    alert('Please upload the discount ID photo.');
    return;
  }
  if (currentMethod !== 'cash') {
    document.querySelectorAll('#' + currentMethod + 'Fields input[data-required]').forEach(field => validateField(field, true));
  }
});

selectMethod(currentMethod);
setDiscount(currentDiscount);
</script>
</body>
</html>
