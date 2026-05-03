<?php

require_once __DIR__ . '/config/db.php';

if (!$conn) {
    fwrite(STDERR, "Database connection failed: " . ($conn_error ?? 'unknown') . "\n");
    exit(1);
}

$migrationsDir = __DIR__ . '/migrations';
$doneTable     = 'migrations';

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS {$doneTable} (name VARCHAR(255) PRIMARY KEY, run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$done = [];
$res  = mysqli_query($conn, "SELECT name FROM {$doneTable}");
if ($res) {
while ($row = mysqli_fetch_assoc($res)) {
    $done[] = $row['name'];
}
}

$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        echo "Skip {$name}\n";
        continue;
    }
    $sql = file_get_contents($file);
    if (mysqli_multi_query($conn, $sql)) {
        do {
            mysqli_store_result($conn);
        } while (mysqli_next_result($conn));
        mysqli_query($conn, "INSERT INTO {$doneTable} (name) VALUES ('" . mysqli_real_escape_string($conn, $name) . "')");
        echo "Run {$name}\n";
    } else {
        fwrite(STDERR, "Migration failed: {$name} - " . mysqli_error($conn) . "\n");
        exit(1);
    }
}

echo "Done.\n";
