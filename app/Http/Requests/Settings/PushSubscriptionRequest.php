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
                $host = is_string($value) ? parse_url($value, PHP_URL_HOST) : null;

                if (! is_string($host) || preg_match(self::PUSH_HOSTS, $host) !== 1) {
                    $fail('Este navegador no es compatible con las notificaciones.');
                }
            }],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ];
    }
}
