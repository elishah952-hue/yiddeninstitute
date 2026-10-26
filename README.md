# Yidden Institute website

Four pages (Home, Courses, Admissions, Contact) built with PHP 8.1+, MySQL and Bootstrap 5.

## Setup (XAMPP, WAMP or Laragon)
1. Copy the `yidden-institute` folder into `htdocs` (XAMPP) or `www` (WAMP/Laragon).
2. Start Apache and MySQL, open phpMyAdmin, go to **Import**, and import `database/schema.sql`.
   This creates the `yidden_institute` database with 8 departments, 54 courses, 6 news items,
   5 events, 5 testimonials and 4 intakes.
3. Open `includes/config.php` and check the database user and password, then replace the placeholder
   contact details, email addresses, social links and statistics.
4. Visit http://localhost/yidden-institute/

## Deploying to shared hosting
- Create a MySQL database and user in cPanel, import `schema.sql` (remove the first two lines that
  create and select `yidden_institute` if your host gives you a fixed database name), and put the
  credentials in `includes/config.php`.
- Set `SITE_URL` in `config.php` to your live address.
- `mail()` must be enabled on the host for confirmation emails. If it is not, applications and
  messages are still saved in the database.
- `includes/` and `database/` include `.htaccess` files that block direct web access on Apache.

## Where things are
- `includes/` shared config, database, helpers, header, footer and card partials
- `api/courses.php` live filtering and course details; `api/newsletter.php` signups
- `assets/css/style.css`, `assets/js/main.js`
- Applications are in the `applications` table, messages in `contact_messages`.
