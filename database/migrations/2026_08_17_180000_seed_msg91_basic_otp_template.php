<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $exists = DB::table('sms_templates')->where('identifier', 'basic_otp')->exists();

        $payload = [
            'name' => 'Basic OTP (MSG91)',
            'sms_body' => '##var1## is your verification code for ##var2##.',
            'template_id' => env('MSG91_OTP_TEMPLATE_ID', '69ef6a68ccafa7555605e383'),
            'status' => 1,
            'updated_at' => $now,
        ];

        if ($exists) {
            DB::table('sms_templates')->where('identifier', 'basic_otp')->update($payload);
        } else {
            DB::table('sms_templates')->insert(array_merge($payload, [
                'identifier' => 'basic_otp',
                'created_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('identifier', 'basic_otp')->delete();
    }
};
