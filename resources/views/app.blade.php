<!DOCTYPE html>
<html lang="{{ session('langue', 'fr') === 'wo' ? 'wo' : 'fr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#241339">
    <title inertia>FEGGU</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @php($ogDescription = 'Signaler une violence basée sur le genre au Sénégal, gratuitement et sans donner son nom : SMS au '.config('feggu.numero_court').', USSD, WhatsApp ou en ligne, en français et en wolof.')
    <meta name="description" content="{{ $ogDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="FEGGU">
    <meta property="og:title" content="FEGGU — Signaler une violence. Nous vous écoutons.">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="FEGGU : Waxal. Dinañu la dégg. Parlez, nous vous écoutons.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset('og-image.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&display=swap" rel="stylesheet">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
