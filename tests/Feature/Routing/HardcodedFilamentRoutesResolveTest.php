<?php

declare(strict_types=1);

use Filament\Facades\Filament;

/**
 * Filament route names embed the panel id — `filament.{panel}.resources.{x}.{page}`.
 * A hardcoded name naming a panel that does not exist still looks plausible,
 * passes static analysis, and only fails when a user reaches that code path.
 *
 * That is exactly what happened: `QuotationEvaluationForm` shipped in February
 * 2026 redirecting to `filament.admin.resources.requests.view`, but this
 * application has no `admin` panel — its panels are app, buyer, supplier and
 * sysadmin. The redirect threw RouteNotFoundException in production for months
 * before anyone exercised that branch.
 *
 * This asserts the panel segment only, deliberately. Whether a given resource
 * exposes a `view` page is a real runtime variable — several notifications
 * reference pages that do not exist and correctly catch RouteNotFoundException
 * to degrade to no action link. A misspelled *panel*, by contrast, is never
 * anything but a typo.
 */
it('every hardcoded filament route name targets a panel that exists', function (): void {
    $panels = array_keys(Filament::getPanels());

    expect($panels)->not->toBeEmpty('Expected Filament panels to be registered.');

    $roots = array_filter([
        base_path('app'),
        base_path('app-modules'),
        base_path('resources'),
    ], is_dir(...));

    $offenders = [];

    foreach ($roots as $root) {
        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());

            // Only strings passed to route(). Filament's Blade view namespaces
            // share the `filament.` prefix (filament.forms.components.*,
            // filament.modals.*, …) and are not routes.
            if (preg_match_all("/\broute\(\s*['\"]filament\.([a-z0-9_-]+)\./i", $contents, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $panel) {
                if (in_array($panel, $panels, true)) {
                    continue;
                }

                $offenders[] = sprintf(
                    "panel '%s' in %s",
                    $panel,
                    str_replace(base_path().'/', '', $file->getPathname()),
                );
            }
        }
    }

    expect(array_values(array_unique($offenders)))->toBe(
        [],
        'Hardcoded Filament route names targeting panels that do not exist (registered: '.implode(', ', $panels)."):\n- "
            .implode("\n- ", array_unique($offenders)),
    );
});
