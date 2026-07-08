<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActorType;
use App\Enums\NoteVisibility;
use App\Models\Company;
use App\Models\Request;
use App\Models\RequestNote;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestNote>
 */
final class RequestNoteFactory extends Factory
{
    protected $model = RequestNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'request_id' => Request::factory(),
            'author_id' => User::factory(),
            'author_actor_type' => ActorType::Staff,
            'body' => $this->faker->sentence(),
            'visibility' => NoteVisibility::Internal,
            'audience_company_id' => null,
        ];
    }

    /**
     * An internal-only note (default) — never reaches a portal.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => NoteVisibility::Internal,
            'audience_company_id' => null,
        ]);
    }

    /**
     * A note shared with the request's buyer.
     */
    public function sharedWithBuyer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => NoteVisibility::Buyer,
            'audience_company_id' => null,
        ]);
    }

    /**
     * A note shared with a single supplier company.
     */
    public function sharedWithSupplier(Company|int|null $company = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => NoteVisibility::Supplier,
            'audience_company_id' => $company instanceof Company
                ? $company->getKey()
                : ($company ?? Company::factory()->supplier()),
        ]);
    }

    /**
     * Attribute the note to a staff author.
     */
    public function authoredByStaff(?User $author = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'author_actor_type' => ActorType::Staff,
            'author_id' => $author?->getKey() ?? User::factory(),
        ]);
    }

    /**
     * Attribute the note to a buyer-portal author.
     */
    public function authoredByBuyer(?User $author = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'author_actor_type' => ActorType::Buyer,
            'author_id' => $author?->getKey() ?? User::factory(),
        ]);
    }

    /**
     * Attribute the note to a supplier-portal author.
     */
    public function authoredBySupplier(?User $author = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'author_actor_type' => ActorType::Supplier,
            'author_id' => $author?->getKey() ?? User::factory(),
        ]);
    }
}
