<!-- resources/views/checkout/confirmed.blade.php -->

@extends('layouts.app')

@section('content')
    <div class="max-w-4xl mx-auto py-10">
        <h1 class="text-3xl font-bold text-green-700 mb-6">Order Confirmed! 🎉</h1>

        <p class="text-lg mb-4">Thank you for your order. Your payment has been processed successfully.</p>

        @if (Session::has('last_order_id'))
            <p class="mb-4">
                <strong>Order ID:</strong> {{ Session::get('last_order_id') }}
            </p>
        @endif

        <p>
            <a href="{{ route('products.index') }}" class="text-blue-600 hover:underline">
                ← Continue Shopping
            </a>
        </p>
    </div>
@endsection
