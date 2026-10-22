(() => {
    'use strict';

    const reducedMotion =
        window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );

    const sleep =
        (ms) =>
            new Promise(
                (resolve) =>
                    window.setTimeout(
                        resolve,
                        ms,
                    ),
            );


    const csrfToken =
        () =>
            document
                .querySelector(
                    'meta[name="csrf-token"]',
                )
                ?.getAttribute(
                    'content',
                )
            ||
            document
                .querySelector(
                    'input[name="_token"]',
                )
                ?.value
            ||
            '';


    const escapeHtml =
        (value) =>
            String(
                value
                ??
                '',
            )
                .replaceAll(
                    '&',
                    '&amp;',
                )
                .replaceAll(
                    '<',
                    '&lt;',
                )
                .replaceAll(
                    '>',
                    '&gt;',
                )
                .replaceAll(
                    '"',
                    '&quot;',
                )
                .replaceAll(
                    "'",
                    '&#039;',
                );


    const stripInlineMarkdown =
        (value) =>
            String(
                value
                ??
                '',
            )
                .replace(
                    /^\s{0,3}#{1,6}\s+/gm,
                    '',
                )
                .replace(
                    /\*\*(.*?)\*\*/g,
                    '$1',
                )
                .replace(
                    /__(.*?)__/g,
                    '$1',
                )
                .replace(
                    /`([^`]+)`/g,
                    '$1',
                );


    const simpleTextHtml =
        (value) => {
            const text =
                stripInlineMarkdown(
                    value,
                )
                    .trim();


            if (
                !text
            ) {
                return `
                    <p class="is-empty-copy">
                        Bagian ini masih kosong.
                    </p>
                `;
            }


            return text
                .split(
                    /\n{2,}/,
                )
                .map(
                    (block) => `
                        <p>
                            ${
                                escapeHtml(
                                    block,
                                )
                                    .replaceAll(
                                        '\n',
                                        '<br>',
                                    )
                            }
                        </p>
                    `,
                )
                .join('');
        };


    /* ============================================================
       CREATE DOCUMENT
       ============================================================ */

    const createForm =
        document.querySelector(
            '[data-document-create-form]',
        );

    const createDialog =
        document.querySelector(
            '[data-document-confirm]',
        );

    const draftLoading =
        document.querySelector(
            '[data-document-draft-loading]',
        );


    if (
        createForm
        &&
        createDialog
    ) {

        const citationSelect =
            createForm
                .querySelector(
                    '[name="citation_style"]',
                );


        const citationChoices = [
            ...document.querySelectorAll(
                '[data-doc-cite-choice]',
            ),
        ];


        const createDraftButton =
            createDialog
                .querySelector(
                    '[data-doc-create-draft]',
                );


        const backButton =
            createDialog
                .querySelector(
                    '[data-doc-back]',
                );


        const syncCitationCards =
            () => {

                citationChoices
                    .forEach(
                        (
                            choice,
                        ) => {

                            choice
                                .setAttribute(
                                    'aria-pressed',

                                    String(
                                        choice
                                            .dataset
                                            .docCiteChoice
                                        ===
                                        citationSelect
                                            ?.value,
                                    ),
                                );

                        },
                    );

            };


        citationChoices
            .forEach(
                (
                    choice,
                ) => {

                    choice
                        .addEventListener(
                            'click',

                            () => {

                                if (
                                    !citationSelect
                                ) {
                                    return;
                                }


                                citationSelect
                                    .value =
                                    choice
                                        .dataset
                                        .docCiteChoice
                                    ||
                                    '';


                                citationSelect
                                    .dispatchEvent(
                                        new Event(
                                            'change',
                                            {
                                                bubbles:
                                                    true,
                                            },
                                        ),
                                    );


                                syncCitationCards();

                            },
                        );

                },
            );


        citationSelect
            ?.addEventListener(
                'change',
                syncCitationCards,
            );


        syncCitationCards();


        createForm
            .addEventListener(
                'submit',

                (
                    event,
                ) => {

                    event
                        .preventDefault();


                    if (
                        !createForm
                            .reportValidity()
                    ) {
                        return;
                    }


                    const get =
                        (
                            name,
                        ) =>
                            createForm
                                .querySelector(
                                    `[name="${name}"]`,
                                )
                                ?.value
                            ||
                            '—';


                    const title =
                        createDialog
                            .querySelector(
                                '[data-doc-review-title]',
                            );


                    const type =
                        createDialog
                            .querySelector(
                                '[data-doc-review-type]',
                            );


                    const citation =
                        createDialog
                            .querySelector(
                                '[data-doc-review-citation]',
                            );


                    if (
                        title
                    ) {
                        title
                            .textContent =
                            get(
                                'title',
                            );
                    }


                    if (
                        type
                    ) {
                        type
                            .textContent =
                            get(
                                'document_type',
                            );
                    }


                    if (
                        citation
                    ) {
                        citation
                            .textContent =
                            get(
                                'citation_style',
                            );
                    }


                    if (
                        typeof
                            createDialog
                                .showModal
                        ===
                        'function'
                    ) {
                        createDialog
                            .showModal();
                    }

                },
            );


        backButton
            ?.addEventListener(
                'click',

                () => {

                    createDialog
                        .close();


                    createForm
                        .querySelector(
                            '[name="title"]',
                        )
                        ?.focus();

                },
            );


        createDialog
            .addEventListener(
                'click',

                (
                    event,
                ) => {

                    if (
                        event.target
                        ===
                        createDialog
                    ) {
                        createDialog
                            .close();
                    }

                },
            );


        createDraftButton
            ?.addEventListener(
                'click',

                async () => {

                    if (
                        createDraftButton
                            .disabled
                    ) {
                        return;
                    }


                    createDraftButton
                        .disabled =
                        true;


                    createDialog
                        .close();


                    if (
                        draftLoading
                    ) {

                        draftLoading
                            .hidden =
                            false;


                        document
                            .body
                            .classList
                            .add(
                                'has-document-generation',
                            );

                    }


                    const startedAt =
                        performance
                            .now();


                    try {

                        const response =
                            await fetch(
                                createForm
                                    .action,

                                {
                                    method:
                                        'POST',

                                    body:
                                        new FormData(
                                            createForm,
                                        ),

                                    credentials:
                                        'same-origin',

                                    headers: {
                                        Accept:
                                            'application/json',

                                        'X-Requested-With':
                                            'XMLHttpRequest',
                                    },
                                },
                            );


                        const payload =
                            await response
                                .json()
                                .catch(
                                    () =>
                                        ({}),
                                );


                        if (
                            !response.ok
                            ||
                            !payload
                                ?.ok
                            ||
                            !payload
                                ?.edit_url
                        ) {

                            const
                                firstValidationError =
                                    Object
                                        .values(
                                            payload
                                                ?.errors
                                            ||
                                            {},
                                        )
                                        .flat()
                                        .find(
                                            Boolean,
                                        );


                            throw new Error(
                                payload
                                    ?.message
                                ||
                                firstValidationError
                                ||
                                'Draft belum berhasil disiapkan.',
                            );

                        }


                        const minimum =
                            reducedMotion
                                .matches
                                ? 0
                                : 900;


                        const elapsed =
                            performance
                                .now()
                            -
                            startedAt;


                        if (
                            elapsed
                            <
                            minimum
                        ) {
                            await sleep(
                                minimum
                                -
                                elapsed,
                            );
                        }


                        window
                            .location
                            .assign(
                                payload
                                    .edit_url,
                            );

                    } catch (
                        error
                    ) {

                        createDraftButton
                            .disabled =
                            false;


                        if (
                            draftLoading
                        ) {

                            draftLoading
                                .hidden =
                                true;


                            document
                                .body
                                .classList
                                .remove(
                                    'has-document-generation',
                                );

                        }


                        window
                            .alert(
                                error
                                instanceof
                                Error
                                    ? error
                                        .message
                                    : 'Draft belum berhasil disiapkan.',
                            );

                    }

                },
            );

    }


    /* ============================================================
       EDITOR PAGE
       ============================================================ */

    const editor =
        document.querySelector(
            '[data-document-editor]',
        );


    if (
        !editor
    ) {
        return;
    }


    const editForm =
        editor
            .querySelector(
                '[data-draft-edit-form]',
            );


    const saveButton =
        editor
            .querySelector(
                '[data-draft-save-button]',
            );


    const saveButtonDefaultText =
        saveButton
            ?.textContent
            ?.trim()
        ||
        'Simpan Perubahan';


    const saveState =
        editor
            .querySelector(
                '[data-draft-save-state]',
            );


    const unsavedLabel =
        editor
            .querySelector(
                '[data-draft-unsaved-label]',
            );


    const titleInput =
        editor
            .querySelector(
                '[data-draft-title-input]',
            );


    const referencesText =
        editor
            .querySelector(
                '[data-references-text]',
            );


    const referenceHiddenInputs =
        editor
            .querySelector(
                '[data-reference-hidden-inputs]',
            );


    const previewTitle =
        editor
            .querySelector(
                '[data-preview-title]',
            );


    const previewReferenceList =
        editor
            .querySelector(
                '[data-preview-reference-list]',
            );


    const previewScroller =
        editor
            .querySelector(
                '[data-preview-scroller]',
            );


    const workspaceGrid =
        editor
            .querySelector(
                '[data-doc-workspace-grid]',
            );


    const mobileModeTabs = [
        ...editor
            .querySelectorAll(
                '[data-doc-mobile-mode]',
            ),
    ];


    const editorTabs = [
        ...editor
            .querySelectorAll(
                '[data-editor-tab]',
            ),
    ];


    const editorPanes = [
        ...editor
            .querySelectorAll(
                '[data-editor-pane]',
            ),
    ];


    const formatButtons = [
        ...editor
            .querySelectorAll(
                '[data-print-format]',
            ),
    ];


    const openPrintConfirm =
        editor
            .querySelector(
                '[data-open-print-confirm]',
            );


    const printDialog =
        document
            .querySelector(
                '[data-print-confirm]',
            );


    const printCancel =
        printDialog
            ?.querySelector(
                '[data-print-cancel]',
            );


    const printConfirmButton =
        printDialog
            ?.querySelector(
                '[data-print-confirm-button]',
            );


    const printSummaryTitle =
        printDialog
            ?.querySelector(
                '[data-print-summary-title]',
            );


    const printSummaryFormat =
        printDialog
            ?.querySelector(
                '[data-print-summary-format]',
            );


    const generation =
        document
            .querySelector(
                '[data-document-generation]',
            );


    const progressTitle =
        generation
            ?.querySelector(
                '[data-doc-progress-title]',
            );


    const progressCopy =
        generation
            ?.querySelector(
                '[data-doc-progress-copy]',
            );


    const progressRetry =
        generation
            ?.querySelector(
                '[data-doc-progress-retry]',
            );


    const successBox =
        generation
            ?.querySelector(
                '[data-doc-success]',
            );


    const successTitle =
        generation
            ?.querySelector(
                '[data-doc-success-title]',
            );


    const successCountdown =
        generation
            ?.querySelector(
                '[data-doc-success-countdown]',
            );


    const successOpenButton =
        generation
            ?.querySelector(
                '[data-doc-open-result]',
            );


    const successStayButton =
        generation
            ?.querySelector(
                '[data-doc-stay-here]',
            );


    let dirty =
        false;


    let selectedFormat =
        '';


    let renderInFlight =
        false;


    let redirectTimer =
        null;


    let redirectCountdownTimer =
        null;


    /* ============================================================
       MOBILE EDIT / PREVIEW
       ============================================================ */

    mobileModeTabs
        .forEach(
            (
                button,
            ) => {

                button
                    .addEventListener(
                        'click',

                        () => {

                            const mode =
                                button
                                    .dataset
                                    .docMobileMode
                                ===
                                'preview'
                                    ? 'preview'
                                    : 'edit';


                            if (
                                workspaceGrid
                            ) {
                                workspaceGrid
                                    .dataset
                                    .mobileView =
                                    mode;
                            }


                            mobileModeTabs
                                .forEach(
                                    (
                                        tab,
                                    ) => {

                                        tab
                                            .setAttribute(
                                                'aria-selected',

                                                String(
                                                    tab
                                                    ===
                                                    button,
                                                ),
                                            );

                                    },
                                );

                        },
                    );

            },
        );


    /* ============================================================
       DIRTY STATE
       ============================================================ */

    const resetFormatSelection =
        () => {

            selectedFormat =
                '';


            formatButtons
                .forEach(
                    (
                        button,
                    ) => {

                        button
                            .classList
                            .remove(
                                'is-selected',
                            );


                        button
                            .setAttribute(
                                'aria-checked',
                                'false',
                            );

                    },
                );


            if (
                openPrintConfirm
            ) {
                openPrintConfirm
                    .disabled =
                    true;
            }

        };


    const setDirty =
        (
            value,
        ) => {

            dirty =
                value;


            if (
                unsavedLabel
            ) {

                unsavedLabel
                    .textContent =
                    value
                        ? 'Ada perubahan yang belum disimpan'
                        : 'Semua perubahan tersimpan';


                unsavedLabel
                    .classList
                    .toggle(
                        'is-dirty',
                        value,
                    );

            }


            if (
                saveState
            ) {

                saveState
                    .textContent =
                    value
                        ? 'Belum disimpan'
                        : 'Draft tersimpan';


                saveState
                    .classList
                    .toggle(
                        'is-dirty',
                        value,
                    );


                if (
                    value
                ) {
                    saveState
                        .classList
                        .remove(
                            'is-success',
                            'is-error',
                        );
                }

            }


            if (
                value
            ) {
                resetFormatSelection();
            }

        };


    /* ============================================================
       AUTO SCROLL PREVIEW
       ============================================================ */

    const previewTargetForPane =
        (
            name,
        ) => {

            if (
                name
                ===
                'title'
            ) {

                return editor
                    .querySelector(
                        '[data-preview-title-anchor]',
                    )
                    ||
                    editor
                        .querySelector(
                            '[data-preview-title]',
                        );

            }


            if (
                name
                ===
                'references'
            ) {

                return editor
                    .querySelector(
                        '[data-preview-references]',
                    );

            }


            if (
                name
                    .startsWith(
                        'section-',
                    )
            ) {

                const index =
                    name
                        .replace(
                            'section-',
                            '',
                        );


                return editor
                    .querySelector(
                        `[data-preview-section="${index}"]`,
                    );

            }


            return null;

        };


    const scrollPreviewToPane =
        (
            name,
            {
                flash = true,
            } = {},
        ) => {

            const target =
                previewTargetForPane(
                    name,
                );


            if (
                !target
            ) {
                return;
            }


            const behavior =
                reducedMotion
                    .matches
                    ? 'auto'
                    : 'smooth';


            if (
                previewScroller
            ) {

                const scrollerRect =
                    previewScroller
                        .getBoundingClientRect();


                const targetRect =
                    target
                        .getBoundingClientRect();


                const nextTop =
                    previewScroller
                        .scrollTop
                    +
                    (
                        targetRect
                            .top
                        -
                        scrollerRect
                            .top
                    )
                    -
                    Math.max(
                        22,
                        scrollerRect
                            .height
                        *
                        .08,
                    );


                previewScroller
                    .scrollTo(
                        {
                            top:
                                Math.max(
                                    0,
                                    nextTop,
                                ),

                            behavior,
                        },
                    );

            } else {

                target
                    .scrollIntoView(
                        {
                            behavior,

                            block:
                                'start',

                            inline:
                                'nearest',
                        },
                    );

            }


            if (
                !flash
                ||
                reducedMotion
                    .matches
            ) {
                return;
            }


            target
                .classList
                .remove(
                    'br-preview-scroll-flash',
                );


            void target
                .offsetWidth;


            target
                .classList
                .add(
                    'br-preview-scroll-flash',
                );


            window
                .setTimeout(
                    () => {

                        target
                            .classList
                            .remove(
                                'br-preview-scroll-flash',
                            );

                    },
                    950,
                );

        };


    /* ============================================================
       SECTION NAVIGATION
       ============================================================ */

    const activatePane =
        (
            name,
            {
                scrollPreview = false,
            } = {},
        ) => {

            editorTabs
                .forEach(
                    (
                        button,
                    ) => {

                        const active =
                            button
                                .dataset
                                .editorTab
                            ===
                            name;


                        button
                            .classList
                            .toggle(
                                'is-active',
                                active,
                            );


                        button
                            .setAttribute(
                                'aria-selected',
                                String(
                                    active,
                                ),
                            );

                    },
                );


            editorPanes
                .forEach(
                    (
                        pane,
                    ) => {

                        const active =
                            pane
                                .dataset
                                .editorPane
                            ===
                            name;


                        pane
                            .classList
                            .toggle(
                                'is-active',
                                active,
                            );


                        pane
                            .hidden =
                            !active;

                    },
                );


            if (
                scrollPreview
            ) {
                scrollPreviewToPane(
                    name,
                );
            }

        };


    editorTabs
        .forEach(
            (
                button,
            ) => {

                button
                    .addEventListener(
                        'click',

                        () => {

                            activatePane(
                                button
                                    .dataset
                                    .editorTab
                                ||
                                'title',

                                {
                                    scrollPreview:
                                        true,
                                },
                            );

                        },
                    );

            },
        );


    const initialPane =
        editorTabs
            .find(
                (
                    button,
                ) =>
                    button
                        .classList
                        .contains(
                            'is-active',
                        ),
            )
            ?.dataset
            .editorTab
        ||
        'title';


    activatePane(
        initialPane,
        {
            scrollPreview:
                false,
        },
    );


    editorPanes
        .forEach(
            (
                pane,
            ) => {

                pane
                    .addEventListener(
                        'focusin',

                        (
                            event,
                        ) => {

                            if (
                                !event
                                    .target
                                    .matches(
                                        'textarea, input, select',
                                    )
                            ) {
                                return;
                            }


                            const name =
                                pane
                                    .dataset
                                    .editorPane;


                            if (
                                name
                            ) {

                                scrollPreviewToPane(
                                    name,

                                    {
                                        flash:
                                            false,
                                    },
                                );

                            }

                        },
                    );

            },
        );


    /* ============================================================
       REFERENCES
       ============================================================ */

    const referenceLines =
        () =>
            referencesText
                ? referencesText
                    .value
                    .split(
                        '\n',
                    )
                    .map(
                        (
                            item,
                        ) =>
                            item
                                .trim(),
                    )
                    .filter(
                        Boolean,
                    )
                : [];


    const syncReferenceInputs =
        () => {

            if (
                !referenceHiddenInputs
            ) {
                return;
            }


            referenceHiddenInputs
                .innerHTML =
                '';


            referenceLines()
                .forEach(
                    (
                        reference,
                        index,
                    ) => {

                        const input =
                            document
                                .createElement(
                                    'input',
                                );


                        input.type =
                            'hidden';


                        input.name =
                            `references[${index}]`;


                        input.value =
                            reference;


                        referenceHiddenInputs
                            .appendChild(
                                input,
                            );

                    },
                );

        };


    /* ============================================================
       LIVE PREVIEW
       ============================================================ */

    const updatePreviewFromForm =
        () => {

            if (
                previewTitle
                &&
                titleInput
            ) {

                previewTitle
                    .textContent =
                    stripInlineMarkdown(
                        titleInput
                            .value,
                    )
                        .trim()
                    ||
                    'Untitled Document';

            }


            editor
                .querySelectorAll(
                    '[data-section-body-input]',
                )
                .forEach(
                    (
                        input,
                    ) => {

                        const index =
                            input
                                .dataset
                                .sectionBodyInput;


                        const previewSection =
                            editor
                                .querySelector(
                                    `[data-preview-section="${index}"]`,
                                );


                        const previewBody =
                            previewSection
                                ?.querySelector(
                                    '[data-preview-body]',
                                );


                        const headingInput =
                            editor
                                .querySelector(
                                    `[data-section-heading-input="${index}"]`,
                                );


                        const previewHeading =
                            previewSection
                                ?.querySelector(
                                    '[data-preview-heading]',
                                );


                        if (
                            previewBody
                        ) {

                            previewBody
                                .innerHTML =
                                simpleTextHtml(
                                    input
                                        .value,
                                );

                        }


                        if (
                            previewHeading
                            &&
                            headingInput
                        ) {

                            previewHeading
                                .textContent =
                                stripInlineMarkdown(
                                    headingInput
                                        .value,
                                )
                                    .trim()
                                ||
                                'SECTION';

                        }

                    },
                );


            if (
                previewReferenceList
            ) {

                const references =
                    referenceLines();


                previewReferenceList
                    .innerHTML =
                    references
                        .length
                        ? references
                            .map(
                                (
                                    reference,
                                ) => `
                                    <p>
                                        ${
                                            escapeHtml(
                                                reference,
                                            )
                                        }
                                    </p>
                                `,
                            )
                            .join('')
                        : `
                            <p class="is-empty-reference">
                                Belum ada daftar pustaka.
                            </p>
                        `;

            }

        };


    editForm
        ?.querySelectorAll(
            'textarea, input',
        )
        .forEach(
            (
                field,
            ) => {

                field
                    .addEventListener(
                        'input',

                        () => {

                            setDirty(
                                true,
                            );


                            window
                                .requestAnimationFrame(
                                    updatePreviewFromForm,
                                );

                        },
                    );

            },
        );


    updatePreviewFromForm();


    /* ============================================================
       SAVE DRAFT
       ============================================================ */

    editForm
        ?.addEventListener(
            'submit',

            async (
                event,
            ) => {

                event
                    .preventDefault();


                if (
                    !editForm
                        .reportValidity()
                ) {
                    return;
                }


                syncReferenceInputs();


                if (
                    saveButton
                ) {

                    saveButton
                        .disabled =
                        true;


                    saveButton
                        .textContent =
                        'Menyimpan…';

                }


                if (
                    saveState
                ) {

                    saveState
                        .textContent =
                        'Menyimpan…';


                    saveState
                        .classList
                        .remove(
                            'is-error',
                            'is-success',
                        );

                }


                try {

                    const response =
                        await fetch(
                            editForm
                                .action,

                            {
                                method:
                                    'POST',

                                body:
                                    new FormData(
                                        editForm,
                                    ),

                                credentials:
                                    'same-origin',

                                headers: {
                                    Accept:
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },
                            },
                        );


                    const payload =
                        await response
                            .json()
                            .catch(
                                () =>
                                    ({}),
                            );


                    if (
                        !response.ok
                        ||
                        !payload
                            ?.ok
                    ) {

                        const
                            firstValidationError =
                                Object
                                    .values(
                                        payload
                                            ?.errors
                                        ||
                                        {},
                                    )
                                    .flat()
                                    .find(
                                        Boolean,
                                    );


                        throw new Error(
                            payload
                                ?.message
                            ||
                            firstValidationError
                            ||
                            'Perubahan belum berhasil disimpan.',
                        );

                    }


                    updatePreviewFromForm();


                    setDirty(
                        false,
                    );


                    if (
                        saveState
                    ) {

                        saveState
                            .textContent =
                            'Perubahan disimpan';


                        saveState
                            .classList
                            .add(
                                'is-success',
                            );


                        window
                            .setTimeout(
                                () => {

                                    saveState
                                        .textContent =
                                        'Draft tersimpan';


                                    saveState
                                        .classList
                                        .remove(
                                            'is-success',
                                        );

                                },
                                1800,
                            );

                    }


                    if (
                        printSummaryTitle
                        &&
                        titleInput
                    ) {

                        printSummaryTitle
                            .textContent =
                            titleInput
                                .value
                                .trim();

                    }

                } catch (
                    error
                ) {

                    if (
                        saveState
                    ) {

                        saveState
                            .textContent =
                            'Gagal menyimpan';


                        saveState
                            .classList
                            .add(
                                'is-error',
                            );

                    }


                    window
                        .alert(
                            error
                            instanceof
                            Error
                                ? error
                                    .message
                                : 'Perubahan belum berhasil disimpan.',
                        );

                } finally {

                    if (
                        saveButton
                    ) {

                        saveButton
                            .disabled =
                            false;


                        saveButton
                            .textContent =
                            saveButtonDefaultText;

                    }

                }

            },
        );


    /* ============================================================
       OUTPUT FORMAT
       ============================================================ */

    formatButtons
        .forEach(
            (
                button,
            ) => {

                button
                    .addEventListener(
                        'click',

                        () => {

                            if (
                                dirty
                            ) {

                                window
                                    .alert(
                                        'Simpan perubahan draft terlebih dahulu sebelum memilih format.',
                                    );


                                return;

                            }


                            const format =
                                button
                                    .dataset
                                    .printFormat;


                            if (
                                ![
                                    'pdf',
                                    'docx',
                                ]
                                    .includes(
                                        format,
                                    )
                            ) {
                                return;
                            }


                            selectedFormat =
                                format;


                            formatButtons
                                .forEach(
                                    (
                                        item,
                                    ) => {

                                        const active =
                                            item
                                            ===
                                            button;


                                        item
                                            .classList
                                            .toggle(
                                                'is-selected',
                                                active,
                                            );


                                        item
                                            .setAttribute(
                                                'aria-checked',

                                                String(
                                                    active,
                                                ),
                                            );

                                    },
                                );


                            if (
                                openPrintConfirm
                            ) {
                                openPrintConfirm
                                    .disabled =
                                    false;
                            }

                        },
                    );

            },
        );


    openPrintConfirm
        ?.addEventListener(
            'click',

            () => {

                if (
                    !selectedFormat
                ) {
                    return;
                }


                if (
                    dirty
                ) {

                    window
                        .alert(
                            'Masih ada perubahan yang belum disimpan.',
                        );


                    return;

                }


                if (
                    printSummaryTitle
                    &&
                    titleInput
                ) {

                    printSummaryTitle
                        .textContent =
                        titleInput
                            .value
                            .trim();

                }


                if (
                    printSummaryFormat
                ) {

                    printSummaryFormat
                        .textContent =
                        selectedFormat
                            .toUpperCase();

                }


                if (
                    typeof
                        printDialog
                            ?.showModal
                    ===
                    'function'
                ) {

                    printDialog
                        .showModal();

                }

            },
        );


    printCancel
        ?.addEventListener(
            'click',

            () =>
                printDialog
                    ?.close(),
        );


    printDialog
        ?.addEventListener(
            'click',

            (
                event,
            ) => {

                if (
                    event.target
                    ===
                    printDialog
                ) {

                    printDialog
                        .close();

                }

            },
        );


    /* ============================================================
       GENERATION PROGRESS
       ============================================================ */

    const setProgress =
        (
            value,
            stage = null,
        ) => {

            if (
                !generation
            ) {
                return;
            }


            const normalized =
                Math.max(
                    0,

                    Math.min(
                        100,

                        Math.round(
                            value,
                        ),
                    ),
                );


            const bar =
                generation
                    .querySelector(
                        '[data-doc-progress-bar]',
                    );


            const fill =
                generation
                    .querySelector(
                        '[data-doc-progress-fill]',
                    );


            const output =
                generation
                    .querySelector(
                        '[data-doc-progress-value]',
                    );


            const stageNode =
                generation
                    .querySelector(
                        '[data-doc-progress-stage]',
                    );


            bar
                ?.setAttribute(
                    'aria-valuenow',

                    String(
                        normalized,
                    ),
                );


            fill
                ?.style
                .setProperty(
                    '--doc-progress',
                    `${normalized}%`,
                );


            if (
                output
            ) {

                output
                    .textContent =
                    `${normalized}%`;

            }


            if (
                stage
                &&
                stageNode
            ) {

                stageNode
                    .textContent =
                    stage;

            }

        };


    const clearRedirectTimers =
        () => {

            if (
                redirectTimer
            ) {

                window
                    .clearTimeout(
                        redirectTimer,
                    );


                redirectTimer =
                    null;

            }


            if (
                redirectCountdownTimer
            ) {

                window
                    .clearInterval(
                        redirectCountdownTimer,
                    );


                redirectCountdownTimer =
                    null;

            }

        };


    const closeGenerationOverlay =
        () => {

            clearRedirectTimers();


            renderInFlight =
                false;


            if (
                generation
            ) {

                generation
                    .hidden =
                    true;


                generation
                    .classList
                    .remove(
                        'is-error',
                        'is-complete',
                    );


                generation
                    .setAttribute(
                        'aria-busy',
                        'false',
                    );

            }


            if (
                successBox
            ) {

                successBox
                    .hidden =
                    true;

            }


            document
                .body
                .classList
                .remove(
                    'has-document-generation',
                );

        };


    const showGenerationSuccess =
        ({
            redirectUrl,
            format,
        }) => {

            clearRedirectTimers();


            if (
                successBox
            ) {

                successBox
                    .hidden =
                    false;

            }


            if (
                successTitle
            ) {

                successTitle
                    .textContent =
                    `${
                        format
                            .toUpperCase()
                    } berhasil dibuat`;

            }


            let seconds =
                5;


            if (
                successCountdown
            ) {

                successCountdown
                    .textContent =
                    String(
                        seconds,
                    );

            }


            const goToResult =
                () => {

                    clearRedirectTimers();


                    if (
                        redirectUrl
                    ) {

                        window
                            .location
                            .assign(
                                redirectUrl,
                            );

                    }

                };


            if (
                successOpenButton
            ) {

                successOpenButton
                    .onclick =
                    goToResult;

            }


            if (
                successStayButton
            ) {

                successStayButton
                    .onclick =
                    closeGenerationOverlay;

            }


            redirectCountdownTimer =
                window
                    .setInterval(
                        () => {

                            seconds -=
                                1;


                            if (
                                successCountdown
                            ) {

                                successCountdown
                                    .textContent =
                                    String(
                                        Math.max(
                                            seconds,
                                            0,
                                        ),
                                    );

                            }


                            if (
                                seconds
                                <=
                                0
                                &&
                                redirectCountdownTimer
                            ) {

                                window
                                    .clearInterval(
                                        redirectCountdownTimer,
                                    );


                                redirectCountdownTimer =
                                    null;

                            }

                        },
                        1000,
                    );


            redirectTimer =
                window
                    .setTimeout(
                        goToResult,
                        5000,
                    );

        };


    /* ============================================================
       FLY FILE TO SIDEBAR
       ============================================================ */

    const flyDocumentToSidebar =
        async (
            format,
        ) => {

            if (
                reducedMotion
                    .matches
            ) {
                return;
            }


            const target =
                document
                    .querySelector(
                        '.br-sidebar-nav[href*="/documents"]',
                    );


            const panel =
                generation
                    ?.querySelector(
                        '.br-doc-generation__panel',
                    );


            if (
                !target
                ||
                !panel
                ||
                typeof
                    Element
                        .prototype
                        .animate
                !==
                'function'
            ) {
                return;
            }


            const startRect =
                panel
                    .getBoundingClientRect();


            const endRect =
                target
                    .getBoundingClientRect();


            const flyer =
                document
                    .createElement(
                        'div',
                    );


            flyer.className =
                `br-doc-flying-file is-${format}`;


            flyer
                .setAttribute(
                    'aria-hidden',
                    'true',
                );


            flyer.innerHTML = `
                <span>
                    ${
                        format
                            .toUpperCase()
                    }
                </span>

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                >

                    <path
                        d="
                            M6 3
                            h8
                            l4 4
                            v14
                            H6
                            V3Z
                        "
                    />

                    <path
                        d="
                            M14 3
                            v5
                            h5
                        "
                    />

                    <path
                        d="
                            M9 12
                            h6

                            M9 16
                            h6
                        "
                    />

                </svg>
            `;


            document
                .body
                .appendChild(
                    flyer,
                );


            const startX =
                startRect
                    .left
                +
                (
                    startRect
                        .width
                    /
                    2
                )
                -
                28;


            const startY =
                startRect
                    .top
                +
                (
                    startRect
                        .height
                    /
                    2
                )
                -
                35;


            const endX =
                endRect
                    .left
                +
                (
                    endRect
                        .width
                    /
                    2
                )
                -
                28;


            const endY =
                endRect
                    .top
                +
                (
                    endRect
                        .height
                    /
                    2
                )
                -
                35;


            const dx =
                endX
                -
                startX;


            const dy =
                endY
                -
                startY;


            flyer.style.left =
                `${startX}px`;


            flyer.style.top =
                `${startY}px`;


            const animation =
                flyer
                    .animate(
                        [
                            {
                                opacity:
                                    0,

                                transform:
                                    'translate3d(0,0,0) rotate(0deg) scale(.95)',
                            },

                            {
                                opacity:
                                    1,

                                transform:
                                    'translate3d(0,-26px,0) rotate(-5deg) scale(1.05)',

                                offset:
                                    .18,
                            },

                            {
                                opacity:
                                    1,

                                transform:
                                    `
                                        translate3d(
                                            ${dx * .5}px,
                                            ${dy * .35 - 50}px,
                                            0
                                        )
                                        rotate(7deg)
                                        scale(.9)
                                    `,

                                offset:
                                    .6,
                            },

                            {
                                opacity:
                                    0,

                                transform:
                                    `
                                        translate3d(
                                            ${dx}px,
                                            ${dy}px,
                                            0
                                        )
                                        rotate(0deg)
                                        scale(.3)
                                    `,
                            },
                        ],

                        {
                            duration:
                                1450,

                            easing:
                                'cubic-bezier(.22,1,.36,1)',

                            fill:
                                'forwards',
                        },
                    );


            try {

                await animation
                    .finished;

            } catch {

                /*
                 * Browser dapat membatalkan
                 * animation saat navigasi.
                 */

            }


            flyer
                .remove();


            target
                .classList
                .add(
                    'is-document-arrival',
                );


            window
                .setTimeout(
                    () => {

                        target
                            .classList
                            .remove(
                                'is-document-arrival',
                            );

                    },
                    700,
                );

        };


    /* ============================================================
       GENERATE FINAL DOCUMENT
       ============================================================ */

    const renderDocument =
        async (
            format,
        ) => {

            if (
                renderInFlight
                ||
                ![
                    'pdf',
                    'docx',
                ]
                    .includes(
                        format,
                    )
            ) {
                return;
            }


            renderInFlight =
                true;


            printDialog
                ?.close();


            clearRedirectTimers();


            if (
                generation
            ) {

                generation
                    .hidden =
                    false;


                generation
                    .classList
                    .remove(
                        'is-error',
                        'is-complete',
                    );


                generation
                    .setAttribute(
                        'aria-busy',
                        'true',
                    );


                document
                    .body
                    .classList
                    .add(
                        'has-document-generation',
                    );

            }


            if (
                successBox
            ) {

                successBox
                    .hidden =
                    true;

            }


            if (
                progressRetry
            ) {

                progressRetry
                    .hidden =
                    true;

            }


            if (
                progressTitle
            ) {

                progressTitle
                    .textContent =
                    `Membuat ${
                        format
                            .toUpperCase()
                    } Anda`;

            }


            if (
                progressCopy
            ) {

                progressCopy
                    .textContent =
                    'Menata draft final, citation, dan layout file.';

            }


            let progress =
                3;


            let serverDone =
                false;


            const startedAt =
                performance
                    .now();


            const minimumAnimation =
                reducedMotion
                    .matches
                    ? 0
                    : 3800;


            setProgress(
                progress,
                'Menyiapkan struktur…',
            );


            const timer =
                window
                    .setInterval(
                        () => {

                            if (
                                serverDone
                            ) {
                                return;
                            }


                            const cap =
                                88;


                            const gap =
                                cap
                                -
                                progress;


                            progress +=
                                Math.max(
                                    .25,

                                    gap
                                    *
                                    .048,
                                );


                            let stage =
                                'Menyiapkan struktur…';


                            if (
                                progress
                                >=
                                18
                            ) {
                                stage =
                                    'Menyusun bagian dokumen…';
                            }


                            if (
                                progress
                                >=
                                38
                            ) {
                                stage =
                                    'Memeriksa evidence…';
                            }


                            if (
                                progress
                                >=
                                55
                            ) {
                                stage =
                                    'Merapikan citation…';
                            }


                            if (
                                progress
                                >=
                                70
                            ) {

                                stage =
                                    `Membentuk ${
                                        format
                                            .toUpperCase()
                                    }…`;

                            }


                            if (
                                progress
                                >=
                                82
                            ) {
                                stage =
                                    'Finalisasi file…';
                            }


                            setProgress(
                                progress,
                                stage,
                            );

                        },

                        reducedMotion
                            .matches
                            ? 800
                            : 210,
                    );


            try {

                const formData =
                    new FormData();


                formData
                    .append(
                        '_token',
                        csrfToken(),
                    );


                formData
                    .append(
                        'output_format',
                        format,
                    );


                const response =
                    await fetch(
                        editor
                            .dataset
                            .renderUrl,

                        {
                            method:
                                'POST',

                            body:
                                formData,

                            credentials:
                                'same-origin',

                            headers: {
                                Accept:
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },
                        },
                    );


                const payload =
                    await response
                        .json()
                        .catch(
                            () =>
                                ({}),
                        );


                serverDone =
                    true;


                window
                    .clearInterval(
                        timer,
                    );


                if (
                    !response.ok
                    ||
                    !payload
                        ?.ok
                    ||
                    !payload
                        ?.redirect_url
                ) {

                    throw new Error(
                        payload
                            ?.message
                        ||
                        'File belum berhasil dibuat.',
                    );

                }


                const elapsed =
                    performance
                        .now()
                    -
                    startedAt;


                if (
                    elapsed
                    <
                    minimumAnimation
                ) {

                    await sleep(
                        minimumAnimation
                        -
                        elapsed,
                    );

                }


                const finishSteps = [
                    [
                        91,
                        'Memeriksa file…',
                    ],

                    [
                        95,
                        'Menyimpan ke Dokumen Saya…',
                    ],

                    [
                        98,
                        'Hampir selesai…',
                    ],

                    [
                        100,
                        'Selesai',
                    ],
                ];


                for (
                    const [
                        value,
                        stage,
                    ]
                    of
                    finishSteps
                ) {

                    setProgress(
                        value,
                        stage,
                    );


                    await sleep(
                        reducedMotion
                            .matches
                            ? 0
                            : 220,
                    );

                }


                generation
                    ?.classList
                    .add(
                        'is-complete',
                    );


                generation
                    ?.setAttribute(
                        'aria-busy',
                        'false',
                    );


                if (
                    progressTitle
                ) {

                    progressTitle
                        .textContent =
                        `${
                            format
                                .toUpperCase()
                        } selesai dibuat`;

                }


                if (
                    progressCopy
                ) {

                    progressCopy
                        .textContent =
                        'File berhasil dibuat dan tersimpan di Dokumen Saya.';

                }


                await sleep(
                    reducedMotion
                        .matches
                        ? 0
                        : 300,
                );


                await flyDocumentToSidebar(
                    format,
                );


                await sleep(
                    reducedMotion
                        .matches
                        ? 0
                        : 180,
                );


                showGenerationSuccess(
                    {
                        redirectUrl:
                            payload
                                .redirect_url,

                        format,
                    },
                );

            } catch (
                error
            ) {

                serverDone =
                    true;


                renderInFlight =
                    false;


                window
                    .clearInterval(
                        timer,
                    );


                clearRedirectTimers();


                generation
                    ?.classList
                    .add(
                        'is-error',
                    );


                generation
                    ?.classList
                    .remove(
                        'is-complete',
                    );


                generation
                    ?.setAttribute(
                        'aria-busy',
                        'false',
                    );


                if (
                    successBox
                ) {

                    successBox
                        .hidden =
                        true;

                }


                if (
                    progressTitle
                ) {

                    progressTitle
                        .textContent =
                        'Dokumen belum berhasil dibuat';

                }


                if (
                    progressCopy
                ) {

                    progressCopy
                        .textContent =
                        error
                        instanceof
                        Error
                            ? error
                                .message
                            : 'Terjadi kesalahan saat membuat dokumen.';

                }


                setProgress(
                    Math.min(
                        progress,
                        88,
                    ),

                    'Proses terhenti',
                );


                if (
                    progressRetry
                ) {

                    progressRetry
                        .hidden =
                        false;


                    progressRetry
                        .dataset
                        .retryFormat =
                        format;

                }

            }

        };


    printConfirmButton
        ?.addEventListener(
            'click',

            () => {

                if (
                    !selectedFormat
                    ||
                    dirty
                ) {
                    return;
                }


                renderDocument(
                    selectedFormat,
                );

            },
        );


    progressRetry
        ?.addEventListener(
            'click',

            (
                event,
            ) => {

                const format =
                    event
                        .currentTarget
                        .dataset
                        .retryFormat
                    ||
                    selectedFormat
                    ||
                    'pdf';


                renderInFlight =
                    false;


                generation
                    ?.classList
                    .remove(
                        'is-error',
                        'is-complete',
                    );


                renderDocument(
                    format,
                );

            },
        );

})();