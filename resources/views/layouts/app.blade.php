<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medusa Laravel Store</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
    {{-- <meta name="csrf-token" content="{{ csrf_token() }}"> --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">


    @stack('styles')
</head>

<body class="bg-gray-100 font-sans antialiased">
    <nav class="bg-white shadow-md p-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="{{ route('products.index') }}" class="text-xl font-bold text-gray-800">Medusa Laravel Store</a>
            <div>
                <!-- Cart Link -->
                <a href="{{ route('cart.index') }}" class="text-gray-600 hover:text-gray-800 mr-4">
                    🛒
                    @php
                        $cartId = session('cart_id');
                        $count = 0;

                        if ($cartId) {
                            try {
                                $cart = app(\App\Services\MedusaApiService::class)->getCart($cartId);
                                $count = collect($cart['cart']['items'])->sum('quantity');
                            } catch (\Exception $e) {
                                // Ignore if cart not found
                            }
                        }
                    @endphp
                    @if (session('cart_count'))
                        <span class="bg-red-500 ...">{{ session('cart_count') }}</span>
                    @endif
                </a>
                <a href="#" class="text-gray-600 hover:text-gray-800">Account</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto mt-8 p-4">
        @yield('content')
    </main>
    @stack('scripts')
</body>

</html>
