<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('registers every approval command under the noun:approve pattern', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('supplier-order:approve')
        ->toContain('goods-receive:approve')
        ->toContain('quotation-evaluation:approve')
        ->toContain('profit-and-loss:approve')
        ->toContain('credit-limit-acceptance:approve')
        ->toContain('member-invite:approve')
        ->not->toContain('qe-or-pnl:approve')
        ->not->toContain('approve:qe-or-pnl')
        ->not->toContain('acceptance-report:approve')
        ->not->toContain('approve:credit-limit-acceptance');
});
