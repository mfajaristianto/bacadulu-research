<?php

namespace App\Services\Security;

use App\Http\Middleware\SecurityHeaders;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditRedactor;
use App\Services\Audit\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/*
 * Keamanan: observability akses dan autentikasi. Hanya MEMBACA. Sumber peristiwa adalah SUBSET audit_logs yang benar-benar berkaitan dengan
 * akses (lihat EVENTS); bukan salinan Log Aktivitas. Tidak ada tulis/ubah/hapus, tidak ada migration, tidak ada tracking baru.
 *
 * Yang dicatat backend hari ini: login admin berhasil/gagal, keluar admin, dan penangguhan/pengaktifan akun pengguna.
 * Yang TIDAK dicatat (dan karena itu tidak pernah ditampilkan sebagai angka): login pengguna Workspace, akses ditolak, pemblokiran throttle (429),
 * reset password, user agent, dan identitas sesi admin. Daftar ini ditampilkan apa adanya lewat limitations().
 */
class SecurityMonitor
{
    public const PER_PAGE = 20;

    public const TZ = 'Asia/Jakarta';

    private const OFFSET_HOURS = 7; // WIB tidak memakai DST; created_at disimpan UTC

    /** kode event => [label, hasil, seri grafik] */
    public const EVENTS = [
        'admin.login' => ['Login admin berhasil', 'berhasil', 'berhasil'],
        'admin.login.failed' => ['Login admin gagal', 'gagal', 'gagal'],
        'admin.logout' => ['Keluar dari panel admin', 'info', 'keluar'],
        'user.suspended' => ['Akun pengguna ditangguhkan', 'info', 'akun'],
        'user.reactivated' => ['Akun pengguna diaktifkan kembali', 'info', 'akun'],
    ];

    public const SERIES = [
        'berhasil' => 'Login berhasil',
        'gagal' => 'Login gagal',
        'keluar' => 'Keluar admin',
        'akun' => 'Perubahan akses akun',
    ];

    public const RESULTS = ['berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'info' => 'Informasi'];

    public const SORTS = ['terbaru' => 'Terbaru', 'terlama' => 'Terlama', 'jenis' => 'Jenis event'];

    public function __construct(private readonly AuditTrail $trail, private readonly AuditRedactor $redactor) {}

    public static function available(): bool
    {
        return Schema::hasTable('audit_logs');
    }

    /*
    |--------------------------------------------------------------------------
    | Filter dan periode
    |--------------------------------------------------------------------------
    */

    /** @return array{f: array<string, mixed>, notice: ?string} */
    public function filters(Request $request): array
    {
        ['f' => $p, 'notice' => $notice] = $this->trail->filters(Request::create('/', 'GET', Arr::only($request->query(), ['periode', 'dari', 'sampai'])));
        $in = Arr::except($request->query(), ['periode', 'dari', 'sampai']);
        $v = Validator::make($in, [
            'q' => ['nullable', 'string', 'max:160'],
            'jenis' => ['nullable', 'in:'.implode(',', array_keys(self::EVENTS))],
            'hasil' => ['nullable', 'in:'.implode(',', array_keys(self::RESULTS))],
            'aktor' => ['nullable', 'in:diketahui,tidak-diketahui'],
            'urut' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))],
            'skala' => ['nullable', 'in:hari,minggu'],
            'page' => ['nullable', 'integer', 'min:1'],
            'log' => ['nullable', 'integer', 'min:1'],
        ]);
        $clean = collect($in)->except(array_keys($v->errors()->toArray()))->all();

        if ($v->errors()->isNotEmpty()) {
            $notice ??= 'Sebagian filter tidak valid dan diabaikan.';
        }

        return ['notice' => $notice, 'f' => [
            'periode' => $p['periode'], 'dari' => $p['dari'], 'sampai' => $p['sampai'],
            'q' => trim((string) ($clean['q'] ?? '')), 'jenis' => $clean['jenis'] ?? null, 'hasil' => $clean['hasil'] ?? null, 'aktor' => $clean['aktor'] ?? null,
            'urut' => $clean['urut'] ?? 'terbaru', 'skala' => $clean['skala'] ?? 'hari', 'log' => isset($clean['log']) ? (int) $clean['log'] : null,
        ]];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    public function range(array $f): array
    {
        return $this->trail->range($f);
    }

    public function filtering(array $f): bool
    {
        return $f['q'] !== '' || $f['jenis'] || $f['hasil'] || $f['aktor'];
    }

    private function utc(CarbonImmutable $d): string
    {
        return $d->utc()->format('Y-m-d H:i:s');
    }

    private function bucketSql(string $gran): string
    {
        $fmt = $gran === 'hour' ? '%Y-%m-%d %H' : '%Y-%m-%d';

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('{$fmt}', created_at, '+".self::OFFSET_HOURS." hours')"
            : 'DATE_FORMAT(DATE_ADD(created_at, INTERVAL '.self::OFFSET_HOURS." HOUR), '{$fmt}')";
    }

    private function events(): Builder
    {
        return AuditLog::query()->whereIn('action', array_keys(self::EVENTS));
    }

    private function inPeriod(Builder $q, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $q->whereBetween('created_at', [$this->utc($from), $this->utc($to)]);
    }

    /*
    |--------------------------------------------------------------------------
    | KPI
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    public function kpis(array $f): array
    {
        [$from, $to] = $this->range($f);
        $prevFrom = $from->subSeconds($to->getTimestamp() - $from->getTimestamp() + 1);
        $count = fn (CarbonImmutable $a, CarbonImmutable $b, ?string $action = null) => $this->inPeriod($action ? AuditLog::query()->where('action', $action) : $this->events(), $a, $b)->count();
        $last = $this->events()->latest('id')->first(['id', 'created_at']);

        return [
            'total' => $count($from, $to),
            'success' => $count($from, $to, 'admin.login'),
            'failed' => $count($from, $to, 'admin.login.failed'),
            'failedPrev' => $count($prevFrom, $from->subSecond(), 'admin.login.failed'),
            'successPrev' => $count($prevFrom, $from->subSecond(), 'admin.login'),
            'unverified' => User::query()->whereNull('email_verified_at')->count(),
            'users' => User::query()->count(),
            'suspended' => User::query()->where('status', 'suspended')->count(),
            'sessions' => $this->workspaceSessions(),
            'last' => $last ? CarbonImmutable::parse($last->created_at, 'UTC')->setTimezone(self::TZ) : null,
        ];
    }

    /**
     * Sesi Workspace yang masih berlaku menurut tabel sessions, HANYA bila driver `database`. Bukan jumlah "online": sesi hanya kedaluwarsa menurut
     * umur, dan sesi admin panel tidak terhubung ke user_id sehingga tidak dapat diidentifikasi.
     */
    public function workspaceSessions(): ?int
    {
        if (config('session.driver') !== 'database' || ! Schema::hasTable(config('session.table', 'sessions'))) {
            return null;
        }

        return DB::table(config('session.table', 'sessions'))->whereNotNull('user_id')->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Grafik aktivitas akses (tumpuk per seri)
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    public function activity(array $f): array
    {
        [$from, $to, $gran] = $this->range($f);
        $weekly = $gran === 'day' && ($f['skala'] === 'minggu' || $from->diffInDays($to) > 62);
        $keyOf = fn (string $b) => $weekly ? CarbonImmutable::createFromFormat('Y-m-d', $b, self::TZ)->startOfWeek()->format('Y-m-d') : $b;
        $buckets = [];

        for ($c = $weekly ? $from->startOfWeek() : $from; $c->lte($to); $c = $gran === 'hour' ? $c->addHour() : ($weekly ? $c->addWeek() : $c->addDay())) {
            $buckets[$gran === 'hour' ? $c->format('Y-m-d H') : $c->format('Y-m-d')] = 0;
        }

        $series = array_map(fn () => $buckets, self::SERIES);
        $sql = $this->bucketSql($gran === 'hour' ? 'hour' : 'day');
        $rows = $this->inPeriod($this->events(), $from, $to)->toBase()->selectRaw("{$sql} as bucket, action, count(*) as n")->groupBy(DB::raw($sql), 'action')->get();

        foreach ($rows as $row) {
            $key = $keyOf($row->bucket);
            $serie = self::EVENTS[$row->action][2];
            $series[$serie][$key] = ($series[$serie][$key] ?? 0) + (int) $row->n;
        }

        $out = [];

        foreach ($series as $key => $points) {
            $sum = array_sum($points);

            // Seri login selalu tampil (pencatatannya ada, jadi nol adalah nol yang sebenarnya); seri lain hanya bila ada kejadian.
            if (in_array($key, ['berhasil', 'gagal'], true) || $sum > 0) {
                $out[$key] = ['label' => self::SERIES[$key], 'total' => $sum, 'points' => array_values($points)];
            }
        }

        $stack = array_fill(0, count($buckets), 0);

        foreach ($out as $s) {
            foreach ($s['points'] as $i => $n) {
                $stack[$i] += $n;
            }
        }

        return [
            'series' => $out, 'labels' => array_map(fn ($b) => $this->label($b, $gran, $weekly), array_keys($buckets)),
            'gran' => $weekly ? 'week' : $gran, 'max' => $stack ? max($stack) : 0, 'total' => array_sum($stack),
            'activeBuckets' => count(array_filter($stack)), 'canWeek' => $gran === 'day',
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
    | Distribusi jenis
    |--------------------------------------------------------------------------
    */

    /** @return list<array{code: string, label: string, result: string, n: int, pct: float}> hanya kode yang punya kejadian pada periode */
    public function distribution(array $f): array
    {
        [$from, $to] = $this->range($f);
        $rows = $this->inPeriod($this->events(), $from, $to)->toBase()->selectRaw('action, count(*) as n')->groupBy('action')->orderByDesc('n')->get();
        $total = (int) $rows->sum('n');

        return $rows->map(fn ($r) => [
            'code' => $r->action, 'label' => self::EVENTS[$r->action][0], 'result' => self::EVENTS[$r->action][1], 'n' => (int) $r->n,
            'pct' => $total > 0 ? round($r->n / $total * 100, 1) : 0.0,
        ])->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Insight (deskriptif; tidak menyimpulkan serangan atau niat)
    |--------------------------------------------------------------------------
    */

    /** @return list<array{label: string, value: string, note: string, pct: ?float}> */
    public function insights(array $f, array $k): array
    {
        $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
        $attempts = $k['success'] + $k['failed'];
        $out = [];

        if ($attempts > 0) {
            $pct = round($k['failed'] / $attempts * 100, 1);
            $out[] = ['label' => 'Proporsi login gagal', 'value' => $nf($k['failed']).' dari '.$nf($attempts).' percobaan', 'note' => str_replace('.', ',', (string) $pct).'% dari percobaan login admin yang tercatat pada periode. Kegagalan bisa berupa salah ketik atau lupa kata sandi.', 'pct' => $pct];
        } else {
            $out[] = ['label' => 'Proporsi login gagal', 'value' => 'Belum ada percobaan', 'note' => 'Tidak ada login admin (berhasil maupun gagal) yang tercatat pada periode ini.', 'pct' => null];
        }

        $delta = $k['failed'] <=> $k['failedPrev'];
        $out[] = ['label' => 'Dibanding periode sebelumnya', 'value' => $nf($k['failed']).' login gagal', 'note' => $nf($k['failedPrev']).' pada rentang sebelumnya dengan panjang sama; percobaan login gagal '.['lebih sedikit', 'sama banyak', 'lebih banyak'][$delta + 1].'. Angka ini tidak menunjukkan penyebab.', 'pct' => null];

        $busy = $this->busiestHour($f);

        if ($busy) {
            $out[] = ['label' => 'Jam paling ramai (WIB)', 'value' => sprintf('%02d.00–%02d.59', $busy['hour'], $busy['hour']), 'note' => $nf($busy['n']).' dari '.$nf($busy['of']).' event login/keluar pada periode terjadi di jam ini.', 'pct' => null];
        }

        $verified = $k['users'] - $k['unverified'];
        $out[] = ['label' => 'Akun terverifikasi', 'value' => $nf($verified).' dari '.$nf($k['users']).' akun', 'note' => $k['users'] > 0 ? str_replace('.', ',', (string) round($verified / $k['users'] * 100, 1)).'% akun terdaftar sudah memverifikasi email, sepanjang waktu.' : 'Belum ada akun terdaftar.', 'pct' => $k['users'] > 0 ? round($verified / $k['users'] * 100, 1) : null];

        return $out;
    }

    /** Jam tersibuk hanya bila datanya cukup (minimal 6 event dan lebih dari satu jam berbeda). @return ?array{hour: int, n: int, of: int} */
    private function busiestHour(array $f): ?array
    {
        [$from, $to] = $this->range($f);
        $expr = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%H', created_at, '+".self::OFFSET_HOURS." hours')" : 'DATE_FORMAT(DATE_ADD(created_at, INTERVAL '.self::OFFSET_HOURS." HOUR), '%H')";
        $rows = $this->inPeriod(AuditLog::query()->whereIn('action', ['admin.login', 'admin.login.failed', 'admin.logout']), $from, $to)->toBase()
            ->selectRaw("{$expr} as h, count(*) as n")->groupBy(DB::raw($expr))->orderByDesc('n')->get();
        $total = (int) $rows->sum('n');

        return $total >= 6 && $rows->count() > 1 ? ['hour' => (int) $rows[0]->h, 'n' => (int) $rows[0]->n, 'of' => $total] : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Linimasa peristiwa (daftar)
    |--------------------------------------------------------------------------
    */

    public function paginate(array $f): LengthAwarePaginator
    {
        [$from, $to] = $this->range($f);
        $q = $this->inPeriod($this->events(), $from, $to);

        if ($f['jenis']) {
            $q->where('action', $f['jenis']);
        }

        match ($f['hasil']) {
            'berhasil' => $q->where('action', 'admin.login'),
            'gagal' => $q->where('action', 'admin.login.failed'),
            'info' => $q->whereNotIn('action', ['admin.login', 'admin.login.failed']),
            default => null,
        };

        match ($f['aktor']) {
            'diketahui' => $q->whereNotNull('user_id'),
            'tidak-diketahui' => $q->whereNull('user_id'),
            default => null,
        };

        if ($f['q'] !== '') {
            $term = $f['q'];
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where(function (Builder $w) use ($term, $like): void {
                if (preg_match('/^#?(\d{1,18})$/', $term, $m)) {
                    $w->orWhere('id', (int) $m[1]);
                }

                $codes = collect(self::EVENTS)->filter(fn ($e, $code) => mb_stripos($e[0], $term) !== false || mb_stripos($code, $term) !== false)->keys()->all();
                $codes && $w->orWhereIn('action', $codes);
                $w->orWhere('ip_address', $term)->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));

                if (mb_strlen($term) >= 3) {
                    $w->orWhere('metadata_json', 'like', $like); // email yang dicoba pada login gagal
                }
            });
        }

        match ($f['urut']) {
            'terlama' => $q->orderBy('created_at')->orderBy('id'),
            'jenis' => $q->orderBy('action')->orderByDesc('id'),
            default => $q->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $q->with('user:id,name,email')->paginate(self::PER_PAGE)->withQueryString();
    }

    /** @param iterable<AuditLog> $logs @return list<array<string, mixed>> */
    public function present(iterable $logs): array
    {
        $logs = collect($logs);
        $targets = User::query()->whereIn('id', $logs->filter(fn ($l) => str_starts_with($l->action, 'user.'))->pluck('auditable_id')->filter())->pluck('name', 'id');

        return $logs->map(fn (AuditLog $l) => $this->row($l, $targets->all()))->all();
    }

    /** @param array<int, string> $targets id => nama akun yang menjadi sasaran penangguhan */
    private function row(AuditLog $l, array $targets): array
    {
        [$label, $result] = self::EVENTS[$l->action];
        $meta = (array) $l->metadata_json;
        $attempted = is_string($meta['email'] ?? null) ? AuditRedactor::maskEmail($meta['email']) : null;
        $actor = $l->user?->name;
        $target = $l->auditable_id ? ($targets[$l->auditable_id] ?? null) : null;
        $time = CarbonImmutable::parse($l->created_at, 'UTC')->setTimezone(self::TZ);

        $sentence = match ($l->action) {
            'admin.login' => ($actor ?? 'Administrator').' masuk ke panel admin',
            'admin.login.failed' => 'Percobaan login admin gagal'.($attempted ? ' dengan email '.$attempted : ''),
            'admin.logout' => ($actor ?? 'Administrator').' keluar dari panel admin',
            'user.suspended' => ($actor ?? 'Admin').' menangguhkan akun '.($target ?? 'pengguna #'.$l->auditable_id),
            default => ($actor ?? 'Admin').' mengaktifkan kembali akun '.($target ?? 'pengguna #'.$l->auditable_id),
        };

        return [
            'id' => $l->id, 'action' => $l->action, 'label' => $label, 'result' => $result, 'resultLabel' => self::RESULTS[$result], 'sentence' => $sentence,
            'actor' => $actor ?? ($l->action === 'admin.login.failed' ? 'Tidak teridentifikasi' : 'Tidak diketahui'), 'actorKnown' => $actor !== null,
            'ip' => AuditRedactor::maskIp($l->ip_address), 'time' => $time, 'day' => $time->locale('id')->translatedFormat('l, j F Y'), 'clock' => $time->format('H.i'),
        ];
    }

    public static function isSecurityEvent(AuditLog $log): bool
    {
        return isset(self::EVENTS[$log->action]);
    }

    /** @return array<string, mixed> */
    public function detail(AuditLog $log): array
    {
        $log->loadMissing('user:id,name,email');
        $row = $this->row($log, $log->auditable_id && str_starts_with($log->action, 'user.') ? User::query()->whereKey($log->auditable_id)->pluck('name', 'id')->all() : []);
        $safe = (array) $this->redactor->redact((array) $log->metadata_json);
        $fields = [];

        foreach ($safe as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $value = $key === 'email' && is_string($value) ? AuditRedactor::maskEmail($value) : $value;
                $fields[] = ['key' => $key, 'value' => is_bool($value) ? ($value ? 'ya' : 'tidak') : (string) ($value ?? '—')];
            }
        }

        $meaning = match ($log->action) {
            'admin.login' => ['Kredensial admin cocok dan sesi panel dibuka.', 'Tidak tercatat dari perangkat atau browser mana login dilakukan.'],
            'admin.login.failed' => ['Email atau kata sandi yang dimasukkan tidak cocok, atau akun admin tidak aktif.', 'Kegagalan tidak membuktikan niat jahat: bisa salah ketik atau lupa kata sandi. Email yang dicoba bisa milik siapa saja, jadi ditampilkan sebagian.'],
            'admin.logout' => ['Sesi panel admin ditutup oleh admin.', 'Sesi yang kedaluwarsa sendiri atau browser yang ditutup tidak tercatat sebagai event.'],
            'user.suspended' => ['Seorang admin menangguhkan akun pengguna, sehingga pengguna tidak dapat masuk ke Workspace.', 'Data riset dan dokumen pengguna tetap tersimpan.'],
            default => ['Seorang admin mengaktifkan kembali akun pengguna yang ditangguhkan.', 'Pengguna dapat masuk lagi; sesi lama tidak otomatis dipulihkan.'],
        };

        $related = [];
        $row['actorKnown'] && $log->user_id && $related[] = ['label' => 'Akun pelaku di Pengguna', 'url' => route('admin.users.index', ['pengguna' => $log->user_id])];
        str_starts_with($log->action, 'user.') && $log->auditable_id && $related[] = ['label' => 'Akun yang diubah di Pengguna', 'url' => route('admin.users.index', ['pengguna' => $log->auditable_id])];
        $related[] = ['label' => 'Event yang sama di Log Aktivitas', 'url' => route('admin.audit.index', ['log' => $log->id])];

        return $row + [
            'full' => $row['time']->locale('id')->translatedFormat('l, j F Y, H.i.s').' WIB', 'meaning' => $meaning[0], 'limit' => $meaning[1],
            'fields' => $fields, 'related' => $related, 'ipNote' => $log->ip_address ? 'Alamat IP ditampilkan sebagian untuk melindungi privasi.' : 'Alamat IP tidak tercatat pada event ini.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Kontrol perlindungan: status DIPERIKSA saat halaman dibuka dari konfigurasi dan tabel rute yang sebenarnya.
    |--------------------------------------------------------------------------
    */

    /** @return list<array{group: string, name: string, status: string, basis: string, note: string, limit: ?string}> status: aktif|tidak-aktif|belum-diverifikasi|tidak-didukung */
    public function controls(): array
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $mw = fn ($route) => collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m))->all();
        $byName = fn (string $name) => $routes->first(fn ($r) => $r->getName() === $name);
        $limits = function (string $name): ?string {
            $callback = RateLimiter::limiter($name);

            if (! $callback) {
                return null;
            }

            $parts = collect(Arr::wrap($callback(Request::create('/', 'POST', ['email' => 'x@x.test']))))->map(fn ($l) => $l->maxAttempts.' percobaan per '.($l->decaySeconds >= 3600 ? 'jam' : 'menit'))->all();

            return $parts ? implode(' dan ', $parts) : null;
        };
        $out = [];
        $add = function (string $group, string $name, string $status, string $basis, string $note, ?string $limit = null) use (&$out): void {
            $out[] = compact('group', 'name', 'status', 'basis', 'note', 'limit');
        };

        // Autentikasi
        $hashed = ((new User)->getCasts()['password'] ?? null) === 'hashed';
        $add('Autentikasi', 'Hash kata sandi pengguna', $hashed ? 'aktif' : 'tidak-aktif', 'Cast password pada model User dan konfigurasi hashing.', $hashed ? 'Kata sandi pengguna disimpan sebagai hash '.config('hashing.driver').'.' : 'Kata sandi pengguna tidak di-hash otomatis oleh model.', 'Kata sandi lama yang dibuat sebelum cast ini tidak diperiksa satu per satu.');

        $hashSet = filled(config('research.admin_password_hash'));
        $plain = filled(config('research.admin_password'));
        $add('Autentikasi', 'Kata sandi admin panel', $hashSet ? 'aktif' : ($plain ? 'tidak-aktif' : 'belum-diverifikasi'), 'Ada tidaknya ADMIN_PASSWORD_HASH (nilainya tidak dibaca ke halaman).', $hashSet ? 'Kata sandi admin tersimpan sebagai hash.' : ($plain ? 'Kata sandi admin masih berupa teks biasa di konfigurasi. Jalankan php artisan research:admin-password-hash lalu isi ADMIN_PASSWORD_HASH.' : 'Kredensial admin belum dikonfigurasi.'));

        $adminLogin = $byName('admin.login.store');
        $adminThrottle = $adminLogin && collect($mw($adminLogin))->contains(fn ($m) => str_contains($m, 'throttle:admin-login'));
        $add('Autentikasi', 'Pembatasan login admin', $adminThrottle ? 'aktif' : 'tidak-aktif', 'Rute login admin dan definisi rate limiter.', $adminThrottle ? 'Maksimal '.($limits('admin-login') ?? 'sesuai konfigurasi').' per alamat IP.' : 'Rute login admin tidak memakai pembatas laju.', 'Pemblokiran karena batas terlampaui (HTTP 429) tidak dicatat sebagai event.');

        $userLogin = $byName('login.store');
        $userThrottle = $userLogin && collect($mw($userLogin))->contains(fn ($m) => str_contains($m, 'throttle:login'));
        $add('Autentikasi', 'Pembatasan login pengguna', $userThrottle ? 'aktif' : 'tidak-aktif', 'Rute login pengguna dan definisi rate limiter.', $userThrottle ? 'Maksimal '.($limits('login') ?? 'sesuai konfigurasi').'.' : 'Rute login pengguna tidak memakai pembatas laju.', 'Login pengguna Workspace tidak tercatat di audit; hanya waktu login terakhir yang tersimpan.');

        $verify = (bool) config('research.require_email_verification', false);
        $add('Autentikasi', 'Verifikasi email pengguna', $verify ? 'aktif' : 'tidak-aktif', 'Pengaturan research.require_email_verification.', $verify ? 'Pengguna harus memverifikasi email sebelum memakai Workspace.' : 'Verifikasi email bersifat opsional pada konfigurasi ini.');

        $twoFa = Schema::hasTable('users') && Schema::hasColumn('users', 'two_factor_secret');
        $add('Autentikasi', 'Autentikasi dua faktor', $twoFa ? 'belum-diverifikasi' : 'tidak-didukung', 'Skema tabel users.', $twoFa ? 'Kolom 2FA ada, tetapi penerapannya belum dapat diperiksa dari halaman ini.' : 'Aplikasi belum memiliki 2FA.');

        // Akses
        $admin = $routes->filter(fn ($r) => str_starts_with((string) $r->getName(), 'admin.') && ! in_array($r->getName(), ['admin.login', 'admin.login.store'], true));
        $guarded = $admin->filter(fn ($r) => in_array('admin', $mw($r), true))->count();
        $add('Akses', 'Otorisasi area admin', $admin->isNotEmpty() && $guarded === $admin->count() ? 'aktif' : 'tidak-aktif', 'Seluruh rute bernama admin.* diperiksa middleware-nya.', $guarded.' dari '.$admin->count().' rute admin (selain halaman login) memakai middleware admin.', 'Hanya memeriksa rute bernama; pemeriksaan di dalam controller tidak ikut dihitung.');

        $active = $routes->filter(fn ($r) => in_array('active', $mw($r), true))->count();
        $add('Akses', 'Penangguhan akun', $active > 0 ? 'aktif' : 'tidak-aktif', 'Middleware active pada rute Workspace.', $active > 0 ? 'Akun berstatus ditangguhkan ditolak masuk di '.$active.' rute Workspace.' : 'Tidak ada rute yang memeriksa status akun.', 'Sesi yang sudah terbuka baru ditolak pada permintaan berikutnya.');

        // Grup `web` dibaca dari kernel HTTP (router belum tentu terisi sebelum request pertama).
        $web = app(Kernel::class)->getMiddlewareGroups()['web'] ?? [];
        $csrfClass = collect($web)->first(fn ($m) => is_string($m) && preg_match('/(ValidateCsrfToken|VerifyCsrfToken|PreventRequestForgery)$/', $m));
        $csrfGroup = $csrfClass !== null;
        $excluded = $csrfGroup && method_exists($csrfClass, 'getExcludedPaths') ? app($csrfClass)->getExcludedPaths() : [];
        $add('Akses', 'Perlindungan CSRF', $csrfGroup ? ($excluded === [] ? 'aktif' : 'belum-diverifikasi') : 'tidak-aktif', 'Grup middleware web dan daftar pengecualian.', $csrfGroup ? ($excluded === [] ? 'Token CSRF diperiksa untuk semua permintaan web yang mengubah data, tanpa pengecualian.' : count($excluded).' jalur dikecualikan dari pemeriksaan CSRF.') : 'Middleware CSRF tidak ada di grup web.');

        // Sesi dan transport
        $httpOnly = (bool) config('session.http_only', true);
        $same = strtolower((string) config('session.same_site', 'lax'));
        $add('Sesi & transport', 'Cookie sesi HttpOnly dan SameSite', $httpOnly && in_array($same, ['lax', 'strict'], true) ? 'aktif' : 'tidak-aktif', 'Konfigurasi session.', 'HttpOnly '.($httpOnly ? 'aktif' : 'mati').', SameSite '.($same ?: 'tidak diatur').'; driver sesi '.config('session.driver').'.');

        $secure = (bool) config('session.secure');
        $add('Sesi & transport', 'Cookie sesi hanya lewat HTTPS (Secure)', $secure ? 'aktif' : 'tidak-aktif', 'Konfigurasi session.secure.', $secure ? 'Cookie sesi hanya dikirim lewat HTTPS.' : 'Cookie Secure belum diaktifkan pada konfigurasi ini; aktifkan di production yang memakai HTTPS (SESSION_SECURE_COOKIE=true).');

        $headers = in_array(SecurityHeaders::class, $web, true);
        $prod = app()->environment('production');
        $add('Sesi & transport', 'Header keamanan HTTP', $headers ? 'aktif' : 'tidak-aktif', 'Middleware SecurityHeaders di grup web.', $headers ? 'X-Content-Type-Options, X-Frame-Options, Referrer-Policy, dan Permissions-Policy dikirim pada setiap respons web.' : 'Middleware header keamanan tidak terpasang.', $headers && ! $prod ? 'Content-Security-Policy hanya dikirim di production; lingkungan ini '.app()->environment().'.' : null);

        $debug = (bool) config('app.debug');
        $add('Sesi & transport', 'Mode debug dimatikan', ! $debug ? 'aktif' : 'tidak-aktif', 'Konfigurasi app.debug.', ! $debug ? 'Detail galat tidak dibocorkan ke pengunjung.' : 'Mode debug menyala'.($prod ? ' di production: segera matikan (APP_DEBUG=false).' : ' pada lingkungan '.app()->environment().'; wajib mati di production.'));

        $revoke = $routes->contains(fn ($r) => str_contains((string) $r->getName(), 'sessions.') || str_contains((string) $r->getName(), 'session.revoke'));
        $add('Sesi & transport', 'Pencabutan sesi dari panel admin', $revoke ? 'belum-diverifikasi' : 'tidak-didukung', 'Daftar rute.', $revoke ? 'Ada rute terkait sesi, tetapi belum diperiksa dari halaman ini.' : 'Belum ada mekanisme untuk mengakhiri sesi pengguna dari panel, jadi tidak ada tombolnya.');

        return $out;
    }

    /** Hal yang belum dicatat backend. Ditampilkan agar "0 kejadian" tidak disalahartikan. @return list<array{name: string, note: string}> */
    public function limitations(): array
    {
        return [
            ['name' => 'Login dan kegagalan login pengguna Workspace', 'note' => 'Tidak ditulis ke audit. Hanya last_login_at (login terakhir) yang tersimpan di akun.'],
            ['name' => 'Akses ditolak', 'note' => 'Penolakan oleh middleware (belum login, akun ditangguhkan) tidak dicatat sebagai event.'],
            ['name' => 'Pemblokiran pembatas laju', 'note' => 'Permintaan yang ditolak karena melewati batas (HTTP 429) tidak sampai ke pencatat audit.'],
            ['name' => 'Reset dan perubahan kata sandi', 'note' => 'Tidak dicatat sebagai event audit.'],
            ['name' => 'Perangkat dan user agent', 'note' => 'Tabel audit hanya menyimpan alamat IP; browser dan perangkat tidak dicatat.'],
            ['name' => 'Sesi admin panel', 'note' => 'Login admin tidak memakai guard pengguna, sehingga sesinya tidak dapat diidentifikasi atau dihitung.'],
        ];
    }
}
