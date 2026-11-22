<?php

namespace App\Services;

use App\Models\Client;
use App\Models\KycDetail;
use App\Models\Nominee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WebsiteClientConversionService
{
    public function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: $phone;
    }

    public function findClientByPhone(string $phone): ?Client
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return null;
        }

        return Client::query()
            ->where('client_phone', $phone)
            ->orWhere('client_phone', 'like', '%' . substr($phone, -10))
            ->first();
    }

    /**
     * @return array{client: Client, created: bool, user_created: bool}
     */
    public function convertToClient(string $name, ?string $email, string $phone, array $additionalData = []): array
    {
        $phone = $this->normalizePhone($phone);
        if (strlen($phone) < 10) {
            throw new \InvalidArgumentException(__('Invalid mobile number.'));
        }

        $existing = $this->findClientByPhone($phone);
        if ($existing) {
            $this->syncClientKycAndNominee($existing, $additionalData, $name);
            return ['client' => $existing, 'created' => false, 'user_created' => false];
        }

        return DB::transaction(function () use ($name, $email, $phone, $additionalData) {
            $user = User::query()->where('phone', $phone)->first();
            $userCreated = false;

            if (! $user) {
                $resolvedEmail = $this->resolveUniqueEmail($email, $phone, $name);
                $user = User::create([
                    'name' => $name,
                    'email' => $resolvedEmail,
                    'phone' => $phone,
                    'password' => Hash::make($phone),
                    'status' => 'active',
                    'must_change_password' => true,
                ]);
                $userCreated = true;

                if (method_exists($user, 'assignRole')) {
                    $user->assignRole('Client');
                }
            }

            $client = $user->client;
            if ($client) {
                $this->syncClientKycAndNominee($client, $additionalData, $name);
                return ['client' => $client, 'created' => false, 'user_created' => $userCreated];
            }

            $client = Client::create([
                'user_id' => $user->id,
                'client_name' => $name,
                'client_email' => $email ?: $user->email,
                'client_phone' => $phone,
                'status' => 'active',
                'accepted_terms' => true,
                'accepted_privacy' => true,
            ]);

            Client::assignCustomerId($client);

            $this->syncClientKycAndNominee($client, $additionalData, $name);

            return ['client' => $client, 'created' => true, 'user_created' => $userCreated];
        });
    }

    public function syncClientKycAndNominee(Client $client, array $data, string $applicantName): void
    {
        if (empty($data)) {
            return;
        }

        // 1. Update Client basic demographic columns if present
        $clientUpdates = [];
        if (!empty($data['aadhaar_number']) && empty($client->aadhaar_number)) {
            $clientUpdates['aadhaar_number'] = str_replace(' ', '', $data['aadhaar_number']);
        }
        if (!empty($data['gender']) && empty($client->gender)) {
            $clientUpdates['gender'] = $data['gender'];
        }
        if (!empty($data['dob']) && empty($client->date_of_birth)) {
            $clientUpdates['date_of_birth'] = $data['dob'];
        }
        if (!empty($data['address']) && empty($client->address)) {
            $clientUpdates['address'] = $data['address'];
        }
        if (!empty($data['city']) && empty($client->city)) {
            $clientUpdates['city'] = $data['city'];
        }
        if (!empty($data['state']) && empty($client->state)) {
            $clientUpdates['state'] = $data['state'];
        }
        if (!empty($data['pincode']) && empty($client->pincode)) {
            $clientUpdates['pincode'] = $data['pincode'];
        }
        if (!empty($clientUpdates)) {
            $client->update($clientUpdates);
        }

        // 2. Sync KYC Details (PAN, Aadhaar, Bank Details)
        $pan = !empty($data['pan_number']) ? strtoupper(trim($data['pan_number'])) : null;
        $aadhaar = !empty($data['aadhaar_number']) ? str_replace(' ', '', $data['aadhaar_number']) : null;
        $bankName = $data['bank_name'] ?? null;
        $accHolder = $data['bank_account_holder'] ?? $applicantName;
        $accNo = $data['bank_account_number'] ?? null;
        $ifsc = !empty($data['bank_ifsc']) ? strtoupper(trim($data['bank_ifsc'])) : null;
        $accType = $data['bank_account_type'] ?? 'savings';

        if ($pan || $aadhaar || $bankName || $accNo) {
            $kyc = KycDetail::where('client_id', $client->id)->first();
            $kycData = [
                'client_id' => $client->id,
                'aadhaar_number' => $aadhaar ?: ($kyc->aadhaar_number ?? null),
                'aadhaar_name' => $applicantName,
                'pan_number' => $pan ?: ($kyc->pan_number ?? null),
                'pan_name' => $applicantName,
                'bank_name' => $bankName ?: ($kyc->bank_name ?? null),
                'account_holder_name' => $accHolder ?: ($kyc->account_holder_name ?? $applicantName),
                'account_number' => $accNo ?: ($kyc->account_number ?? null),
                'ifsc_code' => $ifsc ?: ($kyc->ifsc_code ?? null),
                'account_type' => $accType ?: ($kyc->account_type ?? 'savings'),
                'status' => $kyc->status ?? 'pending',
            ];
            KycDetail::updateOrCreate(['client_id' => $client->id], $kycData);
        }

        // 3. Sync Nominee Details
        $nomineeName = $data['nominee_name'] ?? null;
        $nomineeRelation = $data['nominee_relation'] ?? null;
        $nomineePhone = $data['nominee_phone'] ?? null;

        if ($nomineeName || $nomineePhone) {
            Nominee::updateOrCreate(['client_id' => $client->id], [
                'client_id' => $client->id,
                'nominee1_name' => $nomineeName,
                'nominee1_relationship' => $nomineeRelation,
                'nominee1_mobile' => $nomineePhone,
            ]);
        }
    }

    protected function resolveUniqueEmail(?string $email, string $phone, string $name): string
    {
        $email = $email ? trim($email) : '';
        if ($email !== '' && ! User::query()->where('email', $email)->exists()) {
            return $email;
        }

        $base = Str::slug(Str::before($name, ' ') ?: 'customer', '.');
        if ($base === '') {
            $base = 'customer';
        }

        $candidate = $base . '.' . $phone . '@customers.local';
        if (! User::query()->where('email', $candidate)->exists()) {
            return $candidate;
        }

        return $base . '.' . $phone . '.' . Str::lower(Str::random(4)) . '@customers.local';
    }
}