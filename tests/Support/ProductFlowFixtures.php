<?php

namespace Tests\Support;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\ExternalPublication;
use App\Models\ExternalPublicationCategory;
use App\Models\ExternalPublicationSource;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\ExternalSources\ExternalSourceSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/** Bahan uji alur produk: kategori, alat riset (biasa + buku), publikasi eksternal berabstrak, pengguna dengan profil lengkap. */
trait ProductFlowFixtures
{
    use FullTextFixtures;

    protected Category $cat;

    protected PromptTemplate $tool;

    protected PromptTemplate $bookTool;

    private int $pubN = 0;

    protected function bootFlow(): void
    {
        $this->bootFullText();
        Cache::flush();
        Http::fake();
        foreach (['crossref', 'doaj'] as $p) {
            app(ExternalSourceSettings::class)->save($p, ['enabled' => 1], null);
        }
        $this->cat = Category::create(['name' => 'Akuntansi', 'slug' => 'akuntansi', 'status' => 'active']);
        $this->tool = $this->makeTool('pf-compare', 'Analisis Perbandingan', 'Bandingkan penelitian mengenai {topik} berdasarkan dimensi yang tersedia dalam evidence, seperti objek, sampel, metode, variabel, teori, dan hasil penelitian.');
        $this->bookTool = $this->makeTool('pf-book', 'Riset Buku', 'Kumpulkan sumber untuk setiap rumusan masalah buku.', 'book');
    }

    protected function makeTool(string $code, string $name, string $task, string $mode = 'standard'): PromptTemplate
    {
        $tool = AiTool::create(['code' => $code, 'name' => $name, 'description' => 'x', 'status' => 'active']);
        $data = ['ai_tool_id' => $tool->id, 'name' => $name, 'indonesian_name' => $name, 'short_description' => 'Deskripsi '.$name, 'task_prompt' => $task, 'version' => 1, 'status' => 'active'];
        if (Schema::hasColumn('prompt_templates', 'input_mode')) {
            $data['input_mode'] = $mode;
        }

        return PromptTemplate::create($data);
    }

    protected function ext(string $title, ?string $abstract, ?Category $cat = null): ExternalPublication
    {
        $i = ++$this->pubN;
        $p = $this->pub(['title' => $title, 'abstract' => $abstract, 'doi' => "10.7777/pf.{$i}", 'landing_url' => "https://doaj.org/article/{$i}"]);
        ExternalPublicationSource::create(['publication_id' => $p->id, 'provider' => 'doaj', 'external_id' => "pf{$i}"]);
        ExternalPublicationCategory::create(['publication_id' => $p->id, 'category_id' => ($cat ?? $this->cat)->id, 'status' => 'suggested']);

        return $p;
    }

    /** Dua publikasi: satu abstrak kaya dimensi (sampel, metode, hasil), satu metadata saja. */
    protected function seedGreenAccounting(): array
    {
        return [
            $this->ext('Green accounting dan kinerja keuangan perusahaan manufaktur', 'Penelitian ini menguji green accounting terhadap kinerja keuangan perusahaan manufaktur. Sampel penelitian adalah 45 perusahaan manufaktur yang terdaftar di Bursa Efek Indonesia. Metode analisis menggunakan regresi data panel. Hasil menunjukkan green accounting berpengaruh positif terhadap kinerja keuangan.'),
            $this->ext('Green accounting dan kinerja perusahaan: tinjauan', null),
        ];
    }

    protected function member(array $o = []): User
    {
        return User::factory()->create($o + ['phone' => '0811111111', 'institution' => 'Univ Uji', 'address' => 'Jl. Uji 1', 'date_of_birth' => '1995-01-01']);
    }
}
