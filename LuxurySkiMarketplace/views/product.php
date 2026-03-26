<?php
require_once __DIR__ . '/../models/Product.php';

$productId = isset($_GET['id']) ? $_GET['id'] : null;
$product = $productId ? Product::findById($productId) : null;

if (!$product) {
    $pageTitle = 'Product Not Found';
    require_once __DIR__ . '/partials/header.php';
    echo '<div class="empty-state"><p>Product not found.</p><a href="index.php?page=catalog" class="btn btn-primary">Back to Shop</a></div>';
    require_once __DIR__ . '/partials/footer.php';
    return;
}

$pageTitle = $product['name'];
require_once __DIR__ . '/partials/header.php';
?>

<section class="product-detail">
    <div class="product-detail-layout">
        <div class="product-detail-image">
            <div class="product-image-placeholder large">
                <span class="brand-tag"><?= htmlspecialchars($product['brand']) ?></span>
                <?php if ($product['condition'] !== 'New with Tags'): ?>
                    <span class="condition-tag"><?= htmlspecialchars($product['condition']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="product-detail-info">
            <p class="product-brand-label"><?= htmlspecialchars($product['brand']) ?></p>
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <p class="product-price-large">$<?= number_format($product['price'], 2) ?></p>

            <div class="product-meta">
                <p><strong>Condition:</strong> <?= htmlspecialchars($product['condition']) ?></p>
                <p><strong>Color:</strong> <?= htmlspecialchars($product['color']) ?></p>
                <p><strong>In Stock:</strong> <?= $product['stock'] ?> available</p>
            </div>

            <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>

            <?php if ($product['stock'] > 0): ?>
            <form method="POST" action="index.php?page=cart&action=add" class="add-to-cart-form">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

                <div class="form-group">
                    <label for="size">Size:</label>
                    <select name="size" id="size" required>
                        <?php foreach ($product['sizes'] as $size): ?>
                        <option value="<?= htmlspecialchars($size) ?>"><?= htmlspecialchars($size) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= $product['stock'] ?>">
                </div>

                <button type="submit" class="btn btn-primary btn-large">Add to Cart</button>
            </form>
            <?php else: ?>
                <p class="out-of-stock">This item is currently sold out.</p>
            <?php endif; ?>

            <div class="shipping-info">
                <p>Free shipping on orders over $<?= number_format(FREE_SHIPPING_THRESHOLD, 0) ?></p>
                <p>Secure payment via Authorize.Net</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
