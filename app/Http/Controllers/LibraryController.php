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
    | Raw Library adalah knowledge layer internal untuk Admin.
    |
    | Route user lama tetap dipertahankan hanya sebagai compatibility
    | redirect supaya bookmark/link lama tidak langsung menjadi 404.
    |
    | Endpoint ini:
    |
    | - tidak menampilkan daftar raw source;
    | - tidak menampilkan metadata internal;
    | - tidak mengirim PDF;
    | - tidak mengirim extracted text;
    | - tidak mengirim chunks.
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
                'Source Library digunakan sebagai knowledge layer internal. Gunakan Research Workspace untuk bekerja dengan evidence.'
            );
    }

    public function show(
        Document $document
    ): RedirectResponse {
        /*
         * Model binding boleh menemukan Document,
         * tetapi tidak ada data document yang dikirim
         * kepada user dari endpoint legacy ini.
         */

        return redirect()
            ->route(
                'workspace'
            )
            ->with(
                'success',
                'Source context tersedia melalui evidence yang tervalidasi pada Research Workspace.'
            );
    }
}