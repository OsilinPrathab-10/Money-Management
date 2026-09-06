<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDetail extends Model
{
    protected $table = 'company_details';
    
    protected $fillable = [
        'company_name',
        'company_slogan',
        'company_email',
        'company_mobile',
        'alternate_mobile',
        'support_email',
        'support_mobile',
        'website_url',
        'logo_path',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'country',
        'gst_number',
        'pan_number',
        'cin_number',
        'facebook_url',
        'twitter_url',
        'linkedin_url',
        'instagram_url',
        'agent_contact_email',
        'agent_contact_mobile',
        'working_hours',
    ];
}
