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
   - `ADMIN_EMAIL`
   - `ADMIN_PASSWORD` (optional temporary fallback; no default admin password is shipped)
4. Start the PHP development server from the project root:

```bash
php -S 127.0.0.1:3000
```

5. Visit `http://127.0.0.1:3000`

## Notes

- `login.php`, `signup.php`, `logout.php`, `admin.php`, and `api/session.php` implement the current backend auth/session flow.
- `includes/bootstrap.php` and `includes/auth.php` hold shared PHP helpers.
- Reviews are still browser-local and are not part of this migration.
- `server.js` remains in the repository as the previous Node implementation, but the active backend path is now PHP/MySQL for auth.
