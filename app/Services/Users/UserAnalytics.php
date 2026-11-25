<?php

namespace App\Services\Users;

use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\DocumentGeneration;
use App\Models\User;
use App\Models\UserLoginEvent;
use App\Services\Audit\AuditEvents;
use App\Services\Audit\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/*
 * Analitik dan direktori Pengguna. Hanya MEMBACA tabel yang sudah ada (users, ai_runs, ai_tools, document_generations, conversations, audit_logs);
 * tidak ada migration, tidak ada pelacakan baru. Semua agregat dihitung di database; direktori memakai paginasi sisi server.
 *
 * Yang TIDAK tersedia di data dan karena itu tidak ditampilkan: status online, last seen, frekuensi login, durasi sesi, perangkat, negara, retensi.
 * "last_login_at" hanya menyimpan login TERAKHIR, jadi tidak dipakai untuk mengklaim "pengguna aktif".
 */
class UserAnalytics
{
    public const PER_PAGE = 15;

    public const TZ = 'Asia/Jakarta';

    private const OFFSET_HOURS = 7; // WIB tidak memakai DST; created_at disimpan UTC

    public const SORTS = ['email' => 'Email A–Z', 'terbaru' => 'Terbaru bergabung', 'terlama' => 'Terlama bergabung', 'nama' => 'Nama A–Z', 'riset' => 'Riset terbanyak', 'login' => 'Login terakhir'];

    public function __construct(private readonly AuditTrail $trail) {}

    /*
    |--------------------------------------------------------------------------
    | Filter
    |--------------------------------------------------------------------------
    */

    /** @return array{f: array<string, mixed>, notice: ?string} */
    public function filters(Request $request): array
    {
        // Periode memakai aturan yang sama dengan Log Aktivitas (WIB, rentang kustom divalidasi).
        ['f' => $p, 'notice' => $notice] = $this->trail->filters(Request::create('/', 'GET', Arr::only($request->query(), ['periode', 'dari', 'sampai'])));

        $in = Arr::except($request->query(), ['periode', 'dari', 'sampai']);
        $v = Validator::make($in, [
            'q' => ['nullable', 'string', 'max:160'],
            'peran' => ['nullable', 'in:admin,pengguna'],
            'status' => ['nullable', 'in:active,suspended'],
            'verifikasi' => ['nullable', 'in:ya,belum'],
            'daftar' => ['nullable', 'in:periode'],
            'riset' => ['nullable', 'in:pernah,belum'],
            'urut' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))],
            'page' => ['nullable', 'integer', 'min:1'],
            'pengguna' => ['nullable', 'integer', 'min:1'],
        ]);

        $clean = collect($in)->except(array_keys($v->errors()->toArray()))->all();

        if ($v->errors()->isNotEmpty()) {
            $notice ??= 'Sebagian filter tidak valid dan diabaikan.';
        }

        return ['notice' => $notice, 'f' => [
            'periode' => $p['periode'], 'dari' => $p['dari'], 'sampai' => $p['sampai'],
            'q' => trim((string) ($clean['q'] ?? '')), 'peran' => $clean['peran'] ?? null, 'status' => $clean['status'] ?? null,
            'verifikasi' => $clean['verifikasi'] ?? null, 'daftar' => $clean['daftar'] ?? null, 'riset' => $clean['riset'] ?? null,
            'urut' => $clean['urut'] ?? 'email', 'pengguna' => isset($clean['pengguna']) ? (int) $clean['pengguna'] : null,
        ]];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    public function range(array $f): array
    {
        return $this->trail->range($f);
    }

    public static function adminEmail(): string
    {
        return strtolower(trim((string) config('research.admin_email')));
    }

    /** Akun yang dipakai masuk ke panel admin (ADMIN_EMAIL). Peran "admin" di sini bukan kolom database: ia diturunkan dari konfigurasi. */
    public static function isAdminAccount(User $user): bool
    {
        return self::adminEmail() !== '' && strtolower($user->email) === self::adminEmail();
    }

    private function utc(CarbonImmutable $d): string
    {
        return $d->utc()->format('Y-m-d H:i:s');
    }

    private function bucketSql(string $gran, string $column = 'created_at'): string
    {
        $fmt = $gran === 'hour' ? '%Y-%m-%d %H' : '%Y-%m-%d';

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('{$fmt}', {$column}, '+".self::OFFSET_HOURS." hours')"
            : "DATE_FORMAT(DATE_ADD({$column}, INTERVAL ".self::OFFSET_HOURS." HOUR), '{$fmt}')";
    }

    /*
    |--------------------------------------------------------------------------
    | KPI
    |--------------------------------------------------------------------------
    */

    /** @return array<string, int> */
    public function kpis(array $f): array
    {
        [$from, $to] = $this->range($f);
        $len = $to->getTimestamp() - $from->getTimestamp() + 1;
        $prevFrom = $from->subSeconds($len);
        $between = fn (Builder $q, CarbonImmutable $a, CarbonImmutable $b) => $q->whereBetween('created_at', [$this->utc($a), $this->utc($b)]);

        $runs = AiRun::query()->whereNotNull('user_id');
        $period = $between(AiRun::query()->whereNotNull('user_id'), $from, $to);

        return [
            'total' => User::query()->count(),
            'new' => $between(User::query(), $from, $to)->count(),
            'newPrev' => $between(User::query(), $prevFrom, $from->subSecond())->count(),
            'researchersAll' => (clone $runs)->distinct()->count('user_id'),
            'runsAll' => (clone $runs)->count(),
            'runsPeriod' => (clone $period)->count(),
            'researchersPeriod' => (clone $period)->distinct()->count('user_id'),
            'verified' => User::query()->whereNotNull('email_verified_at')->count(),
            'suspended' => User::query()->where('status', 'suspended')->count(),
            'loggedIn' => User::query()->whereBetween('last_login_at', [$this->utc($from), $this->utc($to)])->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pertumbuhan
    |--------------------------------------------------------------------------
    */

    /** @return array{series: array<string, array<string, mixed>>, labels: list<string>, buckets: list<string>, gran: string, sparse: bool} */
    public function growth(array $f): array
    {
        [$from, $to, $gran] = $this->range($f);
        $weekly = $gran === 'day' && $from->diffInDays($to) > 62;
        $keyOf = fn (string $b) => $weekly ? CarbonImmutable::createFromFormat('Y-m-d', $b, self::TZ)->startOfWeek()->format('Y-m-d') : $b;
        $window = [$this->utc($from), $this->utc($to)];

        $buckets = [];

        for ($c = $weekly ? $from->startOfWeek() : $from; $c->lte($to); $c = $gran === 'hour' ? $c->addHour() : ($weekly ? $c->addWeek() : $c->addDay())) {
            $buckets[$gran === 'hour' ? $c->format('Y-m-d H') : $c->format('Y-m-d')] = 0;
        }

        $new = $buckets;
        $res = $buckets;
        $sql = $this->bucketSql($gran === 'hour' ? 'hour' : 'day');

        foreach (User::query()->whereBetween('created_at', $window)->toBase()->selectRaw("{$sql} as bucket, count(*) as n")->groupBy(DB::raw($sql))->get() as $row) {
            $k = $keyOf($row->bucket);
            $new[$k] = ($new[$k] ?? 0) + (int) $row->n;
        }

        $runs = AiRun::query()->whereNotNull('user_id')->whereBetween('created_at', $window)->toBase();

        if ($weekly) {
            // Pengguna unik per minggu tidak bisa dijumlahkan dari hitungan harian; ambil pasangan (hari, pengguna) lalu kelompokkan di PHP.
            $seen = [];

            foreach ($runs->selectRaw("{$sql} as bucket, user_id")->groupBy(DB::raw($sql), 'user_id')->get() as $row) {
                $seen[$keyOf($row->bucket)][$row->user_id] = true;
            }

            foreach ($seen as $k => $users) {
                $res[$k] = count($users);
            }
        } else {
            foreach ($runs->selectRaw("{$sql} as bucket, count(distinct user_id) as n")->groupBy(DB::raw($sql))->get() as $row) {
                $res[$row->bucket] = (int) $row->n;
            }
        }

        $running = User::query()->where('created_at', '<', $window[0])->count();
        $cum = array_map(function (int $n) use (&$running): int {
            $running += $n;

            return $running;
        }, $new);

        $series = [
            'baru' => ['label' => 'Pengguna baru', 'unit' => 'pengguna baru', 'points' => array_values($new)],
            'kumulatif' => ['label' => 'Total kumulatif', 'unit' => 'pengguna terdaftar', 'points' => array_values($cum)],
            'riset' => ['label' => 'Pengguna yang riset', 'unit' => 'pengguna melakukan riset', 'points' => array_values($res)],
        ];

        foreach ($series as $key => $s) {
            $series[$key]['total'] = $key === 'kumulatif' ? (end($s['points']) ?: 0) : array_sum($s['points']);
            $series[$key]['max'] = $s['points'] ? max($s['points']) : 0;
        }

        $activeBuckets = count(array_filter($new, fn ($n) => $n > 0));

        return [
            'series' => $series,
            'buckets' => array_keys($buckets),
            'labels' => array_map(fn ($b) => $this->label($b, $gran, $weekly), array_keys($buckets)),
            'gran' => $weekly ? 'week' : $gran,
            // Data terlalu sedikit untuk disebut "tren": tampilkan titiknya apa adanya dan beri catatan.
            'sparse' => array_sum($new) < 3 || $activeBuckets < 2,
        ];
    }

    private function label(string $bucket, string $gran, bool $weekly): string
    {
        if ($gran === 'hour') {
            return CarbonImmutable::createFromFormat('Y-m-d H', $bucket, self::TZ)->format('H.00');
        }

        $d = CarbonImmutable::createFromFormat('Y-m-d', $bucket, self::TZ)->locale('id');

        return $weekly ? 'minggu '.$d->translatedFormat('j M') : $d->translatedFormat('j M');
    }

    /*
    |--------------------------------------------------------------------------
    | Komposisi
    |--------------------------------------------------------------------------
    */

    /** Satu dimensi: verifikasi email. @return list<array{key: string, label: string, n: int, pct: float}> */
    public function composition(): array
    {
        $row = User::query()->toBase()->selectRaw('count(*) as total, sum(case when email_verified_at is not null then 1 else 0 end) as verified')->first();
        $total = (int) $row->total;
        $verified = (int) $row->verified;
        $out = [];

        foreach ([['ya', 'Terverifikasi', $verified], ['belum', 'Belum terverifikasi', $total - $verified]] as [$key, $label, $n]) {
            $out[] = ['key' => $key, 'label' => $label, 'n' => $n, 'pct' => $total > 0 ? round($n / $total * 100, 1) : 0.0];
        }

        return $out;
    }

    /*
    |--------------------------------------------------------------------------
    | Aktivitas riset
    |--------------------------------------------------------------------------
    */

    /** @return array{tools: list<array<string, mixed>>, runs: int, failed: int, mock: int, intensity: list<array<string, mixed>>} */
    public function research(array $f, array $k): array
    {
        [$from, $to] = $this->range($f);
        $window = [$this->utc($from), $this->utc($to)];

        $sums = AiRun::query()->whereNotNull('user_id')->whereBetween('created_at', $window)->toBase()
            ->selectRaw("count(*) as runs, sum(case when status = 'failed' then 1 else 0 end) as failed, sum(case when lower(model_provider) = 'mock' then 1 else 0 end) as mock")->first();

        $tools = AiRun::query()->whereNotNull('ai_runs.user_id')->whereBetween('ai_runs.created_at', $window)->toBase()
            ->join('ai_tools', 'ai_tools.id', '=', 'ai_runs.ai_tool_id')
            ->selectRaw('ai_tools.id as id, ai_tools.name as name, count(*) as n')
            ->groupBy('ai_tools.id', 'ai_tools.name')->orderByDesc('n')->orderBy('ai_tools.name')->limit(7)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'n' => (int) $r->n, 'pct' => $sums->runs > 0 ? round($r->n / $sums->runs * 100, 1) : 0.0])->all();

        $bands = DB::query()->fromSub(AiRun::query()->whereNotNull('user_id')->toBase()->selectRaw('user_id, count(*) as n')->groupBy('user_id'), 't')
            ->selectRaw('sum(case when n = 1 then 1 else 0 end) as one, sum(case when n between 2 and 5 then 1 else 0 end) as few, sum(case when n >= 6 then 1 else 0 end) as many')->first();

        $intensity = [
            ['label' => 'Belum pernah riset', 'n' => max(0, $k['total'] - $k['researchersAll'])],
            ['label' => '1 riset', 'n' => (int) ($bands->one ?? 0)],
            ['label' => '2–5 riset', 'n' => (int) ($bands->few ?? 0)],
            ['label' => '6 riset atau lebih', 'n' => (int) ($bands->many ?? 0)],
        ];

        return ['tools' => $tools, 'runs' => (int) $sums->runs, 'failed' => (int) $sums->failed, 'mock' => (int) $sums->mock, 'intensity' => $intensity];
    }

    /*
    |--------------------------------------------------------------------------
    | Insight (deterministik, deskriptif: tidak menyimpulkan sebab-akibat)
    |--------------------------------------------------------------------------
    */

    /** @return list<array{label: string, value: string, note: string, pct: ?float}> */
    public function insights(array $k, array $r): array
    {
        $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
        $pct = $k['total'] > 0 ? round($k['researchersAll'] / $k['total'] * 100, 1) : null;
        $out = [];

        $out[] = ['label' => 'Pernah melakukan riset', 'value' => $nf($k['researchersAll']).' dari '.$nf($k['total']).' pengguna', 'note' => $pct !== null ? str_replace('.', ',', (string) $pct).'% dari seluruh akun terdaftar, sepanjang waktu.' : 'Belum ada akun terdaftar.', 'pct' => $pct];
        $out[] = ['label' => 'Belum pernah riset', 'value' => $nf(max(0, $k['total'] - $k['researchersAll'])).' pengguna', 'note' => 'Akun terdaftar tanpa satu pun riset tercatat.', 'pct' => null];

        if ($k['researchersAll'] > 0) {
            $out[] = ['label' => 'Rata-rata riset per peneliti', 'value' => number_format($k['runsAll'] / $k['researchersAll'], 1, ',', '.').' riset', 'note' => $nf($k['runsAll']).' riset dari '.$nf($k['researchersAll']).' pengguna yang pernah riset, sepanjang waktu.', 'pct' => null];
        }

        if ($r['tools']) {
            $top = $r['tools'][0];
            $out[] = ['label' => 'Alat paling sering dipakai', 'value' => $top['name'], 'note' => $nf($top['n']).' dari '.$nf($r['runs']).' riset pada periode ini ('.str_replace('.', ',', (string) $top['pct']).'%).', 'pct' => null];
        }

        $delta = $k['new'] <=> $k['newPrev'];
        $out[] = ['label' => 'Registrasi pada periode', 'value' => $nf($k['new']).' akun baru', 'note' => $nf($k['newPrev']).' pada rentang sebelumnya dengan panjang sama; '.['lebih sedikit', 'sama', 'lebih banyak'][$delta + 1].'.', 'pct' => null];

        $out[] = ['label' => 'Login terakhir dalam periode', 'value' => $nf($k['loggedIn']).' akun', 'note' => 'Hanya login terakhir yang tercatat, bukan jumlah atau keseringan login.', 'pct' => null];

        return $out;
    }

    /*
    |--------------------------------------------------------------------------
    | Direktori
    |--------------------------------------------------------------------------
    */

    public function directory(array $f): LengthAwarePaginator
    {
        $q = User::query()->withCount(['aiRuns', 'documentGenerations'])->withMax('aiRuns', 'created_at');

        if ($f['q'] !== '') {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(function (Builder $w) use ($f, $like): void {
                if (preg_match('/^#?(\d{1,18})$/', $f['q'], $m)) {
                    $w->orWhere('id', (int) $m[1]);
                }

                $w->orWhere('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('institution', 'like', $like);
            });
        }

        $admin = self::adminEmail();

        if ($f['peran'] === 'admin') {
            $admin === '' ? $q->whereRaw('1 = 0') : $q->whereRaw('lower(email) = ?', [$admin]);
        } elseif ($f['peran'] === 'pengguna' && $admin !== '') {
            $q->whereRaw('lower(email) <> ?', [$admin]);
        }

        $f['status'] && $q->where('status', $f['status']);
        $f['verifikasi'] === 'ya' && $q->whereNotNull('email_verified_at');
        $f['verifikasi'] === 'belum' && $q->whereNull('email_verified_at');
        $f['riset'] === 'pernah' && $q->whereHas('aiRuns');
        $f['riset'] === 'belum' && $q->whereDoesntHave('aiRuns');

        if ($f['daftar'] === 'periode') {
            [$from, $to] = $this->range($f);
            $q->whereBetween('created_at', [$this->utc($from), $this->utc($to)]);
        }

        match ($f['urut']) {
            'terlama' => $q->orderBy('created_at')->orderBy('id'),
            'nama' => $q->orderByRaw('lower(name)')->orderBy('id'),
            'riset' => $q->orderByDesc('ai_runs_count')->orderByDesc('id'),
            'login' => $q->orderByDesc('last_login_at')->orderByDesc('id'),
            'terbaru' => $q->orderByDesc('created_at')->orderByDesc('id'),
            default => $q->orderByRaw('lower(email)')->orderBy('id'), // email A-Z (default direktori)
        };

        $page = $q->paginate(self::PER_PAGE)->withQueryString();
        $this->attachLastDevice($page->getCollection());

        return $page;
    }

    /** Perangkat/IP login TERAKHIR per pengguna di halaman ini (satu kueri; null bila riwayat belum ada atau tabel belum dimigrasi). */
    private function attachLastDevice($users): void
    {
        $ids = $users->pluck('id')->all();
        $latest = [];
        if ($ids !== [] && LoginRecorder::available()) {
            foreach (UserLoginEvent::query()->whereIn('user_id', $ids)->orderByDesc('login_at')->orderByDesc('id')->get() as $e) {
                $latest[$e->user_id] ??= $e;
            }
        }
        foreach ($users as $u) {
            $u->setAttribute('last_login_event', $latest[$u->id] ?? null);
        }
    }

    /** Jumlah hasil per filter cepat tidak ditawarkan; hanya apakah ada filter direktori aktif. */
    public function filtering(array $f): bool
    {
        return $f['q'] !== '' || $f['peran'] || $f['status'] || $f['verifikasi'] || $f['daftar'] || $f['riset'];
    }

    /*
    |--------------------------------------------------------------------------
    | Detail pengguna
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    public function detail(User $user): array
    {
        $now = CarbonImmutable::now(self::TZ);
        $days = [];

        for ($i = 29; $i >= 0; $i--) {
            $days[$now->subDays($i)->format('Y-m-d')] = 0;
        }

        $since = $now->subDays(29)->startOfDay();

        AiRun::query()->where('user_id', $user->id)->where('created_at', '>=', $this->utc($since))->pluck('created_at')
            ->each(function ($t) use (&$days): void {
                $d = CarbonImmutable::parse($t)->utc()->setTimezone(self::TZ)->format('Y-m-d');

                if (isset($days[$d])) {
                    $days[$d]++;
                }
            });

        $stat = AiRun::query()->where('user_id', $user->id)->toBase()
            ->selectRaw("count(*) as runs, sum(case when status = 'completed' then 1 else 0 end) as ok, sum(case when status = 'failed' then 1 else 0 end) as failed")->first();

        $tools = AiRun::query()->where('ai_runs.user_id', $user->id)->toBase()->join('ai_tools', 'ai_tools.id', '=', 'ai_runs.ai_tool_id')
            ->selectRaw('ai_tools.name as name, count(*) as n')->groupBy('ai_tools.id', 'ai_tools.name')->orderByDesc('n')->limit(5)->get()
            ->map(fn ($r) => ['name' => $r->name, 'n' => (int) $r->n])->all();

        $recent = AiRun::query()->where('user_id', $user->id)->with(['aiTool:id,name', 'conversation:id,title'])->latest('id')->limit(8)->get()
            ->map(fn (AiRun $r) => [
                'id' => $r->id, 'tool' => $r->aiTool?->name ?? 'Alat tidak diketahui', 'title' => $r->conversation?->title, 'status' => $r->status,
                'time' => CarbonImmutable::parse($r->created_at)->utc()->setTimezone(self::TZ),
            ])->all();

        $documents = DocumentGeneration::query()->where('user_id', $user->id)->latest('id')->limit(5)->get(['id', 'title', 'status', 'document_type', 'created_at'])
            ->map(fn ($d) => ['title' => $d->title ?: 'Dokumen tanpa judul', 'status' => $d->status, 'type' => $d->document_type, 'time' => CarbonImmutable::parse($d->created_at)->utc()->setTimezone(self::TZ)])->all();

        // Hanya event yang OBJEKNYA akun ini (mis. disuspend admin). Event yang dilakukan akun ini tidak dicampur karena maknanya berbeda.
        $audit = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $user->id)->with('user:id,name')->latest('id')->limit(8)->get()
            ->map(function (AuditLog $l): array {
                $meta = (array) $l->metadata_json;

                return [
                    'phrase' => AuditEvents::describe($l->action)['phrase'], 'actor' => $l->user?->name ?? 'Admin',
                    'change' => isset($meta['from'], $meta['to']) && is_string($meta['from']) && is_string($meta['to']) ? $meta['from'].' → '.$meta['to'] : null,
                    'time' => CarbonImmutable::parse($l->created_at)->utc()->setTimezone(self::TZ),
                ];
            })->all();

        return [
            'user' => $user, 'isAdmin' => self::isAdminAccount($user),
            'runs' => (int) $stat->runs, 'ok' => (int) $stat->ok, 'failed' => (int) $stat->failed,
            'documents' => $user->documentGenerations()->count(), 'conversations' => $user->conversations()->count(), 'saved' => $user->savedResults()->count(),
            'tools' => $tools, 'recent' => $recent, 'docs' => $documents, 'audit' => $audit,
            'days' => $days, 'daysMax' => max($days) ?: 0, 'daysTotal' => array_sum($days),
            'lastRun' => $recent[0]['time'] ?? null,
            'tutorialDone' => $user->onboarding_completed_at !== null,
            'logins' => LoginRecorder::available()
                ? UserLoginEvent::query()->where('user_id', $user->id)->orderByDesc('login_at')->orderByDesc('id')->limit(10)->get()
                    ->map(fn (UserLoginEvent $e) => [
                        'device' => DeviceParser::label($e->device_type, $e->os, $e->browser), 'ip' => $e->ip_address,
                        'at' => CarbonImmutable::parse($e->login_at)->utc()->setTimezone(self::TZ),
                        'active' => $e->last_activity_at ? CarbonImmutable::parse($e->last_activity_at)->utc()->setTimezone(self::TZ) : null,
                    ])->all()
                : null,
        ];
    }
}
