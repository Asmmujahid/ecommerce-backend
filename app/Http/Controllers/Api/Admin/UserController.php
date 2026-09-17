<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * GET ALL CUSTOMERS
     */
    public function index()
    {
        $customers = User::role('customer')->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Customer list fetched successfully',
            'data' => $customers
        ]);
    }

    /**
     * SHOW SINGLE CUSTOMER
     */
    public function show($id)
    {
        $customer = User::role('customer')->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    /**
     * UPDATE CUSTOMER STATUS (active/inactive)
     */
   public function update(Request $request, $id)
{
    $customer = User::role('customer')->find($id);

    if (!$customer) {
        return response()->json([
            'success' => false,
            'message' => 'Customer not found'
        ], 404);
    }

    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'nullable|string|max:20',
        'status' => 'required|boolean',
    ]);

    $customer->update([
        'name' => $request->name,
        'email' => $request->email,
        'phone' => $request->phone,
        'status' => $request->status,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Customer updated successfully',
        'data' => $customer
    ]);
}

    /**
     * DELETE CUSTOMER
     */
    public function destroy($id)
    {
        $customer = User::role('customer')->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
    }
}