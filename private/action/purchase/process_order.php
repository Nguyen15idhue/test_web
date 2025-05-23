<?php
// filepath: e:\Application\laragon\www\surveying_account\private\action\purchase\process_order.php
session_start();

// --- Project Root Path ---
$project_root_path = dirname(dirname(dirname(__DIR__))); // Adjust if necessary

// --- Include Required Files ---
require_once $project_root_path . '/private/config/config.php';
require_once $project_root_path . '/private/classes/Database.php';
require_once $project_root_path . '/private/classes/Package.php';
require_once $project_root_path . '/private/classes/Location.php';
require_once $project_root_path . '/private/utils/functions.php'; // For calculateEndTime, etc.

// --- Base URL for Redirects ---
// You might want to centralize this logic or pass it differently
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
// Adjust base path calculation if needed, assuming this action is called directly
// This might need refinement depending on your server setup and how URLs are handled.
// A robust solution often involves a configuration setting for the base URL.
$base_url = $protocol . $domain . '/surveying_account'; // Adjust '/surveying_account' if needed


// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_url . '/public/pages/auth/login.php?error=not_logged_in');
    exit;
}
$user_id = $_SESSION['user_id'];

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_url . '/public/pages/purchase/packages.php?error=invalid_request');
    exit;
}

// --- Get POST Data ---
$package_int_id = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
$location_id = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
$submitted_total_price = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_FLOAT); // Get price submitted by form

// --- Basic Input Validation ---
if (!$package_int_id || !$quantity || $quantity < 1 || !$location_id || $submitted_total_price === false) {
     // Log the invalid data for debugging
     error_log("Invalid input received in process_order.php: package_id=$package_int_id, quantity=$quantity, location_id=$location_id, total_price=$submitted_total_price");
     header('Location: ' . $base_url . '/public/pages/purchase/packages.php?error=invalid_input');
     exit;
}


// --- Database Operations ---
$db = new Database();
$conn = $db->connect();
$package_obj = new Package(); // Use existing connection if possible, or let it create new
$location_obj = new Location();

try {
    // --- Validate Package ID and Get Details ---
    $package = $package_obj->getPackageById($package_int_id);
    if (!$package) {
        header('Location: ' . $base_url . '/public/pages/purchase/packages.php?error=package_not_found');
        exit;
    }
    $base_price = $package['price'];
    $package_duration_text = $package['duration_text']; // e.g., '1 Tháng', '1 Năm'

    // --- Validate Location ID ---
    if (!$location_obj->locationExists($location_id)) {
        header('Location: ' . $base_url . '/public/pages/purchase/details.php?package=' . $package_int_id . '&error=invalid_location'); // Redirect back to details
        exit;
    }

    // --- Server-Side Price Calculation (Security Measure) ---
    $calculated_total_price = $base_price * $quantity;
    // Optional: Add VAT calculation if needed based on user profile or settings
    $vat_percent = 0; // Example: Get from user settings or config
    $vat_amount = $calculated_total_price * ($vat_percent / 100);
    $final_total_price = $calculated_total_price + $vat_amount;

    // --- Compare Server Calculated Price with Submitted Price (Tolerance for float issues) ---
     if (abs($final_total_price - $submitted_total_price) > 0.01) { // Allow small tolerance
         error_log("Price mismatch: Server calculated=$final_total_price, Submitted=$submitted_total_price for package_id=$package_int_id, quantity=$quantity");
         header('Location: ' . $base_url . '/public/pages/purchase/details.php?package=' . $package_int_id . '&error=price_mismatch');
         exit;
     }


    // --- Calculate Start and End Times ---
    $start_time = date('Y-m-d H:i:s');
    // You need a robust function to calculate end time based on $package_duration_text
    // This is a simplified example, replace with your actual logic
    $end_time = calculateEndTime($start_time, $package_duration_text);
    if (!$end_time) {
         error_log("Could not calculate end time for duration: " . $package_duration_text);
         header('Location: ' . $base_url . '/public/pages/purchase/details.php?package=' . $package_int_id . '&error=duration_error');
         exit;
    }


    // --- Insert into Registration Table ---
    $stmt = $conn->prepare("INSERT INTO registration (user_id, package_id, location_id, num_account, start_time, end_time, base_price, vat_percent, vat_amount, total_price, status, created_at) VALUES (:user_id, :package_id, :location_id, :num_account, :start_time, :end_time, :base_price, :vat_percent, :vat_amount, :total_price, 'pending', NOW())");

    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':package_id', $package_int_id, PDO::PARAM_INT);
    $stmt->bindParam(':location_id', $location_id, PDO::PARAM_INT);
    $stmt->bindParam(':num_account', $quantity, PDO::PARAM_INT);
    $stmt->bindParam(':start_time', $start_time, PDO::PARAM_STR);
    $stmt->bindParam(':end_time', $end_time, PDO::PARAM_STR);
    $stmt->bindParam(':base_price', $base_price); // PDO determines type
    $stmt->bindParam(':vat_percent', $vat_percent);
    $stmt->bindParam(':vat_amount', $vat_amount);
    $stmt->bindParam(':total_price', $final_total_price);

    if ($stmt->execute()) {
        $registration_id = $conn->lastInsertId();
        // Store registration ID in session for the payment page
        $_SESSION['pending_registration_id'] = $registration_id;
        $_SESSION['pending_total_price'] = $final_total_price; // Store price for display on payment page

        // --- Redirect to Payment Page ---
        header('Location: ' . $base_url . '/public/pages/purchase/payment.php');
        exit;
    } else {
        error_log("Failed to insert registration record for user_id: " . $user_id);
        header('Location: ' . $base_url . '/public/pages/purchase/details.php?package=' . $package_int_id . '&error=registration_failed');
        exit;
    }

} catch (PDOException $e) {
    error_log("Database error in process_order.php: " . $e->getMessage());
    header('Location: ' . $base_url . '/public/pages/purchase/packages.php?error=database_error');
    exit;
} catch (Exception $e) {
    error_log("General error in process_order.php: " . $e->getMessage());
     header('Location: ' . $base_url . '/public/pages/purchase/packages.php?error=unknown_error');
     exit;
} finally {
    // Close connections if the classes don't handle it internally on destruction
    $package_obj->closeConnection();
    $location_obj->closeConnection();
    $db->close();
}

// --- Helper function (should be in utils/functions.php) ---
// --- THIS IS A PLACEHOLDER - IMPLEMENT PROPERLY ---
function calculateEndTime(string $startTime, string $durationText): ?string {
    try {
        $date = new DateTime($startTime);
        // Very basic parsing - needs improvement for robustness
        if (str_contains(strtolower($durationText), 'tháng')) {
            $months = (int)filter_var($durationText, FILTER_SANITIZE_NUMBER_INT);
            if ($months > 0) $date->modify("+$months months");
            else return null;
        } elseif (str_contains(strtolower($durationText), 'năm')) {
             $years = (int)filter_var($durationText, FILTER_SANITIZE_NUMBER_INT);
             if ($years > 0) $date->modify("+$years years");
             else return null;
        } elseif (str_contains(strtolower($durationText), 'vĩnh viễn')) {
            // Handle 'lifetime' - maybe set a far future date or a specific flag/NULL?
            // Setting a very far future date for simplicity here
             $date->setDate(9999, 12, 31);
        } else {
            // Unknown duration format
            return null;
        }
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Error calculating end time: " . $e->getMessage());
        return null;
    }
}
?>