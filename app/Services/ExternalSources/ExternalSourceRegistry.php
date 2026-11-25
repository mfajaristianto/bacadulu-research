<?php

namespace App\Services\ExternalSources;

use App\Services\ExternalSources\Adapters\CrossrefAdapter;
use App\Services\ExternalSources\Adapters\DoajAdapter;
use App\Services\ExternalSources\Adapters\ExternalSourceAdapter;
use App\Services\ExternalSources\Adapters\OpenAlexAdapter;
use RuntimeException;

/*
 * Registri konektor. Adapter yang benar-benar ada = `implemented`. Sisanya hanya katalog jujur: tampil dengan status
 * dan alasan, tanpa tombol Sync. Status memakai: SUPPORTED, REQUIRES_CONFIGURATION, METADATA_ONLY,
 * PENDING_VERIFICATION, NOT_SUPPORTED. Menambah konektor baru = tulis adapter + daftarkan di ADAPTERS.
 */
class ExternalSourceRegistry
{
    private const ADAPTERS = [OpenAlexAdapter::class, CrossrefAdapter::class, DoajAdapter::class];

    /** Katalog penyedia yang belum punya adapter. Belum diverifikasi di iterasi ini = PENDING_VERIFICATION. */
    private const CATALOG = [
        ['code' => 'openaire', 'name' => 'OpenAIRE', 'kinds' => 'Artikel, dataset, perangkat lunak', 'priority' => 'B', 'status' => 'PENDING_VERIFICATION', 'note' => 'API Graph ada, tetapi syarat penggunaan dan filter kategori belum diverifikasi.', 'docs_url' => 'https://graph.openaire.eu/docs/'],
        ['code' => 'datacite', 'name' => 'DataCite', 'kinds' => 'Dataset dan DOI riset', 'priority' => 'B', 'status' => 'PENDING_VERIFICATION', 'note' => 'REST API publik ada; belum diverifikasi dan diimplementasikan.', 'docs_url' => 'https://support.datacite.org/docs/api'],
        ['code' => 'europepmc', 'name' => 'Europe PMC', 'kinds' => 'Biomedis', 'priority' => 'B', 'status' => 'PENDING_VERIFICATION', 'note' => 'REST API publik ada; relevansi ke kategori BacaDulu perlu dinilai dulu.', 'docs_url' => 'https://europepmc.org/RestfulWebService'],
        ['code' => 'pubmed', 'name' => 'PubMed / NCBI', 'kinds' => 'Biomedis', 'priority' => 'B', 'status' => 'PENDING_VERIFICATION', 'note' => 'E-utilities membutuhkan pengenal alat/kontak dan batas laju; belum diverifikasi.', 'docs_url' => 'https://www.ncbi.nlm.nih.gov/books/NBK25497/'],
        ['code' => 'semanticscholar', 'name' => 'Semantic Scholar', 'kinds' => 'Artikel', 'priority' => 'C', 'status' => 'PENDING_VERIFICATION', 'note' => 'Umumnya memerlukan API key; syarat penggunaan belum diverifikasi.', 'docs_url' => 'https://api.semanticscholar.org/'],
        ['code' => 'googlebooks', 'name' => 'Google Books', 'kinds' => 'Buku', 'priority' => 'C', 'status' => 'PENDING_VERIFICATION', 'note' => 'Syarat penyimpanan ulang metadata belum diverifikasi.', 'docs_url' => 'https://developers.google.com/books'],
        ['code' => 'openlibrary', 'name' => 'Open Library', 'kinds' => 'Buku', 'priority' => 'C', 'status' => 'PENDING_VERIFICATION', 'note' => 'API untuk pencarian terbatas, bukan panen massal; bulk lewat data dump resmi.', 'docs_url' => 'https://openlibrary.org/developers/api'],
        ['code' => 'arxiv', 'name' => 'arXiv', 'kinds' => 'Pracetak', 'priority' => 'C', 'status' => 'PENDING_VERIFICATION', 'note' => 'API ada dengan aturan laju ketat; belum diverifikasi.', 'docs_url' => 'https://info.arxiv.org/help/api/index.html'],
        ['code' => 'onesearch', 'name' => 'Indonesia OneSearch (Perpusnas)', 'kinds' => 'Katalog perpustakaan Indonesia', 'priority' => 'ID', 'status' => 'PENDING_VERIFICATION', 'note' => 'Belum ada dokumentasi API baca publik yang terverifikasi. Kemungkinan perlu izin atau kerja sama dengan Perpusnas.', 'docs_url' => null],
        ['code' => 'garuda', 'name' => 'GARUDA (Kemdikbud)', 'kinds' => 'Jurnal Indonesia', 'priority' => 'ID', 'status' => 'PENDING_VERIFICATION', 'note' => 'Belum ada jalur pengambilan metadata keluar resmi yang terverifikasi. Jangan scraping; ajukan izin ke pengelola.', 'docs_url' => null],
        ['code' => 'oai-pmh', 'name' => 'OJS & repositori kampus (OAI-PMH)', 'kinds' => 'Jurnal dan repositori Indonesia', 'priority' => 'ID', 'status' => 'REQUIRES_CONFIGURATION', 'note' => 'Standar OAI-PMH 2.0 cocok, tetapi butuh endpoint per jurnal/repositori yang divalidasi admin. Adapter belum dibangun.', 'docs_url' => 'https://www.openarchives.org/OAI/openarchivesprotocol.html'],
        ['code' => 'bacadulu-ojs', 'name' => 'BacaDulu Publisher / OJS', 'kinds' => 'Jurnal BacaDulu', 'priority' => 'ID', 'status' => 'PENDING_VERIFICATION', 'note' => 'Sudah ada modul Impor Sumber untuk PDF. Endpoint OAI-PMH belum terkonfirmasi.', 'docs_url' => null],
    ];

    /** @return array<string, ExternalSourceAdapter> */
    public function adapters(): array
    {
        $out = [];
        foreach (self::ADAPTERS as $class) {
            $adapter = app($class);
            $out[$adapter->code()] = $adapter;
        }

        return $out;
    }

    public function adapter(string $code): ExternalSourceAdapter
    {
        return $this->adapters()[$code] ?? throw new RuntimeException("Konektor [{$code}] belum tersedia.");
    }

    public function has(string $code): bool
    {
        return isset($this->adapters()[$code]);
    }

    /** Daftar gabungan untuk direktori: adapter nyata lebih dulu, lalu katalog. */
    public function directory(): array
    {
        $rows = [];
        foreach ($this->adapters() as $code => $adapter) {
            $rows[] = ['code' => $code, 'implemented' => true, 'status' => 'SUPPORTED', 'priority' => 'A', 'note' => null] + $adapter->info();
        }
        foreach (self::CATALOG as $entry) {
            $rows[] = $entry + ['implemented' => false, 'key' => 'unknown', 'filters' => [], 'types' => [], 'host' => null];
        }

        return $rows;
    }
}
