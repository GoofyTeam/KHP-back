<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function updateInfo(Request $request)
{
    $user = $request->user();

    $validatedData = $request->validate([
        'name' => 'nullable|string|max:255',
        'email' => 'nullable|email|unique:users,email,' . $user->id,
    ]);

    // Mise à jour uniquement des champs réellement envoyés et non vides
    $dataToUpdate = [];

    if ($request->filled('name')) {
        $dataToUpdate['name'] = $validatedData['name'];
    }

    if ($request->filled('email')) {
        $dataToUpdate['email'] = $validatedData['email'];
    }

    if (!empty($dataToUpdate)) {
        $user->update($dataToUpdate);
    }

    return response()->json([
        'message' => 'User information updated successfully',
        'user' => $user->fresh()
    ]);
}

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validatedData = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($validatedData['current_password'], $user->password)) {
            return response()->json(['message' => 'Password is the same as the current one'], 401);
        }

        $user->password = Hash::make($validatedData['new_password']);
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }
}
