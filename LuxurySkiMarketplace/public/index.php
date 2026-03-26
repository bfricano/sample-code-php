<?php
/**
 * Kypre - Luxury Ski Marketplace
 * Main entry point / front controller
 */
session_start();

require_once __DIR__ . '/../config/config.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

$validPages = ['home', 'catalog', 'product', 'cart', 'checkout'];

if (!in_array($page, $validPages, true)) {
    $page = 'home';
}

require_once __DIR__ . '/../views/' . $page . '.php';
