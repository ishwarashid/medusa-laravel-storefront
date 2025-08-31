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

    // public function show()
    // {
    //     $cartId = Session::get('cart_id');
    //     Log::debug('Checkout: Cart ID from session', ['cart_id' => $cartId]);

    //     if (!$cartId) {
    //         return redirect()->route('cart.index')->with('error', 'No active cart.');
    //     }

    //     try {
    //         $cartResponse = $this->medusaService->getCart($cartId);
    //         $cart = $cartResponse['cart'];

    //         Log::debug('Checkout: Fetched cart successfully', ['cart_id' => $cart['id']]);

    //         // ✅ Get region ID
    //         $regionId = $cart['region_id'];

    //         // ✅ Get shipping options
    //         $shippingOptionsResponse = $this->medusaService->getShippingOptions($cartId);
    //         $shippingOptions = $shippingOptionsResponse['shipping_options'] ?? [];

    //         Log::debug('Checkout: Fetched shipping options', ['count' => count($shippingOptions)]);

    //         // ✅ Get payment providers by region
    //         $providersResponse = $this->medusaService->getPaymentProvidersByRegion($regionId);
    //         $paymentProviders = $providersResponse['payment_providers'] ?? [];
    //         logger($providersResponse);

    //         Log::debug('Checkout: Fetched payment providers', ['providers' => $paymentProviders]);

    //         return view('checkout.show', compact('cart', 'shippingOptions', 'paymentProviders'));
    //     } catch (\Exception $e) {
    //         Log::error('Checkout show failed', [
    //             'cart_id' => $cartId,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);

    //         Session::forget('cart_id');
    //         return redirect()->route('cart.index')->with('error', 'Unable to load checkout: ' . $e->getMessage());
    //     }
    // }

    // public function show(Request $request)
    // {
    //     $cartId = Session::get('cart_id');
    //     if (!$cartId) {
    //         return redirect()->route('cart.index')->with('error', 'No active cart.');
    //     }

    //     try {
    //         $cartResponse = $this->medusaService->getCart($cartId);
    //         $cart = $cartResponse['cart'];

    //         $shippingOptionsResponse = $this->medusaService->getShippingOptions($cartId);
    //         $shippingOptions = $shippingOptionsResponse['shipping_options'] ?? [];

    //         $paymentProvidersResponse = $this->medusaService->getPaymentProvidersByRegion($cart['region_id']);
    //         $paymentProviders = $paymentProvidersResponse['payment_providers'] ?? [];

    //         // Pass client_secret for Stripe (if cart has payment collection)
    //         $clientSecret = null;
    //         if ($request->payment_provider === 'pp_stripe_stripe' && $cart['payment_collection']) {
    //             $paymentSession = $this->medusaService->getPaymentSessionForProvider($cartId, 'pp_stripe_stripe');
    //             $clientSecret = $paymentSession['data']['client_secret'] ?? null;
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('Checkout show failed: ' . $e->getMessage());
    //         Session::forget('cart_id');
    //         return redirect()->route('cart.index')->with('error', 'Cart not found.');
    //     }

    //     return view('checkout.show', compact('cart', 'shippingOptions', 'paymentProviders', 'clientSecret'));
    // }

    // public function show()
    // {
    //     $cartId = Session::get('cart_id');
    //     if (!$cartId) {
    //         return redirect()->route('cart.index')->with('error', 'No active cart.');
    //     }

    //     try {
    //         $cartResponse = $this->medusaService->getCart($cartId);
    //         $cart = $cartResponse['cart'];

    //         $shippingOptionsResponse = $this->medusaService->getShippingOptions($cartId);
    //         $shippingOptions = $shippingOptionsResponse['shipping_options'] ?? [];

    //         $regionId = $cart['region_id'];
    //         $paymentProvidersResponse = $this->medusaService->getPaymentProvidersByRegion($regionId);
    //         $paymentProviders = $paymentProvidersResponse['payment_providers'] ?? [];

    //         // ✅ Always create payment session for Stripe if available
    //         $clientSecret = null;
    //         $stripeProvider = collect($paymentProviders)->firstWhere('id', 'pp_stripe_stripe');

    //         if ($stripeProvider) {
    //             // Ensure payment collection exists
    //             $this->medusaService->ensurePaymentCollection($cartId);

    //             // Create payment session for Stripe
    //             try {
    //                 $this->medusaService->createPaymentSessionsForProvider($cartId, 'pp_stripe_stripe');

    //                 // Fetch updated cart to get client_secret
    //                 $updatedCart = $this->medusaService->getCart($cartId);
    //                 $paymentSession = collect($updatedCart['cart']['payment_collection']['payment_sessions'])
    //                     ->firstWhere('provider_id', 'pp_stripe_stripe');

    //                 $clientSecret = $paymentSession['data']['client_secret'] ?? null;
    //             } catch (\Exception $e) {
    //                 Log::error('Failed to create Stripe payment session', ['error' => $e->getMessage()]);
    //             }
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('Checkout show failed: ' . $e->getMessage());
    //         Session::forget('cart_id');
    //         return redirect()->route('cart.index')->with('error', 'Cart not found.');
    //     }

    //     return view('checkout.show', compact('cart', 'shippingOptions', 'paymentProviders', 'clientSecret'));
    // }

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
                // Ensure payment collection exists
                $this->medusaService->ensurePaymentCollection($cartId);

                // 🔒 Guard: Check if Stripe session already exists
                $existingSession = collect($cart['payment_collection']['payment_sessions'] ?? [])
                    ->firstWhere('provider_id', 'pp_stripe_stripe');

                // ✅ Only create new session if none exists
                if (!$existingSession) {
                    try {
                        // ✅ Refresh to avoid "Could not delete" error
                        // $this->medusaService->refreshPaymentCollection($cartId);

                        // ✅ Create session
                        $this->medusaService->createPaymentSessionsForProvider($cartId, 'pp_stripe_stripe');

                        // ✅ Fetch updated cart to get client_secret
                        $updatedCart = $this->medusaService->getCart($cartId);
                        $paymentSession = collect($updatedCart['cart']['payment_collection']['payment_sessions'])
                            ->firstWhere('provider_id', 'pp_stripe_stripe');

                        $clientSecret = $paymentSession['data']['client_secret'] ?? null;
                    } catch (\Exception $e) {
                        $message = $e->getMessage();
                        if (str_contains($message, 'Could not delete all payment sessions')) {
                            Log::warning('Payment session conflict, creating new cart', ['cart_id' => $cartId]);

                            // Create new cart
                            $newCart = $this->medusaService->createCart();
                            Session::put('cart_id', $newCart['cart']['id']);

                            // Re-add items (you'll need to store them temporarily)
                            foreach ($cart['items'] as $item) {
                                $this->medusaService->addToCart($newCart['cart']['id'], $item['variant_id'], $item['quantity']);
                            }

                            // Redirect to checkout with new cart
                            return redirect()->route('checkout.show');
                        }

                        Log::error('Failed to create payment session', ['error' => $message]);
                        // Log::error('Failed to create Stripe payment session', [
                        //     'error' => $e->getMessage(),
                        //     'cart_id' => $cartId,
                        // ]);
                    }
                } else {
                    // ✅ Reuse existing client_secret
                    $clientSecret = $existingSession['data']['client_secret'] ?? null;
                }
            }
        } catch (\Exception $e) {
            Log::error('Checkout show failed: ' . $e->getMessage());
            Session::forget('cart_id');
            return redirect()->route('cart.index')->with('error', 'Cart not found.');
        }

        return view('checkout.show', compact('cart', 'shippingOptions', 'paymentProviders', 'clientSecret'));
    }

    // Submit checkout
    // public function submit(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //         'first_name' => 'required|string|max:255',
    //         'last_name' => 'required|string|max:255',
    //         'address' => 'required|string|max:500',
    //         'city' => 'required|string|max:255',
    //         'country' => 'required|string|size:2',
    //         'postal_code' => 'required|string|max:20',
    //         'shipping_option_id' => 'required|string',
    //         'payment_provider' => 'required|string',
    //     ]);
    //     Log::debug('Payment provider selected', ['provider' => $request->payment_provider]);
    //     $cartId = Session::get('cart_id');
    //     if (!$cartId) {
    //         return redirect()->route('cart.index')->with('error', 'No active cart.');
    //     }

    //     try {
    //         // 1. Update cart with email & address
    //         $this->medusaService->updateCart($cartId, [
    //             'email' => $request->email,
    //             'shipping_address' => [
    //                 'first_name' => $request->first_name,
    //                 'last_name' => $request->last_name,
    //                 'address_1' => $request->address,
    //                 'city' => $request->city,
    //                 'country_code' => strtolower($request->country),
    //                 'province' => $request->state ?? '',
    //                 'postal_code' => $request->postal_code,
    //             ],
    //         ]);

    //         // 2. Add shipping method
    //         $this->medusaService->addShippingMethod($cartId, $request->shipping_option_id);

    //         // 3. Ensure payment collection exists
    //         $this->medusaService->ensurePaymentCollection($cartId);

    //         // 4. ✅ Create payment sessions (plural) — this creates the session
    //         // $this->medusaService->createPaymentSessions($cartId); // ← No provider here
    //         // $this->medusaService->createPaymentSessions($cartId);
    //         $this->medusaService->createPaymentSessionsForProvider($cartId, $request->payment_provider);

    //         $cart = $this->medusaService->getCart($cartId);
    //         $paymentSession = collect($cart['cart']['payment_collection']['payment_sessions'])
    //             ->firstWhere('provider_id', 'pp_stripe_stripe');
    //         logger($paymentSession);
    //         $paymentUrl = "https://checkout.stripe.com/pay/{$paymentSession['data']['id']}?client_secret={$paymentSession['data']['client_secret']}";
    //         // 5. ✅ Select the user's choice
    //         // $this->medusaService->selectPaymentSession($cartId, $request->payment_provider);

    //         // 6. ✅ Initiate the selected session
    //         // $this->medusaService->initiatePaymentSession($cartId, $request->payment_provider);
    //         // $initiateResult = $this->medusaService->initiatePaymentSession($cartId, $request->payment_provider);

    //         // $paymentUrl = $initiateResult['payment_session']['data']['url'] ?? null;
    //         // logger($paymentUrl);
    //         // if ($paymentUrl) {
    //         //     return redirect($paymentUrl);
    //         // }

    //         // 7. ✅ Complete the cart
    //         $result = $this->medusaService->completeCart($cartId);
    //         $cart = $this->medusaService->getCart($cartId);
    //         Log::debug('Final cart state before complete', [
    //             'cart_id' => $cartId,
    //             'payment_collection' => $cart['cart']['payment_collection'] ?? 'missing',
    //             'payment_sessions' => $cart['cart']['payment_collection']['payment_sessions'] ?? [],
    //             'total' => $cart['cart']['total'],
    //             'region_id' => $cart['cart']['region_id'],
    //         ]);
    //         logger('...................');
    //         logger($result);
    //         if (is_array($result)) {
    //             if (isset($result['type']) && $result['type'] === 'order') {
    //                 // ✅ Fix: Use 'order', not 'data'
    //                 $orderId = $result['order']['id'] ?? null;
    //                 if ($orderId) {
    //                     Session::forget('cart_id');
    //                     Session::put('last_order_id', $orderId);
    //                     return redirect()->route('checkout.confirmed', $orderId);
    //                 }
    //             }

    //             if (isset($result['type']) && $result['type'] === 'cart') {
    //                 // ✅ Fix: Use 'order', not 'data'
    //                 $paymentUrl = $result['order']['payment_url'] ?? null;
    //                 if ($paymentUrl) {
    //                     return redirect($paymentUrl);
    //                 }
    //             }
    //         }

    //         return redirect()->route('checkout.show')->with('error', 'Checkout failed. Please try again.');
    //     } catch (\Exception $e) {
    //         Log::error('Checkout failed: ' . $e->getMessage());
    //         return redirect()->route('checkout.show')->with('error', 'Error: ' . $e->getMessage());
    //     }
    // }

    // public function submit(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //         'first_name' => 'required|string|max:255',
    //         'last_name' => 'required|string|max:255',
    //         'address' => 'required|string|max:500',
    //         'city' => 'required|string|max:255',
    //         'country' => 'required|string|size:2',
    //         'postal_code' => 'required|string|max:20',
    //         'shipping_option_id' => 'required|string',
    //         'payment_provider' => 'required|string',
    //     ]);

    //     Log::debug('💳 Payment provider selected', ['provider' => $request->payment_provider]);

    //     $cartId = Session::get('cart_id');
    //     if (!$cartId) {
    //         return redirect()->route('cart.index')->with('error', 'No active cart.');
    //     }

    //     try {
    //         // 1. Update cart
    //         $this->medusaService->updateCart($cartId, [
    //             'email' => $request->email,
    //             'shipping_address' => [
    //                 'first_name' => $request->first_name,
    //                 'last_name' => $request->last_name,
    //                 'address_1' => $request->address,
    //                 'city' => $request->city,
    //                 'country_code' => strtolower($request->country),
    //                 'province' => $request->state ?? '',
    //                 'postal_code' => $request->postal_code,
    //             ],
    //         ]);

    //         // 2. Add shipping method
    //         $this->medusaService->addShippingMethod($cartId, $request->shipping_option_id);

    //         // 3. Ensure payment collection
    //         $this->medusaService->ensurePaymentCollection($cartId);

    //         // 4. Create payment session
    //         $this->medusaService->createPaymentSessionsForProvider($cartId, $request->payment_provider);

    //         // 🔍 Debug: Check payment session
    //         $cart = $this->medusaService->getCart($cartId);
    //         $paymentSession = collect($cart['cart']['payment_collection']['payment_sessions'])
    //             ->firstWhere('provider_id', $request->payment_provider);

    //         Log::debug('💳 Payment Session Created', [
    //             'provider' => $paymentSession['provider_id'] ?? 'none',
    //             'status' => $paymentSession['status'] ?? 'unknown',
    //             'has_data' => !empty($paymentSession['data']),
    //         ]);

    //         // ✅ Final debug before complete
    //         Log::debug('✅ Final Cart Before Complete', [
    //             'cart_id' => $cartId,
    //             'total' => $cart['cart']['total'],
    //             'payment_sessions' => $cart['cart']['payment_collection']['payment_sessions'] ?? [],
    //         ]);

    //         // 5. Complete cart
    //         $result = $this->medusaService->completeCart($cartId);

    //         Log::debug('🎯 completeCart() Response', ['result' => $result]);

    //         if (is_array($result)) {
    //             if (isset($result['type']) && $result['type'] === 'order') {
    //                 $orderId = $result['order']['id'] ?? null;
    //                 if ($orderId) {
    //                     Session::forget('cart_id');
    //                     Session::put('last_order_id', $orderId);
    //                     return redirect()->route('checkout.confirmed', $orderId);
    //                 }
    //             }

    //             if (isset($result['type']) && $result['type'] === 'cart') {
    //                 $paymentUrl = $result['order']['payment_url'] ?? null;
    //                 if ($paymentUrl) {
    //                     Log::info('🚀 Redirecting to Stripe', ['url' => $paymentUrl]);
    //                     return redirect($paymentUrl);
    //                 }

    //                 if (isset($result['error'])) {
    //                     Log::error('💳 Payment Error', ['error' => $result['error']]);
    //                 }
    //             }
    //         }

    //         return redirect()->route('checkout.show')->with('error', 'Checkout failed. Please try again.');
    //     } catch (\Exception $e) {
    //         Log::error('❌ Checkout failed', [
    //             'error' => $e->getMessage(),
    //             'file' => $e->getFile(),
    //             'line' => $e->getLine(),
    //         ]);
    //         return redirect()->route('checkout.show')->with('error', 'Error: ' . $e->getMessage());
    //     }
    // }

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

        $cart = $this->medusaService->getCart($cartId);

        if ($cart['cart']['completed_at']) {
            Session::forget('cart_id');
            return redirect()->route('cart.index')->with('error', 'This order has already been completed.');
        }

        try {
            // 1. Update cart
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
            ]);

            // 2. Add shipping method
            $this->medusaService->addShippingMethod($cartId, $request->shipping_option_id);

            // 3. Ensure payment collection exists
            $this->medusaService->ensurePaymentCollection($cartId);

            // $this->medusaService->refreshPaymentCollection($cartId);
            // 4. Create payment session for selected provider
            // $this->medusaService->createPaymentSessionsForProvider($cartId, $request->payment_provider);

            // 🔍 Debug: Fetch cart to inspect payment session
            $cart = $this->medusaService->getCart($cartId);
            // $existingSession = collect($cart['cart']['payment_collection']['payment_sessions'])
            //     ->firstWhere('provider_id', $request->payment_provider);

            // if (!$existingSession) {
            //     $this->medusaService->createPaymentSessionsForProvider($cartId, $request->payment_provider);
            // }
            // $paymentSession = collect($cart['cart']['payment_collection']['payment_sessions'])
            //     ->firstWhere('provider_id', $request->payment_provider);

            // Log::debug('💳 Payment Session Created', [
            //     'provider' => $paymentSession['provider_id'] ?? 'none',
            //     'status' => $paymentSession['status'] ?? 'unknown',
            //     'has_client_secret' => !empty($paymentSession['data']['client_secret']),
            // ]);

            // 5. Complete the cart
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

            $newCart = $this->medusaService->createCart();
            Session::put('cart_id', $newCart['cart']['id']);
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
}
