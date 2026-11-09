# Master Minds Coaching — Website

Website for Master Minds (MBA, Law, CUET, IPM, BBA, Hotel Management and Study Abroad coaching), with an admin panel.

| File | What it is |
|---|---|
| `index.html` | The public website (HTML, CSS and JS in one file, page links like `#/program/mba`). |
| `admin.html` | Admin panel: form submissions, results, reviews, gallery and website content. |
| `config.js` | Supabase connection settings used by both pages. |
| `supabase/schema.sql` | Database tables, security rules and starter content. |
| `SETUP.md` | **Step-by-step guide to switch on the admin panel.** |

Every push to `main` is deployed automatically to GitHub Pages by `.github/workflows/deploy.yml`.
(One-time: repo **Settings → Pages → Source → GitHub Actions**.)

## Still to do before going live
- Follow `SETUP.md` to connect Supabase. Until then the site shows built-in content and enquiries are **not saved**.
- The Student/Parent login on the website is a demo screen only.
- Replace the placeholder Refund, Terms and Privacy text.
