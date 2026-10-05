<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {

    Route::get('/signup', function () {
        return view('auth.signup');
    })->name('signup');

    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

});

Route::middleware('auth')->group(function () {

    Route::livewire('/dashboard', 'dashboard')->name('dashboard');
    Route::livewire('/products', 'products')->name('products');
    Route::livewire('/pos', 'pos')->name('pos');
    Route::livewire('/sales', 'sales')->name('sales');
    Route::livewire('/load', 'load')->name('load');
    Route::livewire('/gcash', 'gcash')->name('gcash');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});
