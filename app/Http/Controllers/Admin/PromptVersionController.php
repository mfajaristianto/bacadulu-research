<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/*
 * Rute lama "Versi Instruksi". Halamannya sekarang menyatu dengan Instruksi & Versi (PromptController), jadi di sini hanya
 * tersisa pintu kompatibilitas: link lama tetap bekerja dan memakai aturan yang sama persis (satu implementasi, bukan dua).
 */
class PromptVersionController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.prompts.index', $request->only(['search', 'tool', 'status']));
    }

    public function duplicate(Request $request, PromptTemplate $prompt): RedirectResponse|JsonResponse
    {
        return app(PromptController::class)->revise($request, $prompt);
    }

    public function activate(Request $request, PromptTemplate $prompt): RedirectResponse|JsonResponse
    {
        $response = app(PromptController::class)->activate($request, $prompt);

        // Perilaku lama: kembali ke halaman asal, bukan ke detail.
        return $response instanceof RedirectResponse ? back()->with('success', session('success')) : $response;
    }
}
