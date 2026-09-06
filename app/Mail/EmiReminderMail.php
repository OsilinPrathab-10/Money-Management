<?php

namespace App\Mail;

use App\Models\LoanAccount;
use App\Models\Client;
use App\Models\Emi;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmiReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $loanAccount;
    public $client;
    public $emi;
    public $emailSubject;
    public $emailBody;

    /**
     * Create a new message instance.
     */
    public function __construct(
        LoanAccount $loanAccount,
        Client $client,
        Emi $emi,
        string $subject,
        string $body
    ) {
        $this->loanAccount = $loanAccount;
        $this->client = $client;
        $this->emi = $emi;
        $this->emailSubject = $subject;
        $this->emailBody = $body;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject($this->emailSubject)
            ->view('emails.default-email-template')
            ->with([
                'emailContent' => $this->emailBody,
                'logo' => null, // Will use app name
                'footerText' => 'Your trusted financial partner',
            ]);
    }
}
