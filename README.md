# Insurance SMS Reminder (PHP)

Simple PHP app to import policies from Excel, prevent duplicate reminders, and send SMS via Infobip for expirations within a configurable window.

## Stack
- PHP 8.4, SQLite
- Tailwind CSS (CDN)
- Infobip PHP SDK, PhpSpreadsheet, phpdotenv

## Setup
1. Install deps (already done):
   - `php /workspace/composer install`
2. Copy env and configure:
   - `cp .env.example .env`
   - Set `DB_PATH`, `INFOBIP_BASE_URL`, `INFOBIP_API_KEY`, `AGENCY_CODE`.
   - Option A: Set `AGENCY_PASSWORD` (plaintext) for first run; the app will hash and store it, then you should remove the plaintext.
   - Option B: Generate a hash: `php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT), PHP_EOL;"` and set `AGENCY_PASSWORD_HASH`.
3. Run the dev server:
   - `php -S 0.0.0.0:8080 -t public`
   - Open http://localhost:8080

## Usage
- Login with your agency code and password.
- Go to Import Excel, upload a file with headers:
  - Insurance Number, Customer Name, Customer Phone Number, Start Date, End Date
- Dashboard shows policies, search/filter, export CSV.

## Cron (daily reminders)
Run once daily (adjust path):
```
* 8 * * * /usr/bin/php /workspace/cron/send_reminders.php >> /var/log/sms_cron.log 2>&1
```

This sends messages to policies with `end_date` between today and `EXPIRY_WINDOW_DAYS` (default 10), skipping already-notified ones, then marks them notified.

## Security
- Credentials are read from `.env` (never hardcode API keys).
- Sessions are HttpOnly and SameSite=Lax; CSRF tokens on forms.
- Passwords stored hashed using `password_hash`.
- SQLite file should be outside web root (`/storage`), ensure filesystem permissions.

## Notes
- Phone numbers are normalized to digits and `+`. Ensure country code is present.
- Policies before `IGNORE_POLICIES_BEFORE_YEAR` are ignored on import.
- Unique key `(insurance_number, end_date)` prevents duplicates per cycle.