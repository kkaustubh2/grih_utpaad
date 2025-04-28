<?php
session_start();

// Check if user is logged in and is a female householder
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'female_householder') {
    header('Location: ../../login.php');
    exit();
}

require_once('../../config/db.php');

$success = '';
$errors = [];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch product details
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $product_id, $_SESSION['user']['id']);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header('Location: my_products.php');
    exit();
}

// Fetch categories for dropdown
$categories = $conn->query("SELECT id, name FROM product_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category'];
    $price = (float)$_POST['price'];
    
    // Validate inputs
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0.";
    }
    
    // Handle image upload if new image is provided
    if (!empty($_FILES['image']['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image']['type'], $allowedTypes)) {
            $errors[] = "Invalid file type. Only JPG, PNG and GIF are allowed.";
        } else {
            $imageName = time() . '_' . $_FILES['image']['name'];
            if (move_uploaded_file($_FILES['image']['tmp_name'], "../../assets/uploads/" . $imageName)) {
                // Delete old image if exists
                if ($product['image'] && file_exists("../../assets/uploads/" . $product['image'])) {
                    unlink("../../assets/uploads/" . $product['image']);
                }
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    } else {
        $imageName = $product['image'];
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE products SET title = ?, description = ?, category_id = ?, price = ?, image = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ssidsii", $title, $description, $category_id, $price, $imageName, $product_id, $_SESSION['user']['id']);
        if ($stmt->execute()) {
            $success = "Product updated successfully!";
            // Refresh product data
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $product_id, $_SESSION['user']['id']);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = "Failed to update product.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product - Grih Utpaad</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
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
            display: flex;
            flex-direction: column;
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

        .main-wrapper {
            flex: 1;
            position: relative;
            z-index: 2;
            padding: 40px 0;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header h2 {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #007B5E;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: #005b46;
            transform: translateY(-2px);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #dce4ec;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #007B5E;
            box-shadow: 0 0 0 2px rgba(0, 123, 94, 0.2);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .current-image {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .current-image img {
            max-width: 200px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .image-caption {
            margin-top: 10px;
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn {
            background: #007B5E;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #005b46;
            transform: translateY(-2px);
        }

        input[type="file"] {
            padding: 10px;
            border: 2px dashed #dce4ec;
            border-radius: 6px;
            width: 100%;
            box-sizing: border-box;
            margin-top: 5px;
            cursor: pointer;
        }

        input[type="file"]:hover {
            border-color: #007B5E;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .card {
                padding: 20px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="container">
            <a href="view_products.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to My Products
            </a>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-edit"></i> Edit Product</h2>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <div><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Product Title</label>
                        <input type="text" id="title" name="title" class="form-control" required 
                               value="<?php echo htmlspecialchars($product['title']); ?>"
                               placeholder="Enter product title">
                    </div>

                    <div class="form-group">
                        <label for="description">Product Description</label>
                        <textarea id="description" name="description" class="form-control" required rows="4"
                                  placeholder="Enter product description"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select name="category" id="category" class="form-control" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (₹)</label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" required 
                               value="<?php echo $product['price']; ?>"
                               placeholder="Enter product price">
                    </div>

                    <div class="form-group">
                        <label for="image">Product Image</label>
                        <?php if ($product['image']): ?>
                            <div class="current-image">
                                <img src="../../assets/uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                                     alt="Current product image">
                                <p class="image-caption">
                                    <i class="fas fa-info-circle"></i> Current image. Upload a new one to change it.
                                </p>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="image" name="image" accept="image/*">
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php include('../../includes/footer.php'); ?>
</body>
</html>
