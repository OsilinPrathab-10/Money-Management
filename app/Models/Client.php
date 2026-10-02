<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Agent;
use App\Models\ClientKyc;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanAccount;
use App\Models\SupportTicket;
use App\Models\Nominee;
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\ChitCollection;
use App\Models\FixedDeposit;
use App\Models\KycDetail;
use App\Models\EmployeeInformation;
use App\Models\Payout;
use Illuminate\Support\Facades\Storage;
use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Support\Facades\Schema;

class Client extends Model
{
    use HasFactory, SoftDeletes, HasObfuscatedRouteKey;

    protected $appends = ['profile_image_url', 'mpin_set', 'mpin_status'];

    protected $table = 'clients';

    protected $fillable = [
        'user_id',
        'client_name',
        'nickname',
        'client_email',
        'client_phone',
        'fcm_token',
        'profile_image',
        'alternate_phone',
        'aadhaar_number',
        'address',
        'care_of',
        'flat',
        'street',
        'country',
        'state',
        'city',
        'district',
        'subdistrict',
        'pincode',
        'landmark',
        'post_office',
        'vtc',
        'aadhaar_photo_path',
        'date_of_birth',
        'gender',
        'location_id',
        'risk_level',
        'marital_status',
        'cibil_score',
        'assigned_to',
        'status',
        'mpin',
        'mpin_hash',
        'mpin_set_at',
        'accepted_terms',
        'accepted_privacy',
        'collection_day',
        'added_by',
        'customer_id',
    ];

    protected $hidden = [
        'mpin_hash',
    ];

    protected $casts = [
        'mpin_set_at' => 'datetime',
        'accepted_terms' => 'boolean',
        'accepted_privacy' => 'boolean',
        'date_of_birth' => 'date',
    ];

    public function getMpinSetAttribute(): bool
    {
        return !empty($this->mpin_hash) || !empty($this->mpin);
    }

    public function getMpinStatusAttribute(): bool
    {
        return !empty($this->mpin_hash) || !empty($this->mpin);
    }

    /**
     * Unique index allows many NULLs but only one empty string.
     * Flutter / forms often send "" when the field is unused.
     */
    public function setAlternatePhoneAttribute($value): void
    {
        $this->attributes['alternate_phone'] = static::normalizeOptionalPhone($value);
    }

    public static function normalizeOptionalPhone(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/', '', (string) $value);

        return $value === '' ? null : $value;
    }

    public function getProfileImageUrlAttribute()
    {
        // 1. Check direct profile_image
        if ($this->profile_image) {
            $path = ltrim($this->profile_image, '/');
            if (Storage::disk('public')->exists($path)) {
                return url('storage/' . $path);
            }
        }

        // 2. Check KYC selfie image (Primary source in Admin Panel)
        if ($this->kycDetail && $this->kycDetail->selfie_image) {
            $image = $this->kycDetail->selfie_image;
            
            // Check if Base64
            if (str_starts_with($image, 'data:')) {
                // Convert Base64 to file and return URL
                if (preg_match('/^data:image\/(\w+);base64,/', $image, $type)) {
                    $imageData = substr($image, strpos($image, ',') + 1);
                    $type = strtolower($type[1]);
                    
                    if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'], true)) {
                        $type = 'jpg';
                    }

                    $imageData = base64_decode($imageData);

                    if ($imageData !== false) {
                        $filename = 'selfie_' . md5($image) . '.' . $type;
                        $path = 'profile_images/' . $filename;

                        if (!Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->put($path, $imageData);
                        }

                        return url('storage/' . ltrim($path, '/'));
                    }
                }
            }
            
            // Check if it's already a full URL
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                return $image;
            }

            // Assume relative path in storage
            $path = ltrim($image, '/');
            if (Storage::disk('public')->exists($path)) {
                return url('storage/' . $path);
            }
        }

        // 3. Generate dynamic avatar with client's initials
        $name = $this->client_name ?? 'User';
        return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&size=200&background=4F46E5&color=fff";
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notifications()
    {
        return $this->hasMany(CustomerNotification::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(Agent::class, 'added_by');
    }

    public function kycDetail()
    {
        return $this->hasOne(KycDetail::class);
    }

    /**
     * KYC status of the client. Defaults to "unverified" when no KYC record exists.
     * Possible values: unverified | pending | verified | rejected
     */
    public function kycStatus(): string
    {
        $status = optional($this->kycDetail)->status;

        if (empty($status)) {
            return 'unverified';
        }

        return (string) $status;
    }

    /**
     * A client can only be active once KYC is verified by admin.
     */
    public function isKycVerified(): bool
    {
        return $this->kycStatus() === 'verified';
    }

    /**
     * Guard for any "set client active" action.
     * Returns the status that is actually allowed for the requested one.
     *
     * - active is allowed only when KYC is verified
     * - otherwise the client stays/falls back to pending
     */
    public function resolveAllowedStatus(?string $requestedStatus): string
    {
        $requested = strtolower(trim((string) $requestedStatus));

        // Manual/administrative states are always honoured.
        if (in_array($requested, ['inactive', 'blacklist', 'rejected', 'pending'], true)) {
            return $requested;
        }

        if (in_array($requested, ['active', 'verified'], true)) {
            return $this->isKycVerified() ? 'active' : 'pending';
        }

        return $this->status ?: 'pending';
    }

    /**
     * Inactive / blacklisted / rejected clients cannot use the customer app.
     */
    public function canAccessCustomerApp(): bool
    {
        return ! in_array(strtolower((string) $this->status), ['inactive', 'blacklist', 'rejected'], true);
    }

    public function employeeInformation()
    {
      return $this->hasOne(EmployeeInformation::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function loanAccounts()
    {
        return $this->hasMany(LoanAccount::class);
    }

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function fdApplications()
    {
        return $this->hasMany(FixedDepositApplication::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function accountDeletionRequests()
    {
        return $this->hasMany(AccountDeletionRequest::class);
    }

    public function nominee()
    {
        return $this->hasOne(Nominee::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function guarantors()
    {
        return $this->hasMany(Guarantor::class);
    }

    public function groupMembers()
    {
        return $this->hasMany(GroupMember::class, 'client_id');
    }

    public function chitMembershipShares()
    {
        return $this->hasMany(GroupMemberShare::class, 'client_id');
    }

    /**
     * Primary + shared memberships involving this client.
     */
    public function allChitMemberships()
    {
        return GroupMember::with(['group.scheme', 'shares.client', 'installments'])
            ->involvingClient((int) $this->id)
            ->orderByDesc('created_at');
    }

    public function chitGroups()
    {
        return $this->hasManyThrough(ChitGroup::class, GroupMember::class, 'client_id', 'id', 'id', 'group_id');
    }

    /**
     * Check if client has any loan, chit, or fixed deposit account or application.
     */
    public function hasAnyFinancialAccount(): bool
    {
        $hasLoans = $this->loanAccounts()->exists() || $this->loanApplications()->exists();
        $hasChits = $this->groupMembers()->exists();
        $hasFd = $this->fixedDeposits()->exists() || $this->fdApplications()->exists();

        return $hasLoans || $hasChits || $hasFd;
    }

    /**
     * Get public schedule details and URL for client.
     *
     * Always tokens from the client's own unique customer_id to avoid the
     * mismatch where small integer member_numbers (e.g. 1) decode back to
     * a completely different Client ID on the public schedule page.
     */
    public function getPublicScheduleDetails(): array
    {
        $hasLoans = $this->loanAccounts()->exists() || $this->loanApplications()->exists();
        $hasChits = $this->groupMembers()->exists();
        $hasFd   = $this->fixedDeposits()->exists() || $this->fdApplications()->exists();

        $hasAccount = $hasLoans || $hasChits || $hasFd;

        if (! $hasAccount) {
            return [
                'has_account'      => false,
                'public_url'       => null,
                'view_schedule_url'=> null,
                'message'          => "You don't have an active loan, chit, or fixed deposit account.",
            ];
        }

        // Use the client's own globally-unique customer_id as the identifier.
        // Do NOT use loan/chit/member numbers — they are sequential per-group
        // integers (e.g. 1, 2, 3) and are not unique across clients.
        $identifier = $this->customer_id ?: sprintf('CLIENT-%06d', $this->id);

        $token = base64_encode($identifier);
        $url   = url('/view-schedule/' . $token);

        return [
            'has_account'      => true,
            'token'            => $token,
            'public_url'       => $url,
            'view_schedule_url'=> $url,
            'message'          => 'Repayment schedule URL generated successfully.',
        ];
    }

    /**
     * Whether this client still has an open / collecting loan account.
     */
    public function hasActiveLoans(): bool
    {
        return $this->loanAccounts()
            ->whereIn('status', ['active', 'defaulted'])
            ->exists();
    }

    /**
     * Whether this client is still enrolled in an active chit group
     * (primary owner or share holder), and the seat is not closed/exited.
     */
    public function hasActiveChits(): bool
    {
        return GroupMember::query()
            ->involvingClient((int) $this->id)
            ->whereIn('status', ['active', 'approved', 'pending'])
            ->whereHas('group', fn ($q) => $q->whereIn('status', ['forming', 'active']))
            ->exists();
    }

    /**
     * Human-readable reason when client delete must be blocked.
     */
    public function deletionBlockReason(): ?string
    {
        $hasActiveLoans = $this->hasActiveLoans();
        $hasActiveChits = $this->hasActiveChits();

        if (! $hasActiveLoans && ! $hasActiveChits) {
            return null;
        }

        $parts = [];
        if ($hasActiveLoans) {
            $parts[] = 'active loan(s)';
        }
        if ($hasActiveChits) {
            $parts[] = 'active chit membership(s)';
        }

        return 'Cannot delete "' . ($this->client_name ?? 'this client')
            . '" because they have ' . implode(' and ', $parts) . '.'
            . ' Close or complete those accounts first, then try again.';
    }

    public function chitFamilyMember()
    {
        return $this->hasOne(ChitFamilyMember::class, 'client_id');
    }

    public function chitFamily()
    {
        return $this->hasOneThrough(ChitFamily::class, ChitFamilyMember::class, 'client_id', 'id', 'id', 'family_id');
    }

    public function wallet()
    {
        return $this->hasOne(CustomerWallet::class, 'client_id');
    }

    public function fixedDeposits()
    {
        return $this->hasMany(FixedDeposit::class, 'client_id');
    }

    /**
     * Records archived alongside the client are matched back on restore using this window,
     * because each model stamps its own deleted_at a few microseconds apart.
     */
    private const RESTORE_WINDOW_SECONDS = 15;

    /**
     * Archive every account belonging to this client so a recycle bin restore
     * can bring the whole relationship graph back.
     */
    public function cascadeSoftDelete(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->loanApplications()->get()->each->delete();

            $this->loanAccounts()->get()->each(function ($account) {
                $account->emis()->get()->each(function ($emi) {
                    $emi->collections()->get()->each->delete();
                    $emi->delete();
                });
                $account->delete();
            });

            $this->groupMembers()->get()->each(function ($member) {
                ChitCollection::where('member_id', $member->id)->get()->each->delete();
                Payout::where('winner_member_id', $member->id)->delete();
                $member->installments()->get()->each->delete();
                $member->delete();
            });

            $this->fixedDeposits()->get()->each->delete();

            if ($this->kycDetail) $this->kycDetail->delete();
            if ($this->employeeInformation) $this->employeeInformation->delete();
            if ($this->nominee) $this->nominee->delete();
        });
    }

    /**
     * Restore the client together with the accounts archived in the same delete.
     */
    public function restoreWithRelations(): void
    {
        $deletedAt = $this->deleted_at;

        \Illuminate\Support\Facades\DB::transaction(function () use ($deletedAt) {
            $this->restore();

            if (!$deletedAt) {
                return;
            }

            $from = $deletedAt->copy()->subSeconds(self::RESTORE_WINDOW_SECONDS);
            $to = $deletedAt->copy()->addSeconds(self::RESTORE_WINDOW_SECONDS);
            $inWindow = fn ($query) => $query->onlyTrashed()->whereBetween('deleted_at', [$from, $to]);

            $inWindow(LoanApplication::query()->where('client_id', $this->id))->restore();

            $accountIds = LoanAccount::withTrashed()->where('client_id', $this->id)->pluck('id');
            $emiIds = Emi::withTrashed()->whereIn('loan_account_id', $accountIds)->pluck('id');

            $inWindow(EmiCollection::query()->whereIn('emi_id', $emiIds))->restore();
            $inWindow(Emi::query()->whereIn('loan_account_id', $accountIds))->restore();
            $inWindow(LoanAccount::query()->where('client_id', $this->id))->restore();

            $memberIds = GroupMember::withTrashed()->where('client_id', $this->id)->pluck('id');
            $inWindow(ChitCollection::query()->whereIn('member_id', $memberIds))->restore();
            $inWindow(Installment::query()->whereIn('member_id', $memberIds))->restore();
            $inWindow(GroupMember::query()->where('client_id', $this->id))->restore();

            $inWindow(FixedDeposit::query()->where('client_id', $this->id))->restore();
            $inWindow(KycDetail::query()->where('client_id', $this->id)->whereNull('archived_at'))->restore();
            $inWindow(EmployeeInformation::query()->where('client_id', $this->id))->restore();
            $inWindow(Nominee::query()->where('client_id', $this->id))->restore();
        });

        if ($this->user_id && $this->user && $this->user->status === 'inactive') {
            $this->user->update(['status' => 'active']);
        }
    }

    /**
     * Counts shown in the recycle bin so an admin knows what a restore brings back.
     */
    public function trashedAccountSummary(): array
    {
        return [
            'loan_accounts' => LoanAccount::onlyTrashed()->where('client_id', $this->id)->count(),
            'loan_applications' => LoanApplication::onlyTrashed()->where('client_id', $this->id)->count(),
            'chit_memberships' => GroupMember::onlyTrashed()->where('client_id', $this->id)->count(),
            'fixed_deposits' => FixedDeposit::onlyTrashed()->where('client_id', $this->id)->count(),
        ];
    }

    /**
     * Public customer ID shown in admin, app, and documents (e.g. SDS-C0042).
     */
    public function displayCustomerId(): string
    {
        return filled($this->customer_id) ? (string) $this->customer_id : ('#' . $this->id);
    }

    /**
     * Alias used by older revenue views.
     */
    public function getClientCodeAttribute(): ?string
    {
        return $this->customer_id ?: null;
    }

    public static function customerIdPrefix(): string
    {
        $prefix = 'SDS';
        try {
            $config = LoanConfiguration::getAccountPrefixConfig();
            if ($config && $config->is_active && filled($config->prefix)) {
                $prefix = (string) $config->prefix;
            }
        } catch (\Throwable $e) {
            // Fall back to SDS when config is unavailable.
        }

        return rtrim($prefix, '- ') . '-C';
    }

    public static function makeCustomerId(int $numericId): string
    {
        return static::customerIdPrefix() . str_pad((string) $numericId, 4, '0', STR_PAD_LEFT);
    }

    public static function assignCustomerId(self $client, bool $force = false): bool
    {
        if (! Schema::hasColumn($client->getTable(), 'customer_id')) {
            return false;
        }
        if (! $force && filled($client->customer_id)) {
            return false;
        }

        $code = static::makeCustomerId((int) $client->id);
        if ($client->customer_id === $code) {
            return false;
        }

        $client->customer_id = $code;
        $client->saveQuietly();

        return true;
    }

    public static function backfillMissingCustomerIds(): int
    {
        if (! Schema::hasColumn((new static)->getTable(), 'customer_id')) {
            return 0;
        }

        $updated = 0;
        static::withTrashed()
            ->where(function ($q) {
                $q->whereNull('customer_id')->orWhere('customer_id', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($clients) use (&$updated) {
                foreach ($clients as $client) {
                    if (static::assignCustomerId($client)) {
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    protected static function booted()
    {
        static::created(function (self $client) {
            static::assignCustomerId($client);
        });

        static::deleting(function ($client) {
            $force = method_exists($client, 'isForceDeleting') && $client->isForceDeleting();

            if ($force) {
                // Permanent removal: strip accounting rows and hard delete every account.
                app(\App\Services\ClientAccountingCleanupService::class)->purgeForClient($client);

                $client->loanApplications()->withTrashed()->each(function ($app) {
                    if ($app->applicationDetail) $app->applicationDetail()->delete();
                    if ($app->disbursementDetail) $app->disbursementDetail()->delete();
                    $app->forceDelete();
                });

                $client->loanAccounts()->withTrashed()->each(function ($acc) {
                    $acc->emis()->withTrashed()->each(function ($emi) {
                        $emi->collections()->withTrashed()->forceDelete();
                        $emi->forceDelete();
                    });
                    $acc->forceDelete();
                });

                $client->groupMembers()->withTrashed()->each(function ($member) {
                    \App\Models\ChitCollection::withTrashed()->where('member_id', $member->id)->forceDelete();
                    $member->installments()->withTrashed()->forceDelete();
                    $member->forceDelete();
                });

                $client->fixedDeposits()->withTrashed()->forceDelete();

                \App\Models\KycDetail::withTrashed()->where('client_id', $client->id)->forceDelete();
                \App\Models\EmployeeInformation::withTrashed()->where('client_id', $client->id)->forceDelete();
                \App\Models\Nominee::withTrashed()->where('client_id', $client->id)->forceDelete();

                $client->guarantors()->forceDelete();
                $client->supportTickets()->forceDelete();
            } else {
                // Recycle bin: archive every account so the client can be restored intact.
                $client->cascadeSoftDelete();
            }

            // 5. Delete Associated User Login (Only if exclusively a client user, NOT an admin/staff/agent)
            if ($client->user_id && $client->user) {
                $user = $client->user;
                
                // Never touch the currently authenticated user (e.g. Admin performing deletion)
                $isCurrentAuthUser = auth()->check() && auth()->id() === $user->id;

                // Check if user is an Admin, Staff, Agent, or CreditVerifier (case-insensitive check)
                $userRoleNames = [];
                if (method_exists($user, 'getRoleNames')) {
                    $userRoleNames = $user->getRoleNames()->map(fn($r) => strtolower($r))->toArray();
                }
                
                $isAdminStaffOrAgent = $isCurrentAuthUser
                    || (method_exists($user, 'agent') && $user->agent()->exists())
                    || (method_exists($user, 'staff') && $user->staff()->exists())
                    || count(array_intersect($userRoleNames, ['admin', 'staff', 'agent', 'creditverifier'])) > 0;

                // Also check if any other non-deleted Client record still uses this user_id
                $hasOtherClients = static::where('user_id', $user->id)
                    ->where('id', '!=', $client->id)
                    ->exists();

                if (!$isAdminStaffOrAgent && !$hasOtherClients) {
                    if ($force) {
                        $user->delete();
                    } else {
                        $user->update(['status' => 'inactive']);
                    }
                }
            }
        });
    }
}
