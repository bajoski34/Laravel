<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the stylesheet and fonts the Blade components use, from the package itself,
 * so there's nothing to publish and no third-party font requests.
 */
class AssetController extends Controller
{
    /** The only files this serves, and their content types. */
    public const FILES = [
        'flutterwave.css' => 'text/css; charset=utf-8',
        'fonts/Millik.woff2' => 'font/woff2',
        'fonts/Moderat-Regular.woff2' => 'font/woff2',
        'fonts/Moderat-Medium.woff2' => 'font/woff2',
        'fonts/Moderat-Bold.woff2' => 'font/woff2',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(isset(self::FILES[$file]), 404);

        // The stylesheet link carries ?v={package version}, so a year-long cache is safe.
        return response()->file(__DIR__.'/../../resources/js/shared/'.$file, [
            'Content-Type' => self::FILES[$file],
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
