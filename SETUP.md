# Setup: masterminds.mosaicengine.in on Hostinger

Hostinger pulls this GitHub repository straight into the website. Every push to `main` goes live automatically.
Enquiries, reviews, results and website text are stored in your Hostinger MySQL database. Uploaded photos are stored in `uploads/` on the server.

You do steps 1–5 once. It takes about 15 minutes.

---

## 1. Connect the GitHub repository (skip if it's already deploying)

1. **hPanel → Websites → masterminds.mosaicengine.in → Manage → Advanced → GIT.**
2. Repository: `https://github.com/sanskarp661-sudo/masterminds.git`. Branch: `main`. Directory: leave **empty**.
   Hostinger needs the site folder to be empty for the first deploy. Delete any default `index.php` / `default.php` there first.
3. Click **Create**, then **Deploy**.
4. Click **Auto Deployment** and copy the **Webhook URL**.
   In GitHub, open the repo **Settings → Webhooks → Add webhook**, paste it into *Payload URL*, keep "Just the push event", and click **Add webhook**.
   From now on, every push goes live within seconds.

Check: <https://masterminds.mosaicengine.in> shows the website.

## 2. Create the database tables

1. **hPanel → Databases → Management.** Use your existing database (or create one). Note its **database name**, **username** and **password**.
2. Click **Enter phpMyAdmin** for that database → **Import** tab → choose `database/schema.sql` from this repo (download it from GitHub) → **Import**.

This creates 7 tables (`results`, `testimonials`, `gallery`, `content`, `inquiries`, `admins`, `rate_limits`) and fills in the site's current content. If your database already has tables with any of these names, use a new database instead.

## 3. Create the server settings file

1. **hPanel → Files → File Manager.** Open the folder **that contains** your site's folder. If the site is in `public_html/`, that's one level up from it. If it's in `public_html/masterminds/`, that's `public_html/`.
2. Create a file named **`mm-config.php`** there and paste this, filling in your values:

   ```php
   <?php
   return [
       'db_host' => 'localhost',
       'db_name' => 'u123456789_yourdb',      // from step 2
       'db_user' => 'u123456789_youruser',    // from step 2
       'db_pass' => 'your-database-password', // from step 2
       'secret'  => 'type-any-long-random-text-here',
       'setup_code' => 'make-up-a-code',      // used once in step 4
       'notify_email' => '',                  // optional: your email, to get an alert for every enquiry
       'site_url' => 'https://masterminds.mosaicengine.in',
       'allowed_origins' => [],
   ];
   ```

This file is **not** in GitHub, so your passwords never become public and deployments never overwrite it.

Check: open <https://masterminds.mosaicengine.in/api/index.php?a=status>. You should see `{"admin":null,"needsSetup":true}`.
If it says it can't connect to the database, re-check the name, user and password.

## 4. Create your admin account

1. Open <https://masterminds.mosaicengine.in/admin.html>.
2. Enter your **setup code** (from step 3), your name, email and a password (8+ characters), then click **Create admin & sign in**.
3. That page now locks for good. You can delete the `setup_code` line from `mm-config.php`.

Add more people later under **Admins & Password** in the panel.

## 5. Turn on HTTPS

**hPanel → Security → SSL:** make sure masterminds.mosaicengine.in has an SSL certificate (Hostinger gives it free), and turn on **Force HTTPS**.

---

## Using it day to day

- **Change the site's code:** push to `main` on GitHub. It goes live automatically.
- **Change content:** use <https://masterminds.mosaicengine.in/admin.html>. Changes go live as soon as you save.
  - **Form Submissions:** search, set the status (New / Contacted / Enrolled / Closed), add notes, delete, export to CSV.
  - **Results, Reviews:** add, edit, hide or delete, with photos.
  - **Gallery:** upload several photos at once, caption, reorder, hide or delete.
  - **Website Content:** phone, email, banner, highlight numbers, About text, branches, loan points, program descriptions. Wrap words in `*stars*` for the gold italic style, e.g. `Happy *Studying*`.
  - **Admins & Password:** add or remove admins, change your password.

## If something goes wrong

| Problem | Fix |
|---|---|
| Admin page says "Can't reach the server" | Check `mm-config.php` is in the right folder (step 3) and `api/index.php?a=status` works. |
| Photo upload fails | In File Manager, set the `uploads` folder permissions to `755`. |
| No enquiry alert emails | Check spam once and mark as "not spam". Hostinger's mail can be slow to start. |
| Website shows old content after a push | Check the deployment status in **hPanel → Advanced → GIT** and clear your browser cache. |
