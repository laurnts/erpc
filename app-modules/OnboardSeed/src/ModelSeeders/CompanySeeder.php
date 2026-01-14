<?php

declare(strict_types=1);

namespace Relaticle\OnboardSeed\ModelSeeders;

use App\Models\Company;
use App\Models\Team;
use Illuminate\Contracts\Auth\Authenticatable;
use Relaticle\OnboardSeed\Support\BaseModelSeeder;

final class CompanySeeder extends BaseModelSeeder
{
    protected string $modelClass = Company::class;

    protected string $entityType = 'companies';

    /** @var array<int, string> */
    protected array $fieldCodes = [];

    /**
     * Create company entities from fixtures
     *
     * @param  Team  $team  The team to create data for
     * @param  Authenticatable  $user  The user creating the data
     * @param  array<string, mixed>  $context  Context data from previous seeders
     * @return array<string, mixed> Seeded data for use by subsequent seeders
     */
    protected function createEntitiesFromFixtures(Team $team, Authenticatable $user, array $context = []): array
    {
        $fixtures = $this->loadEntityFixtures();
        $companies = [];

        foreach ($fixtures as $key => $data) {
            $company = $this->createCompanyFromFixture($team, $user, $key, $data);
            $companies[$key] = $company;
        }

        return [
            'companies' => $companies,
        ];
    }

    /**
     * Create a company from fixture data
     *
     * @param  array<string, mixed>  $data
     */
    private function createCompanyFromFixture(Team $team, Authenticatable $user, string $key, array $data): Company
    {
        $attributes = [
            'name' => $data['name'],
            'domain' => $data['domain'] ?? null,
            'account_owner_id' => $user->getAuthIdentifier(),
        ];

        $customFields = $data['custom_fields'] ?? [];

        /** @var Company */
        return $this->registerEntityFromFixture($key, $attributes, $customFields, $team, $user);
    }
}
