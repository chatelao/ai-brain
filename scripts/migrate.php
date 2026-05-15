<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\MigrationService;

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$db = new Database();
$migrationService = new MigrationService($db);

echo "Starting database migration...\n";
$logs = $migrationService->migrate();

foreach ($logs as $log) {
    echo "$log\n";
}

echo "Migration finished.\n";
