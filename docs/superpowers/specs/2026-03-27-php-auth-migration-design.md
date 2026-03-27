# Minimal PHP Auth Migration Design

## Goal

Replace the currently implemented Node.js auth/session backend with a minimal PHP/MySQL backend that preserves the existing frontend behavior for login, signup, logout, and admin access.

## Scope

- Add small PHP endpoints for:
  - `login.php`
  - `signup.php`
  - `logout.php`
  - `api/session.php`
  - `admin.php`
- Add shared PHP helpers for database access, session handling, validation, and safe redirects.
- Keep the current HTML, CSS, and JavaScript structure unless a route update is required.
- Align the MySQL schema to the implemented auth model by adding a `users` table with email/password-hash based login.

## Non-Goals

- No full PHP rewrite of the storefront.
- No review CRUD migration in this change.
- No books/admin CRUD implementation in this change.
- No broad visual redesign.

## Recommended Approach

Use PHP as a thin backend layer and preserve the existing frontend forms:

1. Move the current auth logic to PHP with PDO prepared statements.
2. Use PHP's built-in password hashing and native session handling.
3. Gate the admin page through `admin.php`, while preserving the current admin HTML markup.
4. Add a `users` table to the schema rather than rewriting the entire ERD-derived schema around the old `User` table.

This is the smallest safe change that satisfies the PHP/MySQL backend requirement for the currently implemented auth surface.

## Architecture

- `includes/bootstrap.php`
  - Environment-driven DB configuration
  - Session startup
  - Security headers
  - Shared response helpers
- `includes/auth.php`
  - Login/signup validation
  - Session access helpers
  - Admin guard
- Route files:
  - `login.php`
  - `signup.php`
  - `logout.php`
  - `api/session.php`
  - `admin.php`

## Data Model

Add an auth table:

- `users`
  - `id`
  - `name`
  - `email`
  - `password_hash`
  - `role`
  - `created_at`
  - `updated_at`

This matches the implemented auth flow and keeps future role-based admin checks possible.

## Error Handling

- Preserve simple text error responses for form posts.
- Return JSON only for session info.
- Log server-side failures with PHP `error_log`.
- Keep redirects for successful signup/login/logout.

## Security

- PDO prepared statements for SQL injection resistance.
- `password_hash` / `password_verify` for passwords.
- Native PHP sessions with `HttpOnly`, `SameSite=Lax`, and `Secure` in production.
- Validation and normalization for email, password, and name fields.

## Accessibility / Frontend Impact

- No required UI redesign.
- Keep current form markup and client-side validation.
- Only update form actions and protected admin routing where needed.

## Validation Plan

- PHP syntax check on all new PHP files.
- Schema inspection for `users` table definition.
- Route reference check for updated form/admin/logout paths.
- Manual login/signup/admin flow verification in a PHP runtime.
