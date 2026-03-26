<?php
require_once __DIR__ . '/../config/config.php';

class Product
{
    private static $products = null;

    /**
     * Load all products from the JSON data file.
     */
    public static function loadAll()
    {
        if (self::$products === null) {
            $json = file_get_contents(PRODUCTS_FILE);
            self::$products = json_decode($json, true) ?: [];
        }
        return self::$products;
    }

    /**
     * Find a product by its ID.
     */
    public static function findById($id)
    {
        $products = self::loadAll();
        foreach ($products as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }
        return null;
    }

    /**
     * Get products filtered by category.
     */
    public static function getByCategory($category)
    {
        $products = self::loadAll();
        return array_filter($products, function ($p) use ($category) {
            return $p['category'] === $category;
        });
    }

    /**
     * Get products filtered by brand.
     */
    public static function getByBrand($brand)
    {
        $products = self::loadAll();
        return array_filter($products, function ($p) use ($brand) {
            return strcasecmp($p['brand'], $brand) === 0;
        });
    }

    /**
     * Get featured products for the homepage.
     */
    public static function getFeatured()
    {
        $products = self::loadAll();
        return array_filter($products, function ($p) {
            return $p['featured'] === true;
        });
    }

    /**
     * Search products by name, brand, or description.
     */
    public static function search($query)
    {
        $products = self::loadAll();
        $query = strtolower($query);
        return array_filter($products, function ($p) use ($query) {
            return strpos(strtolower($p['name']), $query) !== false
                || strpos(strtolower($p['brand']), $query) !== false
                || strpos(strtolower($p['description']), $query) !== false;
        });
    }

    /**
     * Get all unique brands in the catalog.
     */
    public static function getBrands()
    {
        $products = self::loadAll();
        $brands = array_unique(array_column($products, 'brand'));
        sort($brands);
        return $brands;
    }

    /**
     * Check if a product is in stock.
     */
    public static function isInStock($id)
    {
        $product = self::findById($id);
        return $product !== null && $product['stock'] > 0;
    }
}
