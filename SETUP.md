# Admin panel setup (about 10 minutes, free)

The admin panel lives at **`/admin.html`** on your site, for example
`https://sanskarp661-sudo.github.io/masterminds/admin.html`.

It lets you manage:

- **Form Submissions** – every enquiry from the “Book Your Free Counseling Session” form. You can search them, mark each one New / Contacted / Enrolled / Closed, add private notes, delete them and export to CSV (opens in Excel).
- **Results** – the scores in “Our Success Stories”, with optional student photo, India/Abroad tab and program.
- **Reviews** – student testimonials, with optional photo.
- **Gallery** – upload, caption, reorder, hide or delete photos.
- **Website Content** – phone, email, home banner text and image, highlight numbers, About text and image, call-to-action, footer text, branches, education-loan points and program card descriptions.

Anything can be hidden from the website without deleting it, and changes show on the website straight away.

The data is stored in **Supabase**, a free hosted database. You need to do the steps below once.

---

## 1. Create a Supabase project

1. Go to <https://supabase.com>, sign up and click **New project**.
2. Pick any name (e.g. `masterminds`), set a database password (save it somewhere safe) and choose the region **Mumbai (ap-south-1)**.
3. Wait a minute or two for it to finish setting up.

## 2. Create the database tables

1. In your project, open **SQL Editor → New query**.
2. Open [`supabase/schema.sql`](supabase/schema.sql) from this repository, copy **all** of it, paste it in and click **Run**.
   You should see “Success. No rows returned”. This creates the tables, the security rules, the photo storage and fills in the current website content.

## 3. Create your admin login

1. Go to **Authentication → Users → Add user → Create new user**.
2. Enter the email and password you want to log in with, tick **Auto Confirm User**, and click **Create user**.
3. Back in **SQL Editor**, run this line (with your email):

   ```sql
   insert into public.admins (user_id) select id from auth.users where email = 'you@example.com';
   ```

   Repeat steps 1–3 for each extra person who should have access.
4. Recommended: **Authentication → Sign In / Providers → Email**: turn off **Allow new users to sign up**, so nobody else can create accounts.

## 4. Connect the website

1. In Supabase open **Project Settings → API** (or **Data API**).
2. Copy the **Project URL** and the **anon public** key.
3. In this repository edit [`config.js`](config.js) and paste them in:

   ```js
   window.MM_CONFIG = {
     supabaseUrl: 'https://abcdefgh.supabase.co',
     supabaseAnonKey: 'eyJhbGciOi...'
   };
   ```

4. Commit the change. GitHub Pages redeploys automatically.

The anon key is designed to be public. The security rules in `schema.sql` mean visitors can only **read** published content and **send** enquiries. Only logged-in admins can see enquiries or change anything.

> **Never** paste the `service_role` key into `config.js`. That key bypasses all security rules.

## 5. Log in

Open `/admin.html`, sign in with the email and password from step 3, and you’re in.

---

## Good to know

- **Formatting:** in headings, wrap words in `*stars*` to show them in the gold italic style, e.g. `Happy *Studying*`.
- **Photos:** JPG, PNG or WebP, up to 5 MB each. Smaller, compressed photos make the website load faster.
- **If Supabase is unreachable,** the website keeps showing the last content it loaded. If `config.js` is empty, it shows its built-in content.
- **Free plan limits:** Supabase’s free plan includes 500 MB of database and 1 GB of file storage, which is plenty for this site. Free projects are **paused after 7 days with no activity**. Normal website traffic counts as activity, but if the site gets no visits for a week, log in to supabase.com and click **Restore**.
- **Email alerts for new enquiries:** not included yet. This can be added with a Supabase Database Webhook and an email service.
