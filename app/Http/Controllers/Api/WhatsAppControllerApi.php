<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WhatsAppControllerApi extends Controller
{
    public function send()
    {
        $gallabox = new GallaboxService();
        $response = $gallabox->sendMessage("918888888888", "Hello from Laravel!");

        return response()->json($response);
    }
}
