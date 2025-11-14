<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
        /**
     * Get authenticated user profile
     */
    public function index()
    {
        try {
            $user = Auth::user();
            
            // Add computed fields for the frontend
            $userData = $user->toArray();
            
            // Add statistics using the new methods
            $userData['stats'] = [
                'booksListed' => $user->books()->where('status', 'published')->count(),
                'booksSold' => $user->books()->where('status', 'sold')->count(),
                'rating' => round($user->averageRating(), 1), // Utilise la nouvelle méthode
                'reviews' => $user->reviewsReceived()->count(), // Utilise la nouvelle méthode
            ];

            // Add additional profile info
            $userData['full_name'] = $user->full_name;
            $userData['avatar_url'] = $user->avatar_url;

            return response()->json([
                'success' => true,
                'user' => $userData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore(Auth::id())
            ],
            'phone' => 'nullable|string|max:20',
            'adress' => 'nullable|string|max:500',
            'notifications' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de validation invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            $user->update([
                'name' => $request->input('name'),
                'last_name' => $request->input('last_name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'adress' => $request->input('adress'),
                'notifications' => $request->input('notifications', false)
            ]);

            // Return updated user data with stats using new methods
            $userData = $user->fresh()->toArray();
            $userData['stats'] = [
                'booksListed' => $user->books()->where('status', 'published')->count(),
                'booksSold' => $user->books()->where('status', 'sold')->count(),
                'rating' => round($user->averageRating(), 1),
                'reviews' => $user->reviewsReceived()->count(),
            ];
            $userData['full_name'] = $user->full_name;
            $userData['avatar_url'] = $user->avatar_url;

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'user' => $userData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user avatar
     */
    public function updateAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120' // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Image invalide',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            
            // Update user avatar path
            $user->update(['avatar' => $avatarPath]);

            // Return full URL for the avatar using the model accessor
            $avatarUrl = $user->avatar_url;

            return response()->json([
                'success' => true,
                'message' => 'Avatar mis à jour avec succès',
                'avatar_url' => $avatarUrl,
                'avatar_path' => $avatarPath
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de validation invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            // Verify current password
            if (!Hash::check($request->input('current_password'), $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect'
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->input('new_password'))
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe mis à jour avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics - utilise la nouvelle méthode du modèle
     */
    public function getStats()
    {
        try {
            $user = Auth::user();

            // Utilise la méthode getProfileStats() du modèle
            $stats = $user->getProfileStats();

            // Ajoute des informations supplémentaires
            $stats['memberSince'] = $user->created_at->format('Y-m-d');
            $stats['reputationLevel'] = $user->getReputationLevel();
            $stats['canBeRated'] = $user->canBeRated();

            return response()->json([
                'success' => true,
                'stats' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user activity summary
     */
    public function getActivitySummary()
    {
        try {
            $user = Auth::user();

            $activitySummary = $user->getActivitySummary();

            return response()->json([
                'success' => true,
                'activity' => $activitySummary
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de l\'activité',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user account
     */
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe requis pour supprimer le compte',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            // Verify password
            if (!Hash::check($request->input('password'), $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect'
                ], 422);
            }

            // Delete user avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Delete user (this will cascade delete related records if properly configured)
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du compte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user public profile (pour d'autres utilisateurs)
     */
    public function getPublicProfile($userId)
    {
        try {
            $user = User::findOrFail($userId);

            // Ne retourne que les informations publiques
            $publicData = [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'full_name' => $user->full_name,
                'member_since' => $user->created_at->format('Y-m-d'),
                'reputation_level' => $user->getReputationLevel(),
                'stats' => [
                    'rating' => round($user->averageRating(), 1),
                    'reviews' => $user->reviewsReceived()->count(),
                    'booksListed' => $user->books()->where('status', 'published')->count(),
                    'booksSold' => $user->books()->where('status', 'sold')->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'user' => $publicData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return response()->json(['success' => true], 200);
    }

    public function login()
    {
        $credentials = request()->only('email', 'password');

        if (!Auth::guard('web')->attempt($credentials)) {
            return response()->json(['success' => false ,'message' => 'Incorrect Creddentiels'], 401);
        }

        request()->session()->regenerate();

        return response()->json(['success' => true , 'message' => 'User has been connected with success', 'user' => Auth::user()]);
    }

    public function userList(){
        tryCatchError(function(){
            $allUser = User::All();
            return response()->json(['success'=>true , 'users' => (object) $allUser],200);
        });
    }
}
