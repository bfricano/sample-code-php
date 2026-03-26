<?php
require_once __DIR__ . '/../config/config.php';

class Order
{
    /**
     * Create a new order record.
     */
    public static function create($orderData)
    {
        $orders = self::loadAll();

        $order = [
            'id'           => 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'date'         => date('Y-m-d H:i:s'),
            'customer'     => $orderData['customer'],
            'shipping'     => $orderData['shipping'],
            'items'        => $orderData['items'],
            'subtotal'     => $orderData['subtotal'],
            'tax'          => $orderData['tax'],
            'shipping_cost'=> $orderData['shipping_cost'],
            'total'        => $orderData['total'],
            'payment'      => $orderData['payment'],
            'status'       => 'confirmed',
        ];

        $orders[] = $order;
        file_put_contents(ORDERS_FILE, json_encode($orders, JSON_PRETTY_PRINT));

        return $order;
    }

    /**
     * Load all orders.
     */
    public static function loadAll()
    {
        if (!file_exists(ORDERS_FILE)) {
            return [];
        }
        $json = file_get_contents(ORDERS_FILE);
        return json_decode($json, true) ?: [];
    }

    /**
     * Find an order by ID.
     */
    public static function findById($orderId)
    {
        $orders = self::loadAll();
        foreach ($orders as $order) {
            if ($order['id'] === $orderId) {
                return $order;
            }
        }
        return null;
    }
}
