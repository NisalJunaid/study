<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Focus Lab</title>
    @vite(['resources/css/app.css'])
</head>
<body style="display:grid;place-items:center;min-height:100vh;padding:2rem">
    <section class="card" style="max-width:720px;width:100%">
        <span class="pill">O'Level Study Help</span>
        <h1 style="margin:.75rem 0">Focus Lab is ready</h1>
        <p class="muted">Authentication scaffolding is not yet installed in this repo. Sign in with seeded users once auth pages are added to access student/admin dashboards.</p>
    </section>
</body>
</html>
