<?php

namespace App\Data\Salon;

use App\Enums\ConsentMethod;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * How an authorization, or its withdrawal, reached the salon and what
 * there is to show for it.
 */
final readonly class ConsentProof
{
    /**
     * @param  array<string, int|bool>|null  $details  what a withdrawal removed; never the content
     */
    private function __construct(
        public ConsentMethod $method,
        public ?User $staff = null,
        public ?User $account = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $text = null,
        public ?array $details = null,
    ) {}

    /**
     * Someone of the team declares it: a signed sheet, or the person's word.
     */
    public static function byStaff(ConsentMethod $method, ?User $staff): self
    {
        return new self($method, staff: $staff);
    }

    /**
     * The person themselves, signed in to their own account.
     *
     * @param  string|null  $text  the exact words shown to them, when they agreed to some
     */
    public static function fromAccount(Request $request, User $account, ?string $text = null): self
    {
        return new self(
            ConsentMethod::Account,
            account: $account,
            ip: $request->ip(),
            userAgent: mb_substr((string) $request->userAgent(), 0, 255),
            text: $text,
        );
    }

    /**
     * Nobody asked: the account was deleted or the data ran out its time.
     */
    public static function bySystem(?User $account = null): self
    {
        return new self(ConsentMethod::System, account: $account);
    }

    /**
     * @param  array<string, int|bool>  $details
     */
    public function with(array $details): self
    {
        return new self($this->method, $this->staff, $this->account, $this->ip, $this->userAgent, $this->text, $details);
    }
}
