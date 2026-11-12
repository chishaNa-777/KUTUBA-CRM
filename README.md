# KUTUBA-CRM

KUTUBA CRM web application (PHP, Apache, and PostgreSQL).

## Deploy with Supabase on Render

1. Create a Supabase project and initialize its PostgreSQL database with the CRM schema and any required seed data. This repository does not include a schema or migration, so restore your existing database or supply the schema separately.
2. In Supabase, copy the PostgreSQL connection URI from **Connect**. Use the session pooler URI if the hosting environment cannot connect to Supabase's direct database address.
3. Push this repository to GitHub and create a Blueprint in Render using `render.yaml`.
4. In Render, set `APP_URL` to the public service URL (for example, `https://kutuba-crm.onrender.com`) and `DATABASE_URL` to the Supabase PostgreSQL connection URI. Keep credentials out of source control.
5. Open the service URL. The root route redirects to the login page.

The PHP service accepts PostgreSQL `DATABASE_URL` values using the `postgres://` or `postgresql://` scheme, including an optional `sslmode` query parameter. Alternatively, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`; SSL defaults to `require` and can be changed with `DB_SSLMODE`. Do not use a Supabase `anon` or `service_role` API key as the database password.

For local development, copy `.env.example` to `.env` and set `DATABASE_URL` to the Supabase PostgreSQL connection URI. The PHP connector reads `.env` only when `DATABASE_URL` is not already set in the process environment. The `.env` file is ignored by Git and Docker; configure `DATABASE_URL` separately in Render's service Environment settings.

The Blueprint uses Render's free web plan. Its local filesystem is ephemeral; uploaded documents and photos can be lost on redeploy or restart. For production, attach a persistent disk mounted at `/var/www/html/uploads` or move uploads to object storage.

Password reset links are returned by the password request endpoint; configure `APP_URL` so they use the public service address.
