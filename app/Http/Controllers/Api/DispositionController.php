<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disposition;
use App\Models\Letter;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class DispositionController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    // Incoming dispositions for current user
    public function incoming(Request $request)
    {
        return Disposition::where('to_user_id', $request->user()->id)
            ->with(['letter', 'sender'])
            ->latest()
            ->get();
    }

    // Outgoing dispositions from current user
    public function outgoing(Request $request)
    {
        return Disposition::where('from_user_id', $request->user()->id)
            ->with(['letter', 'receiver'])
            ->latest()
            ->get();
    }

    public function show(Disposition $disposition)
    {
        // Check authorization
        $user = request()->user();
        if ($disposition->from_user_id !== $user->id && $disposition->to_user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $disposition->load(['letter', 'sender', 'receiver']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'letter_id' => 'required|exists:letters,id',
            'to_user_ids' => 'required|array', // Allow multiple recipients
            'to_user_ids.*' => 'distinct|exists:users,id',
            'type' => 'required|in:forward,disposition,lower',
            'note' => 'nullable|string',
        ]);

        $user = $request->user();

        // Only the admin (who registers letters) or a user who currently
        // holds the letter (it was disposed to them) may pass it on.
        $holdsLetter = Disposition::where('letter_id', $validated['letter_id'])
            ->where('to_user_id', $user->id)
            ->exists();

        if ($user->role !== 'admin' && ! $holdsLetter) {
            return response()->json([
                'message' => 'You can only disposition a letter that was disposed to you.',
            ], 403);
        }

        // A user cannot disposition a letter to themselves.
        if (in_array($user->id, $validated['to_user_ids'])) {
            return response()->json([
                'message' => 'You cannot disposition a letter to yourself.',
            ], 422);
        }

        $dispositions = [];

        foreach ($validated['to_user_ids'] as $toUserId) {
            $disposition = Disposition::create([
                'letter_id' => $validated['letter_id'],
                'from_user_id' => $request->user()->id,
                'to_user_id' => $toUserId,
                'type' => $validated['type'],
                'note' => $validated['note'] ?? null,
                'status' => 'pending',
            ]);
            $dispositions[] = $disposition;

            // Send notification (WA)
            $recipient = User::find($toUserId);
            if ($recipient && $recipient->phone_number) {
                $letter = Letter::find($validated['letter_id']);
                $sender = $request->user();

                $typeMap = [
                    'important' => 'Penting',
                    'ordinary' => 'Biasa',
                ];
                $classMap = [
                    'secret' => 'Rahasia',
                    'ordinary' => 'Biasa',
                ];
                $type = $typeMap[$letter->type] ?? $letter->type;
                $classification = $classMap[$letter->classification] ?? $letter->classification;

                $message = "Yth. {$recipient->name},\n\n";
                $message .= "Diberitahukan bahwa Anda telah menerima disposisi surat baru dari {$sender->name} dengan detail sebagai berikut:\n\n";
                $message .= "*DETAIL SURAT*\n";
                $message .= "Tanggal Surat : {$letter->letter_date}\n";
                $message .= "Nomor Surat   : {$letter->letter_number}\n";
                $message .= "Asal Surat    : {$letter->origin}\n";
                $message .= "Sifat         : {$type} / {$classification}\n";
                $message .= "Perihal       : {$letter->subject}\n\n";
                $message .= "*CATATAN DISPOSISI*\n";
                $message .= ($validated['note'] ?? '-')."\n\n";
                $message .= "Mohon untuk segera menindaklanjuti surat tersebut melalui aplikasi pada tautan berikut:\n";
                $message .= config('services.frontend.url')."/incoming/{$disposition->id}\n\n";
                $message .= 'Terima kasih.';

                $this->whatsappService->sendNotification($recipient->phone_number, $message);
            }
        }

        return response()->json(['message' => 'Disposition sent', 'data' => $dispositions], 201);
    }

    public function markAsRead(Request $request, Disposition $disposition)
    {
        if ($disposition->to_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($disposition->status === 'pending') {
            $disposition->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return $disposition;
    }

    public function accept(Request $request, Disposition $disposition)
    {
        if ($disposition->to_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (in_array($disposition->status, ['accepted', 'completed'])) {
            return $disposition; // already accepted, idempotent
        }

        // State machine: must be read before it can be accepted. If it is
        // still pending, mark it read first so the flow stays consistent.
        if ($disposition->status === 'pending') {
            $disposition->update(['status' => 'read', 'read_at' => now()]);
        }

        $disposition->update(['status' => 'accepted']);

        return $disposition;
    }
}
