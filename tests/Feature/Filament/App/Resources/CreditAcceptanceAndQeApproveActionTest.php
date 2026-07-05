<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use App\Enums\QEStatus;
use App\Filament\Resources\CreditLimitAcceptanceResource\Pages\ListCreditLimitAcceptances;
use App\Filament\Resources\QuotationEvaluationResource\Pages\ViewQuotationEvaluation;
use App\Models\Membership;
use App\Models\PaymentDocumentApproval;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();
});

/**
 * Grant a Central Purchasing membership on the acting team to the given (or a new) user.
 */
function acceptanceQeMember(Tests\TestCase $test, CentralPurchasingRole $role, bool $isApprover = false, ?User $user = null): User
{
    $user ??= User::factory()->withPersonalTeam()->create();

    Membership::factory()->create([
        'team_id' => $test->team->getKey(),
        'user_id' => $user->getKey(),
        'role' => 'central_purchasing',
        'central_purchasing_role' => $role,
        'is_approver' => $isApprover,
    ]);

    return $user;
}

/**
 * Create a Quotation Evaluation (status defaults to Need Approval) on the acting team.
 *
 * @param  array<string, mixed>  $attributes
 */
function acceptanceQeEvaluation(Tests\TestCase $test, array $attributes = []): QuotationEvaluation
{
    $request = Request::factory()
        ->for($test->team)
        ->create(['creator_id' => $test->user->getKey()]);

    return QuotationEvaluation::factory()
        ->forRequest($request)
        ->create(['creator_id' => $test->user->getKey(), ...$attributes])
        ->refresh();
}

/**
 * Attach a media record to the given model so it appears in the Credit Limit Acceptances table.
 *
 * @param  array<string, mixed>  $customProperties
 */
function acceptanceReportMedia(\Illuminate\Database\Eloquent\Model $model, string $collection, array $customProperties = []): Media
{
    /** @var Media $media */
    $media = $model->media()->create([
        'uuid' => Str::uuid()->toString(),
        'collection_name' => $collection,
        'name' => 'acceptance-doc',
        'file_name' => 'acceptance-doc.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'local',
        'conversions_disk' => 'local',
        'size' => 1024,
        'manipulations' => [],
        'custom_properties' => $customProperties,
        'generated_conversions' => [],
        'responsive_images' => [],
    ]);

    return $media;
}

describe('CreditLimitAcceptanceReport approve action', function (): void {
    it('approves a QE document as key account, recording the approval and force-approving the QE', function (): void {
        acceptanceQeMember($this, CentralPurchasingRole::KEY_ACCOUNT, user: $this->user);
        $qe = acceptanceQeEvaluation($this);
        $media = acceptanceReportMedia($qe, 'documents');

        livewire(ListCreditLimitAcceptances::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$media])
            ->callAction(TestAction::make('approve')->table($media), data: ['notes' => 'Docs verified'])
            ->assertHasNoErrors()
            ->assertNotified('Document approved');

        $approval = PaymentDocumentApproval::query()->where('media_id', $media->getKey())->first();
        expect($approval)->not->toBeNull()
            ->and($approval->team_id)->toBe($this->team->getKey())
            ->and($approval->user_id)->toBe($this->user->getKey())
            ->and($approval->notes)->toBe('Docs verified')
            ->and($approval->approved_at)->not->toBeNull();

        $qe->refresh();
        expect($qe->status)->toBe(QEStatus::APPROVED)
            ->and($qe->dept_head_sales_approved_at)->not->toBeNull()
            ->and($qe->deputy_director_approved_at)->not->toBeNull()
            ->and($qe->director_approved_at)->not->toBeNull();
    });

    it('approves a payment document as finance approver without touching any related record', function (): void {
        acceptanceQeMember($this, CentralPurchasingRole::FINANCE, isApprover: true, user: $this->user);
        $request = Request::factory()->for($this->team)->create(['creator_id' => $this->user->getKey()]);
        $media = acceptanceReportMedia($request, 'completion_reports', [
            'is_payment_document' => true,
            'payment_terms' => '30-100',
        ]);

        livewire(ListCreditLimitAcceptances::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$media])
            ->callAction(TestAction::make('approve')->table($media), data: ['notes' => null])
            ->assertHasNoErrors()
            ->assertNotified('Document approved');

        $approval = PaymentDocumentApproval::query()->where('media_id', $media->getKey())->first();
        expect($approval)->not->toBeNull()
            ->and($approval->user_id)->toBe($this->user->getKey())
            ->and($approval->notes)->toBeNull()
            ->and(PaymentDocumentApproval::query()->count())->toBe(1);
    });

    it('hides approve from a team member without any central purchasing membership', function (): void {
        $qe = acceptanceQeEvaluation($this);
        $media = acceptanceReportMedia($qe, 'documents');

        livewire(ListCreditLimitAcceptances::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$media])
            ->assertActionHidden(TestAction::make('approve')->table($media));

        expect(PaymentDocumentApproval::query()->count())->toBe(0);
    });

    it('only offers a finance approver the payment document, not the QE document', function (): void {
        acceptanceQeMember($this, CentralPurchasingRole::FINANCE, isApprover: true, user: $this->user);
        $qe = acceptanceQeEvaluation($this);
        $qeMedia = acceptanceReportMedia($qe, 'documents');
        $paymentMedia = acceptanceReportMedia($qe->request, 'completion_reports', ['is_payment_document' => true]);

        livewire(ListCreditLimitAcceptances::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$qeMedia, $paymentMedia])
            ->assertActionVisible(TestAction::make('approve')->table($paymentMedia))
            ->assertActionHidden(TestAction::make('approve')->table($qeMedia));
    });

    it('only offers a key account the QE document, not the payment document', function (): void {
        acceptanceQeMember($this, CentralPurchasingRole::KEY_ACCOUNT, user: $this->user);
        $qe = acceptanceQeEvaluation($this);
        $qeMedia = acceptanceReportMedia($qe, 'documents');
        $paymentMedia = acceptanceReportMedia($qe->request, 'completion_reports', ['is_payment_document' => true]);

        livewire(ListCreditLimitAcceptances::class)
            ->assertOk()
            ->assertActionVisible(TestAction::make('approve')->table($qeMedia))
            ->assertActionHidden(TestAction::make('approve')->table($paymentMedia));
    });

    it('hides approve on a payment document from a finance member who is not a designated approver', function (): void {
        acceptanceQeMember($this, CentralPurchasingRole::FINANCE, isApprover: false, user: $this->user);
        $request = Request::factory()->for($this->team)->create(['creator_id' => $this->user->getKey()]);
        $media = acceptanceReportMedia($request, 'completion_reports', ['is_payment_document' => true]);

        livewire(ListCreditLimitAcceptances::class)
            ->assertOk()
            ->assertActionHidden(TestAction::make('approve')->table($media));
    });

    it('hides approve once the document already has an approval', function (): void {
        acceptanceQeMember($this, CentralPurchasingRole::KEY_ACCOUNT, user: $this->user);
        $otherKeyAccount = acceptanceQeMember($this, CentralPurchasingRole::KEY_ACCOUNT);
        $qe = acceptanceQeEvaluation($this);
        $media = acceptanceReportMedia($qe, 'documents');

        PaymentDocumentApproval::create([
            'team_id' => $this->team->getKey(),
            'media_id' => $media->getKey(),
            'user_id' => $otherKeyAccount->getKey(),
            'approved_at' => now(),
        ]);

        livewire(ListCreditLimitAcceptances::class)
            ->assertOk()
            ->assertActionHidden(TestAction::make('approve')->table($media));

        expect(PaymentDocumentApproval::query()->count())->toBe(1)
            ->and(PaymentDocumentApproval::query()->first()->user_id)->toBe($otherKeyAccount->getKey());
    });
});

describe('QuotationEvaluation approve action', function (): void {
    it('approves, stamps the director and transitions to approved when the sole assigned approver approves', function (): void {
        $director = acceptanceQeMember($this, CentralPurchasingRole::DIRECTOR);
        $qe = acceptanceQeEvaluation($this, ['approved_by_id' => $director->getKey()]);

        $this->actingAs($director);

        livewire(ViewQuotationEvaluation::class, ['record' => $qe->getKey()])
            ->assertOk()
            ->assertActionVisible('approve')
            ->callAction('approve')
            ->assertNotified('Quotation Evaluation approved');

        $qe->refresh();
        expect($qe->status)->toBe(QEStatus::APPROVED)
            ->and($qe->director_approved_at)->not->toBeNull()
            ->and($qe->dept_head_sales_approved_at)->toBeNull()
            ->and($qe->deputy_director_approved_at)->toBeNull();
    });

    it('records a partial approval without transitioning while another approver is pending', function (): void {
        $deptHead = acceptanceQeMember($this, CentralPurchasingRole::DEPT_HEAD_SALES);
        $director = acceptanceQeMember($this, CentralPurchasingRole::DIRECTOR);
        $qe = acceptanceQeEvaluation($this, [
            'dept_head_sales_id' => $deptHead->getKey(),
            'approved_by_id' => $director->getKey(),
        ]);

        $this->actingAs($deptHead);

        livewire(ViewQuotationEvaluation::class, ['record' => $qe->getKey()])
            ->assertOk()
            ->callAction('approve')
            ->assertNotified('Quotation Evaluation approved');

        $qe->refresh();
        expect($qe->dept_head_sales_approved_at)->not->toBeNull()
            ->and($qe->director_approved_at)->toBeNull()
            ->and($qe->status)->toBe(QEStatus::NEED_APPROVAL);
    });

    it('hides approve from a team member who is not an assigned approver', function (): void {
        $director = acceptanceQeMember($this, CentralPurchasingRole::DIRECTOR);
        $qe = acceptanceQeEvaluation($this, ['approved_by_id' => $director->getKey()]);

        livewire(ViewQuotationEvaluation::class, ['record' => $qe->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');

        expect($qe->fresh()->status)->toBe(QEStatus::NEED_APPROVAL);
    });

    it('hides approve from an assigned approver whose central purchasing role does not match', function (): void {
        $finance = acceptanceQeMember($this, CentralPurchasingRole::FINANCE, isApprover: true);
        $qe = acceptanceQeEvaluation($this, ['approved_by_id' => $finance->getKey()]);

        $this->actingAs($finance);

        livewire(ViewQuotationEvaluation::class, ['record' => $qe->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');

        expect($qe->fresh()->status)->toBe(QEStatus::NEED_APPROVAL)
            ->and($qe->fresh()->director_approved_at)->toBeNull();
    });

    it('hides approve once the evaluation is fully approved', function (): void {
        $director = acceptanceQeMember($this, CentralPurchasingRole::DIRECTOR);
        $qe = acceptanceQeEvaluation($this, ['approved_by_id' => $director->getKey()]);

        $qe->approve($director);
        expect($qe->fresh()->status)->toBe(QEStatus::APPROVED);

        $this->actingAs($director);

        livewire(ViewQuotationEvaluation::class, ['record' => $qe->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');
    });

    it('hides approve from an approver who already approved while another approval is still pending', function (): void {
        $deptHead = acceptanceQeMember($this, CentralPurchasingRole::DEPT_HEAD_SALES);
        $director = acceptanceQeMember($this, CentralPurchasingRole::DIRECTOR);
        $qe = acceptanceQeEvaluation($this, [
            'dept_head_sales_id' => $deptHead->getKey(),
            'approved_by_id' => $director->getKey(),
        ]);

        $qe->approve($deptHead);

        $this->actingAs($deptHead);

        livewire(ViewQuotationEvaluation::class, ['record' => $qe->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');

        expect($qe->fresh()->status)->toBe(QEStatus::NEED_APPROVAL);
    });
});
