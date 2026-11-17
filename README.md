# Buzlook

QR-code based freezer inventory tracker (PHP 8.1+, PDO SQLite, no accounts).

Labels are pre-printed (in rolls) with a short URL such as `https://host/7Kq9Xz`. Only labels registered by the
admin work; anything else is denied and rate-limited.

## Run locally

```bash
php -S localhost:8000 index.php
php bin/create-api-token.php        # prints the admin API token once, stores its hash in config.local.php
```

Requires the `pdo_sqlite` and `fileinfo` PHP extensions.

## How a label behaves

| Label state | Scanned when… | Result |
|---|---|---|
| Not registered / unregistered | always | Denied ("Label not valid"); counted by the rate limiter |
| Registered, no type yet | nobody logged in | Can be set up as a **freezer label** (asks for a freezer name) |
| Registered, no type yet | logged in to a freezer | Becomes a **food label** of that freezer when the first item is saved; "Cancel and return to freezer" is offered |
| Freezer label | always | Logs in to that freezer and lists its items; offers search, label export (ZIP), and "Log out" |
| Food label | logged in to its freezer | Add item (with optional photo, quantity, text note, and up to 60s voice note), edit / re-label, or mark consumed |
| Food label | not logged in / other freezer | "Scan your freezer label first" / "Wrong freezer" |

Every page has a **Scan label** button:
- Supports live camera scanning on HTTPS/localhost with a **Switch camera** button (remembers the chosen camera).
- Plays a cheerful two-tone chime on a valid Buzlook QR scan, and a descending two-tone beep on an invalid QR, failed scan, or when closing/cancelling the scanner.
- Includes a "Take a photo of the QR code" fallback.

## Routes

| Method | Path | Purpose |
|---|---|---|
| GET | `/{code}` | Scan a label (see table above) |
| POST | `/{code}/freezer` | Set an untyped label up as a freezer label |
| POST | `/{code}/add` | Save a new item (photo optional & compressed client-side; optional quantity, note, voice note) |
| GET | `/{code}/edit` | Edit an active item or move it to another label |
| POST | `/{code}/edit` | Save item edits / re-label |
| POST | `/{code}/consume` | Mark the label's item as consumed |
| GET | `/export/labels` | JSON list of the logged-in freezer's labels for the client-side PNG/ZIP exporter |
| POST | `/logout` | Forget the freezer in this browser |
| GET | `/admin` | Admin dashboard (overview, batch generator & ZIP/PDF/CSV downloads, bulk register/unregister, label inspector, inactivity report & purge, rate-limit clear, DB backup) |

`{code}` is Base58 (no `0 O I l`), 6 characters by default (`code_length` in config). Any length from 1 to 64 characters
is accepted, so longer codes can be introduced later without breaking printed ones.

## Admin Dashboard (`/admin`)

Sign in at `/admin` using the same admin token created by `php bin/create-api-token.php`.

- **Overview & Storage:** Counts of registered, untyped (free stock), freezer, food, and disabled labels; active and
  consumed item counts; `uploads/` disk usage (with one-click orphan file cleanup); SQLite DB size and one-click
  `.sqlite` backup download.
- **Batch Label Generator & Downloads:** Generate $N$ codes (either **Created & Registered** immediately or **Generated
  Only / Unregistered** for pre-printed rolls) and download them directly in the browser as:
  - **ZIP of PNGs:** Individual `<code>.png` files with the QR code and only the human-readable code underneath.
  - **A4 PDF Grid:** Vector multi-page PDF with an invisible grid (default **Landscape A4, 8 columns × 4 rows**),
    with adjustable columns, rows, orientation, page margins, and optional light cut lines.
  - **2-Column CSV (`url,code`):** Ready for roll/label printing software or for bulk registration later.
- **Bulk Register / Unregister:** Paste codes/URLs or upload a 2-column CSV to register/enable or unregister/disable
  labels in bulk.
- **Single Label Inspector:** Look up or scan any code to see its status, type, parent freezer, active item, created
  timestamp, and `last_scan_at`, and enable, disable, or reset it back to untyped.
- **Inactive Labels Report (Last $N$ days, default $N = 730$):** Shows total registered codes not scanned in the last
  $N$ days (broken down into claimed labels vs. untyped shelf stock vs. never-scanned codes), lists abandoned freezers,
  and lets you purge inactive freezers ($> N$ days) along with their media files.
- **Rate-Limit Blocks:** Lists currently blocked client IPs and lets you clear all blocks with one click.

## Admin API

Send `Authorization: Bearer <token>`; bodies are JSON. Max `api_max_batch` (default 5000) codes per call.

| Endpoint | Body | Purpose |
|---|---|---|
| `POST /api/v1/tags/generate` | `{"count": 100, "length": 6}` | Returns new unique codes. **Nothing is stored.** `length` is optional. |
| `POST /api/v1/tags` | `{"count": 100, "length": 6}` | Creates the codes **and registers** them. |
| `POST /api/v1/tags/register` | `{"codes": ["7Kq9Xz", ...]}` | Registers previously generated codes (e.g. when sold); re-enables unregistered ones. |
| `POST /api/v1/tags/unregister` | `{"codes": ["7Kq9Xz", ...]}` | Disables codes (`enabled = 0`); history is kept. |

`generate` and `POST /api/v1/tags` answer with `{"count": n, "tags": [{"code": "...", "url": "https://host/..."}]}`.
`url` uses `public_base_url` from config, or the request host when unset.

```powershell
$h = @{ Authorization = "Bearer $token" }
Invoke-RestMethod http://host/api/v1/tags -Method Post -Headers $h -ContentType application/json -Body '{"count":10}'
```

Use HTTPS in production: the token travels in a header.

## Configuration

`config.php` holds defaults. Put environment specific values in `config.local.php` (git-ignored, never overwritten by
`deploy.ps1`): `api_token_hash`, `public_base_url`, `code_length`, `default_language`, `rate_limits`, `client_ip_header`,
`voice_max_seconds`, `max_voice_bytes`, `database_path`, ...

## Languages

UI texts live in `lang/{code}.php` (`en` and `tr` included). The language is chosen from the browser's
`Accept-Language` preference among the available files; if none matches, `default_language` from the config
is used (`en` unless changed in `config.local.php`). Missing keys fall back to the default language, then English.

To add a language, copy `lang/en.php` to e.g. `lang/de.php` and translate the values (keep the keys and
`{placeholders}`). Keys starting with `js.` are also sent to the browser scripts. API error messages stay English.

## Freezer page search & label export

- **Search**: Freezers with items show a search box that filters instantly by food name, label code, quantity, or note
  (case, accents and the Turkish i/İ/ı are ignored; several words must all match).
- **Export labels (ZIP)**: Generates high-resolution PNG QR codes (with the human-readable code printed underneath)
  for the freezer label and all of its food labels, packaged into a downloadable `.zip` archive directly in the browser.

## Data model

One `tags` table holds every label: `type` is `NULL` (registered, undecided), `freezer` (has a `name`) or `food`
(belongs to a freezer via `freezer_id`). `enabled = 0` means unregistered. `last_scan_at` stores the UTC timestamp of
the latest scan (also updated on the freezer label when one of its food labels is scanned). `items` reference the
freezer and the food label, and store `name`, `frozen_date`, optional `photo_path`, `quantity`, `note`, and `voice_path`.
`rate_limits` tracks failed scan and API authentication attempts per client IP.

## Deploy to XAMPP

`powershell -ExecutionPolicy Bypass -File deploy.ps1` publishes into `D:\Xampp\htdocs` and never touches `data/`,
`uploads/` content or `config.local.php`.
