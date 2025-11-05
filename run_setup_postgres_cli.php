<?php
// CLI wrapper to run PostgreSQL setup with an explicit DATABASE_URL
// Usage: php run_setup_postgres_cli.php "postgresql://user:pass@host/dbname"

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$url = $argv[1] ?? '';
if (!$url) {
    fwrite(STDERR, "Error: Provide DATABASE_URL as the first argument.\n");
    fwrite(STDERR, "Example: php run_setup_postgres_cli.php \"postgresql://user:pass@host/db\"\n");
    exit(1);
}

// Set environment variables for this process to ensure db.php prefers PostgreSQL
putenv("DATABASE_URL={$url}");
putenv("DB_TYPE=pgsql");
// Clear potential MySQL env leaks that could interfere
putenv("DB_HOST=");
putenv("DB_NAME=");
putenv("DB_USER=");
putenv("DB_PASS=");

require_once __DIR__ . '/setup_database_postgres.php';

echo "\nPostgreSQL setup script finished.\n";
?>