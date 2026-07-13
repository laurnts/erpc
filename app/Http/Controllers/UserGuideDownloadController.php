<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class UserGuideDownloadController
{
    public function __invoke(): Response
    {
        $path = resource_path('docs/USER_GUIDE.md');

        if (! is_file($path) || ! is_readable($path)) {
            abort(404, 'User guide not found.');
        }

        $markdown = file_get_contents($path);
        $html = Str::markdown($markdown ?: '');

        $pdf = Pdf::loadView('pdf.user-guide', ['html' => $html]);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'Central-Purchasing-User-Guide.pdf';

        return response(
            $pdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
    }
}
