<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $status = $request->input('status');

            $query = Order::where('user_id', $request->user()->id)
                ->with([
                    'items.book' => function ($q) {
                        $q->select([
                            'id',
                            'title',
                            'author',
                            'price',
                            'currency',
                            'is_available',
                            'status',
                            'book_type',
                            'book_condition',
                        ])->with([
                            'images' => function ($q) {
                                $q->where('is_primary', true)->select('id', 'book_id', 'image_path');
                            },
                        ]);
                    },
                ])
                ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('status', $status);
            }

            $orders = $query->paginate($perPage);

            $ordersData = $orders->getCollection()->map(function ($order) {
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
                    'can_be_cancelled' => $order->canBeCancelled(),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'book_title' => $item->book_title,
                            'book_author' => $item->book_author,
                            'image_path' => $item->book?->images?->first()?->image_path
                                ?   $item->book->images->first()->image_path
                                : null,
                            'quantity' => $item->quantity,
                            'total_price' => $item->total_price,
                            'book_type' => $item->book_type,
                            'can_download' => $item->can_download,
                        ];
                    }),
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
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching orders:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Erreur lors du chargement des commandes',
                ],
                500,
            );
        }
    }

    public function show(Request $request, Order $order)
    {
        try {
            // Verify ownership
            if ($order->user_id !== $request->user()->id) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Commande non trouvée',
                    ],
                    404,
                );
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
                    'created_at' => $order->created_at,
                    'payment_completed_at' => $order->payment_completed_at,
                    'shipped_at' => $order->shipped_at,
                    'delivered_at' => $order->delivered_at,
                    'can_be_cancelled' => $order->canBeCancelled(),
                    'total_items' => $order->total_items,
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
                            'download_limit' => $item->download_limit,
                            'download_expires_at' => $item->download_expires_at,
                            'book' => $item->book
                                ? [
                                    'id' => $item->book->id,
                                    'title' => $item->book->title,
                                    'author' => $item->book->author,
                                    'image_url' => $item->book->image_url ?? null,
                                ]
                                : null,
                        ];
                    }),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching order details:', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Erreur lors du chargement des détails de la commande',
                ],
                500,
            );
        }
    }

    public function cancel(Request $request, Order $order)
    {
        try {
            // Verify ownership
            if ($order->user_id !== $request->user()->id) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Commande non trouvée',
                    ],
                    404,
                );
            }

            // Check if order can be cancelled
            if (!$order->canBeCancelled()) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Cette commande ne peut plus être annulée',
                    ],
                    422,
                );
            }

            $order->update([
                'status' => 'cancelled',
                'payment_status' => $order->payment_status === 'completed' ? 'refunded' : 'failed',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Commande annulée avec succès',
            ]);
        } catch (\Exception $e) {
            Log::error('Error cancelling order:', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Erreur lors de l\'annulation de la commande',
                ],
                500,
            );
        }
    }

    public function downloadDigitalBook(Request $request, Order $order, OrderItem $orderItem)
    {
        try {
            // Verify ownership
            if ($order->user_id !== $request->user()->id) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Commande non trouvée',
                    ],
                    404,
                );
            }

            // Verify order item belongs to order
            if ($orderItem->order_id !== $order->id) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Article non trouvé dans cette commande',
                    ],
                    404,
                );
            }

            // Check if item can be downloaded
            if (!$orderItem->can_download) {
                $reason = 'Téléchargement non autorisé';

                if ($orderItem->book_type !== 'digital') {
                    $reason = 'Ce n\'est pas un livre numérique';
                } elseif ($order->payment_status !== 'completed') {
                    $reason = 'Paiement non complété';
                } elseif ($orderItem->download_limit && $orderItem->download_count >= $orderItem->download_limit) {
                    $reason = 'Limite de téléchargement atteinte';
                } elseif ($orderItem->download_expires_at && $orderItem->download_expires_at->isPast()) {
                    $reason = 'Période de téléchargement expirée';
                }

                return response()->json(
                    [
                        'success' => false,
                        'message' => $reason,
                    ],
                    422,
                );
            }

            // Get the book file
            $book = $orderItem->book;
            if (!$book || !$book->file_path) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Fichier du livre non trouvé',
                    ],
                    404,
                );
            }

            // Check if file exists
            if (!Storage::disk('private')->exists($book->file_path)) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Fichier non disponible',
                    ],
                    404,
                );
            }

            // Increment download count
            $orderItem->incrementDownloadCount();

            // Log download
            Log::info('Digital book downloaded:', [
                'user_id' => $request->user()->id,
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'book_id' => $book->id,
                'download_count' => $orderItem->download_count,
            ]);

            // Return file download
            $filename = $book->title . '.' . ($book->file_format ?? 'pdf');
            $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);

            return Storage::disk('private')->download($book->file_path, $filename, [
                'Content-Type' => $this->getContentType($book->file_format),
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            Log::error('Error downloading digital book:', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
            ]);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Erreur lors du téléchargement',
                ],
                500,
            );
        }
    }

    private function getContentType($format)
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'epub' => 'application/epub+zip',
            'mobi' => 'application/x-mobipocket-ebook',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        return $mimeTypes[strtolower($format)] ?? 'application/octet-stream';
    }
}
