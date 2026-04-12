# ShopFusion

ShopFusion is a multi-vendor e-commerce website built with PHP, MySQL, HTML, CSS, and JavaScript. It supports three core roles, customer shopping flows, trader storefront management, admin oversight tools, PayPal sandbox checkout, and an optional AI module for chat and review analysis.

## Features

- Multi-role authentication for `admin`, `trader`, and `customer`
- Product catalog with categories, search, sorting, and filtering
- Guest cart support with cart merge after login
- Customer checkout flow with promo codes and loyalty points
- PayPal sandbox payment page
- Trader dashboard for products, orders, and shop-level sales stats
- Admin dashboard for trader approval, products, reports, promo codes, and violations
- AI chatbot for storefront help
- AI review moderation and AI-generated review summaries with fallback behavior when no API key is configured

## Tech Stack

- PHP
- MySQL / MariaDB
- HTML, CSS, JavaScript
- PDO for database access
- cURL for OpenAI API requests
- Chart.js and Font Awesome via CDN

## Project Structure

```text
shopfusion/
|-- admin/        Admin dashboard and management pages
|-- ai/           AI chatbot, review analysis, moderation, docs, and API endpoints
|-- api/          Cart/search AJAX endpoints
|-- auth/         Login, registration, logout, pending approval
|-- config/       Database configuration
|-- customer/     Customer profile, orders, loyalty points
|-- database/     SQL dump and database notes
|-- includes/     Shared helpers, session utilities, layout partials
|-- payment/      PayPal payment flow
|-- shop/         Cart, checkout, product page, search
|-- trader/       Trader dashboard, products, reports, invoices
|-- uploads/      Product uploads
|-- index.php     Homepage
```

## Requirements

- XAMPP, WAMP, Laragon, or another Apache + MySQL + PHP setup
- PHP with:
  - `pdo_mysql`
  - `curl`
- MySQL or MariaDB
- A browser with internet access for CDN assets and PayPal sandbox testing

## Local Setup

1. Place the project in your web root.

   Example for XAMPP:

   ```text
   C:\xampp\htdocs\shopfusion
   ```

2. Create a database named `shopfusion`.

3. Import [`database/shopfusion.sql`](/c:/xampp/htdocs/shopfusion/database/shopfusion.sql) using phpMyAdmin or the MySQL CLI.

4. Verify database settings in [`config/database.php`](/c:/xampp/htdocs/shopfusion/config/database.php).

   Default local values are:

   - Host: `127.0.0.1`
   - Port: `3306`
   - User: `root`
   - Password: empty
   - Database: `shopfusion`

   You can also override them with environment variables:

   - `DB_HOST`
   - `DB_PORT`
   - `DB_USER`
   - `DB_PASS`
   - `DB_NAME`

5. Start Apache and MySQL.

6. Open the app:

   ```text
   http://localhost/shopfusion/
   ```

## Demo Accounts

The login screen includes demo autofill buttons for:

- Trader: `trader1@shopfusion.com`
- Customer: `customer1@shopfusion.com`
- Password used by the login demo buttons: `password`

The SQL dump also includes an admin user:

- Admin email: `admin@shopfusion.com`

If the imported credentials do not match your local environment, reset the passwords directly in the database or register new accounts.

## AI Module Setup

The AI module is optional.

1. Set an environment variable on the server:

   - `OPENAI_API_KEY=your_api_key_here`

2. Optional model overrides:

   - `OPENAI_CHAT_MODEL`
   - `OPENAI_REVIEW_MODEL`

3. Ensure PHP can write to:

   - `ai/logs/`

4. Review the AI notes in:

   - [`ai/docs/SETUP.txt`](/c:/xampp/htdocs/shopfusion/ai/docs/SETUP.txt)
   - [`ai/docs/README.txt`](/c:/xampp/htdocs/shopfusion/ai/docs/README.txt)

### AI Behavior Without an API Key

The AI module still works in fallback mode:

- Chatbot replies use local store-policy logic
- Review moderation uses rule-based checks
- Review summaries use lightweight fallback analysis

## Payment Notes

The PayPal page in [`payment/paypal.php`](/c:/xampp/htdocs/shopfusion/payment/paypal.php) is configured as a demo/sandbox flow and still contains placeholder values:

- `YOUR_PAYPAL_CLIENT_ID`
- `YOUR_PAYPAL_CLIENT_SECRET`

Replace those before expecting a real PayPal sandbox integration to work correctly.

## Core Workflows

- Customers can browse products without logging in
- Guest cart items can be merged after customer login
- Trader registrations are created with `pending` status until approved
- Checkout supports promo codes, loyalty points, and PayPal selection
- Admin users can approve traders, manage violations, and view reports

## Notes

- The AI module may auto-create its own review-related tables during normal use if the database user has permission.
- Product images are stored under `uploads/products/` and `trader/uploads/products/`.
- This project does not require Composer or npm for basic local setup.

## Main Entry Points

- Homepage: [`index.php`](/c:/xampp/htdocs/shopfusion/index.php)
- Login: [`auth/login.php`](/c:/xampp/htdocs/shopfusion/auth/login.php)
- Registration: [`auth/register.php`](/c:/xampp/htdocs/shopfusion/auth/register.php)
- Admin dashboard: [`admin/index.php`](/c:/xampp/htdocs/shopfusion/admin/index.php)
- Trader dashboard: [`trader/index.php`](/c:/xampp/htdocs/shopfusion/trader/index.php)
- Cart: [`shop/cart.php`](/c:/xampp/htdocs/shopfusion/shop/cart.php)
- Checkout: [`shop/checkout.php`](/c:/xampp/htdocs/shopfusion/shop/checkout.php)

## License

This repository does not currently include a license file.
