<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\Book;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminOrderController extends Controller
{
    // public function __construct()
    // {
    //     // Middleware pour vérifier que l'utilisateur est admin
    //     $this->middleware('auth:sanctum');
    //     $this->middleware('admin');
    // }

    /**
     * Afficher la liste de toutes les commandes (avec filtres)
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $status = $request->input('status');
            $paymentStatus = $request->input('payment_status');
            $search = $request->input('search');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            $query = Order::with(['user', 'items.book'])
                ->select('orders.*');

            // Filtres
            if ($status) {
                $query->where('status', $status);
            }

            if ($paymentStatus) {
                $query->where('payment_status', $paymentStatus);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            // Tri
            $allowedSortFields = ['created_at', 'total_amount', 'status', 'payment_status', 'order_number'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $orders = $query->paginate($perPage);

            $ordersData = $orders->getCollection()->map(function ($order) {
                $shippingAddress = $order->shipping_address;

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'payment_status' => $order->payment_status,
                    'payment_status_label' => $order->payment_status_label,
                    'payment_method' => $order->payment_method,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'total_items' => $order->total_items,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                    'tracking_number' => $order->tracking_number,
                    'customer_name' => $order->user ? $order->user->name : 'Utilisateur supprimé',
                    'customer_email' => $order->user ? $order->user->email : 'N/A',
                    'customer_phone' => $shippingAddress['phone'] ?? 'N/A',
                    'shipping_city' => $shippingAddress['city'] ?? 'N/A',
                    'can_be_cancelled' => $order->canBeCancelled(),
                    'items_count' => $order->items->count(),
                    'has_digital_items' => $order->items->where('book_type', 'digital')->count() > 0,
                    'has_physical_items' => $order->items->where('book_type', 'physical')->count() > 0,
                ];
            });

            return response()->json([
                'success' => true,
                'orders' => $ordersData,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching admin orders:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des commandes'
            ], 500);
        }
    }

    /**
     * Afficher les détails d'une commande spécifique
     */
    public function show(Request $request, Order $order)
    {
        try {
            $order->load(['user', 'items.book']);

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
                    'payment_transaction_id' => $order->payment_transaction_id,
                    'subtotal' => $order->subtotal,
                    'shipping_cost' => $order->shipping_cost,
                    'discount_amount' => $order->discount_amount,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'promo_code' => $order->promo_code,
                    'shipping_address' => $order->shipping_address,
                    'billing_address' => $order->billing_address,
                    'tracking_number' => $order->tracking_number,
                    'notes' => $order->notes,
                    'payment_details' => $order->payment_details,
                    'metadata' => $order->metadata,
                    'created_at' => $order->created_at,
                    'payment_completed_at' => $order->payment_completed_at,
                    'shipped_at' => $order->shipped_at,
                    'delivered_at' => $order->delivered_at,
                    'total_items' => $order->total_items,
                    'can_be_cancelled' => $order->canBeCancelled(),
                    'customer' => $order->user ? [
                        'id' => $order->user->id,
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'created_at' => $order->user->created_at
                    ] : null,
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
                            'book_metadata' => $item->book_metadata,
                            'download_count' => $item->download_count,
                            'download_limit' => $item->download_limit,
                            'download_expires_at' => $item->download_expires_at,
                            'can_download' => $item->can_download,
                            'book' => $item->book ? [
                                'id' => $item->book->id,
                                'title' => $item->book->title,
                                'author' => $item->book->author,
                                'isbn' => $item->book->isbn,
                                'status' => $item->book->status,
                                'is_available' => $item->book->is_available
                            ] : null
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching admin order details:', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des détails de la commande'
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateStatus(Request $request, Order $order)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Statut invalide',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldStatus = $order->status;
            $newStatus = $request->status;

            // Vérifications de logique métier
            if ($oldStatus === 'delivered' && $newStatus !== 'delivered') {
                return response()->json([
                    'success' => false,
                    'message' => 'Une commande livrée ne peut pas changer de statut'
                ], 422);
            }

            if ($oldStatus === 'cancelled' && !in_array($newStatus, ['cancelled', 'refunded'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une commande annulée ne peut pas être réactivée'
                ], 422);
            }

            if ($oldStatus === 'refunded' && $newStatus !== 'refunded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Une commande remboursée ne peut pas changer de statut'
                ], 422);
            }

            // Préparer les données de mise à jour
            $updateData = ['status' => $newStatus];

            // Actions automatiques selon le nouveau statut
            switch ($newStatus) {
                case 'confirmed':
                    // Si le paiement était en attente et que c'est du COD, on peut confirmer
                    if ($order->payment_method === 'cash_on_delivery' && $order->payment_status === 'pending') {
                        // Le paiement reste pending jusqu'à la livraison pour COD
                    }
                    break;

                case 'shipped':
                    $updateData['shipped_at'] = now();
                    break;

                case 'delivered':
                    $updateData['delivered_at'] = now();
                    // Pour le paiement à la livraison, marquer comme payé à la livraison
                    if ($order->payment_method === 'cash_on_delivery' && $order->payment_status === 'pending') {
                        $updateData['payment_status'] = 'completed';
                        $updateData['payment_completed_at'] = now();
                        $updateData['payment_details'] = array_merge($order->payment_details ?? [], [
                            'completed_by_admin' => true,
                            'completed_at' => now()->toISOString(),
                            'admin_id' => $request->user()->id
                        ]);
                    }
                    break;

                case 'cancelled':
                    // Si la commande était payée, proposer un remboursement
                    if ($order->payment_status === 'completed') {
                        // Ne pas changer automatiquement le statut de paiement
                        // L'admin devra le faire manuellement s'il souhaite rembourser
                    }
                    break;

                case 'refunded':
                    $updateData['status'] = 'refunded';
                    if ($order->payment_status === 'completed') {
                        $updateData['payment_status'] = 'refunded';
                    }
                    break;
            }

            DB::beginTransaction();

            $order->update($updateData);

            // Log de l'action admin
            Log::info('Order status updated by admin:', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'admin_id' => $request->user()->id,
                'admin_email' => $request->user()->email
            ]);

            // Notification au client (si l'utilisateur existe encore)
            if ($order->user) {
                try {
                    $order->user->notify(new OrderStatusUpdated($order, $oldStatus, $newStatus));
                } catch (\Exception $e) {
                    Log::warning('Failed to send order status notification:', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Statut de la commande mis à jour: {$order->status_label}",
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'payment_status' => $order->payment_status,
                    'payment_status_label' => $order->payment_status_label
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating order status:', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'requested_status' => $request->status ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut'
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut de paiement d'une commande
     */
    public function updatePaymentStatus(Request $request, Order $order)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_status' => 'required|in:pending,processing,completed,failed,refunded',
                'transaction_id' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldPaymentStatus = $order->payment_status;
            $newPaymentStatus = $request->payment_status;

            // Vérifications de logique métier
            if ($oldPaymentStatus === 'completed' && $newPaymentStatus === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Un paiement complété ne peut pas revenir en attente'
                ], 422);
            }

            $updateData = ['payment_status' => $newPaymentStatus];

            // Actions automatiques selon le nouveau statut de paiement
            switch ($newPaymentStatus) {
                case 'completed':
                    $updateData['payment_completed_at'] = now();

                    if ($request->transaction_id) {
                        $updateData['payment_transaction_id'] = $request->transaction_id;
                    }

                    $updateData['payment_details'] = array_merge($order->payment_details ?? [], [
                        'manual_confirmation' => true,
                        'confirmed_by_admin' => true,
                        'confirmed_at' => now()->toISOString(),
                        'admin_id' => $request->user()->id,
                        'admin_notes' => $request->notes
                    ]);

                    // Si la commande était en attente, la confirmer
                    if ($order->status === 'pending') {
                        $updateData['status'] = 'confirmed';
                    }
                    break;

                case 'failed':
                    $updateData['payment_details'] = array_merge($order->payment_details ?? [], [
                        'failed_by_admin' => true,
                        'failed_at' => now()->toISOString(),
                        'admin_id' => $request->user()->id,
                        'failure_reason' => $request->notes ?? 'Marqué comme échec par admin'
                    ]);

                    // Annuler la commande si le paiement échoue
                    if (in_array($order->status, ['pending', 'confirmed'])) {
                        $updateData['status'] = 'cancelled';
                    }
                    break;

                case 'refunded':
                    $updateData['payment_details'] = array_merge($order->payment_details ?? [], [
                        'refunded_by_admin' => true,
                        'refunded_at' => now()->toISOString(),
                        'admin_id' => $request->user()->id,
                        'refund_reason' => $request->notes ?? 'Remboursé par admin'
                    ]);

                    // Marquer la commande comme remboursée
                    if ($order->status !== 'cancelled') {
                        $updateData['status'] = 'refunded';
                    }
                    break;

                case 'processing':
                    $updateData['payment_details'] = array_merge($order->payment_details ?? [], [
                        'processing_by_admin' => true,
                        'processing_at' => now()->toISOString(),
                        'admin_id' => $request->user()->id
                    ]);
                    break;
            }

            DB::beginTransaction();

            $order->update($updateData);

            // Log de l'action admin
            Log::info('Order payment status updated by admin:', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'old_payment_status' => $oldPaymentStatus,
                'new_payment_status' => $newPaymentStatus,
                'transaction_id' => $request->transaction_id,
                'admin_id' => $request->user()->id,
                'admin_email' => $request->user()->email,
                'notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Statut de paiement mis à jour: {$order->payment_status_label}",
                'order' => [
                    'id' => $order->id,
                    'payment_status' => $order->payment_status,
                    'payment_status_label' => $order->payment_status_label,
                    'status' => $order->status,
                    'status_label' => $order->status_label
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating payment status:', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'requested_payment_status' => $request->payment_status ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut de paiement'
            ], 500);
        }
    }

    /**
     * Ajouter/Modifier le numéro de suivi d'une commande
     */
    public function updateTrackingNumber(Request $request, Order $order)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tracking_number' => 'required|string|max:100',
                'carrier' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [
                'tracking_number' => $request->tracking_number
            ];

            // Mettre à jour les métadonnées avec les infos de suivi
            $metadata = $order->metadata ?? [];
            $metadata['tracking'] = [
                'number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'added_by_admin' => true,
                'added_at' => now()->toISOString(),
                'admin_id' => $request->user()->id,
                'notes' => $request->notes
            ];
            $updateData['metadata'] = $metadata;

            // Mettre à jour le statut à "shipped" si pas encore fait
            if (!in_array($order->status, ['shipped', 'delivered'])) {
                $updateData['status'] = 'shipped';
                $updateData['shipped_at'] = now();
            }

            $order->update($updateData);

            // Log de l'action
            Log::info('Tracking number added by admin:', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'admin_id' => $request->user()->id,
                'admin_email' => $request->user()->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Numéro de suivi enregistré avec succès',
                'order' => [
                    'id' => $order->id,
                    'tracking_number' => $order->tracking_number,
                    'status' => $order->status,
                    'status_label' => $order->status_label
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating tracking number:', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du numéro de suivi'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des commandes
     */
    public function getStatistics(Request $request)
    {
        try {
            $period = $request->input('period', '30'); // derniers 30 jours par défaut
            $startDate = Carbon::now()->subDays($period);

            // Statistiques générales
            $totalOrders = Order::where('created_at', '>=', $startDate)->count();
            $totalRevenue = Order::where('created_at', '>=', $startDate)
                ->where('payment_status', 'completed')
                ->sum('total_amount');

            // Commandes par statut
            $ordersByStatus = Order::selectRaw('status, count(*) as count')
                ->where('created_at', '>=', $startDate)
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Paiements par statut
            $paymentsByStatus = Order::selectRaw('payment_status, count(*) as count')
                ->where('created_at', '>=', $startDate)
                ->groupBy('payment_status')
                ->pluck('count', 'payment_status')
                ->toArray();

            // Revenus par jour (derniers 30 jours)
            $revenueByDay = Order::selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders')
                ->where('created_at', '>=', $startDate)
                ->where('payment_status', 'completed')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Top des livres vendus
            $topBooks = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->select('book_title', 'book_author', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(total_price) as total_revenue'))
                ->where('orders.created_at', '>=', $startDate)
                ->where('orders.payment_status', 'completed')
                ->groupBy('book_title', 'book_author')
                ->orderBy('total_sold', 'desc')
                ->limit(10)
                ->get();

            // Méthodes de paiement
            $paymentMethods = Order::selectRaw('payment_method, count(*) as count, SUM(total_amount) as revenue')
                ->where('created_at', '>=', $startDate)
                ->where('payment_status', 'completed')
                ->groupBy('payment_method')
                ->get();

            $stats = [
                'period_days' => $period,
                'total_orders' => $totalOrders,
                'total_revenue' => round($totalRevenue, 2),
                'average_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,

                // Statuts actuels (tous)
                'current_status_counts' => [
                    'pending' => Order::where('status', 'pending')->count(),
                    'confirmed' => Order::where('status', 'confirmed')->count(),
                    'processing' => Order::where('status', 'processing')->count(),
                    'shipped' => Order::where('status', 'shipped')->count(),
                    'delivered' => Order::where('status', 'delivered')->count(),
                    'cancelled' => Order::where('status', 'cancelled')->count(),
                ],

                'current_payment_counts' => [
                    'pending' => Order::where('payment_status', 'pending')->count(),
                    'processing' => Order::where('payment_status', 'processing')->count(),
                    'completed' => Order::where('payment_status', 'completed')->count(),
                    'failed' => Order::where('payment_status', 'failed')->count(),
                    'refunded' => Order::where('payment_status', 'refunded')->count(),
                ],

                // Données pour la période sélectionnée
                'orders_by_status' => $ordersByStatus,
                'payments_by_status' => $paymentsByStatus,
                'revenue_by_day' => $revenueByDay,
                'top_books' => $topBooks,
                'payment_methods' => $paymentMethods,

                // Statistiques supplémentaires
                'digital_vs_physical' => [
                    'digital_orders' => DB::table('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('book_type', 'digital')
                        ->where('orders.created_at', '>=', $startDate)
                        ->where('orders.payment_status', 'completed')
                        ->sum('quantity'),
                    'physical_orders' => DB::table('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('book_type', 'physical')
                        ->where('orders.created_at', '>=', $startDate)
                        ->where('orders.payment_status', 'completed')
                        ->sum('quantity')
                ]
            ];

            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order statistics:', [
                'error' => $e->getMessage(),
                'period' => $request->input('period', '30')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques'
            ], 500);
        }
    }

    /**
     * Supprimer définitivement une commande (action dangereuse)
     */
    public function destroy(Request $request, Order $order)
    {
        try {
            // Vérifications de sécurité
            if (in_array($order->status, ['processing', 'shipped', 'delivered'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer une commande en cours ou livrée'
                ], 422);
            }

            if ($order->payment_status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer une commande payée'
                ], 422);
            }

            DB::beginTransaction();

            // Log de la suppression
            Log::warning('Order deleted by admin:', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total_amount,
                'admin_id' => $request->user()->id,
                'admin_email' => $request->user()->email
            ]);

            $order->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande supprimée définitivement'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting order:', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la commande'
            ], 500);
        }
    }

    /**
     * Export des commandes au format CSV
     */
    public function export(Request $request)
    {
        try {
            $query = Order::with(['user', 'items'])
                ->orderBy('created_at', 'desc');

            // Appliquer les filtres s'ils existent
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_status') && $request->payment_status) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $orders = $query->get();

            // Générer le contenu CSV
            $csvData = [];
            $csvData[] = [
                'Numéro commande',
                'Client',
                'Email',
                'Statut commande',
                'Statut paiement',
                'Méthode paiement',
                'Sous-total',
                'Frais livraison',
                'Réduction',
                'Total',
                'Devise',
                'Articles',
                'Date création',
                'Date paiement',
                'Date expédition',
                'Date livraison',
                'Numéro suivi',
                'Ville livraison',
                'Code promo'
            ];

            foreach ($orders as $order) {
                $shippingAddress = $order->shipping_address;

                $csvData[] = [
                    $order->order_number,
                    $order->user ? $order->user->name : 'Utilisateur supprimé',
                    $order->user ? $order->user->email : 'N/A',
                    $order->status_label,
                    $order->payment_status_label,
                    $this->getPaymentMethodLabel($order->payment_method),
                    $order->subtotal,
                    $order->shipping_cost,
                    $order->discount_amount,
                    $order->total_amount,
                    $order->currency,
                    $order->items->count(),
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->payment_completed_at ? $order->payment_completed_at->format('Y-m-d H:i:s') : '',
                    $order->shipped_at ? $order->shipped_at->format('Y-m-d H:i:s') : '',
                    $order->delivered_at ? $order->delivered_at->format('Y-m-d H:i:s') : '',
                    $order->tracking_number ?? '',
                    $shippingAddress['city'] ?? '',
                    $order->promo_code ?? ''
                ];
            }

            // Créer le fichier CSV
            $filename = 'commandes_' . date('Y-m-d_H-i-s') . '.csv';
            $temp = tmpfile();
            $tempPath = stream_get_meta_data($temp)['uri'];

            $file = fopen($tempPath, 'w');
            // Ajouter le BOM UTF-8 pour Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($csvData as $row) {
                fputcsv($file, $row, ';'); // Utiliser ';' comme séparateur pour Excel français
            }
            fclose($file);

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            return response()->download($tempPath, $filename, $headers)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error exporting orders:', [
                'error' => $e->getMessage(),
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export des commandes'
            ], 500);
        }
    }

    /**
     * Obtenir les actions en masse disponibles
     */
    public function bulkActions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|in:update_status,update_payment_status,export_selected,delete_selected',
                'order_ids' => 'required|array|min:1',
                'order_ids.*' => 'exists:orders,id',
                'status' => 'required_if:action,update_status|in:pending,confirmed,processing,shipped,delivered,cancelled',
                'payment_status' => 'required_if:action,update_payment_status|in:pending,processing,completed,failed,refunded'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $orderIds = $request->order_ids;
            $action = $request->action;
            $results = [];

            DB::beginTransaction();

            switch ($action) {
                case 'update_status':
                    foreach ($orderIds as $orderId) {
                        $order = Order::find($orderId);
                        if ($order) {
                            $oldStatus = $order->status;

                            // Vérifier si le changement est autorisé
                            if ($this->canUpdateStatus($order, $request->status)) {
                                $order->update(['status' => $request->status]);
                                $results[] = [
                                    'order_id' => $orderId,
                                    'success' => true,
                                    'message' => "Statut mis à jour pour {$order->order_number}"
                                ];

                                // Log
                                Log::info("Bulk status update by admin", [
                                    'order_id' => $orderId,
                                    'old_status' => $oldStatus,
                                    'new_status' => $request->status,
                                    'admin_id' => $request->user()->id
                                ]);
                            } else {
                                $results[] = [
                                    'order_id' => $orderId,
                                    'success' => false,
                                    'message' => "Changement non autorisé pour {$order->order_number}"
                                ];
                            }
                        }
                    }
                    break;

                case 'update_payment_status':
                    foreach ($orderIds as $orderId) {
                        $order = Order::find($orderId);
                        if ($order) {
                            $oldPaymentStatus = $order->payment_status;

                            if ($this->canUpdatePaymentStatus($order, $request->payment_status)) {
                                $updateData = ['payment_status' => $request->payment_status];

                                if ($request->payment_status === 'completed') {
                                    $updateData['payment_completed_at'] = now();
                                }

                                $order->update($updateData);
                                $results[] = [
                                    'order_id' => $orderId,
                                    'success' => true,
                                    'message' => "Paiement mis à jour pour {$order->order_number}"
                                ];

                                Log::info("Bulk payment status update by admin", [
                                    'order_id' => $orderId,
                                    'old_payment_status' => $oldPaymentStatus,
                                    'new_payment_status' => $request->payment_status,
                                    'admin_id' => $request->user()->id
                                ]);
                            } else {
                                $results[] = [
                                    'order_id' => $orderId,
                                    'success' => false,
                                    'message' => "Changement de paiement non autorisé pour {$order->order_number}"
                                ];
                            }
                        }
                    }
                    break;

                case 'delete_selected':
                    foreach ($orderIds as $orderId) {
                        $order = Order::find($orderId);
                        if ($order) {
                            if ($this->canDeleteOrder($order)) {
                                $orderNumber = $order->order_number;
                                $order->delete();
                                $results[] = [
                                    'order_id' => $orderId,
                                    'success' => true,
                                    'message' => "Commande {$orderNumber} supprimée"
                                ];

                                Log::warning("Order deleted by admin (bulk)", [
                                    'order_id' => $orderId,
                                    'order_number' => $orderNumber,
                                    'admin_id' => $request->user()->id
                                ]);
                            } else {
                                $results[] = [
                                    'order_id' => $orderId,
                                    'success' => false,
                                    'message' => "Suppression non autorisée pour {$order->order_number}"
                                ];
                            }
                        }
                    }
                    break;
            }

            DB::commit();

            $successCount = collect($results)->where('success', true)->count();
            $totalCount = count($results);

            return response()->json([
                'success' => true,
                'message' => "Action effectuée: {$successCount}/{$totalCount} commandes traitées",
                'results' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error in bulk actions:', [
                'error' => $e->getMessage(),
                'action' => $request->action ?? 'unknown',
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'action en masse'
            ], 500);
        }
    }

    /**
     * Méthodes utilitaires privées
     */
    private function getPaymentMethodLabel($method)
    {
        $labels = [
            'cash_on_delivery' => 'Paiement à la livraison',
            'paypal' => 'PayPal',
            'bank_card' => 'Carte bancaire'
        ];

        return $labels[$method] ?? $method;
    }

    private function canUpdateStatus($order, $newStatus)
    {
        // Règles métier pour les changements de statut
        if ($order->status === 'delivered' && $newStatus !== 'delivered') {
            return false;
        }

        if ($order->status === 'cancelled' && !in_array($newStatus, ['cancelled', 'refunded'])) {
            return false;
        }

        if ($order->status === 'refunded' && $newStatus !== 'refunded') {
            return false;
        }

        return true;
    }

    private function canUpdatePaymentStatus($order, $newPaymentStatus)
    {
        // Règles métier pour les changements de statut de paiement
        if ($order->payment_status === 'completed' && $newPaymentStatus === 'pending') {
            return false;
        }

        return true;
    }

    private function canDeleteOrder($order)
    {
        // Règles pour la suppression
        if (in_array($order->status, ['processing', 'shipped', 'delivered'])) {
            return false;
        }

        if ($order->payment_status === 'completed') {
            return false;
        }

        return true;
    }
}