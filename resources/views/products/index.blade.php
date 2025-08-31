@extends('layouts.app')

@section('content')
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Our Products</h1>

    @if (isset($error))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">{{ $error }}</span>
        </div>
    @endif

    @if (count($products) > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach ($products as $product)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300">
                    <a href="{{ route('products.show', $product['handle']) }}">
                        <img src="{{ $product['thumbnail'] ?? 'https://via.placeholder.com/300x200?text=No+Image' }}"
                            alt="{{ $product['title'] }}" class="w-full h-48 object-cover">
                    </a>
                    <div class="p-4">
                        <h2 class="text-xl font-semibold text-gray-800 mb-2">
                            <a href="{{ route('products.show', $product['handle']) }}"
                                class="hover:text-blue-600">{{ $product['title'] }}</a>
                        </h2>
                        <p class="text-gray-600 text-sm mb-3">
                            {{ Str::limit($product['description'] ?? 'No description available.', 100) }}</p>

                        @php
                            $firstVariant = $product['variants'][0] ?? null;
                            $price = 0;
                            $currency = 'USD';

                            if ($firstVariant && isset($firstVariant['calculated_price'])) {
                                $price = $firstVariant['calculated_price']['calculated_amount'];
                                $currency = $firstVariant['calculated_price']['currency_code'];
                            }
                        @endphp

                        <p class="text-lg font-bold text-gray-900">
                            {{ strtoupper($currency) }} {{ number_format($price, 2) }}
                        </p>

                        <div class="mt-4">
                            <a href="{{ route('products.show', $product['handle']) }}"
                                class="inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors duration-300">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-gray-700">No products found at this time.</p>
    @endif
@endsection
