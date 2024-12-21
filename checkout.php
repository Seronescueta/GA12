<?php
session_start();
require_once 'db.php';  // Assuming you have a separate file for database connection

// Prevent browser cache
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Ensure cart is not empty
if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

// Initialize variables for checkout form
$name = $address = $phone = '';
$totalPrice = 0;
$products = $_SESSION['cart'];

// Calculate total price
foreach ($products as $product) {
    $totalPrice += $product['price'] * $product['quantity'];
}

// Handle form submission (when order is confirmed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    // Ensure the user is logged in
    if (!isset($_SESSION['user_id'])) {
        // Redirect to login page if user is not logged in
        header("Location: login.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];  // Get the actual user ID from session

    // Collect order details
    $product_ids = array_map(function($product) {
        return $product['id'];  // Collecting product IDs for the order
    }, $products);

    // Convert product IDs into a comma-separated string
    $product_ids_string = implode(',', $product_ids);

    // Insert order into database
    $stmt = $conn->prepare("INSERT INTO orders (user_id, product_ids, total_price, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("isd", $user_id, $product_ids_string, $totalPrice);
    if ($stmt->execute()) {
        // After the order is inserted
        $order_id = $conn->insert_id;  // Get the last inserted order ID
        $_SESSION['order_id'] = $order_id;  // Store the order ID in session

        // Clear the cart after order is placed
        unset($_SESSION['cart']);

        // Redirect to order confirmation page
        header("Location: order_confirmation.php");
        exit;
    } else {
        echo "Error: " . $stmt->error;  // Handle any errors
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOODZIE Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 1.5rem;
        }
        .order-summary {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .order-summary li {
            font-size: 1rem;
            border-bottom: 1px solid #ddd;
            padding: 10px 0;
        }
        .order-summary li:last-child {
            border-bottom: none;
        }
        .order-summary .fw-bold {
            color: #333;
        }
        .form-label {
            font-weight: bold;
        }
        .form-control {
            border-radius: 5px;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .footer {
            background-color: #343a40;
            color: #fff;
            padding: 20px 0;
        }
        .footer p {
            margin: 0;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">FOODZIE</a>
        </div>
    </nav>

    <!-- Checkout Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title text-center">Checkout</h2>
            <div class="row">
                <!-- Order Summary -->
                <div class="col-md-6 order-summary">
                    <h4 class="fw-bold">Order Summary</h4>
                    <ul>
                        <?php foreach ($products as $product): ?>
                            <li class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold"><?= htmlspecialchars($product['name']) ?></span> 
                                    <small class="text-muted">(x<?= $product['quantity'] ?>)</small>
                                </div>
                                <span>₱<?= number_format($product['price'] * $product['quantity'], 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <h5 class="mt-3 text-end fw-bold">Total: ₱<?= number_format($totalPrice, 2) ?></h5>
                </div>

                <!-- Shipping Form -->
                <div class="col-md-6">
                    <h4 class="fw-bold">Shipping Information</h4>
                    <form method="POST" action="checkout.php">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" required>
                        </div>
                        <button type="submit" name="confirm_order" class="btn btn-success w-100">Place Order</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer text-center">
        <p>&copy; 2024 FOODZIE. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
