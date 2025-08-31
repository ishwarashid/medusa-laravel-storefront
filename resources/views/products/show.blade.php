{{-- resources/views/products/show.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="bg-white shadow-lg rounded-lg overflow-hidden max-w-4xl mx-auto">
        <div class="md:flex">
            <!-- Image Gallery -->
            <div class="md:w-1/2 p-4">
                @if (!empty($product['images']))
                    <img src="{{ trim($product['thumbnail']) }}" alt="{{ $product['title'] }}"
                        class="w-full h-80 object-cover rounded-md mb-4" id="mainImage">
                    <div class="flex space-x-2">
                        @foreach ($product['images'] as $image)
                            <img src="{{ trim($image['url']) }}" alt="Thumbnail"
                                class="w-16 h-16 object-cover border rounded cursor-pointer hover:border-blue-500"
                                onclick="changeImage(this)">
                        @endforeach
                    </div>
                @else
                    <img src="https://via.placeholder.com/400" alt="No image" class="w-full h-80 object-cover">
                @endif
            </div>

            <!-- Product Info -->
            <div class="md:w-1/2 p-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">{{ $product['title'] }}</h1>
                @if ($product['subtitle'])
                    <p class="text-lg text-gray-600 mb-4">{{ $product['subtitle'] }}</p>
                @endif

                <!-- Price -->
                @php
                    $firstVariant = $product['variants'][0] ?? null;

                    $price =
                        $firstVariant && isset($firstVariant['calculated_price'])
                            ? $firstVariant['calculated_price']['calculated_amount']
                            : 0;

                    $currency = $firstVariant['calculated_price']['currency_code'] ?? 'USD';
                @endphp

                <div class="text-2xl font-semibold text-gray-900 mb-4">
                    {{ strtoupper($currency) }} {{ number_format($price, 2) }}
                </div>

                <p class="text-gray-700 mb-6">{{ $product['description'] }}</p>

                <!-- Variant Selection Form -->
                <form id="add-to-cart-form" method="POST" action="{{ route('cart.add') }}">
                    @csrf
                    <input type="hidden" name="variant_id" id="selected-variant-id"
                        value="{{ $firstVariant['id'] ?? '' }}">

                    <!-- Dynamic Options: Size, Color, etc. -->
                    @foreach ($product['options'] as $option)
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $option['title'] }}
                            </label>
                            <select name="{{ $option['title'] }}" id="option-{{ $option['id'] }}"
                                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300"
                                onchange="updateVariant()">
                                @foreach ($option['values'] as $value)
                                    <option value="{{ $value['id'] }}">{{ $value['value'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach

                    <!-- Quantity -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                        <input type="number" name="quantity" value="1" min="1"
                            class="w-20 border border-gray-300 rounded px-3 py-2">
                    </div>

                    <!-- Add to Cart -->
                    <button type="submit"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition duration-300">
                        Add to Cart
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Pass all variants to JS
        const variants = @json($product['variants']);

        // Update selected variant based on option choices
        function updateVariant() {
            const selections = {};
            @foreach ($product['options'] as $option)
                selections['{{ $option['id'] }}'] = document.getElementById('option-{{ $option['id'] }}').value;
            @endforeach

            const matchedVariant = variants.find(variant => {
                return variant.options.every(opt => {
                    return selections[opt.option_id] === opt.id;
                });
            });

            if (matchedVariant && matchedVariant.calculated_price) {
                const cp = matchedVariant.calculated_price;
                const price = parseFloat(cp.calculated_amount).toFixed(2);
                const currency = cp.currency_code.toUpperCase();
                document.querySelector('.text-2xl.font-semibold').textContent = `${currency} ${price}`;
                document.getElementById('selected-variant-id').value = matchedVariant.id;
            }
        }

        function changeImage(img) {
            document.getElementById('mainImage').src = img.src.trim();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', updateVariant);
    </script>
@endsection