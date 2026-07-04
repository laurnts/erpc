<?php

declare(strict_types=1);

use App\Actions\SupplierPortal\InviteSupplierPortalUser;
use App\Enums\PortalType;
use App\Filament\Supplier\Pages\AcceptPortalInvitation;
use App\Filament\Supplier\Pages\SupplierDashboard;
use App\Filament\Supplier\Resources\SupplierArticleResource;
use App\Filament\Supplier\Resources\SupplierArticleResource\Pages\EditSupplierArticle;
use App\Filament\Supplier\Resources\SupplierArticleResource\Pages\ListSupplierArticles;
use App\Filament\Supplier\Widgets\SupplierStalePricesWidget;
use App\Mail\SupplierPortalUserInvitationMail;
use App\Models\Article;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\SupplierArticle;
use App\Models\Team;
use App\Models\User;
use App\Services\Portal\SupplierPortalContext;
use App\Support\PanelDomain;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config(['app.supplier_portal_enabled' => true]);

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);

    $this->supplier = Company::factory()->supplier()->for($this->team)->create();

    $this->portalUser = User::factory()->create([
        'email' => 'portal@supplier.test',
    ]);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->supplier->getKey(),
        'user_id' => $this->portalUser->getKey(),
        'portal' => PortalType::Supplier,
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);
});

describe('Supplier Portal Access', function (): void {
    it('allows supplier portal user to access supplier panel only', function (): void {
        Filament::setCurrentPanel('supplier');

        expect($this->portalUser->canAccessPanel(Filament::getPanel('supplier')))->toBeTrue()
            ->and($this->portalUser->canAccessPanel(Filament::getPanel('customer')))->toBeFalse()
            ->and($this->portalUser->canAccessPanel(Filament::getPanel('app')))->toBeFalse();
    });

    it('denies internal user without portal membership from supplier panel', function (): void {
        expect($this->admin->canAccessPanel(Filament::getPanel('supplier')))->toBeFalse()
            ->and($this->admin->canAccessPanel(Filament::getPanel('app')))->toBeTrue();
    });

    it('denies buyer-only portal membership from supplier panel', function (): void {
        $buyer = Company::factory()->buyer()->for($this->team)->create();
        $buyerContact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $buyer->getKey(),
            'user_id' => $buyerContact->getKey(),
            'portal' => PortalType::Customer,
            'is_active' => true,
        ]);

        expect($buyerContact->canAccessPanel(Filament::getPanel('supplier')))->toBeFalse()
            ->and($buyerContact->hasActiveSupplierPortalAccess())->toBeFalse();
    });

    it('denies supplier panel access for a customer-typed membership at a dual-role company', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();
        $customerContact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $customerContact->getKey(),
            'portal' => PortalType::Customer,
            'is_active' => true,
        ]);

        expect($customerContact->canAccessPanel(Filament::getPanel('supplier')))->toBeFalse()
            ->and($customerContact->canAccessPanel(Filament::getPanel('customer')))->toBeTrue();
    });

    it('denies customer panel access for a supplier-typed membership at a dual-role company', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();
        $supplierContact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $supplierContact->getKey(),
            'portal' => PortalType::Supplier,
            'is_active' => true,
        ]);

        expect($supplierContact->canAccessPanel(Filament::getPanel('customer')))->toBeFalse()
            ->and($supplierContact->canAccessPanel(Filament::getPanel('supplier')))->toBeTrue();
    });

    it('denies supplier-typed membership when the company is not a supplier', function (): void {
        $buyerOnly = Company::factory()->buyer()->for($this->team)->create();
        $contact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $buyerOnly->getKey(),
            'user_id' => $contact->getKey(),
            'portal' => PortalType::Supplier,
            'is_active' => true,
        ]);

        expect($contact->canAccessPanel(Filament::getPanel('supplier')))->toBeFalse()
            ->and($contact->hasActiveSupplierPortalAccess())->toBeFalse();
    });

    it('protects supplier routes when the portal is disabled', function (): void {
        config(['app.supplier_portal_enabled' => false]);

        $this->get(url()->getSupplierPortalUrl('login'))->assertNotFound();
    });

    it('shows supplier login on the supplier host', function (): void {
        $host = PanelDomain::supplierHost();

        expect(url()->getSupplierPortalUrl('login'))->toBe("http://{$host}/supplier/login");

        $this->get(url()->getSupplierPortalUrl('login'), ['Host' => $host])
            ->assertOk()
            ->assertSee('Supplier Sign in')
            ->assertSee('Sign In');
    });

    it('resolves supplier portal team and company from context', function (): void {
        $this->actingAs($this->portalUser, 'supplier');
        app(SupplierPortalContext::class)->setCompany($this->supplier->getKey());

        expect(app(SupplierPortalContext::class)->team()->getKey())->toBe($this->team->getKey())
            ->and(app(SupplierPortalContext::class)->company()->getKey())->toBe($this->supplier->getKey());
    });

    it('excludes customer-typed memberships from the supplier context', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $this->portalUser->getKey(),
            'portal' => PortalType::Customer,
            'is_active' => true,
        ]);

        $memberships = app(SupplierPortalContext::class)->activeMemberships($this->portalUser);

        expect($memberships->pluck('company_id')->all())->toBe([$this->supplier->getKey()]);
    });
});

describe('Supplier Portal Invitation', function (): void {
    it('creates a supplier-typed invitation and sends an email with a supplier panel acceptance url', function (): void {
        Mail::fake();

        $this->actingAs($this->admin);
        Filament::setTenant($this->team);
        Filament::setCurrentPanel('app');

        $invitation = app(InviteSupplierPortalUser::class)->execute(
            team: $this->team,
            supplier: $this->supplier,
            email: 'new.portal@supplier.test',
            name: 'Supplier Contact',
            invitedBy: $this->admin,
        );

        expect($invitation)->toBeInstanceOf(PortalInvitation::class)
            ->and($invitation->email)->toBe('new.portal@supplier.test')
            ->and($invitation->portal)->toBe(PortalType::Supplier);

        Mail::assertSent(
            SupplierPortalUserInvitationMail::class,
            fn (SupplierPortalUserInvitationMail $mail): bool => str_contains($mail->acceptUrl, '/supplier/invitation/'),
        );
    });

    it('rejects invitations for companies that are not suppliers', function (): void {
        Mail::fake();

        $buyerOnly = Company::factory()->buyer()->for($this->team)->create();

        expect(fn () => app(InviteSupplierPortalUser::class)->execute(
            team: $this->team,
            supplier: $buyerOnly,
            email: 'contact@buyer.test',
            name: 'Buyer Contact',
            invitedBy: $this->admin,
        ))->toThrow(\Illuminate\Validation\ValidationException::class);

        Mail::assertNothingSent();
    });

    it('rejects invitation when email belongs to an existing user', function (): void {
        Mail::fake();

        expect(fn () => app(InviteSupplierPortalUser::class)->execute(
            team: $this->team,
            supplier: $this->supplier,
            email: $this->portalUser->email,
            name: 'Supplier Contact',
            invitedBy: $this->admin,
        ))->toThrow(\Illuminate\Validation\ValidationException::class);

        Mail::assertNothingSent();

        expect(PortalInvitation::query()->where('email', $this->portalUser->email)->exists())->toBeFalse();
    });

    it('accepts invitation, creating a verified user with a supplier-typed membership', function (): void {
        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->supplier->getKey(),
            'email' => 'accept@supplier.test',
            'name' => 'Accept User',
            'portal' => PortalType::Supplier,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        Filament::setCurrentPanel('supplier');

        livewire(AcceptPortalInvitation::class, ['token' => $invitation->token])
            ->fillForm([
                'name' => 'Accept User',
                'email' => 'accept@supplier.test',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->call('accept')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'accept@supplier.test')->firstOrFail();
        $membership = CompanyPortalUser::query()
            ->where('company_id', $this->supplier->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        expect($user->email_verified_at)->not->toBeNull()
            ->and($membership->portal)->toBe(PortalType::Supplier)
            ->and($membership->is_active)->toBeTrue()
            ->and($invitation->fresh()?->accepted_at)->not->toBeNull()
            ->and($user->canAccessPanel(Filament::getPanel('supplier')))->toBeTrue();
    });

    it('does not accept customer invitations on the supplier acceptance page', function (): void {
        $buyer = Company::factory()->buyer()->for($this->team)->create();

        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $buyer->getKey(),
            'email' => 'customer@buyer.test',
            'name' => 'Customer User',
            'portal' => PortalType::Customer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        Filament::setCurrentPanel('supplier');

        expect(fn () => livewire(AcceptPortalInvitation::class, ['token' => $invitation->token]))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('Supplier Article Self-Service', function (): void {
    beforeEach(function (): void {
        $this->article = Article::factory()->for($this->team)->create(['name' => 'Own Widget']);
        $this->ownRow = SupplierArticle::factory()->create([
            'article_id' => $this->article->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'supplier_price' => '100.0000',
            'is_preferred' => false,
        ]);

        $this->otherSupplier = Company::factory()->supplier()->for($this->team)->create();
        $this->otherArticle = Article::factory()->for($this->team)->create(['name' => 'Foreign Widget']);
        $this->otherRow = SupplierArticle::factory()->create([
            'article_id' => $this->otherArticle->getKey(),
            'supplier_id' => $this->otherSupplier->getKey(),
        ]);

        $this->actingAs($this->portalUser, 'supplier');
        Filament::setCurrentPanel('supplier');
        app(SupplierPortalContext::class)->setCompany($this->supplier->getKey());
    });

    it('lists only own supplier-article rows', function (): void {
        livewire(ListSupplierArticles::class)
            ->assertCanSeeTableRecords([$this->ownRow])
            ->assertCanNotSeeTableRecords([$this->otherRow]);
    });

    it('scopes the resource query to the portal company', function (): void {
        $ids = SupplierArticleResource::getEloquentQuery()->pluck('id')->all();

        expect($ids)->toContain($this->ownRow->getKey())
            ->not->toContain($this->otherRow->getKey());
    });

    it('denies view and update of another supplier\'s rows by policy', function (): void {
        expect($this->portalUser->can('view', $this->ownRow))->toBeTrue()
            ->and($this->portalUser->can('update', $this->ownRow))->toBeTrue()
            ->and($this->portalUser->can('view', $this->otherRow))->toBeFalse()
            ->and($this->portalUser->can('update', $this->otherRow))->toBeFalse();
    });

    it('denies create and delete everywhere in the panel', function (): void {
        expect($this->portalUser->can('create', SupplierArticle::class))->toBeFalse()
            ->and($this->portalUser->can('delete', $this->ownRow))->toBeFalse()
            ->and(SupplierArticleResource::canCreate())->toBeFalse()
            ->and(SupplierArticleResource::canDelete($this->ownRow))->toBeFalse();
    });

    it('updates the four supplier-writable fields and stamps timestamps', function (): void {
        livewire(EditSupplierArticle::class, ['record' => $this->ownRow->getKey()])
            ->fillForm([
                'supplier_price' => '150.5000',
                'available_quantity' => '25',
                'lead_time_days' => 7,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $row = $this->ownRow->refresh();

        expect($row->supplier_price)->toBe('150.5000')
            ->and($row->available_quantity)->toBe('25.0000')
            ->and($row->lead_time_days)->toBe(7)
            ->and($row->supplier_price_updated_at)->not->toBeNull()
            ->and($row->quantity_updated_at)->not->toBeNull();
    });

    it('ignores tampered payloads writing staff-owned fields', function (): void {
        livewire(EditSupplierArticle::class, ['record' => $this->ownRow->getKey()])
            ->set('data.is_preferred', true)
            ->set('data.last_quoted_price', '999.99')
            ->set('data.supplier_sku', 'HACKED-SKU')
            ->fillForm([
                'supplier_price' => '175.0000',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $row = $this->ownRow->refresh();

        expect($row->supplier_price)->toBe('175.0000')
            ->and($row->is_preferred)->toBeFalse()
            ->and($row->last_quoted_price)->toBeNull()
            ->and($row->supplier_sku)->not->toBe('HACKED-SKU');
    });

    it('loads the supplier dashboard with the stale prices widget scoped to own rows', function (): void {
        livewire(SupplierDashboard::class)->assertOk();

        livewire(SupplierStalePricesWidget::class)
            ->assertCanSeeTableRecords([$this->ownRow])
            ->assertCanNotSeeTableRecords([$this->otherRow]);
    });
});

describe('Supplier Portal Deactivation', function (): void {
    it('revokes panel capability when the membership is deactivated', function (): void {
        CompanyPortalUser::query()
            ->where('user_id', $this->portalUser->getKey())
            ->update(['is_active' => false]);

        expect($this->portalUser->hasActiveSupplierPortalAccess())->toBeFalse()
            ->and($this->portalUser->canAccessPanel(Filament::getPanel('supplier')))->toBeFalse();
    });

    it('force-logs-out a deactivated supplier user on the next request', function (): void {
        $host = PanelDomain::supplierHost();

        $this->actingAs($this->portalUser, 'supplier')
            ->get("http://{$host}/supplier", ['Host' => $host])
            ->assertOk();

        CompanyPortalUser::query()
            ->where('user_id', $this->portalUser->getKey())
            ->update(['is_active' => false]);

        $this->get("http://{$host}/supplier", ['Host' => $host])
            ->assertRedirect(url()->getSupplierPortalUrl('login'));
    });
});
