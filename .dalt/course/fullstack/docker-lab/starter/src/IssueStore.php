<?php

declare(strict_types=1);

/**
 * A deliberately small data layer. Everything it needs arrives as configuration, so the
 * same image can run against any database without being rebuilt.
 */
final class IssueStore
{
    private ?PDO $connection = null;

    public function __construct(private readonly string $databaseUrl)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        if ($this->databaseUrl === '') {
            throw new RuntimeException('DATABASE_URL is not set.');
        }

        $parts = parse_url($this->databaseUrl);
        if ($parts === false || !isset($parts['host'], $parts['user'], $parts['path'])) {
            throw new RuntimeException('DATABASE_URL is malformed.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'], '/'),
        );

        $this->connection = new PDO($dsn, $parts['user'], $parts['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $this->connection;
    }

    /** @return list<array{id: int, title: string, status: string}> */
    public function all(): array
    {
        $statement = $this->connection()->query('SELECT id, title, status FROM issues ORDER BY id');

        return $statement === false ? [] : $statement->fetchAll();
    }
}
