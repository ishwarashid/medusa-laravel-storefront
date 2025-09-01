<?php

namespace App\Http\Controllers;

use App\Services\MedusaApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class CartController extends Controller
{
    protected $medusaService;

    public function __construct(MedusaApiService $medusaService)
    {
        $this->medusaService = $medusaService;
    }

    // Show cart page
    public function index()
    {
        $cartId = Session::get('cart_id');
        $cart = null;

        if ($cartId) {
            try {
                $cartResponse = $this->medusaService->getCart($cartId);
                $cart = $cartResponse['cart'];
            } catch (\Exception $e) {
                // Cart not found or expired
                Log::warning("Cart not found: " . $e->getMessage());
                Session::forget('cart_id');
            }
        }

        return view('cart.index', compact('cart'));
    }

    // Add item to cart
    public function addToCart(Request $request)
    {
        $request->validate([
            'variant_id' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        $cartId = Session::get('cart_id');

        // Create a new cart if one doesn't exist
        if (!$cartId) {
            try {
                $regionId = config('services.medusa.region_id');
                $cartData = $this->medusaService->createCart([
                    'region_id' => $regionId,
                    'currency_code' => 'eur',
                ]);

                $cartId = $cartData['cart']['id'];
                Session::put('cart_id', $cartId);
            } catch (\Exception $e) {
                Log::error("Failed to create cart: " . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to create cart.');
            }
        }

        try {
            // Add item to cart
            $this->medusaService->addToCart($cartId, $request->variant_id, $request->quantity);

            // Update cart count in session
            $cart = $this->medusaService->getCart($cartId);
            $itemCount = collect($cart['cart']['items'])->sum('quantity');
            Session::put('cart_count', $itemCount);

            return redirect()->back()->with('success', 'Item added to cart!');
        } catch (\Exception $e) {
            Log::error("Add to cart failed: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to add item to cart.');
        }
    }

    // Update line item quantity
    public function updateLineItem(Request $request, $lineItemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cartId = Session::get('cart_id');

        if (!$cartId) {
            return redirect()->back()->with('error', 'No active cart.');
        }

        try {
            $this->medusaService->updateLineItem($cartId, $lineItemId, $request->quantity);
            
            // Update cart count in session
            $cart = $this->medusaService->getCart($cartId);
            $itemCount = collect($cart['cart']['items'])->sum('quantity');
            Session::put('cart_count', $itemCount);
            
            return redirect()->route('cart.index')->with('success', 'Cart updated.');
        } catch (\Exception $e) {
            Log::error("Failed to update cart item: " . $e->getMessage());
            return redirect()->route('cart.index')->with('error', 'Failed to update item.');
        }
    }

    // Remove line item
    public function removeLineItem($lineItemId)
    {
        $cartId = Session::get('cart_id');

        if (!$cartId) {
            return redirect()->back()->with('error', 'No active cart.');
        }

        try {
            $this->medusaService->removeLineItem($cartId, $lineItemId);
            
            // Update cart count in session
            $cart = $this->medusaService->getCart($cartId);
            $itemCount = collect($cart['cart']['items'])->sum('quantity');
            Session::put('cart_count', $itemCount);
            
            return redirect()->route('cart.index')->with('success', 'Item removed from cart.');
        } catch (\Exception $e) {
            Log::error("Failed to remove cart item: " . $e->getMessage());
            return redirect()->route('cart.index')->with('error', 'Failed to remove item.');
        }
    }
}
