<?php

namespace App\Http\Requests\Settings;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PushSubscriptionRequest extends FormRequest
{
    /**
     * The push services browsers use. The server posts to this address
     * later: anything else would let a person point it at any host.
     */
    private const string PUSH_HOSTS = '/(^|\.)(fcm\.googleapis\.com|push\.services\.mozilla\.com|notify\.windows\.com|push\.apple\.com)$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|Closure>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2000', 'url:https', function (string $attribute, mixed $value, Closure $fail): void {
                $url = is_string($value) ? parse_url($value) : false;
                $host = is_array($url) ? ($url['host'] ?? null) : null;
                // Only the service's own door: another port or a "user@" has no reason to be there.
                $isPlain = is_array($url) && ! isset($url['user']) && ! isset($url['pass']) && ($url['port'] ?? 443) === 443;

                if (! is_string($host) || ! $isPlain || preg_match(self::PUSH_HOSTS, $host) !== 1) {
                    $fail('Este navegador no es compatible con las notificaciones.');
                }
            }],
            // An uncompressed P-256 point (65 bytes) and a 16-byte secret, in base64url:
            // anything else could never be encrypted for and would fail on every send.
            'keys.p256dh' => ['required', 'string', 'regex:/^B[A-Za-z0-9_-]{86}=?$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}(==)?$/'],
        ];
    }
}
