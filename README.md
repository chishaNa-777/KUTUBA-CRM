# KUTUBA-CRM

KUTUBA CRM web application (PHP, Apache, and PostgreSQL).

## Deploy on Render

1. Push this repository to GitHub and create a Blueprint in Render using the repository's `render.yaml`.
2. Set `APP_URL` to the public service URL (for example, `https://kutuba-crm.onrender.com`).
3. Before using the CRM, initialize the PostgreSQL database with the application's schema and any required seed data. No database schema or migration is included in this repository; restore your existing database or supply the schema separately.
4. Open the service URL. The root route redirects to the login page.

The web service reads Render's `DATABASE_URL`. For other hosting environments, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` instead. Do not commit credentials.

The Blueprint uses Render's free web and PostgreSQL plans for initial testing. Free PostgreSQL databases expire, and the web service's local filesystem is ephemeral; uploaded documents and photos can be lost on redeploy or restart. For production, select a paid PostgreSQL plan and attach a persistent disk mounted at `/var/www/html/uploads` to the web service.

Password reset links are returned by the password request endpoint; configure `APP_URL` so they use the public service address.
