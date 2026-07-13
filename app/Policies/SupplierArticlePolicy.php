<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierArticle;
use App\Models\User;
use App\Policies\Concerns\ResolvesPanelContext;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Supplier-portal policy for supplier-article links (consulted under the
 * supplier panel's strictAuthorization()). Suppliers may view and update
 * only their own company's rows; listing management (create/delete/attach)
 * is staff-owned and always denied here.
 */
final readonly class SupplierArticlePolicy
{
    use HandlesAuthorization;
    use ResolvesPanelContext;

    public function viewAny(User $user): bool
    {
        return $user->hasActiveSupplierPortalAccess();
    }

    public function view(User $user, SupplierArticle $supplierArticle): bool
    {
        return $this->userOwnsSupplierCompany($user, $supplierArticle->supplier_id);
    }

    public function update(User $user, SupplierArticle $supplierArticle): bool
    {
        return $this->userOwnsSupplierCompany($user, $supplierArticle->supplier_id);
    }

    public function create(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function forceDeleteAny(): bool
    {
        return false;
    }

    public function restore(): bool
    {
        return false;
    }

    public function restoreAny(): bool
    {
        return false;
    }
}
