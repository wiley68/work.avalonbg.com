<?php

declare(strict_types=1);

final class Api
{
    public static function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function readJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function dispatch(string $method, string $path): void
    {
        global $config;
        $origins = $config['cors_origins'] ?? [];
        if (is_array($origins) && $origins !== []) {
            $req = $_SERVER['HTTP_ORIGIN'] ?? '';
            if ($req !== '' && in_array($req, $origins, true)) {
                header('Access-Control-Allow-Origin: ' . $req);
                header('Vary: Origin');
            }
            if ($method === 'OPTIONS') {
                header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type');
                header('Access-Control-Allow-Credentials: true');
                http_response_code(204);
                exit;
            }
        }

        $parts = array_values(array_filter(explode('/', $path)));
        $seg0 = $parts[0] ?? '';

        if ($seg0 === 'auth') {
            self::routeAuth($method, $parts);
            return;
        }

        Auth::requireAuth();

        if ($seg0 === 'operations') {
            self::routeOperations($method, $parts);
            return;
        }
        if ($seg0 === 'timer') {
            self::routeTimer($method, $parts);
            return;
        }
        if ($seg0 === 'sessions') {
            self::routeSessions($method, $parts);
            return;
        }
        if ($seg0 === 'state') {
            self::routeState($method);
            return;
        }

        self::json(['error' => 'not_found'], 404);
    }

    private static function routeAuth(string $method, array $parts): void
    {
        $action = $parts[1] ?? '';
        if ($action === 'login' && $method === 'POST') {
            $body = self::readJsonBody();
            $password = (string) ($body['password'] ?? '');
            if ($password === '') {
                self::json(['error' => 'password_required'], 400);
            }
            if (!Auth::login($password)) {
                self::json(['error' => 'invalid_credentials'], 401);
            }
            self::json(['ok' => true]);
        }
        if ($action === 'logout' && $method === 'POST') {
            Auth::logout();
            self::json(['ok' => true]);
        }
        if ($action === 'me' && $method === 'GET') {
            self::json(['authenticated' => Auth::check()]);
        }
        self::json(['error' => 'not_found'], 404);
    }

    private static function routeOperations(string $method, array $parts): void
    {
        $pdo = Db::pdo();
        $id = isset($parts[1]) ? (int) $parts[1] : 0;

        if ($method === 'GET' && $id === 0) {
            $rows = $pdo->query('SELECT id, name, is_active, created_at FROM operations ORDER BY name COLLATE NOCASE')->fetchAll();
            self::json(['operations' => $rows]);
        }

        if ($method === 'POST' && $id === 0) {
            $body = self::readJsonBody();
            $name = trim((string) ($body['name'] ?? ''));
            if ($name === '') {
                self::json(['error' => 'name_required'], 400);
            }
            $st = $pdo->prepare('INSERT INTO operations (name, is_active) VALUES (?, 1)');
            $st->execute([$name]);
            self::json(['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'is_active' => 1]);
        }

        if ($id <= 0) {
            self::json(['error' => 'not_found'], 404);
        }

        if ($method === 'PATCH') {
            $body = self::readJsonBody();
            $fields = [];
            $params = [];
            if (array_key_exists('name', $body)) {
                $n = trim((string) $body['name']);
                if ($n === '') {
                    self::json(['error' => 'name_invalid'], 400);
                }
                $fields[] = 'name = ?';
                $params[] = $n;
            }
            if (array_key_exists('is_active', $body)) {
                $fields[] = 'is_active = ?';
                $params[] = !empty($body['is_active']) ? 1 : 0;
            }
            if ($fields === []) {
                self::json(['error' => 'no_fields'], 400);
            }
            $params[] = $id;
            $sql = 'UPDATE operations SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $st = $pdo->prepare($sql);
            $st->execute($params);
            if ($st->rowCount() === 0) {
                self::json(['error' => 'not_found'], 404);
            }
            self::json(['ok' => true]);
        }

        if ($method === 'DELETE') {
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM work_sessions WHERE operation_id = ?');
            $cnt->execute([$id]);
            if ((int) $cnt->fetchColumn() > 0) {
                self::json(['error' => 'operation_has_sessions'], 409);
            }
            $pdo->prepare('UPDATE app_state SET current_operation_id = NULL, current_started_at = NULL WHERE current_operation_id = ?')->execute([$id]);
            $st = $pdo->prepare('DELETE FROM operations WHERE id = ?');
            $st->execute([$id]);
            if ($st->rowCount() === 0) {
                self::json(['error' => 'not_found'], 404);
            }
            self::json(['ok' => true]);
        }

        self::json(['error' => 'method_not_allowed'], 405);
    }

    private static function routeTimer(string $method, array $parts): void
    {
        $pdo = Db::pdo();
        $action = $parts[1] ?? '';

        if ($method === 'POST' && $action === 'start') {
            $body = self::readJsonBody();
            $opId = (int) ($body['operation_id'] ?? 0);
            if ($opId <= 0) {
                self::json(['error' => 'operation_id_required'], 400);
            }
            $check = $pdo->prepare('SELECT id FROM operations WHERE id = ? AND is_active = 1');
            $check->execute([$opId]);
            if (!$check->fetch()) {
                self::json(['error' => 'operation_not_found'], 404);
            }

            $state = $pdo->query('SELECT current_operation_id, current_started_at FROM app_state WHERE id = 1')->fetch();
            if (!empty($state['current_operation_id']) && !empty($state['current_started_at'])) {
                self::json(['error' => 'timer_already_running'], 409);
            }

            $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $st = $pdo->prepare('UPDATE app_state SET current_operation_id = ?, current_started_at = ? WHERE id = 1');
            $st->execute([$opId, $now]);
            self::json(['ok' => true, 'started_at' => $now, 'operation_id' => $opId]);
        }

        if ($method === 'POST' && $action === 'stop') {
            $state = $pdo->query('SELECT current_operation_id, current_started_at FROM app_state WHERE id = 1')->fetch();
            $opId = (int) ($state['current_operation_id'] ?? 0);
            $started = (string) ($state['current_started_at'] ?? '');
            if ($opId <= 0 || $started === '') {
                self::json(['error' => 'no_active_timer'], 409);
            }

            $startDt = new DateTimeImmutable($started);
            $stopDt = new DateTimeImmutable('now');
            $duration = max(0, $stopDt->getTimestamp() - $startDt->getTimestamp());
            $workDate = $startDt->format('Y-m-d');

            $pdo->prepare('
                INSERT INTO work_sessions (operation_id, started_at, stopped_at, duration_seconds, work_date)
                VALUES (?, ?, ?, ?, ?)
            ')->execute([
                $opId,
                $startDt->format('Y-m-d H:i:s'),
                $stopDt->format('Y-m-d H:i:s'),
                $duration,
                $workDate,
            ]);
            $sessionId = (int) $pdo->lastInsertId();

            $pdo->exec('UPDATE app_state SET current_operation_id = NULL, current_started_at = NULL WHERE id = 1');

            self::json([
                'ok' => true,
                'session_id' => $sessionId,
                'duration_seconds' => $duration,
                'work_date' => $workDate,
            ]);
        }

        self::json(['error' => 'not_found'], 404);
    }

    private static function routeSessions(string $method, array $parts): void
    {
        if ($method !== 'GET') {
            self::json(['error' => 'method_not_allowed'], 405);
        }
        $date = $_GET['date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            self::json(['error' => 'date_required'], 400);
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare('
            SELECT s.id, s.operation_id, s.started_at, s.stopped_at, s.duration_seconds, s.work_date, o.name AS operation_name
            FROM work_sessions s
            JOIN operations o ON o.id = s.operation_id
            WHERE s.work_date = ?
            ORDER BY s.started_at ASC
        ');
        $st->execute([$date]);
        self::json(['sessions' => $st->fetchAll()]);
    }

    private static function routeState(string $method): void
    {
        if ($method !== 'GET') {
            self::json(['error' => 'method_not_allowed'], 405);
        }
        $pdo = Db::pdo();
        $state = $pdo->query('
            SELECT a.current_operation_id, a.current_started_at, o.name AS operation_name
            FROM app_state a
            LEFT JOIN operations o ON o.id = a.current_operation_id
            WHERE a.id = 1
        ')->fetch();
        self::json([
            'current_operation_id' => $state['current_operation_id'] !== null ? (int) $state['current_operation_id'] : null,
            'current_started_at' => $state['current_started_at'],
            'operation_name' => $state['operation_name'],
        ]);
    }
}
