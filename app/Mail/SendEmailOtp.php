<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Log;

class SendEmailOtp extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $email;
    public $emailBody;
    public $emailSubject;

    /**
     * Create a new message instance.
     */
    public function __construct($otp, $email = null)
    {
        $this->otp = $otp;
        $this->email = $email;
        
        // Fetch email template from database
        $template = EmailTemplate::where('identifier', 'otp_email')
                                 ->where('status', true)
                                 ->first();
        
        if ($template) {
            // Get user name from email if provided
            $userName = 'User';
            if ($this->email) {
                $user = User::where('email', $this->email)->first();
                if ($user) {
                    $userName = $user->name;
                    
                    // Try to get client name if available
                    $client = Client::where('user_id', $user->id)->first();
                    if ($client && $client->client_name) {
                        $userName = $client->client_name;
                    }
                }
            }
            
            // Define all possible placeholder variations (including common mistakes)
            $placeholders = [
                // Correct syntax
                '{{otp}}' => (string)$this->otp,
                '{{ otp }}' => (string)$this->otp,
                '{{client_name}}' => $userName,
                '{{ client_name }}' => $userName,
                '{{user_name}}' => $userName,
                '{{ user_name }}' => $userName,
                '{{app_name}}' => config('app.name'),
                '{{ app_name }}' => config('app.name'),
                '{{expiry_minutes}}' => '10',
                '{{ expiry_minutes }}' => '10',
                
                // Common mistakes with dollar sign (Blade syntax)
                '{{$otp}}' => (string)$this->otp,
                '{{ $otp }}' => (string)$this->otp,
                '{{$client_name}}' => $userName,
                '{{ $client_name }}' => $userName,
                '{{$user_name}}' => $userName,
                '{{ $user_name }}' => $userName,
                '{{$app_name}}' => config('app.name'),
                '{{ $app_name }}' => config('app.name'),
            ];
            
            // Replace placeholders in subject
            $this->emailSubject = str_replace(
                array_keys($placeholders),
                array_values($placeholders),
                $template->subject
            );
            
            // Replace placeholders in body
            $this->emailBody = str_replace(
                array_keys($placeholders),
                array_values($placeholders),
                $template->email_body
            );
            
            // Log for debugging
            Log::info('OTP Email Processing', [
                'template_id' => $template->id,
                'email' => $this->email,
                'otp' => $this->otp,
                'user_name' => $userName,
                'subject_before' => $template->subject,
                'subject_after' => $this->emailSubject,
                'body_has_dollar_otp' => str_contains($template->email_body, '{{$otp}}'),
                'body_has_correct_otp' => str_contains($template->email_body, '{{otp}}'),
                'body_preview_before' => substr($template->email_body, 0, 200),
                'body_preview_after' => substr($this->emailBody, 0, 200),
            ]);
        } else {
            // Fallback if template not found
            $this->emailSubject = 'OTP Verification - ' . config('app.name');
            $this->emailBody = $this->getDefaultOtpBody();
            
            Log::warning('OTP Email Template not found (identifier: otp_email), using fallback');
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.default-email-template',
            with: [
                'emailContent' => $this->emailBody,
                'otp' => $this->otp,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
    
    /**
     * Get default OTP email body if template not found
     */
    private function getDefaultOtpBody()
    {
        $userName = 'User';
        if ($this->email) {
            $user = User::where('email', $this->email)->first();
            if ($user) {
                $userName = $user->name;
                $client = Client::where('user_id', $user->id)->first();
                if ($client && $client->client_name) {
                    $userName = $client->client_name;
                }
            }
        }
        
        return "
            <h2>Hello {$userName},</h2>
            <p>Use the following One-Time Password (OTP) to verify your email address:</p>
            <div style='font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 3px; margin: 20px 0; text-align: center;'>
                {$this->otp}
            </div>
            <p>This OTP is valid for <strong>10 minutes</strong>.</p>
            <p>If you did not request this, please ignore this email.</p>
        ";
    }
}
