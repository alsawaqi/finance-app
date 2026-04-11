<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    
    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'template_sid' => env('TWILIO_WHATSAPP_TEMPLATE_SID'),
        'template_fallback_to_body' => (function () {
            $v = env('TWILIO_WHATSAPP_TEMPLATE_FALLBACK_TO_BODY');
            if ($v === null || $v === '') {
                return true;
            }

            return filter_var($v, FILTER_VALIDATE_BOOLEAN);
        })(),
        /**
         * Empty .env values must not disable flags (filter_var('', BOOL) is false).
         */
        'registration_welcome_enabled' => (function () {
            $v = env('TWILIO_REGISTRATION_WELCOME_WHATSAPP_ENABLED');
            if ($v === null || $v === '') {
                return true;
            }

            return filter_var($v, FILTER_VALIDATE_BOOLEAN);
        })(),
        /**
         * Use Lookup v2 line_type_intelligence (paid add-on) to skip landline/toll-free etc.
         * On Lookup errors, the send is still attempted.
         */
        'registration_lookup_line_type' => (function () {
            $v = env('TWILIO_REGISTRATION_LOOKUP_LINE_TYPE');
            if ($v === null || $v === '') {
                return false;
            }

            return filter_var($v, FILTER_VALIDATE_BOOLEAN);
        })(),
        /** Bilingual WhatsApp after client submits a new request from the wizard */
        'request_submitted_whatsapp_enabled' => (function () {
            $v = env('TWILIO_REQUEST_SUBMITTED_WHATSAPP_ENABLED');
            if ($v === null || $v === '') {
                return true;
            }

            return filter_var($v, FILTER_VALIDATE_BOOLEAN);
        })(),
        /** Bilingual WhatsApp on client-action workflow stages */
        'client_stage_whatsapp_enabled' => (function () {
            $v = env('TWILIO_CLIENT_STAGE_WHATSAPP_ENABLED');
            if ($v === null || $v === '') {
                return true;
            }

            return filter_var($v, FILTER_VALIDATE_BOOLEAN);
        })(),
        /** WhatsApp / outbound copy (not APP_NAME) */
        'brand_name_en' => env('BRAND_NAME_EN', 'Nofa Cast'),
        'brand_name_ar' => env('BRAND_NAME_AR', 'نوفا كاست'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
