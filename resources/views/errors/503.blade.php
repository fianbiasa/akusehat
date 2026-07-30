<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sedang Dalam Perawatan — AkuSehat</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            text-align: center;
            padding: 1.5rem;
        }
        .card { max-width: 28rem; }
        h1 { font-size: 1.5rem; margin-bottom: 0.75rem; }
        p { color: #94a3b8; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Sedang Dalam Perawatan</h1>
        <p>{{ $exception->getMessage() ?: 'Platform sedang dalam perawatan. Silakan coba lagi beberapa saat lagi.' }}</p>
    </div>
</body>
</html>
