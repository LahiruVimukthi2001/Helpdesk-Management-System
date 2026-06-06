<?php
session_start();
require_once 'db.php';

// If already authenticated, redirect immediately to application dashboard grid
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$submittedEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedEmail = trim($_POST['email'] ?? '');
    $email = filter_var($submittedEmail, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? ''; 

    if ($email && !empty($password)) {
        // Targets 'password_hash' safely using a prepared statement matrix
        $stmt = $pdo->prepare("SELECT id, name, role, password_hash FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verification checks against the secure database hash row
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // Prevent Session Hijacking by regenerating the token ID
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            // Normalize role casing style to protect application routing down the line
            $_SESSION['user_role'] = ucfirst(strtolower($user['role'] ?? 'Requester'));

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid system user email address or profile password mismatch.";
        }
    } else {
        $error = "Please provide a valid, well-formed email and password configuration.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Helpdesk Management System - Account Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* Modern geometric background transition gradient */
        .login-bg {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            background-attachment: fixed;
        }
        /* Adjusted to semi-transparent white to maximize the backdrop blur feature */
        .login-card {
            border-radius: 8px;
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.85) !important;
        }
        /* Custom max-width control to smoothly enforce a wider visual field */
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
<body class="login-bg d-flex align-items-center vh-100">

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
                    <h5 class="fw-bold mb-4 text-body-emphasis text-center">Account Login</h5>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger bg-danger-subtle text-danger d-flex align-items-center py-2 small border border-danger-subtle mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                            <div><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control border-secondary-subtle border-start-0" placeholder="username@gmail.com" value="<?php echo htmlspecialchars($submittedEmail, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" autocomplete="username" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" id="passwordInput" name="password" class="form-control border-secondary-subtle border-start-0 border-end-0" placeholder="••••••••" autocomplete="current-password" required>
                                <button class="btn btn-light bg-light border border-secondary-subtle border-start-0 text-muted" type="button" id="togglePassword">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <span>Access Dashboard</span>
                            <i class="bi bi-arrow-right-short fs-5"></i>
                        </button>
                    </form>

                    <hr class="text-secondary opacity-25 my-4">

                    <div class="text-center">
                        <p class="text-muted small mb-0">Need operations profile access? <a href="signup.php" class="text-decoration-none fw-bold text-primary">Register here</a></p>
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
                
                // Toggle between open eye and closed eye icons fluidly
                toggleIcon.classList.toggle('bi-eye', !isPasswordType);
                toggleIcon.classList.toggle('bi-eye-slash', isPasswordType);
            });
        }
    });
</script>
</body>
</html>