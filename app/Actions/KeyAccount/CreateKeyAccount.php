<?php

declare(strict_types=1);

namespace App\Actions\KeyAccount;

use App\Models\KeyAccount;

/**
 * Action to create a new Key Account.
 *
 * This action consolidates key account creation logic that was previously
 * duplicated across multiple Filament resources and Livewire components.
 */
final readonly class CreateKeyAccount
{
    /**
     * Create a new Key Account from the provided data.
     *
     * @param  array{name: string, email?: string|null, phone?: string|null, is_active?: bool}  $data
     */
    public function execute(array $data): KeyAccount
    {
        return KeyAccount::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
