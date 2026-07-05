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

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, SupplierArticle $supplierArticle): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, SupplierArticle $supplierArticle): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, SupplierArticle $supplierArticle): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
