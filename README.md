# yum-yum
Yum-Yum is a two-sided platform for home chefs and buyers, around digital food. Two interconnected issues  are addressed by the project: firstly, there is the problem of busy students and working professionals having  difficulty finding an affordable home cooked meal.

Home cooks list their tiffin meals, buyers browse the menu, add meals to a cart and place orders, and pay the seller in cash when the food arrives.

## Built with

- PHP 8.1+ (PDO, no framework)
- MySQL / MariaDB 10.4+
- HTML, CSS and JavaScript
- XAMPP for running it locally

## How to run locally

1. Copy this folder into `C:\xampp\htdocs\` and keep the folder name `yum-yum`
2. Start Apache and MySQL in the XAMPP control panel
3. Open phpMyAdmin and import `database/yum_yum_db.sql` (it creates the `yum_yum_db` database and 8 food categories)
4. Go to `http://localhost/yum-yum/`

The defaults in `config/config.php` match XAMPP (user `root`, no password, folder `/yum-yum/`), so no setup file is needed to try it. Emails (password reset, order notices) are written to `storage/logs/mail.log` until real email is set up.

To try it, register one account as a Seller and add some food, then register another account as a Buyer and place an order.

### Upgrading an existing database

If you already have `yum_yum_db` from an earlier version, do not re-import the full file (it deletes all tables). Run the upgrade instead, which keeps every user, meal and order:

```
C:\xampp\mysql\bin\mysql -u root yum_yum_db < database\migrations\2026-11-15_cod_profiles_security.sql
```

or import that file in phpMyAdmin with `yum_yum_db` selected. It is safe to run more than once.

## Settings and real email

Copy `config/config.local.sample.php` to `config/config.local.php` and change what you need. This file is ignored by Git, so passwords never reach GitHub.

To send real email through Gmail:

1. Turn on 2-Step Verification for the Gmail account
2. Create an App Password at https://myaccount.google.com/apppasswords
3. In `config/config.local.php` set `MAIL_DRIVER` to `smtp`, `MAIL_USERNAME` and `MAIL_FROM` to the Gmail address, and `MAIL_PASSWORD` to the 16-character app password
4. Set `CONTACT_TO` to the inbox that should receive contact form messages
5. Check it works: `C:\xampp\php\php.exe tools\send-test-email.php you@example.com`

## Putting it online (shared hosting)

1. Create a MySQL database and user in the hosting control panel
2. Import `database/yum_yum_db.sql` into it (remove the first `CREATE DATABASE` and `USE` lines if the host does not allow them)
3. Upload all files. If the site is the root of the domain, set `BASE_PATH` to `/` and change the `ErrorDocument` line in `.htaccess` to `/404.php`
4. Create `config/config.local.php` with `APP_ENV` = `production`, the real `APP_URL` (with `https://`), the database details and the email settings
5. Make sure `uploads/food/` and `storage/logs/` are writable by PHP
6. Turn on HTTPS in the control panel, then uncomment the HTTPS lines at the bottom of `.htaccess`

The `.htaccess` files block direct access to `config/`, `includes/`, `database/` and `storage/`, and stop any script from running inside `uploads/`. On Nginx, add equivalent rules.

## Features

Buyer
- browse the menu, filter by category and sort by price or name
- view a meal, see who cooks it and other meals from the same seller
- seller pages showing the kitchen, area, bio, completed orders and every meal on sale
- cart with quantity changes (up to 20 of a meal)
- checkout with delivery name, phone, address and a note; one order per seller
- cash on delivery: cancel while pending, confirm "I received my food" on arrival
- order history with order and payment status

Seller
- dashboard with items, orders, cash collected and cash still to collect
- add, edit, hide or remove meals with an image (meals that were ordered are archived so order history stays complete)
- orders page with delivery details: move each order forward step by step, and mark the cash as received
- profile with kitchen name, delivery area, phone and bio, shown on the public seller page
- email notice for every new order

Accounts and security
- register as a buyer or seller, log in and log out
- passwords hashed with bcrypt; at least 8 characters with a letter and a number
- forgot password: the link is emailed, works once and expires after one hour; the page never reveals whether an email is registered
- CSRF protection on every form, rate limiting on login, reset and contact, secure session cookies, security headers
- contact form messages are saved in the database and emailed to the team

## Pages

- `index.php`, `about.php`, `contact.php`, `seller.php` (public seller page), `privacy.php`, `terms.php`
- `auth/` login, register, logout, forgot and reset password
- `buyer/` menu, product, cart, checkout and orders
- `seller/` dashboard, add/edit/delete food, orders and profile
- `includes/` shared header and footer, bootstrap (session, CSRF, errors), access guard, mailer, order rules and image uploads
- `database/` full schema and migrations

## Team

| Name | Part |
|---|---|
| Dhruba Raj Parajuli | Team lead, authentication (`authdesign` branch) |
| Sai | Database integration (`DatabaseIntegration` branch) |
| Sneha | Home, about and contact pages, shared header and footer (`Home` branch) |
| Sagar | Seller dashboard (`SellerDashboard` branch) |

Each feature was built on its own branch and merged into `main` through a pull request.
