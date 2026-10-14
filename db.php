
<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

/*
|--------------------------------------------------------------------------
| SUPABASE DATABASE CREDENTIALS
|--------------------------------------------------------------------------
*/

$host     = $_ENV['DB_HOST'] ?? 'aws-0-ap-southeast-1.pooler.supabase.com';
$port     = $_ENV['DB_PORT'] ?? '5432';
$dbname   = $_ENV['DB_NAME'] ?? 'postgres';
$user     = $_ENV['DB_USER'] ?? 'postgres.rpikbrrkqmpfpfyhuvzk';

/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
| Set DB_PASSWORD in Render Environment Variables.
| Do not hardcode your database password here.
*/

$password = $_ENV['DB_PASSWORD'] ?? '';

if (
    trim($host) === '' ||
    trim($port) === '' ||
    trim($dbname) === '' ||
    trim($user) === '' ||
    $password === ''
) {
    error_log('Database configuration is incomplete.');
    http_response_code(500);
    exit('Database configuration error.');
}

/*
|--------------------------------------------------------------------------
| CONNECT TO SUPABASE
|--------------------------------------------------------------------------
*/

try {

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {

    error_log('Database connection failed: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to connect to the database. Please try again later.');
}
