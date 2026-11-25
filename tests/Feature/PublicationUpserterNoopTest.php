<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ExternalPublication;
use App\Models\ExternalPublicationCategory;
use App\Models\ExternalPublicationSource;
use App\Services\ExternalSources\Normalize;
use App\Services\ExternalSources\PublicationUpserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sinkronisasi ulang rekaman yang isinya sama TIDAK boleh mengubah updated_at; jejak "terlihat lagi" (last_seen_at) tetap dicatat.
 * Perubahan isi sungguhan (checksum berbeda, atau penyedia lain melengkapi bidang kosong) tetap memperbarui updated_at.
 */
class PublicationUpserterNoopTest extends TestCase
{
    use RefreshDatabase;

    private function rec(string $provider, string $id, array $o = []): array
    {
        return Normalize::finish($provider, $o + [
            'external_id' => $id, 'doi' => '10.5555/noop.1', 'title' => 'Pelaporan keuangan', 'authors' => ['A. Penulis'], 'year' => 2021,
            'container_title' => 'Jurnal Uji', 'abstract' => 'Abstrak awal tentang pelaporan keuangan yang cukup panjang untuk diuji.', 'type' => 'article', 'oa_status' => 'open',
            'landing_url' => 'https://example.org/p/1',
        ]);
    }

    private function up(array $r, ?int $cat = null): string
    {
        return app(PublicationUpserter::class)->upsert($r, $cat, 'pelaporan keuangan');
    }

    public function test_resyncing_identical_metadata_records_seen_again_but_does_not_touch_updated_at(): void
    {
        $cat = Category::create(['name' => 'Akuntansi', 'slug' => 'akt', 'status' => 'active']);
        $t0 = Carbon::parse('2026-11-18 08:00:00');
        Carbon::setTestNow($t0);
        $this->assertSame('new', $this->up($this->rec('doaj', 'd1'), $cat->id));
        $p = ExternalPublication::firstOrFail();
        $s = ExternalPublicationSource::firstOrFail();
        $link = ExternalPublicationCategory::firstOrFail();

        Carbon::setTestNow($t0->copy()->addHour());
        $this->assertSame('duplicate', $this->up($this->rec('doaj', 'd1'), $cat->id));

        $p->refresh();
        $s->refresh();
        $this->assertTrue($p->updated_at->equalTo($t0), 'isi tidak berubah: updated_at publikasi tetap');
        $this->assertTrue($p->last_seen_at->equalTo($t0->copy()->addHour()), 'jejak terlihat-lagi tetap ditulis');
        $this->assertTrue($s->updated_at->equalTo($t0), 'baris sumber tak berubah: updated_at tetap');
        $this->assertTrue($s->last_seen_at->equalTo($t0->copy()->addHour()));
        $this->assertTrue($link->fresh()->updated_at->equalTo($t0), 'usulan kategori tidak disentuh');
        $this->assertSame([1, 1, 1], [ExternalPublication::count(), ExternalPublicationSource::count(), ExternalPublicationCategory::count()]);
        $this->assertTrue($p->first_seen_at->equalTo($t0), 'first_seen_at tidak pernah berubah');
    }

    public function test_real_content_changes_still_update_updated_at(): void
    {
        $t0 = Carbon::parse('2026-11-18 08:00:00');
        Carbon::setTestNow($t0);
        $this->up($this->rec('doaj', 'd1'));

        // Penyedia yang sama, checksum berbeda (isi berubah).
        Carbon::setTestNow($t0->copy()->addHours(2));
        $this->assertSame('updated', $this->up($this->rec('doaj', 'd1', ['abstract' => 'Abstrak diperbarui oleh penyedia dengan keterangan tambahan yang berbeda.'])));
        $p = ExternalPublication::firstOrFail();
        $this->assertTrue($p->updated_at->equalTo($t0->copy()->addHours(2)));
        $this->assertStringContainsString('diperbarui', $p->abstract);
        $this->assertTrue(ExternalPublicationSource::firstOrFail()->updated_at->equalTo($t0->copy()->addHours(2)), 'checksum sumber berubah = baris sumber diperbarui');
    }

    public function test_other_provider_adds_provenance_and_touches_updated_at_only_when_it_completes_empty_fields(): void
    {
        $t0 = Carbon::parse('2026-11-18 08:00:00');
        Carbon::setTestNow($t0);
        $this->up($this->rec('doaj', 'd1', ['abstract' => null]));

        // OpenAlex: DOI sama, melengkapi abstrak yang kosong -> isi berubah.
        Carbon::setTestNow($t0->copy()->addHour());
        $this->assertSame('duplicate', $this->up($this->rec('openalex', 'W1')));
        $p = ExternalPublication::firstOrFail();
        $this->assertSame(1, ExternalPublication::count(), 'DOI sama = satu publikasi');
        $this->assertSame(['doaj', 'openalex'], ExternalPublicationSource::orderBy('provider')->pluck('provider')->all(), 'provenance gabungan');
        $this->assertNotNull($p->abstract);
        $this->assertTrue($p->updated_at->equalTo($t0->copy()->addHour()), 'abstrak kosong dilengkapi = isi berubah');

        // Crossref: DOI sama, tidak ada yang bisa dilengkapi -> hanya provenance baru dan jejak terlihat-lagi.
        Carbon::setTestNow($t0->copy()->addHours(3));
        $this->up($this->rec('crossref', 'c1'));
        $p->refresh();
        $this->assertSame(1, ExternalPublication::count());
        $this->assertSame(3, ExternalPublicationSource::count());
        $this->assertTrue($p->updated_at->equalTo($t0->copy()->addHour()), 'tidak ada perubahan isi: updated_at tidak berubah');
        $this->assertTrue($p->last_seen_at->equalTo($t0->copy()->addHours(3)));

        // Mengulang openalex tanpa perubahan: tetap tidak menyentuh updated_at, baik publikasi maupun sumber.
        $before = ExternalPublicationSource::where('provider', 'openalex')->firstOrFail()->updated_at;
        Carbon::setTestNow($t0->copy()->addHours(5));
        $this->assertSame('duplicate', $this->up($this->rec('openalex', 'W1')));
        $this->assertTrue($p->fresh()->updated_at->equalTo($t0->copy()->addHour()));
        $this->assertTrue(ExternalPublicationSource::where('provider', 'openalex')->firstOrFail()->updated_at->equalTo($before));
    }

    public function test_empty_provider_values_never_overwrite_stored_metadata(): void
    {
        $t0 = Carbon::parse('2026-11-18 08:00:00');
        Carbon::setTestNow($t0);
        $this->up($this->rec('doaj', 'd1'));
        $abstract = ExternalPublication::firstOrFail()->abstract;

        Carbon::setTestNow($t0->copy()->addHour());
        $this->up($this->rec('doaj', 'd1', ['abstract' => null, 'container_title' => null, 'authors' => []]));

        $p = ExternalPublication::firstOrFail();
        $this->assertSame($abstract, $p->abstract);
        $this->assertSame('Jurnal Uji', $p->container_title);
        $this->assertSame(['A. Penulis'], $p->authors_json);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
