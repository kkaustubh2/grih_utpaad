<?php
require_once('../../includes/auth.php');

// Additional role check for admin
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit();
}

require_once('../../config/db.php');

// Generate and validate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
}

// Handle user actions (block/unblock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['action'])) {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    
    if (empty($reason)) {
        $_SESSION['error'] = "A reason is required for this action.";
        header('Location: manage_users.php');
        exit();
    }

    $admin_id = $_SESSION['user']['id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];

    if ($action === 'block' || $action === 'unblock') {
        $is_blocked = ($action === 'block') ? 1 : 0;
        
        // Use the existing is_blocked column
        $stmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ? AND role != 'admin'");
        $stmt->bind_param("ii", $is_blocked, $user_id);
        
        if ($stmt->execute()) {
            // Send notification to user
            $notify_stmt = $conn->prepare("
                INSERT INTO notifications 
                (user_id, type, message, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $notification_type = $action === 'block' ? 'ACCOUNT_BLOCKED' : 'ACCOUNT_UNBLOCKED';
            $message = $action === 'block' 
                ? "Your account has been blocked. Reason: $reason" 
                : "Your account has been unblocked. Reason: $reason";
            
            $notify_stmt->bind_param("iss", $user_id, $notification_type, $message);
            $notify_stmt->execute();

            $_SESSION['success'] = "User successfully " . ($action === 'block' ? 'blocked' : 'unblocked');
        } else {
            $_SESSION['error'] = "Failed to " . $action . " user";
        }
    }
    
    header('Location: manage_users.php');
    exit();
}

// First, let's add the is_blocked column if it doesn't exist
$check_blocked_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_blocked'");
if ($check_blocked_column->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0");
}

// Check if notifications table exists and create if it doesn't
$check_notifications_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notifications_table->num_rows === 0) {
    $create_notifications_table = "CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    $conn->query($create_notifications_table);
}

// Add last_modified column if it doesn't exist
$check_modified_column = $conn->query("SHOW COLUMNS FROM users LIKE 'last_modified'");
if ($check_modified_column->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN last_modified TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

// Fetch users with additional information
$users = $conn->query("
    SELECT u.*, 
           COALESCE(u.is_blocked, 0) as is_blocked,
           (SELECT COUNT(*) FROM products WHERE user_id = u.id) as total_products,
           (SELECT COUNT(*) FROM orders WHERE 
                CASE 
                    WHEN u.role = 'consumer' THEN consumer_id = u.id
                    WHEN u.role = 'female_householder' THEN product_id IN (SELECT id FROM products WHERE user_id = u.id)
                END
           ) as total_orders,
           (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE 
                CASE 
                    WHEN u.role = 'consumer' THEN consumer_id = u.id
                    WHEN u.role = 'female_householder' THEN product_id IN (SELECT id FROM products WHERE user_id = u.id)
                END
           ) as total_sales
    FROM users u
    WHERE u.role NOT IN ('admin', 'webmaster')
    ORDER BY u.registered_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Debug information
echo "<!-- Debug: SQL Query executed -->";
echo "<!-- Number of users found: " . count($users) . " -->";
foreach ($users as $user) {
    echo "<!-- User: " . $user['name'] . " (Role: " . $user['role'] . ") -->";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - Admin Dashboard</title>
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
            background-color: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stats-badge {
            background: rgba(233, 245, 241, 0.8);
            color: #007B5E;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-left: 8px;
        }

        .user-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(212, 237, 218, 0.8);
            color: #155724;
        }

        .status-blocked {
            background: rgba(248, 215, 218, 0.8);
            color: #721c24;
        }

        .action-btn {
            padding: 6px 12px;
            font-size: 0.9rem;
            margin: 0 4px;
        }

        .btn-block {
            background-color: #dc3545;
        }

        .btn-block:hover {
            background-color: #c82333;
        }

        .btn-unblock {
            background-color: #28a745;
        }

        .btn-unblock:hover {
            background-color: #218838;
        }

        .user-role {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            background: rgba(233, 236, 239, 0.8);
            color: #495057;
            margin-right: 8px;
        }

        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            max-width: 300px;
        }

        .filter-select {
            width: auto;
            margin-bottom: 0;
        }

        .audit-log {
            margin-top: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .action-reason {
            margin-top: 10px;
        }

        .action-reason textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 4px;
            border: 1px solid #ced4da;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }

        .btn {
            background: #007B5E;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: #005b46;
            transform: translateY(-2px);
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
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #007B5E;
            box-shadow: 0 0 0 2px rgba(0, 123, 94, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="card">
            <div class="header">
                <h2><i class="fas fa-users"></i> Manage Users</h2>
            </div>

            <!-- Add transparency info box -->
            <div class="transparency-box">
                <h4>User Management Guidelines:</h4>
                <div class="status-guide">
                    <div class="status-item">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                        <span>Active: User has full access</span>
                    </div>
                    <div class="status-item">
                        <i class="fas fa-ban" style="color: #dc3545;"></i>
                        <span>Blocked: Account access restricted</span>
                    </div>
                </div>
                <p style="margin-top: 10px;"><i class="fas fa-exclamation-circle"></i> All account status changes require a reason and will notify the affected user</p>
                <p><i class="fas fa-shield-alt"></i> Admin accounts cannot be blocked for security reasons</p>
                <p><i class="fas fa-chart-bar"></i> Statistics show product listings, order history, and total sales</p>
            </div>

            <div class="search-box">
                <input type="text" id="searchInput" class="search-input" placeholder="Search users...">
                <select id="roleFilter" class="filter-select">
                    <option value="">All Roles</option>
                    <option value="female_householder">Female Householder</option>
                    <option value="consumer">Consumer</option>
                </select>
                <select id="statusFilter" class="filter-select">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="blocked">Blocked</option>
                </select>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Statistics</th>
                        <th>Joined On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user['name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($user['email']); ?></small>
                            </td>
                            <td>
                                <span class="user-role">
                                    <i class="fas <?php echo $user['role'] === 'female_householder' ? 'fa-store' : 'fa-user'; ?>"></i>
                                    <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="user-status <?php echo $user['is_blocked'] ? 'status-blocked' : 'status-active'; ?>">
                                    <?php echo $user['is_blocked'] ? 'Blocked' : 'Active'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'female_householder'): ?>
                                    <span class="stats-badge">
                                        <i class="fas fa-box"></i> <?php echo $user['total_products']; ?> Products
                                    </span>
                                <?php endif; ?>
                                <span class="stats-badge">
                                    <i class="fas fa-shopping-cart"></i> <?php echo $user['total_orders']; ?> Orders
                                </span>
                                <span class="stats-badge">
                                    <i class="fas fa-rupee-sign"></i> <?php echo number_format($user['total_sales'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($user['registered_at'])); ?>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return validateAction(this);">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <div class="action-buttons" style="display: flex; gap: 10px;">
                                        <?php if ($user['is_blocked']): ?>
                                            <div class="action-group">
                                                <input type="hidden" name="action" value="unblock">
                                                <div class="action-reason">
                                                    <textarea name="reason" placeholder="Reason for unblocking (required)" required></textarea>
                                                </div>
                                                <button type="submit" class="btn action-btn btn-unblock">
                                                    <i class="fas fa-unlock"></i> Unblock
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="action-group">
                                                <input type="hidden" name="action" value="block">
                                                <div class="action-reason">
                                                    <textarea name="reason" placeholder="Reason for blocking (required)" required></textarea>
                                                </div>
                                                <button type="submit" class="btn action-btn btn-block">
                                                    <i class="fas fa-ban"></i> Block
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Audit Log Modal -->
        <div id="auditLogModal" class="modal">
            <div class="modal-content">
                <h3>Admin Action Audit Log</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>User Affected</th>
                            <th>Reason</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_actions as $action): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($action['admin_name']); ?></td>
                                <td><?php echo htmlspecialchars($action['action_type']); ?></td>
                                <td><?php echo htmlspecialchars($action['affected_user_name']); ?></td>
                                <td><?php echo htmlspecialchars($action['reason']); ?></td>
                                <td><?php echo htmlspecialchars($action['ip_address']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($action['timestamp'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function validateAction(form) {
            const reason = form.querySelector('textarea[name="reason"]').value.trim();
            if (!reason) {
                alert('Please provide a reason for this action.');
                return false;
            }
            return confirm('Are you sure you want to perform this action?');
        }

        function showAuditLog() {
            document.getElementById('auditLogModal').style.display = 'block';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('auditLogModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const tableRows = document.querySelectorAll('tbody tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const roleValue = roleFilter.value.toLowerCase();
            const statusValue = statusFilter.value.toLowerCase();

            tableRows.forEach(row => {
                const nameCell = row.querySelector('td:first-child');
                const roleCell = row.querySelector('.user-role');
                const statusCell = row.querySelector('.user-status');

                const name = nameCell.textContent.toLowerCase();
                const email = nameCell.querySelector('small').textContent.toLowerCase();
                // Clean up the role text by removing extra whitespace
                const role = roleCell.textContent.trim().toLowerCase();
                const status = statusCell.textContent.trim().toLowerCase();

                const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
                const matchesRole = !roleValue || role.includes(roleValue.replace('_', ' '));
                const matchesStatus = !statusValue || status === statusValue;

                row.style.display = matchesSearch && matchesRole && matchesStatus ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterTable);
        roleFilter.addEventListener('change', filterTable);
        statusFilter.addEventListener('change', filterTable);
    </script>
    <?php include('../../includes/footer.php'); ?>
</body>
</html> 