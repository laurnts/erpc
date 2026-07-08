<?php

declare(strict_types=1);

use App\Actions\Portal\InvitePortalUser;
use App\Enums\PortalType;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Customer\Pages\Auth\CustomerLogin;
use App\Filament\Customer\Pages\CustomerDashboard;
use App\Filament\Customer\Resources\CustomerRequestResource\Schemas\CustomerRequestForm;
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
use App\Services\Portal\CustomerPortalContext;
use App\Support\Media\DocumentPathGenerator;
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

        expect(url()->getCustomerPortalUrl('login'))->toBe("http://{$host}/buyer/login");

        $this->get(url()->getCustomerPortalUrl('login'), ['Host' => $host])
            ->assertOk()
            ->assertSee('Buyer Sign in')
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

        $this->get("http://{$publicHost}/buyer/login", ['Host' => $publicHost])
            ->assertRedirect(url()->getCustomerPortalUrl('login'));
    });

    it('serves customer dashboard at panel root without tenant route conflict', function (): void {
        $host = PanelDomain::customerHost();

        $this->actingAs($this->portalUser, 'customer')
            ->get("http://{$host}/buyer", ['Host' => $host])
            ->assertOk();
    });

    it('shows login page for staff session instead of redirecting to forbidden dashboard', function (): void {
        $host = PanelDomain::customerHost();

        $this->actingAs($this->admin)
            ->get(url()->getCustomerPortalUrl('login'), ['Host' => $host])
            ->assertOk()
            ->assertSee('Buyer Sign in');
    });

    it('redirects staff user from customer dashboard to login', function (): void {
        $host = PanelDomain::customerHost();

        $this->actingAs($this->admin)
            ->get("http://{$host}/buyer", ['Host' => $host])
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
            ->get("http://{$host}/buyer", ['Host' => $host])
            ->assertOk();

        $this->actingAs($this->admin, 'web')
            ->get(url()->getAppUrl('login'))
            ->assertRedirect();

        $this->actingAs($this->portalUser, 'customer')
            ->get("http://{$host}/buyer", ['Host' => $host])
            ->assertOk();
    });

    it('detects customer session for livewire requests from customer panel', function (): void {
        $request = \Illuminate\Http\Request::create('/livewire-af864c3a/update', 'POST', server: [
            'HTTP_REFERER' => url()->getCustomerPortalUrl('login'),
        ]);

        expect(UsePanelSession::cookieForRequest($request))->toBe((string) config('app.customer_session_cookie'));

        $adminRequest = \Illuminate\Http\Request::create('/livewire-af864c3a/update', 'POST', server: [
            'HTTP_REFERER' => url()->getAppUrl('login'),
        ]);

        expect(UsePanelSession::cookieForRequest($adminRequest))->toBeNull();

        $adminRequestWithCustomerSnapshot = \Illuminate\Http\Request::create('/livewire-af864c3a/update', 'POST', [
            'components' => [[
                'snapshot' => json_encode(['memo' => ['path' => 'buyer/login']]),
            ]],
        ], server: [
            'HTTP_REFERER' => url()->getAppUrl('login'),
        ]);

        expect(UsePanelSession::cookieForRequest($adminRequestWithCustomerSnapshot))->toBeNull();
    });

    it('redirects admin login to app panel not stale customer intended url', function (): void {
        session(['url.intended' => url()->getCustomerPortalUrl()]);

        $this->actingAs($this->admin, 'web');
        Filament::setCurrentPanel('app');

        $redirectUrl = app(\App\Http\Responses\LoginResponse::class)
            ->toResponse(request())
            ->getTargetUrl();

        expect($redirectUrl)->not->toContain('/buyer');
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
            ->assertRedirect(RequestResource::getUrl('index', ['tenant' => $this->team->getKey()]));

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
    it('rejects inviting a user who already has access to the company', function (): void {
        Mail::fake();

        $this->actingAs($this->admin);
        Filament::setTenant($this->team);
        Filament::setCurrentPanel('app');

        expect(fn () => app(InvitePortalUser::class)->execute(
            team: $this->team,
            company: $this->buyer,
            portal: PortalType::Customer,
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
            portal: PortalType::Customer,
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
            portal: PortalType::Customer,
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
            portal: PortalType::Customer,
            email: 'contact@supplier.test',
            name: 'Supplier Contact',
            invitedBy: $this->admin,
        ))->toThrow(\Illuminate\Validation\ValidationException::class);

        Mail::assertNothingSent();
    });

    it('does not resolve supplier-typed invitation tokens on the customer accept page', function (): void {
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

        expect(fn () => livewire(\App\Filament\Customer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token]))
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

    it('allows guests to open the invitation accept page over http', function (): void {
        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'guest.invite@buyer.test',
            'name' => 'Guest Invitee',
            'portal' => PortalType::Customer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        $host = PanelDomain::customerHost();

        $response = $this->get(url()->getCustomerPortalUrl('invitation/'.$invitation->token), ['Host' => $host]);

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
            'portal' => PortalType::Customer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        $host = PanelDomain::customerHost();

        $response = $this->actingAs($unverifiedUser, 'customer')
            ->get(url()->getCustomerPortalUrl('invitation/'.$invitation->token), ['Host' => $host]);

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
            'portal' => PortalType::Customer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'user_id' => null,
            'portal' => PortalType::Customer,
            'invited_by' => $this->admin->getKey(),
            'is_active' => false,
            'invited_name' => 'Multi Company',
            'invited_email' => 'multi@buyer.test',
        ]);

        $this->actingAs($existing, 'customer');

        livewire(\App\Filament\Customer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token])
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
            'portal' => PortalType::Customer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $host = PanelDomain::customerHost();

        $this->get(url()->getCustomerPortalUrl('invitation/'.$invitation->token), ['Host' => $host])
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
            'portal' => PortalType::Customer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        livewire(\App\Filament\Customer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token])
            ->call('accept')
            ->assertRedirect(filament()->getPanel('customer')->getLoginUrl());

        expect($invitation->fresh()?->accepted_at)->toBeNull();
    });

    it('does not resolve an expired invitation on the accept page', function (): void {
        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'expired@buyer.test',
            'name' => 'Expired Invitee',
            'portal' => PortalType::Customer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
            'expires_at' => now()->subDay(),
        ]);

        expect(fn () => livewire(\App\Filament\Customer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token]))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('Customer Request Submission', function (): void {
    it('creates portal request with items', function (): void {
        $uom = UnitOfMeasure::factory()->for($this->team)->create();

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        $component = livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\CreateCustomerRequest::class);

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

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        $component = livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\CreateCustomerRequest::class);

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

    it('preserves item types when a customer edits a draft request', function (): void {
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

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\EditCustomerRequest::class, [
            'record' => $request->getKey(),
        ])
            ->fillForm(['submission_method_choice' => RequestSubmissionMethod::MANUAL->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $item = $request->refresh()->items()->first();

        expect($item->description)->toBe('Machine maintenance')
            ->and($item->item_type)->toBe(\App\Enums\ItemType::SERVICE);
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
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

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
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\ViewCustomerRequest::class, [
            'record' => $request->getKey(),
        ])
            ->assertOk()
            ->assertDontSee('Secret supplier margin info');
    });
});

describe('Customer Portal Phase 2', function (): void {
    it('creates document-based portal request with attachments', function (): void {
        $pdfPath = CustomerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY.'/test-rfq.pdf';
        $absolutePath = storage_path('app/'.$pdfPath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        file_put_contents($absolutePath, '%PDF-1.4 test');

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\CreateCustomerRequest::class)
            ->fillForm([
                'submission_method_choice' => RequestSubmissionMethod::DOCUMENT->value,
                'title' => 'RFQ via Dokumen',
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

        $media = $request->getFirstMedia('attachments');

        expect($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
            ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$request->team_id.'/');
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
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

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
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

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
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Pages\CustomerDashboard::class)->assertOk();

        livewire(\App\Filament\Customer\Widgets\PortalRequestsOverviewWidget::class)
            ->assertSee('Awaiting Confirmation');
    });

    it('resolves portal team from company context', function (): void {
        $this->actingAs($this->portalUser, 'customer');
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        expect(app(CustomerPortalContext::class)->team()->getKey())->toBe($this->team->getKey())
            ->and(app(CustomerPortalContext::class)->company()->getKey())->toBe($this->buyer->getKey());
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

        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());
        expect(app(CustomerPortalContext::class)->companyId())->toBe($this->buyer->getKey());

        app(CustomerPortalContext::class)->setCompany($secondBuyer->getKey());
        expect(app(CustomerPortalContext::class)->companyId())->toBe($secondBuyer->getKey());
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
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

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

describe('Portal-Typed Membership', function (): void {
    it('denies customer panel access for a supplier-typed membership at a dual-role company', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();
        $supplierContact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $supplierContact->getKey(),
            'portal' => \App\Enums\PortalType::Supplier,
            'is_active' => true,
        ]);

        expect($supplierContact->canAccessPanel(Filament::getPanel('customer')))->toBeFalse()
            ->and($supplierContact->hasActiveCustomerPortalAccess())->toBeFalse();
    });

    it('denies customer panel access when the membership company is supplier-only', function (): void {
        $supplierCompany = Company::factory()->supplier()->for($this->team)->create();
        $contact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $supplierCompany->getKey(),
            'user_id' => $contact->getKey(),
            'portal' => \App\Enums\PortalType::Customer,
            'is_active' => true,
        ]);

        expect($contact->canAccessPanel(Filament::getPanel('customer')))->toBeFalse()
            ->and($contact->hasActiveCustomerPortalAccess())->toBeFalse();
    });

    it('grants customer capability only for the customer-typed membership at a dual-role company', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();
        $contact = User::factory()->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $contact->getKey(),
            'portal' => \App\Enums\PortalType::Customer,
            'is_active' => true,
        ]);

        expect($contact->hasActiveCustomerPortalAccess())->toBeTrue()
            ->and($contact->activeCustomerPortalCompanyIds())->toBe([$dualRole->getKey()]);
    });

    it('allows one person to hold customer and supplier memberships at the same company', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();
        $contact = User::factory()->create();

        foreach ([\App\Enums\PortalType::Customer, \App\Enums\PortalType::Supplier] as $portal) {
            CompanyPortalUser::query()->create([
                'team_id' => $this->team->getKey(),
                'company_id' => $dualRole->getKey(),
                'user_id' => $contact->getKey(),
                'portal' => $portal,
                'is_active' => true,
            ]);
        }

        expect(CompanyPortalUser::query()->where('user_id', $contact->getKey())->count())->toBe(2)
            ->and($contact->activeCustomerPortalCompanyIds())->toBe([$dualRole->getKey()]);
    });

    it('excludes supplier-typed memberships from the customer company switcher', function (): void {
        $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $dualRole->getKey(),
            'user_id' => $this->portalUser->getKey(),
            'portal' => \App\Enums\PortalType::Supplier,
            'is_active' => true,
        ]);

        $memberships = app(CustomerPortalContext::class)->activeMemberships($this->portalUser);

        expect($memberships->pluck('company_id')->all())->toBe([$this->buyer->getKey()]);
    });

    it('copies the invitation portal type onto the membership on accept', function (): void {
        $invitation = PortalInvitation::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'email' => 'typed@buyer.test',
            'name' => 'Typed User',
            'portal' => \App\Enums\PortalType::Customer,
            'invited_by' => $this->admin->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        livewire(\App\Filament\Customer\Pages\AcceptPortalInvitation::class, ['token' => $invitation->token])
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

        expect($membership->portal)->toBe(\App\Enums\PortalType::Customer);
    });
});

it('does not notify supplier-typed members of a dual-role company via customer portal fan-out', function (): void {
    $dualRole = Company::factory()->buyerAndSupplier()->for($this->team)->create();

    $customerContact = User::factory()->create();
    $supplierContact = User::factory()->create();

    foreach ([
        [$customerContact, \App\Enums\PortalType::Customer],
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

    app(\App\Actions\CustomerPortal\NotifyPortalUsers::class)->forCompany($dualRole->getKey(), $notification);

    \Illuminate\Support\Facades\Notification::assertSentTo($customerContact, $notification::class);
    \Illuminate\Support\Facades\Notification::assertNotSentTo($supplierContact, $notification::class);
});

it('rejects path traversal in uploaded file attachment and accepts files inside the upload directory', function (): void {
    $request = Request::factory()->for($this->team)->create([
        'buyer_id' => $this->buyer->getKey(),
    ]);

    $uploadDir = storage_path('app/'.CustomerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY);
    \Illuminate\Support\Facades\File::ensureDirectoryExists($uploadDir);
    $legit = $uploadDir.'/legit-'.uniqid().'.pdf';
    file_put_contents($legit, '%PDF-1.4 test');

    app(\App\Actions\Media\AttachUploadedFiles::class)->execute(
        $request,
        [
            '../../.env',
            CustomerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY.'/../../../.env',
            CustomerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY.'/'.basename($legit),
        ],
        'attachments',
        CustomerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY,
    );

    $media = $request->getMedia('attachments');

    expect($media)->toHaveCount(1)
        ->and($media->first()?->file_name)->toBe(basename($legit));

    @unlink($legit);
});

describe('Customer Portal Phase 4', function (): void {
    it('shows action items widget for quotes awaiting confirmation', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION,
        ]);

        \App\Models\BuyerQuote::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->buyer, 'buyer')
            ->sent()
            ->withTotals(1000, 100, 1100)
            ->create();

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Widgets\PortalActionItemsWidget::class)
            ->assertSee('Needs Your Attention')
            ->assertSee('Quote awaiting confirmation');
    });

    it('renders request progress timeline on request detail', function (): void {
        $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
            'submitted_at' => now(),
            'stage' => RequestStage::SHIPPED,
        ]);

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(
            \App\Filament\Customer\Resources\CustomerRequestResource\Pages\ViewCustomerRequest::class,
            ['record' => $request->getRouteKey()],
        )
            ->assertOk()
            ->assertSee('Request Progress')
            ->assertSee('In Transit')
            ->assertSee('Current stage');
    });

    it('filters customer requests by status group', function (): void {
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

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        livewire(\App\Filament\Customer\Resources\CustomerRequestResource\Pages\ListCustomerRequests::class)
            ->filterTable('status_group', 'completed')
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$active]);
    });

    it('allows customer to view sent invoices but not drafts', function (): void {
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

        $this->actingAs($this->portalUser, 'customer');
        Filament::setCurrentPanel('customer');
        app(CustomerPortalContext::class)->setCompany($this->buyer->getKey());

        expect($this->portalUser->can('view', $sentInvoice))->toBeTrue()
            ->and($this->portalUser->can('view', $draftInvoice))->toBeFalse();

        livewire(
            \App\Filament\Customer\Resources\CustomerRequestResource\RelationManagers\InvoicesRelationManager::class,
            [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Customer\Resources\CustomerRequestResource\Pages\ViewCustomerRequest::class,
            ],
        )
            ->assertOk()
            ->assertCanSeeTableRecords([$sentInvoice])
            ->assertCanNotSeeTableRecords([$draftInvoice]);
    });
});
