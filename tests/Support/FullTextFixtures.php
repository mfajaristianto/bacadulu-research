<?php

namespace Tests\Support;

use App\Models\ExternalFulltext;
use App\Models\ExternalPublication;
use App\Services\ExternalSources\Normalize;
use App\Services\FullText\FullTextService;
use App\Services\FullText\LicensePolicy;
use App\Services\FullText\UrlGuard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Bahan uji Full-Text: tanpa jaringan nyata, penyimpanan privat sementara, DNS palsu, PDF kecil buatan sendiri. */
trait FullTextFixtures
{
    /** @var array<string, list<string>> host => IP hasil resolusi palsu */
    protected array $dns = [];

    protected function bootFullText(): void
    {
        Cache::flush();
        FullTextService::forgetReady();
        Storage::fake('external_fulltext');
        config([
            'research.ingestion.pdftotext_binary' => '',          // deterministik: parser PHP
            'external-sources.fulltext.download.host_interval_ms' => 0,
            'external-sources.fulltext.download.min_free_disk_mb' => 1,
            'external-sources.fulltext.allowed_hosts' => [],
        ]);
        $this->dns = ['repo.example.org' => ['93.184.216.34'], 'cdn.example.org' => ['93.184.216.35'], 'mirror.example.net' => ['93.184.216.36']];
        $this->app->instance(UrlGuard::class, new UrlGuard(fn (string $h) => $this->dns[$h] ?? []));
    }

    protected function pub(array $o = []): ExternalPublication
    {
        $title = $o['title'] ?? 'Pelaporan keuangan dan nilai perusahaan';

        return ExternalPublication::create($o + [
            'uuid' => (string) Str::uuid(), 'doi' => null, 'title' => $title, 'title_hash' => Normalize::titleHash($title), 'checksum' => sha1($title.Str::random(6)),
            'authors_json' => ['A. Penulis'], 'publication_year' => 2022, 'abstract' => 'Abstrak yang cukup panjang tentang pelaporan keuangan dan nilai perusahaan.',
            'oa_status' => 'open', 'availability' => 'OPEN_ACCESS_LINK', 'publication_type' => 'article',
        ]);
    }

    /** Lokasi siap unduh (izin allow). */
    protected function loc(ExternalPublication $p, array $o = []): ExternalFulltext
    {
        $url = $o['url'] ?? 'https://repo.example.org/files/'.Str::random(6).'.pdf';

        return ExternalFulltext::create($o + [
            'publication_id' => $p->id, 'provider' => 'openalex', 'url' => $url, 'url_hash' => sha1($url), 'kind' => 'pdf',
            'license' => 'cc-by', 'license_code' => 'cc-by', 'version' => 'publishedVersion', 'license_scope' => 'article',
            'permission' => LicensePolicy::PERMISSION_ALLOW, 'permission_reason' => 'uji', 'state' => 'DOWNLOAD_ELIGIBLE',
        ]);
    }

    /** Artikel 3 halaman dengan bagian baku dan daftar pustaka. */
    protected function articlePdf(): string
    {
        return TinyPdf::build([
            "Pelaporan Keuangan dan Nilai Perusahaan\nAbstract\n".TinyPdf::prose(40, 'penelitian ini menguji pelaporan keuangan'),
            "Introduction\n".TinyPdf::prose(60, 'pelaporan keuangan meningkatkan nilai perusahaan')."\nMethodology\n".TinyPdf::prose(60, 'sampel perusahaan dianalisis dengan regresi'),
            "Results\n".TinyPdf::prose(50, 'hasil menunjukkan pengaruh positif signifikan')."\nConclusion\n".TinyPdf::prose(40, 'pelaporan berkualitas penting')."\nReferences\n".TinyPdf::prose(50, 'Smith J 2020 Journal of Accounting Research'),
        ]);
    }

    protected function fakePdf(string $url, string $body, array $headers = ['Content-Type' => 'application/pdf'], int $status = 200): void
    {
        Http::fake([$url => Http::response($body, $status, $headers)]);
    }

    protected function sent(): int
    {
        return Http::recorded()->filter(fn (array $p) => $p[0] instanceof Request)->count();
    }
}
