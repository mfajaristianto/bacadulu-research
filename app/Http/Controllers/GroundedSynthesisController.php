<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\AiRunEvidence;
use App\Services\Evidence\EvidenceBundleBuilder;
use App\Services\Synthesis\GroundedSynthesisService;
use App\Services\Synthesis\SynthesisFailure;
use App\Services\Synthesis\SynthesisRejected;
use App\Services\Synthesis\SynthesisTools;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * "Analisis dengan AI" dari Research Workspace: konfirmasi -> sintesis berbasis Evidence Bundle -> halaman hasil.
 * Sumber dan bukti SELALU dibangun ulang di server dari kategori + kueri (klien tidak dipercaya). Pada tahap ini hanya provider uji.
 */
class GroundedSynthesisController extends Controller
{
    public function __construct(
        private readonly GroundedSynthesisService $synthesis,
        private readonly EvidenceBundleBuilder $builder,
        private readonly SynthesisTools $tools,
    ) {}

    public function store(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'q' => ['required', 'string', 'max:500'],
            'prompt_template_id' => ['required', 'integer'],
            'year' => ['nullable', 'integer', 'between:1000,2200'], 'language' => ['nullable', 'string', 'regex:/^[a-zA-Z]{2,3}$/'],
            'type' => ['nullable', 'in:article,book,chapter,proceedings,dataset,thesis'],
            'confirm' => ['nullable', 'boolean'],
        ]);
        $filters = ['year' => ($data['year'] ?? null) ? (int) $data['year'] : null, 'language' => ($data['language'] ?? null) ? strtolower($data['language']) : null, 'type' => $data['type'] ?? null];
        $q = trim($data['q']);
        $back = route('research.references', array_filter(['category_id' => $data['category_id'], 'q' => $q] + $filters));

        $status = $this->synthesis->availability();
        $prompt = $this->tools->prompt((int) $data['prompt_template_id']);
        // Panggilan nyata dapat memakan waktu (timeout + percobaan ulang); jangan biarkan PHP memutus di tengah dan meninggalkan run menggantung.
        @set_time_limit((int) config('research.synthesis.total_timeout', 110) + 30);

        $base = ['status' => $status, 'data' => $data, 'filters' => $filters, 'q' => $q, 'back' => $back, 'prompt' => $prompt];

        if (! $status['available'] || ! $prompt) {
            return view('research.references.synthesis', $base + ['state' => 'unavailable', 'reason' => $status['reason'] ?? 'Jenis analisis tidak tersedia di Katalog Alat AI.', 'bundle' => null]);
        }

        if (! ($data['confirm'] ?? false)) {
            $bundle = $this->builder->build((int) $data['category_id'], $q, $filters);
            $estimate = $this->synthesis->estimate($this->synthesis->request($prompt, $bundle, $q));

            return view('research.references.synthesis', $base + ['state' => $bundle->hasEvidenceText() ? 'confirm' : 'empty', 'reason' => null, 'bundle' => $bundle->toArray(), 'estimate' => $estimate]);
        }

        try {
            $run = $this->synthesis->run($request->user(), $prompt, (int) $data['category_id'], $q, $filters, $request->ip());
        } catch (SynthesisRejected $e) {
            // Ditolak sebelum ada AiRun atau permintaan ke provider: pesan sudah aman dan jujur.
            return redirect()->to($back.'#refs-synth')->with('synth_error', $e->getMessage());
        } catch (RuntimeException $e) {
            return redirect()->to($back.'#refs-synth')->with('synth_error', $e->getMessage());
        }

        return redirect()->route('research.references.synthesis.show', ['uuid' => $run->uuid]);
    }

    public function show(Request $request, string $uuid): View
    {
        $run = AiRun::query()->where('uuid', $uuid)->where('user_id', $request->user()->id)->with(['aiTool', 'promptTemplate', 'primaryResult'])->firstOrFail();
        abort_unless(data_get($run->user_parameters_json, 'mode') === 'grounded_synthesis' && $run->primaryResult, 404);

        // Bukti diambil dari SNAPSHOT run (bukan dari data hidup): isi yang ditampilkan = isi yang diberikan ke model.
        $rows = AiRunEvidence::query()->where('ai_run_id', $run->id)->orderBy('id')->get()->keyBy('citation_id');
        $params = $run->user_parameters_json;

        return view('research.references.synthesis-result', [
            'run' => $run, 'out' => $run->primaryResult->structured_output_json, 'rows' => $rows, 'intact' => $rows->every(fn ($r) => $r->intact()),
            'back' => route('research.references', array_filter(['category_id' => $params['category_id'] ?? null, 'q' => $params['user_message'] ?? null] + ($params['filters'] ?? []))),
            'failureMessage' => (($f = $run->primaryResult->structured_output_json['failure_state'] ?? null) && SynthesisFailure::known($f)) ? SynthesisFailure::message($f) : null,
            'toolLabel' => SynthesisTools::labels()[$params['research_tool_code'] ?? ''] ?? ($run->aiTool?->name ?? 'Alat riset'),
        ]);
    }
}
