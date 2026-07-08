<?php

declare(strict_types=1);

namespace App\Services\Timeline;

/**
 * Additive scoping rule for one timeline subject type.
 *
 * A party may only load activity rows whose subject matches one of its
 * rules; a subject type without a rule is denied by default. Constraints
 * are declarative so the read sources can translate them into query
 * predicates, and so tests can assert identity scoping structurally
 * (e.g. supplier #42's rules carry 42 and never another company's id).
 */
final readonly class SubjectRule
{
    /**
     * @param  string  $subjectType  morph alias of the allowed subject
     * @param  array<string, mixed>  $where  attribute (or dotted relation path, e.g. 'request.buyer_id') => required value
     * @param  array<string, list<string>>  $whereNot  attribute => disallowed values
     */
    public function __construct(
        public string $subjectType,
        public array $where = [],
        public array $whereNot = [],
    ) {}
}
