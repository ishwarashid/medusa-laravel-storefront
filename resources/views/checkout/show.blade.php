{{-- resources/views/checkout/show.blade.php --}}
@extends('layouts.app')

@section('content')
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Checkout</h1>

    @if (session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Checkout Form -->
        <div>
            <form id="checkout-form" method="POST" action="{{ route('checkout.submit') }}">
                @csrf

                <!-- Customer Info -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="first_name" required
                            class="w-full border border-gray-300 rounded px-3 py-2 mt-1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="last_name" required
                            class="w-full border border-gray-300 rounded px-3 py-2 mt-1">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" required class="w-full border border-gray-300 rounded px-3 py-2">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Address</label>
                    <input type="text" name="address" required class="w-full border border-gray-300 rounded px-3 py-2">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">City</label>
                        <input type="text" name="city" required
                            class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Postal Code</label>
                        <input type="text" name="postal_code" required
                            class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Country</label>
                        <select name="country" required class="w-full border border-gray-300 rounded px-3 py-2">
                            <option value="us">United States</option>
                            <option value="gb">United Kingdom</option>
                            <option value="de">Germany</option>
                            <option value="fr">France</option>
                            <option value="dk">Denmark</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">State/Province</label>
                        <input type="text" name="state" class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                </div>

                <!-- Shipping Method -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Shipping Method</label>

                    @if (empty($shippingOptions))
                        <p class="text-red-500">No shipping options available for this region.</p>
                    @else
                        @foreach ($shippingOptions as $option)
                            @php
                                $price = $option['amount'] / 100;
                                $currency = $cart['region']['currency_code'] ?? 'EUR';
                            @endphp
                            <label class="flex items-center mt-2">
                                <input type="radio" name="shipping_option_id" value="{{ $option['id'] }}" required>
                                <span class="ml-2 font-medium">{{ $option['name'] }}</span>
                                <span class="ml-2 text-gray-600">
                                    (+{{ strtoupper($currency) }} {{ number_format($price, 2) }})
                                </span>
                            </label>
                            <p class="text-sm text-gray-500 ml-6">{{ $option['description'] ?? '' }}</p>
                        @endforeach
                    @endif
                </div>
                <!-- Payment Method -->
                {{-- <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Payment Method</label>

                    @if (empty($paymentProviders))
                        <p class="text-red-500">No payment methods available.</p>
                    @else
                        @foreach ($paymentProviders as $provider)
                            <label>
                                <input type="radio" name="payment_provider" value="{{ $provider['id'] }}" required>
                                @if ($provider['id'] === 'pp_stripe_stripe')
                                    Pay with Credit Card (via Stripe)
                                @else
                                    {{ ucfirst(str_replace('_', ' ', $provider['id'])) }}
                                @endif
                            </label>
                        @endforeach
                    @endif
                </div> --}}
                <div id="payment-methods">
                    <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                    @foreach ($paymentProviders as $provider)
                        <label class="flex items-center mt-2">
                            <input type="radio" name="payment_provider" value="{{ $provider['id'] }}" required
                                onchange="togglePaymentForm(this.value)">
                            <span class="ml-2">
                                @if ($provider['id'] === 'manual_manual')
                                    Pay on Delivery
                                @elseif ($provider['id'] === 'pp_stripe_stripe')
                                    Credit Card (Stripe)
                                @else
                                    {{ ucfirst(str_replace('_', ' ', $provider['id'])) }}
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
                <div id="stripe-form" style="display: none;" class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Card Details</label>
                    <div id="card-element" class="border rounded p-3 mt-1"></div>
                    <div id="card-errors" class="text-red-500 text-sm mt-1" role="alert"></div>
                </div>

                <!-- Hidden input to pass client_secret -->
                <input type="hidden" id="client-secret" value="{{ $clientSecret ?? '' }}">

                <button type="submit" id="submit-button"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
                    Place Order
                </button>
            </form>
        </div>

        <!-- Order Summary -->
        <div>
            <h2 class="text-xl font-semibold mb-4">Order Summary</h2>
            <div class="bg-gray-50 p-4 rounded">
                <ul class="divide-y divide-gray-200">
                    @foreach ($cart['items'] as $item)
                        <li class="py-2 flex justify-between">
                            <span>{{ $item['product_title'] }} × {{ $item['quantity'] }}</span>
                            <span>{{ number_format($item['unit_price'] / 100, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t pt-2 mt-4 font-semibold">
                    Total: {{ number_format($cart['total'] / 100, 2) }} {{ strtoupper($cart['currency_code']) }}
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            let stripe = null;
            let elements = null;
            let cardElement = null;

            function togglePaymentForm(providerId) {
                const stripeForm = document.getElementById('stripe-form');
                const clientSecretInput = document.getElementById('client-secret');

                if (providerId === 'pp_stripe_stripe' && clientSecretInput.value) {
                    stripe = Stripe('{{ config('services.stripe.publishable_key') }}');
                    elements = stripe.elements();
                    cardElement = elements.create('card');
                    cardElement.mount('#card-element');

                    stripeForm.style.display = 'block';
                } else {
                    if (cardElement) {
                        cardElement.unmount();
                        cardElement = null;
                    }
                    stripeForm.style.display = 'none';
                }
            }


            const form = document.getElementById('checkout-form');
            const submitButton = document.getElementById('submit-button');
            const clientSecret = document.getElementById('client-secret').value;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const paymentProvider = document.querySelector('input[name="payment_provider"]:checked')?.value;

                if (paymentProvider === 'pp_stripe_stripe' && clientSecret) {
                    submitButton.disabled = true;
                    const {
                        error,
                        paymentIntent
                    } = await stripe.confirmCardPayment(clientSecret, {
                        payment_method: {
                            card: cardElement,
                            billing_details: {
                                name: `${document.querySelector('input[name="first_name"]').value} ${document.querySelector('input[name="last_name"]').value}`,
                                email: document.querySelector('input[name="email"]').value,
                            }
                        }
                    });

                    if (error) {
                        alert(error.message);
                        submitButton.disabled = false;
                    } else if (paymentIntent.status === 'succeeded') {
                        // Payment succeeded — now submit form to complete order
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'payment_confirmed';
                        hiddenInput.value = 'true';
                        form.appendChild(hiddenInput);
                        form.submit();
                    }
                } else {
                    // For manual payment, submit normally
                    form.submit();
                }
            });
        </script>
    @endpush
@endsection
