<?php

declare(strict_types=1);

use App\Http\Middleware\UsePanelSession;
use Illuminate\Http\Request;

it('does not treat supplier-quotes media routes as the supplier panel', function (): void {
    $request = Request::create('/supplier-quotes/22/quotation/7', 'DELETE');

    expect(UsePanelSession::cookieForRequest($request))->toBeNull();
});

it('does not treat buyer-quotes media routes as the buyer panel', function (): void {
    $request = Request::create('/buyer-quotes/5/po/3', 'DELETE');

    expect(UsePanelSession::cookieForRequest($request))->toBeNull();
});

it('still resolves the supplier panel cookie for supplier panel paths', function (): void {
    expect(UsePanelSession::cookieForRequest(Request::create('/supplier/requests', 'GET')))
        ->toBe((string) config('app.supplier_session_cookie'))
        ->and(UsePanelSession::cookieForRequest(Request::create('/supplier', 'GET')))
        ->toBe((string) config('app.supplier_session_cookie'));
});

it('still resolves the buyer panel cookie for buyer panel paths', function (): void {
    expect(UsePanelSession::cookieForRequest(Request::create('/buyer/login', 'GET')))
        ->toBe((string) config('app.buyer_session_cookie'));
});

it('falls through to the default staff cookie for internal panel paths', function (): void {
    expect(UsePanelSession::cookieForRequest(Request::create('/requests', 'GET')))
        ->toBeNull()
        ->and(UsePanelSession::cookieForRequest(Request::create('/', 'GET')))
        ->toBeNull();
});

it('gives each panel a distinct, explicitly-named session cookie', function (): void {
    expect(config('session.cookie'))->toBe('erpc_staff_session')
        ->and((string) config('app.buyer_session_cookie'))->toBe('erpc_buyer_session')
        ->and((string) config('app.supplier_session_cookie'))->toBe('erpc_supplier_session')
        ->and(config('session.cookie'))
        ->not->toBe((string) config('app.buyer_session_cookie'))
        ->and(config('session.cookie'))
        ->not->toBe((string) config('app.supplier_session_cookie'));
});
