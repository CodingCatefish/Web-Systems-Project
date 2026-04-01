# Web-Systems-Project

This project includes a minimal PHP/MySQL backend for authentication while preserving the existing static HTML, CSS, and JavaScript storefront.

## Run locally

1. Install PHP 8.1+ and MySQL 8+.
2. Create the database using `database/schema.sql`.
3. Set environment variables if needed:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASSWORD`
   - `APP_ENV` (`production` enables stricter transport and config checks)
   - `ADMIN_EMAIL`
   - `ADMIN_PASSWORD` (optional temporary fallback; no default admin password is shipped)
   - `TRUSTED_PROXY_IPS` (comma-separated reverse proxy IPs allowed to supply forwarded HTTPS/client-IP headers)
   - `SHOW_RESET_DEBUG_LINK` (local development only; writes reset links to the server log and is blocked when `APP_ENV=production`)
4. Start the PHP development server from the project root:

```bash
php -S 127.0.0.1:3000
```

5. Visit `http://127.0.0.1:3000`

## Notes

- `login.php`, `signup.php`, `logout.php`, `admin.php`, and `api/session.php` implement the current backend auth/session flow.
- `includes/bootstrap.php` and `includes/auth.php` hold shared PHP helpers.
- The repository is now PHP-only at runtime; the previous Node backend entry point has been removed to avoid accidental deployment.
- Password reset requests are rate-limited and reset links are no longer rendered back into the browser.
- In production, insecure GET/HEAD requests are redirected to HTTPS and insecure non-idempotent requests are rejected; set `TRUSTED_PROXY_IPS` when TLS terminates at a reverse proxy so secure cookies and HSTS are applied correctly.
- Reviews are still browser-local and are not part of this migration.
