<?php
// app/Http/Controllers/CheckoutController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Book;
use App\Http\Requests\CheckoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function getPaymentMethods(Request $request)
    {
        try {
            $paymentMethods = PaymentMethod::where('user_id', $request->user()->id)
                ->active()
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($method) {
                    return [
                        'id' => $method->id,
                        'type' => $method->type,
                        'provider_name' => $method->provider_name,
                        'display_name' => $method->display_name,
                        'icon' => $method->icon,
                        'is_default' => $method->is_default,
                        'is_verified' => !is_null($method->verified_at)
                    ];
                });

            // Add default payment methods if none exist
            if ($paymentMethods->isEmpty()) {
                $defaultMethods = [
                    [
                        'id' => 'cod',
                        'type' => 'cash_on_delivery',
                        'provider_name' => 'Paiement à la livraison',
                        'display_name' => 'Paiement à la livraison',
                        'icon' => 'cash',
                        'is_default' => true,
                        'is_verified' => true
                    ],
                    [
                        'id' => 'paypal',
                        'type' => 'paypal',
                        'provider_name' => 'PayPal',
                        'display_name' => 'PayPal',
                        'icon' => 'paypal',
                        'is_default' => false,
                        'is_verified' => true
                    ]
                ];
                
                return response()->json([
                    'success' => true,
                    'payment_methods' => $defaultMethods
                ]);
            }

            return response()->json([
                'success' => true,
                'payment_methods' => $paymentMethods
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching payment methods:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des méthodes de paiement'
            ], 500);
        }
    }

    public function createOrder(CheckoutRequest $request)
    {
        DB::beginTransaction();
        
        try {
            $user = $request->user();
            $data = $request->validated();
            
            // Validate cart items
            $cartItems = $user->cartItems()->with('book')->get();
            
            if ($cartItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre panier est vide'
                ], 422);
            }

            // Check availability and calculate totals
            $subtotal = 0;
            $unavailableItems = [];
            
            foreach ($cartItems as $item) {
                if (!$item->book->is_available || $item->book->status !== 'published') {
                    $unavailableItems[] = $item->book->title;
                    continue;
                }
                
                $subtotal += $item->book->price * $item->quantity;
            }

            if (!empty($unavailableItems)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certains articles ne sont plus disponibles: ' . implode(', ', $unavailableItems)
                ], 422);
            }

            // Calculate shipping
            $shippingCost = $this->calculateShippingCost($subtotal, $data['shipping_address']);
            
            // Calculate discount
            $discountAmount = 0;
            if (!empty($data['promo_code'])) {
                $discountAmount = $this->calculateDiscount($data['promo_code'], $subtotal);
            }

            // Calculate total
            $totalAmount = $subtotal + $shippingCost - $discountAmount;

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'currency' => 'MAD',
                'promo_code' => $data['promo_code'] ?? null,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'shipping_address' => $data['shipping_address'],
                'billing_address' => $data['billing_address'] ?? $data['shipping_address'],
                'notes' => $data['notes'] ?? null,
                'metadata' => [
                    'user_agent' => $request->header('User-Agent'),
                    'ip_address' => $request->ip(),
                    'created_via' => 'web'
                ]
            ]);

            // Create order items
            foreach ($cartItems as $cartItem) {
                $book = $cartItem->book;
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id' => $book->id,
                    'book_title' => $book->title,
                    'book_author' => $book->author,
                    'unit_price' => $book->price,
                    'quantity' => $cartItem->quantity,
                    'total_price' => $book->price * $cartItem->quantity,
                    'book_type' => $book->book_type,
                    'book_condition' => $book->book_condition,
                    'book_metadata' => [
                        'isbn' => $book->isbn,
                        'genre' => $book->genre,
                        'language' => $book->language,
                        'pages' => $book->pages,
                        'description' => $book->description
                    ],
                    'download_limit' => $book->book_type === 'digital' ? ($book->download_limit ?? 5) : null,
                    'download_expires_at' => $book->book_type === 'digital' ? now()->addDays(30) : null
                ]);
            }

            // Clear cart
            $user->cartItems()->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'payment_method' => $order->payment_method,
                    'status' => $order->status
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating order:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'data' => $data
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande'
            ], 500);
        }
    }

    public function processPayment(Request $request, Order $order)
    {
        try {
            // Verify order belongs to user
            if ($order->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande non trouvée'
                ], 404);
            }

            // Check if order can be paid
            if ($order->payment_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette commande ne peut plus être payée'
                ], 422);
            }

            $paymentMethod = $order->payment_method;
            
            switch ($paymentMethod) {
                case 'cash_on_delivery':
                    return $this->processCashOnDelivery($order);
                    
                case 'paypal':
                    return $this->processPayPalPayment($order, $request);
                    
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Méthode de paiement non supportée'
                    ], 422);
            }

        } catch (\Exception $e) {
            Log::error('Error processing payment:', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'payment_method' => $order->payment_method
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du traitement du paiement'
            ], 500);
        }
    }

    private function processCashOnDelivery(Order $order)
    {
        // For cash on delivery, we just confirm the order
        $order->update([
            'status' => 'confirmed',
            'payment_status' => 'pending', // Will be completed on delivery
            'payment_details' => [
                'method' => 'cash_on_delivery',
                'confirmed_at' => now()->toISOString()
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande confirmée! Vous paierez à la livraison.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status
            ]
        ]);
    }

    private function processPayPalPayment(Order $order, Request $request)
    {
        // Simulate PayPal payment processing
        // In real implementation, you would integrate with PayPal SDK
        
        $transactionId = 'PAYPAL_' . strtoupper(uniqid());
        
        // Simulate payment processing delay
        sleep(2);
        
        // Simulate successful payment
        $order->markAsPaid($transactionId, [
            'method' => 'paypal',
            'transaction_id' => $transactionId,
            'processed_at' => now()->toISOString(),
            'amount' => $order->total_amount,
            'currency' => $order->currency
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement PayPal réussi!',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'transaction_id' => $transactionId
            ]
        ]);
    }

    private function calculateShippingCost($subtotal, $shippingAddress)
    {
        // Free shipping above 500 MAD
        if ($subtotal >= 500) {
            return 0;
        }

        // Base shipping cost
        $baseCost = 30;
        
        // Different rates for different cities (example)
        $city = strtolower($shippingAddress['city'] ?? '');
        $shippingRates = [
            'casablanca' => 30,
            'rabat' => 35,
            'marrakech' => 40,
            'fes' => 45,
            'tangier' => 50
        ];

        return $shippingRates[$city] ?? 50; // Default to 50 MAD for other cities
    }

    private function calculateDiscount($promoCode, $subtotal)
    {
        // Simple promo codes (in real app, you'd have a promo_codes table)
        $promoCodes = [
            'WELCOME10' => ['type' => 'percentage', 'value' => 10, 'min_amount' => 100],
            'SAVE50' => ['type' => 'fixed', 'value' => 50, 'min_amount' => 200],
            'BOOK20' => ['type' => 'percentage', 'value' => 20, 'min_amount' => 300]
        ];

        $promo = $promoCodes[strtoupper($promoCode)] ?? null;
        
        if (!$promo || $subtotal < $promo['min_amount']) {
            return 0;
        }

        if ($promo['type'] === 'percentage') {
            return ($subtotal * $promo['value']) / 100;
        }

        return $promo['value'];
    }

    public function getOrder(Request $request, Order $order)
    {
        // Verify order belongs to user
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        $order->load('items.book');

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'payment_status' => $order->payment_status,
                'payment_status_label' => $order->payment_status_label,
                'payment_method' => $order->payment_method,
                'subtotal' => $order->subtotal,
                'shipping_cost' => $order->shipping_cost,
                'discount_amount' => $order->discount_amount,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
                'promo_code' => $order->promo_code,
                'shipping_address' => $order->shipping_address,
                'billing_address' => $order->billing_address,
                'created_at' => $order->created_at,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'book_id' => $item->book_id,
                        'book_title' => $item->book_title,
                        'book_author' => $item->book_author,
                        'unit_price' => $item->unit_price,
                        'quantity' => $item->quantity,
                        'total_price' => $item->total_price,
                        'book_type' => $item->book_type,
                        'book_condition' => $item->book_condition,
                        'can_download' => $item->can_download,
                        'download_count' => $item->download_count,
                        'download_limit' => $item->download_limit
                    ];
                })
            ]
        ]);
    }
}