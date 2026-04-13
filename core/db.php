<?php

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'reservasi_badminton';
const DB_USER = 'root';
const DB_PASS = '';

function db() {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Extension pdo_mysql belum aktif di PHP.');
    }

    $host = getenv('DB_HOST') ?: DB_HOST;
    $port = getenv('DB_PORT') ?: DB_PORT;
    $envName = getenv('DB_NAME');
    $name = $envName ?: DB_NAME;
    $user = getenv('DB_USER') ?: DB_USER;
    $pass = getenv('DB_PASS');
    if ($pass === false) {
        $pass = DB_PASS;
    }

    $namesToTry = [$name];
    if ($envName === false || $envName === '') {
        $namesToTry[] = 'reservasi_badminton1';
    }

    $lastError = null;
    foreach (array_unique($namesToTry) as $dbName) {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e;
        }
    }

    if ($lastError) {
        throw $lastError;
    }

    return $pdo;
}

?>
