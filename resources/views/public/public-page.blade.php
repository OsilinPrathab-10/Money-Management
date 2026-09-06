<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->name }} - {{ config('variables.templateName') }}</title>
    
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
            background: white;
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

        .public-page-content {
            padding: 3rem 15%;
            line-height: 1.8;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
        }

        .public-page-content h2 {
            color: #667eea;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .public-page-content h3 {
            color: #764ba2;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .public-page-content p {
            margin-bottom: 1rem;
        }

        .public-page-content ul, 
        .public-page-content ol {
            margin-bottom: 1rem;
            padding-left: 2rem;
        }

        .public-page-content li {
            margin-bottom: 0.5rem;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .back-to-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
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
                <h1>{{ $page->name }}</h1>
            </div>
            
            <div class="public-page-content">
                {!! $page->content !!}
            </div>
            
            <div class="public-page-footer">
                <a href="{{ route('login') }}" class="back-to-login">
                    <i class="ri-arrow-left-line"></i>
                    Back to Login
                </a>
                <div class="footer-text">
                    &copy; {{ date('Y') }} <a href="{{ get_setting('company_website', url('/')) }}" target="_blank" style="color: #667eea; text-decoration: none; font-weight: 500;">{{ get_setting('company_name', config('app.name')) }}</a> - All rights reserved.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
