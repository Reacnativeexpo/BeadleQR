
<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$host     = $_ENV['DB_HOST'] ?? 'aws-0-ap-southeast-1.pooler.supabase.com';
$port     = $_ENV['DB_PORT'] ?? '5432';
$dbname   = $_ENV['DB_NAME'] ?? 'postgres';
$user     = $_ENV['DB_USER'] ?? 'postgres.rpikbrrkqmpfpfyhuvzk';
$password = $_ENV['DB_PASSWORD'] ?? 'J@yj@y@1825';

if ($host === '' || $user === '' || $password === '') {
    error_log('Database configuration is incomplete.');
    http_response_code(500);
    exit('Database configuration error.');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

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
