{{-- Static on purpose: no layout, database queries or chatbot, so it still renders when the database is down. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server error – Sport Manager</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem; text-align: center; font-family: Inter, system-ui, sans-serif; background: #f4f3ed; color: #111; }
        .code { font-size: 6rem; font-weight: 700; margin: 0; }
        h1 { font-size: 1.5rem; margin: 1rem 0 0.5rem; }
        p { color: #4b5563; max-width: 24rem; margin: 0; }
        a { display: inline-block; margin-top: 2rem; background: #111; color: #fff; padding: 0.75rem 2rem; font-size: 13px; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
    <p class="code">500</p>
    <h1>Server error</h1>
    <p>Something went wrong on our end. Please try again in a moment.</p>
    <a href="/">Back to Home</a>
</body>
</html>
