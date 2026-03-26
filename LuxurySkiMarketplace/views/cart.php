<?php
require_once __DIR__ . '/../models/Cart.php';

// Handle cart actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'add':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $result = Cart::addItem(
                    $_POST['product_id'],
                    $_POST['size'],
                    (int)($_POST['quantity'] ?? 1)
                );
                $cartMessage = $result['message'];
                $cartSuccess = $result['success'];
            }
            break;
        case 'remove':
            $cartKey = $_GET['key'] ?? '';
            Cart::removeItem($cartKey);
            $cartMessage = 'Item removed from cart.';
            $cartSuccess = true;
            break;
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                foreach ($_POST['quantities'] as $key => $qty) {
                    Cart::updateQuantity($key, (int)$qty);
                }
                $cartMessage = 'Cart updated.';
                $cartSuccess = true;
            }
            break;
    }
}

$items = Cart::getItems();
$subtotal = Cart::getSubtotal();
$tax = Cart::getTax();
$shipping = Cart::getShipping();
$total = Cart::getTotal();
$pageTitle = 'Shopping Cart';

require_once __DIR__ . '/partials/header.php';
?>

<section class="cart-page">
    <h1>Your Cart</h1>

    <?php if (isset($cartMessage)): ?>
        <div class="alert <?= $cartSuccess ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($cartMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <div class="empty-state">
            <p>Your cart is empty.</p>
            <a href="index.php?page=catalog" class="btn btn-primary">Start Shopping</a>
        </div>
    <?php else: ?>
        <form method="POST" action="index.php?page=cart&action=update">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Brand</th>
                        <th>Size</th>
                        <th>Condition</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $key => $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= htmlspecialchars($item['brand']) ?></td>
                        <td><?= htmlspecialchars($item['size']) ?></td>
                        <td><?= htmlspecialchars($item['condition']) ?></td>
                        <td>$<?= number_format($item['price'], 2) ?></td>
                        <td>
                            <input type="number" name="quantities[<?= htmlspecialchars($key) ?>]"
                                   value="<?= $item['quantity'] ?>" min="0" max="10" class="qty-input">
                        </td>
                        <td>$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        <td><a href="index.php?page=cart&action=remove&key=<?= urlencode($key) ?>" class="remove-link">Remove</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-secondary">Update Cart</button>
        </form>

        <div class="cart-summary">
            <h3>Order Summary</h3>
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>$<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Tax (<?= TAX_RATE * 100 ?>%):</span>
                <span>$<?= number_format($tax, 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Shipping:</span>
                <span><?= $shipping > 0 ? '$' . number_format($shipping, 2) : 'FREE' ?></span>
            </div>
            <div class="summary-row total">
                <span>Total:</span>
                <span>$<?= number_format($total, 2) ?></span>
            </div>
            <a href="index.php?page=checkout" class="btn btn-primary btn-large">Proceed to Checkout</a>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
