<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/user',[UserController::class,'index'])->name('user.index');
    Route::post('/logout',[UserController::class,'logout'])->name('user.logout');
    Route::get('/users-list',[UserController::class,'userList'])->name('user.list');
});

// routes don't need to have a token
Route::prefix('user')->group(function(){
    Route::post('/register',[RegisteredUserController::class,'store']);
    Route::post('/login',[UserController::class,'login']);
});

