/*
 * Admin Beranda V5 (admin/dashboard.blade.php). Everything here is progressive enhancement:
 *  - Bubu: one settle + wave (unchanged behaviour), nothing else animates by itself.
 *  - Donut: hover / focus / click-to-pin on slices and legend, tooltip, touch and keyboard.
 *  - Pusat Analitik: tabs with a sliding indicator, period filter without page reload, SVG charts with scrubbing.
 * Motion only responds to input and is skipped under prefers-reduced-motion. No chart library: charts are drawn as plain SVG.
 */

const NS = 'http://www.w3.org/2000/svg';
const EASE = 'cubic-bezier(.2,.65,.4,1)';
const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
const nf = new Intl.NumberFormat('id-ID');
const compact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });

export function initAdminDashboard() {
    if (!document.querySelector('[data-dash]')) {
        return;
    }

    initBubu();
    initDonut();
    initAnalytics();
}

/* Bubu: settle in once, one small wave, then idle. */
function initBubu() {
    const bubu = document.querySelector('[data-dash-bubu]');

    if (!bubu || reduced() || !bubu.animate) {
        return;
    }

    bubu.animate(
        [{ opacity: 0, transform: 'translateY(8px) scale(.94)' }, { opacity: 1, transform: 'none' }],
        { duration: 400, delay: 120, easing: EASE, fill: 'backwards' },
    );
    bubu.animate(
        [0, -7, 5, -3, 0].map((deg) => ({ transform: `rotate(${deg}deg)`, easing: 'ease-in-out' })),
        { duration: 520, delay: 640 },
    );
}

/* ------------------------------------------------------------------------------------------------ donut */

function initDonut() {
    const root = document.querySelector('[data-dash-users]');
    const donut = root?.querySelector('[data-dash-donut]');

    if (!donut) {
        return;
    }

    const arcs = [...donut.querySelectorAll('circle[data-seg]')];
    const rows = [...root.querySelectorAll('button[data-seg]')];
    const tip = donut.querySelector('[data-donut-tip]');
    const value = donut.querySelector('[data-donut-value]');
    const caption = donut.querySelector('[data-donut-caption]');
    const total = { value: value.textContent, caption: caption.textContent };

    let hover = null; // pointer over a slice or legend row
    let focus = null; // keyboard focus on a slice
    let pinned = null; // chosen by click / tap / Enter
    let at = null; // pointer position inside the donut for the tooltip

    const arc = (key) => arcs.find((node) => node.dataset.seg === key);

    // Middle of a slice in donut pixels, from its dash length + offset (pathLength = 100, starts at 12 o'clock).
    const middle = (node) => {
        const length = parseFloat(node.getAttribute('stroke-dasharray'));
        const offset = -parseFloat(node.getAttribute('stroke-dashoffset') || '0');
        const angle = ((offset + length / 2) / 100) * Math.PI * 2 - Math.PI / 2;
        const size = donut.clientWidth;
        const radius = size / 2 * (15.9155 / 18);

        return { x: size / 2 + Math.cos(angle) * radius, y: size / 2 + Math.sin(angle) * radius };
    };

    const render = () => {
        const key = hover ?? focus ?? pinned;
        const node = key ? arc(key) : null;

        arcs.forEach((item) => {
            item.classList.toggle('is-on', item === node);
            item.classList.toggle('is-dim', Boolean(node) && item !== node);
        });
        rows.forEach((row) => {
            row.setAttribute('aria-pressed', String(row.dataset.seg === pinned));
            row.classList.toggle('is-dim', Boolean(node) && row.dataset.seg !== key);
        });

        if (!node) {
            value.textContent = total.value;
            caption.textContent = total.caption;
            tip.hidden = true;

            return;
        }

        value.textContent = node.dataset.segCount;
        caption.textContent = node.dataset.segShare;

        tip.replaceChildren();
        const strong = document.createElement('b');
        strong.textContent = `${node.dataset.segCount} akun · ${node.dataset.segShare}`;
        const label = document.createElement('span');
        label.textContent = node.dataset.segLabel;
        tip.append(strong, label);
        tip.hidden = false;

        const point = at ?? middle(node);
        const size = donut.clientWidth;
        tip.style.left = `${clamp(point.x + 14, 0, size - tip.offsetWidth)}px`;
        tip.style.top = `${clamp(point.y - tip.offsetHeight - 10, -tip.offsetHeight - 4, size - tip.offsetHeight)}px`;
    };

    const toggle = (key) => {
        pinned = pinned === key ? null : key;
        render();
    };

    arcs.forEach((node, index) => {
        const key = node.dataset.seg;

        node.addEventListener('pointerenter', (event) => {
            hover = key;
            const box = donut.getBoundingClientRect();
            at = { x: event.clientX - box.left, y: event.clientY - box.top };
            render();
        });
        node.addEventListener('pointermove', (event) => {
            const box = donut.getBoundingClientRect();
            at = { x: event.clientX - box.left, y: event.clientY - box.top };
            render();
        });
        node.addEventListener('pointerleave', () => {
            hover = null;
            at = null;
            render();
        });
        node.addEventListener('click', () => toggle(key));
        node.addEventListener('focus', () => {
            focus = key;
            at = null;
            render();
        });
        node.addEventListener('blur', () => {
            focus = null;
            render();
        });
        node.addEventListener('keydown', (event) => {
            const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];

            if (step) {
                event.preventDefault();
                arcs[(index + step + arcs.length) % arcs.length].focus();
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggle(key);
            } else if (event.key === 'Escape') {
                pinned = null;
                render();
            }
        });
    });

    rows.forEach((row) => {
        row.addEventListener('click', () => toggle(row.dataset.seg));
        row.addEventListener('pointerenter', () => {
            hover = row.dataset.seg;
            at = null;
            render();
        });
        row.addEventListener('pointerleave', () => {
            hover = null;
            render();
        });
    });

    // Tapping elsewhere releases the pinned slice (touch has no hover to leave).
    document.addEventListener('pointerdown', (event) => {
        if (pinned && !root.contains(event.target)) {
            pinned = null;
            render();
        }
    });
}

/* ------------------------------------------------------------------------------------------ analytics */

function initAnalytics() {
    const root = document.querySelector('[data-dash-usage]');

    if (!root) {
        return;
    }

    const body = root.querySelector('[data-usage-body]');
    const status = root.querySelector('[data-usage-status]');
    const tabList = root.querySelector('[data-dash-tabs]');
    const tabs = [...tabList.querySelectorAll('[role="tab"]')];
    const periodLinks = [...root.querySelectorAll('[data-usage-period]')];
    const monthField = root.querySelector('[data-usage-month-field]');
    const monthSelect = root.querySelector('[data-usage-month]');
    const periods = periodLinks.map((link) => link.dataset.usagePeriod);
    const hiddenSeries = new Map(); // keeps legend toggles when a filter reloads the panels

    let charts = [];
    let activeTab = 'ai';
    let controller = null;
    let sequence = 0;
    let failed = false;

    root.dataset.dashJs = '';

    /* ---- tabs ---- */

    const placeInk = (instant = false) => {
        const tab = tabs.find((item) => item.getAttribute('aria-selected') === 'true');

        if (!tab) {
            return;
        }

        if (instant) {
            tabList.dataset.instant = '';
        }

        tabList.style.setProperty('--ink-x', `${tab.offsetLeft + 14}px`);
        tabList.style.setProperty('--ink-w', `${Math.max(0, tab.offsetWidth - 28)}px`);
        tabList.dataset.ready = '';

        if (instant) {
            requestAnimationFrame(() => delete tabList.dataset.instant);
        }
    };

    const showPanel = (key, { animate = false } = {}) => {
        activeTab = key;

        tabs.forEach((tab) => {
            const on = tab.dataset.tab === key;

            tab.setAttribute('aria-selected', String(on));
            tab.tabIndex = on ? 0 : -1;
        });

        body.querySelectorAll('[data-dash-panel]').forEach((panel) => {
            const on = panel.dataset.dashPanel === key;

            panel.toggleAttribute('data-active', on);
            panel.removeAttribute('data-enter');

            if (on && animate && !reduced()) {
                panel.setAttribute('data-enter', '');
            }
        });

        placeInk();

        if (animate) {
            requestAnimationFrame(() => requestAnimationFrame(() => charts.forEach((chart) => chart.replay(key))));
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => {
            if (tab.dataset.tab !== activeTab) {
                showPanel(tab.dataset.tab, { animate: true });
            }
        });
        tab.addEventListener('keydown', (event) => {
            const next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 }[event.key];

            if (next === undefined) {
                return;
            }

            event.preventDefault();
            const target = tabs[(next + tabs.length) % tabs.length];

            target.focus();
            showPanel(target.dataset.tab, { animate: true });
        });
    });

    /* ---- charts ---- */

    const mount = ({ animate = false } = {}) => {
        charts.forEach((chart) => chart.destroy());
        charts = [...body.querySelectorAll('[data-dash-chart]')]
            .map((figure) => createChart(figure, hiddenSeries))
            .filter(Boolean);
        showPanel(activeTab, { animate });
    };

    /* ---- period filter ---- */

    const fromUrl = () => {
        const params = new URLSearchParams(location.search);
        const period = periods.includes(params.get('periode')) ? params.get('periode') : 'month';
        const month = [...monthSelect.options].some((option) => option.value === params.get('bulan'))
            ? params.get('bulan')
            : root.dataset.usageDefaultMonth;

        return { period, month: period === 'history' ? month : null };
    };

    let shown = fromUrl();

    const paint = ({ period, month }) => {
        periodLinks.forEach((link) => {
            if (link.dataset.usagePeriod === period) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });

        monthField.hidden = period !== 'history';

        if (month) {
            monthSelect.value = month;
        }
    };

    const pageUrl = ({ period, month }) => {
        const url = new URL(location.href);

        url.searchParams.set('periode', period);

        if (month) {
            url.searchParams.set('bulan', month);
        } else {
            url.searchParams.delete('bulan');
        }

        return url;
    };

    const showError = (next, message) => {
        failed = true;
        charts.forEach((chart) => chart.destroy());
        charts = [];
        body.replaceChildren();

        const box = document.createElement('div');
        const text = document.createElement('p');
        const retry = document.createElement('button');

        box.className = 'bd-dash__usage-error';
        box.setAttribute('role', 'alert');
        text.textContent = message;
        retry.type = 'button';
        retry.className = 'bd-dash__btn bd-dash__btn--primary';
        retry.textContent = 'Coba Lagi';
        retry.addEventListener('click', () => load(next, 'none'));
        box.append(text, retry);
        body.append(box);
        retry.focus({ preventScroll: true });
    };

    // mode: 'push' (user chose a period), 'none' (back/forward or retry: URL already right).
    const load = async (next, mode = 'push') => {
        paint(next);

        if (mode === 'push') {
            history.pushState(next, '', pageUrl(next));
        }

        controller?.abort();
        controller = new AbortController();

        const mine = ++sequence;
        const params = new URLSearchParams({ periode: next.period });

        if (next.month) {
            params.set('bulan', next.month);
        }

        body.classList.add('is-loading');
        body.setAttribute('aria-busy', 'true');
        status.textContent = 'Memuat data analitik…';

        try {
            const response = await fetch(`${root.dataset.usageUrl}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            // An expired admin session redirects to the login page (HTML), not JSON.
            if (response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error('session');
            }

            const data = await response.json();

            if (mine !== sequence) {
                return; // a newer request owns the panel
            }

            if (!response.ok || data.period !== next.period || typeof data.html !== 'string') {
                throw new Error('shape');
            }

            failed = false;
            shown = next;
            charts.forEach((chart) => chart.destroy());
            // Parsed in an inert document, then adopted: nothing in the response can execute.
            body.replaceChildren(...new DOMParser().parseFromString(data.html, 'text/html').body.childNodes);
            mount({ animate: true });
            status.textContent = `Data analitik ${data.label} dimuat.`;
        } catch (error) {
            if (error.name === 'AbortError' || mine !== sequence) {
                return;
            }

            status.textContent = '';
            showError(next, error.message === 'session'
                ? 'Sesi admin berakhir. Muat ulang halaman untuk masuk lagi.'
                : 'Data analitik tidak dapat dimuat. Periksa koneksi lalu coba lagi.');
        } finally {
            if (mine === sequence) {
                body.classList.remove('is-loading');
                body.setAttribute('aria-busy', 'false');
            }
        }
    };

    periodLinks.forEach((link) => link.addEventListener('click', (event) => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return; // let "open in new tab" work on the real link
        }

        event.preventDefault();

        const period = link.dataset.usagePeriod;

        if (period === shown.period && !failed && (period !== 'history' || shown.month === monthSelect.value)) {
            return;
        }

        load({ period, month: period === 'history' ? monthSelect.value : null });
    }));

    monthSelect.addEventListener('change', () => load({ period: 'history', month: monthSelect.value }));

    history.replaceState(shown, '', location.href);
    window.addEventListener('popstate', (event) => load(event.state?.period ? event.state : fromUrl(), 'none'));

    mount();
    placeInk(true);
    new ResizeObserver(() => placeInk(true)).observe(tabList);
    document.fonts?.ready.then(() => placeInk(true));
}

/* --------------------------------------------------------------------------------------------- charts */

const svgEl = (name, attrs, parent) => {
    const node = document.createElementNS(NS, name);

    Object.entries(attrs).forEach(([key, val]) => node.setAttribute(key, val));
    parent?.append(node);

    return node;
};

// Step 1, 2, 5 x 10^n so axis ticks are whole numbers (counts and tokens), four intervals.
const niceTop = (max) => {
    const raw = Math.max(max, 1) / 4;
    const base = 10 ** Math.floor(Math.log10(raw));
    const unit = raw / base;

    return (unit <= 1 ? 1 : unit <= 2 ? 2 : unit <= 5 ? 5 : 10) * base * 4;
};

/**
 * Two-series line chart with pointer / touch scrubbing, keyboard stepping and legend toggles.
 * Reads {series, points} from the figure's data-chart (see admin/partials/dash-chart.blade.php).
 */
function createChart(figure, store) {
    const plot = figure.querySelector('[data-plot]');

    if (!plot) {
        return null;
    }

    let config;

    try {
        config = JSON.parse(figure.dataset.chart);
    } catch {
        return null;
    }

    const { series, points } = config;
    const storeKey = `${figure.closest('[data-dash-panel]')?.dataset.dashPanel}:${figure.querySelector('.bd-dash__chart-title')?.textContent}`;
    const hidden = store.get(storeKey) ?? new Set();

    store.set(storeKey, hidden);

    const svg = svgEl('svg', { 'aria-hidden': 'true', focusable: 'false' });
    const tip = document.createElement('span');
    const live = document.createElement('span');

    tip.className = 'bd-dash__tip';
    tip.hidden = true;
    live.className = 'bd-dash__sr';
    live.setAttribute('role', 'status');
    plot.replaceChildren(svg, tip, live);

    const M = { l: 48, r: 14, t: 14, b: 28 };
    let width = 0;
    let height = 0;
    let layer = null;
    let current = null;
    let timer = 0;

    const visible = () => series.filter((item) => !hidden.has(item.key));
    const xAt = (i) => M.l + (points.length > 1 ? (i / (points.length - 1)) * (width - M.l - M.r) : (width - M.l - M.r) / 2);

    const draw = () => {
        width = plot.clientWidth;
        height = plot.clientHeight;

        if (!width || !height) {
            return; // panel is hidden; ResizeObserver redraws when it becomes visible
        }

        const shownSeries = visible();
        const top = niceTop(Math.max(0, ...points.flatMap((p) => shownSeries.map((item) => p.v[item.key] || 0))));
        const ih = height - M.t - M.b;
        const yAt = (v) => M.t + ih - (v / top) * ih;

        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.replaceChildren();
        current = null;
        tip.hidden = true;

        for (let t = 0; t <= 4; t += 1) {
            const v = (top / 4) * t;

            svgEl('line', { class: 'bd-dash__gridline', x1: M.l, x2: width - M.r, y1: yAt(v), y2: yAt(v) }, svg);
            svgEl('text', { class: 'bd-dash__axis', x: M.l - 8, y: yAt(v), 'text-anchor': 'end', dy: '.32em' }, svg).textContent = compact.format(v);
        }

        const step = Math.max(1, Math.ceil(points.length / Math.max(2, Math.floor((width - M.l - M.r) / 76))));

        points.forEach((point, i) => {
            if (i % step === 0) {
                svgEl('text', { class: 'bd-dash__axis', x: xAt(i), y: height - 6, 'text-anchor': i === 0 ? 'start' : 'middle' }, svg).textContent = point.l;
            }
        });

        layer = svgEl('g', {}, svg);

        shownSeries.forEach((item) => {
            const coords = points.map((p, i) => [xAt(i), yAt(p.v[item.key] || 0)]);

            if (coords.length === 1) {
                svgEl('circle', { class: `bd-dash__mark bd-dash__mark--${item.tone}`, cx: coords[0][0], cy: coords[0][1], r: 4 }, layer);

                return;
            }

            const line = coords.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`).join('');

            svgEl('path', { class: `bd-dash__area bd-dash__area--${item.tone}`, d: `${line}L${xAt(points.length - 1)} ${yAt(0)}L${xAt(0)} ${yAt(0)}Z` }, layer);
            svgEl('path', { class: `bd-dash__line bd-dash__line--${item.tone}`, d: line, pathLength: 1 }, layer);
        });

        svgEl('line', { class: 'bd-dash__guide', y1: M.t, y2: M.t + ih, visibility: 'hidden' }, svg);
        shownSeries.forEach((item) => svgEl('circle', { class: `bd-dash__mark bd-dash__mark--${item.tone}`, r: 4.5, visibility: 'hidden', 'data-mark': item.key }, svg));
    };

    const hide = () => {
        current = null;
        tip.hidden = true;
        svg.querySelector('.bd-dash__guide')?.setAttribute('visibility', 'hidden');
        svg.querySelectorAll('[data-mark]').forEach((mark) => mark.setAttribute('visibility', 'hidden'));
    };

    const show = (index, announce = false) => {
        if (!width || !points.length) {
            return;
        }

        current = index;

        const point = points[index];
        const shownSeries = visible();
        const top = niceTop(Math.max(0, ...points.flatMap((p) => shownSeries.map((item) => p.v[item.key] || 0))));
        const ih = height - M.t - M.b;
        const x = xAt(index);

        const guide = svg.querySelector('.bd-dash__guide');

        guide.setAttribute('x1', x);
        guide.setAttribute('x2', x);
        guide.setAttribute('visibility', 'visible');

        svg.querySelectorAll('[data-mark]').forEach((mark) => {
            mark.setAttribute('cx', x);
            mark.setAttribute('cy', M.t + ih - ((point.v[mark.dataset.mark] || 0) / top) * ih);
            mark.setAttribute('visibility', 'visible');
        });

        tip.replaceChildren();
        const heading = document.createElement('b');

        heading.textContent = point.t;
        tip.append(heading);
        shownSeries.forEach((item) => {
            const row = document.createElement('span');
            const dot = document.createElement('i');

            dot.className = `bd-dash__key-dot bd-dash__key-dot--${item.tone}`;
            row.append(dot, ` ${item.label}: ${nf.format(point.v[item.key] || 0)}`);
            tip.append(row);
        });
        tip.hidden = false;
        tip.style.left = `${clamp(x + 14 + tip.offsetWidth > width ? x - tip.offsetWidth - 14 : x + 14, 0, width - tip.offsetWidth)}px`;
        tip.style.top = `${M.t}px`;

        if (announce) {
            live.textContent = `${point.t}. ${shownSeries.map((item) => `${item.label} ${nf.format(point.v[item.key] || 0)}`).join(', ')}`;
        }
    };

    const nearest = (clientX) => {
        const box = plot.getBoundingClientRect();
        const t = ((clientX - box.left) * (width / box.width) - M.l) / (width - M.l - M.r);

        return clamp(Math.round(t * (points.length - 1)), 0, points.length - 1);
    };

    plot.addEventListener('pointermove', (event) => {
        clearTimeout(timer);
        show(nearest(event.clientX));
    });
    plot.addEventListener('pointerdown', (event) => {
        clearTimeout(timer);
        show(nearest(event.clientX));
    });
    plot.addEventListener('pointerleave', (event) => {
        if (event.pointerType === 'mouse') {
            hide();
        }
    });
    plot.addEventListener('pointerup', (event) => {
        if (event.pointerType !== 'mouse') {
            timer = setTimeout(hide, 2500);
        }
    });
    plot.addEventListener('pointercancel', hide);
    plot.addEventListener('blur', hide);
    plot.addEventListener('keydown', (event) => {
        const last = points.length - 1;
        const next = {
            ArrowRight: (current ?? -1) + 1,
            ArrowLeft: (current ?? last + 1) - 1,
            Home: 0,
            End: last,
        }[event.key];

        if (event.key === 'Escape') {
            hide();
        } else if (next !== undefined) {
            event.preventDefault();
            show(clamp(next, 0, last), true);
        }
    });

    figure.querySelectorAll('[data-series]').forEach((button) => {
        button.setAttribute('aria-pressed', String(!hidden.has(button.dataset.series)));
        button.addEventListener('click', () => {
            const key = button.dataset.series;

            // At least one series stays on, otherwise the chart would be blank.
            if (!hidden.has(key) && visible().length === 1) {
                return;
            }

            hidden.has(key) ? hidden.delete(key) : hidden.add(key);
            button.setAttribute('aria-pressed', String(!hidden.has(key)));
            draw();
            replayLines();
        });
    });

    // Lines draw in from the left once per user action (tab, filter, legend); never on idle.
    const replayLines = () => {
        if (reduced() || !layer?.isConnected) {
            return;
        }

        layer.querySelectorAll('.bd-dash__line').forEach((line) => line.animate(
            [{ strokeDasharray: 1, strokeDashoffset: 1 }, { strokeDasharray: 1, strokeDashoffset: 0 }],
            { duration: 520, easing: EASE },
        ));
        layer.querySelectorAll('.bd-dash__area').forEach((area) => area.animate([{ opacity: 0 }, { opacity: 0.09 }], { duration: 520, easing: EASE }));
    };

    const observer = new ResizeObserver(draw);

    observer.observe(plot);
    draw();

    return {
        replay: (panelKey) => {
            if (figure.closest('[data-dash-panel]')?.dataset.dashPanel === panelKey) {
                replayLines();
            }
        },
        destroy: () => {
            observer.disconnect();
            clearTimeout(timer);
        },
    };
}
