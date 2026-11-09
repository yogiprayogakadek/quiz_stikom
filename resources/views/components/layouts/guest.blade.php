@props(['judul' => 'Masuk'])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul }} | {{ $appSettings->site_name }}</title>
    <link rel="icon" href="{{ $appSettings->logoUrl() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-brand-style />
</head>
<body class="min-h-screen bg-page">
    {{ $slot }}
    <x-global-progress />
</body>
</html>
