<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default meta description
    |--------------------------------------------------------------------------
    |
    | Shown in search snippets and social previews. Override with APP_META_DESCRIPTION.
    |
    */

    'description' => env(
        'APP_META_DESCRIPTION',
        'Secure finance request platform for clients, staff, and administrators—submit applications, track status, and manage workflows in one place.',
    ),

    /*
    |--------------------------------------------------------------------------
    | Meta keywords (optional)
    |--------------------------------------------------------------------------
    |
    | Most search engines ignore this; kept for completeness and some crawlers.
    |
    */

    'keywords' => env('APP_META_KEYWORDS', 'finance, loan request, application, client portal, document upload'),

    /*
    |--------------------------------------------------------------------------
    | Open Graph / social share image
    |--------------------------------------------------------------------------
    |
    | Absolute URL recommended (set APP_OG_IMAGE). If empty, falls back to
    | APP_URL + this public path. Use a 1200×630 image for best results.
    |
    */

    'og_image' => env('APP_OG_IMAGE'),

    'og_image_path' => env('APP_OG_IMAGE_PATH', 'financer/assets/images/icons/favicon.png'),

    /*
    |--------------------------------------------------------------------------
    | Twitter / X card
    |--------------------------------------------------------------------------
    */

    'twitter_card' => env('APP_TWITTER_CARD', 'summary_large_image'),

    'twitter_site' => env('APP_TWITTER_SITE'),

    'twitter_creator' => env('APP_TWITTER_CREATOR'),

    /*
    |--------------------------------------------------------------------------
    | Robots
    |--------------------------------------------------------------------------
    */

    'robots' => env('APP_ROBOTS', 'index, follow'),

    /*
    |--------------------------------------------------------------------------
    | Theme color (browser UI)
    |--------------------------------------------------------------------------
    */

    'theme_color' => env('APP_THEME_COLOR', '#4f46e5'),

];
