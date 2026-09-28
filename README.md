# Pure-IN Fuel Panel
*started at 28/09/2026 4:42PM, total spent time 03:04:18.42*  
*(Note: I Took a long break, which is why it took longer to submit)*

A simple PHP application for a fuel panel dashboard, using an SQLite database.

---

## Setup

**Requirements:** Docker installed.

1. Build and start:
   ```powershell
   # Build the image
   docker build -t purein-web-app .

   # Run the container
   docker run -d --name purein-web-container -p 8080:80 --env-file .env purein-web-app
   ```

2. Access the app:
   Open [http://localhost:8080](http://localhost:8080) in your web browser.

3. To stop:
   ```powershell
   docker stop purein-web-container
   ```
## Security setup

Set `ADMIN_PASSWORD`, `MANAGER_A_PASSWORD`, and `MANAGER_B_PASSWORD` to unique passwords of at least 12 characters and pass them to Docker with `-e ADMIN_PASSWORD -e MANAGER_A_PASSWORD -e MANAGER_B_PASSWORD`. These are required on first startup and when replacing the original default credentials in an existing database. Existing data is migrated transactionally.

``` powershell
cp .env.example .env
```

## Security changes

The changes below address the original security issues documented in [FINDINGS.md](./FINDINGS.md). See that file for the detailed findings, priorities, and suggested fixes; it describes the original state before these changes.

- **Authentication:** hashed passwords, environment-based seed credentials, and a limit of five login attempts per username or IP address per 15 minutes.
- **Sessions and logout:** session ID renewal after login, no passwords stored in sessions, HttpOnly/SameSite cookies, Secure cookies on HTTPS, and full session and cookie cleanup on logout.
- **Request and data protection:** CSRF tokens for login and POST-only logout, prepared sales queries, and escaped HTML output.
- **Station permissions:** managers can access only their assigned station; admins can select all stations. Permissions are checked against the database on each sales request.
- **Database and private files:** foreign-key enforcement, migration of existing data, Apache rules blocking private files, and Git/Docker exclusions for the database and credentials.
- **Audit logging:** login attempts, station access, and logout events are saved as JSON Lines in `logs/audit-YYYY-MM-DD_HH-mm-ss-Asia-Riyadh.log`, using the actual event time. File writes are locked, with fallback to PHP's error log if writing fails.

PHP syntax and functional checks passed on a temporary copy. Apache access rules and HTTPS cookie behavior still need verification in the deployed container.

## Addtional requirements for prod environment

- *HTTPS*: session cookies receive the Secure flag on HTTPS (local HTTP remains supported).
## Error handling

Login errors appear in the login form. Rate-limited requests keep HTTP 429, show “Too many login attempts. Please try again after 15 minutes.” without a countdown, and include a Back to login button. The Retry-After header reflects the remaining limit window.

Forbidden access, invalid or missing stations, expired forms, and incorrect logout requests show a clear error page with Back to sales for signed-in users or Back to login for visitors. Unexpected application failures show a generic message while technical details go to the server error log. Apache uses the same page for 400, 403, 404, and 500 responses.

- **Hidden private resources:** protected files and directories return HTTP 404 with the same Page not found message as missing resources, rather than revealing them with HTTP 403. Access restrictions remain enforced.
- **Wrong station:** attempts to view another manager's station return HTTP 404 with a Wrong station message and a Back to sales button.
- **Hidden implementation errors:** unexpected application failures show a generic error page. Stack traces, internal paths, and exception details are kept out of browser responses and recorded in the server error log.

## Arabic and English

Use the العربية / English switch to choose a language. The choice is remembered in a cookie across login and logout. English is the default and fallback; only `ar` and `en` are accepted. Shared translations in `lang/ar.php` and `lang/en.php` cover login, sales, navigation, and error messages. Arabic uses RTL and English uses LTR with one shared stylesheet. Unknown station or fuel names retain their original text.

The Docker image includes PHP `intl` for localized numbers, SAR amounts, and Gregorian dates. Stored UTC sale times display in Asia/Riyadh in either language. Audit event names and timestamps remain independent of the interface language. The phone sales layout is deferred.