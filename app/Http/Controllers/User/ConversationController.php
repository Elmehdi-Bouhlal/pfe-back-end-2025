<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookConversation;
use App\Models\BookMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConversationController extends Controller
{
    /**
     * Get user's conversations (inbox)
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);
            $filter = $request->get('filter', 'all'); // all, unread, buyer, seller
            $search = $request->get('search', '');

            $query = BookConversation::forUser($user->id)
                ->with([
                    'book:id,title,user_id',
                    'book.images' => function($q) {
                        $q->where('is_primary', true)->select('id', 'book_id', 'image_path');
                    },
                    'buyer:id,name,avatar',
                    'seller:id,name,avatar',
                    'latestMessage:id,conversation_id,sender_id,message,created_at'
                ])
                ->withCount(['messages'])
                ->orderBy('last_message_at', 'desc');

            // Apply filters
            switch ($filter) {
                case 'unread':
                    $query->withUnreadFor($user->id);
                    break;
                case 'buyer':
                    $query->where('buyer_id', $user->id);
                    break;
                case 'seller':
                    $query->where('seller_id', $user->id);
                    break;
            }

            // Search functionality
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->whereHas('book', function($bookQ) use ($search) {
                          $bookQ->where('title', 'like', "%{$search}%");
                      })
                      ->orWhereHas('messages', function($msgQ) use ($search) {
                          $msgQ->where('message', 'like', "%{$search}%");
                      });
                });
            }

            $conversations = $query->paginate($perPage);

            // Transform data for frontend
            $transformedConversations = $conversations->getCollection()->map(function($conversation) use ($user) {
                $otherUser = $conversation->getOtherUser($user->id);
                $isUnread = $conversation->isUnreadFor($user->id);
                $unreadCount = $conversation->getUnreadCountFor($user);
                
                return [
                    'id' => $conversation->id,
                    'book' => [
                        'id' => $conversation->book->id,
                        'title' => $conversation->book->title,
                        'image' => $conversation->book->images->first()?->image_path ?? null,
                    ],
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar' => $otherUser->avatar,
                    ],
                    'user_role' => $conversation->buyer_id === $user->id ? 'buyer' : 'seller',
                    'subject' => "Conversation à propos de \"{$conversation->book->title}\"",
                    'status' => $conversation->status,
                    'is_unread' => $isUnread,
                    'unread_count' => $unreadCount,
                    'last_message' => $conversation->latestMessage ? [
                        'message' => $conversation->latestMessage->message,
                        'sender_id' => $conversation->latestMessage->sender_id,
                        'created_at' => $conversation->latestMessage->created_at,
                        'is_from_me' => $conversation->latestMessage->sender_id === $user->id,
                    ] : null,
                    'last_message_at' => $conversation->last_message_at,
                    'messages_count' => $conversation->messages_count,
                    'created_at' => $conversation->created_at,
                ];
            });

            // Calculate stats
            $totalConversations = BookConversation::forUser($user->id)->count();
            $unreadConversations = BookConversation::forUser($user->id)->withUnreadFor($user->id)->count();
            $activeBuyer = BookConversation::where('buyer_id', $user->id)->where('status', 'active')->count();
            $activeSeller = BookConversation::where('seller_id', $user->id)->where('status', 'active')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'conversations' => $transformedConversations,
                    'pagination' => [
                        'current_page' => $conversations->currentPage(),
                        'per_page' => $conversations->perPage(),
                        'total' => $conversations->total(),
                        'last_page' => $conversations->lastPage(),
                    ],
                    'stats' => [
                        'total_conversations' => $totalConversations,
                        'unread_conversations' => $unreadConversations,
                        'active_conversations' => $activeBuyer + $activeSeller,
                        'as_buyer' => BookConversation::where('buyer_id', $user->id)->count(),
                        'as_seller' => BookConversation::where('seller_id', $user->id)->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des conversations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get conversation messages
     */
    public function show(Request $request, $conversationId)
    {
        try {
            $user = Auth::user();
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 50);

            $conversation = BookConversation::forUser($user->id)
                ->with([
                    'book:id,title,price,currency,user_id',
                    'book.images' => function($q) {
                        $q->where('is_primary', true)->select('id', 'book_id', 'image_path');
                    },
                    'buyer:id,name,avatar',
                    'seller:id,name,avatar'
                ])
                ->findOrFail($conversationId);

            $messages = $conversation->messages()
                ->with('sender:id,name,avatar')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Mark conversation as read for current user
            $conversation->markAsReadFor($user->id);

            $otherUser = $conversation->getOtherUser($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation' => [
                        'id' => $conversation->id,
                        'book' => [
                            'id' => $conversation->book->id,
                            'title' => $conversation->book->title,
                            'price' => $conversation->book->price ?? 0,
                            'currency' => $conversation->book->currency ?? 'MAD',
                            'image' => $conversation->book->images->first()?->image_path ?? null,
                        ],
                        'other_user' => [
                            'id' => $otherUser->id,
                            'name' => $otherUser->name,
                            'avatar' => $otherUser->avatar,
                        ],
                        'user_role' => $conversation->buyer_id === $user->id ? 'buyer' : 'seller',
                        'subject' => "Conversation à propos de \"{$conversation->book->title}\"",
                        'status' => $conversation->status,
                        'created_at' => $conversation->created_at,
                    ],
                    'messages' => $messages->getCollection()->map(function($message) use ($user) {
                        return [
                            'id' => $message->id,
                            'message' => $message->message,
                            'message_type' => $message->message_type,
                            'attachments' => [], // Pas d'attachments dans votre DB actuelle
                            'is_system_message' => false, // Pas de messages système dans votre DB actuelle
                            'sender' => [
                                'id' => $message->sender->id,
                                'name' => $message->sender->name,
                                'avatar' => $message->sender->avatar,
                            ],
                            'is_from_me' => $message->sender_id === $user->id,
                            'created_at' => $message->created_at,
                        ];
                    })->reverse()->values(),
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total(),
                        'last_page' => $messages->lastPage(),
                        'has_more_pages' => $messages->hasMorePages(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de la conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start a new conversation about a book
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'book_id' => 'required|exists:books,id',
            'message' => 'required|string|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $book = Book::findOrFail($request->book_id);

            // Check if user is trying to message themselves
            if ($book->user_id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas envoyer un message à propos de votre propre livre'
                ], 403);
            }

            // Check if conversation already exists
            $existingConversation = BookConversation::where('book_id', $book->id)
                ->where('buyer_id', $user->id)
                ->where('seller_id', $book->user_id)
                ->first();

            if ($existingConversation) {
                // Add message to existing conversation
                $message = $existingConversation->messages()->create([
                    'sender_id' => $user->id,
                    'message' => $request->message,
                    'message_type' => 'text',
                    'is_read' => false
                ]);

                $existingConversation->update(['last_message_at' => now()]);

                return response()->json([
                    'success' => true,
                    'message' => 'Message envoyé avec succès',
                    'data' => [
                        'conversation_id' => $existingConversation->id,
                        'message_id' => $message->id
                    ]
                ]);
            }

            DB::beginTransaction();

            // Create new conversation
            $conversation = BookConversation::create([
                'book_id' => $book->id,
                'buyer_id' => $user->id,
                'seller_id' => $book->user_id,
                'status' => 'active',
                'last_message_at' => now()
            ]);

            // Create first message
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'message' => $request->message,
                'message_type' => 'text',
                'is_read' => false
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conversation créée avec succès',
                'data' => [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message in existing conversation
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
            'message_type' => 'in:text,image,offer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            $conversation = BookConversation::forUser($user->id)
                ->where('status', 'active')
                ->findOrFail($conversationId);

            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'message' => $request->message,
                'message_type' => $request->get('message_type', 'text'),
                'is_read' => false
            ]);

            // Update conversation last message time
            $conversation->update(['last_message_at' => now()]);

            $message->load('sender:id,name,avatar');

            return response()->json([
                'success' => true,
                'message' => 'Message envoyé avec succès',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'message' => $message->message,
                        'message_type' => $message->message_type,
                        'attachments' => [],
                        'sender' => [
                            'id' => $message->sender->id,
                            'name' => $message->sender->name,
                            'avatar' => $message->sender->avatar,
                        ],
                        'is_from_me' => true,
                        'created_at' => $message->created_at,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark conversation as read
     */
    public function markAsRead($conversationId)
    {
        try {
            $user = Auth::user();
            
            $conversation = BookConversation::forUser($user->id)
                ->findOrFail($conversationId);

            $conversation->markAsReadFor($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Conversation marquée comme lue'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all conversations as read
     */
    public function markAllAsRead()
    {
        try {
            $user = Auth::user();

            // Marquer tous les messages non lus comme lus
            BookMessage::whereHas('conversation', function($query) use ($user) {
                $query->forUser($user->id);
            })
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Toutes les conversations marquées comme lues'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archive a conversation
     */
    public function archive($conversationId)
    {
        try {
            $user = Auth::user();
            
            $conversation = BookConversation::forUser($user->id)
                ->findOrFail($conversationId);

            $conversation->update(['status' => 'closed']); // Utilise 'closed' au lieu de 'archived'

            return response()->json([
                'success' => true,
                'message' => 'Conversation archivée'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'archivage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Block/Close a conversation
     */
    public function destroy($conversationId)
    {
        try {
            $user = Auth::user();
            
            $conversation = BookConversation::forUser($user->id)
                ->findOrFail($conversationId);

            // Change status to blocked instead of deleting
            $conversation->update(['status' => 'blocked']);

            return response()->json([
                'success' => true,
                'message' => 'Conversation bloquée'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du blocage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get conversation stats for dashboard
     */
    public function getStats()
    {
        try {
            $user = Auth::user();

            $stats = [
                'total_conversations' => BookConversation::forUser($user->id)->count(),
                'unread_conversations' => BookConversation::forUser($user->id)->withUnreadFor($user->id)->count(),
                'active_conversations' => BookConversation::forUser($user->id)->where('status', 'active')->count(),
                'conversations_as_buyer' => BookConversation::where('buyer_id', $user->id)->count(),
                'conversations_as_seller' => BookConversation::where('seller_id', $user->id)->count(),
                'total_messages_sent' => BookMessage::where('sender_id', $user->id)->count(),
                'messages_today' => BookMessage::where('sender_id', $user->id)->whereDate('created_at', today())->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}