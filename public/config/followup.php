<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Followup Status Options
    |--------------------------------------------------------------------------
    |
    | These are the predefined status options that agents can use when
    | updating followups for EMI collections.
    |
    */

    'status_options' => [
        'appointment_to_visit' => 'Appointment to Visit',
        'call_back' => 'Call Back',
        'promise_to_pay' => 'Promise To Pay',
        'unable_to_reach' => 'Unable to Reach',
        'visit_rescheduled' => 'Visit Rescheduled',
        'request_extension' => 'Request Extension',
        'financial_difficulty' => 'Financial Difficulty',
        'other_reasons' => 'Other Reasons',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Field Requirements
    |--------------------------------------------------------------------------
    |
    | Defines which statuses require date/time and notes
    |
    */

    'statuses_requiring_datetime' => [
        'appointment_to_visit',
        'call_back',
        'promise_to_pay',
        'visit_rescheduled',
        'request_extension',
        'financial_difficulty',
        'unable_to_reach',
        'other_reasons',
    ],

    'statuses_requiring_notes_only' => [
        // Moved to datetime required
    ],

    /*
    |--------------------------------------------------------------------------
    | Visit Status Options
    |--------------------------------------------------------------------------
    |
    | Status options for field visits
    |
    */

    'visit_status_options' => [
        'customer_met' => 'Customer Met',
        'customer_not_available' => 'Customer Not Available',
        'address_not_found' => 'Address Not Found',
        'house_locked' => 'House Locked',
        'refused_to_meet' => 'Refused to Meet',
        'met_family_member' => 'Met Family Member',
        'visit_rescheduled' => 'Visit Rescheduled',
    ],
];
