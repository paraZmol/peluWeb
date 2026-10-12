<?php

use App\Domain\Promotions\PromotionCopy;
use App\Domain\Promotions\PromotionTerms;
use App\Enums\PromotionMechanic;

/*
| The words the system writes for a promotion (docs/promociones.md).
*/

/**
 * @param  array<string, mixed>  $with
 */
function copyTerms(PromotionMechanic $mechanic, array $with = []): PromotionTerms
{
    return new PromotionTerms(...[
        'id' => 1,
        'name' => '',
        'mechanic' => $mechanic,
        'serviceIds' => [1],
        'startsOn' => '2026-10-05',
        'endsOn' => '2026-10-18',
        ...$with,
    ]);
}

it('writes the title from what the promotion does', function () {
    expect(PromotionCopy::title(copyTerms(PromotionMechanic::Discount, ['percent' => 20]), ['Corte']))->toBe('20 % menos en Corte')
        ->and(PromotionCopy::title(copyTerms(PromotionMechanic::Discount, ['amountCents' => 1000]), ['Manicure', 'Pedicure']))->toBe('S/ 10 menos en Manicure y Pedicure')
        ->and(PromotionCopy::title(copyTerms(PromotionMechanic::Discount, ['amountCents' => 1050]), ['Manicure']))->toBe('S/ 10.50 menos en Manicure')
        ->and(PromotionCopy::title(copyTerms(PromotionMechanic::Discount, ['percent' => 15]), ['Corte', 'Barba', 'Cejas', 'Tinte']))->toBe('15 % menos en Corte, Barba y más')
        ->and(PromotionCopy::title(copyTerms(PromotionMechanic::Gift, ['giftServiceId' => 3]), ['Corte'], 'Rasurado'))->toBe('Rasurado gratis con tu Corte');
});

it('writes the fine print in full, the same way every time', function () {
    $plain = copyTerms(PromotionMechanic::Discount, ['percent' => 20]);

    expect(PromotionCopy::finePrint($plain, ['Corte', 'Barba'], null, false))->toBe(
        'Válido para citas del 5 de octubre al 18 de octubre. Aplica a: Corte, Barba. '
        .'Sin límite de cupos, sujeto a horarios disponibles. '
        .'Con reserva previa. No acumulable con otras promociones. Precios en soles con IGV incluido.',
    );

    $quiet = copyTerms(PromotionMechanic::Discount, ['percent' => 20, 'weekdays' => [1, 2, 3], 'fromMinute' => 600, 'toMinute' => 960]);

    expect(PromotionCopy::finePrint($quiet, ['Corte'], 30, true))->toBe(
        'Válido para citas del 5 de octubre al 18 de octubre. Aplica a: Corte. '
        .'Solo lunes, martes y miércoles, de 10:00 a 16:00. Cupos: 30. Una por cliente. '
        .'Con reserva previa. No acumulable con otras promociones. Precios en soles con IGV incluido.',
    );
});

it('adds what a gift is not', function () {
    $gift = copyTerms(PromotionMechanic::Gift, ['giftServiceId' => 3]);

    expect(PromotionCopy::finePrint($gift, ['Corte'], null, false))
        ->toContain('El servicio de regalo se realiza en la misma cita y no es canjeable por dinero ni por otro servicio.');
});

it('says only the days, or only the hours, when it names one of them', function () {
    expect(PromotionCopy::when(copyTerms(PromotionMechanic::Discount, ['weekdays' => [6]])))->toBe('sábado')
        ->and(PromotionCopy::when(copyTerms(PromotionMechanic::Discount, ['fromMinute' => 540, 'toMinute' => 720])))->toBe('de 09:00 a 12:00')
        ->and(PromotionCopy::when(copyTerms(PromotionMechanic::Discount)))->toBeNull();
});
