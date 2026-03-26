# Kypre - Luxury Ski Marketplace

A curated luxury ski fashion marketplace built in PHP, featuring Goldbergh designer pieces and a pre-loved collection of premium ski wear. Powered by Authorize.Net for secure payment processing.

## Features

- **Product Catalog** - Browse by category, brand, or search
- **Goldbergh Collection** - New-with-tags luxury ski fashion from the Dutch brand
- **Ski Collection** - Curated pre-loved designer pieces (Moncler, Fusalp, Bogner, Toni Sailer, etc.)
- **Shopping Cart** - Add, update, remove items with automatic tax and shipping calculation
- **Secure Checkout** - Authorize.Net payment gateway integration
- **Responsive Design** - Mobile-friendly luxury aesthetic

## Quick Start

1. Install dependencies from the project root:
   ```bash
   composer install
   ```

2. Start the PHP development server:
   ```bash
   cd LuxurySkiMarketplace/public
   php -S localhost:8000
   ```

3. Open `http://localhost:8000` in your browser.

## Project Structure

```
LuxurySkiMarketplace/
├── config/
│   └── config.php          # Marketplace configuration
├── controllers/
│   └── CheckoutController.php  # Payment processing
├── data/
│   └── products.json       # Product catalog data
├── models/
│   ├── Cart.php            # Shopping cart logic
│   ├── Order.php           # Order management
│   └── Product.php         # Product catalog queries
├── public/
│   ├── css/style.css       # Luxury design system
│   ├── js/app.js           # Frontend interactions
│   └── index.php           # Front controller
├── views/
│   ├── partials/
│   │   ├── header.php
│   │   └── footer.php
│   ├── home.php            # Landing page
│   ├── catalog.php         # Product listing
│   ├── product.php         # Product detail
│   ├── cart.php            # Shopping cart
│   └── checkout.php        # Checkout & payment
└── vendor_bridge.php       # Autoloader bridge
```

## Payment Processing

Uses Authorize.Net sandbox credentials for testing. In production, update the API credentials in `config/config.php`.

Test card: `4111111111111111`, Expiry: `2038-12`, CVV: `123`
