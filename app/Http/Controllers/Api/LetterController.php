<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LetterController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Add filtering if needed
        $query = Letter::with('dispositions')->latest();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        } else {
            // Default to incoming if not specified, or just show all?
            // Usually we want to separate them.
            $query->where('category', 'incoming');
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Ideally only Admin can create letters
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'letter_number' => 'required|string',
            'origin' => 'required|string',
            'letter_date' => 'required|date',
            'received_date' => 'nullable|date',
            'type' => 'required|in:important,ordinary',
            'classification' => 'required|in:secret,ordinary',
            'subject' => 'required|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB
            'category' => 'nullable|in:incoming,outgoing',
        ]);

        $validated['status'] = 'processing';
        $validated['category'] = $validated['category'] ?? 'incoming';

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('letters', 'public');
            $validated['file_path'] = $path;
        }

        // Generate a collision-free agenda number from the row id (unique by PK)
        // instead of time(), which clashes when 2 letters are created in the
        // same second.
        $letter = DB::transaction(function () use ($validated) {
            $letter = Letter::create($validated + ['agenda_number' => 'AGN-TMP-'.Str::uuid()]);
            $letter->update([
                'agenda_number' => 'AGN-'.now()->format('Y').'-'.str_pad((string) $letter->id, 5, '0', STR_PAD_LEFT),
            ]);

            return $letter;
        });

        return response()->json($letter, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Letter $letter)
    {
        return $letter->load('dispositions.sender', 'dispositions.receiver');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Letter $letter)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'letter_number' => 'sometimes|string',
            'origin' => 'sometimes|string',
            'letter_date' => 'sometimes|date',
            'received_date' => 'nullable|date',
            'type' => 'sometimes|in:important,ordinary',
            'classification' => 'sometimes|in:secret,ordinary',
            'subject' => 'sometimes|string',
            'status' => 'sometimes|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('file')) {
            // Remove the old file so it is not orphaned in storage.
            if ($letter->file_path) {
                Storage::disk('public')->delete($letter->file_path);
            }
            $path = $request->file('file')->store('letters', 'public');
            $validated['file_path'] = $path;
        }

        unset($validated['file']);
        $letter->update($validated);

        return $letter;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Letter $letter)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Delete the stored file too, otherwise it is orphaned in storage.
        if ($letter->file_path) {
            Storage::disk('public')->delete($letter->file_path);
        }

        $letter->delete();

        return response()->json(['message' => 'Letter deleted successfully']);
    }

    /**
     * Track the letter's position and status.
     */
    public function track(Letter $letter)
    {
        // Load dispositions with user details to see the flow
        $letter->load(['dispositions' => function ($query) {
            $query->orderBy('created_at', 'asc')->with(['sender', 'receiver']);
        }]);

        // Determine current holder
        // The last disposition's receiver is likely the current holder,
        // unless it's a loop or parallel.
        // For simple tracking, the last disposition is key.
        $lastDisposition = $letter->dispositions->last();
        $currentHolder = $lastDisposition ? $lastDisposition->receiver : null;

        return response()->json([
            'letter' => $letter,
            'current_holder' => $currentHolder,
            'history' => $letter->dispositions,
        ]);
    }

    /**
     * Escalate the letter (Admin intervention).
     */
    public function escalate(Request $request, Letter $letter)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'from_user_id' => 'required|exists:users,id',
            'to_user_id' => 'required|exists:users,id|different:from_user_id',
            'note' => 'nullable|string',
        ]);

        // Create the disposition
        $disposition = $letter->dispositions()->create([
            'from_user_id' => $validated['from_user_id'],
            'to_user_id' => $validated['to_user_id'],
            'type' => 'disposition', // or 'forward' / 'escalation'
            'note' => trim(($validated['note'] ?? '').' (Escalated by Admin)'),
            'status' => 'pending',
        ]);

        // Send notification (WA)
        $recipient = User::find($validated['to_user_id']);
        if ($recipient && $recipient->phone_number) {
            $sender = User::find($validated['from_user_id']);

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
            $message .= "Diberitahukan bahwa Anda telah menerima disposisi surat baru (Eskalasi Admin) dari {$sender->name} dengan detail sebagai berikut:\n\n";
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

        return response()->json($disposition, 201);
    }

    /**
     * Archive the letter (Admin only).
     */
    public function archive(Request $request, Letter $letter)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $letter->update(['status' => 'archived']);

        return response()->json($letter);
    }
}
