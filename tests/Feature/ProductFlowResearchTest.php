<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\Category;
use App\Models\DocumentGeneration;
use App\Services\Research\AiProvider;
use App\Services\Research\AiProviderManager;
use App\Services\ResearchFlow\DimensionExtractor;
use App\Services\ResearchFlow\ResearchResultComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\ProductFlowFixtures;
use Tests\TestCase;

/** Product Flow V1: Research tanpa AI (alur, komposer, buku, isolasi kategori, tutorial). */
class ProductFlowResearchTest extends TestCase
{
    use ProductFlowFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFlow();
        // Bukti: setiap pemanggilan penyedia AI melempar. Research tidak boleh menyentuhnya.
        $this->app->bind(AiProviderManager::class, fn () => throw new \LogicException('Research memanggil AiProviderManager'));
        $this->app->bind(AiProvider::class, fn () => throw new \LogicException('Research memanggil AiProvider'));
    }

    private function researchPage(array $q = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->member())->get(route('research.result', $q + ['category_id' => $this->cat->id, 'prompt_template_id' => $this->tool->id, 'q' => 'green accounting terhadap kinerja keuangan perusahaan']));
    }

    public function test_workspace_keeps_the_original_visual_with_the_new_research_contract(): void
    {
        // Pengguna dengan riwayat melihat Workspace asli: topbar, panel 01/02/03, kartu selector, kartu pertanyaan, tombol panah.
        $user = $this->member();
        \App\Models\Conversation::create(['user_id' => $user->id, 'category_id' => $this->cat->id, 'title' => 'Riwayat lama', 'status' => 'active']);
        $res = $this->actingAs($user)->get(route('workspace'))->assertOk();

        foreach (['brx-topbar', 'Personal Workspace', 'research-start-guide', 'Pilih Alat Riset', 'Tulis pertanyaan', 'Periksa sumber', 'research-new-composer-card',
            'research-select-shell', 'research-prompt-picker', 'research-question-panel', 'research-send', 'KATEGORI', 'ALAT RISET', 'TOPIK ATAU PERTANYAAN RISET', 'data-rf-book'] as $marker) {
            $res->assertSee($marker, false);
        }
        // Kontrak backend baru: GET ke hasil Research tanpa AI, field q, tanpa tombol teks generik dan tanpa heading pengganti.
        $res->assertSee('action="'.route('research.result').'"', false)->assertSee('method="GET"', false)->assertSee('name="q"', false);
        foreach (['Cari referensi dulu', 'Output AI bukan evidence', 'Pilih Research Tool', 'RESEARCH TOOL / PROMPT', 'Evidence Engine', 'rf-steps', 'Tulis pertanyaan research Anda', 'name="message"'] as $banned) {
            $res->assertDontSee($banned, false);
        }
    }

    public function test_first_run_workspace_also_posts_to_the_new_research_flow(): void
    {
        $res = $this->actingAs($this->member())->get(route('workspace'))->assertOk();
        $res->assertSee('data-first-research', false)->assertSee('action="'.route('research.result').'"', false)->assertSee('name="q"', false)->assertDontSee('name="message"', false);
        foreach (['Cari referensi dulu', 'Output AI bukan evidence', 'Research Tool'] as $banned) {
            $res->assertDontSee($banned, false);
        }
    }

    public function test_book_tool_is_marked_so_the_workspace_can_show_the_problem_statement_fields(): void
    {
        $user = $this->member();
        \App\Models\Conversation::create(['user_id' => $user->id, 'category_id' => $this->cat->id, 'title' => 'Riwayat lama', 'status' => 'active']);
        $this->actingAs($user)->get(route('workspace'))->assertSee('data-prompt-mode="book"', false)->assertSee('data-prompt-mode="standard"', false);
        // Alat buku terpilih lewat ?prompt_template_id: panel rumusan masalah tampil (tanpa atribut hidden) dengan 8 isian bertingkat.
        $html = $this->get(route('workspace', ['prompt_template_id' => $this->bookTool->id, 'questions' => ['RM satu', 'RM dua']]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="research-question-panel rf-book" data-rf-book\s*>/', $html);
        $this->assertStringContainsString('value="RM satu"', $html);
        $this->assertSame(8, substr_count($html, 'name="questions[]"'));
    }

    public function test_normal_research_never_calls_ai_and_creates_no_run_or_document(): void
    {
        $this->seedGreenAccounting();
        $res = $this->researchPage()->assertOk();

        $res->assertSee('Gambaran riset')->assertSee('Perbandingan penelitian')->assertSee('Referensi')->assertSee('Generate Document');
        $res->assertDontSee('Output AI bukan evidence')->assertDontSee('Cari referensi dulu');
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
        $this->assertSame(0, DocumentGeneration::count());
    }

    public function test_research_works_when_every_ai_provider_is_off(): void
    {
        config(['research.ai_provider' => 'unconfigured', 'research.synthesis.provider' => 'none', 'research.synthesis.live_enabled' => false]);
        $this->seedGreenAccounting();
        $this->researchPage()->assertOk()->assertSee('sumber relevan');
    }

    public function test_composer_is_deterministic_and_marks_missing_dimensions_without_inventing(): void
    {
        $this->seedGreenAccounting();
        $in = ['category_id' => $this->cat->id, 'topic' => 'green accounting terhadap kinerja keuangan perusahaan', 'ruleset' => $this->tool->rulesetText()];
        $a = app(ResearchResultComposer::class)->compose($in);
        $b = app(ResearchResultComposer::class)->compose($in);
        $this->assertSame($a, $b);
        $this->assertFalse($a['ai_used']);
        $this->assertSame(['objek', 'sampel', 'metode', 'variabel', 'teori', 'hasil'], array_column($a['dimensions'], 'key'));

        $rich = collect($a['comparison'])->first(fn ($r) => str_contains($r['title'], 'manufaktur'));
        $meta = collect($a['comparison'])->first(fn ($r) => str_contains($r['title'], 'tinjauan'));
        $this->assertTrue($rich['cells']['sampel']['available']);
        $this->assertStringContainsString('45 perusahaan', $rich['cells']['sampel']['value']);
        $this->assertTrue($rich['cells']['metode']['available']);
        $this->assertFalse($rich['cells']['teori']['available']);          // tidak ada di evidence -> tidak dikarang
        $this->assertNull($rich['cells']['teori']['value']);
        foreach ($meta['cells'] as $cell) {                                  // metadata saja tidak punya teks evidence
            $this->assertFalse($cell['available']);
        }
        $this->assertSame(DimensionExtractor::UNAVAILABLE, $a['unavailable_text']);
        $this->assertSame(1, $a['overview']['with_abstract']);
        $this->assertSame(1, $a['overview']['metadata_only']);

        $this->researchPage()->assertSee('Data tidak tersedia pada evidence.');
    }

    public function test_ruleset_dimensions_follow_the_tool_text(): void
    {
        $this->assertSame(['metode', 'hasil'], DimensionExtractor::fromRuleset('Bandingkan metode dan hasil penelitian.'));
        $this->assertCount(6, DimensionExtractor::fromRuleset('Tidak menyebut dimensi apa pun.'));
    }

    public function test_empty_result_is_honest(): void
    {
        $this->researchPage()->assertOk()->assertSee('Belum ada sumber yang cocok')->assertDontSee('id="generate-document"', false);
    }

    public function test_category_isolation(): void
    {
        $other = Category::create(['name' => 'Kesehatan', 'slug' => 'kes', 'status' => 'active']);
        $this->ext('Green accounting dan kinerja keuangan perusahaan di rumah sakit', 'Penelitian green accounting terhadap kinerja keuangan perusahaan.', $other);
        $this->researchPage()->assertSee('Belum ada sumber yang cocok');
        $this->actingAs($this->member())->get(route('research.result', ['category_id' => $other->id, 'prompt_template_id' => $this->tool->id, 'q' => 'green accounting kinerja keuangan perusahaan']))->assertSee('rumah sakit');
    }

    public function test_book_research_gives_one_result_per_question_and_deterministic_aggregate(): void
    {
        $this->seedGreenAccounting();
        $this->ext('Pengungkapan lingkungan dan nilai perusahaan', 'Penelitian ini menguji pengungkapan lingkungan terhadap nilai perusahaan dengan metode regresi.');
        $res = $this->actingAs($this->member())->get(route('research.result', [
            'category_id' => $this->cat->id, 'prompt_template_id' => $this->bookTool->id, 'q' => 'akuntansi lingkungan',
            'questions' => ['green accounting kinerja keuangan perusahaan', 'pengungkapan lingkungan nilai perusahaan', 'topik yang tidak ada sama sekali xyzzy'],
        ]))->assertOk();
        $res->assertSee('Rumusan Masalah 1')->assertSee('Rumusan Masalah 2')->assertSee('Rumusan Masalah 3')->assertSee('Ringkasan keseluruhan');
        $res->assertSee('Belum ada sumber untuk Rumusan Masalah 3');
        Http::assertNothingSent();

        $r = app(ResearchResultComposer::class)->compose(['category_id' => $this->cat->id, 'topic' => 'akuntansi lingkungan', 'questions' => ['green accounting kinerja keuangan perusahaan', 'pengungkapan lingkungan nilai perusahaan', 'xyzzy tidak ada'], 'ruleset' => '']);
        $this->assertSame('book', $r['mode']);
        $this->assertCount(3, $r['questions']);
        $this->assertSame([3], $r['aggregate']['empty_questions']);
        $this->assertSame(2, $r['aggregate']['covered_questions']);
        $this->assertGreaterThanOrEqual(2, $r['aggregate']['unique_sources']);
    }

    public function test_book_tool_requires_at_least_one_question_and_standard_tool_ignores_questions(): void
    {
        $this->actingAs($this->member())->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $this->bookTool->id, 'q' => 'akuntansi lingkungan']))
            ->assertRedirect(route('workspace'))->assertSessionHasErrors('questions');

        $this->seedGreenAccounting();
        $r = $this->researchPage(['questions' => ['abaikan saya']])->assertOk();
        $r->assertDontSee('Rumusan Masalah 1');
    }

    public function test_book_research_accepts_1_to_8_questions_and_rejects_more_than_8(): void
    {
        $this->seedGreenAccounting();
        foreach ([1, 8] as $n) {
            $qs = array_map(fn ($i) => "green accounting kinerja keuangan {$i}", range(1, $n));
            $this->actingAs($this->member())->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $this->bookTool->id, 'q' => 'akuntansi lingkungan', 'questions' => $qs]))
                ->assertOk()->assertSee("Rumusan Masalah {$n}");
        }
        $this->actingAs($this->member())->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $this->bookTool->id, 'q' => 'akuntansi lingkungan', 'questions' => array_fill(0, 9, 'green accounting kinerja')]))
            ->assertSessionHasErrors('questions');
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
    }

    public function test_riset_buku_tool_with_its_ruleset_runs_1_3_and_8_questions_with_zero_ai(): void
    {
        $this->seedGreenAccounting();
        $ruleset = 'Telusuri dan bandingkan penelitian mengenai {topik} untuk menjawab setiap rumusan masalah berdasarkan evidence yang tersedia dari sumber internal dan eksternal. Gunakan dimensi yang tersedia seperti objek, sampel, metode, variabel, teori, dan hasil penelitian.';
        $riset = $this->makeTool('book-research', 'Riset Buku', $ruleset, 'book');
        $this->assertSame('book', $riset->input_mode_name);
        $this->assertSame(array_keys(DimensionExtractor::DIMENSIONS), DimensionExtractor::fromRuleset($riset->rulesetText()));   // enam dimensi, bukan prompt AI

        $pool = ['green accounting kinerja keuangan perusahaan', 'pengungkapan lingkungan nilai perusahaan', 'akuntansi lingkungan biaya', 'sustainability reporting nilai perusahaan', 'corporate social responsibility profitabilitas', 'audit lingkungan', 'topik tidak ada xyzzy', 'tata kelola perusahaan kinerja'];
        foreach ([1, 3, 8] as $n) {
            $res = $this->actingAs($this->member())->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $riset->id, 'q' => 'akuntansi lingkungan', 'questions' => array_slice($pool, 0, $n)]))->assertOk();
            for ($i = 1; $i <= $n; $i++) {
                $res->assertSee("Rumusan Masalah {$i}");
            }
            $res->assertDontSee('Rumusan Masalah '.($n + 1))->assertSee('Ringkasan keseluruhan');
        }
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
        $this->assertSame(0, DocumentGeneration::count());

        // Tool standard tidak pernah menerima perilaku buku, walau questions[] dikirim.
        $this->actingAs($this->member())->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $this->tool->id, 'q' => 'akuntansi lingkungan', 'questions' => $pool]))
            ->assertOk()->assertDontSee('Rumusan Masalah')->assertDontSee('Ringkasan keseluruhan');
    }

    // ---- Riset Buku UX patch: input pengguna vs ruleset, urutan render, validasi ------------------------------------------

    private const RULESET = 'Telusuri dan bandingkan penelitian mengenai {topik} untuk menjawab setiap rumusan masalah berdasarkan evidence yang tersedia dari sumber internal dan eksternal. Gunakan dimensi yang tersedia seperti objek, sampel, metode, variabel, teori, dan hasil penelitian.';

    /** Alat uji lengkap dengan question_template (ruleset) seperti alat produksi. */
    private function templated(string $code, string $name, string $mode): \App\Models\PromptTemplate
    {
        $t = $this->makeTool($code, $name, self::RULESET, $mode);
        $t->update(['question_template' => self::RULESET]);

        return $t->refresh();
    }

    private function returningUser(): \App\Models\User
    {
        $u = $this->member();
        \App\Models\Conversation::create(['user_id' => $u->id, 'category_id' => $this->cat->id, 'title' => 'Riwayat lama', 'status' => 'active']);

        return $u;
    }

    private function topicValue(string $html): string
    {
        $this->assertSame(1, preg_match('/<textarea[^>]*name="q"[^>]*>(.*?)<\/textarea>/s', $html, $m), 'textarea Topik (name="q") tidak ditemukan');

        return trim(html_entity_decode($m[1]));
    }

    public function test_selecting_a_tool_never_prefills_the_topic_with_its_ruleset(): void
    {
        $book = $this->templated('book-uji', 'Riset Buku Uji', 'book');
        $std = $this->templated('std-uji', 'Riset Standar Uji', 'standard');

        foreach ([$book, $std] as $tool) {
            // Alat terpilih lewat query (?prompt_template_id / ?prompt) seperti saat pengguna memilih alat: Topik tetap kosong.
            foreach ([['prompt_template_id' => $tool->id], ['prompt' => $tool->id], []] as $q) {
                $html = $this->actingAs($this->returningUser())->get(route('workspace', $q))->assertOk()->getContent();
                $this->assertSame('', $this->topicValue($html), 'Topik terisi untuk alat '.$tool->name);
                $withoutMap = preg_replace('/<script\s+type="application\/json"\s+data-prompt-question-map\s*>.*?<\/script>/s', '', $html);
                $this->assertStringNotContainsString('Telusuri dan bandingkan penelitian mengenai {topik}', $withoutMap);
            }
            // Varian first-run (tanpa riwayat).
            $html = $this->actingAs($this->member())->get(route('workspace', ['prompt_template_id' => $tool->id]))->assertOk()->getContent();
            $this->assertSame('', $this->topicValue($html));
        }
    }

    public function test_question_template_is_not_a_user_input_in_the_research_form_but_stays_for_backend_and_legacy_flow(): void
    {
        $book = $this->templated('book-uji', 'Riset Buku Uji', 'book');

        // Backend: ruleset tetap tersimpan dan terbaca sebagai aturan.
        $this->assertSame(self::RULESET, $book->question_template);
        $this->assertStringContainsString(self::RULESET, $book->rulesetText());

        // Kontrak JS: template hanya diterapkan pada form percakapan lama, tidak pada form riset baru ([data-rf-form]).
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("'[data-rf-form]'", $js);
        $this->assertMatchesRegularExpression('/if \(isResearchFlowForm\) \{\s*return false;\s*\}/', $js);

        // Jalur lama: peta template tetap dikirim ke halaman percakapan lama, form lanjutannya bukan [data-rf-form].
        $user = $this->returningUser();
        $conv = \App\Models\Conversation::where('user_id', $user->id)->firstOrFail();
        $res = $this->actingAs($user)->get(route('workspace.conversation', $conv))->assertOk();
        $res->assertSee('data-prompt-question-map', false)->assertDontSee('data-rf-form', false);
        $this->assertStringContainsString('Telusuri dan bandingkan penelitian mengenai {topik}', $res->getContent());
    }

    public function test_user_topic_and_tool_ruleset_reach_the_composer_separately(): void
    {
        $this->seedGreenAccounting();
        $book = $this->templated('book-uji', 'Riset Buku Uji', 'book');

        $res = $this->actingAs($this->member())->get(route('research.result', [
            'category_id' => $this->cat->id, 'prompt_template_id' => $book->id, 'q' => 'Akuntansi Keuangan Digital',
            'questions' => ['green accounting kinerja keuangan perusahaan'],
        ]))->assertOk();

        $result = $res->viewData('result');
        $this->assertSame('Akuntansi Keuangan Digital', $result['topic']);                                         // topik = input pengguna apa adanya
        $this->assertStringNotContainsString('Telusuri', $result['topic']);
        $this->assertSame(array_keys(DimensionExtractor::DIMENSIONS), array_column($result['dimensions'], 'key')); // ruleset dibaca terpisah -> enam dimensi
        $this->assertSame(self::RULESET, $res->viewData('tool')->question_template);
        // Ruleset hanya aturan dimensi: tidak ada substitusi {topik} yang bocor ke input maupun ke hasil.
        $this->assertStringContainsString('{topik}', $res->viewData('tool')->rulesetText());
        $this->assertStringNotContainsString('Akuntansi Keuangan Digital', $res->viewData('tool')->rulesetText());
        $res->assertDontSee('{topik}', false);
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
    }

    public function test_empty_topic_or_template_text_is_rejected_and_ruleset_is_never_a_fallback(): void
    {
        $this->seedGreenAccounting();
        $book = $this->templated('book-uji', 'Riset Buku Uji', 'book');
        $std = $this->templated('std-uji', 'Riset Standar Uji', 'standard');
        $user = $this->member();

        foreach ([$book, $std] as $tool) {
            $base = ['category_id' => $this->cat->id, 'prompt_template_id' => $tool->id, 'questions' => ['green accounting kinerja keuangan']];
            $this->actingAs($user)->get(route('research.result', $base + ['q' => '']))->assertSessionHasErrors('q');
            $this->actingAs($user)->get(route('research.result', $base))->assertSessionHasErrors('q');                               // tanpa q: tidak ada fallback ke ruleset
            $this->actingAs($user)->get(route('research.result', $base + ['q' => self::RULESET]))->assertSessionHasErrors('q');        // template utuh yang tak diubah
            // Hanya token template yang dikenal sistem yang ditolak (tidak peka huruf besar-kecil, spasi dalam kurung diabaikan).
            foreach (['{topik}', 'Pengaruh {topik} terhadap kinerja', 'Akuntansi {topik} digital', '{topic}', '{TOPIK}', 'Pengaruh { topik } terhadap kinerja', '{objek} dan {fokus} penelitian'] as $templateLike) {
                $this->actingAs($user)->get(route('research.result', $base + ['q' => $templateLike]))->assertSessionHasErrors('q');
            }
        }
        // Generate Document memakai aturan yang sama untuk topik (pengguna baru: throttle research.* dan document.store berbagi penghitung).
        $this->actingAs($this->member())->post(route('research.document.store'), ['category_id' => $this->cat->id, 'prompt_template_id' => $std->id, 'q' => 'Pengaruh {topik} terhadap kinerja',
            'document_type' => 'Literature Review', 'citation_style' => 'APA 7', 'output_format' => 'docx', 'language' => 'id', 'length' => 'sedang'])->assertSessionHasErrors('q');
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
    }

    public function test_topics_with_other_curly_braces_are_valid(): void
    {
        $this->seedGreenAccounting();
        $book = $this->templated('book-uji', 'Riset Buku Uji', 'book');
        $std = $this->templated('std-uji', 'Riset Standar Uji', 'standard');
        $user = $this->member();

        foreach (['Pengaruh struktur {X} terhadap kinerja', 'Model {A/B} pada penelitian akuntansi', 'Himpunan {1,2,3} dan variabel {x} pada green accounting'] as $topic) {
            // Riset standard
            $res = $this->actingAs($user)->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $std->id, 'q' => $topic]))->assertOk();
            $res->assertSessionHasNoErrors();
            $this->assertSame($topic, $res->viewData('result')['topic']);                  // topik dipakai apa adanya
            // Riset Buku 1 / 3 / 8 RM
            foreach ([1, 3, 8] as $n) {
                $res = $this->actingAs($user)->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $book->id, 'q' => $topic, 'questions' => array_fill(0, $n, 'green accounting kinerja keuangan')]))->assertOk();
                $res->assertSessionHasNoErrors()->assertSee("Rumusan Masalah {$n}");
                $this->assertSame($topic, $res->viewData('result')['topic']);
            }
        }
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
        $this->assertSame(0, DocumentGeneration::count());
    }

    public function test_book_research_requires_every_displayed_problem_statement_within_1_to_8(): void
    {
        $this->seedGreenAccounting();
        $book = $this->templated('book-uji', 'Riset Buku Uji', 'book');
        $base = ['category_id' => $this->cat->id, 'prompt_template_id' => $book->id, 'q' => 'akuntansi lingkungan'];
        $user = $this->member();

        $this->actingAs($user)->get(route('research.result', $base))->assertSessionHasErrors('questions');                                       // 0 RM
        $this->actingAs($user)->get(route('research.result', $base + ['questions' => ['']]))->assertSessionHasErrors('questions');               // RM terlihat tapi kosong
        $this->actingAs($user)->get(route('research.result', $base + ['questions' => ['green accounting kinerja', '', 'audit lingkungan']]))->assertSessionHasErrors('questions'); // satu RM kosong
        $this->actingAs($user)->get(route('research.result', $base + ['questions' => array_fill(0, 9, 'green accounting kinerja')]))->assertSessionHasErrors('questions'); // > 8

        foreach ([1, 3, 8] as $n) {
            $this->actingAs($user)->get(route('research.result', $base + ['questions' => array_fill(0, $n, 'green accounting kinerja keuangan')]))->assertOk()->assertSee("Rumusan Masalah {$n}");
        }
        // JS: semua kolom RM yang tampil wajib (bukan hanya yang pertama).
        $this->assertStringContainsString('input.required = on;', file_get_contents(resource_path('js/research-flow.js')));
        $this->assertSame(0, AiRun::count());
        Http::assertNothingSent();
    }

    public function test_standard_research_still_works_with_a_templated_tool(): void
    {
        $this->seedGreenAccounting();
        $std = $this->templated('std-uji', 'Riset Standar Uji', 'standard');

        $res = $this->actingAs($this->member())->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $std->id, 'q' => 'green accounting terhadap kinerja keuangan perusahaan', 'questions' => ['abaikan']]))->assertOk();
        $res->assertSee('Gambaran riset')->assertDontSee('Rumusan Masalah');
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
    }

    public function test_workspace_renders_topic_before_the_problem_statement_panel(): void
    {
        $book = $this->templated('book-uji', 'Riset Buku Uji', 'book');
        $html = $this->actingAs($this->returningUser())->get(route('workspace', ['prompt_template_id' => $book->id]))->assertOk()->getContent();

        $pos = fn (string $needle) => strpos($html, $needle);
        foreach (['KATEGORI', 'ALAT RISET', 'TOPIK ATAU PERTANYAAN RISET', 'name="q"', 'class="research-question-panel rf-book"', 'RUMUSAN MASALAH'] as $needle) {
            $this->assertNotFalse($pos($needle), "{$needle} tidak ada");
        }
        $this->assertLessThan($pos('ALAT RISET'), $pos('KATEGORI'));
        $this->assertLessThan($pos('TOPIK ATAU PERTANYAAN RISET'), $pos('ALAT RISET'));
        $this->assertLessThan($pos('class="research-question-panel rf-book"'), $pos('name="q"'));        // Topik sebelum panel RM
        $this->assertLessThan($pos('RUMUSAN MASALAH'), $pos('TOPIK ATAU PERTANYAAN RISET'));
        $this->assertSame(1, substr_count($html, 'data-rf-book'));                                           // satu panel RM, tidak digandakan
        $this->assertSame(8, substr_count($html, 'name="questions[]"'));
        // Panel RM tetap memakai komponen existing.
        $this->assertStringContainsString('class="research-question-panel rf-book" data-rf-book', preg_replace('/\s+/', ' ', $html));
    }

    public function test_research_requires_login_and_valid_input(): void
    {
        $this->get(route('research.result', ['category_id' => 1]))->assertRedirect(route('login'));
        $this->actingAs($this->member())->get(route('research.result', ['category_id' => $this->cat->id]))->assertSessionHasErrors(['prompt_template_id', 'q']);
    }

    public function test_first_login_tutorial_complete_and_admin_reset(): void
    {
        $user = $this->member();
        $this->actingAs($user)->get(route('workspace'))->assertSee('data-first-tour', false)->assertSee('Pilih kategori dan alat riset');

        $this->postJson(route('onboarding.complete'))->assertOk();
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->get(route('workspace'))->assertDontSee('data-first-tour', false);

        // Admin: Reset Tutorial. Data riset tidak dihapus.
        config(['research.admin_email' => 'admin@uji.test']);
        $admin = $this->member(['email' => 'admin@uji.test']);
        AiRun::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $user->id, 'status' => 'completed', 'user_parameters_json' => [], 'model_provider' => 'mock', 'model_name' => 'x']);
        $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $admin->id])
            ->post(route('admin.users.tutorial-reset', $user))->assertRedirect();
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertSame(1, AiRun::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.tutorial_reset', 'auditable_id' => $user->id]);

        $this->actingAs($user->fresh())->get(route('workspace'))->assertSee('data-first-tour', false);
    }

    public function test_tutorial_video_is_shown_only_when_configured(): void
    {
        config(['research.tutorial_video_url' => 'https://cdn.example.org/tutorial.mp4']);
        $this->actingAs($this->member())->get(route('workspace'))->assertSee('https://cdn.example.org/tutorial.mp4', false);
    }
}
