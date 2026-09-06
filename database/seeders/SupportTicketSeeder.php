<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupportTicketSeeder extends Seeder
{
    /**
     * Seed support tickets with replies.
     */
    public function run(): void
    {
        $clients = Client::all();
        $users = User::all();

        if ($clients->isEmpty()) {
            $this->command?->warn('No clients found. Please seed clients first.');
            return;
        }

        if ($users->isEmpty()) {
            $this->command?->warn('No users found. Please seed users first.');
            return;
        }

        $subjects = [
            'Unable to access loan application',
            'EMI payment not reflecting',
            'Need help with KYC verification',
            'Loan disbursement delay',
            'Interest rate clarification needed',
            'Document upload issue',
            'Account statement request',
            'Loan closure procedure',
            'EMI schedule modification request',
            'Payment receipt not received',
            'Mobile app login problem',
            'Incorrect loan amount disbursed',
            'Need to update contact details',
            'Loan application status inquiry',
            'Prepayment charges information',
        ];

        $messages = [
            'I am facing issues with my loan application. Please help me resolve this as soon as possible.',
            'My EMI payment was made 3 days ago but it is still not reflecting in my account. Please check.',
            'I have uploaded all KYC documents but the verification is still pending. Can you please expedite?',
            'My loan was approved 5 days ago but I have not received the disbursement yet. Please update.',
            'I need clarification on the interest rate applied to my loan. The rate seems different from what was agreed.',
            'I am unable to upload my documents. The system shows an error every time I try.',
            'I need my account statement for the last 6 months for tax purposes.',
            'I want to close my loan account. Please guide me through the process.',
            'I would like to modify my EMI schedule due to a change in my income. Is this possible?',
            'I made a payment last week but have not received the payment receipt yet.',
            'I cannot log in to the mobile app. It shows "Invalid credentials" even though my password is correct.',
            'The loan amount disbursed is less than what was approved. Please clarify.',
            'I need to update my mobile number and email address in the system.',
            'Can you please provide an update on my loan application submitted last week?',
            'I want to make a prepayment. Please let me know the charges and procedure.',
        ];

        $priorities = ['low', 'medium', 'high', 'urgent'];
        $statuses = ['open', 'pending', 'closed'];

        $ticketsData = [];

        // Create 15 tickets
        for ($i = 0; $i < 15; $i++) {
            $client = $clients->random();
            $status = $statuses[array_rand($statuses)];
            $priority = $priorities[array_rand($priorities)];
            
            // Generate unique ticket number
            $ticketNumber = 'TK-' . strtoupper(Str::random(6));
            while (SupportTicket::where('ticket_number', $ticketNumber)->exists()) {
                $ticketNumber = 'TK-' . strtoupper(Str::random(6));
            }

            $createdAt = Carbon::now()->subDays(rand(1, 30))->setTime(rand(9, 18), rand(0, 59));

            $ticket = SupportTicket::create([
                'ticket_number' => $ticketNumber,
                'client_id' => $client->id,
                'subject' => $subjects[$i],
                'priority' => $priority,
                'message' => $messages[$i],
                'status' => $status,
                'assigned_to' => $status !== 'open' ? $users->random()->id : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Add replies for pending and closed tickets
            if ($status === 'pending' || $status === 'closed') {
                // Admin reply
                $replyCreatedAt = $createdAt->copy()->addHours(rand(2, 24));
                SupportTicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $users->random()->id,
                    'message' => 'Thank you for contacting us. We are looking into your issue and will get back to you shortly.',
                    'created_at' => $replyCreatedAt,
                    'updated_at' => $replyCreatedAt,
                ]);

                // Client reply for pending tickets
                if ($status === 'pending') {
                    $clientReplyAt = $replyCreatedAt->copy()->addHours(rand(1, 12));
                    SupportTicketReply::create([
                        'ticket_id' => $ticket->id,
                        'client_id' => $client->id,
                        'message' => 'Thank you for the update. I am waiting for the resolution.',
                        'created_at' => $clientReplyAt,
                        'updated_at' => $clientReplyAt,
                    ]);
                }

                // Final admin reply for closed tickets
                if ($status === 'closed') {
                    $finalReplyAt = $replyCreatedAt->copy()->addHours(rand(24, 72));
                    SupportTicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $users->random()->id,
                        'message' => 'Your issue has been resolved. If you have any further questions, please feel free to create a new ticket.',
                        'created_at' => $finalReplyAt,
                        'updated_at' => $finalReplyAt,
                    ]);
                }
            }
        }

        $this->command?->info('Support tickets seeded successfully with ' . SupportTicket::count() . ' tickets.');
    }
}
