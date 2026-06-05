<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Helpdesk Management System - Authentication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .login-card {
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        /* Fixes Bootstrap's overlapping input-group border layout cleanly */
        .input-group :not(:first-child):not(.dropdown-menu):not(.valid-tooltip):not(.valid-feedback):not(.invalid-tooltip):not(.invalid-feedback) {
            margin-left: -1px;
        }
    </style>
</head>
<body class="bg-body-tertiary d-flex align-items-center vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-4">
            
            <div class="text-center mb-4">
                <div class="border border-primary-subtle d-inline-block p-3 rounded-circle mb-2 bg-white">
                    <i class="bi bi-shield-lock-fill text-primary fs-1"></i>
                </div>
                <h3 class="fw-bold text-body-emphasis">Helpdesk Operations</h3>
                <p class="text-muted small text-uppercase tracking-wider">IT Department Domain</p>
            </div>

            <!-- Balanced Flat Corporate Card Layout -->
            <div class="card border border-secondary-subtle p-3 login-card bg-white">
                <div class="card-body">
                    <h5 class="fw-bold mb-4 text-body-emphasis text-center">Account Secure Login</h5>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger bg-danger-subtle text-danger d-flex align-items-center py-2 small border border-danger-subtle mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                            <div><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Form target set to self via empty string to handle POST variables correctly -->
                    <form action="" method="POST" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control border-secondary-subtle border-start-0" placeholder="username@baengineering.com" value="<?php echo htmlspecialchars($submittedEmail, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" autocomplete="username" required>
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


</body>
</html>