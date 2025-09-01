<?php

use App\Http\Controllers\Ai\AiController;
use App\Http\Controllers\Ai\BookSearchController;
use App\Http\Controllers\Books\FavoritesController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Books\BookController;
use App\Http\Controllers\ManageUsers;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function(){
    // user action routes
    Route::get('/user',[UserController::class,'index'])->name('user.index');
    
    // admin end-points
    Route::post('/add-user',[ManageUsers::class,'addUser']);
    Route::delete('/delete-user/{email}',[ManageUsers::class,'deleteUser']);
    Route::get('/list-users',[ManageUsers::class,'listUser']);
    Route::post('/logout',[UserController::class,'logout'])->name('user.logout');
    Route::get('/users-list',[UserController::class,'userList'])->name('user.list');

    // roles && permession routes
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions',[PermissionController::class, 'index']);

    // analyze pdf by the ai
    Route::prefix('ai')->group(function () {
        Route::post('/analyze-pdf', [AiController::class, 'analyzePDF']);
        Route::post('/search-books', [BookSearchController::class, 'searchBooksWithAI']);
    });

    // favourit books 
    Route::get('/favorites', [FavoritesController::class, 'index']);
    Route::post('/books/{book}/favorite', [FavoritesController::class, 'toggle']);
    Route::get('/books/{book}/favorite-status', [FavoritesController::class, 'checkStatus']);
    Route::delete('/favorites/bulk', [FavoritesController::class, 'bulkDelete']);
    Route::get('/favorites/export', [FavoritesController::class, 'export']);
    

    Route::prefix('books')->group(function () {
        Route::post('/', [BookController::class, 'store']);         
        Route::put('/{id}', [BookController::class, 'update']);     
        Route::delete('/{id}', [BookController::class, 'destroy']); 
        Route::post('/bulk-delete', [BookController::class, 'bulkDelete']);   
    });
});

// public end-points 
// books 
Route::prefix('books')->group(function(){
    Route::get('/search', [BookController::class, 'search']);
    Route::get('/', [BookController::class, 'index']);           // GET /api/books - List books with pagination
    Route::get('/search', [BookController::class, 'search']);     // GET /api/books/search?q=query - Search books
    Route::get('/{id}', [BookController::class, 'show']);    
});

// related to user
Route::prefix('user')->group(function(){
    Route::post('/register',[RegisteredUserController::class,'store']);
    Route::post('/login',[UserController::class,'login']);
});
