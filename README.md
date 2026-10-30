# yum-yum
Yum-Yum is a two-sided platform for home chefs and buyers, around digital food. Two interconnected issues  are addressed by the project: firstly, there is the problem of busy students and working professionals having  difficulty finding an affordable home cooked meal.

Home cooks list their tiffin meals, buyers browse the menu, add meals to a cart and place orders, and the seller updates each order until it is delivered.

## Built with

- PHP 8 (PDO)
- MySQL / MariaDB
- HTML, CSS and JavaScript
- XAMPP for running it locally

## How to run

1. Copy this folder into `C:\xampp\htdocs\` and keep the folder name `yum-yum`
2. Start Apache and MySQL in the XAMPP control panel
3. Open phpMyAdmin and import `database/yum_yum_db.sql` (it creates the `yum_yum_db` database and 8 food categories)
4. Go to `http://localhost/yum-yum/`

The database settings are in `config/db.php` (default XAMPP user `root` with no password). If you rename the folder, change `BASE_PATH` in the same file.

To try it, register one account as a Seller and add some food, then register another account as a Buyer and place an order.

## Features

Buyer
- browse the menu, filter by category and sort by price or name
- view a meal, add it to the cart and change quantities
- check out and see order history with the current status

Seller
- dashboard with total items, available items, orders and revenue
- add, edit, hide or delete food items with an image
- see orders for their own food and update the status (pending, confirmed, preparing, out for delivery, delivered or cancelled)

Accounts
- register as a buyer or seller, log in and log out
- passwords are hashed and pages are protected by role
- forgot password with a reset link that expires after an hour

## Pages

- `index.php`, `about.php`, `contact.php`
- `auth/` login, register, logout, forgot and reset password
- `buyer/` menu, product, cart, checkout and orders
- `seller/` dashboard, add/edit/delete food and orders

## Team

| Name | Part |
|---|---|
| Dhruba Raj Parajuli | Team lead, authentication (`authdesign` branch) |
| Sai Yadav | Database integration (`DatabaseIntegration` branch) |
| Sneha | Home, about and contact pages, shared header and footer (`Home` branch) |
| Sagar | Seller dashboard (`SellerDashboard` branch) |

Each feature was built on its own branch and merged into `main` through a pull request.

## Known issues

- the password reset link is shown on the page because email is not set up yet
- deleting a food item that has already been ordered shows an error, hiding it works instead
- the old Sprint 1 `.html` pages are still in the repo and will be removed
