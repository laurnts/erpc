<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Key Account personnel for approval workflows.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $is_active
 * @property int|null $creator_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $created_by
 * @property-read string $display_name
 */
final class KeyAccount extends Model
{
    use HasCreator;
    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Quotation Evaluations where this person is the preparer.
     *
     * @return HasMany<QuotationEvaluation, $this>
     */
    public function preparedEvaluations(): HasMany
    {
        return $this->hasMany(QuotationEvaluation::class, 'prepared_by_id');
    }

    /**
     * Quotation Evaluations where this person is the approver.
     *
     * @return HasMany<QuotationEvaluation, $this>
     */
    public function approvedEvaluations(): HasMany
    {
        return $this->hasMany(QuotationEvaluation::class, 'approved_by_id');
    }

    /**
     * Get display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }
}
