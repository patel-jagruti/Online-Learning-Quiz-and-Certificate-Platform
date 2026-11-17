<?php
/**
 * QuizMaster LMS - Database Connection
 * PDO singleton with full error handling
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'quiz_lms');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO instance (singleton pattern).
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log the real error; never expose it to the browser
            error_log('[DB] Connection failed: ' . $e->getMessage());
            if (defined('DOING_API') && DOING_API) {
                header('Content-Type: application/json');
                http_response_code(503);
                die(json_encode(['error' => 'Database unavailable. Please try again later.']));
            }
            die('<h2 style="font-family:sans-serif;color:#c00;padding:2rem">
                  Database connection failed. Please contact the administrator.
                 </h2>');
        }
    }

    return $pdo;
}

/**
 * Convenience wrapper: prepare + execute and return the statement.
 *
 * @param string $sql    SQL with ? or :named placeholders
 * @param array  $params Positional or named values
 */
function dbQuery(string $sql, array $params = []): PDOStatement
{
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single row.
 */
function dbRow(string $sql, array $params = []): array|false
{
    return dbQuery($sql, $params)->fetch();
}

/**
 * Fetch all rows.
 */
function dbRows(string $sql, array $params = []): array
{
    return dbQuery($sql, $params)->fetchAll();
}

/**
 * Execute an INSERT and return the last-insert id.
 */
function dbInsert(string $sql, array $params = []): string|false
{
    dbQuery($sql, $params);
    return getDB()->lastInsertId();
}

/**
 * Execute an UPDATE / DELETE and return affected-row count.
 */
function dbExec(string $sql, array $params = []): int
{
    return dbQuery($sql, $params)->rowCount();
}
