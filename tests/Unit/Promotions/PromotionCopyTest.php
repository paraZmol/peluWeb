<?php

use App\Domain\Promotions\ComboPrice;
use App\Domain\Promotions\PromotionCopy;
use App\Domain\Promotions\PromotionFacts;
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

    $closing = 'Si cambias tu cita, la promoción se mantiene solo si la nueva fecha y hora cumplen estas condiciones. %s'
        .'Con reserva previa. No se suma a otras promociones: si tu cita cumple más de una, se aplica la de mayor ahorro. '
        .'Precios finales en soles, con impuestos incluidos.';

    expect(PromotionCopy::finePrint($plain, ['Corte', 'Barba'], new PromotionFacts('Estudio Luz')))->toBe(
        'Promoción de Estudio Luz. Válido para citas del 5 al 18 de octubre de 2026. Aplica a: Corte, Barba. '
        .'Sin límite de cupos: vale para todas las citas que se reserven dentro de estas fechas, mientras haya horarios libres. '
        .sprintf($closing, ''),
    );

    $quiet = copyTerms(PromotionMechanic::Discount, ['percent' => 20, 'weekdays' => [1, 2, 3], 'fromMinute' => 600, 'toMinute' => 960]);

    expect(PromotionCopy::finePrint($quiet, ['Corte'], new PromotionFacts('Estudio Luz', quota: 30, oncePerClient: true, hasDeposit: true)))->toBe(
        'Promoción de Estudio Luz. Válido para citas del 5 al 18 de octubre de 2026. Aplica a: Corte. '
        .'Solo lunes, martes y miércoles, en citas que empiecen entre las 10:00 y las 16:00. Cupos: 30 reservas. Una por cliente. '
        .sprintf($closing, 'El precio con promoción se reserva hasta el plazo para enviar la seña. '),
    );
});

it('says a first visit is for someone who never came, in the title and in the fine print', function () {
    $first = copyTerms(PromotionMechanic::Discount, ['percent' => 20, 'newClientsOnly' => true]);
    $text = PromotionCopy::finePrint($first, ['Corte'], new PromotionFacts('Estudio Luz', quota: 10));

    expect(PromotionCopy::title($first, ['Corte']))->toBe('20 % menos en Corte en tu primera visita')
        ->and($text)->toContain('Cupos: 10 reservas. Solo para tu primera reserva en Estudio Luz: no vale si ya tuviste una cita allí, aunque no hayas asistido, ni si tienes otra reservada. Las citas canceladas no cuentan. Lo comprobamos con tu cuenta y tu teléfono. Si cambias')
        ->and($text)->not->toContain('Una por cliente.');
});

it('says a fixed amount comes off once, when it covers more than one service', function () {
    $amount = copyTerms(PromotionMechanic::Discount, ['amountCents' => 1000]);
    $once = 'El descuento de S/ 10 se aplica una sola vez por reserva.';

    expect(PromotionCopy::finePrint($amount, ['Manicure', 'Pedicure'], new PromotionFacts('Estudio Luz')))->toContain($once)
        ->and(PromotionCopy::finePrint($amount, ['Manicure'], new PromotionFacts('Estudio Luz')))->not->toContain($once);
});

it('says what a gift is worth, what it adds and what it is not', function () {
    $gift = copyTerms(PromotionMechanic::Gift, ['giftServiceId' => 3]);
    $facts = new PromotionFacts('Estudio Luz', giftName: 'Rasurado', giftPriceCents: 1200, giftMinutes: 15);

    expect(PromotionCopy::finePrint($gift, ['Corte'], $facts))
        ->toContain('El regalo (Rasurado, precio normal S/ 12) se hace en la misma cita y alarga la cita 15 min. No se cambia por dinero ni por otro servicio.');
});

it('writes the dates with the year, and each month when they differ', function () {
    expect(PromotionCopy::period('2026-10-05', '2026-10-18'))->toBe('del 5 al 18 de octubre de 2026')
        ->and(PromotionCopy::period('2026-10-20', '2026-11-08'))->toBe('del 20 de octubre al 8 de noviembre de 2026')
        ->and(PromotionCopy::period('2026-12-15', '2027-01-10'))->toBe('del 15 de diciembre de 2026 al 10 de enero de 2027');
});

it('says only the days, or only the hours, when it names one of them', function () {
    expect(PromotionCopy::when(copyTerms(PromotionMechanic::Discount, ['weekdays' => [6]])))->toBe('sábado')
        ->and(PromotionCopy::when(copyTerms(PromotionMechanic::Discount, ['fromMinute' => 540, 'toMinute' => 720])))->toBe('de 09:00 a 12:00')
        ->and(PromotionCopy::when(copyTerms(PromotionMechanic::Discount)))->toBeNull();
});

it('names a combo by its services and price, "desde" when the price can change', function () {
    $combo = copyTerms(PromotionMechanic::Combo, ['serviceIds' => [1, 2], 'amountCents' => 1000]);

    expect(PromotionCopy::title($combo, ['Corte', 'Barba'], null, new ComboPrice(5500, 1000, false)))->toBe('Corte + Barba por S/ 45')
        ->and(PromotionCopy::title($combo, ['Tinte', 'Matiz'], null, new ComboPrice(18050, 3000, true)))->toBe('Tinte + Matiz desde S/ 150.50')
        ->and(PromotionCopy::title($combo, ['Corte', 'Barba']))->toBe('Combo Corte + Barba')
        ->and(PromotionCopy::finePrint($combo, ['Corte', 'Barba'], new PromotionFacts('Estudio Luz')))
        ->toContain('El precio del combo es por Corte y Barba juntos, en la misma cita y atendidos por una sola persona del equipo. Si eliges una opción o un profesional con un precio más alto, pagas esa diferencia y sigues ahorrando S/ 10. Si quitas uno de los servicios, el combo no aplica y los demás se cobran a su precio normal.')
        ->not->toContain('se aplica una sola vez por reserva');
});
