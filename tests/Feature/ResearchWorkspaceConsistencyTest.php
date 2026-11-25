<?php

namespace Tests\Feature;

use App\Http\Controllers\ResearchOutputPresentationController;
use App\Models\AiRun;
use App\Models\AiTool;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Hasil audit final (provider Mock): konsistensi panel Workspace, judul dokumen, dan regresi JS unggah Pustaka. */
class ResearchWorkspaceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTION = 'Identifikasi research gap mengenai ESG disclosure dan firm value berdasarkan evidence yang tersedia';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('research.ai_provider', 'mock');
        config()->set('research.ai_model', '');
        config()->set('research.require_email_verification', false);
        config()->set('research.retrieval.minimum_evidence', 1);
    }

    public function test_every_step_of_the_suggested_flow_points_to_a_real_tool(): void
    {
        $codes = AiTool::query()->pluck('code')->all();

        foreach (ResearchOutputPresentationController::NEXT_TOOL as $from => $to) {
            $this->assertContains($from, $codes);
            $this->assertContains($to, $codes);
        }
    }

    public function test_workspace_next_action_matches_the_presentation_endpoint_and_document_title_is_not_cut(): void
    {
        [$user, $run] = $this->completedRun();

        $presentation = $this->actingAs($user)->getJson(route('research.runs.presentation', $run))->assertOk();
        $this->assertSame('Theoretical Mapping', $presentation->json('next_prompt.tool'));

        // Panel "Next action" yang dirender server harus menyarankan alat yang sama (bukan alat pertama menurut abjad).
        $this->actingAs($user)->get(route('workspace.conversation', $run->conversation_id))
            ->assertOk()
            ->assertSeeInOrder(['SUGGESTED RESEARCH TOOL', 'Theoretical Mapping'], false);

        // Judul dokumen default memakai pertanyaan utuh, bukan judul percakapan yang dipotong dengan "…".
        $this->actingAs($user)->get(route('documents.create', ['ai_run' => $run->id]))
            ->assertOk()
            ->assertSee('value="'.e(self::QUESTION).'"', false);
    }

    public function test_library_upload_script_reads_the_form_url_from_the_attribute_not_the_clobbered_property(): void
    {
        // Tombol name="action" menimpa form.action di browser (jadi elemen tombol); unggah gagal 405 bila memakai properti itu.
        $this->assertStringContainsString('name="action"', file_get_contents(resource_path('views/admin/library/form.blade.php')));

        $js = file_get_contents(resource_path('js/admin-library.js'));
        $this->assertStringContainsString("form.getAttribute('action')", $js);
        $this->assertDoesNotMatchRegularExpression("/open\\(\\s*['\"]POST['\"]\\s*,\\s*form\\.action/", $js);
    }

    /** @return array{0: User, 1: AiRun} */
    private function completedRun(): array
    {
        $user = User::factory()->create(['phone' => '081234567890', 'institution' => 'Lab', 'address' => 'Jakarta', 'date_of_birth' => '1995-01-01', 'status' => 'active', 'email_verified_at' => now()]);
        $category = Category::create(['name' => 'Uji', 'slug' => 'uji-'.Str::lower(Str::random(8)), 'status' => 'active']);
        $prompt = PromptTemplate::query()->where('status', 'active')->whereHas('aiTool', fn ($q) => $q->where('code', 'research-gap')->where('status', 'active'))->firstOrFail();

        $doc = Document::create([
            'uuid' => (string) Str::uuid(), 'title' => 'ESG Disclosure and Firm Value', 'category_id' => $category->id, 'document_type' => 'pdf',
            'publication_year' => 2026, 'language' => 'en', 'source_label' => 'Curated Source', 'status' => 'published', 'visibility' => 'public',
            'uploaded_by' => $user->id, 'published_at' => now(),
        ]);
        DocumentChunk::create([
            'document_id' => $doc->id, 'chunk_index' => 0,
            'content' => 'The study reports that ESG disclosure quality is positively associated with firm value. Greater ESG disclosure and sustainability transparency may reduce information asymmetry between firms and investors.',
            'metadata_json' => ['page' => 1],
        ]);
        DocumentChunk::create([
            'document_id' => $doc->id, 'chunk_index' => 1,
            'content' => 'Research concerning ESG disclosure and firm value remains limited in several emerging market contexts. This limitation creates opportunities for future research on mediating and moderating mechanisms.',
            'metadata_json' => ['page' => 2],
        ]);

        $this->actingAs($user)
            ->post(route('research.run'), ['category_id' => $category->id, 'prompt_template_id' => $prompt->id, 'message' => self::QUESTION])
            ->assertRedirect();

        $run = AiRun::query()->latest('id')->firstOrFail();
        $this->assertSame('completed', $run->status);

        return [$user, $run];
    }
}
