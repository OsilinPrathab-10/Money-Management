<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Database setup required</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 42rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; color: #1a1a1a; }
    code, pre { background: #f4f4f5; padding: 0.2em 0.4em; border-radius: 4px; font-size: 0.9em; }
    pre { padding: 1rem; overflow-x: auto; }
    h1 { font-size: 1.5rem; }
    .hint { color: #52525b; font-size: 0.95rem; margin-top: 1.5rem; }
  </style>
</head>
<body>
  <h1>Database tables are missing osilin fix it</h1>
  <p>
    Laravel cannot find required tables (for example <code>users</code>). This usually means migrations have not been run
    for the database named in your <code>.env</code> file (<code>DB_DATABASE</code>).
  </p>
  <p><strong>Fix (from the project folder):</strong></p>
  <pre>php artisan migrate</pre>
  <p>Optional sample data:</p>
  <pre>php artisan db:seed</pre>
  <p class="hint">
    In XAMPP: create the MySQL database in phpMyAdmin if it does not exist, match the name in <code>.env</code>,
    then run the commands above. Clear your browser cookies for this site if errors persist after migrating.
  </p>
</body>
</html>
