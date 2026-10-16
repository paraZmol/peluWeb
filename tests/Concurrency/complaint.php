<?php

/*
| Worker for ComplaintNumberRaceTest: one real PHP process that files one
| sheet of the Libro de Reclamaciones at an agreed instant. Prints
| OK:<number> or ERROR:<class> <message>.
|
|   php complaint.php <go-at-unix-float> <email>
*/

use App\Actions\Complaints\FileComplaint;
use App\Enums\ComplaintItemType;
use App\Enums\ComplaintKind;
use App\Enums\IdentityDocument;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $goAt, $email] = $argv;

$wait = (float) $goAt - microtime(true);

if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

try {
    $complaint = app(FileComplaint::class)->handle([
        'kind' => ComplaintKind::Claim->value,
        'consumer_name' => 'Persona de prueba',
        'consumer_address' => 'Calle de prueba 1',
        'document_type' => IdentityDocument::Dni->value,
        'document_number' => '12345678',
        'phone' => '999888777',
        'email' => $email,
        'guardian_name' => null,
        'item_type' => ComplaintItemType::Service->value,
        'item_description' => 'Reservas en línea',
        'claimed_amount_cents' => null,
        'detail' => 'Detalle de prueba de la carrera.',
        'request' => 'Pedido de prueba.',
    ]);

    echo 'OK:'.$complaint->number;
} catch (Throwable $exception) {
    echo 'ERROR:'.$exception::class.' '.$exception->getMessage();
}
