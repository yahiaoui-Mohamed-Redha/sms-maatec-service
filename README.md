# Insurance SMS Reminder (PHP)

Simple PHP app to import policies from Excel, prevent duplicate reminders, and send SMS via Infobip for expirations within a configurable window.

## Stack
- PHP 8.4, MySQL
- Tailwind CSS (CDN)
- Infobip PHP SDK, PhpSpreadsheet, phpdotenv

## Setup
1. Install deps:
   - `php /workspace/composer install`
2. MySQL: create DB and tables, and seed agency 549:
   - `mysql -u root -p < database.sql`
3. Copy env and configure:
   - `cp .env.example .env`
   - Set `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
   - Set `INFOBIP_BASE_URL`, `INFOBIP_API_KEY`.
   - Default agency code is `549`. Password (from database.sql seed) is `test1234`.
4. Run the dev server:
   - `php -S 0.0.0.0:8080 -t public`
   - Open http://localhost:8080

## Usage
- Login with agency `549` and password `test1234`.
- Import Excel with headers:
  - Insurance Number, Customer Name, Customer Phone Number, Start Date, End Date
- Dashboard: search, filter, export CSV.

## Cron (daily reminders)
```
* 8 * * * /usr/bin/php /workspace/cron/send_reminders.php >> /var/log/sms_cron.log 2>&1
```

## Security
- Env-based secrets; CSRF on forms; session hardened; passwords hashed.

## Notes
- Phone numbers must include country code.
- Policies before `IGNORE_POLICIES_BEFORE_YEAR` are ignored on import.
- Uniqueness per `(insurance_number, end_date)` prevents duplicates per cycle.