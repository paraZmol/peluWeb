<?php

use App\Models\ExpenseCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each salon names its own kinds of expense, starting from a common set.
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['salon_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            // Removing a category keeps its expenses, uncategorized.
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('method', 10);
            $table->dateTime('paid_at');
            $table->string('description', 160)->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Money records are never erased: a deleted expense stops counting.
            $table->softDeletes();

            $table->index(['salon_id', 'paid_at']);
        });

        $now = now();

        foreach (DB::table('salons')->pluck('id') as $salonId) {
            DB::table('expense_categories')->insert(array_map(
                fn (string $name, int $position): array => [
                    'salon_id' => $salonId, 'name' => $name, 'position' => $position, 'created_at' => $now, 'updated_at' => $now,
                ],
                ExpenseCategory::DEFAULTS,
                array_keys(ExpenseCategory::DEFAULTS),
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
