<?php

declare(strict_types=1);

it('never expires activity records so financial audit trails are permanent', function (): void {
    expect(config('activitylog.delete_records_older_than_days'))->toBeNull();
});
