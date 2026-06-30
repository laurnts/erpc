<?php

declare(strict_types=1);

use App\Actions\CustomerPortal\InvitePortalUser;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Customer\Pages\Auth\CustomerLogin;
use App\Filament\Customer\Pages\CustomerDashboard;
use App\Filament\Pages\Auth\Login as AppLogin;
use App\Filament\Resources\CompanyResource;
use App\Http\Middleware\UseCustomerPanelSession;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Shipment;
use App\Models\Team;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\CustomerPortal\PortalContext;
use App\Support\PanelDomain;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config(['app.customer_portal_enabled' => true]);

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();

    $this->portalUser = User::factory()->create([
        'email' => 'portal@buyer.test',
    ]);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->buyer->getKey(),
        'user_id' => $this->portalUser->getKey(),
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);
});

describe('Customer Portal Access', function (): void {
    it('allows portal user to access customer panel', function (): void {
        Filament::setCurrentPanel('customer');

        expect($this->portalUser->canAccessPanel(Filament::getPanel('customer')))->toBeTrue()
            ->and($this->portalUser->canAccessPanel(Filament::getPanel('app')))->toBeFalse();
    });

    it('denies internal user without portal access from customer panel', function (): void {
        $internalUser = User::factory()->withPersonalTeam()->create();

        Filament::setCurrentPanel('customer');

        expect($internalUser->canAccessPanel(Filament::getPanel('customer')))->toBeFalse()
            ->and($internalUser->canAccessPanel(Filament::getPanel('app')))->toBeTrue();
    });

    it('protects customer routes when portal is disabled', function (): void {
        config(['app.customer_portal_enabled' => false]);

        $this->get(url()->getCustomerPortalUrl('login'))->assertNotFound();
    });

    it('shows customer login on app subdomain', function (): void {
        $host = PanelDomain::customerHost();

        expect(url()->getCustomerPortalUrl('login'))->toBe("http://{$host}/customer/login");

        $this->get(url()->getCustomerPortalUrl('login'), ['Host' => $host])
            ->assertOk()
            ->assertSee('Customer Sign in')
            ->assertSee('Email address')
            ->assertSee('Password')
            ->assertSee('Remember me')
            ->assertSee('Sign In')
            ->assertDontSee('Login Staf Internal');
    });

    it('redirects legacy public domain customer urls to app subdomain', function (): void {
        $publicHost = PanelDomain::publicHost();
        $customerHost = PanelDomain::customerHost();

        if ($publicHost === $customerHost) {
            expect(true)->toBeTrue();

            return;
        }

        $this->get("http://{$publicHost}/customer/login", ['Host' => $publicHost])
            ->assertRedirect(url()->getCustomerPortalUrl('login'));
    });

    it('serves customer dashboard at panel root without tenant route conflict', function (): void {
        $host = PanelDomain::customerHost();

        $this->actingAs($this->portalUser, 'customer')
            ->get("http://{$host}/customer", ['Host' => $host])
            ->assertOk();
    });

    it('shows login page for staff session instead of redirecting to forbidden dashboard', function (): void {
        $host = PanelDomain::customerHost();

        $this->actingAs($this->admin)
            ->get(url()->getCustomerPortalUrl('login'), ['Host' => $host])
            ->assertOk()
            ->assertSee('Customer Sign in');
    });

    it('redirects staff user from customer dashboard to login', function (): void {
        $host = PanelDomain::customerHost();

        $this->actingAs($this->admin)
            ->get("http://{$host}/customer", ['Host' => $host])
            ->assertRedirect(url()->getCustomerPortalUrl('login'));
    });

    it('redirects customer login to dashboard not stale admin intended url', function (): void {
        $host = PanelDomain::customerHost();

        session(['url.intended' => url()->getAppUrl('login')]);

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');

        expect(app(\App\Http\Responses\LoginResponse::class)
            ->toResponse(request())
            ->getTargetUrl())
            ->toBe(CustomerDashboard::getUrl(panel: 'customer'));
    });

    it('keeps customer and admin sessions isolated on the same subdomain', function (): void {
        $host = PanelDomain::customerHost();

        $this->actingAs($this->portalUser, 'customer')
            ->get("http://{$host}/customer", ['Host' => $host])
            ->assertOk();

        $this->actingAs($this->admin, 'web')
            ->get(url()->getAppUrl('login'))
            ->assertRedirect();

        $this->actingAs($this->portalUser, 'customer')
            ->get("http://{$host}/customer", ['Host' => $host])
            ->assertOk();
    });

    it('detects customer session for livewire requests from customer panel', function (): void {
        $request = \Illuminate\Http\Request::create('/livewire-af864c3a/update', 'POST', server: [
            'HTTP_REFERER' => url()->getCustomerPortalUrl('login'),
        ]);

        expect(UseCustomerPanelSession::shouldUseCustomerSession($request))->toBeTrue();

        $adminRequest = \Illuminate\Http\Request::create('/livewire-af864c3a/update', 'POST', server: [
            'HTTP_REFERER' => url()->getAppUrl('login'),
        ]);

        expect(UseCustomerPanelSession::shouldUseCustomerSession($adminRequest))->toBeFalse();

        $adminRequestWithCustomerSnapshot = \Illuminate\Http\Request::create('/livewire-af864c3a/update', 'POST', [
            'components' => [[
                'snapshot' => json_encode(['memo' => ['path' => 'customer/login']]),
            ]],
        ], server: [
            'HTTP_REFERER' => url()->getAppUrl('login'),
        ]);

        expect(UseCustomerPanelSession::shouldUseCustomerSession($adminRequestWithCustomerSnapshot))->toBeFalse();
    });

    it('redirects admin login to app panel not stale customer intended url', function (): void {
        session(['url.intended' => url()->getCustomerPortalUrl()]);

        $this->actingAs($this->admin, 'web');
        Filament::setCurrentPanel('app');

        $redirectUrl = app(\App\Http\Responses\LoginResponse::class)
            ->toResponse(request())
            ->getTargetUrl();

        expect($redirectUrl)->not->toContain('/customer');
    });

    it('redirects admin login to app dashboard after customer login', function (): void {
        Filament::setCurrentPanel('customer');

        livewire(CustomerLogin::class)
            ->fillForm([
                'email' => $this->portalUser->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($this->portalUser, 'customer');

        Filament::setCurrentPanel('app');

        livewire(AppLogin::class)
            ->fillForm([
                'email' => $this->admin->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertRedirect(CompanyResource::getUrl('index', ['tenant' => $this->team->getKey()]));

        $this->assertAuthenticatedAs($this->admin, 'web');
    });

    it('authenticates customer via livewire after admin login page was opened', function (): void {
        $this->get(url()->getAppUrl('login'))->assertOk();

        Filament::setCurrentPanel('customer');

        livewire(CustomerLogin::class)
            ->fillForm([
                'email' => $this->portalUser->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($this->portalUser, 'customer');
    });
});

describe('Portal Invitation Security', function (): void {
    it('rejects invitation when email belongs to an existing user', function (): void {
        Mail::fake();

        $this->actingAs($this->admin);
        Filament::setTenant($this->team);
        Filament::setCurrentPanel('app');

        expect(fn () => app(InvitePortalUser::class)->execute(
            team: $this->team,
            buyer: $this->buyer,
            email: $this->portalUser->email, // already has a User record
            name: 'Portal Contact',
            invitedBy: $this->admin,
        ))->toThrow(\Illuminate\Validation\ValidationException::class);

        Mail::assertNothingSent();
    });

    it('does not create an invitation record when email belongs to existing user', function (): void {
        Mail::fake();

        $this->actingAs($this->admin);

        try {
            app(InvitePortalUser::class)->execute(
                team: $this->team,
                buyer: $this->buyer,
                email: $this->portalUser->email,
                name: 'Portal Contact',
                invitedBy: $this->admin,
            );
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        expect(PortalInvitation::query()->where('email', $this->portalUser->email)->exists())->toBeFalse();
    });
});

describe('Portal Invitation', function (): void {
    it('creates invitation and sends email', function (): void {
        Mail::fake();

        $this->actingAs($this->admin);
        Filament::setTenant($this->team);
        Filament::setCurrentPanel('app');

        $invitation = app(InvitePortalUser::class)->execute(
            team: $this->team,
            buyer: $this->buyer,
            email: 'new.portal@buyer.test',
            name: 'Portal Contact',
            invitedBy: $this->admin,
        );

        expect($invitation)->toBeInstanceOf(PortalInvitation::class)
            ->and($invitation->email)->toBe('new.portal@buyer.test');

        Mail::assertSent(\App\Mail\PortalUserInvitationMail::class);
    });

    it('accepts invitation and creates portal access', function (): void {
        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'accept@buyer.test',
            'name' => 'Accept User',
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        livewire(\App\Filament\Customer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token])
            ->fillForm([
                'name' => 'Accept User',
                'email' => 'accept@buyer.test',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->call('accept')
            ->assertHasNoFormErrors();

        expect(User::query()->where('email', 'accept@buyer.test')->exists())->toBeTrue()
            ->and(CompanyPortalUser::query()->where('company_id', $this->buyer->getKey())->whereHas('user', fn ($q) => $q->where('email', 'accept@buyer.test'))->exists())->toBeTrue()
            ->and($invitation->fresh()?->accepted_at)->not->toBeNull();
    });
});

describe('Customer Request Submission', function (): void {
    it('creates portal request with items', function (): void {
        $uom = UnitOfMeasure::factory()->for($this->team)->create();

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(PortalContext::class)->setCompany($this->buyer->getKey());

        $component = livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\CreateCustomerRequest::class);

        $itemKey = array_key_first($component->get('data.items') ?? []) ?? (string) \Illuminate\Support\Str::uuid();

        $component
            ->fillForm([
                'submission_method_choice' => RequestSubmissionMethod::MANUAL->value,
                'title' => 'Office Supplies',
                'request_type' => 'goods',
                'required_by' => now()->addWeek()->toDateString(),
                'items' => [
                    $itemKey => [
                        'description' => 'A4 Paper',
                        'quantity' => 10,
                        'unit_of_measure_id' => $uom->getKey(),
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = Request::query()->where('title', 'Office Supplies')->first();

        expect($request)->not->toBeNull()
            ->and($request->submission_method)->toBe(RequestSubmissionMethod::MANUAL)
            ->and($request->submitted_by_user_id)->toBe($this->portalUser->getKey())
            ->and($request->buyer_id)->toBe($this->buyer->getKey())
            ->and($request->items)->toHaveCount(1);
    });

    it('scopes customer request list to portal company only', function (): void {
        $otherBuyer = Company::factory()->buyer()->for($this->team)->create();

        Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'title' => 'Visible Request',
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
        ]);

        Request::factory()->for($this->team)->for($otherBuyer, 'buyer')->create([
            'title' => 'Hidden Request',
        ]);

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(PortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\ListCustomerRequests::class)
            ->assertCanSeeTableRecords(Request::query()->where('buyer_id', $this->buyer->getKey())->get())
            ->assertCanNotSeeTableRecords(Request::query()->where('buyer_id', $otherBuyer->getKey())->get());
    });

    it('does not expose internal notes on customer view', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'submitted_by_user_id' => $this->portalUser->getKey(),
            'internal_notes' => 'Secret supplier margin info',
            'stage' => RequestStage::DRAFT,
        ]);

        RequestItem::factory()->for($request)->create();

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(PortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\ViewCustomerRequest::class, [
            'record' => $request->getKey(),
        ])
            ->assertOk()
            ->assertDontSee('Secret supplier margin info');
    });
});

describe('Customer Portal Phase 2', function (): void {
    it('creates document-based portal request with attachments', function (): void {
        $pdfPath = 'requests/portal-attachments/test-rfq.pdf';
        $absolutePath = storage_path('app/'.$pdfPath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        file_put_contents($absolutePath, '%PDF-1.4 test');

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(PortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\CreateCustomerRequest::class)
            ->fillForm([
                'submission_method_choice' => RequestSubmissionMethod::DOCUMENT->value,
                'title' => 'RFQ via Dokumen',
                'request_type' => 'goods',
                'description' => 'Lihat lampiran RFQ',
                'attachment_files' => [$pdfPath],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = Request::query()->where('title', 'RFQ via Dokumen')->first();

        expect($request)->not->toBeNull()
            ->and($request->submission_method)->toBe(RequestSubmissionMethod::DOCUMENT)
            ->and($request->items)->toHaveCount(0)
            ->and($request->getMedia('attachments'))->toHaveCount(1);
    });

    it('allows customer to accept a sent buyer quote', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'submitted_by_user_id' => $this->portalUser->getKey(),
        ]);

        $quote = \App\Models\BuyerQuote::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->buyer, 'buyer')
            ->sent()
            ->withTotals(1000, 100, 1100)
            ->create();

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(PortalContext::class)->setCompany($this->buyer->getKey());

        expect($this->portalUser->can('respond', $quote))->toBeTrue();

        $quote->markAsAccepted();

        expect($quote->fresh()?->status)->toBe(\App\Enums\BuyerQuoteStatus::ACCEPTED);
    });

    it('denies customer access to draft buyer quotes', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create();

        $quote = \App\Models\BuyerQuote::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->buyer, 'buyer')
            ->draft()
            ->create();

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');

        expect($this->portalUser->can('view', $quote))->toBeFalse();
    });

    it('notifies portal users when request stage changes', function (): void {
        \Illuminate\Support\Facades\Notification::fake();

        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::DRAFT,
        ]);

        $request->update(['stage' => RequestStage::AWAITING_SUPPLIER_RESPONSE]);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->portalUser,
            \App\Notifications\PortalRequestStageChangedNotification::class,
        );
    });
});

describe('Customer Portal Phase 3', function (): void {
    it('allows customer to view outbound shipments only', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
        ]);

        $outbound = Shipment::factory()
            ->for($this->team)
            ->for($request)
            ->outbound()
            ->inTransit()
            ->create();

        $inbound = Shipment::factory()
            ->for($this->team)
            ->for($request)
            ->inbound()
            ->create();

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(PortalContext::class)->setCompany($this->buyer->getKey());

        expect($this->portalUser->can('view', $outbound))->toBeTrue()
            ->and($this->portalUser->can('view', $inbound))->toBeFalse();
    });

    it('loads customer dashboard with scoped widgets', function (): void {
        Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION,
        ]);

        $otherBuyer = Company::factory()->buyer()->for($this->team)->create();

        Request::factory()->for($this->team)->for($otherBuyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(PortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Pages\CustomerDashboard::class)->assertOk();

        livewire(\App\Filament\Customer\Widgets\PortalRequestsOverviewWidget::class)
            ->assertSee('Awaiting Confirmation');
    });

    it('resolves portal team from company context', function (): void {
        $this->actingAs($this->portalUser, 'customer');
        app(PortalContext::class)->setCompany($this->buyer->getKey());

        expect(app(PortalContext::class)->team()->getKey())->toBe($this->team->getKey())
            ->and(app(PortalContext::class)->company()->getKey())->toBe($this->buyer->getKey());
    });

    it('switches portal company context', function (): void {
        $secondBuyer = Company::factory()->buyer()->for($this->team)->create([
            'name' => 'Second Buyer Co',
        ]);

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $secondBuyer->getKey(),
            'user_id' => $this->portalUser->getKey(),
            'invited_by' => $this->admin->getKey(),
            'is_active' => true,
        ]);

        $this->actingAs($this->portalUser, 'customer');

        app(PortalContext::class)->setCompany($this->buyer->getKey());
        expect(app(PortalContext::class)->companyId())->toBe($this->buyer->getKey());

        app(PortalContext::class)->setCompany($secondBuyer->getKey());
        expect(app(PortalContext::class)->companyId())->toBe($secondBuyer->getKey());
    });

    it('lists outbound shipments in customer relation manager', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
        ]);

        $shipment = Shipment::factory()
            ->for($this->team)
            ->for($request)
            ->outbound()
            ->inTransit()
            ->create([
                'tracking_number' => 'TRK-VISIBLE-123',
            ]);

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(PortalContext::class)->setCompany($this->buyer->getKey());

        livewire(
            \App\Filament\Customer\Resources\CustomerRequestResource\RelationManagers\ShipmentsRelationManager::class,
            [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Customer\Resources\CustomerRequestResource\Pages\ViewCustomerRequest::class,
            ],
        )
            ->assertOk()
            ->assertCanSeeTableRecords([$shipment]);
    });
});
