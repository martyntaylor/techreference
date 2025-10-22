@props([
    'title' => null,
    'description' => null,
    'keywords' => null,
    'canonical' => null,
    'image' => null,
    'type' => 'website',
    'noindex' => false,
])

@php
$pageTitle = $title ?? config('app.name');
$metaDescription = $description ?? 'TechReference - Technical reference for developers covering network ports, error codes, file extensions, HTTP codes, and MIME types.';
$metaKeywords = $keywords ?? 'technical reference, network ports, error codes, file extensions, developer documentation';
$canonicalUrl = $canonical ?? url()->current();
$ogImage = $image ?? asset('images/og-default.png');
@endphp

<!-- Basic Meta Tags -->
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<meta name="keywords" content="{{ $metaKeywords }}">

@if($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow">
@endif

<!-- Canonical URL -->
<link rel="canonical" href="{{ $canonicalUrl }}">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="{{ $type }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:site_name" content="TechReference">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ $canonicalUrl }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">

<!-- Additional SEO -->
<meta name="author" content="TechReference">
<meta name="language" content="en">
<meta name="revisit-after" content="7 days">
