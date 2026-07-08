<?php

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Enums\ActorType;

/**
 * Identifies WHO is looking at a request timeline.
 *
 * Staff and admin are internal parties; buyer and supplier parties are
 * identity-scoped by company id so the audience helper can key its
 * allow-list rules to that specific company (supplier #42 must never
 * resolve rules that reach supplier #43's records on a shared request).
 *
 * The constructor is private: the named factories are the only way to
 * build a party, which guarantees portal parties always carry a company id
 * and that a System party can never be constructed.
 */
final readonly class TimelineParty
{
    private function __construct(
        public ActorType $actorType,
        public ?int $companyId,
    ) {}

    public static function staff(): self
    {
        return new self(ActorType::Staff, null);
    }

    public static function admin(): self
    {
        return new self(ActorType::Admin, null);
    }

    public static function buyer(int $companyId): self
    {
        return new self(ActorType::Buyer, $companyId);
    }

    public static function supplier(int $companyId): self
    {
        return new self(ActorType::Supplier, $companyId);
    }

    public function isInternal(): bool
    {
        return $this->actorType === ActorType::Staff || $this->actorType === ActorType::Admin;
    }
}
