<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MedusaApiService
{
    protected $baseUrl;
    protected $publishableKey;
    protected $httpClient;

    public function __construct()
    {
        $this->baseUrl = config('services.medusa.url');
        $this->publishableKey = config('services.medusa.publishable_key');

        // Initialize the HTTP client with the required header
        $this->httpClient = Http::withHeaders([
            'x-publishable-api-key' => $this->publishableKey,
        ]);
    }

    public function getProducts(array $query = [])
    {
        // Merge region_id from config if not provided
        $query['region_id'] = $query['region_id'] ?? config('services.medusa.region_id');

        $response = $this->httpClient->get("{$this->baseUrl}/products", $query);
        // logger($response->body()); // More useful than dumping entire response object
        $response->throw();
        return $response->json();
    }

    public function getProductByHandle(string $handle)
    {
        $regionId = config('services.medusa.region_id');
        $response = $this->httpClient->get("{$this->baseUrl}/products", [
            'handle' => $handle,
            'region_id' => $regionId,
        ]);
        // logger($response->json());
        $response->throw();
        $data = $response->json();

        return $data['products'][0] ?? null;
    }

    public function createCart(array $data = [])
    {
        $data['region_id'] = $data['region_id'] ?? config('services.medusa.region_id');
        $data['currency_code'] = $data['currency_code'] ?? 'eur';

        $response = $this->httpClient->post("{$this->baseUrl}/carts", $data);
        $response->throw();
        return $response->json();
    }

    // ✅ addToCart – no expand
    public function addToCart(string $cartId, string $variantId, int $quantity)
    {
        $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}/line-items", [
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);
        $response->throw();
        return $response->json();
    }

    public function getCart(string $cartId)
    {
        $response = $this->httpClient->get("{$this->baseUrl}/carts/{$cartId}");
        // logger($response->json());
        $response->throw();
        return $response->json();
    }

    public function updateLineItem(string $cartId, string $lineItemId, int $quantity)
    {
        $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}/line-items/{$lineItemId}", [
            'quantity' => $quantity,
        ]);
        $response->throw();
        return $response->json();
    }

    public function removeLineItem(string $cartId, string $lineItemId)
    {
        $response = $this->httpClient->delete("{$this->baseUrl}/carts/{$cartId}/line-items/{$lineItemId}");
        $response->throw();
        return $response->json();
    }

    public function updateCart(string $cartId, array $data)
    {
        $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}", $data);
        $response->throw();
        return $response->json();
    }

    // public function completeCart(string $cartId)
    // {
    //     $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}/complete", []);
    //     $response->throw();
    //     return $response->json();
    // }

    // public function createPaymentSessions(string $cartId)
    // {
    //     $url = "{$this->baseUrl}/carts/{$cartId}/payment-sessions";
    //     Log::debug('Creating payment sessions at URL:', ['url' => $url]);
    //     $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}/payment-sessions");
    //     $response->throw();
    //     return $response->json();
    // }

    // public function initiatePaymentSession(string $cartId, string $provider = 'manual')
    // {
    //     $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}/payment-sessions/{$provider}/initiate");
    //     $response->throw();
    //     return $response->json();
    // }

    public function addShippingMethod(string $cartId, string $shippingOptionId)
    {
        logger('herreeeeeeeeeee');
        $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}/shipping-methods", [
            'option_id' => $shippingOptionId,
        ]);
        $response->throw();
        return $response->json();
    }

    // Create payment sessions
    public function createPaymentSessions(string $cartId)
    {
        $url = "{$this->baseUrl}/carts/{$cartId}/payment-sessions";
        try {
            $response = $this->httpClient->timeout(30)->post($url);

            Log::debug('✅ Response Status', ['status' => $response->status()]);
            return $response->json();
        } catch (\Exception $e) {
            Log::debug('❌ Request Failed', [
                'url' => $url,
                'exception_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // Select payment session
    public function selectPaymentSession(string $cartId, string $providerId)
    {
        $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}/payment-session", [
            'provider_id' => $providerId,
        ]);
        $response->throw();
        return $response->json();
    }

    // Initiate payment session
    public function initiatePaymentSession(string $cartId, string $providerId)
    {
        $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}/payment-sessions/{$providerId}/initiate");
        $response->throw();
        return $response->json();
    }

    // Complete cart
    public function completeCart(string $cartId)
    {
        $response = $this->httpClient->post("{$this->baseUrl}/carts/{$cartId}/complete", []);
        $response->throw();
        return $response->json();
    }

    public function getShippingOptions(string $cartId)
    {
        // logger('herreeeeeeeeeee');
        $response = $this->httpClient->get("{$this->baseUrl}/shipping-options", [
            'cart_id' => $cartId,
        ]);
        // logger($response->json());
        $response->throw();
        return $response->json();
    }

    public function getRegions()
    {
        $response = $this->httpClient->get("{$this->baseUrl}/regions");
        $response->throw();
        return $response->json();
    }
}


// It looks like you’re calling the legacy Store API route on carts. In Medusa’s current payment flow, payment sessions are created on a payment collection, not directly on the cart. The recommended flow is:

//     Ensure the cart has a payment collection

//     If not, create one for the cart (via workflow or API), then use its ID to create sessions (Payment checkout flow and Accept payment flow).
//     (Payment Steps in Checkout Flow).

//     Create/initiate a payment session for that collection

//     Store API route: POST /store/payment-collections/{id}/payment-sessions (used by JS SDK’s initiatePaymentSession) (JS SDK initiatePaymentSession, Store API usage in checkout guide).
//     Core workflow to reuse server-side: createPaymentSessionsWorkflow with input { payment_collection_id, provider_id, customer_id?, data?, context? } (createPaymentSessionsWorkflow, workflow input).

// Important notes:

//     Some providers (e.g., Stripe) require a non-zero total; skip creating a session if total is 0 or use the Manual System provider (Troubleshooting zero total).
//     The session data field is for provider-specific public data; providers also store necessary info in session.data (Payment session data).
//     If you’re creating sessions programmatically (not via the Store API), you can call the Payment Module directly (advanced): createPaymentSession(payment_collection_id, { provider_id, currency_code, amount, data }) (Payment module createPaymentSession, example).

// How to adapt your Laravel call:

//     First create or fetch the cart’s payment collection ID.
//     Then POST to /store/payment-collections/{payment_collection_id}/payment-sessions with a JSON body like { "provider_id": "pp_stripe_stripe", "data": { ... } } (mirrors JS SDK flow) (JS SDK initiatePaymentSession, Checkout step).
