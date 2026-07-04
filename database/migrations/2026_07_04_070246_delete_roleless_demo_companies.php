<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> Morph aliases that resolve to the Company model */
    private array $companyMorphTypes = ['company', 'buyer', 'supplier'];

    /**
     * Run the migrations.
     *
     * Every company must now be a buyer, a supplier, or both; the standalone
     * Companies resource that allowed role-less companies is retired. The only
     * role-less rows are OnboardSeed demo companies, which this deletes together
     * with their seeded notes/tasks/opportunities. Companies with people or
     * transactional references are skipped. Idempotent and safe to re-run.
     */
    public function up(): void
    {
        $companyIds = DB::table('companies')
            ->where('is_buyer', false)
            ->where('is_supplier', false)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('company_people')
                ->whereColumn('company_people.company_id', 'companies.id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('requests')
                ->whereColumn('requests.buyer_id', 'companies.id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('projects')
                ->whereColumn('projects.buyer_id', 'companies.id'))
            ->pluck('id');

        if ($companyIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('opportunities')) {
            DB::table('opportunities')->whereIn('company_id', $companyIds)->delete();
        }

        // hasTable guards: the later remove_tasks_and_notes_entities migration
        // drops these tables, so re-runs (tests) may find them gone.
        if (Schema::hasTable('noteables')) {
            $this->deletePolymorphicAttachments('noteables', 'noteable', 'notes', 'note_id', $companyIds->all());
        }

        if (Schema::hasTable('taskables')) {
            $this->deletePolymorphicAttachments('taskables', 'taskable', 'tasks', 'task_id', $companyIds->all());
        }

        DB::table('companies')->whereIn('id', $companyIds)->delete();
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally irreversible: deleted rows were unreachable demo seed data.
     */
    public function down(): void
    {
        //
    }

    /**
     * Detach the companies from a morph pivot, then delete parent records
     * (notes/tasks) that were attached to them and have no remaining attachments.
     *
     * @param  array<int, int>  $companyIds
     */
    private function deletePolymorphicAttachments(string $pivotTable, string $morphName, string $parentTable, string $parentKey, array $companyIds): void
    {
        $parentIds = DB::table($pivotTable)
            ->whereIn("{$morphName}_type", $this->companyMorphTypes)
            ->whereIn("{$morphName}_id", $companyIds)
            ->pluck($parentKey);

        DB::table($pivotTable)
            ->whereIn("{$morphName}_type", $this->companyMorphTypes)
            ->whereIn("{$morphName}_id", $companyIds)
            ->delete();

        if ($parentIds->isEmpty()) {
            return;
        }

        DB::table($parentTable)
            ->whereIn('id', $parentIds->unique())
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from($pivotTable)
                ->whereColumn("{$pivotTable}.{$parentKey}", "{$parentTable}.id"))
            ->delete();
    }
};
