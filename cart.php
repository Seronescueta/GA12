<?php
session_start();

// Prevent browser cache
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Database connection
$dsn = 'mysql:host=localhost;dbname=foodzie';
$username = 'root';
$password = '';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; // Get the logged-in user's ID

// Handle "Remove from Cart" request
if (isset($_GET['remove'])) {
    $product_id = $_GET['remove'];

    // Remove the product from the database-based cart
    $stmt = $pdo->prepare("DELETE FROM carts WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);

    // Return updated cart count and total price
    $cartCount = getCartCount($pdo, $user_id);
    $totalPrice = calculateTotalPrice($pdo, $user_id);
    echo json_encode(['cart_count' => $cartCount, 'total_price' => number_format($totalPrice, 2)]);
    exit();
}

// Handle "Update Cart" request via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_cart') {
    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    if ($quantity > 0) {
        // Update quantity in the database
        $stmt = $pdo->prepare("UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$quantity, $user_id, $product_id]);
    } else {
        // Remove product from cart
        $stmt = $pdo->prepare("DELETE FROM carts WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
    }

    // Calculate the total price
    $totalPrice = calculateTotalPrice($pdo, $user_id);

    echo json_encode([
        'cart_count' => getCartCount($pdo, $user_id),
        'total_price' => number_format($totalPrice, 2),
    ]);
    exit();
}

// Function to get the cart count
function getCartCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) AS total_quantity FROM carts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['total_quantity'] ?? 0;
}

// Function to calculate the total price
function calculateTotalPrice($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT SUM(p.price * c.quantity) AS total_price
        FROM carts c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['total_price'] ?? 0.0;
}

// Get products in the cart
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.price, p.image, c.quantity
    FROM carts c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOODZIE Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>
    <!-- Navbar -->
    <div class="container-fluid">
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">FOODZIE</a>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link" href="index.php#home">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php#products">Products</a></li>
                        <li class="nav-item"><a class="nav-link" href="checkout.php">Checkout</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php#about">About Us</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>

    <!-- Cart Section -->
    <section id="cart" class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">Your Cart</h2>
            <?php if (empty($products)): ?>
                <p class="text-center">Your cart is empty.</p>
            <?php else: ?>
                <div class="row">
                    <?php
                    $totalPrice = 0;
                    foreach ($products as $product):
                        $subtotal = $product['price'] * $product['quantity'];
                        $totalPrice += $subtotal;
                        $imagePath = "images/" . $product['image']; // Ensure correct path for images
                    ?>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <!-- Displaying the product image -->
                                <img src="<?= htmlspecialchars($imagePath) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                    <p class="price">₱<?= number_format($product['price'], 2) ?></p>
                                    <form method="POST" class="d-flex align-items-center update-cart-form">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                        <input type="number" name="quantity" value="<?= $product['quantity'] ?>" min="1" class="form-control me-2" style="width: 80px;">
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </form>
                                    <a href="?remove=<?= $product['id'] ?>" class="btn btn-danger mt-2 remove-from-cart" data-product-id="<?= $product['id'] ?>">Remove</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-4">
                    <h4>Total: ₱<span id="total-price"><?= number_format($totalPrice, 2) ?></span></h4>
                    
                    <!-- Store cart details in session for checkout -->
                    <?php 
                    $_SESSION['cart'] = $products; // Save cart data to session
                    $_SESSION['total_price'] = $totalPrice; // Save total price to session
                    ?>

                    <a href="checkout.php" class="btn btn-success">Proceed to Checkout</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <p>&copy; 2024 FOODZIE. All rights reserved.</p>
    </footer>

    <script>
        // Handle updating cart quantity via AJAX
        $(document).on('submit', '.update-cart-form', function (e) {
            e.preventDefault();
            const form = $(this);
            const productId = form.find('input[name="product_id"]').val();
            const quantity = form.find('input[name="quantity"]').val();

            $.post("cart.php", {
                action: 'update_cart',
                product_id: productId,
                quantity: quantity
            }, function (response) {
                const data = JSON.parse(response);
                if (data.cart_count !== undefined) {
                    $('#cart-count').text(data.cart_count); // Update cart count in navbar
                }
                if (data.total_price !== undefined) {
                    $('#total-price').text(data.total_price); // Update total price dynamically
                }
            });
        });

        // Handle removing item from cart via AJAX
        $(document).on('click', '.remove-from-cart', function (e) {
            e.preventDefault();
            const productId = $(this).data('product-id');
            $.get("cart.php?remove=" + productId, function (response) {
                const data = JSON.parse(response);
                $('#cart-count').text(data.cart_count); // Update cart count in navbar
                $('#total-price').text(data.total_price); // Update total price dynamically
                location.reload(); // Refresh the page to update cart
            });
        });
    </script>
</body>
</html>
