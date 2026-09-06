<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('email-title', \App\Models\CompanyDetail::first()->company_name ?? config('app.name'))</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', 'Helvetica', sans-serif;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .email-logo {
            max-width: 180px;
            height: auto;
        }
        .email-body {
            padding: 40px 30px;
            color: #333333;
            line-height: 1.6;
        }
        .email-image {
            width: 100%;
            max-width: 540px;
            height: auto;
            margin: 20px 0;
            border-radius: 8px;
        }
        .email-button {
            display: inline-block;
            padding: 12px 30px;
            margin: 20px 0;
            background-color: #667eea;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
            border-top: 1px solid #e9ecef;
        }
        .email-footer a {
            color: #667eea;
            text-decoration: none;
        }
        .social-links {
            margin: 15px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            color: #6c757d;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 20px 15px;
            }
            .email-header {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header with Logo -->
        <div class="email-header">
            @php
                $companyDetails = \App\Models\CompanyDetail::first();
                $companyName = $companyDetails->company_name ?? config('app.name');
            @endphp
            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">{{ $companyName }}</h1>
        </div>

        <!-- Email Body Content -->
        <div class="email-body">
            @if(isset($emailContent))
                {!! $emailContent !!}
            @else
                @yield('email-content')
            @endif
            
            @if(isset($emailImage) && $emailImage)
            <div style="text-align: center;">
                <img src="{{ $emailImage }}" alt="Email Image" class="email-image">
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p style="margin: 0 0 10px 0;">
                <strong>{{ \App\Models\CompanyDetail::first()->company_name ?? config('app.name') }}</strong>
            </p>
            <p style="margin: 0 0 15px 0;">
                {{ $footerText ?? 'Your trusted financial partner' }}
            </p>
            
            <div class="social-links">
                <!-- Add your social media links here -->
                <a href="#">Facebook</a> |
                <a href="#">Twitter</a> |
                <a href="#">LinkedIn</a>
            </div>
            
            <p style="margin: 15px 0 0 0; font-size: 11px; color: #999;">
                © {{ date('Y') }} {{ \App\Models\CompanyDetail::first()->company_name ?? config('app.name') }}. All rights reserved.
            </p>
            <p style="margin: 5px 0 0 0; font-size: 11px; color: #999;">
                This email was sent to you because you are a valued customer.
            </p>
        </div>
    </div>
</body>
</html>
