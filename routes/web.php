<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{handle}', [ProductController::class, 'show'])->name('products.show');
Route::post('/cart/add', [CartController::class, 'addToCart'])->name('cart.add');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items/{lineItemId}', [CartController::class, 'updateLineItem'])->name('cart.update');
Route::delete('/cart/items/{lineItemId}', [CartController::class, 'removeLineItem'])->name('cart.remove');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'submit'])->name('checkout.submit');
Route::get('/order-confirmed/{orderId}', [CheckoutController::class, 'confirmed'])->name('checkout.confirmed');

Route::post('/checkout/create-payment-session', [CheckoutController::class, 'createPaymentSession'])
    ->name('checkout.create-payment-session');