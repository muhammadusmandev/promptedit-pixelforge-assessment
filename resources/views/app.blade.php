<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Standard favicon -->
    <link rel="icon" href="https://promptedit.com/favicon.ico" type="image/x-icon">

    <!-- PNG favicons for better quality -->
    <link rel="icon" type="image/png" sizes="32x32" href="https://promptedit.com/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://promptedit.com/favicon-16x16.png">

    <!-- Apple touch icons for iOS devices -->
    <link rel="apple-touch-icon" sizes="180x180" href="https://promptedit.com/apple-touch-icon.png">

    <!-- Android/Chrome -->
    <link rel="icon" type="image/png" sizes="192x192" href="https://promptedit.com/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="https://promptedit.com/android-chrome-512x512.png">
    <title>PromptEdit Image Generation App</title>
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>