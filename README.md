# VaxCare: Vaccination Management System

A web application that helps parents keep their children's vaccinations on schedule, lets hospitals manage and record appointments, and gives the admin full control with live statistics.

Built with **PHP (PDO)**, **MySQL**, **HTML, CSS, JavaScript** and **Bootstrap 5**.

🌐 **Live website:** https://vaxcare-pk.infinityfreeapp.com (free hosting on InfinityFree, deployed 21 Nov 2026)

---

## The problem and our solution

Vaccinations for infants and children are often handled manually, so some children miss their doses and are exposed to diseases such as polio. VaxCare moves the whole process online: parents register their children and see exactly which vaccines are due, book hospital appointments, and keep a verifiable digital record, while the admin approves requests and hospitals record each vaccination.

## Features

### Parent
- Register, login, profile (name, phone, city, address) and password change
- Add, edit and delete children
- **Smart vaccine schedule** built from the child's date of birth: *Vaccinated, Booked, Due now, Overdue, Upcoming*
- Dashboard **reminders** for appointments in the next 7 days
- **Past vaccinations:** add vaccines the child already had (verified by the admin) so they are not shown as overdue
- Search hospitals and book an appointment (date, time slot, slot capacity checks); hospitals in the parent's city are shown first as **Near you**
- **Stock-aware booking:** a hospital is not offered for a vaccine that is out of stock there, or whose bookings the admin has paused
- **Suggest a hospital** that is not in the list (the admin verifies it before it is listed)
- Track and cancel pending bookings
- **Visit token** for every approved appointment: unique token number and code, hospital details, directions and 4 easy steps (printable)
- **Notifications (bell):** booking approved (opens the token), rejected, vaccination recorded, record verified, hospital added
- **Vaccination report** and a printable **vaccination certificate** (Save as PDF) with a signed number

### Hospital
- Register with name, **license number** and city; the account becomes active after the team verifies it
- **Invitation link:** hospitals in the list without a login get a one-time link (sent on WhatsApp or email); they sign up and all waiting appointments appear on their dashboard
- Dashboard with today's and upcoming appointments, and a bell for new appointments
- Sees only **approved** appointments of its own
- Records *Vaccinated* or *Not vaccinated* (only on or after the appointment day)
- **Vaccine stock:** its own stock page shows doses left for every vaccine and lets the hospital update quantities; a red **low-stock warning** appears below 10 doses, and every recorded dose reduces the stock automatically by 1

### Admin
- Dashboard with live statistics and **Chart.js charts**
- Hospitals: add and edit with **required** contact email, license number and **password** (the hospital login is created at the same time in `users` and `hospitals`); optional map location (latitude / longitude) with a "use my location" button
- **Invite** hospitals that have no login: WhatsApp, Gmail or email app, copy link or copy message
- Vaccines: add, edit, availability toggle, delete (blocked if already used)
- Children with their vaccination history; all bookings with filters and pagination
- **Approve or reject** parent requests (unavailable vaccines cannot be approved)
- **Hospital requests** and **Past records** verification, **Users** (approve, disable), **Messages** from the contact form
- For hospitals without their own login, the admin can record the vaccination
- **Vaccine stock overview:** every hospital's stock in one place, low-stock and out-of-stock rows highlighted at the top, a low-stock count badge in the menu, and a **pause / resume bookings** switch per hospital and vaccine
- Date-wise **reports** with print (disabled when there is nothing to print) and CSV export

### Website
- Modern navy and coral design, responsive on phones (tables turn into cards)
- Landing page built from **real database data**; the hero shows the logged-in user's own child record or workload
- **Hospitals near me:** uses the visitor's location to list the closest hospitals first, with distance, "Nearest" tag, 5 / 10 / 25 km filter and Google Maps directions
- Hospital directory with area filter and search
- Contact form with spam protection (honeypot and rate limit)
- Public **certificate verification** page
- **City dropdown** on the registration, hospital and profile forms: the city is picked from a list of 22 Pakistani cities and is also checked on the server, so junk entries like "ABCD" can never be saved
- Toast notifications, modern confirm dialogs and double-click protection on forms

## Latest updates

| Date | Update | Details |
|---|---|---|
| 21 Nov 2026 | **Live deployment** | The website is live on free hosting (InfinityFree) and opens on any phone: https://vaxcare-pk.infinityfreeapp.com — the live database is separate from the local XAMPP copy |
| 21 Nov 2026 | **Vaccine stock system** | Stock per hospital and vaccine: hospital stock page, admin overview with low-stock badge (below 10 doses) and pause / resume bookings, stock goes down by 1 automatically when a dose is recorded, and parents are only offered hospitals that have the vaccine in stock |
| 21 Nov 2026 | **City dropdown** | The city is picked from a list of 22 Pakistani cities on the registration, hospital and profile forms, with the same list checked on the server — free-text entries like "ABCD" can no longer be saved |

## Tech stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 5, Bootstrap Icons, Chart.js |
| Backend | PHP 8 (plain PHP, PDO) |
| Database | MySQL |
| Local server | XAMPP (Apache, MySQL, phpMyAdmin) |

## Setup

1. Install **XAMPP** and start **Apache** and **MySQL**.
2. Copy this folder to `C:\xampp\htdocs\vaccination-system` (the folder name must match `BASE_URL` in `config/config.php`).
3. Open `http://localhost/phpmyadmin`, go to the **Import** tab and import `database/vaccination.sql`.
4. Open `http://localhost/vaccination-system/`.

If Apache uses another port, add it to the address (for example `http://localhost:82/vaccination-system/`). If MySQL uses another port or has a password, change it in `config/db.php`.

Before a final demo set `DEBUG` to `false` in `config/config.php` and change `CERT_SECRET`.

When the website is online, set `SITE_URL` in `config/config.php` (for example `https://vaxcare.pk`) so invitation links open on any phone. **Hospitals near me** needs `https://` or `localhost` (browsers block location on plain `http`).

The site loads Bootstrap, Bootstrap Icons, Chart.js and the Poppins font from the internet (CDN), so an internet connection is needed.

## Deployment (live site)

- Hosted free on **InfinityFree**: https://vaxcare-pk.infinityfreeapp.com
- Live database: `if0_43496946_vaxcare` on `sql101.infinityfree.com` (managed from the InfinityFree dashboard → phpMyAdmin). The live site's data is **only** in this database — the XAMPP database on a laptop is a separate test copy.
- The live copy differs from this folder only in `config/`: `DEBUG` is `false`, `BASE_URL` is empty, `SITE_URL` is the live address, a fresh `CERT_SECRET` is set, and `config/db.php` holds the hosting database details (the password exists only in that file on the server — never in this README or in chat).
- For security, `README.md`, the SQL file, `docs/` and the checklists are **not** uploaded to the live site; only the website code is.
- Free-hosting limits: the site can be a little slow, there is a daily visits limit, and PHP mail does not work. Good for demos and viva; real patient data should not be put on free hosting.

## Demo accounts

| Role | Email | Password |
|---|---|---|
| Admin | admin@vaccine.com | admin123 |
| Parent | ali@example.com | 123456 |
| Parent | sara@example.com | 123456 |
| Parent | fatima@example.com | 123456 |
| Hospital | city@hospital.com | 123456 |
| Hospital | care@hospital.com | 123456 |
| Hospital (waiting for approval) | sunrise@hospital.com | 123456 |

Demo hospital invitation link: `register.php?role=hospital&claim=1b20bef250dcf56702311e704998cb02`

(New registrations need at least 8 characters; the demo passwords were inserted directly.)

These demo passwords are for local testing only — never use them on a live site.

## How the system works

```
Parent adds child -> sees due vaccines -> books appointment (pending)
        -> Admin approves or rejects (parent is notified)
        -> Hospital sees the approved appointment -> records Vaccinated / Not vaccinated
        -> Parent is notified, the schedule updates and the certificate can be downloaded
```

## Project structure

```
vaccination-system/
├── index.php              Home page: hero, schedule, hospitals near me, contact form, FAQ
├── login.php              One login for admin, parent and hospital
├── register.php           Sign up for parents and hospitals (also opens hospital invitation links)
├── logout.php             Ends the session
├── verify.php             Public page to check if a certificate is real
├── contact_submit.php     Saves the contact form message (no page)
├── notifications.php      All notifications of the logged-in user
├── notification_open.php  Marks a notification as read and opens its link (no page)
│
├── config/
│   ├── config.php         Settings: app name, BASE_URL, SITE_URL, DEBUG, secret, session, timezone
│   └── db.php             MySQL connection (PDO)
│
├── includes/
│   ├── auth.php           Login and role check for protected pages
│   ├── functions.php      Helpers: escaping, CSRF, flash messages, dates, pagination, notifications, token number
│   ├── schedule.php       Vaccination schedule engine (due, overdue, upcoming)
│   ├── header.php / footer.php                    Public page layout
│   ├── admin_header.php / admin_footer.php        Admin panel layout (sidebar + topbar)
│   ├── parent_header.php / parent_footer.php      Parent panel layout
│   ├── hospital_header.php / hospital_footer.php  Hospital panel layout
│   └── notification_bell.php                      Bell dropdown in the topbar
│
├── admin/
│   ├── dashboard.php      Stats, charts and recent bookings
│   ├── hospitals.php      Hospital list  ·  hospital_form.php  add / edit (with login)  ·  hospital_delete.php
│   ├── hospital_invite.php  Invitation link + WhatsApp / Gmail / email buttons
│   ├── vaccines.php       Vaccine list  ·  vaccine_form.php  ·  vaccine_toggle.php  ·  vaccine_delete.php
│   ├── requests.php       Approve / reject appointment requests  ·  request_action.php
│   ├── bookings.php       All bookings with filters  ·  booking_status.php (record vaccination for hospitals without login)
│   ├── children.php       All children  ·  child_view.php  one child's history
│   ├── past_records.php   Verify past vaccinations  ·  past_action.php
│   ├── suggestions.php    Hospitals suggested by parents  ·  suggestion_action.php
│   ├── users.php          Approve / disable accounts  ·  user_action.php
│   ├── messages.php       Contact form messages  ·  message_action.php
│   ├── reports.php        Date-wise report, print and CSV
│   └── index.php          Redirects to the dashboard
│
├── parent/
│   ├── dashboard.php      Reminders and each child's schedule
│   ├── children.php       Children list  ·  child_form.php add / edit  ·  child_delete.php
│   ├── book_hospital.php  Book an appointment
│   ├── my_bookings.php    All bookings  ·  booking_cancel.php  ·  token.php (visit token)
│   ├── past_vaccinations.php  Add earlier vaccines  ·  past_delete.php
│   ├── suggest_hospital.php   Suggest a new hospital
│   ├── reports.php        Vaccination record  ·  certificate.php  printable certificate
│   ├── profile.php        Profile and password
│   └── index.php          Redirects to the dashboard
│
├── hospital/
│   ├── dashboard.php      Today's and upcoming appointments
│   ├── appointments.php   Approved appointments  ·  update_status.php (Vaccinated / Not vaccinated)
│   ├── profile.php        Hospital details and password
│   └── index.php          Redirects to the dashboard
│
├── assets/
│   ├── css/style.css      All styles (theme colors at the top)
│   ├── js/script.js       All JavaScript (forms, confirm dialogs, copy, hospitals near me, ...)
│   └── img/logo.svg       Logo and browser icon
│
└── database/
    └── vaccination.sql    Full database: tables + demo data (import this one file)
```

`.htaccess` files block direct browser access to `config/`, `includes/` and `database/`. The small `index.html` files stop the browser from listing a folder's files.

## Database (9 tables)

| Table | Purpose |
|---|---|
| `users` | Login for all roles: name, email, password, phone, role, status, login lockout, city, address |
| `hospitals` | Hospital details: name, address, city, phone, license number, contact email, map location, invitation token |
| `children` | Children of a parent: name, date of birth, gender, blood group |
| `vaccines` | Vaccine list with recommended age (in days) and availability |
| `bookings` | Appointments: `approval_status` (admin) and `vaccination_status` (hospital) |
| `past_vaccinations` | Earlier vaccines entered by parents, verified by the admin |
| `hospital_suggestions` | Hospitals suggested by parents, reviewed by the admin |
| `contact_messages` | Messages from the website contact form |
| `notifications` | Bell notifications for parents and hospitals |

## Security

- Passwords hashed with `password_hash()` and verified with `password_verify()`; minimum 8 characters
- **PDO prepared statements** wherever user input reaches SQL (SQL injection)
- Output escaped with `htmlspecialchars()` through the `e()` helper (XSS)
- **CSRF token** in every POST form, checked on the server
- **Role-based access control** on every protected page, and **ownership checks** (IDOR)
- Account status checked on every request (a disabled account is logged out immediately)
- **Login lockout:** 5 wrong passwords lock the account for 5 minutes
- Hospital approval, one-time invitation tokens, signed (HMAC) certificate numbers
- Session cookie flags `HttpOnly` and `SameSite`, session ID regenerated on login and password change
- Security headers; direct browser access to `config/`, `includes/`, and `database/` is blocked
- Server-side validation for every form; database transactions where several tables change together

## Known limitations and future work

- Notifications appear inside the website only. Invitations are sent through WhatsApp or the admin's own email (Gmail / email app); automatic emails would need a mail server
- The login lockout is per account (an IP-based limit would be the next step)
- Slot capacity check and booking insert are separate steps (a database lock would remove the small race condition)
- A recorded vaccination status is final; corrections would need an admin audit feature
- English interface only


