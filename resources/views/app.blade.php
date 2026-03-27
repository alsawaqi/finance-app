<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Finance App</title>
    @vite(['resources/css/app.css', 'resources/ts/app.ts'])
</head>
<body class="bg-slate-50 text-slate-900">
    <div id="app"></div>
</body>
</html>