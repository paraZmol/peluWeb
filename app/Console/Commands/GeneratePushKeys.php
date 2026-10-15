<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webpush:keys')]
#[Description('Generates the pair of keys that signs push notifications, to paste into .env')]
class GeneratePushKeys extends Command
{
    /** Bytes of each coordinate and of the private number on the P-256 curve. */
    private const int KEY_LENGTH = 32;

    public function handle(): int
    {
        $keys = $this->generate();

        if ($keys === null) {
            $this->error('PHP no pudo crear las claves con OpenSSL. En Windows, define OPENSSL_CONF con la ruta de openssl.cnf (ver docs/stack.md).');

            return self::FAILURE;
        }

        $this->line('Pega estas líneas en el archivo .env de este servidor (no las subas a git):');
        $this->newLine();
        $this->line("VAPID_PUBLIC_KEY={$keys['public']}");
        $this->line("VAPID_PRIVATE_KEY={$keys['private']}");
        $this->line('VAPID_SUBJECT=mailto:'.config('mail.from.address'));
        $this->newLine();
        $this->warn('Si cambias las claves, todos los dispositivos tendrán que volver a activar las notificaciones.');

        return self::SUCCESS;
    }

    /**
     * A P-256 pair in the form browsers and the push library expect:
     * base64url of the uncompressed point and of the private number.
     *
     * @return array{public: string, private: string}|null
     */
    private function generate(): ?array
    {
        // PHP on Windows only finds its openssl.cnf when told where it is.
        $bundled = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            ...(is_file($bundled) ? ['config' => $bundled] : []),
        ]);
        $curve = $key === false ? null : (openssl_pkey_get_details($key) ?: [])['ec'] ?? null;

        if (! is_array($curve)) {
            return null;
        }

        $pad = fn (string $bytes): string => str_pad($bytes, self::KEY_LENGTH, "\0", STR_PAD_LEFT);
        $encode = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return [
            'public' => $encode("\x04".$pad($curve['x']).$pad($curve['y'])),
            'private' => $encode($pad($curve['d'])),
        ];
    }
}
