<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\LoanAccount;
use App\Models\Client;

class LoanStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public $loanAccount;
    public $client;
    public $emailSubject;
    public $emailBody;
    public $pdfPath;
    public $pdfFileName;

    /**
     * Create a new message instance.
     */
    public function __construct(
        LoanAccount $loanAccount,
        Client $client,
        string $emailSubject,
        string $emailBody,
        $pdfPath = null,
        $pdfFileName = null
    ) {
        $this->loanAccount = $loanAccount;
        $this->client = $client;
        $this->emailSubject = $emailSubject;
        $this->emailBody = $emailBody;
        $this->pdfPath = $pdfPath;
        $this->pdfFileName = $pdfFileName ?? 'Loan_Statement.pdf';
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
            htmlString: $this->emailBody,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $attachments[] = Attachment::fromPath($this->pdfPath)
                ->as($this->pdfFileName)
                ->withMime('application/pdf');
        }
        
        return $attachments;
    }
}
