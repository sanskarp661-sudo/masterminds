-- =====================================================================
-- Master Minds website — database setup for Supabase
-- Paste this whole file into Supabase → SQL Editor → New query → Run.
-- It is safe to run more than once.
-- =====================================================================

create extension if not exists pgcrypto;

-- ---------- Admins ----------
create table if not exists public.admins (
  user_id    uuid primary key references auth.users(id) on delete cascade,
  created_at timestamptz not null default now()
);
alter table public.admins enable row level security;  -- no policies: not readable through the API

create or replace function public.is_admin()
returns boolean language sql stable security definer set search_path = public as $$
  select exists (select 1 from public.admins where user_id = auth.uid());
$$;
grant execute on function public.is_admin() to anon, authenticated;

-- ---------- Results (success stories) ----------
create table if not exists public.results (
  id           uuid primary key default gen_random_uuid(),
  student_name text,
  score        text not null,
  metric       text not null,
  school       text not null,
  region       text not null default 'India' check (region in ('India','Abroad')),
  program      text,
  photo_url    text,
  sort         int  not null default 0,
  published    boolean not null default true,
  created_at   timestamptz not null default now()
);

-- ---------- Reviews (testimonials) ----------
create table if not exists public.testimonials (
  id         uuid primary key default gen_random_uuid(),
  name       text not null,
  result     text,
  quote      text not null,
  program    text,
  photo_url  text,
  sort       int  not null default 0,
  published  boolean not null default true,
  created_at timestamptz not null default now()
);

-- ---------- Gallery ----------
create table if not exists public.gallery (
  id         uuid primary key default gen_random_uuid(),
  url        text not null,
  caption    text,
  sort       int  not null default 0,
  published  boolean not null default true,
  created_at timestamptz not null default now()
);

-- ---------- Website content (key → JSON) ----------
create table if not exists public.content (
  key        text primary key,
  value      jsonb not null,
  updated_at timestamptz not null default now()
);

-- ---------- Form submissions ----------
create table if not exists public.inquiries (
  id         uuid primary key default gen_random_uuid(),
  created_at timestamptz not null default now(),
  name       text not null check (char_length(name)  between 1 and 120),
  phone      text not null check (char_length(phone) between 6 and 20),
  email      text check (char_length(email)  <= 200),
  city       text check (char_length(city)   <= 100),
  course     text check (char_length(course) <= 100),
  status     text not null default 'new' check (status in ('new','contacted','enrolled','closed')),
  notes      text
);

-- ---------- Row level security ----------
alter table public.results      enable row level security;
alter table public.testimonials enable row level security;
alter table public.gallery      enable row level security;
alter table public.content      enable row level security;
alter table public.inquiries    enable row level security;

do $$
declare t text;
begin
  foreach t in array array['results','testimonials','gallery'] loop
    execute format('drop policy if exists "public read" on public.%I', t);
    execute format('create policy "public read" on public.%I for select using (published or public.is_admin())', t);
    execute format('drop policy if exists "admin write" on public.%I', t);
    execute format('create policy "admin write" on public.%I for all using (public.is_admin()) with check (public.is_admin())', t);
  end loop;
end $$;

drop policy if exists "public read" on public.content;
create policy "public read" on public.content for select using (true);
drop policy if exists "admin write" on public.content;
create policy "admin write" on public.content for all using (public.is_admin()) with check (public.is_admin());

-- Anyone can submit the enquiry form; only admins can read / change / delete submissions.
drop policy if exists "anyone can submit" on public.inquiries;
create policy "anyone can submit" on public.inquiries for insert to anon, authenticated
  with check (status = 'new' and notes is null);
drop policy if exists "admin manage" on public.inquiries;
create policy "admin manage" on public.inquiries for all using (public.is_admin()) with check (public.is_admin());

-- ---------- Image storage ----------
insert into storage.buckets (id, name, public) values ('media', 'media', true)
on conflict (id) do nothing;

drop policy if exists "admin upload media" on storage.objects;
create policy "admin upload media" on storage.objects for insert to authenticated
  with check (bucket_id = 'media' and public.is_admin());
drop policy if exists "admin update media" on storage.objects;
create policy "admin update media" on storage.objects for update to authenticated
  using (bucket_id = 'media' and public.is_admin());
drop policy if exists "admin delete media" on storage.objects;
create policy "admin delete media" on storage.objects for delete to authenticated
  using (bucket_id = 'media' and public.is_admin());

-- =====================================================================
-- Starter content (only inserted when the tables are empty)
-- =====================================================================
insert into public.results (score, metric, school, region, program, sort)
select * from (values
  ('99.98','CAT Percentile','IIM Ahmedabad','India','mba',1),
  ('99.5','CAT Percentile','IIM Bangalore','India','mba',2),
  ('99.4','CAT Percentile','IIM Calcutta','India','mba',3),
  ('99.0','CAT Percentile','FMS Delhi','India','mba',4),
  ('98.5','XAT Percentile','XLRI Jamshedpur','India','mba',5),
  ('8.5','IELTS Band','Oxford University','Abroad','ielts',6),
  ('335','GRE Score','Harvard University','Abroad','gre',7),
  ('1550','SAT Score','Stanford University','Abroad','sat',8),
  ('118','TOEFL Score','MIT','Abroad','toefl',9),
  ('85','PTE Score','University of Toronto','Abroad','pte',10)
) v(score, metric, school, region, program, sort)
where not exists (select 1 from public.results);

insert into public.testimonials (name, result, quote, program, sort)
select * from (values
  ('Aman Gupta','IIM Indore Select','Master Minds helped me crack IPMAT with ease. The mentors are top-notch and the study material is very comprehensive.','ipm',1),
  ('Priya Singh','AIR 45, CLAT 2024','The CLAT preparation here is unparalleled. I got into NLSIU Bangalore thanks to their constant guidance and mock tests.','law',2),
  ('Rohan Verma','Masters at NYU','Studying abroad seemed like a dream until I joined Master Minds. They handled everything from GRE prep to my Visa application perfectly.','gre',3),
  ('Sneha Reddy','SRCC, Delhi University','The personalized feedback and Doubt sessions were game-changers for my CUET preparation. Highly recommended!','cuet',4)
) v(name, result, quote, program, sort)
where not exists (select 1 from public.testimonials);

insert into public.gallery (url, sort)
select * from (values
  ('https://mymasterminds.in/uploads/landing/gallery_69c0f2900bebd.png',1),
  ('https://mymasterminds.in/uploads/landing/gallery_69c0f2900c856.png',2),
  ('https://mymasterminds.in/uploads/landing/gallery_69c0f2900d0cb.png',3),
  ('https://mymasterminds.in/uploads/landing/gallery_69c0f2900d987.png',4)
) v(url, sort)
where not exists (select 1 from public.gallery);

insert into public.content (key, value) values
('site', '{
  "phone":"8877997887","phone_fmt":"+91 8877 997 887","email":"admin@mymasterminds.com",
  "hero_eyebrow":"MBA · Law · CUET · IPM · BBA · Study Abroad",
  "hero_title":"Happy *Studying*",
  "hero_sub":"Prepare for MBA, Law, CUET, IPM, BBA and more. Achieve your career goals and fulfill your dreams.",
  "hero_image":"https://mymasterminds.in/uploads/landing/banner-girl.png",
  "badge_score":"99.98","badge_text":"CAT percentile\nIIM Ahmedabad",
  "stats":[{"v":"12","l":"Exam & admission programs"},{"v":"5","l":"City branches"},{"v":"India + Abroad","l":"Coaching, admissions & visas"},{"v":"30 min","l":"Free expert consultation"}],
  "about_title":"Nurturing academic excellence, shaping *future-ready* individuals",
  "about_p1":"At Master Minds, we are dedicated to nurturing academic excellence and shaping future-ready individuals. With a strong track record of guiding thousands of students toward their goals, we deliver high-quality coaching for competitive examinations, study abroad pathways, and skill development initiatives.",
  "about_p2":"Our learner-centric approach combines experienced faculty, modern infrastructure, and personalized mentorship to ensure every student receives the guidance needed to succeed — academically and professionally.",
  "about_image":"https://mymasterminds.in/uploads/landing/class.jpg",
  "cta_title":"Ready to start? *Talk to our experts.*",
  "cta_text":"Seamlessly apply to multiple colleges and unlock exclusive benefits on your application fees.",
  "footer_text":"Seamlessly apply to multiple colleges and unlock exclusive benefits on your application fees."
}'::jsonb),
('branches', '[
  {"city":"Jamshedpur","address":"Opposite Meditek, Contractors Area, Road No - 3, Bistupur, Jamshedpur.","phones":["9204857887","7887517887"]},
  {"city":"Ranchi","address":"2nd Floor, H Square Mall, Opposite Westside, Lalpur, Ranchi","phones":["8877997887","8858867887"]},
  {"city":"Bhilai","address":"119, New Civic Center, Beside Miraj Cinemas, Sector 5, Bhilai.","phones":["7887397887","7887937887"]},
  {"city":"Raipur","address":"G E Road, Opp. Anupam Garden, Beside Westside, Raipur.","phones":["7887637887","7887937887"]},
  {"city":"Bhubaneswar","address":"2nd Floor, Plot No.11, Beside Home Town, In front of Kendriya Vidyalaya 1, Satyanagar, Bhubaneswar","phones":["7887547887"]}
]'::jsonb),
('loans', '["Flexible & Competitive Interest rates","Supports all Majors, Degree & Diplomas","Quick approval within 3 working days","Loan Tenure up to 15 years","Personal Advisor for you exclusively for Guidance"]'::jsonb),
('program_desc', '{
  "mba":"CAT, XAT and SNAP prep for top B-schools","law":"CLAT and AILET prep for National Law Universities",
  "ipm":"IPMAT prep for the IIMs’ five-year management programme","bba":"Entrance prep for leading undergraduate business schools",
  "cuet":"Central university admissions, every domain covered","hotel-management":"NCHM JEE prep for top hospitality institutes",
  "gmat":"Focus Edition prep for global business schools","gre":"Quant and verbal prep for master’s programmes abroad",
  "sat":"Digital SAT prep for US undergraduate admissions","ielts":"Band-focused training across all four modules",
  "toefl":"iBT prep for reading, listening, speaking and writing","study-abroad":"Shortlisting, applications and visa guidance"
}'::jsonb)
on conflict (key) do nothing;

-- =====================================================================
-- LAST STEP — make yourself an admin.
-- 1. Supabase → Authentication → Users → Add user (email + password, tick "Auto confirm").
-- 2. Replace the email below with that user's email and run just this line:
--
--   insert into public.admins (user_id) select id from auth.users where email = 'you@example.com';
-- =====================================================================
