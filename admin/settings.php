<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
if (!is_admin()) {
    // Redirect to login page
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Define default settings
$settings = [
    'site_name' => 'DevMarket Philippines',
    'site_email' => 'admin@devmarket.ph',
    'commission_rate' => 10,
    'currency' => 'PHP',
    'maintenance_mode' => 'off',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'enable_seller_registration' => 'yes',
    'require_admin_approval' => 'yes',
    'max_file_size' => 10, // In MB
    'allowed_file_types' => 'jpg,jpeg,png,gif,zip,rar,pdf,doc,docx'
];

// Initialize settings table if it doesn't exist
$check_table_sql = "SHOW TABLES LIKE 'settings'";
$table_exists = $conn->query($check_table_sql)->num_rows > 0;

if (!$table_exists) {
    // Create settings table
    $create_table_sql = "CREATE TABLE settings (
        id INT(11) NOT NULL AUTO_INCREMENT,
        setting_key VARCHAR(255) NOT NULL,
        setting_value TEXT,
        PRIMARY KEY (id),
        UNIQUE KEY setting_key (setting_key)
    )";
    
    if ($conn->query($create_table_sql)) {
        // Insert default settings
        foreach ($settings as $key => $value) {
            $insert_sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
    } else {
        $error = "Error creating settings table: " . $conn->error;
    }
} else {
    // Load existing settings
    $load_settings_sql = "SELECT setting_key, setting_value FROM settings";
    $settings_result = $conn->query($load_settings_sql);
    
    if ($settings_result && $settings_result->num_rows > 0) {
        while ($row = $settings_result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update general settings
    if (isset($_POST['update_general'])) {
        $site_name = clean_input($_POST['site_name']);
        $site_email = clean_input($_POST['site_email']);
        $commission_rate = (int)$_POST['commission_rate'];
        $currency = clean_input($_POST['currency']);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 'on' : 'off';
        
        // Validate inputs
        if (empty($site_name)) {
            $error = "Site name cannot be empty.";
        } elseif (!filter_var($site_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif ($commission_rate < 0 || $commission_rate > 100) {
            $error = "Commission rate must be between 0 and 100.";
        } else {
            // Update settings in database
            $update_settings = [
                'site_name' => $site_name,
                'site_email' => $site_email,
                'commission_rate' => $commission_rate,
                'currency' => $currency,
                'maintenance_mode' => $maintenance_mode
            ];
            
            foreach ($update_settings as $key => $value) {
                $update_sql = "INSERT INTO settings (setting_key, setting_value) 
                             VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sss", $key, $value, $value);
                $stmt->execute();
            }
            
            $success = "General settings updated successfully.";
            
            // Update local settings array
            foreach ($update_settings as $key => $value) {
                $settings[$key] = $value;
            }
        }
    }
    
    // Update email settings
    if (isset($_POST['update_email'])) {
        $smtp_host = clean_input($_POST['smtp_host']);
        $smtp_port = (int)$_POST['smtp_port'];
        $smtp_username = clean_input($_POST['smtp_username']);
        $smtp_password = $_POST['smtp_password']; // No cleaning for passwords
        $smtp_encryption = clean_input($_POST['smtp_encryption']);
        
        // Validate inputs
        if (empty($smtp_host) && (!empty($smtp_username) || !empty($smtp_password))) {
            $error = "SMTP host is required if username or password is provided.";
        } elseif ($smtp_port < 1 || $smtp_port > 65535) {
            $error = "Invalid SMTP port.";
        } else {
            // Update settings in database
            $update_settings = [
                'smtp_host' => $smtp_host,
                'smtp_port' => $smtp_port,
                'smtp_username' => $smtp_username,
                'smtp_encryption' => $smtp_encryption
            ];
            
            // Only update password if provided
            if (!empty($smtp_password)) {
                $update_settings['smtp_password'] = $smtp_password;
            }
            
            foreach ($update_settings as $key => $value) {
                $update_sql = "INSERT INTO settings (setting_key, setting_value) 
                             VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sss", $key, $value, $value);
                $stmt->execute();
            }
            
            $success = "Email settings updated successfully.";
            
            // Update local settings array
            foreach ($update_settings as $key => $value) {
                $settings[$key] = $value;
            }
        }
    }
    
    // Update registration settings
    if (isset($_POST['update_registration'])) {
        $enable_seller_registration = isset($_POST['enable_seller_registration']) ? 'yes' : 'no';
        $require_admin_approval = isset($_POST['require_admin_approval']) ? 'yes' : 'no';
        
        // Update settings in database
        $update_settings = [
            'enable_seller_registration' => $enable_seller_registration,
            'require_admin_approval' => $require_admin_approval
        ];
        
        foreach ($update_settings as $key => $value) {
            $update_sql = "INSERT INTO settings (setting_key, setting_value) 
                         VALUES (?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
        }
        
        $success = "Registration settings updated successfully.";
        
        // Update local settings array
        foreach ($update_settings as $key => $value) {
            $settings[$key] = $value;
        }
    }
    
    // Update file upload settings
    if (isset($_POST['update_file_uploads'])) {
        $max_file_size = (int)$_POST['max_file_size'];
        $allowed_file_types = clean_input($_POST['allowed_file_types']);
        
        // Validate inputs
        if ($max_file_size < 1) {
            $error = "Maximum file size must be at least 1 MB.";
        } elseif (empty($allowed_file_types)) {
            $error = "Allowed file types cannot be empty.";
        } else {
            // Update settings in database
            $update_settings = [
                'max_file_size' => $max_file_size,
                'allowed_file_types' => $allowed_file_types
            ];
            
            foreach ($update_settings as $key => $value) {
                $update_sql = "INSERT INTO settings (setting_key, setting_value) 
                             VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sss", $key, $value, $value);
                $stmt->execute();
            }
            
            $success = "File upload settings updated successfully.";
            
            // Update local settings array
            foreach ($update_settings as $key => $value) {
                $settings[$key] = $value;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - DevMarket Philippines</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Site Settings</h1>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <!-- Settings Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                            <i class="fas fa-cog me-2"></i> General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">
                            <i class="fas fa-envelope me-2"></i> Email
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="registration-tab" data-bs-toggle="tab" data-bs-target="#registration" type="button" role="tab" aria-controls="registration" aria-selected="false">
                            <i class="fas fa-user-plus me-2"></i> Registration
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="file-uploads-tab" data-bs-toggle="tab" data-bs-target="#file-uploads" type="button" role="tab" aria-controls="file-uploads" aria-selected="false">
                            <i class="fas fa-upload me-2"></i> File Uploads
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabsContent">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-cog me-2"></i> General Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="site_name" class="form-label">Site Name</label>
                                        <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                                        <div class="form-text">The name of your website (shown in browser title and site header).</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="site_email" class="form-label">Site Email</label>
                                        <input type="email" class="form-control" id="site_email" name="site_email" value="<?php echo htmlspecialchars($settings['site_email']); ?>" required>
                                        <div class="form-text">The main email address used for notifications and system emails.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="commission_rate" class="form-label">Commission Rate (%)</label>
                                        <input type="number" class="form-control" id="commission_rate" name="commission_rate" min="0" max="100" value="<?php echo (int)$settings['commission_rate']; ?>" required>
                                        <div class="form-text">Percentage fee charged on each transaction (10% = platform keeps 10% of each sale).</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="currency" class="form-label">Currency</label>
                                        <select class="form-select" id="currency" name="currency">
                                            <option value="PHP" <?php echo $settings['currency'] == 'PHP' ? 'selected' : ''; ?>>Philippine Peso (₱)</option>
                                            <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                            <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                        </select>
                                        <div class="form-text">The primary currency used on the platform.</div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="maintenance_mode" name="maintenance_mode" <?php echo $settings['maintenance_mode'] == 'on' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">Enable Maintenance Mode</label>
                                        <div class="form-text">When enabled, only administrators can access the site.</div>
                                    </div>
                                    
                                    <button type="submit" name="update_general" class="btn btn-primary">Save General Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email Settings -->
                    <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-envelope me-2"></i> Email Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i> Configure SMTP settings to send emails through an external mail server. Leave blank to use PHP's mail() function.
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                                        <div class="form-text">The hostname of your SMTP server (e.g., smtp.gmail.com).</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_port" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?php echo (int)$settings['smtp_port']; ?>">
                                        <div class="form-text">Common ports are 25, 465 (SSL), or 587 (TLS).</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_username" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_password" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="<?php echo empty($settings['smtp_password']) ? 'No password set' : '••••••••••••'; ?>">
                                        <div class="form-text">Leave blank to keep the current password.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_encryption" class="form-label">Encryption</label>
                                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                            <option value="none" <?php echo $settings['smtp_encryption'] == 'none' ? 'selected' : ''; ?>>None</option>
                                            <option value="ssl" <?php echo $settings['smtp_encryption'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                            <option value="tls" <?php echo $settings['smtp_encryption'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="update_email" class="btn btn-primary">Save Email Settings</button>
                                    <button type="button" class="btn btn-secondary" onclick="testEmailSettings()">Test Email Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Registration Settings -->
                    <div class="tab-pane fade" id="registration" role="tabpanel" aria-labelledby="registration-tab">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i> Registration Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="enable_seller_registration" name="enable_seller_registration" <?php echo $settings['enable_seller_registration'] == 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_seller_registration">Allow Seller Registration</label>
                                        <div class="form-text">When disabled, users can only register as buyers.</div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="require_admin_approval" name="require_admin_approval" <?php echo $settings['require_admin_approval'] == 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="require_admin_approval">Require Admin Approval for Sellers</label>
                                        <div class="form-text">When enabled, new seller accounts must be approved by an administrator.</div>
                                    </div>
                                    
                                    <button type="submit" name="update_registration" class="btn btn-primary">Save Registration Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- File Upload Settings -->
                    <div class="tab-pane fade" id="file-uploads" role="tabpanel" aria-labelledby="file-uploads-tab">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-upload me-2"></i> File Upload Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="max_file_size" class="form-label">Maximum File Size (MB)</label>
                                        <input type="number" class="form-control" id="max_file_size" name="max_file_size" min="1" value="<?php echo (int)$settings['max_file_size']; ?>" required>
                                        <div class="form-text">The maximum size for uploaded files in megabytes.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="allowed_file_types" class="form-label">Allowed File Types</label>
                                        <input type="text" class="form-control" id="allowed_file_types" name="allowed_file_types" value="<?php echo htmlspecialchars($settings['allowed_file_types']); ?>" required>
                                        <div class="form-text">Comma-separated list of allowed file extensions (e.g., jpg,jpeg,png,zip,pdf).</div>
                                    </div>
                                    
                                    <button type="submit" name="update_file_uploads" class="btn btn-primary">Save File Upload Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        function testEmailSettings() {
            // Show testing message
            alert('Email testing functionality will be implemented soon.');
            
            // In a real implementation, you would send an AJAX request to a testing endpoint
            // and display the result to the user
        }
    </script>
</body>
</html> 