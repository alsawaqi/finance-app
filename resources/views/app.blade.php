<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>{{ config('app.name', 'Finance App') }}</title>

    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags(config('seo.description')), 320) }}">
    @if(filled(config('seo.keywords')))
        <meta name="keywords" content="{{ config('seo.keywords') }}">
    @endif
    <meta name="author" content="{{ config('app.name') }}">
    <meta name="robots" content="{{ config('seo.robots') }}">
    <meta name="googlebot" content="{{ config('seo.robots') }}">
    <meta name="theme-color" content="{{ config('seo.theme_color') }}">
    <meta name="color-scheme" content="light dark">
    <meta name="application-name" content="{{ config('app.name') }}">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link rel="canonical" href="{{ url()->current() }}">

    <link rel="icon" href="{{ asset('financer/assets/images/icons/favicon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('financer/assets/images/icons/favicon.png') }}">

    @php
        $siteName = config('app.name', 'Finance App');
        $pageDescription = \Illuminate\Support\Str::limit(strip_tags(config('seo.description')), 200);
        $canonicalUrl = url()->current();
        $ogImage = config('seo.og_image');
        if (blank($ogImage)) {
            $ogImage = asset(config('seo.og_image_path'));
        }
        $localeShort = strtolower(substr(str_replace('_', '-', app()->getLocale()), 0, 2));
        $ogLocale = $localeShort === 'ar' ? 'ar_SA' : 'en_US';
    @endphp

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $siteName }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:locale" content="{{ $ogLocale }}">
    <meta property="og:image" content="{{ $ogImage }}">

    <meta name="twitter:card" content="{{ config('seo.twitter_card') }}">
    <meta name="twitter:title" content="{{ $siteName }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    @if(filled(config('seo.twitter_site')))
        <meta name="twitter:site" content="{{ config('seo.twitter_site') }}">
    @endif
    @if(filled(config('seo.twitter_creator')))
        <meta name="twitter:creator" content="{{ config('seo.twitter_creator') }}">
    @endif

    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $siteName,
        'description' => $pageDescription,
        'url' => rtrim((string) config('app.url'), '/').'/',
        'inLanguage' => str_replace('_', '-', app()->getLocale()),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    @vite(['resources/ts/app.ts'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
