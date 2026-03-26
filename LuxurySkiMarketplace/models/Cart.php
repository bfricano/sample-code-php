<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Product.php';

class Cart
{
    /**
     * Initialize the cart session.
     */
    public static function init()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION[CART_SESSION_KEY])) {
            $_SESSION[CART_SESSION_KEY] = [];
        }
    }

    /**
     * Add an item to the cart.
     */
    public static function addItem($productId, $size, $quantity = 1)
    {
        self::init();
        $product = Product::findById($productId);
        if ($product === null || $product['stock'] < 1) {
            return ['success' => false, 'message' => 'Product not available'];
        }

        $cartKey = $productId . '_' . $size;

        if (isset($_SESSION[CART_SESSION_KEY][$cartKey])) {
            $newQty = $_SESSION[CART_SESSION_KEY][$cartKey]['quantity'] + $quantity;
            if ($newQty > $product['stock']) {
                return ['success' => false, 'message' => 'Insufficient stock'];
            }
            $_SESSION[CART_SESSION_KEY][$cartKey]['quantity'] = $newQty;
        } else {
            $_SESSION[CART_SESSION_KEY][$cartKey] = [
                'product_id' => $productId,
                'name'       => $product['name'],
                'brand'      => $product['brand'],
                'price'      => $product['price'],
                'size'       => $size,
                'quantity'   => $quantity,
                'condition'  => $product['condition'],
            ];
        }

        return ['success' => true, 'message' => 'Added to cart'];
    }

    /**
     * Remove an item from the cart.
     */
    public static function removeItem($cartKey)
    {
        self::init();
        if (isset($_SESSION[CART_SESSION_KEY][$cartKey])) {
            unset($_SESSION[CART_SESSION_KEY][$cartKey]);
            return true;
        }
        return false;
    }

    /**
     * Update item quantity.
     */
    public static function updateQuantity($cartKey, $quantity)
    {
        self::init();
        if (isset($_SESSION[CART_SESSION_KEY][$cartKey])) {
            if ($quantity <= 0) {
                return self::removeItem($cartKey);
            }
            $_SESSION[CART_SESSION_KEY][$cartKey]['quantity'] = $quantity;
            return true;
        }
        return false;
    }

    /**
     * Get all cart items.
     */
    public static function getItems()
    {
        self::init();
        return $_SESSION[CART_SESSION_KEY];
    }

    /**
     * Get the subtotal of all cart items.
     */
    public static function getSubtotal()
    {
        self::init();
        $subtotal = 0;
        foreach ($_SESSION[CART_SESSION_KEY] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        return round($subtotal, 2);
    }

    /**
     * Calculate tax amount.
     */
    public static function getTax()
    {
        return round(self::getSubtotal() * TAX_RATE, 2);
    }

    /**
     * Calculate shipping cost (free over threshold).
     */
    public static function getShipping()
    {
        $subtotal = self::getSubtotal();
        if ($subtotal === 0.0) {
            return 0;
        }
        return $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FLAT_RATE;
    }

    /**
     * Get grand total.
     */
    public static function getTotal()
    {
        return round(self::getSubtotal() + self::getTax() + self::getShipping(), 2);
    }

    /**
     * Get total number of items in cart.
     */
    public static function getItemCount()
    {
        self::init();
        $count = 0;
        foreach ($_SESSION[CART_SESSION_KEY] as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }

    /**
     * Clear the entire cart.
     */
    public static function clear()
    {
        self::init();
        $_SESSION[CART_SESSION_KEY] = [];
    }
}
