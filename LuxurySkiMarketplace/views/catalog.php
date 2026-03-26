<?php
require_once __DIR__ . '/../models/Product.php';

$category = isset($_GET['category']) ? $_GET['category'] : null;
$brand = isset($_GET['brand']) ? $_GET['brand'] : null;
$search = isset($_GET['q']) ? $_GET['q'] : null;
$categories = json_decode(CATEGORIES, true);

if ($search) {
    $products = Product::search($search);
    $pageTitle = 'Search: ' . htmlspecialchars($search);
} elseif ($category) {
    $products = Product::getByCategory($category);
    $pageTitle = isset($categories[$category]) ? $categories[$category] : 'Shop';
} elseif ($brand) {
    $products = Product::getByBrand($brand);
    $pageTitle = htmlspecialchars($brand);
} else {
    $products = Product::loadAll();
    $pageTitle = 'All Products';
}

$brands = Product::getBrands();
require_once __DIR__ . '/partials/header.php';
?>

<section class="catalog-page">
    <div class="catalog-header">
        <h1><?= $pageTitle ?></h1>
        <p class="results-count"><?= count($products) ?> piece<?= count($products) !== 1 ? 's' : '' ?> available</p>
    </div>

    <div class="catalog-layout">
        <aside class="catalog-sidebar">
            <h3>Categories</h3>
            <ul class="filter-list">
                <li><a href="index.php?page=catalog" class="<?= !$category && !$brand ? 'active' : '' ?>">All Products</a></li>
                <?php foreach ($categories as $key => $label): ?>
                <li><a href="index.php?page=catalog&category=<?= $key ?>" class="<?= $category === $key ? 'active' : '' ?>"><?= $label ?></a></li>
                <?php endforeach; ?>
            </ul>

            <h3>Brands</h3>
            <ul class="filter-list">
                <?php foreach ($brands as $b): ?>
                <li><a href="index.php?page=catalog&brand=<?= urlencode($b) ?>" class="<?= $brand === $b ? 'active' : '' ?>"><?= htmlspecialchars($b) ?></a></li>
                <?php endforeach; ?>
            </ul>

            <h3>Search</h3>
            <form method="GET" action="index.php" class="search-form">
                <input type="hidden" name="page" value="catalog">
                <input type="text" name="q" placeholder="Search pieces..." value="<?= htmlspecialchars($search ?? '') ?>">
                <button type="submit" class="btn btn-small">Search</button>
            </form>
        </aside>

        <div class="catalog-products">
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <p>No products found. Try a different search or browse our collections.</p>
                    <a href="index.php?page=catalog" class="btn btn-primary">View All</a>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image-placeholder">
                            <span class="brand-tag"><?= htmlspecialchars($product['brand']) ?></span>
                            <?php if ($product['condition'] !== 'New with Tags'): ?>
                                <span class="condition-tag"><?= htmlspecialchars($product['condition']) ?></span>
                            <?php endif; ?>
                            <?php if ($product['stock'] <= 1): ?>
                                <span class="stock-tag">Only 1 left</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-brand"><?= htmlspecialchars($product['brand']) ?></p>
                            <p class="product-color"><?= htmlspecialchars($product['color']) ?></p>
                            <p class="product-price">$<?= number_format($product['price'], 2) ?></p>
                            <a href="index.php?page=product&id=<?= $product['id'] ?>" class="btn btn-small">View Details</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
