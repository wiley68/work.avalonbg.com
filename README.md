# Работен график

Мобилно уеб приложение (Vue 3 + Vite + PWA) с PHP API и SQLite: операции, таймер старт/стоп, дневна статистика и текстов експорт.

## Структура

- `public/` — входна точка за Apache (`index.php`, `.htaccess`). Статичният build отива в `public/spa/`.
- `lib/` — PDO, миграции, автентикация, JSON API.
- `config/` — `config.php` (не се комитива; копирай от `config.example.php`).
- `data/` — `app.sqlite` (не се комитива).
- `frontend/` — Vue 3 изходен код.
- `scripts/set_password.php` — задаване на парола за единствения потребител.

## База данни

Таблици: `users` (ред `id=1`, bcrypt парола), `operations`, `work_sessions`, `app_state` (ред `id=1` — текущ таймер).

## Инсталация на сървъра

1. Копирай `config/config.example.php` → `config/config.php`. Задай `base_path` на публичния URL път до папката `public` (без крайна `/`), напр. `/моят-сайт/public`. Ако виртуалният хост сочи директно към `public/`, остави `base_path` празен.
2. Права: уеб сървърът трябва да може да пише в `data/` (SQLite файлът се създава автоматично).
3. Парола:  
   `php scripts/set_password.php 'силна-парола'`
4. Frontend build (локално или на сървъра с Node):  
   `cd frontend && npm install && npm run build`
5. Отвори в браузър: `https://твой-домейн/.../public/spa/` (началният `index.php` пренасочва към `/spa/`).

## Локална разработка

Терминал 1 — PHP (от корена на проекта):

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Терминал 2 — Vite:

```bash
cd frontend && npm install && npm run dev
```

Задай `cors_origins` в `config.php` на `['http://127.0.0.1:5173']` за заявки с credentials от dev сървъра, или ползвай само proxy към `:8080` (по подразбиране `vite.config.ts` пренасочва `/api` към `http://127.0.0.1:8080`).

Променлива `VITE_PHP_ORIGIN` сменя целта на proxy.

## Защита

- Сесия + една парола в БД. За допълнителна защита: Basic Auth в Apache за папката `public` или ограничаване по IP.

## Още (бъдещи стъпки)

- Експорт XLSX/PDF, по-ясен offline режим за API (service worker в момента кешира основно shell).
