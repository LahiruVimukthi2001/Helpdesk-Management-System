<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $role     = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($name) && $email && !empty($role) && !empty($password)) {
        
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $checkStmt->execute(['email' => $email]);
        
        if ($checkStmt->fetch()) {
            $error = "This email address is already registered to a system profile.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // FIXED: Now inserting specifically into the 'password_hash' table column
            $insertStmt = $pdo->prepare("INSERT INTO users (name, email, role, password_hash) VALUES (:name, :email, :role, :password_hash)");
            $result = $insertStmt->execute([
                'name'          => $name,
                'email'         => $email,
                'role'          => $role, 
                'password_hash' => $hashedPassword
            ]);

            if ($result) {
                $success = "Account created successfully! You can now log in.";
                $_POST = array(); 
            } else {
                $error = "An error occurred while creating your account. Please try again.";
            }
        }
    } else {
        $error = "Please provide valid information inside all registration fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Helpdesk Management System - Account Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* Modern geometric background transition gradient matched with login */
        .login-bg {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            background-attachment: fixed;
        }
        /* Adjusted semi-transparent panel for high-quality backdrop glass blur */
        .login-card {
            border-radius: 8px;
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.85) !important;
        }
        /* Custom max-width control to smoothly match the wider visual field */
        .mw-500 {
            max-width: 500px !important;
        }
        /* Fixes Bootstrap's overlapping input-group border layout cleanly */
        .input-group :not(:first-child):not(.dropdown-menu):not(.valid-tooltip):not(.valid-feedback):not(.invalid-tooltip):not(.invalid-feedback) {
            margin-left: -1px;
        }
        /* Custom handling for corporate branding height balance */
        .brand-logo {
            max-height: 65px;
            width: auto;
            object-fit: contain;
        }
    </style>
</head>
<body class="login-bg d-flex align-items-center min-vh-100 py-2">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-5 mw-500">
            
            <div class="text-center mb-3">
                <div class="d-inline-block p-2 mb-2">
                    <img src="assets/login.png" alt="Company Logo" class="brand-logo">
                </div>
                <h3 class="fw-bold text-body-emphasis">IT Helpdesk Management System</h3>
                <p class="text-muted small text-uppercase tracking-wider">DEPARTMENT OF INFORMATION TECHNOLOGY</p>
            </div>

            <div class="card border border-secondary-subtle p-3 login-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-4 text-body-emphasis text-center">Create New Account</h5>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger bg-danger-subtle text-danger d-flex align-items-center py-2 small border border-danger-subtle mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                            <div><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success bg-success-subtle text-success d-flex align-items-center py-2 small border border-success-subtle mb-3" role="alert">
                            <i class="bi bi-check-circle-fill me-2 flex-shrink-0"></i>
                            <div><?php echo htmlspecialchars($success, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="signup.php" method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" name="name" class="form-control border-secondary-subtle border-start-0" placeholder="Full Name" value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control border-secondary-subtle border-start-0" placeholder="username@gmail.com" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">System User Role</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-person-badge"></i></span>
                                <select name="role" class="form-select border-secondary-subtle border-start-0 text-secondary" required>
                                    <option value="" disabled selected>Select profile status...</option>
                                    <option value="requester" <?php echo (($_POST['role'] ?? '') === 'requester') ? 'selected' : ''; ?>>Requester (Staff User)</option>
                                    <option value="agent" <?php echo (($_POST['role'] ?? '') === 'agent') ? 'selected' : ''; ?>>Agent Operator (IT Team)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" id="passwordInput" name="password" class="form-control border-secondary-subtle border-start-0 border-end-0" placeholder="••••••••" required>
                                <button class="btn btn-light bg-light border border-secondary-subtle border-start-0 text-muted" type="button" id="togglePassword">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <span>Register Account</span>
                            <i class="bi bi-arrow-right-short fs-5"></i>
                        </button>
                    </form>

                    <hr class="text-secondary opacity-25 my-4">

                    <div class="text-center">
                        <p class="text-muted small mb-0">Already have a credentials clearance profile? <a href="login.php" class="text-decoration-none fw-bold text-primary">Sign In here</a></p>
                    </div>

                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#passwordInput');
        const toggleIcon = document.querySelector('#toggleIcon');

        if (togglePassword && passwordInput && toggleIcon) {
            togglePassword.addEventListener('click', function () {
                const isPasswordType = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPasswordType ? 'text' : 'password');
                
                toggleIcon.classList.toggle('bi-eye', !isPasswordType);
                toggleIcon.classList.toggle('bi-eye-slash', isPasswordType);
            });
        }
    });
</script>
</body>
</html>