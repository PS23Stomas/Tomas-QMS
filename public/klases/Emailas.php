<?php
/**
 * El. pašto siuntimo klasė
 *
 * Ši klasė atsakinga už laiškų siuntimą per internetą.
 * Ji naudoja Resend paslaugą (resend.com) — tai tarsi pašto dėžutė,
 * per kurią sistema išsiunčia laiškus vartotojams.
 *
 * Pavyzdžiai kam naudojama:
 * - Kai vartotojas pamiršta slaptažodį — sistema išsiunčia atstatymo nuorodą
 * - Kai sukuriama nauja pretenzija — sistema praneša atsakingam darbuotojui
 * - Kai siunčiamas atsakymas klientui — laiškas keliauja su PDF priedu
 */
class Emailas {
    /** API raktas prisijungimui prie Resend paslaugos */
    private static $apiKey = null;

    /** Siuntėjo adresas, kurį matys gavėjas (pvz. "Tomo-QMS <noreply@updates.elga.tech>") */
    private static $fromEmail = 'Tomo-QMS <noreply@updates.elga.tech>';

    /**
     * Nustato Resend API raktą rankiniu būdu.
     * Paprastai raktas nuskaitomas automatiškai iš aplinkos kintamojo,
     * bet šis metodas leidžia jį pakeisti programiškai.
     */
    public static function setApiKey(string $key): void {
        self::$apiKey = $key;
    }

    /**
     * Nustato siuntėjo el. pašto adresą.
     * Tai adresas, kurį matys laiško gavėjas lauke "Nuo:".
     */
    public static function setFromEmail(string $email): void {
        self::$fromEmail = $email;
    }

    /**
     * Grąžina API raktą.
     * Pirma ieško rankiniu būdu nustatyto rakto, jei nėra — skaito iš RESEND_API_KEY aplinkos kintamojo.
     * Jei rakto visai nėra — išmeta klaidą, nes be jo siųsti negalima.
     */
    private static function getApiKey(): string {
        if (self::$apiKey) return self::$apiKey;
        $key = getenv('RESEND_API_KEY');
        if (!$key) throw new Exception('RESEND_API_KEY nenustatytas');
        return $key;
    }

    /** Paskutinės klaidos pranešimas (jei siuntimas nepavyko) */
    private static $lastError = '';

    /** Paskutinis serverio atsakymas (sėkmės arba klaidos JSON) */
    private static $lastResponse = '';

    /** Grąžina paskutinį klaidos pranešimą — naudinga diagnozuoti problemas */
    public static function getLastError(): string { return self::$lastError; }

    /** Grąžina paskutinį serverio atsakymą — naudinga derinant klaidas */
    public static function getLastResponse(): string { return self::$lastResponse; }

    /**
     * Išsiunčia el. laišką nurodytam gavėjui.
     *
     * Kaip veikia: suformuoja JSON paketą su gavėjo adresu, tema, HTML turiniu
     * ir neprivalomais priedais, tada išsiunčia jį į Resend API per internetą.
     *
     * @param string $kam     Gavėjo el. pašto adresas (pvz. "vardas@imone.lt")
     * @param string $tema    Laiško tema (pvz. "Jūsų užsakymas patvirtintas")
     * @param string $html    Laiško turinys HTML formatu (su spalvomis, lentelėmis ir t.t.)
     * @param array  $priedai Neprivalomi priedai: [['filename'=>'...', 'content'=>base64_string], ...]
     * @return bool           true jei laiškas išsiųstas sėkmingai, false jei klaida
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

    /**
     * Išsiunčia slaptažodžio atstatymo laišką.
     *
     * Kai vartotojas paspaudžia "Pamiršau slaptažodį", ši funkcija
     * sugeneruoja gražiai atrodantį laišką su mygtuku "Atstatyti slaptažodį".
     * Nuoroda galioja tik 1 valandą saugumo sumetimais.
     *
     * @param string $kam    Vartotojo el. pašto adresas
     * @param string $vardas Vartotojo vardas (rodomas laiške kaip sveikinimas)
     * @param string $token  Unikalus kodas, patvirtinantis kad laiškas tikras
     * @return bool          true jei laiškas išsiųstas sėkmingai
     */
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
