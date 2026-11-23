-- =====================================================
-- Vaccination Management System - Database (Day 1)
-- phpMyAdmin > Import tab se ye poori file import karo
-- =====================================================

CREATE DATABASE IF NOT EXISTS vaccination_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vaccination_db;

-- Purani tables hata do (dobara import karna asaan ho)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications, contact_messages, hospital_suggestions, past_vaccinations, vaccine_stock, bookings, vaccines, children, hospitals, users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1) USERS: teeno roles ka login yahan hota hai
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,          -- hashed password
  phone VARCHAR(20),
  role ENUM('admin','parent','hospital') NOT NULL,
  status ENUM('active','pending','disabled') NOT NULL DEFAULT 'active',  -- hospital naya ho to pending, admin manzoor kare to active
  failed_logins INT NOT NULL DEFAULT 0,                                  -- galat password ki ginti (brute-force se bachao)
  locked_until DATETIME NULL,                                            -- is waqt tak login band
  city VARCHAR(80) NULL,                                                 -- parent ka shehar ("Near you" hospitals ke liye)
  address VARCHAR(255) NULL,                                             -- parent ka pata (optional)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2) HOSPITALS: hospital ki extra details
CREATE TABLE hospitals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,                         -- hospital ka login account
  hospital_name VARCHAR(150) NOT NULL,
  address VARCHAR(255) NOT NULL,
  location VARCHAR(100) NOT NULL,           -- city / area
  phone VARCHAR(20),
  license_no VARCHAR(60) NULL,                      -- hospital ka license / registration number (admin verify karta hai)
  contact_email VARCHAR(120) NULL,                  -- invitation bhejne ke liye (admin bharta hai)
  latitude DECIMAL(10,7) NULL,                      -- "Hospitals near me" ke liye
  longitude DECIMAL(10,7) NULL,
  invite_token VARCHAR(64) NULL,                    -- invitation link ka khufiya code (ek hi baar chalta hai)
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_invite (invite_token)
) ENGINE=InnoDB;

-- 3) CHILDREN: parent ke bachon ki info
CREATE TABLE children (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  dob DATE NOT NULL,
  gender ENUM('male','female') NOT NULL,
  blood_group VARCHAR(5),
  FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4) VACCINES: vaccine ki list
--    recommended_age_days: bache ki umar (din mein) jab ye lagni chahiye
--    (baad mein "auto-suggest" feature isi se chalega)
CREATE TABLE vaccines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vaccine_name VARCHAR(120) NOT NULL,
  description VARCHAR(255),
  recommended_age VARCHAR(50),              -- dikhane ke liye e.g. "6 weeks"
  recommended_age_days INT NOT NULL DEFAULT 0,
  availability ENUM('available','unavailable') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB;

-- 5) BOOKINGS: sab se important table
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  child_id INT NOT NULL,
  parent_id INT NOT NULL,
  hospital_id INT NOT NULL,
  vaccine_id INT NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  vaccination_status ENUM('pending','vaccinated','not_vaccinated') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (child_id)    REFERENCES children(id)  ON DELETE CASCADE,
  FOREIGN KEY (parent_id)   REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
  FOREIGN KEY (vaccine_id)  REFERENCES vaccines(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6) PAST_VACCINATIONS: purani (pehle se lag chuki) vaccines jo parent khud darj kare, admin verify kare
CREATE TABLE past_vaccinations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  child_id INT NOT NULL,
  vaccine_id INT NOT NULL,
  given_date DATE NOT NULL,
  place VARCHAR(150) NOT NULL,                       -- kahan lagi (hospital/clinic ka naam)
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (child_id)   REFERENCES children(id) ON DELETE CASCADE,
  FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_child_vaccine (child_id, vaccine_id)   -- ek bache ki ek vaccine ek hi baar
) ENGINE=InnoDB;

-- 7) HOSPITAL_SUGGESTIONS: parent ke batae hue naye hospitals; admin verify karke list mein jorta hai
CREATE TABLE hospital_suggestions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  hospital_name VARCHAR(150) NOT NULL,
  city VARCHAR(80) NOT NULL,
  address VARCHAR(255) NULL,
  phone VARCHAR(20) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  decided_at DATETIME NULL,                          -- admin ne kab faisla kiya
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id)   REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8) CONTACT_MESSAGES: landing page ke contact form se aaye hue paigham
CREATE TABLE contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL,
  subject VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','read') NOT NULL DEFAULT 'new',
  ip VARCHAR(45) NULL,                               -- spam rokne ke liye (ek ghante mein ziyada paigham nahi)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 9) NOTIFICATIONS: ghanti (bell) ke paigham, har user ke apne
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message VARCHAR(255) NOT NULL,
  link VARCHAR(255) NULL,                            -- click karne par kahan jana hai (project ke andar ka raasta)
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10) VACCINE_STOCK: har hospital ke paas har vaccine ki kitni doses baqi hain
--     quantity 10 se kam ho to "low stock" samjha jata hai.
--     booking_paused = 1 ho to parent us hospital mein us vaccine ki booking nahi kar sakta.
CREATE TABLE vaccine_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT NOT NULL,
  vaccine_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 0,                    -- baqi doses ki ginti
  booking_paused TINYINT(1) NOT NULL DEFAULT 0,       -- admin ne booking band ki hui hai?
  FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
  FOREIGN KEY (vaccine_id)  REFERENCES vaccines(id)  ON DELETE CASCADE,
  UNIQUE KEY uniq_hospital_vaccine (hospital_id, vaccine_id)   -- ek hospital + ek vaccine ki ek hi row
) ENGINE=InnoDB;

-- =====================================================
-- DEMO DATA (taake charts aur reports khali na dikhein)
-- Admin   : admin@vaccine.com    / admin123
-- Parents : ali@example.com, sara@example.com   / 123456
-- Hospital: city@hospital.com, care@hospital.com / 123456
-- =====================================================

INSERT INTO users (id, name, email, password, phone, role) VALUES
(1, 'Admin',               'admin@vaccine.com',  '$2y$12$8oy9SbOdpuJIa4vcOH3OiOQfwxha78CXziCYvUh7odBQD1WMM72KK', '03000000000', 'admin'),
(2, 'Ali Khan',            'ali@example.com',    '$2y$12$9dwEw9WRwgH4f7dCSUL4dOvRYRWN7.nS4fN7SktujTPQhZhwRpMGm', '03011111111', 'parent'),
(3, 'Sara Ahmed',          'sara@example.com',   '$2y$12$9dwEw9WRwgH4f7dCSUL4dOvRYRWN7.nS4fN7SktujTPQhZhwRpMGm', '03022222222', 'parent'),
(4, 'City Hospital',       'city@hospital.com',  '$2y$12$9dwEw9WRwgH4f7dCSUL4dOvRYRWN7.nS4fN7SktujTPQhZhwRpMGm', '02111111111', 'hospital'),
(5, 'Care Children Clinic','care@hospital.com',  '$2y$12$9dwEw9WRwgH4f7dCSUL4dOvRYRWN7.nS4fN7SktujTPQhZhwRpMGm', '02122222222', 'hospital');

INSERT INTO hospitals (id, user_id, hospital_name, address, location, phone) VALUES
(1, 4, 'City Hospital',        'Main Shahrah-e-Faisal',  'Karachi', '02111111111'),
(2, 5, 'Care Children Clinic', 'Block 5, Clifton',       'Karachi', '02122222222'),
(3, NULL, 'Green Crescent Health Center', 'Gulshan-e-Iqbal', 'Karachi', '02133333333');

INSERT INTO children (id, parent_id, name, dob, gender, blood_group) VALUES
(1, 2, 'Hamza Ali',   DATE_SUB(CURDATE(), INTERVAL 3 MONTH),  'male',   'B+'),
(2, 2, 'Ayesha Ali',  DATE_SUB(CURDATE(), INTERVAL 14 MONTH), 'female', 'O+'),
(3, 3, 'Zain Sara',   DATE_SUB(CURDATE(), INTERVAL 1 MONTH),  'male',   'A+');

INSERT INTO vaccines (vaccine_name, description, recommended_age, recommended_age_days, availability) VALUES
('BCG',               'Tuberculosis se bachao',            'Birth',     0,   'available'),
('OPV 0 (Polio)',     'Polio ki pehli khuraak',            'Birth',     0,   'available'),
('Pentavalent 1',     'DPT, Hepatitis B, Hib',             '6 weeks',   42,  'available'),
('Pneumococcal 1',    'Pneumonia se bachao',               '6 weeks',   42,  'available'),
('Pentavalent 2',     'DPT, Hepatitis B, Hib (doosri)',    '10 weeks',  70,  'available'),
('Pentavalent 3',     'DPT, Hepatitis B, Hib (teesri)',    '14 weeks',  98,  'available'),
('IPV (Injectable Polio)', 'Polio injection',              '14 weeks',  98,  'unavailable'),
('Measles 1',         'Khasra se bachao',                  '9 months',  270, 'available'),
('Measles 2',         'Khasra (doosri khuraak)',           '15 months', 450, 'available'),
('Typhoid',           'Typhoid se bachao',                 '9 months',  270, 'available');

INSERT INTO bookings (child_id, parent_id, hospital_id, vaccine_id, appointment_date, appointment_time, approval_status, vaccination_status) VALUES
(1, 2, 1, 3, DATE_ADD(CURDATE(), INTERVAL 3 DAY),  '10:00:00', 'approved', 'pending'),
(2, 2, 2, 9, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '11:30:00', 'approved', 'vaccinated'),
(3, 3, 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  '09:00:00', 'approved', 'vaccinated'),
(3, 3, 2, 3, DATE_ADD(CURDATE(), INTERVAL 20 DAY), '12:00:00', 'pending',  'pending'),
(1, 2, 1, 1, DATE_SUB(CURDATE(), INTERVAL 60 DAY), '10:30:00', 'approved', 'not_vaccinated'),
(2, 2, 1, 10, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:00:00', 'rejected', 'pending');

-- =====================================================
-- EXTRA DEMO DATA (Day 14): charts aur reports bharpoor dikhane ke liye
-- Naya parent: fatima@example.com / 123456
-- =====================================================

INSERT INTO users (id, name, email, password, phone, role) VALUES
(6, 'Fatima Noor', 'fatima@example.com', '$2y$12$9dwEw9WRwgH4f7dCSUL4dOvRYRWN7.nS4fN7SktujTPQhZhwRpMGm', '03033333333', 'parent');

INSERT INTO children (id, parent_id, name, dob, gender, blood_group) VALUES
(4, 6, 'Inaya Noor',   DATE_SUB(CURDATE(), INTERVAL 240 DAY), 'female', 'A-'),
(5, 6, 'Rayyan Noor',  DATE_SUB(CURDATE(), INTERVAL 730 DAY), 'male',   'B+'),
(6, 3, 'Maryam Sara',  DATE_SUB(CURDATE(), INTERVAL 180 DAY), 'female', 'O-');

INSERT INTO bookings (child_id, parent_id, hospital_id, vaccine_id, appointment_date, appointment_time, approval_status, vaccination_status) VALUES
-- Inaya (8 mahine)
(4, 6, 1, 1,  DATE_SUB(CURDATE(), INTERVAL 230 DAY), '09:00:00', 'approved', 'vaccinated'),
(4, 6, 1, 2,  DATE_SUB(CURDATE(), INTERVAL 230 DAY), '09:30:00', 'approved', 'vaccinated'),
(4, 6, 2, 3,  DATE_SUB(CURDATE(), INTERVAL 190 DAY), '10:00:00', 'approved', 'vaccinated'),
(4, 6, 2, 4,  DATE_SUB(CURDATE(), INTERVAL 190 DAY), '10:30:00', 'approved', 'vaccinated'),
(4, 6, 2, 5,  DATE_SUB(CURDATE(), INTERVAL 160 DAY), '11:00:00', 'approved', 'vaccinated'),
(4, 6, 1, 6,  DATE_SUB(CURDATE(), INTERVAL 100 DAY), '09:30:00', 'approved', 'vaccinated'),
(4, 6, 1, 8,  DATE_ADD(CURDATE(), INTERVAL 30 DAY),  '10:00:00', 'pending',  'pending'),
-- Rayyan (2 saal)
(5, 6, 1, 1,  DATE_SUB(CURDATE(), INTERVAL 700 DAY), '10:00:00', 'approved', 'vaccinated'),
(5, 6, 2, 8,  DATE_SUB(CURDATE(), INTERVAL 440 DAY), '11:30:00', 'approved', 'vaccinated'),
(5, 6, 2, 9,  DATE_SUB(CURDATE(), INTERVAL 75 DAY),  '12:00:00', 'approved', 'vaccinated'),
(5, 6, 1, 10, DATE_SUB(CURDATE(), INTERVAL 45 DAY),  '09:00:00', 'approved', 'not_vaccinated'),
-- Maryam (6 mahine)
(6, 3, 2, 1,  DATE_SUB(CURDATE(), INTERVAL 170 DAY), '09:00:00', 'approved', 'vaccinated'),
(6, 3, 1, 3,  DATE_SUB(CURDATE(), INTERVAL 110 DAY), '10:00:00', 'approved', 'vaccinated'),
(6, 3, 1, 5,  DATE_SUB(CURDATE(), INTERVAL 20 DAY),  '10:30:00', 'rejected', 'pending');

-- =====================================================
-- DEMO DATA: City Hospital ki aaj ki 2 approved appointments
-- Hospital demo (city@hospital.com) mein "Vaccinated" mark karne ke liye
-- =====================================================
INSERT INTO bookings (child_id, parent_id, hospital_id, vaccine_id, appointment_date, appointment_time, approval_status, vaccination_status) VALUES
(1, 2, 1, 5, CURDATE(), '10:00:00', 'approved', 'pending'),   -- Hamza, Pentavalent 2
(3, 3, 1, 4, CURDATE(), '11:30:00', 'approved', 'pending');   -- Zain, Pneumococcal 1

-- =====================================================
-- REALISTIC HISTORY (final): purane bachon ki zyadatar vaccines lag chuki hon,
-- taake dashboard par "overdue" ki ginti sirf chand (asli) vaccines ki ho.
-- Hamza ko jaan boojh kar "attention wala" bacha rakha hai (BCG miss, OPV 0 aur Pneumococcal 1 baqi).
-- =====================================================
INSERT INTO bookings (child_id, parent_id, hospital_id, vaccine_id, appointment_date, appointment_time, approval_status, vaccination_status) VALUES
-- Ayesha (14 mahine): zyadatar mukammal (IPV unavailable aur Typhoid baqi)
(2, 2, 2, 1, DATE_SUB(CURDATE(), INTERVAL 415 DAY), '09:00:00', 'approved', 'vaccinated'),
(2, 2, 2, 2, DATE_SUB(CURDATE(), INTERVAL 415 DAY), '09:30:00', 'approved', 'vaccinated'),
(2, 2, 2, 3, DATE_SUB(CURDATE(), INTERVAL 375 DAY), '10:00:00', 'approved', 'vaccinated'),
(2, 2, 2, 4, DATE_SUB(CURDATE(), INTERVAL 375 DAY), '10:30:00', 'approved', 'vaccinated'),
(2, 2, 1, 5, DATE_SUB(CURDATE(), INTERVAL 345 DAY), '11:00:00', 'approved', 'vaccinated'),
(2, 2, 1, 6, DATE_SUB(CURDATE(), INTERVAL 315 DAY), '11:30:00', 'approved', 'vaccinated'),
(2, 2, 1, 8, DATE_SUB(CURDATE(), INTERVAL 150 DAY), '12:00:00', 'approved', 'vaccinated'),
-- Rayyan (2 saal): mukammal (sirf IPV unavailable aur Typhoid miss)
(5, 6, 1, 2, DATE_SUB(CURDATE(), INTERVAL 700 DAY), '10:30:00', 'approved', 'vaccinated'),
(5, 6, 1, 3, DATE_SUB(CURDATE(), INTERVAL 660 DAY), '09:00:00', 'approved', 'vaccinated'),
(5, 6, 1, 4, DATE_SUB(CURDATE(), INTERVAL 660 DAY), '09:30:00', 'approved', 'vaccinated'),
(5, 6, 2, 5, DATE_SUB(CURDATE(), INTERVAL 630 DAY), '10:00:00', 'approved', 'vaccinated'),
(5, 6, 2, 6, DATE_SUB(CURDATE(), INTERVAL 600 DAY), '10:30:00', 'approved', 'vaccinated'),
-- Maryam (6 mahine)
(6, 3, 2, 2, DATE_SUB(CURDATE(), INTERVAL 170 DAY), '09:30:00', 'approved', 'vaccinated'),
(6, 3, 1, 4, DATE_SUB(CURDATE(), INTERVAL 110 DAY), '10:30:00', 'approved', 'vaccinated');

-- =====================================================
-- DEMO DATA
-- Sunrise Clinic: naya hospital jo admin ki manzoori ka intezar kar raha hai
--   (login: sunrise@hospital.com / 123456 -> "waiting for approval" ka message)
-- Purani vaccinations: ek pending (admin verify karega), ek verified
-- =====================================================
INSERT INTO users (id, name, email, password, phone, role, status) VALUES
(7, 'Sunrise Clinic', 'sunrise@hospital.com', '$2y$12$9dwEw9WRwgH4f7dCSUL4dOvRYRWN7.nS4fN7SktujTPQhZhwRpMGm', '02144444444', 'hospital', 'pending');

INSERT INTO hospitals (id, user_id, hospital_name, address, location, phone) VALUES
(4, 7, 'Sunrise Clinic', 'Block 2, North Nazimabad', 'Karachi', '02144444444');

INSERT INTO past_vaccinations (child_id, vaccine_id, given_date, place, status) VALUES
(1, 2, DATE_SUB(CURDATE(), INTERVAL 85 DAY),  'Local maternity clinic',   'pending'),
(6, 5, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 'Care Children Clinic',     'verified');

-- =====================================================
-- DEMO DATA: shehar, pata aur license
-- Fatima Lahore mein hai: booking page par Lahore ka Green Crescent "Near you" (sab se upar) aayega
-- =====================================================
UPDATE users SET city = 'Karachi', address = 'Gulshan-e-Iqbal, Block 13' WHERE id = 2;
UPDATE users SET city = 'Karachi', address = 'North Nazimabad, Block H'  WHERE id = 3;
UPDATE users SET city = 'Lahore',  address = 'Model Town, Block C'       WHERE id = 6;

UPDATE hospitals SET license_no = 'KHI-H-0001' WHERE id = 1;
UPDATE hospitals SET license_no = 'KHI-H-0002' WHERE id = 2;
UPDATE hospitals SET license_no = 'LHR-H-0003', location = 'Lahore', address = 'Gulberg III, Main Boulevard' WHERE id = 3;
UPDATE hospitals SET license_no = 'KHI-H-0004' WHERE id = 4;

-- =====================================================
-- DEMO DATA: hospital suggestions
-- Sara ki ek request pending hai (admin ke "Hospital Requests" mein nazar aayegi);
-- Fatima ki request kal approve hui (uske dashboard par "good news" ka banner aayega)
-- =====================================================
INSERT INTO hospital_suggestions (parent_id, hospital_name, city, address, phone, status, decided_at) VALUES
(3, 'Indus Kids Clinic',            'Karachi', 'Clifton, Block 4',   '02155555555', 'pending',  NULL),
(6, 'Green Crescent Health Center', 'Lahore',  'Gulberg III',        NULL,          'approved', DATE_SUB(CURDATE(), INTERVAL 1 DAY));

-- =====================================================
-- DEMO DATA: contact messages (admin ke "Messages" mein nazar aayenge)
-- =====================================================
INSERT INTO contact_messages (name, email, subject, message, status, ip) VALUES
('Bilal Ahmed',  'bilal@example.com',  'Hospital registration', 'We run a small children clinic in Rawalpindi and would like to join VaxCare. What documents do you need?', 'new',  '127.0.0.1'),
('Ayesha Malik', 'ayesha@example.com', 'General question',      'Can I add more than one child to my account? My twins are 4 months old.',                                   'read', '127.0.0.1');

-- =====================================================
-- DEMO DATA: hospital invitation
-- Green Crescent (admin ne jora, abhi login nahi) ke liye ek invitation link bana hua hai aur uski ek booking intezar mein hai.
-- Demo link: register.php?role=hospital&claim=1b20bef250dcf56702311e704998cb02
-- =====================================================
UPDATE hospitals SET contact_email = 'info@greencrescent.example', invite_token = '1b20bef250dcf56702311e704998cb02' WHERE id = 3;

INSERT INTO bookings (child_id, parent_id, hospital_id, vaccine_id, appointment_date, appointment_time, approval_status, vaccination_status) VALUES
(4, 6, 3, 10, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '11:00:00', 'approved', 'pending');

-- =====================================================
-- DEMO DATA: notifications (bell mein nazar aayengi)
-- =====================================================
INSERT INTO notifications (user_id, message, link, is_read) VALUES
(2, 'Your appointment for Hamza (Pentavalent 1) is confirmed.',                          'parent/my_bookings.php?status=approved', 0),
(2, 'Welcome to VaxCare. Check your child schedule on the dashboard.',                   'parent/dashboard.php',                   1),
(6, 'Your suggested hospital "Green Crescent Health Center" has been added. You can now book there.', 'parent/book_hospital.php',   0),
(4, 'New appointment: Zain Sara, Pneumococcal 1 today at 11:30 AM.',                     'hospital/appointments.php?view=today',   0);

-- Demo hospitals ke coordinates ("Hospitals near me" ke liye)
UPDATE hospitals SET latitude = 24.8732, longitude = 67.0807 WHERE id = 1;
UPDATE hospitals SET latitude = 24.8138, longitude = 67.0300 WHERE id = 2;
UPDATE hospitals SET latitude = 31.5161, longitude = 74.3420 WHERE id = 3;
UPDATE hospitals SET latitude = 24.9420, longitude = 67.0390 WHERE id = 4;

-- 18 Karachi hospitals with coordinates (for "Hospitals near me"). Safe to run more than once.
-- Phone, license and contact email: admin panel se bharein. Coordinates approximate hain.

INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Aga Khan University Hospital', 'Stadium Road, Gulshan-e-Iqbal', 'Karachi', 24.892, 67.0745 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Aga Khan University Hospital');
UPDATE hospitals SET latitude = 24.892, longitude = 67.0745 WHERE hospital_name = 'Aga Khan University Hospital' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Jinnah Postgraduate Medical Centre (JPMC)', 'Rafiqui Shaheed Road, Saddar', 'Karachi', 24.8513, 67.0444 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Jinnah Postgraduate Medical Centre (JPMC)');
UPDATE hospitals SET latitude = 24.8513, longitude = 67.0444 WHERE hospital_name = 'Jinnah Postgraduate Medical Centre (JPMC)' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'National Institute of Child Health (NICH)', 'Rafiqui Shaheed Road, near JPMC', 'Karachi', 24.853, 67.042 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'National Institute of Child Health (NICH)');
UPDATE hospitals SET latitude = 24.853, longitude = 67.042 WHERE hospital_name = 'National Institute of Child Health (NICH)' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Civil Hospital Karachi', 'Baba-e-Urdu Road, Saddar', 'Karachi', 24.859, 67.0105 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Civil Hospital Karachi');
UPDATE hospitals SET latitude = 24.859, longitude = 67.0105 WHERE hospital_name = 'Civil Hospital Karachi' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Liaquat National Hospital', 'Stadium Road, Gulshan-e-Iqbal', 'Karachi', 24.8935, 67.07 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Liaquat National Hospital');
UPDATE hospitals SET latitude = 24.8935, longitude = 67.07 WHERE hospital_name = 'Liaquat National Hospital' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Abbasi Shaheed Hospital', 'Paposh Nagar, Nazimabad', 'Karachi', 24.923, 67.033 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Abbasi Shaheed Hospital');
UPDATE hospitals SET latitude = 24.923, longitude = 67.033 WHERE hospital_name = 'Abbasi Shaheed Hospital' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Indus Hospital Korangi', 'Korangi Crossing, Korangi', 'Karachi', 24.846, 67.141 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Indus Hospital Korangi');
UPDATE hospitals SET latitude = 24.846, longitude = 67.141 WHERE hospital_name = 'Indus Hospital Korangi' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Dow University Hospital (Ojha Campus)', 'Suparco Road, Gulzar-e-Hijri', 'Karachi', 24.931, 67.136 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Dow University Hospital (Ojha Campus)');
UPDATE hospitals SET latitude = 24.931, longitude = 67.136 WHERE hospital_name = 'Dow University Hospital (Ojha Campus)' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Patel Hospital', 'ST-18, Block 4, Gulshan-e-Iqbal', 'Karachi', 24.924, 67.09 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Patel Hospital');
UPDATE hospitals SET latitude = 24.924, longitude = 67.09 WHERE hospital_name = 'Patel Hospital' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Ziauddin Hospital Clifton', 'Block 6, Clifton', 'Karachi', 24.818, 67.03 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Ziauddin Hospital Clifton');
UPDATE hospitals SET latitude = 24.818, longitude = 67.03 WHERE hospital_name = 'Ziauddin Hospital Clifton' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Ziauddin Hospital North Nazimabad', 'Block B, North Nazimabad', 'Karachi', 24.932, 67.042 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Ziauddin Hospital North Nazimabad');
UPDATE hospitals SET latitude = 24.932, longitude = 67.042 WHERE hospital_name = 'Ziauddin Hospital North Nazimabad' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'South City Hospital', 'Block 2, Clifton', 'Karachi', 24.813, 67.033 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'South City Hospital');
UPDATE hospitals SET latitude = 24.813, longitude = 67.033 WHERE hospital_name = 'South City Hospital' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Sindh Government Hospital Liaquatabad', 'Liaquatabad No. 10', 'Karachi', 24.91, 67.043 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Sindh Government Hospital Liaquatabad');
UPDATE hospitals SET latitude = 24.91, longitude = 67.043 WHERE hospital_name = 'Sindh Government Hospital Liaquatabad' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Sindh Government Hospital New Karachi', 'Sector 5-B, New Karachi', 'Karachi', 24.985, 67.068 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Sindh Government Hospital New Karachi');
UPDATE hospitals SET latitude = 24.985, longitude = 67.068 WHERE hospital_name = 'Sindh Government Hospital New Karachi' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Qatar Hospital Orangi', 'Sector 6-B, Orangi Town', 'Karachi', 24.95, 66.99 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Qatar Hospital Orangi');
UPDATE hospitals SET latitude = 24.95, longitude = 66.99 WHERE hospital_name = 'Qatar Hospital Orangi' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Lyari General Hospital', 'Chakiwara Road, Lyari', 'Karachi', 24.867, 66.995 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Lyari General Hospital');
UPDATE hospitals SET latitude = 24.867, longitude = 66.995 WHERE hospital_name = 'Lyari General Hospital' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Saifee Hospital', 'Block F, North Nazimabad', 'Karachi', 24.938, 67.043 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Saifee Hospital');
UPDATE hospitals SET latitude = 24.938, longitude = 67.043 WHERE hospital_name = 'Saifee Hospital' AND latitude IS NULL;
INSERT INTO hospitals (hospital_name, address, location, latitude, longitude) SELECT 'Kharadar General Hospital', 'Atmaram Pritamdas Road, Kharadar', 'Karachi', 24.854, 66.997 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_name = 'Kharadar General Hospital');
UPDATE hospitals SET latitude = 24.854, longitude = 66.997 WHERE hospital_name = 'Kharadar General Hospital' AND latitude IS NULL;

-- =====================================================
-- VACCINE STOCK (demo): har hospital x har vaccine = 50 doses se shuru
-- (file ke aakhir mein hai taake upar wale saare hospitals is mein aa jayen)
-- Do rows jaan boojh kar aisi rakhi hain taake import ke foran baad dono
-- features test ho saken (tafseel CHANGES.md mein hai):
--   City Hospital x Measles 1          -> 6 doses (LOW STOCK alert dikhega)
--   Care Children Clinic x BCG         -> 0 doses (OUT OF STOCK: booking nahi hogi)
-- =====================================================
INSERT INTO vaccine_stock (hospital_id, vaccine_id, quantity)
SELECT h.id, v.id, 50 FROM hospitals h CROSS JOIN vaccines v;

UPDATE vaccine_stock SET quantity = 6
WHERE hospital_id = (SELECT id FROM hospitals WHERE hospital_name = 'City Hospital')
  AND vaccine_id  = (SELECT id FROM vaccines  WHERE vaccine_name  = 'Measles 1');

UPDATE vaccine_stock SET quantity = 0
WHERE hospital_id = (SELECT id FROM hospitals WHERE hospital_name = 'Care Children Clinic')
  AND vaccine_id  = (SELECT id FROM vaccines  WHERE vaccine_name  = 'BCG');
