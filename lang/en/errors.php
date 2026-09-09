<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Error Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for error messages throughout
    | the application. You are free to change these to better suit your
    | application's needs.
    |
    */

    'validation_failed' => 'The given data was invalid.',
    'invalid_credentials' => 'Invalid credentials.',

    // Premium
    'premium_blocks_required' => 'This content uses blocks reserved for the Premium plan (:blocks). Upgrade to Premium to use them.',
    'premium_blocks_locked' => 'This content has existing Premium blocks (:blocks): they stay visible but can no longer be added or edited without the Premium plan.',
    'premium_channel_limit' => 'Your plan is limited to :max editorial channel(s). Upgrade to a higher plan to create more.',
    'premium_channel_members_limit' => 'Your plan is limited to :max member(s) per channel. Upgrade to a higher plan to invite more members.',
    'premium_identity_verification' => 'Voter identity verification is a Premium-only feature.',

    // Billing (Stripe)
    'stripe_not_configured' => 'Online payment is not available right now.',

    'route_not_found' => 'The requested route does not exist.',
    'unauthenticated' => 'Unauthenticated.',
    'unauthorized' => 'Unauthorized.',
    'server_error' => 'Internal server error.',
    'service_unavailable' => 'Service temporarily unavailable.',
    'too_many_requests' => 'Too many requests. Please try again later.',
    'payload_too_large' => 'The uploaded file is too large.',
    'http_error' => 'HTTP Error :code',

];
