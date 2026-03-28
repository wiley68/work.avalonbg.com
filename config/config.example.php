<?php

/**
 * Копирай като config/config.php и настрой пътищата според хостинга.
 */

declare(strict_types=1);

return [
    // Часова зона за work_date и статистика
    'timezone' => 'Europe/Sofia',

    // Път до SQLite (извън web root препоръчително)
    'database_path' => dirname(__DIR__) . '/data/app.sqlite',

    // Име на session cookie
    'session_name' => 'ws_sess',

    // Базов URL път на приложението (без крайна наклонена), напр. '/work/public' или ''
    'base_path' => '',

    // За CORS при dev (vite на :5173) — в production остави празен масив
    'cors_origins' => [],
];
