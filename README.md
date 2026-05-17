# Uptime Monitor API

A Laravel 13 REST API for tracking the availability of URLs. You register a URL, the system polls it on a schedule, records each check result, and sends email alerts when a site goes down or recovers.

---

## How it works

1. The Laravel scheduler runs every minute and dispatches a `CheckMonitorJob` for every monitor whose `last_checked_at` is past its `check_interval`.
2. The job makes an HTTP GET request to the monitor's URL (10-second timeout, redirects not followed).
3. The result is recorded as a `MonitorCheck` row with the HTTP status code, response time, and whether the site was up (`2xx`/`3xx` = up, `4xx`/`5xx` or a connection failure = down).
4. Consecutive failures are counted. When they reach the monitor's `threshold`, the monitor is marked **down**.
5. A successful check resets the counter and marks the monitor **up**.
6. When a monitor transitions **to down** or **back up**, an email alert is sent to the address configured in `MONITOR_ALERT_EMAIL`.
7. `uptime_percentage` is recalculated after every check as `(up checks / total checks) * 100`.

---

## Requirements

- PHP 8.4 or higher
- Composer
- SQLite

---

## Setup

Clone the repository and install dependencies:

```bash
composer install
```

Copy the environment file and generate an application key:

```bash
cp .env.example .env
php artisan key:generate
```

Run migrations:

```bash
php artisan migrate
```

### Alert email

Open `.env` and set the address that should receive up/down alerts:

```env
MONITOR_ALERT_EMAIL=you@example.com
```

If this value is empty or not set, notifications are silently skipped.

Configure your mail driver as normal via the `MAIL_*` variables. For local development the default `log` driver writes mail to `storage/logs/laravel.log` so you can verify alerts are being triggered without needing an SMTP server.

### Queue worker

Jobs are dispatched to the queue. Start a worker so they are processed:

```bash
php artisan queue:listen
```

For production, manage this with a process supervisor such as Supervisor or systemd so it restarts automatically.

### Scheduler

The scheduler must run continuously for monitors to be polled. During development:

```bash
php artisan schedule:work
```

This runs the scheduler every minute in the foreground. For production, add a single cron entry to the server:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### Development shortcut

The following Composer command starts the HTTP server, queue worker, and Vite dev server together:

```bash
composer dev
```

The scheduler still needs to be started separately with `php artisan schedule:work`.

---

## Running Tests

The test suite uses Pest and runs against an in-memory SQLite database — no separate database or queue setup needed.

```bash
composer test
```

This runs a code style check followed by the full test suite. Coverage includes:

- All three API endpoints (create, list, history) with validation rules, defaults, pagination, and error responses.
- `CheckMonitorJob` — HTTP check recording (200, 301, 500, connection failures), threshold and consecutive-failure logic, uptime percentage calculation, `last_checked_at` updates, and all notification transitions (pending→down, up→down, down→up, stays up, stays down, failures below threshold, no alert email configured).

---

## API Reference

All endpoints are prefixed with `/api/v1`. Every response is JSON with this shape:

```json
{
  "status": "success",
  "message": "...",
  "data": {}
}
```

Errors follow the same shape with `"status": "error"` and an appropriate HTTP status code.

---

### Health check

```
GET /api/v1
```

Returns a plain string confirming the API is reachable.

---

### List monitors

```
GET /api/v1/monitors
```

Returns all registered monitors.

**Response 200**

```json
{
  "status": "success",
  "message": "Monitors retrieved successfully",
  "data": [
    {
      "id": 1,
      "url": "https://example.com",
      "check_interval": 5,
      "threshold": 3,
      "status": "pending",
      "consecutive_failures": 0,
      "uptime_percentage": null,
      "last_checked_at": null,
      "created_at": "2026-05-17T10:00:00.000000Z"
    }
  ]
}
```

---

### Create a monitor

```
POST /api/v1/monitors
Content-Type: application/json
```

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `url` | string | yes | Must be a valid HTTP or HTTPS URL. Must be unique. |
| `check_interval` | integer | no | Minutes between checks. Between 1 and 60. Defaults to `5`. |
| `threshold` | integer | no | Consecutive failures before marking the monitor as down. Minimum 1. Defaults to `3`. |

**Example**

```json
{
  "url": "https://example.com",
  "check_interval": 10,
  "threshold": 2
}
```

**Response 201**

```json
{
  "status": "success",
  "message": "Monitor created successfully",
  "data": {
    "id": 1,
    "url": "https://example.com",
    "check_interval": 10,
    "threshold": 2,
    "status": "pending",
    "consecutive_failures": 0,
    "uptime_percentage": null,
    "last_checked_at": null,
    "created_at": "2026-05-17T10:00:00.000000Z"
  }
}
```

**Response 422** — when validation fails

```json
{
  "status": "error",
  "message": "Validation failed",
  "data": {
    "url": ["The url field is required."]
  }
}
```

---

### Get monitor history

```
GET /api/v1/monitors/{id}/history
```

Returns paginated check results for a given monitor, ordered from most recent to oldest.

**Query parameters**

| Parameter | Default | Notes |
|---|---|---|
| `page` | 1 | Page number. |
| `per_page` | 15 | Results per page. Maximum 100. |

**Response 200**

```json
{
  "status": "success",
  "message": "Monitor history retrieved successfully",
  "data": [
    {
      "id": 1,
      "monitor_id": 1,
      "status_code": 200,
      "response_time_ms": 142,
      "is_up": true,
      "checked_at": "2026-05-17T10:05:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

A `status_code` of `0` and a `null` `response_time_ms` indicate a connection failure or timeout.

**Response 404** — when the monitor does not exist

```json
{
  "status": "error",
  "message": "Monitor not found",
  "data": null
}
```

---

## Monitor status values

| Status | Meaning |
|---|---|
| `pending` | Created but not yet checked. |
| `up` | The URL is responding successfully. |
| `down` | The URL has failed consecutively at least as many times as `threshold`. |

---

## Environment variables

| Variable | Required | Description |
|---|---|---|
| `APP_KEY` | yes | Laravel application key. Generated by `php artisan key:generate`. |
| `DB_CONNECTION` | no | Database driver. Uses `sqlite`. |
| `MONITOR_ALERT_EMAIL` | no | Email address to receive up/down alerts. Notifications are skipped when empty. |
| `MAIL_MAILER` | no | Mail driver (`smtp`, `log`, `ses`, etc.). Defaults to `log`. |
| `MAIL_FROM_ADDRESS` | no | Sender address for alert emails. |
| `QUEUE_CONNECTION` | no | Queue driver. Defaults to `database`. |
