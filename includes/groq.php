<?php
define('GROQ_API_KEY', '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');
define('GROQ_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL', 'llama-3.1-8b-instant');

function groqChat(array $messages, float $temp = 0.3, int $maxTokens = 500, int $timeout = 10): array {
    $payload = json_encode(['model'=>GROQ_MODEL,'messages'=>$messages,'temperature'=>$temp,'max_tokens'=>$maxTokens]);

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init(GROQ_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.GROQ_API_KEY],
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$err && $code === 200) {
            $text = json_decode($raw, true)['choices'][0]['message']['content'] ?? null;
            if ($text !== null) return ['ok'=>true,'text'=>$text];
        }
        if ($code === 429 && $attempt === 1) { sleep(1); continue; }
        break;
    }
    return ['ok'=>false, 'error'=>$err ?: "HTTP $code"];
}