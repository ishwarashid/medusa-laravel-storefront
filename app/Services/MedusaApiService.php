<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MedusaApiService
{
    protected $baseUrl;
    protected $publishableKey;

    public function __construct()
    {
        // Ensure clean base URL with trailing slash
        $this->baseUrl = rtrim(trim(config('services.medusa.url')), '/') . '/';
        $this->publishableKey = trim(config('services.medusa.publishable_key'));
    }

    /**
     * Make HTTP request using raw cURL to avoid Laravel Http client issues
     */
    protected function request($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . ltrim($endpoint, '/');

        // For GET requests, append data as query params
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null; // No body for GET
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-publishable-api-key: ' . $this->publishableKey,
            'Content-Type: application/json',
            'User-Agent: Laravel-Medusa/1.0',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data ? json_encode($data) : '');
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: $error");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $message = $decoded['message'] ?? $decoded['error'] ?? $response;
            throw new \Exception("Medusa API Error $httpCode: $message", $httpCode);
        }

        return $decoded;
    }

    // --- Public Methods ---

    public function getProducts(array $query = [])
    {
        $query['region_id'] = $query['region_id'] ?? config('services.medusa.region_id');
        return $this->request('GET', 'products?' . http_build_query($query));
    }

    public function getProductByHandle(string $handle)
    {
        $regionId = config('services.medusa.region_id');
        $query = http_build_query([
            'handle' => $handle,
            'region_id' => $regionId,
        ]);

        $data = $this->request('GET', "products?$query");
        return $data['products'][0] ?? null;
    }

    public function createCart(array $data = [])
    {
        $data['region_id'] = $data['region_id'] ?? config('services.medusa.region_id');
        $data['currency_code'] = $data['currency_code'] ?? 'eur';
        return $this->request('POST', 'carts', $data);
    }

    public function addToCart(string $cartId, string $variantId, int $quantity)
    {
        return $this->request('POST', "carts/{$cartId}/line-items", [
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);
    }

    public function getCart(string $cartId)
    {
        return $this->request('GET', "carts/{$cartId}");
    }

    public function updateLineItem(string $cartId, string $lineItemId, int $quantity)
    {
        return $this->request('POST', "carts/{$cartId}/line-items/{$lineItemId}", [
            'quantity' => $quantity,
        ]);
    }

    public function removeLineItem(string $cartId, string $lineItemId)
    {
        return $this->request('DELETE', "carts/{$cartId}/line-items/{$lineItemId}");
    }

    public function updateCart(string $cartId, array $data)
    {
        return $this->request('POST', "carts/{$cartId}", $data);
    }

    public function addShippingMethod(string $cartId, string $shippingOptionId)
    {
        return $this->request('POST', "carts/{$cartId}/shipping-methods", [
            'option_id' => $shippingOptionId,
        ]);
    }

    public function getShippingOptions(string $cartId)
    {
        $query = http_build_query(['cart_id' => $cartId]);
        return $this->request('GET', "shipping-options?$query");
    }

    public function getRegions()
    {
        return $this->request('GET', 'regions');
    }

    public function ensurePaymentCollection(string $cartId)
    {
        $cart = $this->getCart($cartId);

        if (!empty($cart['cart']['payment_collection'])) {
            return $cart['cart']['payment_collection'];
        }

        // Create payment collection if not exists
        $response = $this->createPaymentCollectionForCart($cartId);
        return $response['payment_collection'];
    }

    public function completeCart(string $cartId)
    {
        return $this->request('POST', "carts/{$cartId}/complete", []);
    }

    public function createPaymentCollectionForCart(string $cartId)
    {
        return $this->request('POST', 'payment-collections', [
            'cart_id' => $cartId,
        ]);
    }

    public function getPaymentProvidersByRegion(string $regionId)
    {
        return $this->request('GET', 'payment-providers', [
            'region_id' => $regionId,
        ]);
    }

    public function createPaymentSessionsForProvider(string $cartId, string $providerId)
    {
        // First ensure payment collection exists
        $collection = $this->ensurePaymentCollection($cartId);
        $collectionId = $collection['id'];

        // Before creating new sessions, try to refresh the payment collection to clean up any stale sessions
        try {
            $this->refreshPaymentCollection($cartId);
        } catch (\Exception $e) {
            // If refresh fails, continue anyway
            \Illuminate\Support\Facades\Log::warning('Failed to refresh payment collection: ' . $e->getMessage());
        }

        // Create payment session for the specific provider with proper data
        return $this->request('POST', "payment-collections/{$collectionId}/payment-sessions", [
            'provider_id' => $providerId,
            'data' => [
                'setup_future_usage' => 'off_session',
            ],
        ]);
    }

    public function getPaymentSessionForProvider(string $cartId, string $providerId)
    {
        $cart = $this->getCart($cartId);
        
        if (empty($cart['cart']['payment_collection']['payment_sessions'])) {
            return null;
        }
        
        $paymentSession = collect($cart['cart']['payment_collection']['payment_sessions'])
            ->firstWhere('provider_id', $providerId);

        return $paymentSession;
    }

    public function refreshPaymentCollection(string $cartId)
    {
        try {
            // First ensure payment collection exists
            $collection = $this->ensurePaymentCollection($cartId);
            $collectionId = $collection['id'];

            // Refresh the payment collection
            return $this->request('POST', "payment-collections/{$collectionId}/refresh", []);
        } catch (\Exception $e) {
            // Log the error but don't throw it
            \Illuminate\Support\Facades\Log::warning('Failed to refresh payment collection: ' . $e->getMessage());
            return null;
        }
    }
}
