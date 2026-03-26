<?php
require_once __DIR__ . '/../../models/Cart.php';
$cartCount = Cart::getItemCount();
$categories = json_decode(CATEGORIES, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? MARKETPLACE_NAME ?> | Kypre</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <span class="logo-text">KYPRE</span>
                <span class="logo-tagline"><?= MARKETPLACE_TAGLINE ?></span>
            </a>
            <nav class="main-nav">
                <a href="index.php">Home</a>
                <a href="index.php?page=catalog">Shop All</a>
                <a href="index.php?page=catalog&category=goldbergh">Goldbergh</a>
                <a href="index.php?page=catalog&category=ski-collection">Ski Collection</a>
                <a href="index.php?page=catalog&category=deer-valley">Deer Valley</a>
                <a href="index.php?page=cart" class="cart-link">
                    Cart (<span id="cart-count"><?= $cartCount ?></span>)
                </a>
            </nav>
        </div>
    </header>
    <main class="main-content">
