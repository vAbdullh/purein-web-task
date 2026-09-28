# Pure-IN Fuel Panel
*started at 28/09/2026 4:42PM, total spent time $$:$$:$$.$$*

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

## Addtional requirements for prod environment

- *HTTPS*: session cookies receive the Secure flag on HTTPS (local HTTP remains supported).