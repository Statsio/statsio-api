<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Support\Enums\ContactMessageStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Support\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class AdminContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContactMessage::query();

        if ($search = $request->input('search')) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(message) LIKE ?', [$like]);
            });
        }

        if ($reason = $request->input('reason')) {
            $query->where('reason', $reason);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $messages = $query->latest()->paginate($request->input('per_page', 25));

        return response()->json($messages);
    }

    public function show(int $id): JsonResponse
    {
        $message = ContactMessage::findOrFail($id);

        return response()->json(['data' => $message]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $message = ContactMessage::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', new Enum(ContactMessageStatusEnum::class)],
        ]);

        $message->update($data);

        return response()->json(['data' => $message]);
    }

    public function destroy(int $id): JsonResponse
    {
        $message = ContactMessage::findOrFail($id);
        $message->delete();

        return response()->json(null, 204);
    }
}
