<?php

declare(strict_types=1);

use App\Enums\RequestSubmissionMethod;
use App\Models\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('hasProofOfRequest', function (): void {
    it('is self-proving for MANUAL entries without media', function (): void {
        $request = Request::factory()->create([
            'submission_method' => RequestSubmissionMethod::MANUAL,
        ]);

        expect($request->hasProofOfRequest())->toBeTrue();
    });

    it('is self-proving for CATALOG entries without media', function (): void {
        $request = Request::factory()->create([
            'submission_method' => RequestSubmissionMethod::CATALOG,
        ]);

        expect($request->hasProofOfRequest())->toBeTrue();
    });

    it('is not proven for staff entries without an attachment', function (): void {
        $request = Request::factory()->create([
            'submission_method' => null,
        ]);

        expect($request->hasProofOfRequest())->toBeFalse();
    });

    it('is proven for staff entries once an attachment is added', function (): void {
        $request = Request::factory()->create([
            'submission_method' => null,
        ]);

        $request->addMediaFromString('dummy')
            ->usingFileName('proof.pdf')
            ->toMediaCollection('attachments');

        expect($request->hasProofOfRequest())->toBeTrue();
    });

    it('is proven for DOCUMENT uploads with an attachment', function (): void {
        $request = Request::factory()->create([
            'submission_method' => RequestSubmissionMethod::DOCUMENT,
        ]);

        $request->addMediaFromString('dummy')
            ->usingFileName('source.pdf')
            ->toMediaCollection('attachments');

        expect($request->hasProofOfRequest())->toBeTrue();
    });
});
