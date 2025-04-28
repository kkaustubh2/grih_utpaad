<?php
require_once('../../includes/auth.php');

// Additional role check for consumer
if ($_SESSION['user']['role'] !== 'consumer') {
    header('Location: ../../index.php');
    exit();
}

require_once('../../config/db.php');

// Fetch cart items with product details
$query = "SELECT c.*, p.title, p.price, p.image, u.name as seller_name 
          FROM cart c 
          JOIN products p ON c.product_id = p.id 
          JOIN users u ON p.user_id = u.id 
          WHERE c.consumer_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user']['id']);
$stmt->execute();
$cart_items = $stmt->get_result();

// Calculate total
$total = 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Cart - Grih Utpaad</title>
    <link rel="stylesheet" href="../../assets/uploads/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-image: url('../../assets/images/background.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            font-family: 'Segoe UI', sans-serif;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.85);
            z-index: 1;
        }

        .container {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .cart-container {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 2fr 1fr 1fr auto;
            gap: 20px;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: #fff;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .product-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 8px;
        }

        .product-info h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1.2rem;
        }

        .seller-info {
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .seller-info i {
            color: #007B5E;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            background: #007B5E;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: #005b46;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .quantity {
            font-size: 1.1rem;
            font-weight: 500;
            color: #2c3e50;
        }

        .price {
            color: #007B5E;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .remove-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            padding: 8px;
            border-radius: 8px;
        }

        .remove-btn:hover {
            color: #c82333;
            background: rgba(220, 53, 69, 0.1);
        }

        .cart-summary {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total {
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .total span {
            color: #007B5E;
            font-weight: 600;
        }

        .checkout-btn {
            background: #007B5E;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .checkout-btn:hover {
            background: #005b46;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .empty-cart {
            text-align: center;
            padding: 50px 20px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .empty-cart i {
            font-size: 48px;
            color: #007B5E;
            margin-bottom: 20px;
        }

        .empty-cart h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .empty-cart p {
            color: #6c757d;
            margin-bottom: 25px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: #007B5E;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .back-link:hover {
            color: #005b46;
            transform: translateX(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 15px;
            }

            .product-image {
                margin: 0 auto;
            }

            .quantity-controls {
                justify-content: center;
            }

            .cart-summary {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="view_product.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Continue Shopping
        </a>

        <div class="cart-container">
            <h1 style="margin-top: 0; color: #2c3e50;">My Cart</h1>

            <?php if ($cart_items->num_rows > 0): ?>
                <?php while ($item = $cart_items->fetch_assoc()):
                    $subtotal = $item['price'] * $item['quantity'];
                    $total += $subtotal;
                ?>
                    <div class="cart-item">
                        <img src="../../assets/uploads/<?= htmlspecialchars($item['image']) ?>" 
                             alt="<?= htmlspecialchars($item['title']) ?>" 
                             class="product-image">
                        
                        <div class="product-info">
                            <h3><?= htmlspecialchars($item['title']) ?></h3>
                            <div class="seller-info">
                                <i class="fas fa-store"></i>
                                <?= htmlspecialchars($item['seller_name']) ?>
                            </div>
                        </div>

                        <div class="quantity-controls">
                            <form action="update_cart.php" method="POST" style="display: inline;">
                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="action" value="decrease">
                                <button type="submit" class="quantity-btn">-</button>
                            </form>
                            
                            <span class="quantity"><?= $item['quantity'] ?></span>
                            
                            <form action="update_cart.php" method="POST" style="display: inline;">
                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="action" value="increase">
                                <button type="submit" class="quantity-btn">+</button>
                            </form>
                        </div>

                        <div class="price">
                            ₹<?= number_format($subtotal, 2) ?>
                        </div>

                        <form action="update_cart.php" method="POST">
                            <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                            <input type="hidden" name="action" value="remove">
                            <button type="submit" class="remove-btn" title="Remove item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                <?php endwhile; ?>

                <div class="cart-summary">
                    <div class="total">
                        Total: ₹<?= number_format($total, 2) ?>
                    </div>
                    <form action="place_order.php" method="POST">
                        <input type="hidden" name="action" value="cart_order">
                        <button type="submit" class="checkout-btn">
                            <i class="fas fa-shopping-cart"></i>
                            Place Order
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Your cart is empty</h2>
                    <p>Browse our products and add items to your cart!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 