<?php

// app/Http/Controllers/ProductController.php
namespace App\Http\Controllers;

use App\Services\MedusaApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected $medusaService;

    public function __construct(MedusaApiService $medusaService)
    {
        $this->medusaService = $medusaService;
    }

    public function index()
    {
        try {
            $productsResponse = $this->medusaService->getProducts();
            $products = $productsResponse['products'] ?? [];
            return view('products.index', compact('products'));
        } catch (\Exception $e) {
            // Log the error
            Log::error("Failed to fetch products from Medusa: " . $e->getMessage());
            // You might want to return an error view or a message to the user
            return view('products.index', ['products' => [], 'error' => 'Could not load products at this time.']);
        }
    }


    public function show(string $handle)
    {
        try {
            $product = $this->medusaService->getProductByHandle($handle);

            if (!$product) {
                abort(404, 'Product not found.');
            }

            return view('products.show', compact('product'));
        } catch (\Exception $e) {
            Log::error("Failed to fetch product {$handle} from Medusa: " . $e->getMessage());
            abort(500, 'Could not load product details at this time.');
        }
    }
}
