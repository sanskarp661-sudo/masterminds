# Master Minds Coaching — Website

Website for Master Minds (MBA, Law, CUET, IPM, BBA, Hotel Management and Study Abroad coaching), with an admin panel.
Runs on Hostinger: PHP + MySQL.

| Path | What it is |
|---|---|
| `index.html` | The public website (one file, page links like `#/program/mba`). |
| `admin.html` | Admin panel: form submissions, results, reviews, gallery, website content, admins. |
| `api/index.php` | PHP API used by both pages. Talks to MySQL. |
| `api/config.sample.php` | Template for the server settings file `mm-config.php` (never committed). |
| `database/schema.sql` | MySQL tables and starter content. Import it once in phpMyAdmin. |
| `uploads/` | Photos uploaded from the admin panel are saved here on the server. |
| `config.js` | Tells the pages where the API is (`api/` by default). |
| `SETUP.md` | **Step-by-step Hostinger setup.** |

Live at **https://masterminds.mosaicengine.in**. Hostinger deploys this repo directly, so every push to `main` goes live. Admin panel: `/admin.html`.

## Still to do before going live
- Follow `SETUP.md`.
- The Student/Parent login on the website is a demo screen only.
- Replace the placeholder Refund, Terms and Privacy text.
