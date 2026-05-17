<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disposition;
use App\Models\Letter;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return response()->json([
                'stats' => [
                    'total_letters' => Letter::count(),
                    'processing_letters' => Letter::where('status', 'processing')->count(),
                    'archived_letters' => Letter::where('status', 'archived')->count(),
                    'total_users' => User::count(),
                ],
                'recent_letters' => Letter::latest()->take(5)->get(),
            ]);
        } else {
            return response()->json([
                'stats' => [
                    'incoming_dispositions' => Disposition::where('to_user_id', $user->id)->count(),
                    'unread_dispositions' => Disposition::where('to_user_id', $user->id)->where('status', 'pending')->count(),
                    'outgoing_dispositions' => Disposition::where('from_user_id', $user->id)->count(),
                ],
                'recent_incoming' => Disposition::where('to_user_id', $user->id)
                    ->with('letter', 'sender')
                    ->latest()
                    ->take(5)
                    ->get(),
            ]);
        }
    }
}
