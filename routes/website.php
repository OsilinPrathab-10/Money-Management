<?php

use App\Http\Controllers\Website\ApplicationController;
use App\Http\Controllers\Website\ContactController;
use App\Http\Controllers\Website\EligibilityController;
use App\Http\Controllers\Website\EmiController;
use App\Http\Controllers\Website\HomeController;
use App\Http\Controllers\Website\LoanController;
use App\Http\Controllers\Website\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('website.home');

Route::prefix('loans')->name('website.loans.')->group(function () {
    Route::get('/', [LoanController::class, 'index'])->name('index');
    Route::get('/category/{slug}', [LoanController::class, 'category'])->name('category');
    Route::get('/{slug}', [LoanController::class, 'show'])->name('show');
});

Route::get('/about', [PageController::class, 'about'])->name('website.about');
Route::get('/services', [PageController::class, 'services'])->name('website.services');
Route::get('/faq', [PageController::class, 'faq'])->name('website.faq');
Route::get('/contact', [ContactController::class, 'show'])->name('website.contact');
Route::post('/contact', [ContactController::class, 'store'])->name('website.contact.store');

Route::get('/eligibility', [EligibilityController::class, 'show'])->name('website.eligibility');
Route::post('/eligibility', [EligibilityController::class, 'check'])->name('website.eligibility.check');

Route::get('/emi-calculator', [EmiController::class, 'show'])->name('website.emi');
Route::post('/emi-calculator/calculate', [EmiController::class, 'calculate'])->name('website.emi.calculate');

Route::get('/apply', [ApplicationController::class, 'create'])->name('website.apply');
Route::get('/apply/{productSlug}', [ApplicationController::class, 'create'])->name('website.apply.product');
Route::post('/apply', [ApplicationController::class, 'store'])->name('website.apply.store');

Route::get('/track-application', [ApplicationController::class, 'trackForm'])->name('website.application.track.form');
Route::post('/track-application', [ApplicationController::class, 'track'])->name('website.application.track');
