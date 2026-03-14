<?php
define('DB_HOST', getenv('DB_HOST') ?: 'aws-1-ap-southeast-1.pooler.supabase.com');
define('DB_USER', getenv('DB_USER') ?: 'postgres.tfbjaybenekxymwozwek');
define('DB_PASS', getenv('DB_PASS') ?: 'mannysdatabase12');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_PORT', getenv('DB_PORT') ?: 5432);
 
function getDB(): PDO {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        DB_HOST, DB_PORT, DB_NAME
    );
 
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [    
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die('<div style="color:red;padding:20px">❌ DB Error: ' . $e->getMessage() . '</div>');
    }
}
 
function baht(float $n): string {
    return '฿' . number_format($n, 0);
}