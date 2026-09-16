<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * The database handle.
 *
 * Prepared statements only, exceptions on, emulation off — the same three
 * rules the Node layer follows, for the same reason: a registration form is
 * the most-probed surface on the site and string concatenation there is how
 * an academy loses its applicant list.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = (string)cfg('db.host', 'localhost');
    $port = (int)cfg('db.port', 3306);
    $name = (string)cfg('db.name', '');
    $charset = (string)cfg('db.charset', 'utf8mb4');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
        (string)cfg('db.user', ''),
        (string)cfg('db.password', ''),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements, so the driver never interpolates.
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ],
    );
    return $pdo;
}

/** True when the install has a database it can actually reach. */
function db_ready(): bool
{
    if (cfg('db.name', '') === '' ) return false;
    try {
        db()->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        error_log('[db] not reachable: ' . $e->getMessage());
        return false;
    }
}

/** @return array<int,array<string,mixed>> */
function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** @return array<string,mixed>|null */
function db_one(string $sql, array $params = []): ?array
{
    $rows = db_all($sql, $params);
    return $rows[0] ?? null;
}

function db_run(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/**
 * Run $work inside a transaction, rolling back on any throw.
 *
 * Used for exactly one thing here, and it is the important one: the
 * application row, its first event and the two notifications it causes are
 * written together or not at all, so a registration can never land in the
 * queue with nobody told about it.
 */
function db_transaction(callable $work): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
