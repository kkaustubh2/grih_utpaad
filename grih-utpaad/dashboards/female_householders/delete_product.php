<?php
session_start();

// Check if user is logged in and is a female householder
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'female_householder') {
    header('Location: ../../login.php');
    exit();
}

require_once('../../config/db.php');

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id) {
    // First, get the product details to check ownership and get image filename
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $product_id, $_SESSION['user']['id']);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if ($product) {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Delete the product
            $delete_stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
            $delete_stmt->bind_param("ii", $product_id, $_SESSION['user']['id']);
            
            if ($delete_stmt->execute()) {
                // Delete the product image if it exists
                if ($product['image'] && file_exists("../../assets/uploads/" . $product['image'])) {
                    unlink("../../assets/uploads/" . $product['image']);
                }
                
                $conn->commit();
                $_SESSION['success'] = "Product deleted successfully!";
            } else {
                throw new Exception("Failed to delete product");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Product not found or you don't have permission to delete it.";
    }
}

// Redirect back to the products page
header('Location: view_products.php');
exit();
?> 