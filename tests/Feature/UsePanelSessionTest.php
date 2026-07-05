<?php

declare(strict_types=1);

use App\Http\Middleware\UsePanelSession;
use Illuminate\Http\Request;

it('does not treat supplier-quotes media routes as the supplier panel', function (): void {
    $request = Request::create('/supplier-quotes/22/quotation/7', 'DELETE');

    expect(UsePanelSession::cookieForRequest($request))->toBeNull();
});

it('does not treat buyer-quotes media routes as the customer panel', function (): void {
    $request = Request::create('/buyer-quotes/5/po/3', 'DELETE');

    expect(UsePanelSession::cookieForRequest($request))->toBeNull();
});

it('still resolves the supplier panel cookie for supplier panel paths', function (): void {
    expect(UsePanelSession::cookieForRequest(Request::create('/supplier/rfqs', 'GET')))
        ->toBe((string) config('app.supplier_session_cookie'))
        ->and(UsePanelSession::cookieForRequest(Request::create('/supplier', 'GET')))
        ->toBe((string) config('app.supplier_session_cookie'));
});

it('still resolves the customer panel cookie for buyer panel paths', function (): void {
    expect(UsePanelSession::cookieForRequest(Request::create('/buyer/login', 'GET')))
        ->toBe((string) config('app.customer_session_cookie'));
});
