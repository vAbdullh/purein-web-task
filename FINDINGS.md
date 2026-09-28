# Pure-IN Fuel Panel — Project Analysis

## 1. Project Overview

A minimal PHP/SQLite fuel-sales dashboard running inside Docker (Apache + PHP 8.2).

---

## 2. File-by-File Breakdown

| File | Role | Notes |
|---|---|---|
| [`index.php`](./index.php) | **Entry point / Login page** | Handles `POST` login, starts session, redirects on success |
| [`sales.php`](./sales.php) | **Protected dashboard** | Shows sales per station; only gate is `$_SESSION['user']` check |
| [`logout.php`](./logout.php) | **Session teardown** | Destroys session and redirects to login |
| [`db.php`](./db.php) | **Database bootstrap** | Opens SQLite, runs DDL + seed data on first run |
| [`style.css`](./style.css) | Styling | Basic CSS; no security surface |
| [`Dockerfile`](./Dockerfile) | Container definition | `php:8.2-apache`, copies everything into `/var/www/html/` |
| [`app.sqlite`](./app.sqlite) | **Live database file** | Committed to repo; served inside the web root |
| [`README.md`](./README.md) | Setup instructions | Docker build/run commands |
| [`PROMPTS.md`](./PROMPTS.md) | Dev notes | Records the AI prompts used to build this project |

---

## 3. Request Flow

```
Browser
  │
  ├── GET  /              → index.php  (renders login form)
  ├── POST /              → index.php  (validates credentials → session → redirect)
  │
  ├── GET  /sales.php?station=N  → sales.php
  │         └─ checks $_SESSION['user']  (only gate)
  │         └─ SQL query with $station interpolated directly into query string
  │         └─ renders HTML table
  │
  └── GET  /logout.php   → logout.php (session_destroy → redirect to index.php)
```

All three PHP files independently call `session_start()` and `require db.php`.

---

## 4. Authentication, Session Handling, Logout & Authorization

### 4.1 Authentication — [`index.php` L6–L16](./index.php#L6-L16)
- Username is looked up with a **prepared statement** ✅
- Password is compared with **plain `===` string equality** — passwords are stored in plaintext ❌
- On success, the **entire `$user` row** (including the plaintext password) is written into `$_SESSION['user']` ❌
- No rate-limiting or brute-force protection ❌

### 4.2 Session Handling — [`index.php` L2](./index.php#L2), [`sales.php` L2](./sales.php#L2)
- `session_start()` is called with **no configuration** (no `HttpOnly`, no `Secure`, no `SameSite` cookie flags; no session fixation protection) ❌
- Session ID is never regenerated after login ❌

### 4.3 Logout — [`logout.php`](./logout.php)
- `session_destroy()` is called ✅
- The **session cookie is not explicitly expired/deleted** from the browser ❌  
- No CSRF token required to trigger logout ❌ (GET request is sufficient)

### 4.4 Authorization — [`sales.php` L5–L12](./sales.php#L5-L12)
- Only check: `if (empty($_SESSION['user']))` → redirect ✅ (basic authentication gate)
- `is_admin` column exists in DB but **is never checked anywhere** ❌
- `station_id` on the user exists but **any authenticated user can query any station** by changing the `?station=` GET parameter ❌  
  (`manager_a` is bound to station 1 but can freely view stations 2 and 3)
- **No role-based access control (RBAC) exists** ❌

### 4.5 What Is Missing
| Feature | Status |
|---|---|
| Password hashing | ❌ Missing |
| Session ID regeneration after login | ❌ Missing |
| Secure/HttpOnly/SameSite cookie flags | ❌ Missing |
| CSRF protection (login & logout) | ❌ Missing |
| Station-level authorization for managers | ❌ Missing |
| Admin-only routes / `is_admin` enforcement | ❌ Missing |
| Brute-force / rate limiting | ❌ Missing |
| Audit / access logging | ❌ Missing |
| `exit` after logout redirect | ❌ Missing |

---

## 5. Database Schema

### Tables & Columns

#### `stations`
| Column | Type | Constraints |
|---|---|---|
| `id` | INTEGER | PRIMARY KEY |
| `name` | TEXT | NOT NULL |

#### `users`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | INTEGER | PRIMARY KEY | |
| `username` | TEXT | NOT NULL, UNIQUE | |
| `password` | TEXT | NOT NULL | **Stored in plaintext** |
| `station_id` | INTEGER | nullable (FK → `stations.id`) | NULL = head office |
| `is_admin` | INTEGER | NOT NULL DEFAULT 0 | Never enforced in code |

#### `sales`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | INTEGER | PRIMARY KEY | |
| `station_id` | INTEGER | NOT NULL (FK → `stations.id`) | No FK constraint declared |
| `sold_at` | TEXT | NOT NULL | ISO 8601 datetime string |
| `pump` | INTEGER | NOT NULL | |
| `fuel` | TEXT | NOT NULL | e.g. "Gasoline 91" |
| `litres` | REAL | NOT NULL | |
| `amount` | REAL | NOT NULL | SAR currency |

### Relationships
```
stations (1) ──< users     (many — station_id FK, nullable)
stations (1) ──< sales     (many — station_id FK, not nullable)
```
> [!NOTE]
> SQLite `FOREIGN KEY` constraints are **not declared** in the DDL, so referential integrity is not enforced by the database.

### Seed Users (from [`db.php` L33–L36](./db.php#L33-L36))
| username | password (plaintext) | station_id | is_admin |
|---|---|---|---|
| `admin` | `admin123` | NULL | 1 |
| `manager_a` | `manager_a` | 1 | 0 |
| `manager_b` | `pass1234` | 2 | 0 |

---

## 6. Security Weaknesses

| # | Issue | Evidence (file : line) | Priority | Suggested Fix |
|---|---|---|---|---|
| 1 | **SQL Injection** — `$station` GET param interpolated directly into SQL query | [`sales.php:14`](./sales.php#L14) | 🔴 Critical | Use a prepared statement: `$db->prepare('SELECT * FROM sales WHERE station_id = ? ORDER BY sold_at DESC')` then `->execute([$station])` |
| 2 | **Plaintext Password Storage** — passwords stored and compared as raw strings | [`db.php:33–36`](./db.php#L33-L36), [`index.php:11`](./index.php#L11) | 🔴 Critical | Hash passwords at seed time with `password_hash()` and verify with `password_verify()` |
| 3 | **Broken Access Control / IDOR** — any logged-in user can view any station's data via `?station=N` | [`sales.php:12`](./sales.php#L12) | 🔴 Critical | Enforce station scope: if `$user['station_id']` is not NULL, force `$station = $user['station_id']` and ignore the GET param |
| 4 | **Database File in Web Root** — `app.sqlite` is served as a static file by Apache | [`Dockerfile:4`](./Dockerfile#L4), `app.sqlite` in project root | 🔴 Critical | Move `app.sqlite` outside the web root (e.g. `/var/data/`) and update the DSN in `db.php`, or add Apache `<Files>` deny rule |
| 5 | **Sensitive Data in Session** — entire user row (including plaintext password) stored in `$_SESSION` | [`index.php:12`](./index.php#L12) | 🟠 High | Store only non-sensitive fields: `id`, `username`, `station_id`, `is_admin` |
| 6 | **No Session Fixation Protection** — session ID not regenerated after successful login | [`index.php:2,13`](./index.php#L2-L14) | 🟠 High | Call `session_regenerate_id(true)` immediately after confirming valid credentials, before redirect |
| 7 | **Missing Secure Cookie Flags** — no `HttpOnly`, `Secure`, or `SameSite` attributes on session cookie | [`index.php:2`](./index.php#L2), [`sales.php:2`](./sales.php#L2) | 🟠 High | Call `session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict'])` before `session_start()` |
| 8 | **No CSRF Protection** — logout triggered by a plain GET link; login form lacks CSRF token | [`logout.php`](./logout.php), [`index.php:30–34`](./index.php#L30-L34) | 🟠 High | Add a synchronizer token to the login form and change logout to a POST form with a CSRF token |
| 9 | **`is_admin` Flag Never Enforced** — admin column exists but no route or check uses it | [`db.php:19`](./db.php#L19), [`sales.php` entire file] | 🟡 Medium | Add an admin guard where administrative actions are needed; define which pages/actions require `is_admin = 1` |
| 10 | **Reflected XSS** — `$user['username']` and all `$row` values echo'd with `<?=` without escaping | [`sales.php:25`](./sales.php#L25), [`sales.php:41–45`](./sales.php#L41-L45), [`index.php:29`](./index.php#L29) | 🟡 Medium | Wrap all output in `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` |
| 11 | **`exit` Missing After Logout Redirect** — execution continues after `header('Location:')` | [`logout.php:4`](./logout.php#L4) | 🟡 Medium | Add `exit;` after every `header('Location: ...')` call |
| 12 | **Weak / Default Credentials in Seed** — `admin/admin123`, `manager_a/manager_a` hardcoded | [`db.php:33–36`](./db.php#L33-L36) | 🟡 Medium | Use environment variables or a secure seed process; never seed with trivial passwords |
| 13 | **No `FOREIGN KEY` Constraints** — referential integrity not enforced in SQLite DDL | [`db.php:9–29`](./db.php#L9-L29) | 🟢 Low | Add `FOREIGN KEY` declarations and enable `PRAGMA foreign_keys = ON` |
| 14 | **No Request Logging / Audit Trail** — `logs/log.log` exists but nothing writes to it | [`logs/log.log`](./logs/log.log) | 🟢 Low | Log login attempts (success/failure), station queries, and session events |
| 15 | **Database Committed to Repo** — `app.sqlite` tracked by Git, exposes all data in version history | `.gitignore` (not present for `app.sqlite`) | 🟢 Low | Add `app.sqlite` to `.gitignore`; add it to `.dockerignore` so it is not bundled in the image |
