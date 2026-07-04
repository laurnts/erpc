<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\CustomFields\PeopleField;
use App\Models\Company;
use App\Models\People;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class RecordContextBuilder
{
    /**
     * Build context data for a record to be used in AI summary generation.
     *
     * @return array<string, mixed>
     */
    public function buildContext(Model $record): array
    {
        return match (true) {
            $record instanceof Company => $this->buildCompanyContext($record),
            $record instanceof People => $this->buildPeopleContext($record),
            default => throw new InvalidArgumentException('Unsupported record type: '.$record::class),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCompanyContext(Company $company): array
    {
        $company->loadCount(['people']);

        $company->load([
            'accountOwner',
            'customFieldValues.customField',
        ]);

        return [
            'entity_type' => 'Company',
            'name' => $company->name,
            'basic_info' => $this->getCompanyBasicInfo($company),
            'relationships' => [
                'people_count' => $company->people_count,
            ],
            'last_updated' => $company->updated_at?->diffForHumans(),
            'created' => $company->created_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPeopleContext(People $person): array
    {
        $person->load([
            'company',
            'customFieldValues.customField',
        ]);

        return [
            'entity_type' => 'Person',
            'name' => $person->name,
            'basic_info' => $this->getPeopleBasicInfo($person),
            'company' => $person->company?->name,
            'last_updated' => $person->updated_at?->diffForHumans(),
            'created' => $person->created_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCompanyBasicInfo(Company $company): array
    {
        return collect([
            'domain' => $company->domain,
            'account_owner' => $company->accountOwner?->name,
        ])->filter(fn (mixed $value): bool => filled($value))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function getPeopleBasicInfo(People $person): array
    {
        $emails = $this->getCustomFieldValue($person, PeopleField::EMAILS->value);

        return collect([
            'job_title' => $this->getCustomFieldValue($person, PeopleField::JOB_TITLE->value),
            'emails' => is_array($emails) ? implode(', ', $emails) : $emails,
        ])->filter(fn (mixed $value): bool => filled($value))->all();
    }

    private function getCustomFieldValue(Model $model, string $code): mixed
    {
        if (! method_exists($model, 'customFieldValues')) {
            return null;
        }

        /** @var Collection<int, \Relaticle\CustomFields\Models\CustomFieldValue> $customFieldValues */
        $customFieldValues = $model->customFieldValues; // @phpstan-ignore property.notFound

        $customFieldValue = $customFieldValues->first(fn (\Relaticle\CustomFields\Models\CustomFieldValue $cfv): bool => $cfv->customField->code === $code);

        if ($customFieldValue === null) {
            return null;
        }

        return $model->getCustomFieldValue($customFieldValue->customField); // @phpstan-ignore method.notFound
    }
}
