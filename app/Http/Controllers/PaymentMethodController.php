<?php
// app/Http/Controllers/PaymentMethodController.php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Http\Requests\PaymentMethodRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
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
                        'is_verified' => !is_null($method->verified_at),
                        'created_at' => $method->created_at
                    ];
                });

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

    public function store(PaymentMethodRequest $request)
    {
        try {
            $data = $request->validated();
            $data['user_id'] = $request->user()->id;

            // Encrypt sensitive details
            if (isset($data['details'])) {
                $data['details'] = encrypt($data['details']);
            }

            $paymentMethod = PaymentMethod::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Méthode de paiement ajoutée avec succès',
                'payment_method' => [
                    'id' => $paymentMethod->id,
                    'type' => $paymentMethod->type,
                    'provider_name' => $paymentMethod->provider_name,
                    'display_name' => $paymentMethod->display_name,
                    'icon' => $paymentMethod->icon,
                    'is_default' => $paymentMethod->is_default,
                    'is_verified' => !is_null($paymentMethod->verified_at)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating payment method:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout de la méthode de paiement'
            ], 500);
        }
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod)
    {
        try {
            // Verify ownership
            if ($paymentMethod->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Méthode de paiement non trouvée'
                ], 404);
            }

            $data = $request->validated();

            // Encrypt sensitive details
            if (isset($data['details'])) {
                $data['details'] = encrypt($data['details']);
            }

            $paymentMethod->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Méthode de paiement mise à jour avec succès',
                'payment_method' => [
                    'id' => $paymentMethod->id,
                    'type' => $paymentMethod->type,
                    'provider_name' => $paymentMethod->provider_name,
                    'display_name' => $paymentMethod->display_name,
                    'icon' => $paymentMethod->icon,
                    'is_default' => $paymentMethod->is_default,
                    'is_verified' => !is_null($paymentMethod->verified_at)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating payment method:', [
                'error' => $e->getMessage(),
                'payment_method_id' => $paymentMethod->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la méthode de paiement'
            ], 500);
        }
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod)
    {
        try {
            // Verify ownership
            if ($paymentMethod->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Méthode de paiement non trouvée'
                ], 404);
            }

            // Check if this is the default method and there are other methods
            if ($paymentMethod->is_default) {
                $otherMethods = PaymentMethod::where('user_id', $request->user()->id)
                    ->where('id', '!=', $paymentMethod->id)
                    ->active()
                    ->count();

                if ($otherMethods > 0) {
                    // Set another method as default
                    PaymentMethod::where('user_id', $request->user()->id)
                        ->where('id', '!=', $paymentMethod->id)
                        ->active()
                        ->first()
                        ->update(['is_default' => true]);
                }
            }

            $paymentMethod->delete();

            return response()->json([
                'success' => true,
                'message' => 'Méthode de paiement supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting payment method:', [
                'error' => $e->getMessage(),
                'payment_method_id' => $paymentMethod->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la méthode de paiement'
            ], 500);
        }
    }

    public function setDefault(Request $request, PaymentMethod $paymentMethod)
    {
        try {
            // Verify ownership
            if ($paymentMethod->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Méthode de paiement non trouvée'
                ], 404);
            }

            // Remove default from all other methods
            PaymentMethod::where('user_id', $request->user()->id)
                ->where('id', '!=', $paymentMethod->id)
                ->update(['is_default' => false]);

            // Set this method as default
            $paymentMethod->update(['is_default' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Méthode de paiement définie par défaut'
            ]);
        } catch (\Exception $e) {
            Log::error('Error setting default payment method:', [
                'error' => $e->getMessage(),
                'payment_method_id' => $paymentMethod->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la définition de la méthode par défaut'
            ], 500);
        }
    }
}