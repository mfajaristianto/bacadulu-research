<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\RedirectResponse;

class LibraryController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Legacy User Library
    |--------------------------------------------------------------------------
    |
    | Library penuh sekarang merupakan knowledge layer Admin.
    |
    | Route lama tetap dipertahankan sementara sebagai compatibility
    | redirect agar bookmark/link existing tidak langsung 404.
    |
    | User diarahkan kembali ke Research Workspace.
    |
    */

    public function index(): RedirectResponse
    {
        return redirect()
            ->route(
                'workspace'
            )
            ->with(
                'success',
                'Source Library sekarang digunakan sebagai knowledge layer internal. Gunakan Research Workspace untuk bekerja dengan evidence.'
            );
    }

    public function show(
        Document $document
    ): RedirectResponse {
        /*
         * Tidak expose metadata/raw source melalui
         * endpoint Library lama.
         *
         * Relevant source metadata tetap dapat
         * diberikan melalui evidence hasil research.
         */
        return redirect()
            ->route(
                'workspace'
            )
            ->with(
                'success',
                'Source context tersedia melalui evidence pada Research Workspace.'
            );
    }
}