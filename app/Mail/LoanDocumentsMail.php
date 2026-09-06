<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use App\Models\LoanAccount;
use App\Models\Client;
use Illuminate\Support\Collection;

class LoanDocumentsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $loanAccount;
    public $client;
    public $emailSubject;
    public $emailBody;
    public $documents;

    /**
     * Create a new message instance.
     */
    public function __construct(
        LoanAccount $loanAccount,
        Client $client,
        string $emailSubject,
        string $emailBody,
        Collection $documents
    ) {
        $this->loanAccount = $loanAccount;
        $this->client = $client;
        $this->emailSubject = $emailSubject;
        $this->emailBody = $emailBody;
        $this->documents = $documents;
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

        foreach ($this->documents as $document) {
            if ($document->fileExists()) {
                $attachments[] = Attachment::fromPath($document->full_path)
                    ->as($document->file_name)
                    ->withMime($this->getMimeType($document->file_path));
            }
        }

        return $attachments;
    }

    /**
     * Get MIME type for file
     */
    private function getMimeType(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
}
