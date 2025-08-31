{{-- resources/views/cart/index.blade.php --}}
@extends('layouts.app')

@section('content')
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Shopping Cart</h1>

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if (!$cart || empty($cart['items']))
        <p class="text-gray-700">Your cart is empty.</p>
        <a href="{{ route('products.index') }}" class="text-blue-600 hover:underline">Continue Shopping</a>
    @else
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <ul class="divide-y divide-gray-200">
                @foreach ($cart['items'] as $item)
                    @php
                        // Use available fields from cart item
                        $title = $item['product_title'] ?? ($item['title'] ?? 'Product');
                        $variantTitle = $item['variant_title'] ?? 'One Size';
                        $quantity = $item['quantity'];
                        $unitPrice = $item['unit_price']; // cents → euros
                        $currency = $cart['currency_code'] ?? 'EUR';
                        $thumbnail = $item['thumbnail'] ?? 'https://via.placeholder.com/100?text=No+Image';
                    @endphp

                    <li class="p-4 flex items-center">
                        <!-- Product Image -->
                        <img src="{{ trim($thumbnail) }}" alt="{{ $title }}" class="w-16 h-16 object-cover rounded">

                        <!-- Product Info -->
                        <div class="ml-4 flex-1">
                            <h3 class="text-lg font-semibold text-gray-800">{{ $title }}</h3>
                            <p class="text-gray-600">{{ $variantTitle }} × {{ $quantity }}</p>
                            <p class="text-gray-900 font-medium">
                                {{ strtoupper($currency) }} {{ number_format($unitPrice, 2) }}
                            </p>
                        </div>

                        <!-- Update Quantity -->
                        <form method="POST" action="{{ route('cart.update', $item['id']) }}" class="mx-4">
                            @csrf
                            @method('POST')
                            <input type="number" name="quantity" value="{{ $quantity }}" min="1"
                                onchange="this.form.submit()"
                                class="w-16 border border-gray-300 rounded px-2 py-1 text-center">
                        </form>

                        <!-- Remove Item -->
                        <form method="POST" action="{{ route('cart.remove', $item['id']) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700">
                                ✕
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>

            <!-- Cart Summary -->
            <div class="p-4 bg-gray-50 border-t">
                <div class="flex justify-between text-lg font-semibold">
                    <span>Total:</span>
                    <span>
                        {{ strtoupper($currency) }}
                        {{ number_format($cart['total'], 2) }}
                    </span>
                </div>

                <div class="mt-4">
                    <a href="{{ route('checkout.show') }}"
                        class="block text-center bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                        Proceed to Checkout
                    </a>
                </div>
            </div>
        </div>
    @endif
@endsection
