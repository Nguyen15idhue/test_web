<?php
session_start();

// --- Base URL Configuration ---
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['PHP_SELF']); // /pages/purchase
$base_project_dir = dirname(dirname($script_dir)); // Lùi 2 cấp để đến thư mục gốc dự án
$base_url = $protocol . $domain . ($base_project_dir === '/' || $base_project_dir === '\\' ? '' : $base_project_dir);

// --- Project Root Path for Includes ---
$project_root_path = dirname(dirname(dirname(__DIR__))); // Should point to surveying_account

// --- Include Required Files ---
require_once $project_root_path . '/private/config/config.php';
require_once $project_root_path . '/private/classes/Database.php';
require_once $project_root_path . '/private/classes/Package.php'; // Assuming Package class exists
require_once $project_root_path . '/private/classes/Location.php'; // Assuming Location class exists

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_url . '/public/pages/auth/login.php'); // Corrected login path
    exit;
}

// --- Get Selected Package ID (varchar) from URL ---
$selected_package_varchar_id = $_GET['package'] ?? null;

// --- Fetch Package Details from Database ---
$package_obj = new Package();
$selected_package = $package_obj->getPackageByVarcharId($selected_package_varchar_id);
$package_obj->closeConnection(); // Close connection after fetching package

// --- Validate Selected Package ---
if (!$selected_package) {
    // If package not found, redirect back to packages page
    header('Location: ' . $base_url . '/public/pages/purchase/packages.php?error=invalid_package');
    exit;
}

// --- Check if it's a "Contact Us" package ---
$is_contact_package = ($selected_package['button_text'] === 'Liên hệ mua');
if ($is_contact_package) {
    // Redirect or display contact information - For now, redirect back with a message
    header('Location: ' . $base_url . '/public/pages/purchase/packages.php?info=contact_required&package_name=' . urlencode($selected_package['name']));
    exit;
    // Alternatively, you could display a contact message on this page itself
    // and disable the form.
}

$base_price = $selected_package['price']; // Get price from DB

// --- Fetch List of Provinces/Cities from Database ---
$location_obj = new Location();
$provinces = $location_obj->getAllProvinces(); // Assumes a method getAllProvinces() exists
$location_obj->closeConnection(); // Close connection after fetching locations

// --- User Info ---
$user_username = $_SESSION['username'] ?? 'Người dùng';

// --- Include Header ---
// Note: Adjust the path if header.php is in public/includes
include $project_root_path . '/private/includes/header.php';
?>

<!-- CSS cho Trang Chi Tiết Mua Hàng (Keep existing styles) -->
<style>
    /* ... (Existing CSS styles remain unchanged) ... */
    .purchase-details-form {
        background-color: white;
        padding: 2rem;
        border-radius: var(--rounded-lg);
        border: 1px solid var(--gray-200);
        max-width: 600px; /* Giới hạn chiều rộng form */
        margin: 2rem auto; /* Căn giữa form */
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        font-weight: var(--font-medium);
        color: var(--gray-700);
        margin-bottom: 0.5rem;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--gray-300);
        border-radius: var(--rounded-md);
        font-size: var(--font-size-base);
        transition: border-color 0.2s ease;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);
    }
    /* Style cho input[type=number] */
    input[type=number] {
        -moz-appearance: textfield; /* Firefox */
    }
    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .selected-package-info {
        background-color: var(--gray-50);
        padding: 1rem 1.5rem;
        border-radius: var(--rounded-md);
        margin-bottom: 1.5rem;
        border: 1px dashed var(--gray-200);
    }
    .selected-package-info strong {
        color: var(--primary-600);
    }

    .total-price-display {
        font-size: 1.25rem;
        font-weight: var(--font-semibold);
        color: var(--gray-800);
        margin-top: 1rem;
        text-align: right;
    }
     .total-price-display span {
         color: var(--primary-600);
         font-weight: var(--font-bold);
     }

    .btn-submit {
        display: block;
        width: 100%;
        padding: 0.8rem 1.5rem;
        background-color: var(--success-500, #10B981); /* Green color, fallback hex */
        color: white;
        border: none;
        border-radius: var(--rounded-md);
        font-weight: var(--font-semibold);
        text-decoration: none;
        transition: background-color 0.2s ease;
        cursor: pointer;
        font-size: var(--font-size-base);
        text-align: center;
    }

    .btn-submit:hover {
        background-color: var(--success-600, #059669); /* Darker green on hover */
    }

     @media (max-width: 768px) {
        .content-wrapper {
            padding: 1rem !important;
        }
        .purchase-details-form {
            margin-top: 1rem;
            padding: 1.5rem;
        }
    }
</style>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <?php // Note: Adjust the path if sidebar.php is in public/includes
          include $project_root_path . '/private/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="content-wrapper">
        <h2 class="text-2xl font-semibold mb-4">Chi tiết mua hàng</h2>

        <!-- Action của form trỏ đến process_order.php -->
        <form action="<?php echo $base_url; ?>/private/action/purchase/process_order.php" method="POST" class="purchase-details-form" id="details-form">
            <!-- Thông tin gói đã chọn -->
            <div class="selected-package-info">
                Bạn đang chọn: <strong><?php echo htmlspecialchars($selected_package['name']); ?></strong>
                 (<?php echo htmlspecialchars($selected_package['duration_text']); ?>)
            </div>

            <!-- Input ẩn để gửi thông tin gói -->
            <!-- Submit package.id (INT Primary Key) for foreign key relation -->
            <input type="hidden" name="package_id" value="<?php echo htmlspecialchars($selected_package['id']); ?>">
            <input type="hidden" name="package_name" value="<?php echo htmlspecialchars($selected_package['name']); ?>">
            <input type="hidden" name="base_price" id="base_price" value="<?php echo $base_price; ?>"> <!-- Giá gốc để JS tính toán -->
            <input type="hidden" name="total_price" id="total_price_hidden" value="<?php echo $base_price; ?>"> <!-- Giá tổng, sẽ được JS cập nhật -->

            <!-- Số lượng tài khoản -->
            <div class="form-group">
                <label for="quantity">Số lượng tài khoản:</label>
                <input type="number" id="quantity" name="quantity" class="form-control" value="1" min="1" required>
            </div>

            <!-- Chọn Tỉnh/Thành phố -->
            <div class="form-group">
                <label for="location_id">Tỉnh/Thành phố sử dụng:</label>
                <select id="location_id" name="location_id" class="form-control" required>
                    <option value="" disabled selected>-- Chọn Tỉnh/Thành phố --</option>
                    <?php foreach ($provinces as $province): ?>
                        <option value="<?php echo htmlspecialchars($province['id']); ?>">
                            <?php echo htmlspecialchars($province['province']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

             <!-- Hiển thị tổng tiền (cập nhật bằng JS) -->
            <div class="total-price-display">
                Tổng cộng: <span id="total-price-view"><?php echo number_format($base_price, 0, ',', '.'); ?>đ</span>
            </div>

            <!-- Nút chuyển đến thanh toán -->
            <div class="form-group" style="margin-top: 2rem; margin-bottom: 0;">
                <button type="submit" class="btn-submit">Tiếp tục đến Thanh toán</button>
            </div>
        </form>

    </main>
</div>

<!-- JavaScript để cập nhật giá tiền (Keep existing script) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const basePrice = parseFloat(document.getElementById('base_price').value);
    const totalPriceView = document.getElementById('total-price-view');
    const totalPriceHidden = document.getElementById('total_price_hidden');

    function updateTotalPrice() {
        let quantity = parseInt(quantityInput.value);
        // Đảm bảo số lượng hợp lệ (ít nhất là 1)
        if (isNaN(quantity) || quantity < 1) {
            quantity = 1;
            quantityInput.value = 1; // Sửa lại input nếu không hợp lệ
        }

        const total = basePrice * quantity;

        // Cập nhật giá hiển thị (dùng toLocaleString để format tiền tệ VNĐ)
        totalPriceView.textContent = total.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' });

        // Cập nhật giá trị input ẩn để gửi đi (giá trị số thuần túy)
        totalPriceHidden.value = total;
    }

    // Gọi hàm lần đầu khi tải trang
    updateTotalPrice();

    // Thêm sự kiện lắng nghe khi giá trị số lượng thay đổi
    quantityInput.addEventListener('input', updateTotalPrice);

    // Ngăn chặn submit nếu chưa chọn tỉnh thành
    const form = document.getElementById('details-form');
    const locationSelect = document.getElementById('location_id'); // Changed ID
    form.addEventListener('submit', function(event) {
        if (!locationSelect.value) {
            alert('Vui lòng chọn Tỉnh/Thành phố sử dụng.');
            event.preventDefault(); // Ngăn form gửi đi
            locationSelect.focus();
            return; // Dừng thực thi thêm
        }
        // Cập nhật giá lần cuối trước khi submit phòng trường hợp JS lỗi hoặc người dùng sửa đổi nhanh
        updateTotalPrice();
    });
});
</script>

<?php
// --- Include Footer ---
// Note: Adjust the path if footer.php is in public/includes
include $project_root_path . '/private/includes/footer.php';
?>