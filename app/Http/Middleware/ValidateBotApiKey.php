<?php
// app/Http/Middleware/ValidateBotApiKey.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\BotConfig;

class ValidateBotApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-Bot-Api-Key');
        
        if (!$apiKey) {
            return response()->json([
                'message' => 'API Key required'
            ], 401);
        }
        
        $config = BotConfig::where('waha_api_key', $apiKey)
            ->where('is_active', true)
            ->first();
        
        if (!$config) {
            return response()->json([
                'message' => 'Invalid API Key'
            ], 401);
        }
        
        return $next($request);
    }
}