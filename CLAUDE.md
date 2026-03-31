# CLAUDE.md

## Project Overview

PHP sample code repository for **Authorize.Net** payment gateway integration. Contains standalone API usage examples and a full e-commerce demo application (LuxurySkiMarketplace). Licensed under MIT (Authorize.Net).

## Repository Structure

```
sample-code-php/
├── ApplePayTransactions/       # Apple Pay payment example (1 sample)
├── CustomerProfiles/           # Customer profile CRUD operations (12 samples)
├── PaymentTransactions/        # Core payment processing (12 samples)
├── PaypalExpressCheckout/      # PayPal Express integration (8 samples)
├── RecurringBilling/           # Subscription/ARB management (6 samples)
├── TransactionReporting/       # Reporting and settlement queries (5 samples)
├── VisaCheckout/               # Visa Checkout integration (2 samples)
├── LuxurySkiMarketplace/      # Full e-commerce application (MVC)
│   ├── config/                 # App configuration constants
│   ├── controllers/            # Business logic (CheckoutController)
│   ├── models/                 # Data models (Cart, Product, Order)
│   ├── views/                  # PHP view templates
│   ├── public/                 # Web root (index.php, CSS, JS)
│   ├── data/                   # JSON-based data storage
│   ├── vendor_bridge.php       # Bridges to root composer autoloader
│   └── serve.py                # Dev server proxy script
├── composer.json               # Dependencies (authorizenet SDK 1.8.6.2)
└── README.md
```

## Setup & Running

```bash
# Install dependencies
composer update

# Run any standalone sample
php PaymentTransactions/charge-credit-card.php
php CustomerProfiles/create-customer-profile.php

# Run the LuxurySkiMarketplace web app
cd LuxurySkiMarketplace/public
php -S localhost:8000
```

## Dependencies

- **PHP** >= 5.2.0 (composer.json minimum; LuxurySkiMarketplace uses PHP 7+ patterns)
- **ext-curl** required
- **authorizenet/authorizenet** 1.8.6.2 — official Authorize.Net PHP SDK
- Autoloader: `vendor/autoload.php` (standard Composer)

## Code Conventions

### File Naming
- Sample scripts: **kebab-case** (e.g., `charge-credit-card.php`, `create-customer-profile.php`)
- Class files: **PascalCase** (e.g., `Cart.php`, `CheckoutController.php`)

### PHP Style
- Classes: **PascalCase** (`Cart`, `Product`, `CheckoutController`)
- Methods: **camelCase** (`getItems()`, `addItem()`, `processPayment()`)
- Constants: **UPPER_SNAKE_CASE** (`AUTHORIZENET_API_LOGIN_ID`, `MERCHANT_AUTHENTICATION`)
- Sample scripts use procedural style with inline Authorize.Net API calls
- LuxurySkiMarketplace uses MVC with static methods on model/controller classes

### Authorize.Net SDK Patterns
All samples follow this structure:
1. Require `vendor/autoload.php`
2. Import `net\authorize\api\contract\v1` and `net\authorize\api\controller` namespaces
3. Create `MerchantAuthenticationType` with API login ID and transaction key
4. Build request object, create controller, execute, check response

### Credential Handling
- Sample scripts use **hardcoded sandbox credentials** (login ID: `5KP3u95bQpv`, transaction key: `346HZ32z3fP4hTG2`)
- LuxurySkiMarketplace uses constants in `config/config.php`
- For production: replace with environment variables; never commit real credentials

### Data Storage (LuxurySkiMarketplace)
- Products: `data/products.json`
- Orders: `data/orders.json`
- Cart: PHP session-based
- No database; all persistence is flat-file JSON

## Testing

No automated test suite exists. Samples are verified by manual execution against the Authorize.Net sandbox environment. When modifying samples:
- Test against sandbox API (`apitest.authorize.net`)
- Verify response codes and output
- Use test card number `4111111111111111`, expiry `2038-12`, CVV `123`

## CI/CD

No CI/CD pipeline is configured.

## Architecture Notes

- **Standalone samples** are self-contained scripts — each file demonstrates one API operation
- **LuxurySkiMarketplace** is an MVC e-commerce app with a front-controller router (`public/index.php` + `router.php`)
- Views use `htmlspecialchars()` for XSS protection
- No database layer, ORM, or framework — all code is vanilla PHP
- No dependency injection; configuration is via PHP constants

## Common Tasks

| Task | Command |
|------|---------|
| Install deps | `composer update` |
| Run a sample | `php <Directory>/<sample-file>.php` |
| Start web app | `cd LuxurySkiMarketplace/public && php -S localhost:8000` |
| Add a new sample | Create kebab-case `.php` file in the appropriate directory |
