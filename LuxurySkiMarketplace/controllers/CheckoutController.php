<?php
require_once __DIR__ . '/../vendor_bridge.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../config/config.php';

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class CheckoutController
{
    /**
     * Process a checkout with Authorize.Net payment.
     */
    public static function processPayment($postData)
    {
        $items = Cart::getItems();
        if (empty($items)) {
            return ['success' => false, 'message' => 'Your cart is empty.'];
        }

        // Validate required fields
        $required = [
            'first_name', 'last_name', 'email', 'address',
            'city', 'state', 'zip', 'card_number',
            'card_expiry', 'card_cvv',
        ];
        foreach ($required as $field) {
            if (empty($postData[$field])) {
                return ['success' => false, 'message' => "Please fill in all required fields. Missing: $field"];
            }
        }

        // Set up API credentials
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName(AUTHORIZENET_API_LOGIN_ID);
        $merchantAuthentication->setTransactionKey(AUTHORIZENET_TRANSACTION_KEY);
        $refId = 'ref' . time();

        // Create payment data
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($postData['card_number']);
        $creditCard->setExpirationDate($postData['card_expiry']);
        $creditCard->setCardCode($postData['card_cvv']);
        $payment = new AnetAPI\PaymentType();
        $payment->setCreditCard($creditCard);

        // Order info
        $order = new AnetAPI\OrderType();
        $order->setDescription('Kypre Luxury Ski Marketplace');

        // Line items
        $lineItems = [];
        foreach ($items as $item) {
            $lineItem = new AnetAPI\LineItemType();
            $lineItem->setItemId($item['product_id']);
            $lineItem->setName(substr($item['name'], 0, 31));
            $lineItem->setDescription($item['brand'] . ' - ' . $item['size']);
            $lineItem->setQuantity($item['quantity']);
            $lineItem->setUnitPrice($item['price']);
            $lineItems[] = $lineItem;
        }

        // Tax
        $tax = new AnetAPI\ExtendedAmountType();
        $tax->setName('Sales Tax');
        $tax->setAmount(Cart::getTax());
        $tax->setDescription('State sales tax');

        // Customer
        $customer = new AnetAPI\CustomerDataType();
        $customer->setEmail($postData['email']);

        // Shipping address
        $shipTo = new AnetAPI\NameAndAddressType();
        $shipTo->setFirstName($postData['first_name']);
        $shipTo->setLastName($postData['last_name']);
        $shipTo->setAddress($postData['address']);
        $shipTo->setCity($postData['city']);
        $shipTo->setState($postData['state']);
        $shipTo->setZip($postData['zip']);
        $shipTo->setCountry('USA');

        // Billing address
        $billTo = new AnetAPI\CustomerAddressType();
        $billTo->setFirstName($postData['first_name']);
        $billTo->setLastName($postData['last_name']);
        $billTo->setAddress($postData['address']);
        $billTo->setCity($postData['city']);
        $billTo->setState($postData['state']);
        $billTo->setZip($postData['zip']);
        $billTo->setCountry('USA');

        // Build transaction
        $transactionRequest = new AnetAPI\TransactionRequestType();
        $transactionRequest->setTransactionType('authCaptureTransaction');
        $transactionRequest->setAmount(Cart::getTotal());
        $transactionRequest->setPayment($payment);
        $transactionRequest->setOrder($order);
        foreach ($lineItems as $li) {
            $transactionRequest->addToLineItems($li);
        }
        $transactionRequest->setTax($tax);
        $transactionRequest->setCustomer($customer);
        $transactionRequest->setBillTo($billTo);
        $transactionRequest->setShipTo($shipTo);

        // Execute transaction
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse(
            \net\authorize\api\constants\ANetEnvironment::SANDBOX
        );

        if ($response !== null) {
            $tresponse = $response->getTransactionResponse();
            if ($tresponse !== null && $tresponse->getResponseCode() == '1') {
                // Payment successful — save order
                $orderRecord = Order::create([
                    'customer' => [
                        'first_name' => $postData['first_name'],
                        'last_name'  => $postData['last_name'],
                        'email'      => $postData['email'],
                    ],
                    'shipping' => [
                        'address' => $postData['address'],
                        'city'    => $postData['city'],
                        'state'   => $postData['state'],
                        'zip'     => $postData['zip'],
                    ],
                    'items'         => $items,
                    'subtotal'      => Cart::getSubtotal(),
                    'tax'           => Cart::getTax(),
                    'shipping_cost' => Cart::getShipping(),
                    'total'         => Cart::getTotal(),
                    'payment'       => [
                        'auth_code' => $tresponse->getAuthCode(),
                        'trans_id'  => $tresponse->getTransId(),
                    ],
                ]);

                Cart::clear();

                return [
                    'success'   => true,
                    'message'   => 'Payment successful!',
                    'order'     => $orderRecord,
                    'auth_code' => $tresponse->getAuthCode(),
                    'trans_id'  => $tresponse->getTransId(),
                ];
            }

            $errorMessages = $tresponse ? $tresponse->getErrors() : null;
            $errorText = 'Transaction failed.';
            if ($errorMessages) {
                $errorText = $errorMessages[0]->getErrorText();
            }
            return ['success' => false, 'message' => $errorText];
        }

        return ['success' => false, 'message' => 'No response from payment gateway.'];
    }
}
