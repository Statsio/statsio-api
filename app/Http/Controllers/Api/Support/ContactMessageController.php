<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreContactMessageRequest;
use App\Mail\Support\ContactConfirmationMailable;
use App\Models\Support\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ContactMessageController extends Controller
{
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $message = ContactMessage::create($request->safe()->except('turnstile_token'));

        Mail::to($message->email)->send(new ContactConfirmationMailable($message));

        return response()->json([
            'success' => true,
            'message' => 'Votre message a bien été envoyé.',
            'data' => ['id' => $message->id],
        ], 201);
    }
}
