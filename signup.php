<?php
// Include session configuration first - before any output
require_once __DIR__ . '/includes/session_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - Blood Donation System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        
        .signup-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            position: relative;
        }
        
        .signup-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 50%, #a71e2a 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .signup-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .signup-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            position: relative;
            z-index: 2;
        }
        
        .signup-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }
        
        .signup-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        
        .signup-body {
            padding: 40px 30px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            height: auto;
        }
        
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .form-floating > label {
            padding: 15px 20px;
            color: #6c757d;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .input-group .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            border: 2px solid #e9ecef;
            border-left: none;
            background: white;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .input-group .btn:hover {
            background: #f8f9fa;
            color: #dc3545;
        }
        
        .form-check {
            margin-bottom: 25px;
        }
        
        .form-check-input {
            border-radius: 6px;
            border: 2px solid #e9ecef;
            width: 1.2rem;
            height: 1.2rem;
        }
        
        .form-check-input:checked {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        .form-check-label {
            font-weight: 500;
            color: #495057;
        }
        
        .btn-signup {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-signup::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-signup:hover::before {
            left: 100%;
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.3);
        }
        
        .signup-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        
        .signup-footer a {
            color: #dc3545;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .signup-footer a:hover {
            color: #c82333;
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .alert i {
            margin-right: 8px;
        }
        
        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            z-index: 3;
        }
        
        .back-home:hover {
            color: rgba(255,255,255,0.8);
            transform: translateX(-5px);
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.85rem;
        }
        
        .strength-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #17a2b8; width: 75%; }
        .strength-strong { background: #28a745; width: 100%; }
        
        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .required {
            color: #dc3545;
        }
        
        @media (max-width: 480px) {
            .signup-container {
                margin: 20px;
                border-radius: 15px;
            }
            
            .signup-header {
                padding: 30px 20px;
            }
            
            .signup-body {
                padding: 30px 20px;
            }
            
            .signup-footer {
                padding: 15px 20px;
            }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="signup-container fade-in">
        <a href="index.php" class="back-home" title="Back to Home">
            <i class="fas fa-arrow-left"></i>
        </a>
        
        <div class="signup-header">
            <div class="signup-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 class="signup-title">Join Our Cause</h1>
            <p class="signup-subtitle">Create your account and start saving lives</p>
        </div>
        
        <div class="signup-body">
            <?php
            if (isset($_GET['error'])) {
                $msg = '';
                if ($_GET['error'] === 'empty') $msg = 'Please fill in all required fields.';
                elseif ($_GET['error'] === 'exists') $msg = 'That email is already registered.';
                elseif ($_GET['error'] === 'server') $msg = 'Server error. Please try again.';
                elseif ($_GET['error'] === 'exception') $msg = 'Unexpected error. Please try again.';
                if ($msg) {
                    echo '<div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            ' . htmlspecialchars($msg) . '
                          </div>';
                }
            }
            ?>
            
            <form action="signup_handler.php" method="POST" autocomplete="on" novalidate id="signupForm">
                <div class="form-floating">
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           placeholder="Enter your full name"
                           autocomplete="name" 
                           required 
                           aria-required="true">
                    <label for="name">
                        <i class="fas fa-user me-2"></i>Full Name <span class="required">*</span>
                    </label>
                    <div class="form-text">Enter your full legal name</div>
                </div>
                
                <div class="form-floating">
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           placeholder="your@email.com"
                           autocomplete="email" 
                           required 
                           inputmode="email" 
                           aria-required="true">
                    <label for="email">
                        <i class="fas fa-envelope me-2"></i>Email Address <span class="required">*</span>
                    </label>
                    <div class="form-text">We'll never share your email with anyone else</div>
                </div>
                
                <div class="input-group">
                    <div class="form-floating flex-grow-1">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Create a password"
                               autocomplete="new-password" 
                               required 
                               minlength="6" 
                               aria-required="true" 
                               aria-describedby="passwordHelp">
                        <label for="password">
                            <i class="fas fa-lock me-2"></i>Password <span class="required">*</span>
                        </label>
                    </div>
                    <button class="btn" type="button" id="togglePassword">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
                <div id="passwordHelp" class="form-text">Must be at least 6 characters long</div>
                <div class="password-strength" id="passwordStrength" style="display: none;">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div id="strengthText" class="form-text"></div>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" 
                           class="form-check-input" 
                           id="terms" 
                           name="terms" 
                           required 
                           aria-required="true">
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" class="text-danger">Terms of Service</a> and <a href="#" class="text-danger">Privacy Policy</a> <span class="required">*</span>
                    </label>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" 
                           class="form-check-input" 
                           id="newsletter" 
                           name="newsletter" 
                           value="1">
                    <label class="form-check-label" for="newsletter">
                        Send me updates about blood donation campaigns and events
                    </label>
                </div>
                
                <button type="submit" class="btn btn-signup">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>
        </div>
        
        <div class="signup-footer">
            Already have an account? 
            <a href="login.php">Sign in here</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
        
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }
            
            strengthDiv.style.display = 'block';
            
            let strength = 0;
            let strengthLabel = '';
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                strengthFill.className = 'strength-fill strength-weak';
                strengthLabel = 'Weak';
            } else if (strength <= 3) {
                strengthFill.className = 'strength-fill strength-fair';
                strengthLabel = 'Fair';
            } else if (strength <= 4) {
                strengthFill.className = 'strength-fill strength-good';
                strengthLabel = 'Good';
            } else {
                strengthFill.className = 'strength-fill strength-strong';
                strengthLabel = 'Strong';
            }
            
            strengthText.textContent = `Password strength: ${strengthLabel}`;
        });
        
        // Form validation
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const terms = document.getElementById('terms').checked;
            
            if (!name || !email || !password) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }
            
            if (!isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('Please accept the Terms of Service and Privacy Policy.');
                return;
            }
        });
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Auto-focus on name field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('name').focus();
        });
        
        // Real-time validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        document.getElementById('password').addEventListener('blur', function() {
            const password = this.value;
            if (password && password.length < 6) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>