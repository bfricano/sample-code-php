<?php
require_once __DIR__ . '/../models/Product.php';
$featured = Product::getFeatured();
$pageTitle = 'Home';
require_once __DIR__ . '/partials/header.php';
?>

<section class="hero" style="background-image: linear-gradient(rgba(26,26,46,0.55), rgba(26,26,46,0.7)), url('https://images.unsplash.com/photo-1551524559-8af4e6624178?w=1600&h=900&fit=crop&q=80');">
    <div class="hero-content">
        <h1>Deer Valley 2034 Winter Olympics</h1>
        <p class="hero-subtitle">The world's most exclusive ski resort welcomes the 2034 Winter Olympic Games. Shop Kypre's curated luxury ski fashion to look your best on and off the slopes of Deer Valley, Utah.</p>
        <div class="hero-actions">
            <a href="index.php?page=catalog&category=goldbergh" class="btn btn-primary">Shop Goldbergh</a>
            <a href="index.php?page=catalog&category=deer-valley" class="btn btn-primary">Deer Valley Collection</a>
            <a href="index.php?page=catalog&category=ski-collection" class="btn btn-secondary">Browse the Collection</a>
        </div>
    </div>
</section>

<section class="collections-intro">
    <div class="collection-card">
        <div class="collection-icon">&#9830;</div>
        <h3>Goldbergh Luxury</h3>
        <p>New-with-tags pieces from the Dutch luxury ski brand known for glamorous designs and impeccable quality.</p>
        <a href="index.php?page=catalog&category=goldbergh" class="btn btn-outline">Explore</a>
    </div>
    <div class="collection-card">
        <div class="collection-icon">&#9968;</div>
        <h3>Deer Valley Collection</h3>
        <p>Women's luxury ski fashion curated for Deer Valley's refined slopes. From Silver Lake Lodge to Empire Canyon, dress the part.</p>
        <a href="index.php?page=catalog&category=deer-valley" class="btn btn-outline">Explore</a>
    </div>
    <div class="collection-card">
        <div class="collection-icon">&#10052;</div>
        <h3>The Ski Collection</h3>
        <p>Pre-loved luxury ski wear from top-tier brands. Each piece hand-selected from seasons in Verbier, Courchevel, and St. Moritz.</p>
        <a href="index.php?page=catalog&category=ski-collection" class="btn btn-outline">Explore</a>
    </div>
</section>

<section class="featured-section">
    <h2>Featured Pieces</h2>
    <div class="product-grid">
        <?php foreach ($featured as $product): ?>
            <?php include __DIR__ . '/partials/product_card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="deer-valley-promo" style="background-image: linear-gradient(rgba(26,26,46,0.6), rgba(15,52,96,0.75)), url('https://images.unsplash.com/photo-1605540436563-5bca919ae766?w=1600&h=900&fit=crop&q=80');">
    <div class="promo-content">
        <h2>Women's Ski Fashion at Deer Valley</h2>
        <p>Deer Valley Resort is synonymous with elegance -- and your ski wardrobe should match. Kypre's Deer Valley collection brings together the world's finest women's ski fashion brands, from Goldbergh's glamorous Dutch designs to Fusalp's French precision tailoring. Whether you're carving Lady Morgan or warming up at the Mariposa, arrive in style.</p>
        <a href="index.php?page=catalog&category=deer-valley" class="btn btn-primary">Shop the Deer Valley Edit</a>
    </div>
</section>

<section class="brand-story">
    <h2>The Kypre Story</h2>
    <p>Kypre was born from a passion for luxury women's ski fashion. When Kylan's girlfriend decided to refresh her designer ski wardrobe, we saw an opportunity to share these exquisite pieces with fellow ski enthusiasts who appreciate craftsmanship and style. Based in the heart of Deer Valley country, we're proud to offer brand-new Goldbergh pieces, a curated Deer Valley collection, and pre-loved designer ski wear from the world's most exclusive resorts.</p>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
