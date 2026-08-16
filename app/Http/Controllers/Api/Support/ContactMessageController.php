<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreContactMessageRequest;
use App\Models\Support\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactMessageController extends Controller
{
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $message = ContactMessage::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Votre message a bien été envoyé.',
            'data' => ['id' => $message->id],
        ], 201);
    }
}
