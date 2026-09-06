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
use Illuminate\Support\Facades\Storage;
use App\Models\Concerns\HasObfuscatedRouteKey;

class Client extends Model
{
    use HasFactory, SoftDeletes, HasObfuscatedRouteKey;

    protected $appends = ['profile_image_url'];

    protected $table = 'clients';

    protected $fillable = [
        'user_id',
        'client_name',
        'client_email',
        'client_phone',
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
        'accepted_terms',
        'accepted_privacy',
        'collection_day',
        'added_by',
    ];

    public function getProfileImageUrlAttribute()
    {
        // 1. Check direct profile_image
        if ($this->profile_image) {
            return url(Storage::url($this->profile_image));
        }

        // 2. Check KYC selfie image (Primary source in Admin Panel)
        if ($this->kycDetail && $this->kycDetail->selfie_image) {
            $image = $this->kycDetail->selfie_image;
            
            // Check if Base64
            if (str_starts_with($image, 'data:')) {
                // Convert Base64 to file and return URL
                // 1. Extract image data
                if (preg_match('/^data:image\/(\w+);base64,/', $image, $type)) {
                    $imageData = substr($image, strpos($image, ',') + 1);
                    $type = strtolower($type[1]); // jpg, png, etc.
                    
                    if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                        $type = 'jpg'; // Default
                    }

                    $imageData = base64_decode($imageData);

                    if ($imageData === false) {
                        return null; // Invalid base64
                    }
                } else {
                    return null; // Not valid data URI
                }

                // 2. Generate unique filename
                $filename = 'selfie_' . md5($image) . '.' . $type;
                $path = 'profile_images/' . $filename;

                // 3. Save to storage if not exists
                if (!Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->put($path, $imageData);
                }

                // 4. Return URL
                return url(Storage::url($path));
            }
            
            // Check if it's already a full URL
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                return $image;
            }

            // Assume relative path in storage
            return url(Storage::url($image));
        }

        // 3. Generate dynamic avatar with client's initials
        $name = $this->client_name ?? 'User';
        return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&size=200&background=4F46E5&color=fff";
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
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

    protected static function booted()
    {
        static::creating(function ($client) {
            if (!isset($client->user_id)) {
                try {
                    // Formula: Full Mobile Number
                    $cleanPhone = trim($client->client_phone);
                    $defaultPassword = $cleanPhone;

                    $user = User::firstOrCreate(
                        ['phone' => $client->client_phone],
                        [
                            'name' => trim($client->client_name ?? 'Client'),
                            'email' => $client->client_email,
                            'password' => Hash::make($defaultPassword),
                        ]
                    );

                    $client->user_id = $user->id;

                    // Dispatch event for admin notification (only for new users)
                    if ($user->wasRecentlyCreated) {
                        event(new \App\Events\NewUserRegistrationEvent($user));
                    }
                } catch (\Throwable $e) {
                    // If user creation fails, try to get existing user
                    $user = User::where('phone', $client->client_phone)->first();
                    if ($user) {
                        $client->user_id = $user->id;
                    } else {
                        throw $e;
                    }
                }
            }
        });

        static::deleting(function ($client) {
            $force = method_exists($client, 'isForceDeleting') && $client->isForceDeleting();

            // 1. Delete Loan Applications and details
            $client->loanApplications()->each(function ($app) {
                if ($app->applicationDetail) $app->applicationDetail()->delete();
                if ($app->disbursementDetail) $app->disbursementDetail()->delete();
                $app->delete();
            });

            // 2. Delete Loan Accounts, EMIs and Collections
            $client->loanAccounts()->each(function ($acc) {
                $acc->emis()->each(function ($emi) {
                    $emi->collections()->delete();
                    $emi->delete();
                });
                $acc->delete();
            });

            // 3. Delete Personal / KYC Data (Cascading Soft/Force Delete)
            if ($client->kycDetail) $force ? $client->kycDetail->forceDelete() : $client->kycDetail->delete();
            if ($client->employeeInformation) $force ? $client->employeeInformation->forceDelete() : $client->employeeInformation->delete();
            if ($client->nominee) $force ? $client->nominee->forceDelete() : $client->nominee->delete();
            
            // 4. Delete Other Related Records (Hard Delete as no SoftDeletes)
            $client->guarantors()->delete();
            $client->supportTickets()->delete();

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
