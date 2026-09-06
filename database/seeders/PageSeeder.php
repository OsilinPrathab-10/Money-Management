<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PageConfiguration;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Privacy Policy
        PageConfiguration::updateOrCreate(['slug' => 'privacy-policy'], [
            'name' => 'Privacy Policy',
            'status' => 'active',
            'order' => 1,
            'content' => '<h3>Privacy Policy</h3>
<p><strong>Effective Date:</strong> 08 January 2026</p>

<p>This Privacy Policy explains how we collect, use, store, and protect your information when you use the <strong>Loan Management System (LMS)</strong> application. By using this App, you agree to this Privacy Policy.</p>

<h3>Information We Collect</h3>

<h4>Personal Information</h4>
<p>We may collect your name, mobile number, email address, identity details, and address information. This information is required only to provide loan-related services and customer support.</p>

<h4>Location Information</h4>
<p>Location data may be collected only to verify user identity, eligibility, or compliance requirements. Location access is used only while the app is active. We do not track users in the background.</p>

<h4>Images and Documents</h4>
<p>We may collect images of identity documents (e.g., Aadhaar, PAN) and photos for KYC verification. These are stored securely and used only for verification purposes.</p>

<h4>Device Information</h4>
<p>We may collect device identifiers, operating system details, and app usage data to improve performance and security.</p>

<h4>Financial Information</h4>
<p>Bank account details, loan history, and transaction data are collected to process loans and manage repayments.</p>

<h3>How We Use Your Information</h3>
<ul>
    <li>To process loan applications and manage repayments</li>
    <li>To verify your identity and comply with legal requirements</li>
    <li>To communicate updates, notifications, and customer support</li>
    <li>To improve app functionality and user experience</li>
    <li>To detect and prevent fraud or unauthorized activities</li>
</ul>

<h3>Data Sharing and Disclosure</h3>
<p>We do not sell your personal information. However, we may share data with:</p>
<ul>
    <li><strong>Service Providers:</strong> Third-party vendors who assist in app operations (e.g., payment gateways, cloud storage)</li>
    <li><strong>Legal Authorities:</strong> When required by law or to protect our rights</li>
    <li><strong>Credit Bureaus:</strong> To assess creditworthiness and report loan repayment history</li>
</ul>

<h3>Data Security</h3>
<p>We implement industry-standard security measures to protect your data, including encryption, secure servers, and access controls. However, no system is completely secure, and we cannot guarantee absolute security.</p>

<h3>Data Retention</h3>
<p>We retain your data as long as necessary to provide services, comply with legal obligations, or resolve disputes. You may request data deletion, subject to legal and operational limitations.</p>

<h3>Your Rights</h3>
<p>You have the right to:</p>
<ul>
    <li>Access, update, or delete your personal information</li>
    <li>Withdraw consent for data collection (may affect app functionality)</li>
    <li>Request a copy of your data</li>
    <li>Lodge a complaint with relevant authorities</li>
</ul>

<h3>Permissions Required</h3>
<p>The App may request the following permissions:</p>
<ul>
    <li><strong>Camera:</strong> To capture identity documents and photos</li>
    <li><strong>Location:</strong> To verify user location for compliance</li>
    <li><strong>Storage:</strong> To save documents and images</li>
    <li><strong>Contacts:</strong> To facilitate loan references (optional)</li>
    <li><strong>SMS:</strong> To auto-read OTPs for verification</li>
</ul>
<p>Users may request access, correction, or deletion of their personal data, subject to legal and operational limitations. Some app features may not function if required permissions are withdrawn.</p>

<h3>Children\'s Privacy</h3>
<p>This App is not intended for users under the age of 18. We do not knowingly collect data from minors.</p>

<h3>Policy Updates</h3>
<p>This Privacy Policy may be updated from time to time. Continued use of the App indicates acceptance of the revised policy.</p>

<h3>Contact Information</h3>
<p><strong>App Name:</strong> Loan Management System</p>
<p><strong>Email:</strong> <a href="mailto:support@example.com">support@example.com</a></p>
<p><strong>Phone:</strong> +91 9840697692</p>',
        ]);

        // Terms and Conditions
        PageConfiguration::updateOrCreate(['slug' => 'terms-and-conditions'], [
            'name' => 'Terms and Conditions',
            'status' => 'active',
            'order' => 2,
            'content' => '<h3>Terms and Conditions</h3>
<p><strong>Effective Date:</strong> 08 January 2026</p>

<p>Welcome to the <strong>Loan Management System (LMS)</strong>. By using this App, you agree to comply with and be bound by the following terms and conditions. Please read them carefully.</p>

<h3>1. Acceptance of Terms</h3>
<p>By accessing or using the App, you agree to these Terms and Conditions. If you do not agree, please do not use the App.</p>

<h3>2. Eligibility</h3>
<p>You must be at least 18 years old and a resident of India to use this App. By using the App, you confirm that you meet these requirements.</p>

<h3>3. User Account</h3>
<p>You are responsible for maintaining the confidentiality of your account credentials. Any activity under your account is your responsibility. Notify us immediately if you suspect unauthorized access.</p>

<h3>4. Loan Services</h3>
<ul>
    <li>The App facilitates loan applications, approvals, and repayments.</li>
    <li>Loan approval is subject to verification and creditworthiness assessment.</li>
    <li>Interest rates, fees, and repayment terms will be clearly disclosed before loan disbursement.</li>
    <li>Failure to repay loans may result in penalties, legal action, and credit score impact.</li>
</ul>

<h3>5. User Responsibilities</h3>
<p>You agree to:</p>
<ul>
    <li>Provide accurate and truthful information</li>
    <li>Use the App only for lawful purposes</li>
    <li>Not engage in fraudulent activities or misuse the App</li>
    <li>Comply with all applicable laws and regulations</li>
</ul>

<h3>6. Prohibited Activities</h3>
<p>You must not:</p>
<ul>
    <li>Use the App for illegal or unauthorized purposes</li>
    <li>Attempt to hack, reverse-engineer, or disrupt the App</li>
    <li>Share your account with others</li>
    <li>Upload malicious content or viruses</li>
</ul>

<h3>7. Data Collection and Privacy</h3>
<p>We collect and use your data as described in our Privacy Policy. By using the App, you consent to data collection and processing.</p>

<h3>8. Intellectual Property</h3>
<p>All content, trademarks, and materials in the App are owned by us or our licensors. You may not copy, modify, or distribute any content without permission.</p>

<h3>9. Limitation of Liability</h3>
<p>We are not liable for:</p>
<ul>
    <li>Errors, interruptions, or delays in the App</li>
    <li>Loss of data or unauthorized access</li>
    <li>Third-party services or payment gateway issues</li>
</ul>
<p>Use the App at your own risk.</p>

<h3>10. Termination</h3>
<p>We reserve the right to suspend or terminate your account if you violate these Terms or engage in fraudulent activities.</p>

<h3>11. Changes to Terms</h3>
<p>We may update these Terms from time to time. Continued use of the App indicates acceptance of the revised Terms.</p>

<h3>12. Governing Law</h3>
<p>These Terms are governed by the laws of India. Any disputes will be resolved in the courts of [Your City/State].</p>

<h3>13. Contact Us</h3>
<p>If you have any questions or concerns, please contact us:</p>
<p><strong>App Name:</strong> Loan Management System</p>
<p><strong>Email:</strong> <a href="mailto:support@example.com">support@example.com</a></p>
<p><strong>Phone:</strong> +91 9840697692</p>',
        ]);
    }
}
