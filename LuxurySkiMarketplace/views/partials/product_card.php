<?php
/**
 * Reusable product card component.
 * Expects $product array to be set before including this file.
 */
require_once __DIR__ . '/product_helpers.php';
$catLabel = getCategoryLabel($product['category']);
$hasImage = !empty($product['image']);
?>
<div class="product-card">
    <a href="index.php?page=product&id=<?= $product['id'] ?>" class="product-card-link">
        <div class="product-image-wrap">
            <?php if ($hasImage): ?>
                <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-img" loading="lazy">
            <?php else: ?>
                <div class="product-img-fallback"></div>
            <?php endif; ?>
            <span class="brand-tag"><?= htmlspecialchars($product['brand']) ?></span>
            <span class="category-tag"><?= $catLabel ?></span>
            <?php if ($product['condition'] !== 'New with Tags'): ?>
                <span class="condition-tag"><?= htmlspecialchars($product['condition']) ?></span>
            <?php endif; ?>
            <?php if ($product['stock'] <= 1): ?>
                <span class="stock-tag">Only 1 left</span>
            <?php endif; ?>
        </div>
        <div class="product-info">
            <p class="product-brand"><?= htmlspecialchars($product['brand']) ?></p>
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p class="product-color"><?= htmlspecialchars($product['color']) ?></p>
            <div class="product-price-row">
                <p class="product-price">$<?= number_format($product['price'], 2) ?></p>
                <?php if (count($product['sizes']) > 1): ?>
                    <p class="product-sizes"><?= count($product['sizes']) ?> sizes</p>
                <?php else: ?>
                    <p class="product-sizes">Size <?= htmlspecialchars($product['sizes'][0]) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </a>
</div>
