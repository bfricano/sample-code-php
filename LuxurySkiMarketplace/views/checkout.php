<?php
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../controllers/CheckoutController.php';

$items = Cart::getItems();
$checkoutResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkoutResult = CheckoutController::processPayment($_POST);
    if ($checkoutResult['success']) {
        $pageTitle = 'Order Confirmed';
        require_once __DIR__ . '/partials/header.php';
        ?>
        <section class="confirmation-page">
            <div class="confirmation-box">
                <h1>Thank You for Your Order!</h1>
                <p class="confirmation-message">Your payment has been processed successfully.</p>
                <div class="order-details">
                    <p><strong>Order ID:</strong> <?= htmlspecialchars($checkoutResult['order']['id']) ?></p>
                    <p><strong>Transaction ID:</strong> <?= htmlspecialchars($checkoutResult['trans_id']) ?></p>
                    <p><strong>Auth Code:</strong> <?= htmlspecialchars($checkoutResult['auth_code']) ?></p>
                    <p><strong>Total Charged:</strong> $<?= number_format($checkoutResult['order']['total'], 2) ?></p>
                </div>
                <p>A confirmation will be sent to <?= htmlspecialchars($checkoutResult['order']['customer']['email']) ?>.</p>
                <a href="index.php" class="btn btn-primary">Continue Shopping</a>
            </div>
        </section>
        <?php
        require_once __DIR__ . '/partials/footer.php';
        return;
    }
}

if (empty($items)) {
    $pageTitle = 'Checkout';
    require_once __DIR__ . '/partials/header.php';
    echo '<div class="empty-state"><p>Your cart is empty.</p><a href="index.php?page=catalog" class="btn btn-primary">Shop Now</a></div>';
    require_once __DIR__ . '/partials/footer.php';
    return;
}

$subtotal = Cart::getSubtotal();
$tax = Cart::getTax();
$shipping = Cart::getShipping();
$total = Cart::getTotal();
$pageTitle = 'Checkout';
require_once __DIR__ . '/partials/header.php';
?>

<section class="checkout-page">
    <h1>Checkout</h1>

    <?php if ($checkoutResult && !$checkoutResult['success']): ?>
        <div class="alert alert-error"><?= htmlspecialchars($checkoutResult['message']) ?></div>
    <?php endif; ?>

    <div class="checkout-layout">
        <form method="POST" action="index.php?page=checkout" class="checkout-form">
            <div class="form-section">
                <h2>Contact & Shipping</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="address">Address *</label>
                    <input type="text" id="address" name="address" required
                           value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City *</label>
                        <input type="text" id="city" name="city" required
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="state">State *</label>
                        <input type="text" id="state" name="state" required maxlength="2"
                               value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="zip">ZIP Code *</label>
                        <input type="text" id="zip" name="zip" required
                               value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Payment Details</h2>
                <p class="secure-badge">Secured by Authorize.Net</p>
                <div class="form-group">
                    <label for="card_number">Card Number *</label>
                    <input type="text" id="card_number" name="card_number" required
                           placeholder="4111111111111111" maxlength="16">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="card_expiry">Expiration (YYYY-MM) *</label>
                        <input type="text" id="card_expiry" name="card_expiry" required
                               placeholder="2038-12">
                    </div>
                    <div class="form-group">
                        <label for="card_cvv">CVV *</label>
                        <input type="text" id="card_cvv" name="card_cvv" required
                               maxlength="4" placeholder="123">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-large">Place Order - $<?= number_format($total, 2) ?></button>
        </form>

        <div class="checkout-summary">
            <h3>Order Summary</h3>
            <?php foreach ($items as $item): ?>
            <div class="checkout-item">
                <span><?= htmlspecialchars($item['name']) ?> (<?= $item['size'] ?>) x<?= $item['quantity'] ?></span>
                <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
            </div>
            <?php endforeach; ?>
            <hr>
            <div class="summary-row"><span>Subtotal:</span><span>$<?= number_format($subtotal, 2) ?></span></div>
            <div class="summary-row"><span>Tax:</span><span>$<?= number_format($tax, 2) ?></span></div>
            <div class="summary-row"><span>Shipping:</span><span><?= $shipping > 0 ? '$' . number_format($shipping, 2) : 'FREE' ?></span></div>
            <div class="summary-row total"><span>Total:</span><span>$<?= number_format($total, 2) ?></span></div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
