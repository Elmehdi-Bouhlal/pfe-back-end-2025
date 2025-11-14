<?php

use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\Ai\AiController;
use App\Http\Controllers\Ai\BookSearchController;
use App\Http\Controllers\Ai\UserBooksController;
use App\Http\Controllers\Books\FavoritesController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Books\BookController;
use App\Http\Controllers\Books\ReadingListController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ManageUsers;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\User\ConversationController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {

    // user action routes    
    Route::prefix('user')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::put('/profile', [UserController::class, 'update']);
        Route::post('/avatar', [UserController::class, 'updateAvatar']);
        Route::put('/password', [UserController::class, 'updatePassword']);
        Route::get('/stats', [UserController::class, 'getStats']);
        Route::delete('/account', [UserController::class, 'destroy']);
    });

    // admin end-points
    Route::post('/add-user', [ManageUsers::class, 'addUser']);
    Route::delete('/delete-user/{email}', [ManageUsers::class, 'deleteUser']);
    Route::get('/list-users', [ManageUsers::class, 'listUser']);
    Route::post('/logout', [UserController::class, 'logout'])->name('user.logout');
    Route::get('/users-list', [UserController::class, 'userList'])->name('user.list');

    // roles && permession routes
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions', [PermissionController::class, 'index']);

    // analyze pdf by the ai
    Route::prefix('user/books')->name('user.books.')->group(function () {
        Route::get('/', [UserBooksController::class, 'index'])->name('index');
        Route::get('/stats', [UserBooksController::class, 'getReadingStats'])->name('stats');
        Route::get('/{id}', [UserBooksController::class, 'show'])->name('show');
        Route::get('/{id}/download', [UserBooksController::class, 'download'])->name('download');
        Route::put('/{id}/progress', [UserBooksController::class, 'updateProgress'])->name('progress');
        Route::post('/{id}/notes', [UserBooksController::class, 'addNote'])->name('notes.store');
        Route::get('/{id}/notes', [UserBooksController::class, 'getNotes'])->name('notes.index');
        Route::delete('/{id}/notes/{noteId}', [UserBooksController::class, 'deleteNote'])->name('notes.destroy');
    });

    // Ajoutez cette ligne après la fermeture du groupe user/books
    Route::get('/user/recommendations', [UserBooksController::class, 'getRecommendations'])->name('user.recommendations');

    // AI Assistant Routes
    Route::prefix('ai')->group(function () {
        // Existing routes
        Route::post('/analyze-pdf', [AiController::class, 'analyzePDF']);
        Route::post('/search-books', [BookSearchController::class, 'searchBooksWithAI']);

        // New AI reading assistant routes
        Route::post('/summarize-page', [AiController::class, 'summarizePage']);
        Route::post('/explain-concepts', [AiController::class, 'explainConcepts']);
        Route::post('/chat-about-book', [AiController::class, 'chatAboutBook']);
        Route::post('/reading-recommendations', [AiController::class, 'getReadingRecommendations']);
        Route::post('/study-notes', [AiController::class, 'generateStudyNotes']);
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

    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/add', [CartController::class, 'addToCart']);
        Route::put('/{cartItemId}', [CartController::class, 'updateQuantity']);
        Route::delete('/{cartItemId}', [CartController::class, 'removeFromCart']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
        Route::get('/summary', [CartController::class, 'getCartSummary']);
        Route::get('/check/{bookId}', [CartController::class, 'checkInCart']);
        Route::post('/{cartItemId}/move-to-favorites', [CartController::class, 'moveToFavorites']);
    });

    // kanban
    Route::prefix('reading-list')->group(function () {
        Route::get('/', [ReadingListController::class, 'index'])->name('reading-list.index');
        Route::post('/', [ReadingListController::class, 'store'])->name('reading-list.store');
        Route::get('/{id}', [ReadingListController::class, 'show'])->name('reading-list.show');
        Route::put('/{id}', [ReadingListController::class, 'update'])->name('reading-list.update');
        Route::delete('/{id}', [ReadingListController::class, 'destroy'])->name('reading-list.destroy');
        Route::patch('/{id}/status', [ReadingListController::class, 'updateStatus'])->name('reading-list.update-status');
        Route::patch('/{id}/progress', [ReadingListController::class, 'updateProgress'])->name('reading-list.update-progress');
        Route::patch('/{id}/rating', [ReadingListController::class, 'addRating'])->name('reading-list.add-rating');
        Route::post('/reorder', [ReadingListController::class, 'reorder'])->name('reading-list.reorder');
        Route::get('/stats/detailed', [ReadingListController::class, 'getStats'])->name('reading-list.stats');
        Route::post('/import-book', [ReadingListController::class, 'importBook'])->name('reading-list.import-book');
        Route::get('/recommendations', [ReadingListController::class, 'getRecommendations'])->name('reading-list.recommendations');
    });

    // conversation events
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ConversationController::class, 'index']);
        Route::post('/', [ConversationController::class, 'store']);
        Route::get('/stats', [ConversationController::class, 'getStats']);
        Route::post('/mark-all-read', [ConversationController::class, 'markAllAsRead']);

        Route::prefix('{conversationId}')->group(function () {
            Route::get('/', [ConversationController::class, 'show']);
            Route::post('/messages', [ConversationController::class, 'sendMessage']);
            Route::post('/mark-read', [ConversationController::class, 'markAsRead']);
            Route::post('/archive', [ConversationController::class, 'archive']);
            Route::delete('/', [ConversationController::class, 'destroy']);
        });
    });

    // Payment methods
    Route::prefix('checkout')->group(function () {
        Route::get('/payment-methods', [CheckoutController::class, 'getPaymentMethods']);
        Route::post('/create-order', [CheckoutController::class, 'createOrder']);
        Route::post('/process-payment/{order}', [CheckoutController::class, 'processPayment']);
        Route::get('/order/{order}', [CheckoutController::class, 'getOrder']);
    });

    // Payment methods management
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/', [PaymentMethodController::class, 'store']);
        Route::put('/{paymentMethod}', [PaymentMethodController::class, 'update']);
        Route::delete('/{paymentMethod}', [PaymentMethodController::class, 'destroy']);
        Route::post('/{paymentMethod}/set-default', [PaymentMethodController::class, 'setDefault']);
    });

    // Orders management
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
        Route::get('/{order}/download/{orderItem}', [OrderController::class, 'downloadDigitalBook']);
    });

    Route::prefix('admin/orders')->group(function () {
        Route::get('/', [AdminOrderController::class, 'index']);
        Route::get('/{order}', [AdminOrderController::class, 'show']);
        Route::put('/{order}/status', [AdminOrderController::class, 'updateStatus']);
        Route::put('/{order}/payment-status', [AdminOrderController::class, 'updatePaymentStatus']);
        Route::put('/{order}/tracking', [AdminOrderController::class, 'updateTrackingNumber']);
    });

    Route::prefix('user')->group(function () {
        Route::get('/notification-counts', [NotificationController::class, 'getCounts']);
        Route::get('/notifications', [NotificationController::class, 'getNotifications']);
        Route::put('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        
        // Route de test pour créer une notification (optionnel)
        Route::post('/notifications/test', [NotificationController::class, 'createTestNotification']);
    });

    // Statistiques
    Route::get('/admin/statistics/orders', [AdminOrderController::class, 'getStatistics']);
});

// public end-points
// books
Route::prefix('books')->group(function () {
    Route::get('/', [BookController::class, 'index']);           // GET /api/books - List books with pagination
    Route::get('/search', [BookController::class, 'search']);     // GET /api/books/search?q=query - Search books
    Route::get('/{id}', [BookController::class, 'show']);
    Route::post('/{id}/view', [BookController::class, 'recordView']);
});

// related to user
Route::prefix('user')->group(function () {
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::post('/login', [UserController::class, 'login']);
});
