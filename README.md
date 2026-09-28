# yum-yum
Yum-Yum is a two-sided platform for home chefs and buyers, around digital food. Two interconnected issues  are addressed by the project: firstly, there is the problem of busy students and working professionals having  difficulty finding an affordable home cooked meal.

## Sprint 1

Front-end prototype built from our Figma design. All pages are plain HTML, CSS and JavaScript with sample data. There is no backend or database yet, so forms just move to the next page. Signing up as a Seller opens the seller dashboard, everything else goes to the menu.

## How to run

Option 1: open `index.html` in a browser.

Option 2 (XAMPP):
1. Copy this folder into `C:\xampp\htdocs\`
2. Start Apache
3. Go to `http://localhost/yum-yum/`

VS Code Live Server also works.

## Pages

Public
- `index.html` - home page
- `about.html` - about us
- `contact.html` - contact form

Account
- `auth/login.html`
- `auth/register.html`
- `auth/forgot-password.html`

Buyer
- `buyer/menu.html` - all meals, filter by category
- `buyer/product.html` - single meal
- `buyer/cart.html` - cart
- `buyer/checkout.html` - checkout
- `buyer/orders.html` - my orders, filter by status

Seller
- `seller/dashboard.html` - stats and food list
- `seller/food_add.html` - add new food
- `seller/food_edit.html` - edit food
- `seller/orders.html` - orders and status update
