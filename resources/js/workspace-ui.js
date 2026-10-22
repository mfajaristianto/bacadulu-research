const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            callback,
            {
                once: true,
            },
        );

        return;
    }

    callback();
};


const first = (
    selector,
    scope = document,
) =>
    scope.querySelector(
        selector,
    );


const all = (
    selector,
    scope = document,
) => [
    ...scope.querySelectorAll(
        selector,
    ),
];


ready(() => {

    /*
    |--------------------------------------------------------------------------
    | Conversation action menu dipakai juga
    | di halaman History, bukan cuma Workspace.
    |--------------------------------------------------------------------------
    */

    initConversationActions();


    const workspace =
        first(
            '.research-workspace-shell',
        );


    if (!workspace) {
        return;
    }


    fixResearchResultTitle();

    initWorkspaceSwitcher();

    initWorkspaceHelp();

    initPromptModalGuard();
    initResearchSubmitGuard();
    initInlineSavedResult();
    initResearchRunPolling();

});


/* =========================================================================
   FIX RESULT TITLE
   ========================================================================= */

function fixResearchResultTitle() {

    const resultTitle =
        first(
            '.research-result-header h1',
        );


    const activeConversation =
        first(
            '.research-conversation.is-active[data-conversation-title]',
        );


    if (
        !resultTitle
        ||
        !activeConversation
    ) {
        return;
    }


    const title =
        String(
            activeConversation
                .dataset
                .conversationTitle
            || '',
        ).trim();


    if (!title) {
        return;
    }


    /*
     * Jangan lagi tampilkan seluruh question template
     * sebagai heading result.
     */

    resultTitle.textContent =
        title;
}


/* =========================================================================
   PERSONAL WORKSPACE DROPDOWN
   ========================================================================= */

function initWorkspaceSwitcher() {

    const switcher =
        first(
            '.research-workspace-switcher',
        );


    const host =
        first(
            '.research-workspace-topbar__left',
        );


    if (
        !switcher
        ||
        !host
    ) {
        return;
    }


    host.classList.add(
        'has-workspace-menu',
    );


    const sidebarFooterLinks =
        all(
            '.research-sidebar__footer nav a',
        );


    const findSidebarLink = (
        label,
    ) =>
        sidebarFooterLinks
            .find(
                (
                    link,
                ) =>
                    link
                        .textContent
                        .trim()
                        .toLowerCase()
                        .includes(
                            label,
                        ),
            );


    const workspaceUrl =
        switcher.href;


    const savedUrl =
        findSidebarLink(
            'saved',
        )
            ?.href
        || '#';


    const profileUrl =
        findSidebarLink(
            'profile',
        )
            ?.href
        || '#';


    const menu =
        document
            .createElement(
                'div',
            );


    menu.className =
        'research-workspace-menu';


    menu.hidden =
        true;


    menu.setAttribute(
        'role',
        'menu',
    );


    const heading =
        document
            .createElement(
                'div',
            );


    heading.className =
        'research-workspace-menu__heading';


    heading.innerHTML = `
        <span>
            WORKSPACE
        </span>

        <strong>
            Personal Workspace
        </strong>
    `;


    menu.appendChild(
        heading,
    );


    const current =
        document
            .createElement(
                'div',
            );


    current.className =
        'research-workspace-menu__current';


    current.innerHTML = `
        <span
            class="
                research-workspace-menu__current-icon
            "
        >
            ✓
        </span>

        <span>
            <strong>
                Personal Workspace
            </strong>

            <small>
                Research, history, evidence,
                dan dokumen Anda
            </small>
        </span>
    `;


    menu.appendChild(
        current,
    );


    const links = [

        [
            'New Research',
            'Mulai workspace research baru',
            workspaceUrl,
            '＋',
        ],

        [
            'Saved',
            'Buka hasil research yang disimpan',
            savedUrl,
            '☆',
        ],

        [
            'Profile',
            'Pengaturan profil dan identitas',
            profileUrl,
            '○',
        ],

    ];


    const linkWrap =
        document
            .createElement(
                'div',
            );


    linkWrap.className =
        'research-workspace-menu__links';


    links.forEach(
        ([
            label,
            description,
            href,
            icon,
        ]) => {

            const link =
                document
                    .createElement(
                        'a',
                    );


            link.href =
                href;


            link.setAttribute(
                'role',
                'menuitem',
            );


            link.innerHTML = `
                <span
                    class="
                        research-workspace-menu__item-icon
                    "
                >
                    ${icon}
                </span>

                <span
                    class="
                        research-workspace-menu__item-copy
                    "
                >
                    <strong></strong>
                    <small></small>
                </span>

                <b>
                    →
                </b>
            `;


            first(
                'strong',
                link,
            ).textContent =
                label;


            first(
                'small',
                link,
            ).textContent =
                description;


            linkWrap
                .appendChild(
                    link,
                );

        },
    );


    menu.appendChild(
        linkWrap,
    );


    host.appendChild(
        menu,
    );


    const setOpen = (
        open,
    ) => {

        menu.hidden =
            !open;


        switcher
            .classList
            .toggle(
                'is-open',
                open,
            );


        switcher
            .setAttribute(
                'aria-expanded',
                String(open),
            );

    };


    switcher
        .setAttribute(
            'aria-haspopup',
            'menu',
        );


    switcher
        .setAttribute(
            'aria-expanded',
            'false',
        );


    /*
     * Personal Workspace sekarang
     * membuka menu.
     *
     * Tidak lagi langsung mengembalikan
     * user ke /workspace.
     */

    switcher
        .addEventListener(
            'click',
            (
                event,
            ) => {

                event
                    .preventDefault();


                event
                    .stopPropagation();


                const helpPanel =
                    first(
                        '.research-workspace-help-panel',
                    );


                if (helpPanel) {
                    helpPanel.hidden =
                        true;
                }


                setOpen(
                    menu.hidden,
                );

            },
        );


    menu
        .addEventListener(
            'click',
            (
                event,
            ) => {

                event
                    .stopPropagation();

            },
        );


    document
        .addEventListener(
            'click',
            (
                event,
            ) => {

                if (
                    !menu.hidden
                    &&
                    !host.contains(
                        event.target,
                    )
                ) {

                    setOpen(
                        false,
                    );

                }

            },
        );


    document
        .addEventListener(
            'keydown',
            (
                event,
            ) => {

                if (
                    event.key ===
                        'Escape'
                    &&
                    !menu.hidden
                ) {

                    setOpen(
                        false,
                    );


                    switcher
                        .focus();

                }

            },
        );

}


/* =========================================================================
   WORKSPACE HELP
   ========================================================================= */

function initWorkspaceHelp() {

    const helpButton =
        first(
            '.research-topbar-help',
        );


    const host =
        first(
            '.research-workspace-topbar__right',
        );


    if (
        !helpButton
        ||
        !host
    ) {
        return;
    }


    host.classList.add(
        'has-workspace-help',
    );


    const panel =
        document
            .createElement(
                'div',
            );


    panel.className =
        'research-workspace-help-panel';


    panel.hidden =
        true;


    panel.innerHTML = `
        <div
            class="
                research-workspace-help-panel__head
            "
        >

            <div>

                <span>
                    WORKSPACE HELP
                </span>

                <strong>
                    Cara kerja BacaResearch
                </strong>

            </div>


            <button
                type="button"
                data-workspace-help-close
                aria-label="Tutup bantuan"
            >
                ×
            </button>

        </div>


        <div
            class="
                research-workspace-help-panel__steps
            "
        >

            <article>

                <span>
                    01
                </span>

                <div>

                    <strong>
                        Pilih Research Tool
                    </strong>

                    <p>
                        Tentukan jenis analisis seperti
                        Research Gap, Literature Synthesis,
                        atau Theoretical Mapping.
                    </p>

                </div>

            </article>


            <article>

                <span>
                    02
                </span>

                <div>

                    <strong>
                        Tulis pertanyaan yang fokus
                    </strong>

                    <p>
                        Gunakan topik, objek, konteks,
                        dan hubungan penelitian yang
                        ingin dianalisis.
                    </p>

                </div>

            </article>


            <article>

                <span>
                    03
                </span>

                <div>

                    <strong>
                        Periksa evidence
                    </strong>

                    <p>
                        Nilai hasil bersama source dan
                        validated evidence, bukan output
                        AI saja.
                    </p>

                </div>

            </article>

        </div>


        <div
            class="
                research-workspace-help-panel__foot
            "
        >

            <span>
                Tip
            </span>

            Saved dan Profile tersedia dari menu

            <strong>
                Personal Workspace
            </strong>.

        </div>
    `;


    host.appendChild(
        panel,
    );


    const closeButton =
        first(
            '[data-workspace-help-close]',
            panel,
        );


    const setOpen = (
        open,
    ) => {

        panel.hidden =
            !open;


        helpButton
            .classList
            .toggle(
                'is-open',
                open,
            );


        helpButton
            .setAttribute(
                'aria-expanded',
                String(open),
            );

    };


    helpButton
        .setAttribute(
            'aria-haspopup',
            'dialog',
        );


    helpButton
        .setAttribute(
            'aria-expanded',
            'false',
        );


    /*
     * Tombol ? tidak lagi pindah
     * ke halaman Home.
     */

    helpButton
        .addEventListener(
            'click',
            (
                event,
            ) => {

                event
                    .preventDefault();


                event
                    .stopPropagation();


                const workspaceMenu =
                    first(
                        '.research-workspace-menu',
                    );


                if (workspaceMenu) {

                    workspaceMenu.hidden =
                        true;

                }


                setOpen(
                    panel.hidden,
                );

            },
        );


    closeButton
        ?.addEventListener(
            'click',
            () => {

                setOpen(
                    false,
                );


                helpButton
                    .focus();

            },
        );


    panel
        .addEventListener(
            'click',
            (
                event,
            ) => {

                event
                    .stopPropagation();

            },
        );


    document
        .addEventListener(
            'click',
            (
                event,
            ) => {

                if (
                    !panel.hidden
                    &&
                    !host.contains(
                        event.target,
                    )
                ) {

                    setOpen(
                        false,
                    );

                }

            },
        );


    document
        .addEventListener(
            'keydown',
            (
                event,
            ) => {

                if (
                    event.key ===
                        'Escape'
                    &&
                    !panel.hidden
                ) {

                    setOpen(
                        false,
                    );


                    helpButton
                        .focus();

                }

            },
        );

}


/* =========================================================================
   CONVERSATION ACTIONS
   ========================================================================= */

function initConversationActions() {

    const menus =
        all(
            '.conversation-actions',
        );


    if (!menus.length) {
        return;
    }


    menus.forEach(
        (
            menu,
        ) => {

            menu
                .addEventListener(
                    'toggle',
                    () => {

                        if (!menu.open) {
                            return;
                        }


                        menus.forEach(
                            (
                                other,
                            ) => {

                                if (
                                    other
                                    !== menu
                                ) {

                                    other.open =
                                        false;

                                }

                            },
                        );


                        window
                            .requestAnimationFrame(
                                () => {

                                    positionConversationMenu(
                                        menu,
                                    );

                                },
                            );

                    },
                );

        },
    );


    document
        .addEventListener(
            'click',
            (
                event,
            ) => {

                const shareButton =
                    event
                        .target
                        .closest(
                            '[data-conversation-share]',
                        );


                if (shareButton) {

                    event
                        .preventDefault();


                    shareConversation(
                        shareButton,
                    );


                    return;
                }


                const renameCancel =
                    event
                        .target
                        .closest(
                            '[data-conversation-rename-cancel]',
                        );


                if (renameCancel) {

                    event
                        .preventDefault();


                    const disclosure =
                        renameCancel
                            .closest(
                                '.conversation-rename-disclosure',
                            );


                    if (disclosure) {

                        disclosure.open =
                            false;

                    }


                    return;
                }


                const renameSummary =
                    event
                        .target
                        .closest(
                            '.conversation-rename-disclosure > summary',
                        );


                if (renameSummary) {

                    window
                        .requestAnimationFrame(
                            () => {

                                const disclosure =
                                    renameSummary
                                        .parentElement;


                                if (
                                    !disclosure
                                        ?.open
                                ) {
                                    return;
                                }


                                const input =
                                    first(
                                        'input[name="title"]',
                                        disclosure,
                                    );


                                input
                                    ?.focus();


                                input
                                    ?.select();

                            },
                        );


                    return;
                }


                if (
                    !event
                        .target
                        .closest(
                            '.conversation-actions',
                        )
                ) {

                    menus.forEach(
                        (
                            menu,
                        ) => {

                            menu.open =
                                false;

                        },
                    );

                }

            },
        );


    document
        .addEventListener(
            'keydown',
            (
                event,
            ) => {

                if (
                    event.key
                    !==
                    'Escape'
                ) {
                    return;
                }


                menus.forEach(
                    (
                        menu,
                    ) => {

                        menu.open =
                            false;

                    },
                );

            },
        );


    window
        .addEventListener(
            'resize',
            () => {

                menus.forEach(
                    (
                        menu,
                    ) => {

                        if (
                            menu.open
                        ) {

                            positionConversationMenu(
                                menu,
                            );

                        }

                    },
                );

            },
        );


    /*
     * Kalau container/sidebar discroll,
     * menu ditutup supaya tidak menggantung
     * di posisi lama.
     */

    window
        .addEventListener(
            'scroll',
            () => {

                menus.forEach(
                    (
                        menu,
                    ) => {

                        if (
                            menu.open
                        ) {

                            menu.open =
                                false;

                        }

                    },
                );

            },
            true,
        );

}


/* =========================================================================
   MENU POSITION
   ========================================================================= */

function positionConversationMenu(
    details,
) {

    const summary =
        first(
            ':scope > summary',
            details,
        );


    const panel =
        first(
            ':scope > .conversation-actions__menu',
            details,
        );


    if (
        !summary
        ||
        !panel
    ) {
        return;
    }


    const rect =
        summary
            .getBoundingClientRect();


    const panelWidth =
        Math.min(
            268,
            window.innerWidth
                - 24,
        );


    panel.style.width =
        `${panelWidth}px`;


    panel.style.left =
        '12px';


    panel.style.top =
        '12px';


    const panelHeight =
        panel.offsetHeight
        || 360;


    let left =
        rect.right
        -
        panelWidth;


    let top =
        rect.bottom
        +
        8;


    left =
        Math.max(
            12,
            Math.min(
                left,
                window.innerWidth
                    -
                    panelWidth
                    -
                    12,
            ),
        );


    if (
        top
        +
        panelHeight
        >
        window.innerHeight
        -
        12
    ) {

        top =
            Math.max(
                12,
                rect.top
                    -
                    panelHeight
                    -
                    8,
            );

    }


    panel.style.left =
        `${left}px`;


    panel.style.top =
        `${top}px`;

}


/* =========================================================================
   SHARE
   ========================================================================= */

async function shareConversation(
    button,
) {

    const url =
        String(
            button
                .dataset
                .conversationUrl
            || '',
        ).trim();


    const title =
        String(
            button
                .dataset
                .conversationTitle
            ||
            'BacaResearch',
        ).trim();


    if (!url) {

        showWorkspaceToast(
            'Tautan research tidak tersedia.',
            'error',
        );


        return;
    }


    try {

        if (
            navigator.share
        ) {

            await navigator
                .share({
                    title,

                    text:
                        `BacaResearch — ${title}`,

                    url,
                });


            showWorkspaceToast(
                'Research siap dibagikan.',
            );


            closeConversationMenu(
                button,
            );


            return;
        }

    } catch (
        error
    ) {

        if (
            error?.name
            ===
            'AbortError'
        ) {
            return;
        }

    }


    /*
     * Desktop browser biasanya jatuh
     * ke clipboard.
     */

    try {

        await copyText(
            url,
        );


        showWorkspaceToast(
            'Tautan research berhasil disalin.',
        );


        closeConversationMenu(
            button,
        );

    } catch {

        showWorkspaceToast(
            'Tautan belum berhasil disalin.',
            'error',
        );

    }

}


/* =========================================================================
   COPY
   ========================================================================= */

async function copyText(
    value,
) {

    if (
        navigator
            .clipboard
            ?.writeText
    ) {

        await navigator
            .clipboard
            .writeText(
                value,
            );


        return;
    }


    const textarea =
        document
            .createElement(
                'textarea',
            );


    textarea.value =
        value;


    textarea
        .setAttribute(
            'readonly',
            '',
        );


    textarea.style.position =
        'fixed';


    textarea.style.opacity =
        '0';


    document
        .body
        .appendChild(
            textarea,
        );


    textarea
        .select();


    const successful =
        document
            .execCommand(
                'copy',
            );


    textarea
        .remove();


    if (!successful) {

        throw new Error(
            'Copy failed',
        );

    }

}


/* =========================================================================
   CLOSE ACTION MENU
   ========================================================================= */

function closeConversationMenu(
    element,
) {

    const menu =
        element
            .closest(
                '.conversation-actions',
            );


    if (menu) {

        menu.open =
            false;

    }

}


/* =========================================================================
   TOAST
   ========================================================================= */

function showWorkspaceToast(
    message,
    type = 'success',
) {

    let toast =
        first(
            '[data-workspace-toast]',
        );


    if (!toast) {

        toast =
            document
                .createElement(
                    'div',
                );


        toast.className =
            'research-ui-toast';


        toast.dataset
            .workspaceToast =
            '';


        toast
            .setAttribute(
                'role',
                'status',
            );


        toast
            .setAttribute(
                'aria-live',
                'polite',
            );


        document
            .body
            .appendChild(
                toast,
            );

    }


    toast
        .classList
        .remove(
            'is-visible',
            'is-error',
        );


    toast.textContent =
        message;


    toast
        .classList
        .toggle(
            'is-error',
            type ===
                'error',
        );


    window
        .requestAnimationFrame(
            () => {

                toast
                    .classList
                    .add(
                        'is-visible',
                    );

            },
        );


    window
        .clearTimeout(
            showWorkspaceToast
                .timer,
        );


    showWorkspaceToast.timer =
        window
            .setTimeout(
                () => {

                    toast
                        .classList
                        .remove(
                            'is-visible',
                        );

                },
                2400,
            );

}


/* =========================================================================
   PROMPT MODAL BODY LOCK
   ========================================================================= */

function initPromptModalGuard() {

    const menu =
        first(
            '[data-prompt-menu]',
        );


    if (!menu) {
        return;
    }


    const observer =
        new MutationObserver(
            () => {

                document
                    .body
                    .classList
                    .toggle(
                        'research-prompt-modal-open',
                        !menu.hidden,
                    );

            },
        );


    observer
        .observe(
            menu,
            {
                attributes:
                    true,

                attributeFilter: [
                    'hidden',
                    'class',
                ],
            },
        );


    document
        .body
        .classList
        .toggle(
            'research-prompt-modal-open',
            !menu.hidden,
        );

}



/* =========================================================================
   RESEARCH SUBMIT GUARD — prevents double POST / accidental duplicate run
   ========================================================================= */

function initResearchSubmitGuard() {
    const forms = all('[data-chat-form]');

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }

            if (!form.reportValidity()) {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = '1';
            form.classList.add('is-submitting');

            const submit = first('button[type="submit"]', form);
            if (submit) {
                submit.disabled = true;
                submit.setAttribute('aria-busy', 'true');
                submit.setAttribute('aria-label', 'Research sedang diproses');
            }
        });
    });
}

/* =========================================================================
   INLINE SAVED RESULT
   ========================================================================= */

function initInlineSavedResult(scope = document) {
    const forms = all('[data-inline-save-form]', scope);

    if (!forms.length) {
        return;
    }

    forms.forEach((form) => {
        const button = first('button[type="submit"]', form);
        const label = first('[data-inline-save-label]', form);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!button || button.disabled) {
                return;
            }

            const originalLabel = label?.textContent || 'Saved';
            button.disabled = true;
            button.classList.add('is-loading');

            if (label) {
                label.textContent = 'Menyimpan…';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || 'Hasil belum dapat disimpan.');
                }

                button.classList.remove('is-loading');
                button.classList.add('is-saved');
                button.disabled = true;
                button.setAttribute('aria-label', 'Hasil research sudah tersimpan');

                if (label) {
                    label.textContent = 'Saved';
                }

                showWorkspaceToast(payload.message || 'Tersimpan ke Saved.');

                document.dispatchEvent(new CustomEvent('bacaresearch:saved', {
                    detail: payload,
                }));
            } catch (error) {
                button.disabled = false;
                button.classList.remove('is-loading');

                if (label) {
                    label.textContent = originalLabel;
                }

                showWorkspaceToast(
                    error instanceof Error ? error.message : 'Gagal menyimpan hasil.',
                    'error',
                );
            }
        });
    });
}


/* =========================================================================
   ASYNC RESEARCH STATUS — no full-page refresh loop
   ========================================================================= */

function initResearchRunPolling() {
    const host = first('[data-research-run-status]');

    if (!host) {
        return;
    }

    const statusUrl = String(host.dataset.researchRunStatus || '').trim();
    const currentStatus = String(host.dataset.researchRunState || '').trim();
    const terminalStates = new Set([
        'completed',
        'insufficient_evidence',
        'citation_failed',
        'failed',
    ]);

    if (!statusUrl || terminalStates.has(currentStatus)) {
        return;
    }

    const progressBar = first('[data-research-live-progress]', host);
    const progressLabel = first('[data-research-live-label]', host);
    const retryLink = first('[data-research-live-retry]', host);

    let attempts = 0;
    let timeoutId = null;
    let stopped = false;
    const maxAttempts = 90; // about three minutes at 2s interval

    const stop = () => {
        stopped = true;
        window.clearTimeout(timeoutId);
    };

    const schedule = () => {
        if (stopped) {
            return;
        }

        timeoutId = window.setTimeout(poll, document.hidden ? 4500 : 2000);
    };

    const applyProgress = (payload) => {
        const progress = Math.max(0, Math.min(100, Number(payload.progress || 0)));

        if (progressBar) {
            progressBar.style.setProperty('--research-live-progress', `${progress}%`);
            progressBar.setAttribute('aria-valuenow', String(progress));
        }

        if (progressLabel) {
            const labels = {
                queued: 'Menyiapkan research…',
                retrieving: 'Mengambil source dan evidence…',
                evidence_ready: 'Evidence siap, membandingkan temuan…',
                generating: 'Menyusun jawaban dengan sitasi…',
                completed: 'Research selesai.',
                insufficient_evidence: 'Evidence belum cukup.',
                citation_failed: 'Validasi sitasi perlu diperiksa.',
                failed: 'Research gagal diproses.',
            };

            progressLabel.textContent = labels[payload.status] || 'Memproses research…';
        }
    };

    const poll = async () => {
        attempts += 1;

        try {
            const response = await fetch(statusUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error(`Status request failed: ${response.status}`);
            }

            const payload = await response.json();
            applyProgress(payload);

            if (payload.terminal) {
                stop();

                // Update the current result in-place without a full-page reload.
                // The backend continues to be the source of truth for evidence.
                try {
                    const page = await fetch(window.location.href, {
                        method: 'GET', credentials: 'same-origin', cache: 'no-store',
                        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!page.ok) throw new Error('Halaman hasil tidak tersedia');
                    const html = new DOMParser().parseFromString(await page.text(), 'text/html');
                    const incoming = html.querySelector('.research-result-layout');
                    const current = document.querySelector('.research-result-layout');
                    if (!incoming || !current) throw new Error('Hasil belum dapat ditampilkan');
                    const incomingProcess = html.querySelector('.research-result-process');
                    if (incomingProcess) host.replaceWith(incomingProcess);
                    current.replaceWith(incoming);
                    initInlineSavedResult(incoming);
                    document.dispatchEvent(new CustomEvent('bacaresearch:result-updated'));
                } catch {
                    host.classList.add('is-stalled');
                    if (progressLabel) progressLabel.textContent = 'Proses selesai, tetapi hasil belum berhasil dimuat.';
                    if (retryLink) {
                        retryLink.textContent = 'Buka hasil';
                        retryLink.hidden = false;
                    }
                }
                return;
            }
        } catch {
            // A temporary network failure should not cause a reload storm.
        }

        if (attempts >= maxAttempts) {
            stop();
            host.classList.add('is-stalled');

            if (progressLabel) {
                progressLabel.textContent = 'Proses belum selesai. Worker mungkin belum aktif.';
            }

            if (retryLink) {
                retryLink.hidden = false;
            }

            return;
        }

        schedule();
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !stopped) {
            window.clearTimeout(timeoutId);
            poll();
        }
    });

    poll();
}
