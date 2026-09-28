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
   docker run -d --name purein-web-container -p 8080:80 purein-web-app
   ```

2. Access the app:
   Open [http://localhost:8080](http://localhost:8080) in your web browser.

3. To stop:
   ```powershell
   docker stop purein-web-container
   ```