<section class="br-tour" data-first-tour data-complete-url="{{ route('onboarding.complete') }}" role="dialog" aria-label="Tutorial BacaDulu Research" aria-modal="false">
    <div class="br-tour__top"><span>TUTORIAL</span><button type="button" data-tour-skip aria-label="Lewati tutorial">Lewati ×</button></div>
    <div class="br-tour__progress" aria-hidden="true"><span data-tour-progress></span></div>
    @php $tutorialVideo = $tutorialVideo ?? config('research.tutorial_video_url'); @endphp
    @if(! empty($tutorialVideo))
        <video class="br-tour__video" controls preload="none" playsinline src="{{ $tutorialVideo }}" aria-label="Video tutorial BacaDulu Research"></video>
    @endif
    <div class="br-tour__copy" aria-live="polite"><span data-tour-count>01 / 03</span><h2 data-tour-title>Pilih kategori dan alat riset</h2><p data-tour-copy>Pilih kategori dan alat riset, tulis topik, lalu tekan Mulai Riset.</p></div>
    <div class="br-tour__actions"><button class="br-tour__back" type="button" data-tour-back>Kembali</button><button class="br-tour__next" type="button" data-tour-next>Berikutnya →</button></div>
</section>
