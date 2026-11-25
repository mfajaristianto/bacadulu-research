<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Keamanan migration draf full-text: hanya tabel baru, nama pengenal ≤ 64 karakter (batas MariaDB; SQLite tidak menangkapnya). */
class FullTextMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'database/migrations/2026_11_24_000001_create_external_fulltext_tables.php';

    public function test_migration_only_creates_two_new_tables_and_never_alters_existing_ones(): void
    {
        $src = (string) file_get_contents(base_path(self::FILE));

        preg_match_all("/Schema::create\('([a-z_]+)'/", $src, $m);
        $this->assertSame(['external_fulltexts', 'external_fulltext_chunks'], $m[1]);
        $this->assertStringNotContainsString('Schema::table(', $src);
        $this->assertStringNotContainsString('dropColumn', $src);
        $this->assertStringContainsString('DRAFT, MENUNGGU PERSETUJUAN', $src);
    }

    public function test_every_identifier_fits_the_mariadb_64_character_limit(): void
    {
        $src = (string) file_get_contents(base_path(self::FILE));

        preg_match_all("/,\s*'([a-z_]+)'\)/", $src, $explicit);
        $names = $explicit[1];

        // Nama kunci asing otomatis Laravel: {tabel}_{kolom}_foreign.
        foreach (['external_fulltexts' => ['publication_id', 'reviewed_by'], 'external_fulltext_chunks' => ['fulltext_id', 'publication_id']] as $table => $cols) {
            foreach ($cols as $c) {
                $names[] = "{$table}_{$c}_foreign";
            }
        }
        $this->assertNotEmpty($names);
        foreach ($names as $n) {
            $this->assertLessThanOrEqual(64, strlen($n), "pengenal terlalu panjang: {$n}");
        }

        foreach (['external_fulltexts', 'external_fulltext_chunks'] as $t) {
            $this->assertTrue(Schema::hasTable($t));
        }
        $this->assertTrue(Schema::hasColumns('external_fulltexts', ['publication_id', 'url_hash', 'state', 'permission', 'license_code', 'sha256', 'queue_token']));
    }

    public function test_deleting_a_publication_cascades_to_locations_and_chunks_but_not_the_other_way(): void
    {
        $p = (new class
        {
            use \Tests\Support\FullTextFixtures;

            public function make()
            {
                return [$this->pub()];
            }
        })->make()[0];
        $ft = \App\Models\ExternalFulltext::create(['publication_id' => $p->id, 'provider' => 'openalex', 'url' => 'https://repo.example.org/a.pdf', 'url_hash' => sha1('a'), 'kind' => 'pdf']);
        \App\Models\ExternalFulltextChunk::create(['fulltext_id' => $ft->id, 'publication_id' => $p->id, 'chunk_index' => 0, 'content_sha' => 'x', 'source_checksum' => 'y', 'content' => 'z']);

        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');
        $ft->delete();
        $this->assertDatabaseHas('external_publications', ['id' => $p->id]);
        $this->assertSame(0, \App\Models\ExternalFulltextChunk::count());
    }
}
