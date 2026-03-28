#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Употреба: php scripts/set_password.php \"ВашатаПарола\"\n");
    exit(1);
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$password = $argv[1];
$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo = Db::pdo();
$pdo->prepare('UPDATE users SET password_hash = ?, updated_at = datetime("now") WHERE id = 1')->execute([$hash]);

echo "Паролата е зададена.\n";
