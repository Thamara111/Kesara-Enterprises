<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Database Connection Helper
 * Loads environment variables and establishes a connection to MySQL, handling connection errors gracefully.
 */

// Getting data -> Helper function to load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Checking data -> Skipping comment lines and empty lines in .env file
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        // Processing data -> Parsing KEY=VALUE pair from line
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);    
            $key = trim($key);
            $value = trim($value);

            // Formatting data -> Removing single or double quotes around variable values
            if (preg_match('/^"([^"]*)"$/', $value, $matches) || preg_match('/^\'([^\']*)\'$/', $value, $matches)) {
                $value = $matches[1];
            }

            // Saving data -> Registering environment variable in system environment arrays
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    return true;
}

// Getting data -> Loading .env environment configuration from project root directory
loadEnv(__DIR__ . '/../.env');

// Getting data -> Reading database configuration values with fallback defaults
$host    = getenv('DB_HOST') ?: 'localhost';
$db      = getenv('DB_NAME') ?: 'kesara_db';
$user    = getenv('DB_USER') ?: 'root';
$pass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
$db_error = null;

try {
    // Connecting database -> Creating PDO connection object with MySQL credentials
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Handling errors -> Catching database connection failure
    $db_error = $e->getMessage();
}
?>
