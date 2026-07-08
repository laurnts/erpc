<?php

declare(strict_types=1);

use App\Actions\Portal\InvitePortalUser;
use App\Enums\PortalType;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Buyer\Pages\Auth\BuyerLogin;
use App\Filament\Buyer\Resources\BuyerRequestResource;
use App\Filament\Buyer\Resources\BuyerRequestResource\Schemas\BuyerRequestForm;
use App\Filament\Pages\Auth\Login as AppLogin;
use App\Filament\Resources\RequestResource;
use App\Http\Middleware\UsePanelSession;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Shipment;
use App\Models\Team;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Portal\BuyerPortalContext;
use App\Support\Media\DocumentPathGenerator;
use App\Support\PanelDomain;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config(['app.buyer_portal_enabled' => true]);

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

describe('Buyer Portal Access', function (): void {
    it('allows portal user to access buyer panel', function (): void {
        Filament::setCurrentPanel('buyer');

        expect($this->portalUser->canAccessPanel(Filament::getPanel('buyer')))->toBeTrue()
            ->and($this->portalUser->canAccessPanel(Filament::getPanel('app')))->toBeFalse();
    });

    it('denies internal user without portal access from buyer panel', function (): void {
        $internalUser = User::factory()->withPersonalTeam()->create();

        Filament::setCurrentPanel('buyer');

        expect($internalUser->canAccessPanel(Filament::getPanel('buyer')))->toBeFalse()
            ->and($internalUser->canAccessPanel(Filament::getPanel('app')))->toBeTrue();
    });

    it('protects buyer routes when portal is disabled', function (): void {
        config(['app.buyer_portal_enabled' => false]);

        $this->get(url()->getBuyerPortalUrl('login'))->assertNotFound();
    });

    it('shows buyer login on app subdomain', function (): void {
        $host = PanelDomain::buyerHost();

        expect(url()->getBuyerPortalUrl('login'))->toBe("http://{$host}/buyer/login");

        $this->get(url()->getBuyerPortalUrl('login'), ['Host' => $host])
            ->assertOk()
            ->assertSee('Buyer Sign in')
            ->assertSee('Email address')
            ->assertSee('Password')
            ->assertSee('Remember me')
            ->assertSee('Sign In')
            ->assertDontSee('Login Staf Internal');
    });

    it('redirects legacy public domain buyer urls to app subdomain', function (): void {
        $publicHost = PanelDomain::publicHost();
        $buyerHost = PanelDomain::buyerHost();

        if ($publicHost === $buyerHost) {
            expect(true)->toBeTrue();

            return;
        }

        $this->get("http://{$publicHost}/buyer/login", ['Host' => $publicHost])
            ->assertRedirect(url()->getBuyerPortalUrl('login'));
    });

    it('redirects panel root to the requests list without tenant route conflict', function (): void {
        $host = PanelDomain::buyerHost();

        $this->actingAs($this->portalUser, 'buyer')
            ->get("http://{$host}/buyer", ['Host' => $host])
            ->assertRedirect(BuyerRequestResource::getUrl('index', panel: 'buyer'));
    });

    it('shows login page for staff session instead of redirecting to forbidden dashboard', function (): void {
        $host = PanelDomain::buyerHost();

        $this->actingAs($this->admin)
            ->get(url()->getBuyerPortalUrl('login'), ['Host' => $host])
            ->assertOk()
            ->assertSee('Buyer Sign in');
    });

    it('redirects staff user from buyer dashboard to login', function (): void {
        $host = PanelDomain::buyerHost();

        $this->actingAs($this->admin)
            ->get("http://{$host}/buyer", ['Host' => $host])
            ->assertRedirect(url()->getBuyerPortalUrl('login'));
    });

    it('redirects buyer login to requests list not stale admin intended url', function (): void {
        $host = PanelDomain::buyerHost();

        session(['url.intended' => url()->getAppUrl('login')]);

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');

        expect(app(\App\Http\Responses\LoginResponse::class)
            ->toResponse(request())
            ->getTargetUrl())
            ->toBe(BuyerRequestResource::getUrl('index', panel: 'buyer'));
    });

    it('shows Requests as the only item in the top navigation', function (): void {
        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        $panel = Filament::getPanel('buyer');

        $labels = collect($panel->getNavigation())
            ->flatMap(fn ($group) => collect($group->getItems())->map(fn ($item): string => (string) $item->getLabel()))
            ->values();

        expect($panel->hasTopNavigation())->toBeTrue()
            ->and($labels->all())->toBe(['Requests']);
    });

    it('keeps buyer and admin sessions isolated on the same subdomain', function (): void {
        $host = PanelDomain::buyerHost();

        $this->actingAs($this->portalUser, 'buyer')
            ->get("http://{$host}/buyer", ['Host' => $host])
            ->assertRedirect(BuyerRequestResource::getUrl('index', panel: 'buyer'));

        $this->actingAs($this->admin, 'web')
            ->get(url()->getAppUrl('login'))
            ->assertRedirect();

        $this->actingAs($this->portalUser, 'buyer')
            ->get("http://{$host}/buyer", ['Host' => $host])
            ->assertRedirect(BuyerRequestResource::getUrl('index', panel: 'buyer'));
    });

    it('detects buyer session for livewire requests from buyer panel', function (): void {
        $request = \Illuminate\Http\Request::create('/livewire-af864c3a/update', 'POST', server: [
            'HTTP_REFERER' => url()->getBuyerPortalUrl('login'),
        ]);

        expect(UsePanelSession::cookieForRequest($request))->toBe((string) config('app.buyer_session_cookie'));

        $adminRequest = \Illuminate\Http\Request::create('/livewire-af864c3a/update', 'POST', server: [
            'HTTP_REFERER' => url()->getAppUrl('login'),
        ]);

        expect(UsePanelSession::cookieForRequest($adminRequest))->toBeNull();

        $adminRequestWithBuyerSnapshot = \Illuminate\Http\Request::create('/livewire-af864c3a/update', 'POST', [
            'components' => [[
                'snapshot' => json_encode(['memo' => ['path' => 'buyer/login']]),
            ]],
        ], server: [
            'HTTP_REFERER' => url()->getAppUrl('login'),
        ]);

        expect(UsePanelSession::cookieForRequest($adminRequestWithBuyerSnapshot))->toBeNull();
    });

    it('redirects admin login to app panel not stale buyer intended url', function (): void {
        session(['url.intended' => url()->getBuyerPortalUrl()]);

        $this->actingAs($this->admin, 'web');
        Filament::setCurrentPanel('app');

        $redirectUrl = app(\App\Http\Responses\LoginResponse::class)
            ->toResponse(request())
            ->getTargetUrl();

        expect($redirectUrl)->not->toContain('/buyer');
    });

    it('redirects admin login to app dashboard after buyer login', function (): void {
        Filament::setCurrentPanel('buyer');

        livewire(BuyerLogin::class)
            ->fillForm([
                'email' => $this->portalUser->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($this->portalUser, 'buyer');

        Filament::setCurrentPanel('app');

        livewire(AppLogin::class)
            ->fillForm([
                'email' => $this->admin->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertRedirect(RequestResource::getUrl('index', ['tenant' => $this->team->getKey()]));

        $this->assertAuthenticatedAs($this->admin, 'web');
    });

    it('authenticates buyer via livewire after admin login page was opened', function (): void {
        $this->get(url()->getAppUrl('login'))->assertOk();

        Filament::setCurrentPanel('buyer');

        livewire(BuyerLogin::class)
            ->fillForm([
                'email' => $this->portalUser->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($this->portalUser, 'buyer');
    });
});

describe('Portal Invitation Security', function (): void {
    it('rejects inviting a user who already has access to the company', function (): void {
        Mail::fake();

        $this->actingAs($this->admin);
        Filament::setTenant($this->team);
        Filament::setCurrentPanel('app');

        expect(fn () => app(InvitePortalUser::class)->execute(
            team: $this->team,
            company: $this->buyer,
            portal: PortalType::Buyer,
            email: $this->portalUser->email, // already an active member of this buyer
            name: 'Portal Contact',
            invitedBy: $this->admin,
        ))->toThrow(\Illuminate\Validation\ValidationException::class);

        Mail::assertNothingSent();
        expect(PortalInvitation::query()->where('email', $this->portalUser->email)->exists())->toBeFalse();
    });

    it('invites an existing user to a company they do not yet belong to', function (): void {
        Mail::fake();

        $existing = User::factory()->create(['email' => 'existing.contact@buyer.test']);

        $this->actingAs($this->admin);
        Filament::setTenant($this->team);
        Filament::setCurrentPanel('app');

        $invitation = app(InvitePortalUser::class)->execute(
            team: $this->team,
            company: $this->buyer,
            portal: PortalType::Buyer,
            email: $existing->email,
            name: 'Existing Contact',
            invitedBy: $this->admin,
        );

        expect($invitation->email)->toBe('existing.contact@buyer.test');
        Mail::assertSent(\App\Mail\PortalUserInvitationMail::class);
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
            company: $this->buyer,
            portal: PortalType::Buyer,
            email: 'new.portal@buyer.test',
            name: 'Portal Contact',
            invitedBy: $this->admin,
        );

        expect($invitation)->toBeInstanceOf(PortalInvitation::class)
            ->and($invitation->email)->toBe('new.portal@buyer.test');

        Mail::assertSent(\App\Mail\PortalUserInvitationMail::class);
    });

    it('rejects invitations for companies without the buyer role', function (): void {
        Mail::fake();

        $supplierOnly = Company::factory()->supplier()->for($this->team)->create();

        expect(fn () => app(InvitePortalUser::class)->execute(
            team: $this->team,
            company: $supplierOnly,
            portal: PortalType::Buyer,
            email: 'contact@supplier.test',
            name: 'Supplier Contact',
            invitedBy: $this->admin,
        ))->toThrow(\Illuminate\Validation\ValidationException::class);

        Mail::assertNothingSent();
    });

    it('does not resolve supplier-typed invitation tokens on the buyer accept page', function (): void {
        $supplier = Company::factory()->supplier()->for($this->team)->create();

        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $supplier->getKey(),
            'email' => 'supplier.invite@supplier.test',
            'name' => 'Supplier Invitee',
            'portal' => PortalType::Supplier,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        expect(fn () => livewire(\App\Filament\Buyer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token]))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
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

        livewire(\App\Filament\Buyer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token])
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

    it('allows guests to open the invitation accept page over http', function (): void {
        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'guest.invite@buyer.test',
            'name' => 'Guest Invitee',
            'portal' => PortalType::Buyer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        $host = PanelDomain::buyerHost();

        $response = $this->get(url()->getBuyerPortalUrl('invitation/'.$invitation->token), ['Host' => $host]);

        $response->assertOk()
            ->assertSee('Create Account')
            ->assertSee($invitation->email);
    });

    it('allows unverified authenticated users to open the invitation accept page over http', function (): void {
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'other.invite@buyer.test',
            'name' => 'Other Invitee',
            'portal' => PortalType::Buyer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        $host = PanelDomain::buyerHost();

        $response = $this->actingAs($unverifiedUser, 'buyer')
            ->get(url()->getBuyerPortalUrl('invitation/'.$invitation->token), ['Host' => $host]);

        $response->assertOk()
            ->assertSee('Create Account')
            ->assertSee($invitation->email);
    });

    it('lets an existing user accept an invitation to an additional company', function (): void {
        $existing = User::factory()->create([
            'email' => 'multi@buyer.test',
            'password' => \Illuminate\Support\Facades\Hash::make('original-password'),
        ]);

        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'multi@buyer.test',
            'name' => 'Multi Company',
            'portal' => PortalType::Buyer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'user_id' => null,
            'portal' => PortalType::Buyer,
            'invited_by' => $this->admin->getKey(),
            'is_active' => false,
            'invited_name' => 'Multi Company',
            'invited_email' => 'multi@buyer.test',
        ]);

        $this->actingAs($existing, 'buyer');

        livewire(\App\Filament\Buyer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token])
            ->call('accept')
            ->assertHasNoErrors();

        expect(User::query()->where('email', 'multi@buyer.test')->count())->toBe(1)
            ->and(\Illuminate\Support\Facades\Hash::check('original-password', (string) $existing->fresh()?->password))->toBeTrue()
            ->and(CompanyPortalUser::query()
                ->where('company_id', $this->buyer->getKey())
                ->where('user_id', $existing->getKey())
                ->where('is_active', true)
                ->exists())->toBeTrue()
            ->and($invitation->fresh()?->accepted_at)->not->toBeNull();
    });

    it('asks an unauthenticated existing-account invitee to sign in before accepting', function (): void {
        User::factory()->create(['email' => 'guest.multi@buyer.test']);

        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'guest.multi@buyer.test',
            'name' => 'Guest Multi',
            'portal' => PortalType::Buyer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $host = PanelDomain::buyerHost();

        $this->get(url()->getBuyerPortalUrl('invitation/'.$invitation->token), ['Host' => $host])
            ->assertOk()
            ->assertSee('Sign in')
            ->assertDontSee('Create Account');
    });

    it('does not grant access when an unauthenticated user submits an existing-account invitation', function (): void {
        User::factory()->create(['email' => 'guest.multi2@buyer.test']);

        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'guest.multi2@buyer.test',
            'name' => 'Guest Multi 2',
            'portal' => PortalType::Buyer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        livewire(\App\Filament\Buyer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token])
            ->call('accept')
            ->assertRedirect(filament()->getPanel('buyer')->getLoginUrl());

        expect($invitation->fresh()?->accepted_at)->toBeNull();
    });

    it('does not resolve an expired invitation on the accept page', function (): void {
        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'expired@buyer.test',
            'name' => 'Expired Invitee',
            'portal' => PortalType::Buyer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
            'expires_at' => now()->subDay(),
        ]);

        expect(fn () => livewire(\App\Filament\Buyer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token]))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('Buyer Request Submission', function (): void {
    it('creates portal request with items', function (): void {
        $uom = UnitOfMeasure::factory()->for($this->team)->create();

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        $component = livewire(\App\Filament\Buyer\Resources\BuyerRequestResource\Pages\CreateBuyerRequest::class);

        $itemKey = array_key_first($component->get('data.items') ?? []) ?? (string) \Illuminate\Support\Str::uuid();

        $component
            ->fillForm([
                'submission_method_choice' => RequestSubmissionMethod::MANUAL->value,
                'title' => 'Office Supplies',
                'required_by' => now()->addWeek()->toDateString(),
                'items' => [
                    $itemKey => [
                        'description' => 'A4 Paper',
                        'item_type' => 'goods',
                        'quantity' => 10,
                        'unit_of_measure_id' => $uom->getKey(),
                    ],
                    (string) \Illuminate\Support\Str::uuid() => [
                        'description' => 'Printer installation',
                        'item_type' => 'services',
                        'quantity' => 1,
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
            ->and($request->items)->toHaveCount(2)
            ->and($request->hasGoodsItems())->toBeTrue()
            ->and($request->hasServiceItems())->toBeTrue()
            ->and($request->item_type_summary)->toBe('Mixed');
    });

    it('notifies staff and buyer portal users when a request is submitted', function (): void {
        \Illuminate\Support\Facades\Notification::fake();

        $uom = UnitOfMeasure::factory()->for($this->team)->create();

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        $component = livewire(\App\Filament\Buyer\Resources\BuyerRequestResource\Pages\CreateBuyerRequest::class);

        $itemKey = array_key_first($component->get('data.items') ?? []) ?? (string) \Illuminate\Support\Str::uuid();

        $component
            ->fillForm([
                'submission_method_choice' => RequestSubmissionMethod::MANUAL->value,
                'title' => 'Notification Test Request',
                'required_by' => now()->addWeek()->toDateString(),
                'items' => [
                    $itemKey => [
                        'description' => 'Test item',
                        'item_type' => 'goods',
                        'quantity' => 1,
                        'unit_of_measure_id' => $uom->getKey(),
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->admin,
            \App\Notifications\PortalRequestSubmittedNotification::class,
        );

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->portalUser,
            \App\Notifications\PortalRequestReceivedConfirmationNotification::class,
        );
    });

    it('preserves item types when a buyer edits a draft request', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'submitted_by_user_id' => $this->portalUser->getKey(),
            'stage' => RequestStage::DRAFT,
        ]);
        $uom = UnitOfMeasure::factory()->for($this->team)->create();
        RequestItem::factory()->for($request)->create([
            'description' => 'Machine maintenance',
            'item_type' => \App\Enums\ItemType::SERVICE,
            'unit_of_measure_id' => $uom->getKey(),
        ]);

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Buyer\Resources\BuyerRequestResource\Pages\EditBuyerRequest::class, [
            'record' => $request->getKey(),
        ])
            ->fillForm(['submission_method_choice' => RequestSubmissionMethod::MANUAL->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $item = $request->refresh()->items()->first();

        expect($item->description)->toBe('Machine maintenance')
            ->and($item->item_type)->toBe(\App\Enums\ItemType::SERVICE);
    });

    it('scopes buyer request list to portal company only', function (): void {
        $otherBuyer = Company::factory()->buyer()->for($this->team)->create();

        Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'title' => 'Visible Request',
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
        ]);

        Request::factory()->for($this->team)->for($otherBuyer, 'buyer')->create([
            'title' => 'Hidden Request',
        ]);

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ListBuyerRequests::class)
            ->assertCanSeeTableRecords(Request::query()->where('buyer_id', $this->buyer->getKey())->get())
            ->assertCanNotSeeTableRecords(Request::query()->where('buyer_id', $otherBuyer->getKey())->get());
    });

    it('does not expose internal notes on buyer view', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'submitted_by_user_id' => $this->portalUser->getKey(),
            'internal_notes' => 'Secret supplier margin info',
            'stage' => RequestStage::DRAFT,
        ]);

        RequestItem::factory()->for($request)->create();

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest::class, [
            'record' => $request->getKey(),
        ])
            ->assertOk()
            ->assertDontSee('Secret supplier margin info');
    });
});

describe('Buyer Portal Phase 2', function (): void {
    it('creates document-based portal request with attachments', function (): void {
        $pdfPath = BuyerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY.'/test-request.pdf';
        $absolutePath = storage_path('app/'.$pdfPath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        file_put_contents($absolutePath, '%PDF-1.4 test');

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Buyer\Resources\BuyerRequestResource\Pages\CreateBuyerRequest::class)
            ->fillForm([
                'submission_method_choice' => RequestSubmissionMethod::DOCUMENT->value,
                'title' => 'Request via Dokumen',
                'description' => 'Lihat lampiran request',
                'attachment_files' => [$pdfPath],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = Request::query()->where('title', 'Request via Dokumen')->first();

        expect($request)->not->toBeNull()
            ->and($request->submission_method)->toBe(RequestSubmissionMethod::DOCUMENT)
            ->and($request->items)->toHaveCount(0)
            ->and($request->getMedia('attachments'))->toHaveCount(1);

        $media = $request->getFirstMedia('attachments');

        expect($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
            ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$request->team_id.'/');
    });

    it('allows buyer to accept a sent buyer quote', function (): void {
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

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        expect($this->portalUser->can('respond', $quote))->toBeTrue();

        $quote->markAsAccepted();

        expect($quote->fresh()?->status)->toBe(\App\Enums\BuyerQuoteStatus::ACCEPTED);
    });

    it('denies buyer access to draft buyer quotes', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create();

        $quote = \App\Models\BuyerQuote::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->buyer, 'buyer')
            ->draft()
            ->create();

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');

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

describe('Buyer Portal Phase 3', function (): void {
    it('allows buyer to view outbound shipments only', function (): void {
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

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        expect($this->portalUser->can('view', $outbound))->toBeTrue()
            ->and($this->portalUser->can('view', $inbound))->toBeFalse();
    });

    it('resolves portal team from company context', function (): void {
        $this->actingAs($this->portalUser, 'buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        expect(app(BuyerPortalContext::class)->team()->getKey())->toBe($this->team->getKey())
            ->and(app(BuyerPortalContext::class)->company()->getKey())->toBe($this->buyer->getKey());
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

        $this->actingAs($this->portalUser, 'buyer');

        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());
        expect(app(BuyerPortalContext::class)->companyId())->toBe($this->buyer->getKey());

        app(BuyerPortalContext::class)->setCompany($secondBuyer->getKey());
        expect(app(BuyerPortalContext::class)->companyId())->toBe($secondBuyer->getKey());
    });

    it('lists outbound shipments in buyer relation manager', function (): void {
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

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(
            \App\Filament\Buyer\Resources\BuyerRequestResource\RelationManagers\ShipmentsRelationManager::class,
            [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest::class,
            ],
        )
            ->assertOk()
            ->assertCanSeeTableRecords([$shipment]);
    });
});

describe('Portal-Typed Membership', function (): void {
    it('denies buyer panel access for a supplier-typed membership at a dual-role company', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();
        $supplierContact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $supplierContact->getKey(),
            'portal' => \App\Enums\PortalType::Supplier,
            'is_active' => true,
        ]);

        expect($supplierContact->canAccessPanel(Filament::getPanel('buyer')))->toBeFalse()
            ->and($supplierContact->hasActiveBuyerPortalAccess())->toBeFalse();
    });

    it('denies buyer panel access when the membership company is supplier-only', function (): void {
        $supplierCompany = Company::factory()->supplier()->for($this->team)->create();
        $contact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $supplierCompany->getKey(),
            'user_id' => $contact->getKey(),
            'portal' => \App\Enums\PortalType::Buyer,
            'is_active' => true,
        ]);

        expect($contact->canAccessPanel(Filament::getPanel('buyer')))->toBeFalse()
            ->and($contact->hasActiveBuyerPortalAccess())->toBeFalse();
    });

    it('grants buyer capability only for the buyer-typed membership at a dual-role company', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();
        $contact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $contact->getKey(),
            'portal' => \App\Enums\PortalType::Buyer,
            'is_active' => true,
        ]);

        expect($contact->hasActiveBuyerPortalAccess())->toBeTrue()
            ->and($contact->activeBuyerPortalCompanyIds())->toBe([$dualRole->getKey()]);
    });

    it('allows one person to hold buyer and supplier memberships at the same company', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();
        $contact = User::factory()->create();

        foreach ([\App\Enums\PortalType::Buyer, \App\Enums\PortalType::Supplier] as $portal) {
            CompanyPortalUser::query()->create([
                'team_id' => $this->team->getKey(),
                'company_id' => $dualRole->getKey(),
                'user_id' => $contact->getKey(),
                'portal' => $portal,
                'is_active' => true,
            ]);
        }

        expect(CompanyPortalUser::query()->where('user_id', $contact->getKey())->count())->toBe(2)
            ->and($contact->activeBuyerPortalCompanyIds())->toBe([$dualRole->getKey()]);
    });

    it('excludes supplier-typed memberships from the buyer company switcher', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $this->portalUser->getKey(),
            'portal' => \App\Enums\PortalType::Supplier,
            'is_active' => true,
        ]);

        $memberships = app(BuyerPortalContext::class)->activeMemberships($this->portalUser);

        expect($memberships->pluck('company_id')->all())->toBe([$this->buyer->getKey()]);
    });

    it('copies the invitation portal type onto the membership on accept', function (): void {
        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'typed@buyer.test',
            'name' => 'Typed User',
            'portal' => \App\Enums\PortalType::Buyer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        livewire(\App\Filament\Buyer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token])
            ->fillForm([
                'name' => 'Typed User',
                'email' => 'typed@buyer.test',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->call('accept')
            ->assertHasNoFormErrors();

        $membership = CompanyPortalUser::query()
            ->where('company_id', $this->buyer->getKey())
            ->whereHas('user', fn ($q) => $q->where('email', 'typed@buyer.test'))
            ->firstOrFail();

        expect($membership->portal)->toBe(\App\Enums\PortalType::Buyer);
    });
});

it('does not notify supplier-typed members of a dual-role company via buyer portal fan-out', function (): void {
    $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();

    $buyerContact = User::factory()->create();
    $supplierContact = User::factory()->create();

    foreach ([
        [$buyerContact, \App\Enums\PortalType::Buyer],
        [$supplierContact, \App\Enums\PortalType::Supplier],
    ] as [$contact, $portal]) {
        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $contact->getKey(),
            'portal' => $portal,
            'is_active' => true,
        ]);
    }

    \Illuminate\Support\Facades\Notification::fake();

    $notification = new class extends \Illuminate\Notifications\Notification
    {
        /**
         * @return list<string>
         */
        public function via(object $notifiable): array
        {
            return ['database'];
        }

        /**
         * @return array<string, mixed>
         */
        public function toArray(object $notifiable): array
        {
            return [];
        }
    };

    app(\App\Actions\BuyerPortal\NotifyPortalUsers::class)->forCompany($dualRole->getKey(), $notification);

    \Illuminate\Support\Facades\Notification::assertSentTo($buyerContact, $notification::class);
    \Illuminate\Support\Facades\Notification::assertNotSentTo($supplierContact, $notification::class);
});

it('rejects path traversal in uploaded file attachment and accepts files inside the upload directory', function (): void {
    $request = Request::factory()->for($this->team)->create([
        'buyer_id' => $this->buyer->getKey(),
    ]);

    $uploadDir = storage_path('app/'.BuyerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY);
    \Illuminate\Support\Facades\File::ensureDirectoryExists($uploadDir);
    $legit = $uploadDir.'/legit-'.uniqid().'.pdf';
    file_put_contents($legit, '%PDF-1.4 test');

    app(\App\Actions\Media\AttachUploadedFiles::class)->execute(
        $request,
        [
            '../../.env',
            BuyerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY.'/../../../.env',
            BuyerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY.'/'.basename($legit),
        ],
        'attachments',
        BuyerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY,
    );

    $media = $request->getMedia('attachments');

    expect($media)->toHaveCount(1)
        ->and($media->first()?->file_name)->toBe(basename($legit));

    @unlink($legit);
});

describe('Buyer Portal Phase 4', function (): void {
    it('renders the staff-style header on the request detail page', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::SHIPPED,
        ]);

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(
            \App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest::class,
            ['record' => $request->getRouteKey()],
        )
            ->assertOk()
            ->assertSee($request->request_number)
            ->assertSee('Required By')
            ->assertSee('Type');
    });

    it('renders request progress timeline on request detail', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::SHIPPED,
        ]);

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(
            \App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest::class,
            ['record' => $request->getRouteKey()],
        )
            ->assertOk()
            ->assertSee('Request Progress')
            ->assertSee('In transit');
    });

    it('shows awaiting confirmation for sent quotes even when request stage is stale', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::PREPARING_BUYER_QUOTE,
        ]);

        \App\Models\BuyerQuote::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->buyer, 'buyer')
            ->sent()
            ->withTotals(15894, 0, 15894)
            ->create([
                'quote_number' => 'BQ-2026-0067',
            ]);

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(
            \App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest::class,
            ['record' => $request->getRouteKey()],
        )
            ->assertOk()
            ->assertSee('Awaiting Your Confirmation')
            ->assertSee('Quote awaiting your confirmation')
            ->assertSee('BQ-2026-0067 · v1');

        livewire(
            \App\Filament\Buyer\Resources\BuyerRequestResource\RelationManagers\BuyerQuotesRelationManager::class,
            [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest::class,
            ],
        )
            ->assertOk()
            ->assertSee('BQ-2026-0067')
            ->assertSee('Actions')
            ->assertActionVisible(\Filament\Actions\Testing\TestAction::make('downloadPdf')->table($request->buyerQuotes()->first()));
    });

    it('shows accept reject and upload po actions for sent buyer quotes', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION,
        ]);

        $quote = \App\Models\BuyerQuote::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->buyer, 'buyer')
            ->sent()
            ->withTotals(15894, 0, 15894)
            ->create();

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        // Simulate portal session where the default web guard has no user.
        auth()->shouldUse('web');

        livewire(\App\Livewire\BuyerPendingQuoteActions::class, ['request' => $request])
            ->assertOk()
            ->assertSee($quote->quote_number)
            ->assertSee('Accept')
            ->assertSee('Reject')
            ->assertDontSee('Upload PO');
    });

    it('opens upload po modal after buyer confirms quote acceptance', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION,
        ]);

        $quote = \App\Models\BuyerQuote::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->buyer, 'buyer')
            ->sent()
            ->withTotals(15894, 0, 15894)
            ->create();

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Livewire\BuyerPendingQuoteActions::class, ['request' => $request])
            ->callAction(\Filament\Actions\Testing\TestAction::make('accept')->arguments(['quote' => $quote->getKey()]))
            ->assertActionMounted('uploadPo');
    });

    it('does not send duplicate stage notification when quote send advances request stage', function (): void {
        \Illuminate\Support\Facades\Notification::fake();

        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::PREPARING_BUYER_QUOTE,
        ]);

        $quote = \App\Models\BuyerQuote::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->buyer, 'buyer')
            ->draft()
            ->withTotals(1000, 100, 1100)
            ->create();

        $quote->markAsSent();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->portalUser,
            \App\Notifications\PortalBuyerQuoteSentNotification::class,
        );

        \Illuminate\Support\Facades\Notification::assertNotSentTo(
            $this->portalUser,
            \App\Notifications\PortalRequestStageChangedNotification::class,
        );

        expect($request->fresh()->stage)->toBe(RequestStage::AWAITING_BUYER_CONFIRMATION);
    });

    it('filters buyer requests by status group', function (): void {
        $active = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::AWAITING_SUPPLIER_RESPONSE,
            'title' => 'Active Request Alpha',
        ]);

        $completed = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::COMPLETED,
            'title' => 'Completed Request Beta',
        ]);

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ListBuyerRequests::class)
            ->filterTable('status_group', 'completed')
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$active]);
    });

    it('allows buyer to view sent invoices but not drafts', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
        ]);

        $sentInvoice = \App\Models\BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->sent()
            ->create([
                'total' => '1100.0000',
            ]);

        $draftInvoice = \App\Models\BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->draft()
            ->create();

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

        expect($this->portalUser->can('view', $sentInvoice))->toBeTrue()
            ->and($this->portalUser->can('view', $draftInvoice))->toBeFalse();

        livewire(
            \App\Filament\Buyer\Resources\BuyerRequestResource\RelationManagers\InvoicesRelationManager::class,
            [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest::class,
            ],
        )
            ->assertOk()
            ->assertCanSeeTableRecords([$sentInvoice])
            ->assertCanNotSeeTableRecords([$draftInvoice]);
    });
});
