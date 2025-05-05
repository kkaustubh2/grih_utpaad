<?php
session_start();
require_once('config/db.php');

// Fetch featured products (latest 6 approved products)
$query = "SELECT p.*, u.name as seller_name 
          FROM products p 
          LEFT JOIN users u ON p.user_id = u.id 
          WHERE p.is_approved = 1 
          ORDER BY p.created_at DESC 
          LIMIT 6";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Grih Utpaad - Empowering Women Entrepreneurs</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-image-container {
            width: 100%;
            height: 300px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .product-image {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
        }

        .product-info {
            padding: 20px;
        }

        .product-category {
            color: #007B5E;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .product-title {
            color: #2c3e50;
            font-size: 18px;
            margin: 0 0 10px;
        }

        .product-price {
            color: #007B5E;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 15px;
        }

        .product-seller {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .view-btn {
            background: #007B5E;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
            text-align: center;
        }

        .view-btn:hover {
            background: #005b46;
        }

        .section-title {
            text-align: center;
            margin: 40px 0 20px;
            color: #2c3e50;
            font-size: 28px;
        }

        .view-all-btn {
            display: block;
            text-align: center;
            margin: 30px auto;
            padding: 12px 24px;
            background-color: #007B5E;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s ease;
            width: fit-content;
        }

        .view-all-btn:hover {
            background-color: #005c45;
        }

        .no-products {
            grid-column: 1/-1;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 15px;
            color: #6c757d;
        }

        .features {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin: 40px 0;
            position: relative;
            z-index: 2;
        }

        .features .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            text-align: center;
            transition: transform 0.3s ease;
            width: 280px;
        }

        .features .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .features .card i {
            font-size: 40px;
            color: #007B5E;
            margin-bottom: 20px;
            display: block;
        }

        .features .card h3 {
            color: #2c3e50;
            font-size: 20px;
            margin: 0 0 15px;
        }

        .features .card p {
            color: #6c757d;
            margin: 0;
            line-height: 1.5;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007B5E;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }

        .btn:hover {
            background-color: #005b46;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body class="index-page">

<div class="container">
    <div class="hero card" style="padding: 40px; margin-bottom: 30px;">
        <h1 style="color: #007B5E; font-size: 40px; margin-bottom: 10px;">Welcome to Grih Utpaad</h1>
        <p style="font-size: 18px; margin-bottom: 20px;">
            Empowering women to showcase and sell homemade products & skills online.
        </p>

        <?php if (isset($_SESSION['user'])): ?>
            <p>Hello, <?= htmlspecialchars($_SESSION['user']['name']) ?>!</p>
            <?php if ($_SESSION['user']['role'] === 'female_householder'): ?>
                <a href="dashboards/female_householders/dashboard.php" class="btn">🏠 Seller Dashboard</a>
            <?php elseif ($_SESSION['user']['role'] === 'consumer'): ?>
                <a href="dashboards/consumers/index.php" class="btn">🛒 Consumer Dashboard</a>
            <?php elseif ($_SESSION['user']['role'] === 'admin'): ?>
                <a href="dashboards/admin/dashboard.php" class="btn">👩‍💼 Admin Dashboard</a>
            <?php endif; ?>
            <a href="auth/logout.php" class="btn" style="background: #d9534f;">Logout</a>
        <?php else: ?>
            <div style="display: flex; gap: 10px; align-items: center; justify-content: center; flex-wrap: wrap;">
                <a href="auth/login.php" class="btn">🔐 Login</a>
                <a href="auth/register.php" class="btn" style="background-color: #28a745;">📝 Register</a>
                <a href="dashboards/admin/login.php" class="btn" style="background-color: #6c757d;">
                    <i class="fas fa-user-shield"></i> Login as Admin
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Featured Products Section -->
    <h2 class="section-title">Featured Products</h2>
    <div class="products-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($product = $result->fetch_assoc()): ?>
                <div class="product-card">
                    <div class="product-image-container">
                        <?php 
                            $image_path = 'assets/uploads/' . $product['image'];
                            if (!empty($product['image']) && file_exists($image_path)): 
                        ?>
                            <img src="<?= htmlspecialchars($image_path) ?>" 
                                 alt="<?= htmlspecialchars($product['title']) ?>" 
                                 class="product-image">
                        <?php else: ?>
                            <div class="product-image-placeholder">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <div class="product-category">
                            <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?>
                        </div>
                        <h3 class="product-title"><?= htmlspecialchars($product['title']) ?></h3>
                        <p class="product-price">₹<?= number_format($product['price'], 2) ?></p>
                        <p class="product-seller">By <?= htmlspecialchars($product['seller_name']) ?></p>
                        <a href="product_detail.php?id=<?= $product['id'] ?>" class="view-btn" style="width: 100%; display: block; margin-top: 15px;">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-products">
                <i class="fas fa-box-open" style="font-size: 48px; color: #6c757d; margin-bottom: 20px;"></i>
                <h3>No Products Available</h3>
                <p>Check back soon for exciting products from our sellers!</p>
            </div>
        <?php endif; ?>
    </div>

    <a href="products.php" class="view-all-btn">View All Products</a>

    <!-- Features Section -->
    <div class="features">
        <div class="card">
            <i class="fas fa-store"></i>
            <h3>Sell Handmade Products</h3>
            <p>List crafts, food items, services, and more.</p>
        </div>
        <div class="card">
            <i class="fas fa-shopping-cart"></i>
            <h3>Shop with Purpose</h3>
            <p>Explore & buy unique goods directly from creators.</p>
        </div>
        <div class="card">
            <i class="fas fa-user-shield"></i>
            <h3>Admin Monitoring</h3>
            <p>Ensuring safe, authentic experiences.</p>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>

</body>
</html>
