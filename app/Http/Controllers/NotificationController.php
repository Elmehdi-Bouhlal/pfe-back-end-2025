<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use Illuminate\Support\Facades\Schema;
class NotificationController extends Controller
{
    /**
     * Obtenir les compteurs de notifications
     */
    public function getCounts(Request $request)
    {
        try {
            $user = $request->user();

            // Compteur de favoris (book_likes)
            $favoritesCount = DB::table('book_likes')
                ->where('user_id', $user->id)
                ->count();

            // Compteur de notifications non lues (notifications - si la table existe)
            $notificationsCount = 0;
            if (Schema::hasTable('notifications')) {
                $notificationsCount = DB::table('notifications')
                    ->where('notifiable_id', $user->id)
                    ->where('notifiable_type', 'App\Models\User')
                    ->whereNull('read_at')
                    ->count();
            }

            // Compteur de messages non lus (book_messages)
            $unreadMessagesCount = DB::table('book_messages')
                ->join('book_conversations', 'book_messages.conversation_id', '=', 'book_conversations.id')
                ->where(function($query) use ($user) {
                    $query->where('book_conversations.buyer_id', $user->id)
                          ->orWhere('book_conversations.seller_id', $user->id);
                })
                ->where('book_messages.sender_id', '!=', $user->id)
                ->whereNull('book_messages.read_at')
                ->count();

            // Compteur de commandes en attente (orders)
            $pendingOrdersCount = Order::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'confirmed', 'processing'])
                ->count();

            return response()->json([
                'success' => true,
                'counts' => [
                    'favorites' => $favoritesCount,
                    'notifications' => $notificationsCount,
                    'unreadMessages' => $unreadMessagesCount,
                    'pendingOrders' => $pendingOrdersCount
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('error notification : ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des compteurs'
            ], 500);
        }
    }

    /**
     * Obtenir les notifications de l'utilisateur
     */
    public function getNotifications(Request $request)
    {
        try {
            $user = $request->user();
            $limit = $request->input('limit', 10);

            // Si la table notifications n'existe pas, retourner des notifications simulées ou vides
            if (!Schema::hasTable('notifications')) {
                return response()->json([
                    'success' => true,
                    'notifications' => []
                ]);
            }

            $notifications = DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', 'App\Models\User')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($notification) {
                    $data = json_decode($notification->data, true);
                    return [
                        'id' => $notification->id,
                        'type' => $data['type'] ?? 'system',
                        'title' => $data['title'] ?? 'Notification',
                        'message' => $data['message'] ?? '',
                        'read_at' => $notification->read_at,
                        'created_at' => $notification->created_at,
                        'data' => $data
                    ];
                });

            return response()->json([
                'success' => true,
                'notifications' => $notifications
            ]);

        } catch (\Exception $e) {
            \Log::error('error fetching notifications : ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications'
            ], 500);
        }
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(Request $request, $notificationId)
    {
        try {
            $user = $request->user();

            if (!Schema::hasTable('notifications')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Système de notifications non disponible'
                ], 404);
            }

            $updated = DB::table('notifications')
                ->where('id', $notificationId)
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', 'App\Models\User')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue'
            ]);

        } catch (\Exception $e) {
            \Log::error('error marking notification as read : ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->user();

            if (!Schema::hasTable('notifications')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Système de notifications non disponible'
                ], 404);
            }

            DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', 'App\Models\User')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications ont été marquées comme lues'
            ]);

        } catch (\Exception $e) {
            \Log::error('error marking all notifications as read : ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Créer une notification de test (pour développement)
     */
    public function createTestNotification(Request $request)
    {
        try {
            $user = $request->user();

            if (!Schema::hasTable('notifications')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table notifications non disponible'
                ], 404);
            }

            $notificationData = [
                'type' => 'test',
                'title' => 'Notification de test',
                'message' => 'Ceci est une notification de test pour vérifier le système.'
            ];

            DB::table('notifications')->insert([
                'id' => \Str::uuid(),
                'type' => 'App\Notifications\TestNotification',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $user->id,
                'data' => json_encode($notificationData),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification de test créée'
            ]);

        } catch (\Exception $e) {
            \Log::error('error creating test notification : ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la notification'
            ], 500);
        }
    }
}