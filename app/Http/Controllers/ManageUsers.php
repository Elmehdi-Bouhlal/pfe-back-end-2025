<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ManageUsers extends Controller
{
    /**
     * 
     */
    public function deleteUser($email)
    {
        try{
            $user = User::where('email',$email)->first() ;
            if(!$user){
                return response()->json(['success' => false , 'error' => 'user has not be found'],200) ;
            }
            $user->delete();
            return response()->json(['success' => true , "message" => "user has been removed with success"],200) ;
        }catch(\Exception $err){
            return response()->json(["success" => false , "error" => $err->getMessage()],200);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */

    public function listUser()
    {
        try{
            $users = User::with('roles')->get() ;
            return response()->json(['success' => true , 'data' => $users]);
        }catch(\Exception $err){
            return response()->json(['success' => true , 'error' => $err->getMessage()]) ;
        }
    }

    /**
     * Summary of addUser
     * @param \App\Http\Requests\StoreUserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addUser(StoreUserRequest $request)      
    {

        try {
            DB::beginTransaction();

            $data = $request->validated();
            
            $user = User::create([
                'name' => $data['name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => (int) $data['phone'],
                'adress' => $data['address'] ?? '',
                'notifications' => $data['notifications'] ?? true,
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole($data['role']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'user has been added with success',
                'user' => $user
            ], 201);

        } catch (\Exception $err) {
            DB::rollBack(); 

            return response()->json([
                'success' => false,
                'error' => $err->getMessage()
            ], 500); 
        }
    }
}
