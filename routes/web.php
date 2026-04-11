<?php

use Illuminate\Support\Facades\Route;
 
 
use Twilio\Rest\Client;

Route::get('/test-whatsapp-template', function () {
    try {
        $client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        $message = $client->messages->create(
            'whatsapp:+966500317576',
            [
                'from' => config('services.twilio.whatsapp_from'),
                'contentSid' => config('services.twilio.template_sid'),
                'contentVariables' => json_encode([
                    '1' => 'Abdallah',
                    '2' => 'Nofa Cast',
                ]),
            ]
        );

        return response()->json([
            'success' => true,
            'sid' => $message->sid,
            'status' => $message->status,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|sanctum|storage).*$');
