const onReady = (callback) => {
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


const q = (
    selector,
    scope = document,
) =>
    scope.querySelector(
        selector,
    );


const qa = (
    selector,
    scope = document,
) =>
    Array.from(
        scope.querySelectorAll(
            selector,
        ),
    );


const wait = (
    milliseconds,
) =>
    new Promise(
        (
            resolve,
        ) => {

            window.setTimeout(
                resolve,
                milliseconds,
            );

        },
    );


onReady(
    () => {

        const page =
            q(
                '.landing-reference-page',
            );


        if (!page) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Bersihkan state lama dari markup/CSS versi sebelumnya.
        |--------------------------------------------------------------------------
        */

        cleanupLegacyStates(
            page,
        );


        removeLargeHeadingPeriods(
            page,
        );


        initToolCards(
            page,
        );


        const reduceMotion =
            window
                .matchMedia(
                    '(prefers-reduced-motion: reduce)',
                )
                .matches;


        initEvidenceFlow(
            page,
            reduceMotion,
        );


        if (reduceMotion) {
            return;
        }


        // Defer decorative motion until the page has painted.
        window.setTimeout(() => {
            if (document.hidden || !page.isConnected) return;
            initHeroLoop(page);
            initWorkflowLoop(page);
            initDocumentLoop(page);
        }, 350);

    },
);


/* ==========================================================================
   CLEAN OLD STATES
   ========================================================================== */

function cleanupLegacyStates(
    page,
) {

    /*
    |--------------------------------------------------------------------------
    | Workflow lama punya:
    | <article class="is-active">
    |--------------------------------------------------------------------------
    */

    qa(
        '.br-workflow__track article',
        page,
    )
        .forEach(
            (
                card,
            ) => {

                card.classList.remove(
                    'is-active',
                    'is-current',
                );

            },
        );


    /*
    |--------------------------------------------------------------------------
    | Tool Research Gap lama punya is-featured.
    | Sekarang semua default harus sama.
    |--------------------------------------------------------------------------
    */

    qa(
        '.br-tool-card',
        page,
    )
        .forEach(
            (
                card,
            ) => {

                card.classList.remove(
                    'is-featured',
                    'is-default-active',
                    'is-active',
                );

            },
        );


    /*
    |--------------------------------------------------------------------------
    | Spark/petir lama dibuang.
    |--------------------------------------------------------------------------
    */

    qa(
        '.br-tool-card__spark',
        page,
    )
        .forEach(
            (
                spark,
            ) => {

                spark.remove();

            },
        );

}


/* ==========================================================================
   REMOVE PERIOD FROM BIG HEADINGS
   ========================================================================== */

function removeLargeHeadingPeriods(
    page,
) {

    const headings =
        qa(
            [
                '.br-hero__title',
                '.br-deep__title',
                '.br-trust__title',
                '.br-workflow__header h2',
                '.br-tools__header h2',
                '.bd-document-story__copy h2',
                '.br-final-cta h2',
            ].join(', '),
            page,
        );


    headings.forEach(
        (
            heading,
        ) => {

            const walker =
                document
                    .createTreeWalker(
                        heading,
                        NodeFilter
                            .SHOW_TEXT,
                    );


            const textNodes = [];


            let node =
                walker
                    .nextNode();


            while (node) {

                if (
                    node
                        .nodeValue
                        ?.trim()
                ) {

                    textNodes
                        .push(
                            node,
                        );

                }


                node =
                    walker
                        .nextNode();

            }


            const last =
                textNodes
                    .at(-1);


            if (!last) {
                return;
            }


            last.nodeValue =
                last
                    .nodeValue
                    .replace(
                        /\.\s*$/,
                        '',
                    );

        },
    );

}


/* ==========================================================================
   HERO
   ========================================================================== */

function initHeroLoop(
    page,
) {

    const visual =
        q(
            '.br-discovery',
            page,
        );


    if (!visual) {
        return;
    }


    const keywords =
        qa(
            '.br-keyword',
            visual,
        );


    const papers =
        qa(
            '.br-discovery__paper',
            visual,
        );


    const routes =
        qa(
            '.br-discovery__routes path',
            visual,
        );


    const magnifier =
        q(
            '.br-magnifier',
            visual,
        );


    const searchButtons =
        qa(
            '.br-search__submit',
            page,
        );


    if (!keywords.length) {
        return;
    }


    let index = 0;


    const activate =
        (
            targetIndex,
        ) => {

            keywords
                .forEach(
                    (
                        keyword,
                        keywordIndex,
                    ) => {

                        keyword
                            .classList
                            .toggle(
                                'is-active',
                                keywordIndex
                                    ===
                                    targetIndex,
                            );

                    },
                );


            routes
                .forEach(
                    (
                        route,
                        routeIndex,
                    ) => {

                        route
                            .classList
                            .toggle(
                                'is-active',
                                routeIndex
                                    ===
                                    targetIndex,
                            );

                    },
                );


            papers
                .forEach(
                    (
                        paper,
                        paperIndex,
                    ) => {

                        paper
                            .classList
                            .toggle(
                                'is-active',
                                paperIndex
                                    ===
                                    targetIndex,
                            );

                    },
                );


            if (magnifier) {

                magnifier
                    .classList
                    .remove(
                        'is-scan-0',
                        'is-scan-1',
                        'is-scan-2',
                    );


                magnifier
                    .classList
                    .add(
                        `is-scan-${targetIndex}`,
                    );

            }


            searchButtons
                .forEach(
                    (
                        button,
                    ) => {

                        button
                            .classList
                            .remove(
                                'is-active',
                            );


                        window
                            .requestAnimationFrame(
                                () => {

                                    button
                                        .classList
                                        .add(
                                            'is-active',
                                        );


                                    window
                                        .setTimeout(
                                            () => {

                                                button
                                                    .classList
                                                    .remove(
                                                        'is-active',
                                                    );

                                            },
                                            650,
                                        );

                                },
                            );

                    },
                );

        };


    activate(
        0,
    );


    window
        .setInterval(
            () => {

                index =
                    (
                        index
                        +
                        1
                    )
                    %
                    keywords.length;


                activate(
                    index,
                );

            },
            1750,
        );

}


/* ==========================================================================
   EVIDENCE FLOW
   Satu-satunya orkestrasi untuk desktop dan mobile: satu IntersectionObserver
   menjalankan satu rantai timer yang mengganti data-flow-state pada
   .br-evidence-panel. Garis, node, dan legend diturunkan dari state itu di
   landing-refinement.css. Loop hanya berjalan saat panel
   terlihat dan tab aktif; di luar itu timer dihentikan dan state kembali idle.
   ========================================================================== */

// [state, ms sampai state berikutnya]. Total satu siklus ≈ 3,1 dtk:
// sumber 0–400 → garis 400–830 → evidence 830–1210 → garis 1210–1630
// → insight 1630–2030 → hold ~750 → reset 300.
const EVIDENCE_FLOW_SCHEDULE = [
    ['sources', 400],
    ['line-in', 430],
    ['evidence', 380],
    ['line-out', 420],
    ['insight', 1150],
    ['idle', 300],
];

function initEvidenceFlow(
    page,
    reduceMotion,
) {

    const panel =
        q(
            '.br-evidence-panel',
            page,
        );

    // Tanpa data-flow-state, CSS menampilkan tampilan final.
    if (
        !panel
        ||
        reduceMotion
        ||
        !('IntersectionObserver' in window)
    ) {
        return;
    }

    let timer = 0;
    let inView = false;

    const stop = () => {

        window.clearTimeout(timer);

        timer = 0;

        panel.dataset.flowState = 'idle';

    };

    const step = (index) => {

        const [state, duration] =
            EVIDENCE_FLOW_SCHEDULE[index];

        panel.dataset.flowState = state;

        timer =
            window.setTimeout(
                () => step(
                    (index + 1) % EVIDENCE_FLOW_SCHEDULE.length,
                ),
                duration,
            );

    };

    const sync = () => {

        const shouldRun = inView && !document.hidden;

        if (shouldRun && !timer) {
            step(0);
        } else if (!shouldRun && timer) {
            stop();
        }

    };

    panel.dataset.flowState = 'idle';

    new IntersectionObserver(
        (entries) => {

            const entry = entries[entries.length - 1];

            // Mulai di ±30% terlihat; berhenti hanya saat benar-benar keluar.
            if (entry.intersectionRatio >= 0.3) {
                inView = true;
            } else if (!entry.isIntersecting) {
                inView = false;
            }

            sync();

        },
        {
            threshold: [0, 0.3],
        },
    ).observe(panel);

    document.addEventListener(
        'visibilitychange',
        sync,
    );

}


/* ==========================================================================
   WORKFLOW
   ========================================================================== */

function initWorkflowLoop(
    page,
) {

    const track =
        q(
            '.br-workflow__track',
            page,
        );


    if (!track) {
        return;
    }


    const cards =
        qa(
            ':scope > article',
            track,
        );


    if (!cards.length) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Hilangkan state bawaan lama.
    |--------------------------------------------------------------------------
    */

    cards
        .forEach(
            (
                card,
            ) => {

                card
                    .classList
                    .remove(
                        'is-active',
                        'is-current',
                    );

            },
        );


    /*
    |--------------------------------------------------------------------------
    | Moving dot.
    |--------------------------------------------------------------------------
    */

    q(
        '.br-workflow-runner',
        track,
    )
        ?.remove();


    const runner =
        document
            .createElement(
                'span',
            );


    runner.className =
        'br-workflow-runner';


    runner.setAttribute(
        'aria-hidden',
        'true',
    );


    track
        .appendChild(
            runner,
        );


    let index = 0;


    const moveRunner =
        (
            targetIndex,
            immediate = false,
        ) => {

            const card =
                cards[
                    targetIndex
                ];


            if (!card) {
                return;
            }


            cards
                .forEach(
                    (
                        item,
                        cardIndex,
                    ) => {

                        item
                            .classList
                            .toggle(
                                'is-current',
                                cardIndex
                                    ===
                                    targetIndex,
                            );

                    },
                );


            const trackRect =
                track
                    .getBoundingClientRect();


            const cardRect =
                card
                    .getBoundingClientRect();


            const center =
                cardRect.left
                -
                trackRect.left
                +
                (
                    cardRect.width
                    /
                    2
                );


            if (immediate) {

                runner.style.transition =
                    'none';


                runner.style.left =
                    `${center}px`;


                window
                    .requestAnimationFrame(
                        () => {

                            runner.style.transition =
                                '';

                        },
                    );


                return;
            }


            runner.style.left =
                `${center}px`;

        };


    moveRunner(
        0,
        true,
    );


    window
        .setInterval(
            () => {

                index =
                    (
                        index
                        +
                        1
                    )
                    %
                    cards.length;


                moveRunner(
                    index,
                );

            },
            1550,
        );


    window
        .addEventListener(
            'resize',
            () => {

                moveRunner(
                    index,
                    true,
                );

            },
            {
                passive: true,
            },
        );

}


/* ==========================================================================
   RESEARCH TOOL CARDS
   ========================================================================== */

function initToolCards(
    page,
) {

    const cards =
        qa(
            '.br-tool-card',
            page,
        );


    if (!cards.length) {
        return;
    }


    const clear =
        () => {

            cards
                .forEach(
                    (
                        card,
                    ) => {

                        card
                            .classList
                            .remove(
                                'is-active',
                                'is-default-active',
                                'is-featured',
                            );

                    },
                );

        };


    /*
    |--------------------------------------------------------------------------
    | Semua netral saat pertama load.
    |--------------------------------------------------------------------------
    */

    clear();


    cards
        .forEach(
            (
                card,
            ) => {

                /*
                |--------------------------------------------------------------------------
                | Desktop hover
                |--------------------------------------------------------------------------
                */

                card
                    .addEventListener(
                        'mouseenter',
                        () => {

                            clear();


                            card
                                .classList
                                .add(
                                    'is-active',
                                );

                        },
                    );


                card
                    .addEventListener(
                        'mouseleave',
                        () => {

                            card
                                .classList
                                .remove(
                                    'is-active',
                                );

                        },
                    );


                /*
                |--------------------------------------------------------------------------
                | Keyboard
                |--------------------------------------------------------------------------
                */

                card
                    .addEventListener(
                        'focusin',
                        () => {

                            clear();


                            card
                                .classList
                                .add(
                                    'is-active',
                                );

                        },
                    );


                card
                    .addEventListener(
                        'focusout',
                        () => {

                            card
                                .classList
                                .remove(
                                    'is-active',
                                );

                        },
                    );


                /*
                |--------------------------------------------------------------------------
                | Mobile touch.
                | Card yang disentuh menyala.
                |--------------------------------------------------------------------------
                */

                card
                    .addEventListener(
                        'touchstart',
                        () => {

                            clear();


                            card
                                .classList
                                .add(
                                    'is-active',
                                );

                        },
                        {
                            passive: true,
                        },
                    );

            },
        );

}


/* ==========================================================================
   DOCUMENT SECTION
   ========================================================================== */

function initDocumentLoop(
    page,
) {

    const preview =
        q(
            '.bd-document-preview',
            page,
        );


    const listItems =
        qa(
            '.bd-document-story__list li',
            page,
        );


    if (
        !preview
        &&
        !listItems.length
    ) {
        return;
    }


    let index = 0;


    const activate =
        (
            targetIndex,
        ) => {

            listItems
                .forEach(
                    (
                        item,
                        itemIndex,
                    ) => {

                        item
                            .classList
                            .toggle(
                                'is-active',
                                itemIndex
                                    ===
                                    targetIndex,
                            );

                    },
                );

        };


    if (
        listItems.length
    ) {

        activate(
            0,
        );


        window
            .setInterval(
                () => {

                    index =
                        (
                            index
                            +
                            1
                        )
                        %
                        listItems
                            .length;


                    activate(
                        index,
                    );

                },
                1750,
            );

    }


    if (preview) {

        preview
            .addEventListener(
                'mouseenter',
                () => {

                    preview
                        .classList
                        .add(
                            'is-soft-lit',
                        );

                },
            );


        preview
            .addEventListener(
                'mouseleave',
                () => {

                    preview
                        .classList
                        .remove(
                            'is-soft-lit',
                        );

                },
            );

    }

}