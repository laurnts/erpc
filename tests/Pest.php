<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Pure Unit Tests (No Database)
|--------------------------------------------------------------------------
|
| Tests in this section do not require database access and should not use
| RefreshDatabase trait. These are for testing pure logic like enums.
|
*/

pest()->extend(Tests\TestCase::class)
    ->in('Unit/Enums', 'Unit/Erp');

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Only fake specific events that cause unwanted side effects during tests
        // Don't globally fake all events as that breaks model observers
        Event::fake([
            \Laravel\Jetstream\Events\TeamCreated::class,
            \Laravel\Jetstream\Events\TeamDeleted::class,
        ]);

        // Seed ERP permissions for all tests
        $this->seed(\Database\Seeders\ErpPermissionSeeder::class);
    })
    ->in('Feature', 'Unit/Models', 'Unit/Services');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
