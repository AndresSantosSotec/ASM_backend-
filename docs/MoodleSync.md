# Moodle Sync

Use the `php artisan moodle:sync {id}` command to import or update a course from Moodle.


The service reads the following environment variables:

- `MOODLE_URL` / `NEXT_PUBLIC_MOODLE_URL` – base URL of your Moodle instance
- `MOODLE_ALT_URL` / `NEXT_PUBLIC_MOODLE_ALT_URL` – optional fallback URL
- `MOODLE_TOKEN` / `NEXT_PUBLIC_MOODLE_TOKEN` – webservice token
- `MOODLE_FORMAT` / `NEXT_PUBLIC_MOODLE_FORMAT` – response format (defaults to `json`)

Set them in your `.env` file or provide them via deployment configuration.

