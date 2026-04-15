<?php
/**
 * El. pašto siuntimo klasė per Resend API
 */
class Emailas {
    private static $apiKey = null;
    private static $fromEmail = 'Tomo-QMS <noreply@updates.elga.tech>';

    /** Nustato Resend API raktą */
    public static function setApiKey(string $key): void {
        self::$apiKey = $key;
    }

    /** Nustato siuntėjo el. pašto adresą */
    public static function setFromEmail(string $email): void {
        self::$fromEmail = $email;
    }

    /** Grąžina API raktą iš nustatymo arba aplinkos kintamojo RESEND_API_KEY */
    private static function getApiKey(): string {
        if (self::$apiKey) return self::$apiKey;
        $key = getenv('RESEND_API_KEY');
        if (!$key) throw new Exception('RESEND_API_KEY nenustatytas');
        return $key;
    }

    private static $lastError = '';
    private static $lastResponse = '';

    public static function getLastError(): string { return self::$lastError; }
    public static function getLastResponse(): string { return self::$lastResponse; }

    /** Išsiunčia el. laišką per Resend API nurodytam gavėjui su tema ir HTML turiniu.
     *  $priedai — neprivalomas masyvas priedų: [['filename'=>'...', 'content'=>base64_string], ...]
     */
    public static function siusti(string $kam, string $tema, string $html, array $priedai = []): bool {
        self::$lastError = '';
        self::$lastResponse = '';
        $apiKey = self::getApiKey();

        $payload = [
            'from' => self::$fromEmail,
            'to' => [$kam],
            'subject' => $tema,
            'html' => $html,
        ];

        if (!empty($priedai)) {
            $payload['attachments'] = $priedai;
        }

        $data = json_encode($payload);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        self::$lastResponse = $response ?: '';

        if ($curlError) {
            self::$lastError = 'Ryšio klaida: ' . $curlError;
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $body = json_decode($response, true);
            $msg = $body['message'] ?? $body['error'] ?? $response;
            self::$lastError = "Resend API klaida (HTTP {$httpCode}): {$msg}";
            return false;
        }

        return true;
    }

    /** Išsiunčia slaptažodžio atstatymo el. laišką su unikalia nuoroda vartotojui */
    public static function siustiAtstatymoNuoroda(string $kam, string $vardas, string $token): bool {
        $baseUrl = getBaseUrl();
        $url = "{$baseUrl}/slaptazodis_keitimas.php?token=" . urlencode($token);

        $html = '
        <div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
                <h1 style="color: white; margin: 0; font-size: 20px;">MT Modulis</h1>
            </div>
            <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
                <p style="color: #333; font-size: 16px;">Sveiki, <strong>' . htmlspecialchars($vardas) . '</strong></p>
                <p style="color: #555; font-size: 14px; line-height: 1.6;">
                    Gavome prašymą atstatyti jūsų slaptažodį MT Modulis sistemoje.
                </p>
                <div style="text-align: center; margin: 25px 0;">
                    <a href="' . htmlspecialchars($url) . '" 
                       style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                              color: white; padding: 12px 30px; border-radius: 6px; 
                              text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                        Atstatyti slaptažodį
                    </a>
                </div>
                <p style="color: #888; font-size: 13px; line-height: 1.5;">
                    Ši nuoroda galioja <strong>1 valandą</strong>. 
                    Jei jūs neprašėte slaptažodžio atstatymo, tiesiog ignoruokite šį laišką.
                </p>
                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
                <p style="color: #aaa; font-size: 11px; text-align: center;">
                    MT Modulis - Gamybos valdymo sistema
                </p>
            </div>
        </div>';

        return self::siusti($kam, 'Slaptažodžio atstatymas - MT Modulis', $html);
    }
}
