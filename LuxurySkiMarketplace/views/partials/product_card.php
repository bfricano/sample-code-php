<?php
/**
 * Reusable product card component.
 * Expects $product array to be set before including this file.
 */
require_once __DIR__ . '/product_helpers.php';
$brandColors = getBrandColors($product['brand']);
$iconSvg = getProductIcon($product['icon'] ?? 'jacket');
$catLabel = getCategoryLabel($product['category']);

// Map color names to CSS color values for the color swatch
$colorMap = [
    'White/Gold' => ['#f5f0e8', '#c9a96e'],
    'Black' => ['#1a1a1a', '#333333'],
    'Navy' => ['#1a2744', '#2a3f6a'],
    'Cream' => ['#f5f0e0', '#e8dcc8'],
    'Midnight Blue' => ['#0a1628', '#1a3050'],
    'Black/Silver' => ['#1a1a1a', '#a0a0b0'],
    'Leopard Print' => ['#8a6a3a', '#c4a460'],
    'Red/Gold' => ['#8a1a1a', '#c9a96e'],
    'Sage Green' => ['#6a7a5a', '#8a9a7a'],
    'White' => ['#f0f0f0', '#e0e0e0'],
    'Black/Gold' => ['#1a1a1a', '#c9a96e'],
    'Rose Gold' => ['#b76e79', '#e8b4b8'],
    'White/Blue' => ['#e8f0f8', '#5080b0'],
    'Alpine White' => ['#f5f5f0', '#e0ddd5'],
    'Midnight Navy' => ['#0a1020', '#1a2848'],
    'Red/White Star' => ['#c03030', '#f0e8e0'],
    'Cream/Gold' => ['#f5f0e0', '#c9a96e'],
];
$swatchColors = $colorMap[$product['color']] ?? ['#d0c8c0', '#b0a898'];
?>
<div class="product-card">
    <a href="index.php?page=product&id=<?= $product['id'] ?>" class="product-card-link">
        <div class="product-image-visual" style="background: linear-gradient(145deg, <?= $brandColors[0] ?> 0%, <?= $brandColors[1] ?> 100%);">
            <div class="product-icon" style="color: <?= $brandColors[2] ?>;">
                <?= $iconSvg ?>
            </div>
            <span class="brand-tag"><?= htmlspecialchars($product['brand']) ?></span>
            <span class="category-tag"><?= $catLabel ?></span>
            <?php if ($product['condition'] !== 'New with Tags'): ?>
                <span class="condition-tag"><?= htmlspecialchars($product['condition']) ?></span>
            <?php endif; ?>
            <?php if ($product['stock'] <= 1): ?>
                <span class="stock-tag">Only 1 left</span>
            <?php endif; ?>
            <div class="color-swatch-row">
                <span class="color-dot" style="background: <?= $swatchColors[0] ?>;"></span>
                <span class="color-dot" style="background: <?= $swatchColors[1] ?>;"></span>
                <span class="color-label"><?= htmlspecialchars($product['color']) ?></span>
            </div>
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
