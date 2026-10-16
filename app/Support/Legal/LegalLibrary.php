<?php

namespace App\Support\Legal;

use App\Data\Legal\PublishedDocument;
use App\Domain\Legal\LegalSource;
use App\Enums\LegalDocumentType;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * The published legal texts: `resources/legal/{document}/{version}.md`.
 * The highest version is the current one; older ones stay readable, as
 * the texts promise. Read once per request.
 */
#[Scoped]
class LegalLibrary
{
    /** What each gap of the texts stands for, shown while it is empty. */
    public const array LABELS = [
        'trade_name' => 'nombre comercial',
        'legal_name' => 'razón social',
        'ruc' => 'RUC',
        'address' => 'domicilio',
        'contact_email' => 'correo de contacto',
        'privacy_email' => 'correo de privacidad',
        'support_hours' => 'horario de atención',
        'jurisdiction_city' => 'ciudad',
        'hosting_provider' => 'proveedor de hosting y país',
        'mail_provider' => 'proveedor de correo y país',
        'payment_registration_term' => 'plazo para registrar un pago',
        'domain' => 'dominio',
        'complaints_url' => 'enlace del Libro de Reclamaciones',
        'session_cookie' => 'nombre de la cookie de sesión',
    ];

    /** @var array<string, PublishedDocument|null> */
    private array $documents = [];

    public function current(LegalDocumentType $type): PublishedDocument
    {
        $latest = $this->versions($type)[0] ?? null;

        return ($latest === null ? null : $this->version($type, $latest))
            ?? throw new \RuntimeException("No published version of {$type->value}.");
    }

    public function version(LegalDocumentType $type, string $version): ?PublishedDocument
    {
        $key = "{$type->value}@{$version}";

        if (! array_key_exists($key, $this->documents)) {
            $this->documents[$key] = $this->load($type, $version);
        }

        return $this->documents[$key];
    }

    /**
     * @return list<string> newest first
     */
    public function versions(LegalDocumentType $type): array
    {
        $versions = array_map(
            fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            glob($this->folder($type).'/*.md') ?: [],
        );

        usort($versions, fn (string $a, string $b): int => version_compare($b, $a));

        return $versions;
    }

    /**
     * Who the provider is, as the footer of every page shows it.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        return [
            'trade_name' => (string) config('app.name'),
            'legal_name' => (string) config('legal.provider.name'),
            'ruc' => (string) config('legal.provider.ruc'),
            'address' => (string) config('legal.provider.address'),
            'contact_email' => (string) config('legal.contact.email'),
            'privacy_email' => (string) config('legal.contact.privacy_email'),
            'support_hours' => (string) config('legal.contact.support_hours'),
            'jurisdiction_city' => (string) config('legal.jurisdiction_city'),
            'hosting_provider' => (string) config('legal.service_providers.hosting'),
            'mail_provider' => (string) config('legal.service_providers.mail'),
            'payment_registration_term' => (string) config('legal.payment_registration_term'),
            'domain' => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            'complaints_url' => Route::has('complaints.create') ? route('complaints.create') : '',
            'session_cookie' => (string) config('session.cookie'),
        ];
    }

    private function load(LegalDocumentType $type, string $version): ?PublishedDocument
    {
        // The version comes from the URL: only a plain number reaches the disk.
        $path = $this->folder($type)."/{$version}.md";

        if (preg_match('/\A\d+(\.\d+)*\z/', $version) !== 1 || ! is_file($path)) {
            return null;
        }

        $source = LegalSource::parse((string) file_get_contents($path));
        $filled = $source->fill($this->values(), self::LABELS, showMissing: ! app()->isProduction());

        if ($filled['missing'] !== [] && app()->isProduction()) {
            Log::warning('Legal text published with empty gaps.', ['document' => $type->value, 'missing' => $filled['missing']]);
        }

        return new PublishedDocument(
            type: $type,
            title: $source->meta('title'),
            version: $version,
            effectiveOn: CarbonImmutable::parse($source->meta('effective')),
            changes: $source->meta('changes'),
            markdown: $filled['text'],
        );
    }

    private function folder(LegalDocumentType $type): string
    {
        return rtrim((string) config('legal.documents_path', resource_path('legal')), '/\\').'/'.$type->value;
    }
}
