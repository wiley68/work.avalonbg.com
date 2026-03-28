<?php

declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        global $config;
        $path = $config['database_path'];
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        self::$pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        self::$pdo->exec('PRAGMA foreign_keys = ON;');
        self::migrate(self::$pdo);

        return self::$pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                password_hash TEXT NOT NULL DEFAULT "",
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS operations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS work_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                operation_id INTEGER NOT NULL,
                started_at TEXT NOT NULL,
                stopped_at TEXT NOT NULL,
                duration_seconds INTEGER NOT NULL,
                work_date TEXT NOT NULL,
                FOREIGN KEY (operation_id) REFERENCES operations(id)
            );
        ');
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS app_state (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                current_operation_id INTEGER,
                current_started_at TEXT,
                FOREIGN KEY (current_operation_id) REFERENCES operations(id)
            );
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_work_sessions_date ON work_sessions(work_date);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_work_sessions_op ON work_sessions(operation_id);');

        $row = $pdo->query('SELECT COUNT(*) AS c FROM users')->fetch();
        if ((int) ($row['c'] ?? 0) === 0) {
            $pdo->exec('INSERT INTO users (id, password_hash) VALUES (1, "")');
        }
        $row = $pdo->query('SELECT COUNT(*) AS c FROM app_state')->fetch();
        if ((int) ($row['c'] ?? 0) === 0) {
            $pdo->exec('INSERT INTO app_state (id, current_operation_id, current_started_at) VALUES (1, NULL, NULL)');
        }
    }
}
