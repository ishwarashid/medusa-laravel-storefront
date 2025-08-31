<?php

// app/Http/Controllers/CartController.php

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
        logger('here');
        $cartId = Session::get('cart_id');
        logger($cartId);
        if (!$cartId) {
            $regionId = config('services.medusa.region_id');
            $cartData = $this->medusaService->createCart([
                'region_id' => $regionId,
                'currency_code' => 'eur',
            ]);

            $cartId = $cartData['cart']['id'];
            Session::put('cart_id', $cartId);
        }

        try {
            $this->medusaService->addToCart($cartId, $request->variant_id, $request->quantity);

            // ✅ Now fetch the updated cart to get item count
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
            return redirect()->route('cart.index')->with('success', 'Cart updated.');
        } catch (\Exception $e) {
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
            return redirect()->route('cart.index')->with('success', 'Item removed from cart.');
        } catch (\Exception $e) {
            return redirect()->route('cart.index')->with('error', 'Failed to remove item.');
        }
    }
}
