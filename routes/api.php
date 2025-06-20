<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Trasy dla operacji CRUD na użytkownikach (POST, GET, PUT/PATCH, DELETE)
Route::apiResource('users', UserController::class);

// Trasa do wysyłki maila powitalnego
Route::post('users/{user}/send-welcome-email', [UserController::class, 'sendWelcomeEmail']);