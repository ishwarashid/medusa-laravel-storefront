<?php

namespace App\Http\Controllers;

use App\Services\MedusaApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class CheckoutController extends Controller
{
    protected $medusaService;

    public function __construct(MedusaApiService $medusaService)
    {
        $this->medusaService = $medusaService;
    }

    public function show()
    {
        $cartId = Session::get('cart_id');
        if (!$cartId) {
            return redirect()->route('cart.index')->with('error', 'No active cart.');
        }

        try {
            $cartResponse = $this->medusaService->getCart($cartId);
            $cart = $cartResponse['cart'];

            $shippingOptionsResponse = $this->medusaService->getShippingOptions($cartId);
            $shippingOptions = $shippingOptionsResponse['shipping_options'] ?? [];

            $regionId = $cart['region_id'];
            $paymentProvidersResponse = $this->medusaService->getPaymentProvidersByRegion($regionId);
            $paymentProviders = $paymentProvidersResponse['payment_providers'] ?? [];

            // ✅ Default: no client secret
            $clientSecret = null;

            // ✅ Check if Stripe is available
            $stripeProvider = collect($paymentProviders)->firstWhere('id', 'pp_stripe_stripe');
            if ($stripeProvider) {
                try {
                    // Ensure payment collection exists
                    $this->medusaService->ensurePaymentCollection($cartId);

                    // 🔒 Guard: Check if Stripe session already exists
                    $existingSession = collect($cart['payment_collection']['payment_sessions'] ?? [])
                        ->firstWhere('provider_id', 'pp_stripe_stripe');

                    // ✅ Only create new session if none exists
                    if (!$existingSession) {
                        // Try to refresh payment collection first to clean up any stale sessions
                        $this->medusaService->refreshPaymentCollection($cartId);
                        
                        // ✅ Create session for Stripe provider with proper data
                        $this->medusaService->createPaymentSessionsForProvider($cartId, 'pp_stripe_stripe');

                        // ✅ Fetch updated cart to get client_secret
                        $updatedCart = $this->medusaService->getCart($cartId);
                        $paymentSession = collect($updatedCart['cart']['payment_collection']['payment_sessions'])
                            ->firstWhere('provider_id', 'pp_stripe_stripe');

                        $clientSecret = $paymentSession['data']['client_secret'] ?? null;
                    } else {
                        // ✅ Reuse existing client_secret
                        $clientSecret = $existingSession['data']['client_secret'] ?? null;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create payment session', ['error' => $e->getMessage()]);
                    // Even if we fail to create a payment session, we can still show the checkout page
                    // The client-side JavaScript will handle creating the session when needed
                }
            }
        } catch (\Exception $e) {
            Log::error('Checkout show failed: ' . $e->getMessage());
            Session::forget('cart_id');
            return redirect()->route('cart.index')->with('error', 'Cart not found.');
        }

        return view('checkout.show', compact('cart', 'shippingOptions', 'paymentProviders', 'clientSecret'));
    }

    public function submit(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'country' => 'required|string|size:2',
            'postal_code' => 'required|string|max:20',
            'shipping_option_id' => 'required|string',
            'payment_provider' => 'required|string',
        ]);

        Log::debug('💳 Payment provider selected', ['provider' => $request->payment_provider]);
        $cartId = Session::get('cart_id');
        if (!$cartId) {
            return redirect()->route('cart.index')->with('error', 'No active cart.');
        }

        try {
            // 1. Update cart with customer details
            $this->medusaService->updateCart($cartId, [
                'email' => $request->email,
                'shipping_address' => [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'address_1' => $request->address,
                    'city' => $request->city,
                    'country_code' => strtolower($request->country),
                    'province' => $request->state ?? '',
                    'postal_code' => $request->postal_code,
                ],
                'billing_address' => [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'address_1' => $request->address,
                    'city' => $request->city,
                    'country_code' => strtolower($request->country),
                    'province' => $request->state ?? '',
                    'postal_code' => $request->postal_code,
                ],
            ]);

            // 2. Add shipping method
            $this->medusaService->addShippingMethod($cartId, $request->shipping_option_id);

            // 3. Ensure payment collection exists
            $this->medusaService->ensurePaymentCollection($cartId);

            // 4. Complete the cart - this will process the payment that was handled client-side
            $result = $this->medusaService->completeCart($cartId);

            Log::debug('🎯 completeCart() Response', ['result' => $result]);

            if (is_array($result)) {
                // ✅ Order completed (e.g., manual payment)
                if (isset($result['type']) && $result['type'] === 'order') {
                    $orderId = $result['order']['id'] ?? null;
                    if ($orderId) {
                        Session::forget('cart_id'); // ✅ Clear the old cart
                        Session::put('last_order_id', $orderId);
                        return redirect()->route('checkout.confirmed', $orderId);
                    }
                }

                // 🔁 Redirect needed (e.g., Stripe)
                if (isset($result['type']) && $result['type'] === 'cart') {
                    // Stripe: Check for payment_url
                    $paymentUrl = $result['order']['payment_url'] ?? null;
                    if ($paymentUrl) {
                        Log::info('🚀 Redirecting to Stripe', ['url' => $paymentUrl]);
                        return redirect($paymentUrl);
                    }

                    // If no payment_url, check for error
                    if (isset($result['error'])) {
                        Log::error('💳 Payment Error', ['error' => $result['error']]);
                        return redirect()->route('checkout.show')->with('error', 'Payment failed: ' . $result['error']['message']);
                    }
                }
            }

            // 🛑 Fallback: No valid response
            return redirect()->route('checkout.show')->with('error', 'Checkout failed. Please try again.');
        } catch (\Exception $e) {
            Log::error('❌ Checkout failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()->route('checkout.show')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // Order confirmed page
    public function confirmed($orderId)
    {
        $lastOrderId = session('last_order_id');

        if ($lastOrderId !== $orderId) {
            Log::warning('Order confirmation mismatch', [
                'provided' => $orderId,
                'session' => $lastOrderId,
                'ip' => request()->ip(),
            ]);

            return redirect()->route('products.index')->with('error', 'Order not found.');
        }

        return view('checkout.confirmed', compact('orderId'));
    }

    // Create payment session for Stripe
    public function createPaymentSession(Request $request)
    {
        $cartId = Session::get('cart_id');
        if (!$cartId) {
            return response()->json(['error' => 'No active cart'], 400);
        }

        try {
            // Ensure payment collection exists
            $this->medusaService->ensurePaymentCollection($cartId);

            // Before creating new sessions, try to refresh the payment collection to clean up any stale sessions
            try {
                $this->medusaService->refreshPaymentCollection($cartId);
            } catch (\Exception $e) {
                // Continue even if refresh fails
            }

            // Create payment session for Stripe with proper data
            $this->medusaService->createPaymentSessionsForProvider($cartId, 'pp_stripe_stripe');

            // Fetch updated cart to get client_secret
            $cart = $this->medusaService->getCart($cartId);
            $paymentSession = collect($cart['cart']['payment_collection']['payment_sessions'])
                ->firstWhere('provider_id', 'pp_stripe_stripe');

            $clientSecret = $paymentSession['data']['client_secret'] ?? null;

            return response()->json(['client_secret' => $clientSecret]);
        } catch (\Exception $e) {
            Log::error('Failed to create payment session: ' . $e->getMessage());
            
            // Try once more with a fresh approach
            try {
                // Create a new payment collection
                $this->medusaService->createPaymentCollectionForCart($cartId);
                
                // Create payment session for Stripe with proper data
                $this->medusaService->createPaymentSessionsForProvider($cartId, 'pp_stripe_stripe');

                // Fetch updated cart to get client_secret
                $cart = $this->medusaService->getCart($cartId);
                $paymentSession = collect($cart['cart']['payment_collection']['payment_sessions'])
                    ->firstWhere('provider_id', 'pp_stripe_stripe');

                $clientSecret = $paymentSession['data']['client_secret'] ?? null;

                return response()->json(['client_secret' => $clientSecret]);
            } catch (\Exception $retryException) {
                Log::error('Retry failed to create payment session: ' . $retryException->getMessage());
                return response()->json(['error' => 'Failed to create payment session: ' . $e->getMessage()], 500);
            }
        }
    }
}
