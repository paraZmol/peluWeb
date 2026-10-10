<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonPermission;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 5.2: what the salon spends, by category, with the receipt's photo.
| "Now" is Wednesday 2026-10-07 20:30 in Lima (already Thursday in UTC).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-07 20:30', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::BeautySalon, Clientele::Everyone);
    $this->rent = ExpenseCategory::withoutGlobalScope('salon')->where('salon_id', $this->salon->id)->where('name', 'Alquiler')->sole();
});

function expensePayload(array $overrides = []): array
{
    return ['category' => test()->rent->id, 'amount' => '1200.50', 'method' => 'transfer', 'date' => '2026-10-07', 'description' => 'Alquiler de octubre', ...$overrides];
}

function expensesOf(Salon $salon): Collection
{
    return Expense::withoutGlobalScope('salon')->where('salon_id', $salon->id)->get();
}

it('gives a new salon the starting categories', function () {
    expect(ExpenseCategory::withoutGlobalScope('salon')->where('salon_id', $this->salon->id)->orderBy('position')->pluck('name')->all())
        ->toBe(ExpenseCategory::DEFAULTS);
});

it('records an expense on the salon\'s calendar day and lists it in its month', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload())->assertRedirect();

    $expense = expensesOf($this->salon)->sole();

    expect([$expense->amount_cents, $expense->method->value, $expense->expense_category_id, $expense->recorded_by])
        ->toBe([120050, 'transfer', $this->rent->id, $this->owner->id])
        ->and($expense->paid_at->setTimezone('America/Lima')->toDateString())->toBe('2026-10-07');

    $this->actingAs($this->owner)->get(route('expenses.index'))->assertInertia(fn (Assert $page) => $page
        ->component('owner/expenses/index', false)
        ->where('month', '2026-10')
        ->where('today', '2026-10-07')
        ->where('total', '1200.50')
        ->where('expenses.0.amount', '1200.50')
        ->where('expenses.0.date', '2026-10-07')
        ->where('expenses.0.categoryName', 'Alquiler')
        ->has('categories', 5)
        ->has('paymentMethods', 6));

    $this->actingAs($this->owner)->get(route('expenses.index', ['mes' => '2026-09']))
        ->assertInertia(fn (Assert $page) => $page->where('month', '2026-09')->has('expenses', 0)->where('total', '0.00'));
});

it('keeps a month\'s first and last day inside that month', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload(['date' => '2026-09-30', 'amount' => '10']));
    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload(['date' => '2026-10-01', 'amount' => '20']));

    $this->actingAs($this->owner)->get(route('expenses.index', ['mes' => '2026-09']))->assertInertia(fn (Assert $page) => $page->where('total', '10.00'));
    $this->actingAs($this->owner)->get(route('expenses.index', ['mes' => '2026-10']))->assertInertia(fn (Assert $page) => $page->where('total', '20.00'));
    $this->actingAs($this->owner)->get(route('expenses.index', ['mes' => '2099-01']))->assertInertia(fn (Assert $page) => $page->where('month', '2026-10'));
    $this->actingAs($this->owner)->get(route('expenses.index', ['mes' => "2026-10' OR 1=1"]))->assertInertia(fn (Assert $page) => $page->where('month', '2026-10'));
});

it('rejects amounts and dates that make no sense', function (array $overrides, string $field) {
    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload($overrides))->assertSessionHasErrors($field);

    expect(expensesOf($this->salon))->toBeEmpty();
})->with([
    'zero' => [['amount' => '0.00'], 'amount'],
    'negative' => [['amount' => '-5'], 'amount'],
    'three decimals' => [['amount' => '10.555'], 'amount'],
    'words' => [['amount' => 'mil'], 'amount'],
    'tomorrow on the salon\'s calendar' => [['date' => '2026-10-08'], 'date'],
    'unknown method' => [['method' => 'bitcoin'], 'method'],
    'markup in the description' => [['description' => '<script>alert(1)</script>'], 'description'],
]);

it('stores the receipt re-encoded and replaces or removes it on edit', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload(['receipt' => UploadedFile::fake()->image('boleta.jpg', 900, 1200)]));
    $expense = expensesOf($this->salon)->sole();
    $first = $expense->receipt_path;

    expect($first)->toEndWith('.webp')->and(Storage::disk('local')->exists($first))->toBeTrue();

    $this->actingAs($this->owner)->post(route('expenses.update', $expense), expensePayload(['amount' => '1300', 'receipt' => UploadedFile::fake()->image('otra.png', 600, 600)]));
    $second = $expense->refresh()->receipt_path;

    expect($expense->amount_cents)->toBe(130000)
        ->and($second)->not->toBe($first)
        ->and(Storage::disk('local')->exists($first))->toBeFalse();

    $this->actingAs($this->owner)->post(route('expenses.update', $expense), expensePayload(['remove_receipt' => '1']));

    expect($expense->refresh()->receipt_path)->toBeNull()->and(Storage::disk('local')->exists($second))->toBeFalse();

    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload(['receipt' => UploadedFile::fake()->create('boleta.svg', 10, 'image/svg+xml')]))
        ->assertSessionHasErrors('receipt');
});

it('shows the receipt only to whoever handles this salon\'s cash', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload(['receipt' => UploadedFile::fake()->image('boleta.jpg', 900, 1200)]));
    $expense = expensesOf($this->salon)->sole();
    $member = SalonMember::factory()->for($this->salon)->create();
    $intruder = User::factory()->create();
    app(CreateSalon::class)->handle($intruder, 'Otro Salón', BusinessType::Barbershop, Clientele::Men);

    $this->actingAs($this->owner)->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page->where('expenses.0.receiptUrl', fn (string $url) => str_contains($url, "/gastos/{$expense->id}/comprobante")));
    $this->actingAs($this->owner)->get(route('expenses.receipt', $expense))
        ->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'max-age=3600, private');

    $this->actingAs($member->user)->get(route('expenses.receipt', $expense))->assertForbidden();
    $this->actingAs($intruder)->get(route('expenses.receipt', $expense->id))->assertNotFound();
    auth()->logout();
    $this->get(route('expenses.receipt', $expense))->assertRedirect(route('login'));

    // Nothing of it on the public disk.
    expect(Storage::disk('public')->exists($expense->receipt_path))->toBeFalse()
        ->and(Storage::disk('local')->exists($expense->receipt_path))->toBeTrue();
});

it('treats array parameters as missing instead of failing', function () {
    $this->actingAs($this->owner)->get('/gastos?mes[]=x')->assertOk();
    $this->actingAs($this->owner)->get('/comisiones?desde[]=x&hasta[]=y')->assertOk();
    $this->actingAs($this->owner)->get('/reportes/movimientos.csv?desde[]=x')->assertOk();
});

it('stops counting a deleted expense but keeps its record', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload(['receipt' => UploadedFile::fake()->image('boleta.jpg')]));
    $expense = expensesOf($this->salon)->sole();
    $receipt = $expense->receipt_path;

    $this->actingAs($this->owner)->delete(route('expenses.destroy', $expense))->assertRedirect();

    expect(expensesOf($this->salon))->toBeEmpty()
        ->and(Expense::withoutGlobalScope('salon')->withTrashed()->whereKey($expense->id)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists($receipt))->toBeFalse();
});

it('needs the cash permission for everything', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload());
    $expense = expensesOf($this->salon)->sole();

    $this->actingAs($member->user)->get(route('expenses.index'))->assertForbidden();
    $this->actingAs($member->user)->post(route('expenses.store'), expensePayload())->assertForbidden();
    $this->actingAs($member->user)->post(route('expenses.update', $expense), expensePayload(['amount' => '1']))->assertForbidden();
    $this->actingAs($member->user)->delete(route('expenses.destroy', $expense))->assertForbidden();
    $this->actingAs($member->user)->post(route('expenses.categories.store'), ['name' => 'Publicidad'])->assertForbidden();
    $this->actingAs($member->user)->delete(route('expenses.categories.destroy', $this->rent))->assertForbidden();

    $member->forceFill(['permissions' => [SalonPermission::ManageCash]])->save();
    $this->actingAs($member->user->fresh())->post(route('expenses.store'), expensePayload(['amount' => '15']))->assertRedirect();

    expect(expensesOf($this->salon)->pluck('amount_cents')->all())->toBe([120050, 1500]);
});

describe('categories', function () {
    it('adds, renames and removes them, leaving the expenses uncategorized', function () {
        $this->actingAs($this->owner)->post(route('expenses.categories.store'), ['name' => 'Publicidad'])->assertRedirect();
        $this->actingAs($this->owner)->post(route('expenses.categories.store'), ['name' => 'Publicidad'])->assertSessionHasErrors('name');
        $this->actingAs($this->owner)->put(route('expenses.categories.update', $this->rent), ['name' => 'Alquiler del local']);
        $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload());

        $this->actingAs($this->owner)->delete(route('expenses.categories.destroy', $this->rent))->assertRedirect();

        expect(ExpenseCategory::withoutGlobalScope('salon')->where('salon_id', $this->salon->id)->pluck('name')->all())
            ->toContain('Publicidad')->not->toContain('Alquiler del local')
            ->and(expensesOf($this->salon)->sole()->expense_category_id)->toBeNull();
    });

    it('stops at the limit', function () {
        foreach (range(1, ExpenseCategory::MAX_PER_SALON - count(ExpenseCategory::DEFAULTS)) as $number) {
            $this->actingAs($this->owner)->post(route('expenses.categories.store'), ['name' => "Categoría {$number}"]);
        }

        $this->actingAs($this->owner)->post(route('expenses.categories.store'), ['name' => 'Una más'])->assertInertiaFlash('toast.type', 'error');

        expect(ExpenseCategory::withoutGlobalScope('salon')->where('salon_id', $this->salon->id)->count())->toBe(ExpenseCategory::MAX_PER_SALON);
    });
});

it('keeps another salon away from this one\'s expenses and categories', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), expensePayload());
    $expense = expensesOf($this->salon)->sole();
    $intruder = User::factory()->create();
    $other = app(CreateSalon::class)->handle($intruder, 'Otro Salón', BusinessType::Barbershop, Clientele::Men);

    $this->actingAs($intruder)->post(route('expenses.update', $expense->id), expensePayload(['amount' => '1']))->assertNotFound();
    $this->actingAs($intruder)->delete(route('expenses.destroy', $expense->id))->assertNotFound();
    $this->actingAs($intruder)->put(route('expenses.categories.update', $this->rent->id), ['name' => 'Robada'])->assertNotFound();
    $this->actingAs($intruder)->delete(route('expenses.categories.destroy', $this->rent->id))->assertNotFound();
    $this->actingAs($intruder)->get(route('expenses.index'))->assertInertia(fn (Assert $page) => $page->has('expenses', 0)->where('total', '0.00'));

    // Naming the other salon's category does not attach it.
    $this->actingAs($intruder)->post(route('expenses.store'), expensePayload())->assertRedirect();

    expect($expense->refresh()->amount_cents)->toBe(120050)
        ->and($this->rent->refresh()->name)->toBe('Alquiler')
        ->and(expensesOf($other)->sole()->expense_category_id)->toBeNull();
});
