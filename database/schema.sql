-- =====================================================================
-- Master Minds website — MySQL / MariaDB tables (Hostinger)
-- hPanel → Databases → phpMyAdmin → choose your database → Import this file
-- (or paste it into the SQL tab and click Go). Safe to run more than once.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  name          VARCHAR(120) NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS results (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_name VARCHAR(120) NULL,
  score        VARCHAR(20)  NOT NULL,
  metric       VARCHAR(60)  NOT NULL,
  school       VARCHAR(120) NOT NULL,
  region       ENUM('India','Abroad') NOT NULL DEFAULT 'India',
  program      VARCHAR(40)  NULL,
  photo_url    VARCHAR(500) NULL,
  sort         INT NOT NULL DEFAULT 0,
  published    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  result     VARCHAR(120) NULL,
  quote      TEXT NOT NULL,
  program    VARCHAR(40)  NULL,
  photo_url  VARCHAR(500) NULL,
  sort       INT NOT NULL DEFAULT 0,
  published  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  url        VARCHAR(500) NOT NULL,
  caption    VARCHAR(120) NULL,
  sort       INT NOT NULL DEFAULT 0,
  published  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content (
  `key`      VARCHAR(60) PRIMARY KEY,
  value      LONGTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  name       VARCHAR(120) NOT NULL,
  phone      VARCHAR(20)  NOT NULL,
  email      VARCHAR(200) NULL,
  city       VARCHAR(100) NULL,
  course     VARCHAR(100) NULL,
  status     ENUM('new','contacted','enrolled','closed') NOT NULL DEFAULT 'new',
  notes      TEXT NULL,
  ip_hash    CHAR(64) NULL,
  INDEX (status), INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Used to slow down password guessing and form spam.
CREATE TABLE IF NOT EXISTS rate_limits (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket     VARCHAR(20) NOT NULL,
  ip_hash    CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (bucket, ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Starter content (only inserted when the tables are empty)
-- =====================================================================
INSERT INTO results (score, metric, school, region, program, sort)
SELECT * FROM (
  SELECT '99.98' s,'CAT Percentile' m,'IIM Ahmedabad' c,'India' r,'mba' p,1 o UNION ALL
  SELECT '99.5','CAT Percentile','IIM Bangalore','India','mba',2 UNION ALL
  SELECT '99.4','CAT Percentile','IIM Calcutta','India','mba',3 UNION ALL
  SELECT '99.0','CAT Percentile','FMS Delhi','India','mba',4 UNION ALL
  SELECT '98.5','XAT Percentile','XLRI Jamshedpur','India','mba',5 UNION ALL
  SELECT '8.5','IELTS Band','Oxford University','Abroad','ielts',6 UNION ALL
  SELECT '335','GRE Score','Harvard University','Abroad','gre',7 UNION ALL
  SELECT '1550','SAT Score','Stanford University','Abroad','sat',8 UNION ALL
  SELECT '118','TOEFL Score','MIT','Abroad','toefl',9 UNION ALL
  SELECT '85','PTE Score','University of Toronto','Abroad','pte',10
) v WHERE NOT EXISTS (SELECT 1 FROM results);

INSERT INTO testimonials (name, result, quote, program, sort)
SELECT * FROM (
  SELECT 'Aman Gupta' n,'IIM Indore Select' r,'Master Minds helped me crack IPMAT with ease. The mentors are top-notch and the study material is very comprehensive.' q,'ipm' p,1 o UNION ALL
  SELECT 'Priya Singh','AIR 45, CLAT 2024','The CLAT preparation here is unparalleled. I got into NLSIU Bangalore thanks to their constant guidance and mock tests.','law',2 UNION ALL
  SELECT 'Rohan Verma','Masters at NYU','Studying abroad seemed like a dream until I joined Master Minds. They handled everything from GRE prep to my Visa application perfectly.','gre',3 UNION ALL
  SELECT 'Sneha Reddy','SRCC, Delhi University','The personalized feedback and Doubt sessions were game-changers for my CUET preparation. Highly recommended!','cuet',4
) v WHERE NOT EXISTS (SELECT 1 FROM testimonials);

INSERT INTO gallery (url, sort)
SELECT * FROM (
  SELECT 'https://mymasterminds.in/uploads/landing/gallery_69c0f2900bebd.png' u,1 o UNION ALL
  SELECT 'https://mymasterminds.in/uploads/landing/gallery_69c0f2900c856.png',2 UNION ALL
  SELECT 'https://mymasterminds.in/uploads/landing/gallery_69c0f2900d0cb.png',3 UNION ALL
  SELECT 'https://mymasterminds.in/uploads/landing/gallery_69c0f2900d987.png',4
) v WHERE NOT EXISTS (SELECT 1 FROM gallery);

INSERT IGNORE INTO content (`key`, value) VALUES
('site', '{"phone":"8877997887","phone_fmt":"+91 8877 997 887","email":"admin@mymasterminds.com","hero_eyebrow":"MBA · Law · CUET · IPM · BBA · Study Abroad","hero_title":"Happy *Studying*","hero_sub":"Prepare for MBA, Law, CUET, IPM, BBA and more. Achieve your career goals and fulfill your dreams.","hero_image":"https://mymasterminds.in/uploads/landing/banner-girl.png","badge_score":"99.98","badge_text":"CAT percentile\\nIIM Ahmedabad","stats":[{"v":"12","l":"Exam & admission programs"},{"v":"5","l":"City branches"},{"v":"India + Abroad","l":"Coaching, admissions & visas"},{"v":"30 min","l":"Free expert consultation"}],"about_title":"Nurturing academic excellence, shaping *future-ready* individuals","about_p1":"At Master Minds, we are dedicated to nurturing academic excellence and shaping future-ready individuals. With a strong track record of guiding thousands of students toward their goals, we deliver high-quality coaching for competitive examinations, study abroad pathways, and skill development initiatives.","about_p2":"Our learner-centric approach combines experienced faculty, modern infrastructure, and personalized mentorship to ensure every student receives the guidance needed to succeed — academically and professionally.","about_image":"https://mymasterminds.in/uploads/landing/class.jpg","cta_title":"Ready to start? *Talk to our experts.*","cta_text":"Seamlessly apply to multiple colleges and unlock exclusive benefits on your application fees.","footer_text":"Seamlessly apply to multiple colleges and unlock exclusive benefits on your application fees."}'),
('branches', '[{"city":"Jamshedpur","address":"Opposite Meditek, Contractors Area, Road No - 3, Bistupur, Jamshedpur.","phones":["9204857887","7887517887"]},{"city":"Ranchi","address":"2nd Floor, H Square Mall, Opposite Westside, Lalpur, Ranchi","phones":["8877997887","8858867887"]},{"city":"Bhilai","address":"119, New Civic Center, Beside Miraj Cinemas, Sector 5, Bhilai.","phones":["7887397887","7887937887"]},{"city":"Raipur","address":"G E Road, Opp. Anupam Garden, Beside Westside, Raipur.","phones":["7887637887","7887937887"]},{"city":"Bhubaneswar","address":"2nd Floor, Plot No.11, Beside Home Town, In front of Kendriya Vidyalaya 1, Satyanagar, Bhubaneswar","phones":["7887547887"]}]'),
('loans', '["Flexible & Competitive Interest rates","Supports all Majors, Degree & Diplomas","Quick approval within 3 working days","Loan Tenure up to 15 years","Personal Advisor for you exclusively for Guidance"]'),
('program_desc', '{"mba":"CAT, XAT and SNAP prep for top B-schools","law":"CLAT and AILET prep for National Law Universities","ipm":"IPMAT prep for the IIMs’ five-year management programme","bba":"Entrance prep for leading undergraduate business schools","cuet":"Central university admissions, every domain covered","hotel-management":"NCHM JEE prep for top hospitality institutes","gmat":"Focus Edition prep for global business schools","gre":"Quant and verbal prep for master’s programmes abroad","sat":"Digital SAT prep for US undergraduate admissions","ielts":"Band-focused training across all four modules","toefl":"iBT prep for reading, listening, speaking and writing","study-abroad":"Shortlisting, applications and visa guidance"}');

-- The first admin account is created from the admin page itself (/admin.html)
-- the first time you open it. No SQL needed.
