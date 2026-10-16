<?php

namespace Database\Factories;

use App\Actions\Legal\RecordLegalAcceptance;
use App\Data\Legal\AcceptanceProof;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Every account made here already accepted the current terms, so tests
     * and demo data do not stop at the acceptance screen.
     */
    private bool $acceptsTerms = true;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user is a platform (super) admin.
     */
    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_platform_admin' => true,
        ]);
    }

    /**
     * An account that never accepted the terms: the acceptance screen stops it.
     */
    public function withoutAcceptedTerms(): static
    {
        $factory = $this->newInstance();
        $factory->acceptsTerms = false;

        return $factory;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function newInstance(array $arguments = []): static
    {
        $factory = parent::newInstance($arguments);
        $factory->acceptsTerms = $this->acceptsTerms;

        return $factory;
    }

    /**
     * @param  Collection<int, Model>  $instances
     */
    protected function callAfterCreating(Collection $instances, ?Model $parent = null): void
    {
        parent::callAfterCreating($instances, $parent);

        if (! $this->acceptsTerms) {
            return;
        }

        $instances->each(function (Model $user): void {
            if ($user instanceof User) {
                app(RecordLegalAcceptance::class)->forAccount($user, AcceptanceProof::seeded());
            }
        });
    }
}
