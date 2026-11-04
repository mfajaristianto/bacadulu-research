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
    initFirstResearch();

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


/* =========================================================================
   FIRST RESEARCH EXPERIENCE
   - Chip Research Tool = tampilan lain dari menu prompt existing: klik chip
     meneruskan klik ke opsi [data-prompt-id], jadi nilai prompt_template_id
     tetap ditentukan logic existing (app.js initPromptPicker).
   - "Coba Contoh" = BUBU GUIDED CURSOR TOUR: satu state machine berbasis
     langkah (intro → judul → kategori → tool → tombol → alur → selesai).
     Langkah UI 1–4 menunggu user menekan "Lanjut"; hanya simulasi alur
     (langkah 5) yang berjalan otomatis. Ghost cursor (desktop) / tap
     indicator (touch) mengikuti posisi elemen asli lewat
     getBoundingClientRect. Murni UI di client: TIDAK ada fetch/XHR/submit;
     research sungguhan hanya berjalan lewat tombol "Mulai Research".
   ========================================================================= */

const FR_DEMO_TOOLS = ['Research Gap', 'Literature Synthesis'];
const FR_CATEGORY_HINTS = ['keuangan', 'ekonomi', 'manajemen', 'bisnis', 'akuntansi'];

const FR_BUBU_COPY = {
    search: 'Mulai dari judul atau topikmu.',
    reading: 'Membaca sumber yang relevan…',
    processing: 'Menganalisis evidence…',
    verified: 'Siap. Sekarang kamu bisa mulai research.',
};

const FR_FLOW = ['title', 'tool', 'source', 'reading', 'evidence', 'insight'];

// Indeks langkah tour.
const FR_INTRO = 0;
const FR_TITLE = 1;
const FR_CATEGORY = 2;
const FR_TOOL = 3;
const FR_SUBMIT = 4;
const FR_FLOWSTEP = 5;
const FR_COMPLETE = 6;

function initFirstResearch() {

    const root = first('[data-first-research]');

    if (!root) {
        return;
    }

    const input = first('[data-chat-input]', root);
    const select = first('#fr-category', root);
    const hidden = first('[data-prompt-input]', root);
    const menu = first('[data-prompt-menu]', root);
    const label = first('[data-prompt-label]', root);
    const chips = all('[data-fr-tool]', root);
    const toolDesc = first('[data-fr-tool-desc]', root);
    const copy = first('[data-fr-copy]', root);
    const sources = first('[data-fr-sources]', root);
    const flowList = first('.fr-flow', root);
    const demoButton = first('[data-fr-demo]', root);
    const submit = first('button[type="submit"]', root);
    const bubus = all('[data-bubu]', root);
    const steps = Object.fromEntries(
        all('[data-fr-step]', root).map((node) => [node.dataset.frStep, node]),
    );

    const tour = first('[data-fr-tour]', root);
    const spot = first('[data-fr-spot]', root);
    const pointer = first('[data-fr-pointer]', root);
    const bubble = first('[data-fr-bubble]', root);
    const bubbleCopy = first('[data-fr-bubble-copy]', root);
    const bubbleNote = first('[data-fr-bubble-note]', root);
    const bubbleCount = first('[data-fr-bubble-count]', root);
    const bubbleBubus = all('[data-fr-bubble-bubu]', root);
    const btn = {
        start: first('[data-fr-start]', root),
        next: first('[data-fr-next]', root),
        back: first('[data-fr-back]', root),
        skip: first('[data-fr-skip]', root),
        keep: first('[data-fr-keep]', root),
        own: first('[data-fr-own]', root),
    };

    const options = menu ? all('[data-prompt-id]', menu) : [];
    const demoTitle = root.dataset.demoTitle || '';
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const touchMode = () =>
        window.matchMedia('(pointer: coarse)').matches || window.innerWidth <= 900;

    // ---- Research Tool: chip <-> menu prompt existing ----------------------

    const syncChips = () => {
        const selected = options.find((option) => option.getAttribute('aria-selected') === 'true');
        const id = selected?.dataset.promptId || '';

        chips.forEach((chip) => {
            chip.setAttribute('aria-pressed', String(chip.dataset.frTool === id && id !== ''));
        });

        if (toolDesc) {
            toolDesc.textContent = selected?.dataset.promptDescription || 'Pilih satu jenis analisis.';
        }
    };

    chips.forEach((chip) => {
        chip.addEventListener('click', () => {
            options.find((option) => option.dataset.promptId === chip.dataset.frTool)?.click();
        });
    });

    if (menu) {
        new MutationObserver(syncChips).observe(menu, {
            subtree: true,
            attributes: true,
            attributeFilter: ['aria-selected'],
        });
    }

    // stopPropagation: klik "luar" tidak boleh langsung menutup menu yang baru dibuka.
    first('[data-fr-tools-info]', root)?.addEventListener('click', (event) => {
        event.stopPropagation();
        first('[data-prompt-open]', root)?.click();
    });

    const clearTool = () => {
        options.forEach((option) => option.setAttribute('aria-selected', 'false'));

        if (hidden) {
            hidden.value = '';
        }

        if (label) {
            label.textContent = 'Pilih research prompt';
        }

        syncChips();
    };

    const selectedToolId = () => hidden?.value || '';

    const chipById = (id) => chips.find((chip) => chip.dataset.frTool === String(id));

    const demoChip = () =>
        FR_DEMO_TOOLS
            .map((name) => chips.find((chip) => chip.dataset.frToolName === name))
            .find(Boolean)
        || chips[0];

    const demoCategory = () => {
        const real = all('option', select).filter((option) => option.value !== '');

        return real.find((option) =>
            FR_CATEGORY_HINTS.some((hint) => option.textContent.toLowerCase().includes(hint)),
        ) || real[0];
    };

    // ---- Bubu (kartu pemandu + avatar di bubble) + mini flow ------------------

    const loadBubus = () => {
        bubus.forEach((img) => {
            if (!img.getAttribute('src') && img.dataset.src) {
                img.src = img.dataset.src;
            }
        });

        bubbleBubus.forEach((img) => {
            const guide = bubus.find((item) => item.dataset.bubu === img.dataset.frBubbleBubu);
            const url = guide?.getAttribute('src') || guide?.dataset.src;

            if (url && !img.getAttribute('src')) {
                img.src = url;
            }
        });
    };

    const setBubu = (name) => {
        loadBubus();

        bubus.forEach((img) => img.classList.toggle('is-on', img.dataset.bubu === name));
        bubbleBubus.forEach((img) => img.classList.toggle('is-on', img.dataset.frBubbleBubu === name));

        if (copy) {
            copy.textContent = FR_BUBU_COPY[name];
        }
    };

    // actives: daftar langkah flow yang menyala; doneUpTo: indeks terakhir yang ✓.
    const setFlow = (actives = [], doneUpTo = -1) => {
        FR_FLOW.forEach((name, index) => {
            const node = steps[name];

            if (!node) {
                return;
            }

            node.classList.toggle('is-done', index <= doneUpTo);
            node.classList.toggle('is-active', actives.includes(name));
        });

        sources?.classList.toggle('is-on', actives.includes('source'));
    };

    // ---- Tour: state machine -------------------------------------------------

    let index = -1;
    let active = false;
    let token = 0;
    let snapshot = null;
    let current = null;
    let waiter = null;
    let raf = 0;
    let moveFrame = 0;
    let pointerEl = null;
    let pointerId = 0;

    const wait = (ms) => new Promise((resolve) => {
        waiter = {
            resolve,
            id: window.setTimeout(() => {
                waiter = null;
                resolve(true);
            }, ms),
        };
    });

    // Satu-satunya jalur pembatalan: timer tunggu, rAF mengetik, dan token.
    const bump = () => {
        token += 1;

        if (waiter) {
            window.clearTimeout(waiter.id);
            waiter.resolve(false);
            waiter = null;
        }

        window.cancelAnimationFrame(raf);

        return token;
    };

    const typeTitle = (run) => new Promise((resolve) => {
        const total = demoTitle.length;
        const duration = reduced() ? 0 : 1500;
        const started = performance.now();

        const frame = (now) => {
            if (run !== token) {
                resolve(false);

                return;
            }

            const count = duration
                ? Math.min(total, Math.ceil(((now - started) / duration) * total))
                : total;

            input.value = demoTitle.slice(0, count);
            input.dispatchEvent(new Event('input', { bubbles: true }));

            if (count < total) {
                raf = window.requestAnimationFrame(frame);

                return;
            }

            resolve(true);
        };

        raf = window.requestAnimationFrame(frame);
    });

    const dispatchValue = (field) => {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // Keadaan nilai form setelah langkah k selesai (k = -1/0: kondisi awal user).
    const applyUpTo = (k) => {
        if (!snapshot) {
            return;
        }

        // Judul contoh yang pernah dipertahankan = nilai demo: kategori/tool-nya ikut dibersihkan.
        const fromDemo = snapshot.text === demoTitle;
        const baseText = fromDemo ? '' : snapshot.text;
        const baseCategory = fromDemo ? '' : snapshot.category;
        const baseTool = fromDemo ? '' : snapshot.tool;

        input.value = k >= FR_TITLE ? demoTitle : baseText;
        dispatchValue(input);

        if (select) {
            const category = demoCategory();

            select.value = k >= FR_CATEGORY && category ? category.value : baseCategory;
            dispatchValue(select);
        }

        const chip = demoChip();

        if (k >= FR_TOOL) {
            if (chip && selectedToolId() !== chip.dataset.frTool) {
                chip.click();
            }
        } else if (baseTool) {
            if (selectedToolId() !== baseTool) {
                chipById(baseTool)?.click();
            }
        } else if (selectedToolId() !== '') {
            clearTool();
        }

        setFlow([], k >= FR_TOOL ? 1 : (k >= FR_TITLE ? 0 : -1));
    };

    const safe = () => ({
        top: (first('.research-workspace-topbar')?.getBoundingClientRect().bottom || 72) + 8,
        bottom: touchMode() ? 92 : 16,
    });

    // Gulir halus sampai target terlihat, lalu tunggu posisinya stabil.
    const ensureVisible = (el) => new Promise((resolve) => {
        const rect = el.getBoundingClientRect();
        const bounds = safe();

        if (rect.top >= bounds.top && rect.bottom <= window.innerHeight - bounds.bottom) {
            resolve();

            return;
        }

        el.scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' });

        const started = performance.now();
        let last = null;
        let still = 0;

        const poll = () => {
            const top = el.getBoundingClientRect().top;

            still = last !== null && Math.abs(top - last) < 0.5 ? still + 1 : 0;
            last = top;

            if (still >= 4 || performance.now() - started > 1400) {
                resolve();

                return;
            }

            raf = window.requestAnimationFrame(poll);
        };

        raf = window.requestAnimationFrame(poll);
    });

    const pointFor = (el, id) => {
        const rect = el.getBoundingClientRect();

        if (touchMode()) {
            return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
        }

        if (id === FR_TITLE) {
            return { x: rect.left + Math.min(72, rect.width * 0.2), y: rect.top + rect.height / 2 + 8 };
        }

        if (id === FR_CATEGORY) {
            return { x: rect.right - 56, y: rect.top + rect.height / 2 + 8 };
        }

        return { x: rect.left + rect.width / 2 + 14, y: rect.top + rect.height / 2 + 10 };
    };

    const move = (el, x, y, animate) => {
        el.classList.toggle('is-instant', !animate);
        el.style.transform = `translate(${x}px, ${y}px)`;
    };

    const placeBubble = (rect, animate) => {
        const margin = 12;

        // Bubble boleh sementara menutupi topbar/bottom-nav (target yang disorot tetap terlihat).
        const bounds = { top: 8, bottom: 8 };
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const w = bubble.offsetWidth;
        const h = bubble.offsetHeight;
        const clampX = (x) => Math.max(margin, Math.min(x, vw - w - margin));
        const clampY = (y) => Math.max(bounds.top, Math.min(y, vh - bounds.bottom - h));
        const centerX = clampX(rect.left + rect.width / 2 - w / 2);

        const below = { x: centerX, y: rect.bottom + 14 };
        const above = { x: centerX, y: rect.top - h - 14 };
        const right = { x: rect.right + 14, y: clampY(rect.top + rect.height / 2 - h / 2) };
        const left = { x: rect.left - w - 14, y: clampY(rect.top + rect.height / 2 - h / 2) };

        const fits = {
            below: below.y + h <= vh - bounds.bottom,
            above: above.y >= bounds.top,
            right: right.x + w <= vw - margin,
            left: left.x >= margin,
        };

        const lower = rect.top + rect.height / 2 > vh * 0.55;
        const order = touchMode()
            ? (lower ? ['above', 'below'] : ['below', 'above'])
            : (lower ? ['above', 'below', 'right', 'left'] : ['below', 'above', 'right', 'left']);

        const spots = { below, above, right, left };
        let choice = order.find((key) => fits[key]);

        // Desktop dua kolom: langkah form → bubble di kolom kanan agar field tidak tertutup.
        const formRect = first('.fr-form', root)?.getBoundingClientRect();

        if (!touchMode() && formRect && index >= FR_TITLE && index <= FR_SUBMIT
            && formRect.right + 24 + w <= vw - margin) {
            spots.beside = { x: formRect.right + 24, y: clampY(rect.top + rect.height / 2 - h / 2) };
            choice = 'beside';
        }

        // Tidak ada ruang di sekitar target: tempel di atas/bawah layar.
        const pos = choice
            ? spots[choice]
            : {
                x: clampX(vw / 2 - w / 2),
                y: rect.top + rect.height / 2 > vh / 2
                    ? bounds.top
                    : vh - bounds.bottom - h,
            };

        move(bubble, Math.round(pos.x), Math.round(pos.y), animate);
    };

    const targetFor = (i) => {
        switch (i) {
            case FR_TITLE: return input;
            case FR_CATEGORY: return select;
            case FR_TOOL: return demoChip();
            case FR_SUBMIT: return submit;
            case FR_FLOWSTEP:
            case FR_COMPLETE: return flowList;
            default: return demoButton;
        }
    };

    // Langkah alur menyorot daftar flow + chip source (tanpa blok bantuan) agar muat di layar pendek.
    const rectOf = (el) => {
        const rect = el.getBoundingClientRect();

        // Chip source hanya ikut disorot saat sedang tampil (fase membaca).
        if (el !== flowList || !sources || !sources.classList.contains('is-on')) {
            return rect;
        }

        const extra = sources.getBoundingClientRect();
        const left = Math.min(rect.left, extra.left);
        const top = Math.min(rect.top, extra.top);
        const right = Math.max(rect.right, extra.right);
        const bottom = Math.max(rect.bottom, extra.bottom);

        return { left, top, right, bottom, width: right - left, height: bottom - top };
    };

    // Posisi spotlight + bubble dari DOM aktual (dipanggil ulang saat scroll/resize).
    const place = (animate) => {
        if (!active || !current) {
            return;
        }

        const rect = rectOf(current);
        const showSpot = index >= FR_TITLE && index <= FR_FLOWSTEP;

        spot.hidden = !showSpot;

        // Penempatan pertama langsung di posisi (tanpa meluncur dari pojok).
        const glide = animate && bubble.dataset.placed === '1';

        bubble.dataset.placed = '1';

        if (showSpot) {
            spot.classList.toggle('is-instant', !glide);
            spot.style.width = `${Math.round(rect.width + 12)}px`;
            spot.style.height = `${Math.round(rect.height + 12)}px`;
            spot.style.transform = `translate(${Math.round(rect.left - 6)}px, ${Math.round(rect.top - 6)}px)`;
        }

        placeBubble(rect, glide);
    };

    const onViewportChange = () => {
        if (moveFrame) {
            return;
        }

        moveFrame = window.requestAnimationFrame(() => {
            moveFrame = 0;
            place(false);

            // Pointer ikut elemen yang sedang disorot (bukan koordinat tetap).
            if (!pointer.hidden && pointerEl) {
                const point = pointFor(pointerEl, pointerId);

                move(pointer, Math.round(point.x), Math.round(point.y), false);
            }
        });
    };

    const onKey = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeTour({ restore: true, focus: demoButton });
        }
    };

    const movePointer = async (run, el, id, from) => {
        pointer.dataset.mode = touchMode() ? 'tap' : 'cursor';

        const target = pointFor(el, id);

        if (pointer.hidden) {
            const start = from || target;

            pointer.hidden = false;
            move(pointer, Math.round(start.x), Math.round(start.y), false);

            // paksa reflow agar transisi ke target terbaca
            void pointer.offsetWidth;
        }

        const animate = !reduced();

        move(pointer, Math.round(target.x), Math.round(target.y), animate);

        if (animate && !await wait(680)) {
            return false;
        }

        pointerEl = el;
        pointerId = id;

        return run === token;
    };

    const pulse = async (run) => {
        if (reduced()) {
            return true;
        }

        pointer.classList.remove('is-click');
        void pointer.offsetWidth;
        pointer.classList.add('is-click');

        return await wait(460) && run === token;
    };

    const hidePointer = () => {
        pointer.hidden = true;
        pointer.classList.remove('is-click');
        pointerEl = null;
    };

    // Dibaca saat dipanggil (bukan saat init): konstanta FR_* ada di bawah modul.
    const copyFor = (i) => {
        switch (i) {
            case FR_INTRO:
                return 'Yuk, aku tunjukin cara mulai research.';
            case FR_TITLE:
                return 'Mulai dari judul atau topik yang ingin kamu teliti.';
            case FR_CATEGORY:
                return 'Pilih kategori supaya pencarian sumber lebih terarah.';
            case FR_TOOL: {
                const name = demoChip()?.dataset.frToolName || 'Research Tool';

                return `Lalu pilih bantuan yang kamu butuhkan. Misalnya, ${name === 'Research Gap' ? 'mencari Research Gap' : name}.`;
            }
            case FR_SUBMIT:
                return 'Kalau tombol ini kamu tekan, barulah BacaDulu mulai memproses research.';
            case FR_COMPLETE:
                return 'Sekarang giliran kamu.';
            default:
                return '';
        }
    };

    const renderBubble = (i, text, bubu) => {
        bubbleCopy.textContent = text;

        bubbleNote.hidden = i !== FR_SUBMIT;
        bubbleNote.textContent = i === FR_SUBMIT ? 'Demo ini belum mengirim apa pun.' : '';

        bubbleCount.textContent = i >= FR_TITLE ? `${Math.min(i, FR_FLOWSTEP)} / 5` : '';

        btn.start.hidden = i !== FR_INTRO;
        btn.back.hidden = i < FR_TITLE;
        btn.next.hidden = i < FR_TITLE || i > FR_FLOWSTEP;
        btn.skip.hidden = i === FR_COMPLETE;
        btn.keep.hidden = i !== FR_COMPLETE;
        btn.own.hidden = i !== FR_COMPLETE;

        setBubu(bubu);
    };

    const focusPrimary = (i) => {
        const target = i === FR_INTRO ? btn.start : (i === FR_COMPLETE ? btn.keep : btn.next);

        target?.focus({ preventScroll: true });
    };

    const runFlow = async (run) => {
        const slow = reduced() ? 0.35 : 1;
        const phases = [
            ['reading', 'Pertama, BacaDulu membaca sumber yang relevan.', ['source', 'reading'], 1, 1500],
            ['processing', 'Evidence dianalisis sesuai Research Tool yang kamu pilih.', ['evidence'], 3, 1800],
            ['verified', 'Setelah selesai, kamu bisa memeriksa evidence dan hasilnya.', [], 5, 1300],
        ];

        for (const [bubu, text, actives, doneUpTo, duration] of phases) {
            if (run !== token) {
                return;
            }

            renderBubble(FR_FLOWSTEP, text, bubu);
            setFlow(actives, doneUpTo);
            place(false);

            if (!await wait(duration * slow)) {
                return;
            }
        }

        if (run === token) {
            enterStep(FR_COMPLETE);
        }
    };

    async function enterStep(i, { animate = true } = {}) {
        const run = bump();

        index = i;
        current = targetFor(i);

        // Kembali/lanjut: nilai form selalu konsisten dengan langkah yang ditampilkan.
        applyUpTo(animate ? i - 1 : i);
        renderBubble(i, copyFor(i), i === FR_COMPLETE ? 'verified' : 'search');

        if (i === FR_COMPLETE) {
            setFlow([], FR_FLOW.length - 1);
        }

        if (i === FR_INTRO || i === FR_COMPLETE) {
            hidePointer();
        }

        await ensureVisible(current);

        if (run !== token) {
            return;
        }

        place(animate && !reduced());
        focusPrimary(i);

        if (i === FR_INTRO || i === FR_COMPLETE) {
            return;
        }

        if (i === FR_FLOWSTEP) {
            hidePointer();
            runFlow(run);

            return;
        }

        const from = pointer.hidden
            ? (() => {
                const rect = demoButton.getBoundingClientRect();

                return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
            })()
            : null;

        if (!await movePointer(run, current, i, from) || !await pulse(run)) {
            return;
        }

        if (i === FR_TITLE && animate && input.value !== demoTitle) {
            input.readOnly = true;

            const done = await typeTitle(run);

            input.readOnly = false;

            if (!done) {
                return;
            }
        }

        applyUpTo(i);

        // Memilih tool lewat menu existing memindahkan fokus; kembalikan ke bubble.
        focusPrimary(i);
    }

    function startTour() {
        if (!input || !chips.length || !tour) {
            return;
        }

        if (!active) {
            snapshot = {
                text: input.value,
                category: select ? select.value : '',
                tool: selectedToolId(),
            };

            active = true;
            tour.hidden = false;
            delete bubble.dataset.placed;
            root.dataset.tourActive = '';
            submit.disabled = true;

            window.addEventListener('resize', onViewportChange);
            window.addEventListener('scroll', onViewportChange, true);
            document.addEventListener('keydown', onKey);
        }

        loadBubus();
        hidePointer();
        enterStep(FR_INTRO);
    }

    function closeTour({ restore = false, focus = null } = {}) {
        if (!active) {
            return;
        }

        bump();

        active = false;
        current = null;
        pointerEl = null;
        tour.hidden = true;
        delete root.dataset.tourActive;
        hidePointer();
        spot.hidden = true;
        input.readOnly = false;
        submit.disabled = false;

        window.removeEventListener('resize', onViewportChange);
        window.removeEventListener('scroll', onViewportChange, true);
        document.removeEventListener('keydown', onKey);

        if (restore) {
            applyUpTo(FR_INTRO);
        }

        setFlow([], -1);
        setBubu('search');

        snapshot = null;
        index = -1;

        focus?.focus({ preventScroll: false });
    }

    demoButton?.addEventListener('click', startTour);
    btn.start?.addEventListener('click', () => enterStep(FR_TITLE));
    btn.next?.addEventListener('click', () => enterStep(Math.min(index + 1, FR_COMPLETE)));
    btn.back?.addEventListener('click', () => enterStep(Math.max(index - 1, FR_INTRO), { animate: index - 1 === FR_FLOWSTEP }));
    btn.skip?.addEventListener('click', () => closeTour({ restore: true, focus: demoButton }));

    // Gunakan contoh ini: nilai tetap di form; user tetap menekan "Mulai Research".
    btn.keep?.addEventListener('click', () => {
        closeTour();

        submit?.scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' });
        submit?.focus({ preventScroll: true });
    });

    // Mulai dengan topik saya: kembalikan kondisi sebelum tour.
    btn.own?.addEventListener('click', () => {
        closeTour({ restore: true });
        input.focus();
    });

    // Tour tidak boleh tertinggal saat halaman disembunyikan/ditinggalkan.
    window.addEventListener('pagehide', () => closeTour());

    syncChips();
}
