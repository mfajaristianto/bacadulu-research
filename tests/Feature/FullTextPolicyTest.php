<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ExternalFulltext;
use App\Models\ExternalPublicationSource;
use App\Models\User;
use App\Services\FullText\FullTextException;
use App\Services\FullText\FullTextService;
use App\Services\FullText\FullTextState as S;
use App\Services\FullText\LicensePolicy;
use App\Services\FullText\UrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FullTextFixtures;
use Tests\TestCase;

/** Gerbang lisensi (default-deny), pelindung SSRF, dan penemuan lokasi dari OpenAlex/Crossref/DOAJ/Pustaka. */
class FullTextPolicyTest extends TestCase
{
    use FullTextFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFullText();
    }

    // ---- lisensi ----------------------------------------------------------------------------------------------------------

    #[DataProvider('licenseStrings')]
    public function test_license_strings_are_normalised(?string $raw, ?string $url, ?string $code): void
    {
        $this->assertSame($code, LicensePolicy::normalize($raw, $url)['code']);
    }

    public static function licenseStrings(): array
    {
        return [
            ['cc-by', null, 'cc-by'], ['CC BY', null, 'cc-by'], ['CC BY 4.0', null, 'cc-by'], ['CC0', null, 'cc0'],
            ['CC BY-NC', null, 'cc-by-nc'], ['CC BY-NC-ND', null, 'cc-by-nc-nd'], ['cc-by-sa', null, 'cc-by-sa'],
            [null, 'https://creativecommons.org/licenses/by/4.0/', 'cc-by'],
            [null, 'http://creativecommons.org/licenses/by-nc-nd/3.0/', 'cc-by-nc-nd'],
            [null, 'https://creativecommons.org/publicdomain/zero/1.0/', 'cc0'],
            ['public-domain', null, 'public-domain'], ['publisher-specific-oa', null, 'publisher-specific-oa'],
            ['Publisher\'s own license', null, 'other'], ['all rights reserved', null, 'other'], [null, null, null], ['', '', null],
        ];
    }

    private function decide(array $o): array
    {
        return LicensePolicy::decide($o + ['kind' => 'pdf', 'license_code' => 'cc-by', 'version' => 'publishedVersion', 'license_scope' => 'article', 'oa' => true]);
    }

    public function test_only_verified_cc0_and_cc_by_files_are_automatically_eligible(): void
    {
        foreach (['cc0', 'cc-by'] as $code) {
            $d = $this->decide(['license_code' => $code]);
            $this->assertSame(['allow', S::ELIGIBLE], [$d['permission'], $d['state']], $code);
        }
        $this->assertSame('allow', $this->decide(['version' => 'acceptedVersion'])['permission']);
    }

    #[DataProvider('notAutomatic')]
    public function test_everything_else_is_default_deny(array $o, string $permission, string $state): void
    {
        $d = $this->decide($o);
        $this->assertSame([$permission, $state], [$d['permission'], $d['state']]);
        $this->assertNotSame('', $d['reason']);
    }

    public static function notAutomatic(): array
    {
        return [
            'OA tanpa lisensi terkonfirmasi' => [['license_code' => null], 'review', S::REVIEW_REQUIRED],
            'lisensi tak dikenal' => [['license_code' => 'other'], 'review', S::REVIEW_REQUIRED],
            'NC' => [['license_code' => 'cc-by-nc'], 'review', S::REVIEW_REQUIRED],
            'ND' => [['license_code' => 'cc-by-nd'], 'review', S::REVIEW_REQUIRED],
            'SA' => [['license_code' => 'cc-by-sa'], 'review', S::REVIEW_REQUIRED],
            'publisher-specific' => [['license_code' => 'publisher-specific-oa'], 'review', S::REVIEW_REQUIRED],
            'public domain mark' => [['license_code' => 'public-domain'], 'review', S::REVIEW_REQUIRED],
            'versi tidak diketahui' => [['version' => null], 'review', S::REVIEW_REQUIRED],
            'versi submitted' => [['version' => 'submittedVersion'], 'review', S::REVIEW_REQUIRED],
            'lisensi tingkat jurnal' => [['license_scope' => 'journal'], 'review', S::REVIEW_REQUIRED],
            'OA tidak terkonfirmasi' => [['oa' => null], 'review', S::REVIEW_REQUIRED],
            'tertutup' => [['oa' => false], 'deny', S::LINK_FOUND],
            'halaman arahan' => [['kind' => 'landing'], 'review', S::LINK_FOUND],
            'tautan TDM' => [['kind' => 'tdm'], 'review', S::LINK_FOUND],
        ];
    }

    public function test_open_access_flag_alone_never_grants_permission(): void
    {
        $d = LicensePolicy::decide(['kind' => 'pdf', 'oa' => true]);
        $this->assertSame(['review', S::REVIEW_REQUIRED], [$d['permission'], $d['state']]);
        $this->assertStringContainsString('Flag open access saja bukan izin', $d['reason']);
    }

    // ---- SSRF -------------------------------------------------------------------------------------------------------------

    #[DataProvider('blockedUrls')]
    public function test_ssrf_guard_blocks_dangerous_destinations(string $url, string $code): void
    {
        $this->dns['dual.example.org'] = ['93.184.216.34', '10.1.2.3'];
        $this->dns['v6.example.org'] = ['::1'];
        $this->dns['mapped.example.org'] = ['::ffff:127.0.0.1'];
        $this->dns['nat64.example.org'] = ['64:ff9b::7f00:1'];
        $this->dns['ula.example.org'] = ['fd12:3456::1'];
        $this->dns['ll.example.org'] = ['fe80::1'];
        $this->dns['cgnat.example.org'] = ['100.64.0.9'];
        $this->dns['meta.example.org'] = ['169.254.169.254'];
        $this->dns['lo.example.org'] = ['127.0.0.1'];
        $this->dns['sixtofour.example.org'] = ['2002:7f00:0001::1'];

        try {
            app(UrlGuard::class)->validate($url);
            $this->fail("harus ditolak: {$url}");
        } catch (FullTextException $e) {
            $this->assertSame($code, $e->errorCode, $url);
        }
    }

    public static function blockedUrls(): array
    {
        return [
            'http' => ['http://repo.example.org/a.pdf', 'bad_scheme'], 'file' => ['file:///etc/passwd', 'bad_scheme'], 'ftp' => ['ftp://repo.example.org/a', 'bad_scheme'],
            'gopher' => ['gopher://repo.example.org/', 'bad_scheme'], 'data' => ['data:text/plain,hi', 'bad_scheme'], 'javascript' => ['javascript:alert(1)', 'bad_scheme'],
            'kredensial' => ['https://user:pass@repo.example.org/a.pdf', 'bad_url'], 'port lain' => ['https://repo.example.org:8443/a.pdf', 'bad_port'],
            'loopback v4' => ['https://127.0.0.1/a', 'blocked_host'], 'loopback v6' => ['https://[::1]/a', 'blocked_host'], 'privat' => ['https://192.168.1.1/a', 'blocked_host'],
            'link-local' => ['https://169.254.169.254/latest/meta-data', 'blocked_host'], 'desimal' => ['https://2130706433/a', 'blocked_host'],
            'heks' => ['https://0x7f.0.0.1/a', 'blocked_host'], 'oktal' => ['https://0177.0.0.1/a', 'blocked_host'], 'localhost' => ['https://localhost/a', 'blocked_host'],
            'subdomain localhost' => ['https://app.localhost/a', 'blocked_host'], '.internal' => ['https://db.internal/a', 'blocked_host'], '.local' => ['https://printer.local/a', 'blocked_host'],
            'tanpa TLD' => ['https://intranet/a', 'blocked_host'], 'spasi' => ["https://repo.example.org/a b", 'bad_url'], 'kosong' => ['', 'bad_url'],
            'DNS campuran publik+privat' => ['https://dual.example.org/a.pdf', 'blocked_address'], 'DNS IPv6 loopback' => ['https://v6.example.org/a', 'blocked_address'],
            'IPv4-mapped' => ['https://mapped.example.org/a', 'blocked_address'], 'NAT64 ke loopback' => ['https://nat64.example.org/a', 'blocked_address'],
            'ULA IPv6' => ['https://ula.example.org/a', 'blocked_address'], 'link-local IPv6' => ['https://ll.example.org/a', 'blocked_address'],
            'CGNAT' => ['https://cgnat.example.org/a', 'blocked_address'], 'metadata via DNS' => ['https://meta.example.org/a', 'blocked_address'],
            'loopback via DNS' => ['https://lo.example.org/a', 'blocked_address'], '6to4 ke loopback' => ['https://sixtofour.example.org/a', 'blocked_address'],
            'DNS gagal' => ['https://unknown.example.org/a', 'dns_failed'],
        ];
    }

    public function test_public_destinations_pass_and_the_connection_is_pinned_to_the_validated_ip(): void
    {
        $r = app(UrlGuard::class)->validate('https://repo.example.org/a.pdf');
        $this->assertSame('93.184.216.34', $r['ip']);
        $this->assertSame('repo.example.org', $r['host']);

        $this->dns['dual.example.org'] = ['2606:2800:220:1::1', '93.184.216.40'];
        $this->assertSame('93.184.216.40', app(UrlGuard::class)->validate('https://dual.example.org/a')['ip'], 'IPv4 diutamakan');
    }

    public function test_optional_host_allowlist_restricts_downloads(): void
    {
        config(['external-sources.fulltext.allowed_hosts' => ['example.org']]);
        $this->assertSame('repo.example.org', app(UrlGuard::class)->validate('https://repo.example.org/a')['host']);

        $this->dns['evil.example.net'] = ['93.184.216.50'];
        $this->expectException(FullTextException::class);
        app(UrlGuard::class)->validate('https://evil.example.net/a');
    }

    #[DataProvider('ips')]
    public function test_ip_classification(string $ip, bool $public): void
    {
        $this->assertSame($public, UrlGuard::isPublicIp($ip), $ip);
    }

    public static function ips(): array
    {
        return [['8.8.8.8', true], ['93.184.216.34', true], ['2606:4700::1111', true], ['10.0.0.1', false], ['172.16.0.1', false], ['172.31.255.255', false], ['172.32.0.1', true],
            ['192.168.0.1', false], ['127.0.0.2', false], ['0.0.0.0', false], ['169.254.1.1', false], ['100.127.255.255', false], ['224.0.0.1', false], ['255.255.255.255', false],
            ['::', false], ['::1', false], ['fc00::1', false], ['fe80::1', false], ['ff02::1', false], ['::ffff:10.0.0.1', false], ['::ffff:8.8.8.8', true], ['not-an-ip', false]];
    }

    // ---- penemuan lokasi --------------------------------------------------------------------------------------------------

    private function openAlexBody(array $locations, ?array $best = null): array
    {
        return ['id' => 'https://openalex.org/W100', 'doi' => 'https://doi.org/10.5555/loc.1', 'open_access' => ['is_oa' => true], 'best_oa_location' => $best, 'locations' => $locations];
    }

    public function test_openalex_locations_are_found_with_license_version_and_decision(): void
    {
        $p = $this->pub(['doi' => '10.5555/loc.1']);
        ExternalPublicationSource::create(['publication_id' => $p->id, 'provider' => 'openalex', 'external_id' => 'W100']);

        $good = ['is_oa' => true, 'landing_page_url' => 'https://repo.example.org/abs/1', 'pdf_url' => 'https://repo.example.org/pdf/1.pdf', 'license' => 'cc-by', 'version' => 'publishedVersion', 'source' => ['display_name' => 'Repo', 'type' => 'repository']];
        $nc = ['is_oa' => true, 'landing_page_url' => 'https://cdn.example.org/a', 'pdf_url' => 'https://cdn.example.org/a.pdf', 'license' => 'cc-by-nc', 'version' => 'acceptedVersion'];
        $nolic = ['is_oa' => true, 'landing_page_url' => 'https://mirror.example.net/a', 'pdf_url' => 'https://mirror.example.net/a.pdf', 'license' => null, 'version' => 'publishedVersion'];
        $closed = ['is_oa' => false, 'landing_page_url' => 'https://publisher.example.com/paywalled', 'pdf_url' => null];
        Http::fake(['api.openalex.org/*' => Http::response($this->openAlexBody([$good, $nc, $nolic, $closed], $good))]);

        $r = app(FullTextService::class)->check($p, true);

        $by = collect($r['locations'])->keyBy('url');
        $this->assertCount(3, $by, 'lokasi tertutup tanpa PDF bukan "teks lengkap"');
        $this->assertSame([S::ELIGIBLE, 'allow', 'cc-by', 'publishedVersion'], [$by['https://repo.example.org/pdf/1.pdf']->state, $by['https://repo.example.org/pdf/1.pdf']->permission, $by['https://repo.example.org/pdf/1.pdf']->license_code, $by['https://repo.example.org/pdf/1.pdf']->version]);
        $this->assertSame([S::REVIEW_REQUIRED, 'cc-by-nc'], [$by['https://cdn.example.org/a.pdf']->state, $by['https://cdn.example.org/a.pdf']->license_code]);
        $this->assertSame(S::REVIEW_REQUIRED, $by['https://mirror.example.net/a.pdf']->state);
        $this->assertStringContainsString('Lisensi tidak diketahui', $by['https://mirror.example.net/a.pdf']->permission_reason);
        $this->assertStringContainsString('10.5555/loc.1', $by['https://repo.example.org/pdf/1.pdf']->attribution);
        $this->assertTrue($by['https://repo.example.org/pdf/1.pdf']->evidence_json['is_best']);

        // Pemeriksaan ulang idempoten: tidak menggandakan lokasi.
        app(FullTextService::class)->check($p, true);
        $this->assertSame(3, ExternalFulltext::count());
    }

    public function test_crossref_links_are_never_treated_as_permission_to_download(): void
    {
        $p = $this->pub(['doi' => '10.5555/cr.1', 'oa_status' => 'unknown', 'fulltext_url' => null]);
        Http::fake(['api.crossref.org/*' => Http::response(['message' => [
            'DOI' => '10.5555/cr.1',
            'license' => [['URL' => 'https://creativecommons.org/licenses/by/4.0/', 'content-version' => 'vor', 'delay-in-days' => 0], ['URL' => 'https://publisher.example.com/tdm', 'content-version' => 'tdm', 'delay-in-days' => 0]],
            'link' => [
                ['URL' => 'https://repo.example.org/tdm/cr1.pdf', 'content-type' => 'application/pdf', 'content-version' => 'vor', 'intended-application' => 'text-mining'],
                ['URL' => 'https://repo.example.org/vor/cr1.pdf', 'content-type' => 'application/pdf', 'content-version' => 'vor', 'intended-application' => 'syndication'],
            ],
        ]])]);

        $r = app(FullTextService::class)->check($p, true);
        $by = collect($r['locations'])->keyBy('url');

        $tdm = $by['https://repo.example.org/tdm/cr1.pdf'];
        $this->assertSame(['tdm', 'review', S::LINK_FOUND], [$tdm->kind, $tdm->permission, $tdm->state]);

        // Bahkan tautan PDF dengan lisensi CC BY di Crossref: status OA tidak terkonfirmasi, jadi tetap tinjauan manual.
        $pdf = $by['https://repo.example.org/vor/cr1.pdf'];
        $this->assertSame(['pdf', 'cc-by', 'review', S::REVIEW_REQUIRED], [$pdf->kind, $pdf->license_code, $pdf->permission, $pdf->state]);
        $this->assertSame([], ExternalFulltext::query()->where('state', S::ELIGIBLE)->pluck('id')->all());
    }

    private function sameUrlFakes(string $crossrefLicenseUrl): void
    {
        Http::fake([
            'api.openalex.org/*' => Http::response($this->openAlexBody([['is_oa' => true, 'landing_page_url' => 'https://repo.example.org/abs/9', 'pdf_url' => 'https://repo.example.org/pdf/9.pdf', 'license' => 'cc-by', 'version' => 'publishedVersion']])),
            'api.crossref.org/*' => Http::response(['message' => [
                'license' => [['URL' => $crossrefLicenseUrl, 'content-version' => 'vor', 'delay-in-days' => 0]],
                'link' => [['URL' => 'https://repo.example.org/pdf/9.pdf', 'content-type' => 'application/pdf', 'content-version' => 'vor', 'intended-application' => 'text-mining']],
            ]]),
        ]);
    }

    public function test_same_url_from_two_providers_is_one_location_and_agreement_is_only_corroboration(): void
    {
        $p = $this->pub(['doi' => '10.5555/same.1', 'fulltext_url' => null]);
        ExternalPublicationSource::create(['publication_id' => $p->id, 'provider' => 'openalex', 'external_id' => 'W9']);
        $this->sameUrlFakes('https://creativecommons.org/licenses/by/4.0');

        $svc = app(FullTextService::class);
        $svc->check($p, true);
        $svc->check($p, true); // diulang: hasil sama (tidak bergantung urutan atau jumlah pemeriksaan)

        $this->assertSame(1, ExternalFulltext::count());
        $ft = ExternalFulltext::first();
        $this->assertSame(['pdf', S::ELIGIBLE, 'openalex', 'cc-by'], [$ft->kind, $ft->state, $ft->provider, $ft->license_code]);
        $this->assertSame('crossref', $ft->evidence_json['also_described_by'][0]['provider']);
        $this->assertTrue($ft->evidence_json['also_described_by'][0]['corroborates']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.crossref.org') && ! str_contains($r->url(), 'select='));
    }

    public function test_direct_provider_evidence_outranks_stored_metadata_for_the_same_url(): void
    {
        // Data tersimpan (tanpa versi) dan OpenAlex (versi terbit) menunjuk URL yang sama: yang dipakai bukti OpenAlex.
        $p = $this->pub(['doi' => '10.5555/same.3', 'fulltext_url' => 'https://repo.example.org/pdf/9.pdf', 'license' => 'cc-by']);
        ExternalPublicationSource::create(['publication_id' => $p->id, 'provider' => 'openalex', 'external_id' => 'W9']);
        Http::fake([
            'api.openalex.org/*' => Http::response($this->openAlexBody([['is_oa' => true, 'landing_page_url' => 'https://repo.example.org/abs/9', 'pdf_url' => 'https://repo.example.org/pdf/9.pdf', 'license' => 'cc-by', 'version' => 'publishedVersion']])),
            'api.crossref.org/*' => Http::response(['message' => []]),
        ]);

        app(FullTextService::class)->check($p, false);
        $this->assertSame(S::REVIEW_REQUIRED, ExternalFulltext::first()->state, 'hanya data tersimpan: versi tidak diketahui');

        app(FullTextService::class)->check($p, true);
        $ft = ExternalFulltext::firstOrFail();
        $this->assertSame(1, ExternalFulltext::count());
        $this->assertSame([S::ELIGIBLE, 'publishedVersion'], [$ft->state, $ft->version]);
    }

    public function test_conflicting_licenses_between_providers_never_auto_allow(): void
    {
        $p = $this->pub(['doi' => '10.5555/same.2', 'fulltext_url' => null]);
        ExternalPublicationSource::create(['publication_id' => $p->id, 'provider' => 'openalex', 'external_id' => 'W9']);
        $this->sameUrlFakes('https://creativecommons.org/licenses/by-nc/4.0');

        app(FullTextService::class)->check($p, true);

        $ft = ExternalFulltext::firstOrFail();
        $this->assertSame([S::REVIEW_REQUIRED, 'review'], [$ft->state, $ft->permission]);
        $this->assertTrue($ft->evidence_json['also_described_by'][0]['conflict']);
    }

    public function test_stored_doaj_metadata_yields_link_only_or_review_never_auto_download(): void
    {
        $landing = $this->pub(['fulltext_url' => 'https://journal.example.org/article/view/12', 'license' => 'CC BY']);
        ExternalPublicationSource::create(['publication_id' => $landing->id, 'provider' => 'doaj', 'external_id' => 'd1']);
        $pdf = $this->pub(['title' => 'Artikel DOAJ lain', 'fulltext_url' => 'https://journal.example.org/files/12.pdf', 'license' => 'CC BY']);
        ExternalPublicationSource::create(['publication_id' => $pdf->id, 'provider' => 'doaj', 'external_id' => 'd2']);

        $svc = app(FullTextService::class);
        $a = $svc->check($landing)['locations'][0];
        $b = $svc->check($pdf)['locations'][0];

        $this->assertSame(['landing', S::LINK_FOUND], [$a->kind, $a->state]);
        $this->assertSame(['pdf', 'journal', S::REVIEW_REQUIRED], [$b->kind, $b->license_scope, $b->state], 'lisensi jurnal bukan bukti lisensi artikel');
    }

    public function test_publication_without_any_link_creates_no_location(): void
    {
        $p = $this->pub(['fulltext_url' => null, 'oa_status' => 'closed']);
        $this->assertSame([], app(FullTextService::class)->check($p)['locations']);
        $this->assertSame(0, ExternalFulltext::count());
    }

    public function test_admin_review_is_kept_when_the_location_is_rechecked(): void
    {
        $p = $this->pub(['fulltext_url' => 'https://journal.example.org/files/9.pdf', 'license' => null]);
        ExternalPublicationSource::create(['publication_id' => $p->id, 'provider' => 'openalex', 'external_id' => 'W9']);
        $svc = app(FullTextService::class);
        $admin = User::factory()->create()->id;
        $ft = $svc->check($p)['locations'][0];
        $this->assertSame(S::REVIEW_REQUIRED, $ft->state);

        $this->assertFalse($svc->approve($ft, $admin, 'singkat'), 'catatan bukti wajib cukup rinci');
        $this->assertTrue($svc->approve($ft, $admin, 'Lisensi CC BY tercantum di halaman 1 PDF; diperiksa manual.'));
        $this->assertSame([S::ELIGIBLE, 'allow'], [$ft->refresh()->state, $ft->permission]);

        $svc->check($p); // data yang sama diperiksa ulang
        $this->assertSame([S::ELIGIBLE, 'allow'], [$ft->refresh()->state, $ft->permission], 'keputusan admin tidak ditimpa');
        $this->assertNotNull($ft->reviewed_at);
    }

    public function test_landing_and_tdm_locations_can_never_be_approved_for_download(): void
    {
        $p = $this->pub();
        $landing = $this->loc($p, ['kind' => 'landing', 'permission' => 'review', 'state' => S::LINK_FOUND]);
        $this->assertFalse(app(FullTextService::class)->approve($landing, 1, 'Mencoba menyetujui halaman arahan.'));
        $this->assertSame(S::LINK_FOUND, $landing->refresh()->state);
    }

    public function test_internal_library_document_with_same_doi_prevents_a_duplicate_copy(): void
    {
        $cat = Category::create(['name' => 'Akuntansi', 'slug' => 'akt', 'status' => 'active']);
        $user = User::factory()->create();
        $mk = fn (array $o) => tap(Document::create($o + ['uuid' => (string) Str::uuid(), 'title' => 'Dok Pustaka', 'category_id' => $cat->id, 'document_type' => 'pdf', 'status' => 'published', 'visibility' => 'public', 'uploaded_by' => $user->id, 'published_at' => now()]),
            fn ($d) => DocumentChunk::create(['document_id' => $d->id, 'chunk_index' => 0, 'content' => 'isi', 'metadata_json' => []]));

        $mk(['doi' => '10.5555/int.1']);
        $mk(['doi' => '10.5555/priv.1', 'visibility' => 'private']);

        $pub = $this->pub(['doi' => '10.5555/int.1', 'fulltext_url' => 'https://repo.example.org/x.pdf']);
        $priv = $this->pub(['title' => 'Artikel lain', 'doi' => '10.5555/priv.1', 'fulltext_url' => 'https://repo.example.org/y.pdf']);

        $r = app(FullTextService::class)->check($pub);
        $this->assertNotNull($r['internal_document_id']);
        $this->assertSame([], $r['locations'], 'tidak ada salinan ganda dari Pustaka');

        $r2 = app(FullTextService::class)->check($priv);
        $this->assertNull($r2['internal_document_id'], 'dokumen privat tidak dianggap tersedia');
        $this->assertCount(1, $r2['locations']);
    }

    public function test_provider_failures_are_reported_without_breaking_the_check(): void
    {
        $p = $this->pub(['doi' => '10.5555/err.1', 'fulltext_url' => 'https://journal.example.org/f/1.pdf', 'license' => null]);
        Http::fake(['*' => Http::response('', 500)]);

        $r = app(FullTextService::class)->check($p, true);
        $this->assertCount(1, $r['locations'], 'lokasi tersimpan tetap dicatat');
        $this->assertCount(2, $r['errors']);
        $this->assertStringNotContainsString('api_key', json_encode($r['errors']));
    }
}
