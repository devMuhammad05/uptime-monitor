# Uptime Monitor API

A Laravel 13 REST API for tracking the availability of URLs. You register a URL, the system records check results against it, and you can query the history to see how it has been performing over time.

---

## Requirements

- PHP 8.4 or higher
- Composer
- Node.js and npm (for asset compilation)
- SQLite (default) or any database Laravel supports

---

## Setup

Clone the repository and install dependencies:

```bash
composer install
npm install
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

Build frontend assets:

```bash
npm run build
```

Start the development server:

```bash
php artisan serve
```

If you want the queue worker running alongside (for background jobs):

```bash
php artisan queue:listen
```

There is also a Composer shortcut that runs the server, queue listener, and Vite dev server together:

```bash
composer dev
```

---

## Running Tests

The test suite uses Pest and runs against an in-memory SQLite database, so no extra database setup is needed.

```bash
composer test
```

This runs a code style check followed by the full test suite. All three API endpoints are covered end to end, including validation rules, edge cases, pagination, and error responses. The tests also cover factory states like timed-out checks and checks with null response times.

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
| `check_interval` | integer | no | Minutes between checks. Between 1 and 60. Defaults to 5. |
| `threshold` | integer | no | Consecutive failures before marking the monitor as down. Minimum 1. Defaults to 3. |

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
| `page` | 1 | Page number |
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

A `status_code` of `0` and a `null` `response_time_ms` indicate the check timed out.

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

A monitor can be in one of three states:

- `pending` — created but not yet checked
- `up` — the URL is responding successfully
- `down` — the URL has failed more consecutive times than the configured threshold

---

## Design Principles

**Type hinting**

Every method across the codebase carries full PHP 8.4 type hints — parameter types, return types, and property types.


**Testing**

The project is fully tested end to end using Pest. Every API endpoint is covered, including validation failures, edge cases like timed-out checks with a `status_code` of `0` and a `null` response time, pagination boundaries, and 404 behaviour for non-existent monitors. Tests run against an in-memory SQLite database, so they are fast and self-contained with no external dependencies.

---

## Notes

- The API endpoints are currently public and do not require authentication. 
- The queue driver is set to `database` by default, which is where background checking jobs would run once implemented.

