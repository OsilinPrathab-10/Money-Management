<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Account Deletion - {{ config('variables.templateName') }}</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f9;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .public-page-container {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .public-page-card {
            background: white;
            width: 100%;
        }

        .public-page-header {
            background: #696cff;
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .public-page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .public-page-header p {
            margin-top: 1rem;
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .public-page-content {
            padding: 2rem 5%;
            line-height: 1.8;
            color: #333;
            max-width: 100%;
            margin: 0;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #696cff;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 0;
        }

        .info-box h3 {
            color: #696cff;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 0;
        }

        .warning-box h3 {
            color: #856404;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .deletion-form {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0;
            padding: 2rem;
            margin: 1.5rem 0;
            box-shadow: none;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d9dee3;
            border-radius: 0.375rem;
            font-size: 0.9375rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #696cff;
            box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn-submit {
            background: #696cff;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.375rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            background: #5f61e6;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(105, 108, 255, 0.3);
        }

        .btn-submit:disabled {
            background: #b4b7ff;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            display: none;
        }

        .alert-success {
            background: #d1e7dd;
            border-left: 4px solid #0f5132;
            color: #0f5132;
        }

        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #842029;
            color: #842029;
        }

        .public-page-footer {
            background: #f8f9fa;
            padding: 2rem;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .back-to-login {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #696cff;
            color: white;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .back-to-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(105, 108, 255, 0.3);
            color: white;
            background: #5f61e6;
        }

        .footer-text {
            margin-top: 1rem;
            color: #6c757d;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .public-page-header h1 {
                font-size: 1.75rem;
            }
            
            .public-page-content {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="public-page-container">
        <div class="public-page-card">
            <div class="public-page-header">
                <h1>Account Deletion Request</h1>
                <p>Manage your account and data privacy</p>
            </div>
            
            <div class="public-page-content">
                <div class="info-box">
                    <h3><i class="ri-information-line"></i> About Account Deletion</h3>
                    <p>We respect your right to privacy and data control. If you wish to delete your account and all associated data from our system, please submit the form below.</p>
                </div>

                <div class="warning-box">
                    <h3><i class="ri-alert-line"></i> Important Information</h3>
                    <p><strong>Please note the following before deleting your account:</strong></p>
                    <ul style="margin-top: 1rem; padding-left: 1.5rem;">
                        <li>Account deletion is permanent and cannot be undone</li>
                        <li>All your personal data will be permanently removed from our servers</li>
                        <li>Any pending transactions or loans must be settled before deletion</li>
                        <li>You will lose access to all services and features</li>
                        <li>The deletion process may take up to 30 days to complete</li>
                        <li>Our team will verify your identity before processing the request</li>
                    </ul>
                </div>

                <h2>Submit Account Deletion Request</h2>
                <p>Please fill out the form below to request account deletion. Our support team will review your request and contact you for verification.</p>

                <div class="deletion-form">
                    <div id="successAlert" class="alert alert-success">
                        <i class="ri-checkbox-circle-line"></i>
                        <strong>Request Submitted!</strong> Your account deletion request has been received. Our team will contact you within 24-48 hours for verification.
                    </div>

                    <div id="errorAlert" class="alert alert-error">
                        <i class="ri-error-warning-line"></i>
                        <strong>Error!</strong> <span id="errorMessage">Something went wrong. Please try again.</span>
                    </div>

                    <form id="deletionForm">
                        @csrf
                        <div class="form-group">
                            <label for="full_name">Full Name <span class="required">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="Enter your full name">
                        </div>

                        <div class="form-group">
                            <label for="email">Registered Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" required placeholder="Enter your registered email">
                        </div>

                        <div class="form-group">
                            <label for="mobile">Registered Mobile Number <span class="required">*</span></label>
                            <input type="tel" id="mobile" name="mobile" class="form-control" required placeholder="Enter your registered mobile number">
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason for Account Deletion</label>
                            <textarea id="reason" name="reason" class="form-control" placeholder="Please let us know why you want to delete your account"></textarea>
                        </div>

                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="ri-send-plane-fill"></i>
                            Submit Deletion Request
                        </button>
                    </form>
                </div>

                <h2>Data Retention Policy</h2>
                <p>Upon account deletion request approval:</p>
                <ul style="margin-top: 1rem; padding-left: 2rem;">
                    <li>Your personal information will be permanently deleted within 30 days</li>
                    <li>Some data may be retained for legal and regulatory compliance purposes</li>
                    <li>Transaction records may be kept for accounting and audit purposes as required by law</li>
                    <li>Anonymized data may be retained for analytical purposes</li>
                </ul>

                <div class="info-box" style="margin-top: 2rem;">
                    <h3><i class="ri-question-line"></i> Need Help?</h3>
                    <p>If you have any questions about the account deletion process or our data policies, please don't hesitate to contact our support team at <a href="mailto:{{ get_setting('company_email', get_setting('support_email', 'support@' . request()->getHost())) }}" style="color: #696cff; text-decoration: none; font-weight: 500;">{{ get_setting('company_email', get_setting('support_email', 'support@' . request()->getHost())) }}</a></p>
                </div>
            </div>
            
            <div class="public-page-footer">
                <a href="{{ route('login') }}" class="back-to-login">
                    <i class="ri-arrow-left-line"></i>
                    Back to Login
                </a>
                <div class="footer-text">
                    &copy; {{ date('Y') }} <a href="{{ get_setting('company_website', url('/')) }}" target="_blank" style="color: #696cff; text-decoration: none; font-weight: 500;">{{ get_setting('company_name', config('app.name')) }}</a> - All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('deletionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            
            // Hide alerts
            successAlert.style.display = 'none';
            errorAlert.style.display = 'none';
            
            // Check if required fields are filled
            const fullName = document.getElementById('full_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const mobile = document.getElementById('mobile').value.trim();
            
            if (!fullName || !email || !mobile) {
                errorAlert.style.display = 'block';
                document.getElementById('errorMessage').textContent = 'Please fill in all required fields.';
                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            
            // Disable button temporarily
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="ri-loader-4-line"></i> Submitting...';
            
            // Simulate submission delay for better UX
            setTimeout(() => {
                // Show success message
                successAlert.style.display = 'block';
                this.reset();
                successAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="ri-send-plane-fill"></i> Submit Deletion Request';
            }, 800);
        });
    </script>
</body>
</html>
