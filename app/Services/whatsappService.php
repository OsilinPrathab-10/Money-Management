<?php

namespace App\Services;

use App\Models\BankAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User; // if needed for type hinting

class GallaboxMessenger
{
    protected static function getBasePayload($name,$phone)
    {
        return [
            "channelId" => env('GALLABOX_CHANNEL_ID'),
            "channelType" => "whatsapp",
            "recipient" => [
                "name" => $name,
                "phone" => "91" . self::getLastTenDigits($phone)
            ]
        ];
    }
    protected static function getBasePayload_1($phone)
    {
        return [
            "channelId" => env('GALLABOX_CHANNEL_ID'),
            "channelType" => "whatsapp",
            "recipient" => [
                "name" => 'Customer',
                "phone" => "91" . self::getLastTenDigits($phone)
            ]
        ];
    }

    protected static function sendMessage($data)
    {
        try {
            $response = Http::withHeaders([
                'apiKey' => env('GALLABOX_API_KEY'),
                'apiSecret' => env('GALLABOX_API_SECRET'),
                'Content-Type' => 'application/json',
            ])->post('https://server.gallabox.com/devapi/messages/whatsapp', $data);
//dd($response);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Gallabox WhatsApp Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    protected static function getLastTenDigits($phone)
    {
        return substr(preg_replace('/\D/', '', $phone), -10);
    }

    public static function otp($user)
    {
        $data = self::getBasePayload($user->name,$user->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "otp_messages",
                "bodyValues" => [
                    "otp" => $user->otp,
                ]
            ]
        ];
        return self::sendMessage($data);
    }

    public static function otp_1($phone,$otp)
    {
        $data = self::getBasePayload_1($phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "otp_messages",
                "bodyValues" => [
                    "otp" => $otp,
                ]
            ]
        ];
        return self::sendMessage($data);
    }
    public static function sendPaymentLinkMessages($user, $link)
    {
        return 1;
        // Send to creator
        self::payment_link_creator($user, $link);

        // Send to customer
        self::payment_link_customer($user, $link);
    }
    public static function payment_link_customer($user, $link)
    {
        $data = self::getBasePayload($link->contacts->name,$link->contacts->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "payment_link_customer2",
                "bodyValues"=> [
                    "customer_name"=> $link->contacts->name,
                    "amount"=> $link->amount,
                    "description"=>$link->payment_for,
                    "order_id"=> $link->order_id,
                    "support_email"=> \get_setting('company_email')
                ],
                "buttonValues"=> [
                    [
                        "index"=> 0,
                        "sub_type"=> "url",
                        "parameters"=> [
                        "type"=> "text",
                            "text"=> "pay-now/".$link->ref_id,
                            "url"=> "https://merchant.slpe.in/"
                        ]
                    ]
                ],
                "headerValues"=> [
                    "mediaUrl"=> "https://files.gallabox.com/68144a6e94b9fc1afb80cb2c/57b9c8f9-3cd3-45e9-922e-ba7863fb60ac-paymentlinkgentration.png",
                    "mediaName"=> "paymentlink_gentration.png"
                ],
            ]
        ];
        return self::sendMessage($data);
    }

    public static function payment_link_creator($user, $link)
    {
        $data = self::getBasePayload($user->name,$user->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "payment_link_creator_4",
                "bodyValues"=> [
                    "creator_name"=> $link->user->name,
                    "customer_name"=> $link->contacts->name,
                    "customer_phone"=> $link->contacts->phone,
                    "amount"=> $link->amount,
                    "description"=>$link->payment_for,
                    "order_id"=> $link->order_id,
                    "pg_link"=>route('redirect_to_payment_link', $link->ref_id),
                    "support_email"=> \get_setting('company_email')
                ],
                "buttonValues"=> [
                    [
                        "index"=> 0,
                        "sub_type"=> "url",
                        "parameters"=> [
                        "type"=> "text",
                            "text"=> "pay-now/".$link->ref_id,
                            "url"=> "https://merchant.slpe.in/"
                        ]
                    ]
                ]
            ]
        ];
        return self::sendMessage($data);
    }

    public static function sendPaymentReceivedMessages($link)
    {
        return 1;

        self::payment_received_creator($link);
        self::payment_received_customer($link);
    }

    public static function payment_received_customer($link)
    {
        $data = self::getBasePayload($link->contacts->name, $link->contacts->phone);

        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "payment_received_customer",
                "bodyValues" => [
                    "customer_name" => $link->contacts->name,
                    "amount" => $link->amount,
                    "order_id" => $link->order_id,
                    "description" => $link->payment_for,
                    "support_email" => \get_setting('company_email'),
                ],
                "headerValues" => [
                    "mediaUrl" => "https://files.gallabox.com/68144a6e94b9fc1afb80cb2c/dd989361-caf8-41cc-8bd8-550a929f688f-paymentlinkstatusupdate.png",
                    "mediaName" => "paymentlinkstatus_update.png"
                ]
            ]
        ];

        return self::sendMessage($data);
    }

    public static function payment_received_creator($link)
    {
        $data = self::getBasePayload($link->user->name, $link->user->phone);

        // Calculate charges and final amount
        $charges = $link->fixed_value ?? 0; // Ensure you have this field available
        $final_amount = $link->amount - $charges;

        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "payment_received_creator",
                "bodyValues" => [
                    "creator_name"     => $link->user->name,
                    "customer_name"    => $link->contacts->name,
                    "customer_phone"   => $link->contacts->phone,
                    "amount"           => $link->amount,
                    "description"      => $link->payment_for,
                    "order_id"         => $link->order_id,
                    "charges"          => number_format($charges, 2),
                    "final_amount"     => number_format($final_amount, 2),
                    "support_email"    => \get_setting('company_email'),
                ],
                "headerValues" => [
                    "mediaUrl"  => "https://files.gallabox.com/68144a6e94b9fc1afb80cb2c/1456f12e-9478-453b-be07-348c98016866-paymentlinkstatusupdate.png",
                    "mediaName" => "paymentlinkstatus_update.png"
                ]
            ]
        ];

        return self::sendMessage($data);
    }
    public static function sendTopupRequestReceivedMessages($link)
    {
        return 1;

        self::topup_request_received_admin_notification($link);
        self::topup_request_received_customer($link);
    }
    public static function topup_request_received_customer($topup_request)
    {
        $data = self::getBasePayload($topup_request->user->name, $topup_request->user->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "new_topup_request_customer",
                "bodyValues" => [
                    "user_name"=> $topup_request->user->name,
                    "merchant_id"=> $topup_request->user->merchant_id,
                    "reference_id"=> $topup_request->reference_id,
                    "amount"=> $topup_request->amount,
                    "date_time"=> $topup_request->created_at->format('d-m-Y h:i A'),
                ],
                "headerValues" => [
                    "mediaUrl" => "https://files.gallabox.com/68144a6e94b9fc1afb80cb2c/94623db5-3362-407c-9664-fe9ecb806a5f-topuprequestadd.png",
                    "mediaName"=> "topuprequest_add.png"
                ]
            ]
        ];

        return self::sendMessage($data);
    }

    public static function topup_request_received_admin_notification($topup_request)
    {
        $notify_names = json_decode(\get_setting('topup_notify_names'), true) ?? [];
        $notify_phones = json_decode(\get_setting('topup_notify_phones'), true) ?? [];

        foreach ($notify_names as $key => $name) {
            // Check if phone exists for this key, else skip
            if (!isset($notify_phones[$key])) {
                continue;
            }

            $phone = $notify_phones[$key];
            $data = self::getBasePayload($name, $phone);

            $data["whatsapp"] = [
                "type" => "template",
                "template" => [
                    "templateName" => "new_topup_request_admin",
                    "bodyValues" => [
                        "admin_name"=> $name,
                        "user_name"=> $topup_request->user->name,
                        "merchant_id"=> $topup_request->user->merchant_id,
                        "reference_id"=> $topup_request->reference_id,
                        "amount"=> $topup_request->amount,
                        "date_time"=> $topup_request->created_at->format('d-m-Y h:i A'),
                    ],
                    "headerValues" => [
                        "mediaUrl" => "https://files.gallabox.com/68144a6e94b9fc1afb80cb2c/49953775-4b71-4d7b-a32e-f60656f2d64d-topuprequestadd.png",
                        "mediaName"=> "topuprequest_add.png"
                    ]
                ]
            ];

            // Send message for each contact separately
            self::sendMessage($data);
        }
    }
    public static function topup_request_approved($topup_request)
    {
        return 1;

        $data = self::getBasePayload($topup_request->user->name, $topup_request->user->phone);

        // Calculate charges and final amount
        $charges = $topup_request->final_amount ?? 0; // Ensure you have this field available
        $final_amount = $topup_request->amount - $charges;

        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "topup_request_approved",
                "bodyValues" => [
                    "user_name"        => $topup_request->user->name,
                    "amount"           => $topup_request->amount,
                    "reference_id"     => $topup_request->reference_id,
                    "charges"          => number_format($charges, 2),
                    "final_amount"     => number_format($final_amount, 2),
                    "support_email"    => \get_setting('company_email'),
                ],
                "headerValues" => [
                    "mediaUrl"  => "https://files.gallabox.com/68144a6e94b9fc1afb80cb2c/77501e6c-1764-40d0-85e9-7344ce42985f-TopupreqStatusupdate.png",
                    "mediaName" => "Top up req Status update.png"
                ]
            ]
        ];

        return self::sendMessage($data);
    }

    public static function topup_request_rejected($topup_request)
    {
        return 1;

        $data = self::getBasePayload($topup_request->user->name, $topup_request->user->phone);

        // Calculate charges and final amount
        $charges = $topup_request->final_amount ?? 0; // Ensure you have this field available
        $final_amount = $topup_request->amount - $charges;

        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "topup_request_rejected",
                "bodyValues" => [
                    "user_name"        => $topup_request->user->name,
                    "amount"           => $topup_request->amount,
                    "request_id"       => $topup_request->reference_id,
                    "rejection_reason" => $topup_request->notes,
                    "support_email"    => \get_setting('company_email'),
                ],
                "headerValues" => [
                    "mediaUrl"  => "https://files.gallabox.com/68144a6e94b9fc1afb80cb2c/4a13e208-1cec-4dd9-8260-77dfad16a183-TopupreqStatusupdate.png",
                    "mediaName" => "Top up req Status update.png"
                ]
            ]
        ];

        return self::sendMessage($data);
    }
    public static function sendSupportTicketMessage($tickets)
    {
        return 1;

        self::send_ticket_notification_admin($tickets);
        self::send_ticket_notification_customer($tickets);
    }
    public static function send_ticket_notification_customer($ticket)
    {
        $data = self::getBasePayload($ticket->user->name, $ticket->user->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "support_ticket_created",
                "bodyValues" => [
                    "name"=> $ticket->user->name,
                    "ticket_id"=> "#".$ticket->unique_id,
                    "date"=> $ticket->created_at->format('d-m-Y h:i A'),
                    "support_email"=> \get_setting('company_email'),
                ]
            ]
        ];

        return self::sendMessage($data);
    }
    public static function send_ticket_notification_admin($ticket)
    {
        $notify_names = json_decode(\get_setting('support_ticket_names'), true) ?? [];
        $notify_phones = json_decode(\get_setting('support_ticket_phones'), true) ?? [];

        foreach ($notify_names as $key => $name) {
            // Check if phone exists for this key, else skip
            if (!isset($notify_phones[$key])) {
                continue;
            }

            $phone = $notify_phones[$key];
            $data = self::getBasePayload($name, $phone);

            $data["whatsapp"] = [
                "type" => "template",
                "template" => [
                    "templateName" => "new_support_ticket_alert_admin",
                    "bodyValues" => [
                        "admin_name"=> $name,
                        "ticket_id"=> "#".$ticket->unique_id,
                        "customer_name"=> $ticket->user->name,
                        "customer_phone"=> $ticket->user->phone,
                        "date"=> $ticket->created_at->format('d-m-Y h:i A'),
                        "ticket_subject"=> $ticket->message,
                        "support_email"=> \get_setting('company_email'),
                    ]
                ]
            ];

            // Send message for each contact separately
            self::sendMessage($data);
        }
    }

    public static function sendPayoutCreatedMessages($payout)
    {
        return 1;

        self::send_payout_created_notification_initiator($payout);
        self::send_payout_created_notification_customer($payout);
    }

    public static function send_payout_created_notification_customer($payout)
    {
        $data = self::getBasePayload($payout->contacts->name, $payout->contacts->phone);
        $bank = BankAccount::where('id',$payout->bank_account_id)->first();

        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "payout_initiated_1",
                "bodyValues" => [
                    "name"=> $payout->contacts->name,
                    "amount"=> $payout->amount,
                    "date"=> $payout->created_at->format('d-m-Y h:i A'),
                    "account_number"=> $bank->account_number,
                    "payout_id"=> $payout->payout_id,
                    "status"=> ucfirst($payout->status),
                    "support_email"=> \get_setting('company_email'),
                ]
            ]
        ];

        return self::sendMessage($data);
    }
    public static function send_payout_created_notification_initiator($payout)
    {
        $data = self::getBasePayload($payout->user->name, $payout->user->phone);
        $bank = BankAccount::where('id',$payout->bank_account_id)->first();
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "payout_initiated_initiator_1",
                "bodyValues" => [
                    "initiator_name"=>$payout->user->name,
                    "customer_name"=> $payout->contacts->name,
                    "customer_phone"=> $payout->contacts->phone,
                    "account_number"=> $bank->account_number,
                    "amount"=> $payout->amount,
                    "payout_id"=> $payout->payout_id,
                    "date"=> $payout->created_at->format('d-m-Y h:i A'),
                    "status"=> ucfirst($payout->status),
                    "support_email"=> \get_setting('company_email'),
                ]
            ]
        ];

        return self::sendMessage($data);
    }

    public static function sendPayoutStatusMessages($payout)
    {
        return 1;

        self::send_payout_status_notification_initiator($payout);
        self::send_payout_status_notification_customer($payout);
    }

    public static function send_payout_status_notification_customer($payout)
    {
        $data = self::getBasePayload($payout->contacts->name, $payout->contacts->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "payout_status_update",
                "bodyValues" => [
                    "name"=> $payout->contacts->name,
                    "payout_id"=> $payout->payout_id,
                    "amount"=> $payout->amount,
                    "date"=> $payout->created_at->format('d-m-Y h:i A'),
                    "status"=> ucfirst($payout->status),
                    "support_email"=> \get_setting('company_email'),
                ]
            ]
        ];

        return self::sendMessage($data);
    }

    public static function send_payout_status_notification_initiator($payout)
    {
        $data = self::getBasePayload($payout->user->name, $payout->user->phone);
        $bank = BankAccount::where('id',$payout->bank_account_id)->first();
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "payout_status_update_initiator",
                "bodyValues" => [
                    "initiator_name"=>$payout->user->name,
                    "customer_name"=> $payout->contacts->name,
                    "customer_phone"=> $payout->contacts->phone,
                    "account_number"=> $bank->account_number,
                    "payout_id"=> $payout->payout_id,
                    "amount"=> $payout->amount,
                    "date"=> $payout->created_at->format('d-m-Y h:i A'),
                    "status"=> ucfirst($payout->status),
                    "support_email"=> \get_setting('company_email'),
                ]
            ]
        ];

        return self::sendMessage($data);
    }
    public static function send_welcome_notification($user)
    {
        $data = self::getBasePayload($user->name, $user->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "welcome_to_slpe_infotech",
                "bodyValues" => [
                    "name"=>$user->name,
                    "support_email"=> \get_setting('company_email'),
                ]
            ]
        ];

        return self::sendMessage($data);
    }
    public static function sendKycReceivedMessages($user)
    {
        self::send_kyc_request_received_notification_admin($user);
        self::send_kyc_request_received_notification($user);
    }

    public static function send_kyc_request_received_notification($user)
    {
        $data = self::getBasePayload($user->name, $user->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "kyc_request_received",
                "bodyValues" => [
                    "name"=>$user->name,
                    "date"=>$user->kyc->updated_at->format('d-m-Y h:i A'),
                    "support_email"=> \get_setting('company_email'),
                ]
            ]
        ];

        return self::sendMessage($data);
    }
    public static function send_kyc_request_received_notification_admin($user)
    {
        $notify_names = json_decode(\get_setting('kyc_notification_names'), true) ?? [];
        $notify_phones = json_decode(\get_setting('kyc_notification_phones'), true) ?? [];

        foreach ($notify_names as $key => $name) {
            // Check if phone exists for this key, else skip
            if (!isset($notify_phones[$key])) {
                continue;
            }

            $phone = $notify_phones[$key];
            $data = self::getBasePayload($name, $phone);

            $data["whatsapp"] = [
                "type" => "template",
                "template" => [
                    "templateName" => "new_kyc_request_received",
                    "bodyValues" => [
                        "admin_name"=> $name,
                        "customer_name"=> $user->name,
                        "customer_phone"=> $user->phone,
                        "date"=> $user->kyc->updated_at->format('d-m-Y h:i A'),
                        "support_email"=> \get_setting('company_email'),
                    ]
                ]
            ];

            // Send message for each contact separately
            self::sendMessage($data);
        }
    }

    public static function send_kyc_status_approved_notification($user)
    {
        $data = self::getBasePayload($user->name, $user->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "kyc_approved",
                "bodyValues" => [
                    "name"=>$user->name,
                    "date"=>$user->kyc->updated_at->format('d-m-Y h:i A'),
                    "support_email"=> \get_setting('company_email'),
                ]
            ]
        ];

        return self::sendMessage($data);
    }

    public static function send_kyc_status_rejected_notification($user)
    {
        $data = self::getBasePayload($user->name, $user->phone);
        $data["whatsapp"] = [
            "type" => "template",
            "template" => [
                "templateName" => "kyc_rejected",
                "bodyValues" => [
                    "name"=>$user->name,
                    "date"=>$user->kyc->updated_at->format('d-m-Y h:i A'),
                    "support_email"=> \get_setting('company_email'),
                ]
            ]
        ];

        return self::sendMessage($data);
    }

}
