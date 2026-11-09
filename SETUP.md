# Setting up the website and admin panel on Hostinger

The site runs on your Hostinger hosting with your Hostinger MySQL database.

| Piece | Where it runs |
|---|---|
| Website (`index.html`) | Hostinger, in `public_html` |
| Admin panel (`admin.html`) | Hostinger, at `yourdomain.com/admin.html` |
| API (`api/index.php`) | Hostinger. PHP talks to the MySQL database. |
| Photos you upload | Hostinger, in `public_html/uploads/` |
| Enquiries, reviews, results, content | Your Hostinger MySQL database |

Do these steps once. After that, every change pushed to GitHub uploads itself to Hostinger.

---

## 1. Create the database tables

1. In **hPanel → Databases → Management**, create a database if you don't already have one.
   Note the **database name**, **username** and **password**. They look like `u123456789_masterminds`.
2. Click **Enter phpMyAdmin** next to that database.
3. Go to the **Import** tab, choose [`database/schema.sql`](database/schema.sql) from this repository and click **Import** (or **Go**).
   This creates the tables and fills in the website's current results, reviews, gallery and text.

> You can use an existing database. All the tables have their own names (`results`, `testimonials`, `gallery`, `content`, `inquiries`, `admins`, `rate_limits`), so check that none of these already exist in it.

## 2. Add your database details to the server

1. Open **hPanel → Files → File Manager**.
2. Go to the folder that **contains** `public_html` (one level up, usually `domains/yourdomain.com/`).
3. Create a new file called **`mm-config.php`** there. Paste in the contents of [`api/config.sample.php`](api/config.sample.php) and fill in:
   - `db_name`, `db_user`, `db_pass`: from step 1. `db_host` stays `localhost`.
   - `secret`: type any long random text.
   - `notify_email`: *(optional)* your email address, to get an email for every new enquiry.

Keeping this file outside `public_html` means nobody can open it in a browser, and deployments never overwrite it.
(If File Manager doesn't let you go above `public_html`, save it as `public_html/api/config.php` instead. That folder blocks direct access too.)

## 3. Upload the website

**Option A: automatic from GitHub (recommended).** Every push to `main` uploads the site.

1. In **hPanel → Files → FTP Accounts**, note the **FTP IP/hostname** and **username**, and set or reset the password.
2. In GitHub, open the repository **Settings → Secrets and variables → Actions → New repository secret** and add:
   | Name | Value |
   |---|---|
   | `FTP_SERVER` | the FTP hostname or IP, e.g. `ftp.yourdomain.com` |
   | `FTP_USERNAME` | the FTP username |
   | `FTP_PASSWORD` | the FTP password |
   | `FTP_DIR` | *(optional)* folder to upload into. Default `public_html/`. If your FTP account already opens inside `public_html`, set this to `./` |
3. Open the **Actions** tab → **Deploy to Hostinger** → **Run workflow**. Later pushes upload automatically.

**Option B: by hand.** Upload these into `public_html` with File Manager: `index.html`, `admin.html`, `config.js`, the `api` folder and the `uploads` folder.

Make sure the **`uploads`** folder is writable (File Manager → right-click → Permissions → `755`). Photos uploaded from the admin panel are saved there.

## 4. Create your admin account

Open **`https://yourdomain.com/admin.html`**.

The first time, it asks you to **create the first admin** (name, email, password). That page only works once. After that, everyone signs in normally.

You can add more admins, remove them, and change your password under **Admins & Password**.

---

## What the admin panel does

- **Form Submissions:** every enquiry from the counseling form. Search them, mark each New / Contacted / Enrolled / Closed, add private notes, delete, and export to CSV (opens in Excel). New ones show a red count in the menu.
- **Results:** success-story scores with optional student photo, India/Abroad tab and program.
- **Reviews:** student testimonials with optional photo.
- **Gallery:** upload several photos at once, add captions, reorder, hide or delete.
- **Website Content:** phone, email, home banner text and image, highlight numbers, About text and image, call-to-action, footer text, branches, education-loan points and program card descriptions.
- **Admins & Password:** add or remove admins and change your password.

Anything can be hidden without deleting it. Changes show on the website straight away.

## Good to know

- **Formatting:** in headings, wrap words in `*stars*` for the gold italic style, e.g. `Happy *Studying*`.
- **Photos:** JPG, PNG, WebP or GIF, up to 5 MB. Smaller photos make the site faster.
- **Security:**
  - Passwords are stored encrypted (hashed).
  - Sign-in is limited to 10 wrong attempts per 15 minutes.
  - The enquiry form accepts up to 5 enquiries per visitor every 10 minutes, and includes a hidden spam trap.
  - Only admins can read enquiries or change anything.
  - Use HTTPS on your domain (hPanel → Security → SSL), which Hostinger gives free.
- **If the server can't be reached,** the website keeps showing the last content it loaded, so it never looks broken.
- **Email alerts** use Hostinger's built-in mail. If they land in spam, mark them "not spam" once.
- **Website on a different domain from the API** (e.g. GitHub Pages): set `apiBase` in `config.js` to the full API address, and add the website's address to `allowed_origins` in `mm-config.php`. Hosting everything on Hostinger is simpler.
