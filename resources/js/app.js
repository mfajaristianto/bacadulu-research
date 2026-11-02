import './profile-security.js';
import './research-sidebar.js';
import './workspace-topbar.js';
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


ready(() => {
    // Load each heavy feature only on pages that use it.
    if (document.querySelector('[data-document-create-form], [data-document-editor]')) {
        import('./document-guidance.js');
    }
    if (document.querySelector('[data-saved-library]')) {
        import('./saved-research.js');
    }
    if (document.querySelector('[data-profile-page]')) {
        import('./profile-research.js');
    }
    const reduceMotion =
        window
            .matchMedia(
                '(prefers-reduced-motion: reduce)',
            )
            .matches;

    initMobileNavigation();
    initDeclarativeForms();
    initPasswordToggles();
    initPromptPicker();
    initChatSidebar();
    initChatComposer();
    initAvatarPreview();
    initDateOfBirthFields();
    initProfileProgress();
    initNetworkState();
    initAvatarFallbacks();
    initToasts();

    if (!reduceMotion) {
        animateReveals();
        initMotionDetails();
    }
});


/*
 * CSP-safe replacements for inline handlers:
 * - [data-confirm="..."] on a form or submit button asks before submitting.
 * - [data-auto-submit] on a select/input submits its form on change.
 */
function initDeclarativeForms() {
    document.addEventListener(
        'click',
        (event) => {
            const trigger =
                event.target.closest(
                    'button[data-confirm], input[type="submit"][data-confirm]',
                );

            if (
                trigger
                && !window.confirm(
                    trigger.dataset.confirm,
                )
            ) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        },
        true,
    );

    document.addEventListener(
        'submit',
        (event) => {
            const form =
                event.target;

            if (
                form.matches?.('form[data-confirm]')
                && !window.confirm(
                    form.dataset.confirm,
                )
            ) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        },
        true,
    );

    document.addEventListener(
        'change',
        (event) => {
            const field =
                event.target.closest?.(
                    '[data-auto-submit]',
                );

            field?.form?.requestSubmit();
        },
    );
}


function initMobileNavigation() {
    const toggle =
        document.querySelector(
            '[data-mobile-menu]',
        );

    const panel =
        document.querySelector(
            '[data-mobile-panel]',
        );

    if (!toggle || !panel) {
        return;
    }

    /* Keep the collapsed panel out of the accessibility tree and tab order. */
    const setOpen = (open) => {
        panel.classList.toggle(
            'is-open',
            open,
        );

        panel.setAttribute(
            'aria-hidden',
            String(!open),
        );

        panel.inert = !open;

        toggle.setAttribute(
            'aria-expanded',
            String(open),
        );

        toggle.setAttribute(
            'aria-label',
            open
                ? 'Tutup navigasi'
                : 'Buka navigasi',
        );
    };

    const close = () => setOpen(false);

    setOpen(false);

    toggle.addEventListener(
        'click',
        () => {
            setOpen(
                !panel.classList.contains(
                    'is-open',
                ),
            );
        },
    );

    panel
        .querySelectorAll(
            'a, button',
        )
        .forEach(
            (element) => {
                element.addEventListener(
                    'click',
                    close,
                );
            },
        );

    document.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key === 'Escape'
            ) {
                close();
            }
        },
    );
}


function initPasswordToggles() {
    document
        .querySelectorAll(
            '[data-password-toggle]',
        )
        .forEach(
            (button) => {
                button.addEventListener(
                    'click',
                    () => {
                        const wrapper =
                            button.closest(
                                '.password-field',
                            );

                        const input =
                            wrapper
                                ?.querySelector(
                                    '[data-password-input]',
                                );

                        if (!input) {
                            return;
                        }

                        const show =
                            input.type ===
                            'password';

                        input.type =
                            show
                                ? 'text'
                                : 'password';

                        button.textContent =
                            show
                                ? 'Hide'
                                : 'Show';

                        button.setAttribute(
                            'aria-label',
                            show
                                ? 'Hide password'
                                : 'Show password',
                        );
                    },
                );
            },
        );
}


/* ============================================================
   PROMPT PICKER
   ============================================================ */

function initPromptPicker() {
    const button =
        document.querySelector(
            '[data-prompt-open]',
        );

    const secondaryButton =
        document.querySelector(
            '[data-prompt-open-secondary]',
        );

    const menu =
        document.querySelector(
            '[data-prompt-menu]',
        );

    const hiddenInput =
        document.querySelector(
            '[data-prompt-input]',
        );

    const label =
        document.querySelector(
            '[data-prompt-label]',
        );

    const closeButton =
        document.querySelector(
            '[data-prompt-close]',
        );

    const activePanel =
        document.querySelector(
            '[data-active-prompt]',
        );

    const activeName =
        activePanel
            ?.querySelector(
                '[data-active-prompt-name]',
            );

    const activeDescription =
        activePanel
            ?.querySelector(
                '[data-active-prompt-description]',
            );

    const chatInput =
        document.querySelector(
            '[data-chat-input]',
        );

    const questionMapElement =
        document.querySelector(
            '[data-prompt-question-map]',
        );

    const usePromptLinks = [
        ...document.querySelectorAll(
            '.prompt-explainer__use',
        ),
    ];

    if (
        !button
        ||
        !menu
    ) {
        return;
    }

    const options = [
        ...menu.querySelectorAll(
            '[data-prompt-id]',
        ),
    ];

    let questionMap = {};

    let lastAppliedQuestion = '';

    try {
        questionMap =
            questionMapElement
                ?.textContent
                ? JSON.parse(
                    questionMapElement
                        .textContent,
                )
                : {};
    } catch {
        questionMap = {};
    }


    const normalizeQuestion = (
        value,
    ) =>
        String(
            value || '',
        )
            .replace(
                /\r\n/g,
                '\n',
            )
            .trim();


    const questionForPrompt = (
        promptId,
    ) =>
        normalizeQuestion(
            questionMap[
                String(promptId)
            ]
            || '',
        );


    const escapeRegExp = (
        value,
    ) =>
        String(value)
            .replace(
                /[.*+?^${}()|[\]\\]/g,
                '\\$&',
            );


    /*
    |--------------------------------------------------------------------------
    | Detect Text That Still Comes From A Question Template
    |--------------------------------------------------------------------------
    |
    | Contoh template:
    |
    | Identifikasi research gap mengenai {topik} berdasarkan evidence...
    |
    | Akan cocok dengan:
    |
    | Identifikasi research gap mengenai ESG disclosure berdasarkan evidence...
    |
    | Jadi kalau user mengganti Research Tool, starter lama boleh diganti.
    |
    */

    const templateToRegex = (
        template,
    ) => {
        const normalized =
            normalizeQuestion(
                template,
            );

        if (!normalized) {
            return null;
        }

        const pieces =
            normalized
                .split(
                    /(\{[^{}]+\})/g,
                )
                .filter(
                    (piece) =>
                        piece !== '',
                );

        const pattern =
            pieces
                .map(
                    (piece) => {
                        if (
                            /^\{[^{}]+\}$/
                                .test(piece)
                        ) {
                            return '[\\s\\S]+?';
                        }

                        return escapeRegExp(
                            piece,
                        )
                            .replace(
                                /\\\s+/g,
                                '\\s+',
                            );
                    },
                )
                .join('');

        try {
            return new RegExp(
                `^${pattern}$`,
                'iu',
            );
        } catch {
            return null;
        }
    };


    const managedTemplateRegexes =
        Object
            .values(
                questionMap,
            )
            .map(
                templateToRegex,
            )
            .filter(Boolean);


    const isManagedTemplateText = (
        value,
    ) => {
        const normalized =
            normalizeQuestion(
                value,
            );

        if (!normalized) {
            return false;
        }

        return managedTemplateRegexes
            .some(
                (regex) =>
                    regex.test(
                        normalized,
                    ),
            );
    };


    /*
    |--------------------------------------------------------------------------
    | Detect A Complete User Request
    |--------------------------------------------------------------------------
    |
    | Dipakai ketika user paste.
    |
    | Jika yang ditempel:
    |
    | "Identifikasi research gap mengenai ESG disclosure..."
    |
    | jangan masukkan seluruh kalimat itu ke dalam {topik}.
    |
    */

    const looksLikeCompleteResearchRequest = (
        value,
    ) => {
        const text =
            normalizeQuestion(
                value,
            )
                .toLowerCase();

        if (
            text.length < 30
        ) {
            return false;
        }

        const patterns = [
            'identifikasi research gap',
            'identifikasi kesenjangan',
            'susun conceptual framework',
            'susun kerangka konseptual',
            'petakan teori',
            'pemetaan teori',
            'bandingkan penelitian',
            'analisis komparatif',
            'identifikasi variabel',
            'sintesis penelitian',
            'sintesis literatur',
            'ringkas penelitian',
            'apa research gap',
            'what is the research gap',
            'identify the research gap',
            'build a conceptual framework',
            'map the theory',
            'compare the studies',
            'identify the variables',
            'synthesize the literature',
        ];

        return patterns.some(
            (pattern) =>
                text.includes(
                    pattern,
                ),
        );
    };


    const selectedOption = () =>
        options.find(
            (option) =>
                option.getAttribute(
                    'aria-selected',
                )
                ===
                'true',
        );


    const setOpen = (
        open,
    ) => {
        menu.classList.toggle(
            'is-open',
            open,
        );

        menu.hidden =
            !open;

        button.setAttribute(
            'aria-expanded',
            String(open),
        );

        if (open) {
            const selected =
                selectedOption();

            window
                .requestAnimationFrame(
                    () =>
                        (
                            selected
                            || options[0]
                        )
                            ?.focus(),
                );
        }
    };


    const applyQuestionTemplate = (
        promptId,
        force = false,
    ) => {
        if (!chatInput) {
            return false;
        }

        const question =
            questionForPrompt(
                promptId,
            );

        if (!question) {
            return false;
        }

        const current =
            normalizeQuestion(
                chatInput.value,
            );

        const previous =
            normalizeQuestion(
                lastAppliedQuestion,
            );

        /*
         * Template baru boleh mengganti composer ketika:
         *
         * - kosong
         * - masih persis starter sebelumnya
         * - masih memiliki struktur starter prompt
         * - dipanggil force oleh tombol Gunakan Prompt
         */

        const canReplace =
            force
            ||
            current === ''
            ||
            (
                previous !== ''
                &&
                current === previous
            )
            ||
            isManagedTemplateText(
                current,
            );

        if (!canReplace) {
            return false;
        }

        chatInput.value =
            question;

        lastAppliedQuestion =
            question;

        chatInput
            .setAttribute(
                'data-active-question-template',
                String(promptId),
            );

        chatInput.dispatchEvent(
            new Event(
                'input',
                {
                    bubbles: true,
                },
            ),
        );

        return true;
    };


    const focusComposer = () => {
        if (!chatInput) {
            return;
        }

        window
            .requestAnimationFrame(
                () => {
                    chatInput.focus();

                    const placeholder =
                        chatInput
                            .value
                            .match(
                                /\{[^{}]+\}/,
                            );

                    if (
                        placeholder
                        &&
                        typeof placeholder.index
                        ===
                        'number'
                    ) {
                        chatInput
                            .setSelectionRange?.(
                                placeholder.index,

                                placeholder.index
                                +
                                placeholder[0]
                                    .length,
                            );

                        return;
                    }

                    const end =
                        chatInput
                            .value
                            .length;

                    chatInput
                        .setSelectionRange?.(
                            end,
                            end,
                        );
                },
            );
    };


    const selectOption = (
        option,
        {
            forceQuestion = false,
            moveFocusToComposer = true,
        } = {},
    ) => {
        if (!option) {
            return;
        }

        options.forEach(
            (item) =>
                item.setAttribute(
                    'aria-selected',
                    String(
                        item === option,
                    ),
                ),
        );

        const promptId =
            option
                .dataset
                .promptId
            || '';

        if (hiddenInput) {
            hiddenInput.value =
                promptId;
        }

        if (label) {
            label.textContent =
                option
                    .dataset
                    .promptName
                ||
                'Research prompt dipilih';
        }

        if (activePanel) {
            activePanel.hidden =
                false;

            activePanel
                .classList
                .add(
                    'is-visible',
                );
        }

        if (activeName) {
            activeName.textContent =
                option
                    .dataset
                    .promptName
                || '';
        }

        if (
            activeDescription
        ) {
            activeDescription
                .textContent =
                    option
                        .dataset
                        .promptDescription
                    ||
                    option
                        .dataset
                        .promptTool
                    ||
                    '';
        }

        const questionApplied =
            applyQuestionTemplate(
                promptId,
                forceQuestion,
            );

        setOpen(
            false,
        );

        if (
            moveFocusToComposer
            &&
            chatInput
            &&
            (
                questionApplied
                ||
                forceQuestion
            )
        ) {
            focusComposer();

            return;
        }

        button.focus();
    };


    /*
    |--------------------------------------------------------------------------
    | Smart Paste
    |--------------------------------------------------------------------------
    */

    chatInput
        ?.addEventListener(
            'paste',
            (event) => {
                const start =
                    chatInput
                        .selectionStart
                    ?? 0;

                const end =
                    chatInput
                        .selectionEnd
                    ?? start;

                const selectedText =
                    chatInput
                        .value
                        .slice(
                            start,
                            end,
                        )
                        .trim();

                if (
                    !/^\{[^{}]+\}$/
                        .test(
                            selectedText,
                        )
                ) {
                    return;
                }

                const clipboard =
                    event
                        .clipboardData
                        ?.getData(
                            'text/plain',
                        )
                        ?.trim()
                    || '';

                if (
                    !looksLikeCompleteResearchRequest(
                        clipboard,
                    )
                ) {
                    return;
                }

                /*
                 * User menempel pertanyaan lengkap.
                 *
                 * Jadi starter prompt dibuang dan request user
                 * dipakai apa adanya.
                 */

                event.preventDefault();

                chatInput.value =
                    clipboard;

                lastAppliedQuestion =
                    '';

                chatInput
                    .removeAttribute(
                        'data-active-question-template',
                    );

                chatInput
                    .dispatchEvent(
                        new Event(
                            'input',
                            {
                                bubbles: true,
                            },
                        ),
                    );

                window
                    .requestAnimationFrame(
                        () => {
                            chatInput.focus();

                            const finalPosition =
                                chatInput
                                    .value
                                    .length;

                            chatInput
                                .setSelectionRange?.(
                                    finalPosition,
                                    finalPosition,
                                );
                        },
                    );
            },
        );


    /*
    |--------------------------------------------------------------------------
    | URL ?prompt=
    |--------------------------------------------------------------------------
    */

    const promptFromUrl =
        new URL(
            window.location.href,
        )
            .searchParams
            .get(
                'prompt',
            );

    if (
        promptFromUrl
        &&
        hiddenInput?.value
        ===
        String(
            promptFromUrl,
        )
    ) {
        applyQuestionTemplate(
            promptFromUrl,
            true,
        );
    }

    setOpen(
        false,
    );


    button.addEventListener(
        'click',
        (event) => {
            event.preventDefault();

            setOpen(
                menu.hidden,
            );
        },
    );


    secondaryButton
        ?.addEventListener(
            'click',
            (event) => {
                event.preventDefault();

                setOpen(
                    true,
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

                button.focus();
            },
        );


    options.forEach(
        (option) => {
            option.addEventListener(
                'click',
                () =>
                    selectOption(
                        option,
                    ),
            );

            option.addEventListener(
                'keydown',
                (event) => {
                    const currentIndex =
                        options
                            .indexOf(
                                option,
                            );

                    if (
                        event.key ===
                        'ArrowDown'
                    ) {
                        event
                            .preventDefault();

                        options[
                            (
                                currentIndex
                                +
                                1
                            )
                            %
                            options.length
                        ]
                            ?.focus();
                    }

                    if (
                        event.key ===
                        'ArrowUp'
                    ) {
                        event
                            .preventDefault();

                        options[
                            (
                                currentIndex
                                -
                                1
                                +
                                options.length
                            )
                            %
                            options.length
                        ]
                            ?.focus();
                    }

                    if (
                        event.key ===
                        'Home'
                    ) {
                        event
                            .preventDefault();

                        options[0]
                            ?.focus();
                    }

                    if (
                        event.key ===
                        'End'
                    ) {
                        event
                            .preventDefault();

                        options
                            .at(-1)
                            ?.focus();
                    }

                    if (
                        event.key ===
                            'Enter'
                        ||
                        event.key ===
                            ' '
                    ) {
                        event
                            .preventDefault();

                        selectOption(
                            option,
                        );
                    }
                },
            );
        },
    );


    usePromptLinks.forEach(
        (link) => {
            link.addEventListener(
                'click',
                (event) => {
                    let promptId =
                        null;

                    try {
                        promptId =
                            new URL(
                                link.href,
                                window
                                    .location
                                    .origin,
                            )
                                .searchParams
                                .get(
                                    'prompt',
                                );
                    } catch {
                        promptId =
                            null;
                    }

                    const option =
                        options.find(
                            (item) =>
                                item
                                    .dataset
                                    .promptId
                                ===
                                String(
                                    promptId,
                                ),
                        );

                    if (!option) {
                        return;
                    }

                    event
                        .preventDefault();

                    const explainer =
                        link.closest(
                            '[popover]',
                        );

                    if (
                        explainer
                        &&
                        typeof explainer
                            .hidePopover
                        ===
                        'function'
                    ) {
                        explainer
                            .hidePopover();
                    }

                    selectOption(
                        option,
                        {
                            forceQuestion:
                                true,

                            moveFocusToComposer:
                                true,
                        },
                    );
                },
            );
        },
    );


    /*
    |--------------------------------------------------------------------------
    | Prompt Chaining
    |--------------------------------------------------------------------------
    */

    const optionObserver =
        new MutationObserver(
            (mutations) => {
                mutations.forEach(
                    (mutation) => {
                        const option =
                            mutation.target;

                        if (
                            !(
                                option
                                instanceof
                                HTMLElement
                            )
                            ||
                            !option.matches(
                                '[data-prompt-id]',
                            )
                            ||
                            option
                                .getAttribute(
                                    'aria-selected',
                                )
                            !==
                            'true'
                        ) {
                            return;
                        }

                        const promptId =
                            option
                                .dataset
                                .promptId
                            || '';

                        if (
                            hiddenInput
                            &&
                            hiddenInput
                                .value
                            !==
                            promptId
                        ) {
                            return;
                        }

                        if (
                            applyQuestionTemplate(
                                promptId,
                            )
                        ) {
                            focusComposer();
                        }
                    },
                );
            },
        );


    options.forEach(
        (option) => {
            optionObserver
                .observe(
                    option,
                    {
                        attributes:
                            true,

                        attributeFilter:
                            [
                                'aria-selected',
                            ],
                    },
                );
        },
    );


    document.addEventListener(
        'click',
        (event) => {
            if (
                !menu.contains(
                    event.target,
                )
                &&
                !button.contains(
                    event.target,
                )
                &&
                !secondaryButton
                    ?.contains(
                        event.target,
                    )
            ) {
                setOpen(
                    false,
                );
            }
        },
    );


    document.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key ===
                    'Escape'
                &&
                !menu.hidden
            ) {
                setOpen(
                    false,
                );

                button.focus();
            }
        },
    );
}


/* ============================================================
   CHAT SIDEBAR
   ============================================================ */

function initChatSidebar() {
    const sidebar =
        document.querySelector(
            '[data-chat-sidebar]',
        );

    const backdrop =
        document.querySelector(
            '[data-chat-sidebar-backdrop]',
        );

    const openButton =
        document.querySelector(
            '[data-chat-sidebar-open]',
        );

    const closeButton =
        document.querySelector(
            '[data-chat-sidebar-close]',
        );

    if (
        !sidebar
        ||
        !backdrop
        ||
        !openButton
    ) {
        return;
    }

    const setOpen = (
        open,
    ) => {
        sidebar
            .classList
            .toggle(
                'is-open',
                open,
            );

        backdrop
            .classList
            .toggle(
                'is-open',
                open,
            );

        document
            .body
            .classList
            .toggle(
                'is-locked',
                open,
            );

        openButton
            .setAttribute(
                'aria-expanded',
                String(open),
            );
    };

    openButton
        .addEventListener(
            'click',
            () =>
                setOpen(
                    true,
                ),
        );

    closeButton
        ?.addEventListener(
            'click',
            () =>
                setOpen(
                    false,
                ),
        );

    backdrop
        .addEventListener(
            'click',
            () =>
                setOpen(
                    false,
                ),
        );

    document.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key ===
                'Escape'
            ) {
                setOpen(
                    false,
                );
            }
        },
    );
}


/* ============================================================
   CHAT COMPOSER
   ============================================================ */

function initChatComposer() {
    const input =
        document.querySelector(
            '[data-chat-input]',
        );

    const scroller =
        document.querySelector(
            '[data-chat-scroll]',
        );

    const form =
        document.querySelector(
            '[data-chat-form]',
        );


    const resize = () => {
        if (!input) {
            return;
        }

        input.style.height =
            'auto';

        input.style.height =
            `${Math.min(
                input.scrollHeight,
                160,
            )}px`;
    };


    input
        ?.addEventListener(
            'input',
            () => {
                input.setCustomValidity(
                    '',
                );

                resize();
            },
        );


    resize();


    /*
    |--------------------------------------------------------------------------
    | Submit Guard
    |--------------------------------------------------------------------------
    |
    | Jangan kirim:
    |
    | ... mengenai {topik} ...
    |
    */

    const hasUnresolvedPlaceholder =
        () =>
            /\{[^{}]+\}/
                .test(
                    input?.value
                    || '',
                );


    const focusPlaceholder = () => {
        if (!input) {
            return;
        }

        const match =
            input
                .value
                .match(
                    /\{[^{}]+\}/,
                );

        if (
            !match
            ||
            typeof match.index
                !==
                'number'
        ) {
            return;
        }

        input.focus();

        input
            .setSelectionRange?.(
                match.index,
                match.index
                +
                match[0].length,
            );
    };


    input
        ?.addEventListener(
            'keydown',
            (event) => {
                if (
                    event.key !==
                        'Enter'
                    ||
                    event.shiftKey
                    ||
                    event.isComposing
                ) {
                    return;
                }

                event.preventDefault();

                form
                    ?.requestSubmit();
            },
        );


    form
        ?.addEventListener(
            'submit',
            (event) => {
                if (
                    hasUnresolvedPlaceholder()
                ) {
                    event.preventDefault();

                    input.setCustomValidity(
                        'Lengkapi bagian placeholder sebelum menjalankan research.',
                    );

                    input.reportValidity();

                    focusPlaceholder();

                    return;
                }

                form
                    .querySelectorAll(
                        'button[type="submit"]',
                    )
                    .forEach(
                        (button) => {
                            button.disabled =
                                true;

                            button
                                .setAttribute(
                                    'aria-busy',
                                    'true',
                                );
                        },
                    );
            },
        );


    if (scroller) {
        window
            .requestAnimationFrame(
                () => {
                    scroller.scrollTop =
                        scroller
                            .scrollHeight;
                },
            );
    }
}


/* ============================================================
   AVATAR PREVIEW
   ============================================================ */

function initAvatarPreview() {
    const input =
        document.querySelector(
            '[data-avatar-input]',
        );

    const preview =
        document.querySelector(
            '[data-avatar-preview]',
        );

    if (
        !input
        ||
        !preview
    ) {
        return;
    }

    let temporaryUrl =
        null;

    input.addEventListener(
        'change',
        () => {
            const file =
                input.files?.[0];

            if (!file) {
                return;
            }

            if (temporaryUrl) {
                URL.revokeObjectURL(
                    temporaryUrl,
                );
            }

            temporaryUrl =
                URL.createObjectURL(
                    file,
                );

            let image =
                preview
                    .querySelector(
                        '[data-avatar-image]',
                    );

            if (!image) {
                image =
                    document
                        .createElement(
                            'img',
                        );

                image.className =
                    'profile-photo-preview-img';

                image.alt =
                    'New profile photo preview';

                image.dataset
                    .avatarImage =
                    '';

                preview
                    .appendChild(
                        image,
                    );
            }

            image.src =
                temporaryUrl;

            image.hidden =
                false;
        },
    );


    window.addEventListener(
        'beforeunload',
        () => {
            if (temporaryUrl) {
                URL.revokeObjectURL(
                    temporaryUrl,
                );
            }
        },
    );
}


/* ============================================================
   DATE OF BIRTH
   ============================================================ */

function initDateOfBirthFields() {
    document
        .querySelectorAll(
            '[data-dob-group]',
        )
        .forEach(
            (group) => {
                const month =
                    group
                        .querySelector(
                            '[data-dob-month]',
                        );

                const day =
                    group
                        .querySelector(
                            '[data-dob-day]',
                        );

                const year =
                    group
                        .querySelector(
                            '[data-dob-year]',
                        );

                const output =
                    group
                        .parentElement
                        ?.querySelector(
                            '[data-dob-output]',
                        );

                const form =
                    group.closest(
                        'form',
                    );

                if (
                    !month
                    ||
                    !day
                    ||
                    !year
                    ||
                    !output
                ) {
                    return;
                }

                const sync = () => {
                    if (
                        !month.value
                        ||
                        !day.value
                        ||
                        !year.value
                    ) {
                        output.value =
                            '';

                        return;
                    }

                    output.value =
                        `${year.value}-${String(
                            month.value,
                        ).padStart(
                            2,
                            '0',
                        )}-${String(
                            day.value,
                        ).padStart(
                            2,
                            '0',
                        )}`;
                };

                [
                    month,
                    day,
                    year,
                ].forEach(
                    (element) =>
                        element
                            .addEventListener(
                                'change',
                                sync,
                            ),
                );

                form
                    ?.addEventListener(
                        'submit',
                        sync,
                    );
            },
        );
}


/* ============================================================
   PROFILE PROGRESS
   ============================================================ */

function initProfileProgress() {
    document
        .querySelectorAll(
            '[data-progress-value]',
        )
        .forEach(
            (bar) => {
                const value =
                    Math.max(
                        0,

                        Math.min(
                            100,

                            Number(
                                bar
                                    .dataset
                                    .progressValue,
                            )
                            ||
                            0,
                        ),
                    );

                bar.style.width =
                    `${value}%`;
            },
        );
}


/* ============================================================
   NETWORK
   ============================================================ */

function initNetworkState() {
    const banner =
        document.querySelector(
            '[data-network-banner]',
        );

    if (!banner) {
        return;
    }

    const update = () => {
        banner.hidden =
            navigator.onLine;
    };

    window
        .addEventListener(
            'online',
            update,
        );

    window
        .addEventListener(
            'offline',
            update,
        );

    update();
}


/* ============================================================
   AVATAR FALLBACK
   ============================================================ */

function initAvatarFallbacks() {
    document
        .querySelectorAll(
            '[data-avatar-image]',
        )
        .forEach(
            (image) => {
                const hideBrokenImage =
                    () => {
                        image.hidden =
                            true;

                        image
                            .setAttribute(
                                'aria-hidden',
                                'true',
                            );
                    };

                image.addEventListener(
                    'error',
                    hideBrokenImage,
                );

                if (
                    image.complete
                    &&
                    image.naturalWidth ===
                    0
                ) {
                    hideBrokenImage();
                }
            },
        );
}


/* ============================================================
   TOASTS
   ============================================================ */

function initToasts() {
    const toast =
        document.querySelector(
            '[data-admin-toast]',
        );

    if (!toast) {
        return;
    }

    window.setTimeout(
        () => {
            toast
                .animate(
                    [
                        {
                            opacity: 1,
                            transform:
                                'translateY(0)',
                        },

                        {
                            opacity: 0,
                            transform:
                                'translateY(-8px)',
                        },
                    ],
                    {
                        duration:
                            240,

                        easing:
                            'ease',

                        fill:
                            'forwards',
                    },
                )
                .finished
                .then(
                    () =>
                        toast.remove(),
                );
        },
        4200,
    );
}


/* ============================================================
   REVEALS
   ============================================================ */

function animateReveals() {
    const elements = [
        ...document
            .querySelectorAll(
                '[data-reveal], .reveal, [data-admin-reveal]',
            ),
    ];

    if (
        elements.length === 0
        ||
        !Element
            .prototype
            .animate
    ) {
        return;
    }

    const offsets = {
        left:
            'translate3d(-26px, 0, 0)',

        right:
            'translate3d(26px, 0, 0)',

        up:
            'translate3d(0, -18px, 0)',

        down:
            'translate3d(0, 24px, 0)',
    };

    elements.forEach(
        (element) => {
            element.style.opacity =
                '0';

            element.style.transform =
                offsets[
                    element
                        .dataset
                        .revealFrom
                    ||
                    'down'
                ]
                ||
                offsets.down;
        },
    );


    const reveal = (
        element,
    ) => {
        if (
            element
                .dataset
                .revealed
            ===
            'true'
        ) {
            return;
        }

        element.dataset.revealed =
            'true';

        const direction =
            element
                .dataset
                .revealFrom
            ||
            'down';

        const offset =
            offsets[
                direction
            ]
            ||
            offsets.down;

        const delay =
            Math.max(
                0,

                Number(
                    element
                        .dataset
                        .revealDelay,
                )
                ||
                0,
            );

        const animation =
            element.animate(
                [
                    {
                        opacity: 0,

                        transform:
                            `${offset} scale(.985)`,

                        filter:
                            'blur(3px)',
                    },

                    {
                        opacity: 1,

                        transform:
                            'translate3d(0, 0, 0) scale(1)',

                        filter:
                            'blur(0)',
                    },
                ],
                {
                    duration:
                        620,

                    delay,

                    easing:
                        'cubic-bezier(.22,1,.36,1)',

                    fill:
                        'forwards',
                },
            );

        animation
            .finished
            .then(
                () => {
                    element.style.opacity =
                        '1';

                    element.style.transform =
                        '';

                    element.style.filter =
                        '';

                    animation.cancel();
                },
            )
            .catch(
                () => {},
            );
    };


    if (
        !(
            'IntersectionObserver'
            in
            window
        )
    ) {
        elements.forEach(
            reveal,
        );

        return;
    }


    const observer =
        new IntersectionObserver(
            (entries) => {
                const visibleEntries =
                    entries
                        .filter(
                            (entry) =>
                                entry
                                    .isIntersecting,
                        )
                        .sort(
                            (
                                a,
                                b,
                            ) =>
                                a
                                    .boundingClientRect
                                    .top
                                -
                                b
                                    .boundingClientRect
                                    .top,
                        );

                visibleEntries
                    .forEach(
                        (
                            entry,
                            index,
                        ) => {
                            if (
                                !entry
                                    .target
                                    .dataset
                                    .revealDelay
                            ) {
                                entry
                                    .target
                                    .dataset
                                    .revealDelay =
                                    String(
                                        Math.min(
                                            index
                                            *
                                            70,

                                            280,
                                        ),
                                    );
                            }

                            reveal(
                                entry.target,
                            );

                            observer
                                .unobserve(
                                    entry.target,
                                );
                        },
                    );
            },
            {
                rootMargin:
                    '0px 0px -10% 0px',

                threshold:
                    0.12,
            },
        );

    elements.forEach(
        (element) =>
            observer.observe(
                element,
            ),
    );
}


/* ============================================================
   MOTION
   ============================================================ */

function initMotionDetails() {
    const heroCard =
        document.querySelector(
            '[data-motion-card]',
        );

    if (
        heroCard
        &&
        window
            .matchMedia(
                '(pointer: fine)',
            )
            .matches
    ) {
        const reset = () => {
            heroCard.style.transform =
                '';
        };

        heroCard
            .addEventListener(
                'pointermove',
                (event) => {
                    const rect =
                        heroCard
                            .getBoundingClientRect();

                    const x =
                        (
                            event.clientX
                            -
                            rect.left
                        )
                        /
                        rect.width
                        -
                        0.5;

                    const y =
                        (
                            event.clientY
                            -
                            rect.top
                        )
                        /
                        rect.height
                        -
                        0.5;

                    const rotateX =
                        y
                        *
                        -2.5;

                    const rotateY =
                        x
                        *
                        3.5;

                    heroCard
                        .style
                        .transform =
                        `perspective(1100px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-2px)`;
                },
            );

        heroCard
            .addEventListener(
                'pointerleave',
                reset,
            );

        heroCard
            .addEventListener(
                'blur',
                reset,
                true,
            );
    }


    document
        .querySelectorAll(
            '[data-motion-button]',
        )
        .forEach(
            (button) => {
                button
                    .addEventListener(
                        'pointermove',
                        (event) => {
                            const rect =
                                button
                                    .getBoundingClientRect();

                            button
                                .style
                                .setProperty(
                                    '--motion-x',
                                    `${event.clientX - rect.left}px`,
                                );

                            button
                                .style
                                .setProperty(
                                    '--motion-y',
                                    `${event.clientY - rect.top}px`,
                                );
                        },
                    );
            },
        );
}
