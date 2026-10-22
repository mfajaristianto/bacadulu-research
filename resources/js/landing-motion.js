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


        if (reduceMotion) {
            return;
        }


        // Defer decorative motion until the page has painted.
        window.setTimeout(() => {
            if (document.hidden || !page.isConnected) return;
            initHeroLoop(page);
            initEvidenceLoop(page);
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
   EVIDENCE PANEL
   ========================================================================== */

function initEvidenceLoop(
    page,
) {

    const panel =
        q(
            '.br-evidence-panel',
            page,
        );


    if (!panel) {
        return;
    }


    const flow =
        q(
            '.br-evidence-panel__flow',
            panel,
        );


    const connector =
        q(
            '.br-evidence-panel__connectors',
            panel,
        );


    const sourceCards =
        qa(
            '.br-source-stack > div',
            panel,
        );


    const paths =
        qa(
            '.br-evidence-panel__connectors path',
            panel,
        );


    const analysisNode =
        q(
            '.br-analysis-node',
            panel,
        );


    const insightNode =
        q(
            '.br-insight-node',
            panel,
        );


    const legends =
        qa(
            '.br-evidence-panel__legend span',
            panel,
        );


    if (
        !flow
        ||
        !sourceCards.length
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Kalau Blade versi lama/baru sudah punya dots,
    | hapus dulu biar tidak dobel.
    |--------------------------------------------------------------------------
    */

    qa(
        '.br-flow-dot',
        flow,
    )
        .forEach(
            (
                dot,
            ) => {

                dot.remove();

            },
        );


    const dots = [];


    for (
        let index = 0;
        index < 4;
        index += 1
    ) {

        const dot =
            document
                .createElement(
                    'span',
                );


        dot.className =
            'br-flow-dot';


        dot.dataset
            .flowDot =
            String(index);


        flow
            .appendChild(
                dot,
            );


        dots.push(
            dot,
        );

    }


    const mobileMedia =
        window
            .matchMedia(
                '(max-width: 760px)',
            );


    const clearStates =
        () => {

            sourceCards
                .forEach(
                    (
                        card,
                    ) => {

                        card
                            .classList
                            .remove(
                                'is-active',
                            );

                    },
                );


            paths
                .forEach(
                    (
                        path,
                    ) => {

                        path
                            .classList
                            .remove(
                                'is-flow',
                            );

                    },
                );


            dots
                .forEach(
                    (
                        dot,
                    ) => {

                        dot
                            .classList
                            .remove(
                                'is-visible',
                                'is-arrived',
                            );


                        dot.style.left =
                            '-40px';


                        dot.style.top =
                            '-40px';

                    },
                );


            analysisNode
                ?.classList
                .remove(
                    'is-active',
                );


            insightNode
                ?.classList
                .remove(
                    'is-active',
                );


            legends
                .forEach(
                    (
                        legend,
                    ) => {

                        legend
                            .classList
                            .remove(
                                'is-active',
                            );

                    },
                );

        };


    const svgPointToFlow =
        (
            point,
        ) => {

            if (!connector) {

                return {
                    x: 0,
                    y: 0,
                };

            }


            const viewBox =
                connector
                    .viewBox
                    .baseVal;


            const scaleX =
                connector
                    .clientWidth
                /
                viewBox
                    .width;


            const scaleY =
                connector
                    .clientHeight
                /
                viewBox
                    .height;


            return {

                x:
                    point.x
                    *
                    scaleX,

                y:
                    point.y
                    *
                    scaleY,

            };

        };


    const setDotPosition =
        (
            dot,
            point,
            offsetY = 0,
        ) => {

            const converted =
                svgPointToFlow(
                    point,
                );


            dot.style.left =
                `${converted.x}px`;


            dot.style.top =
                `${
                    converted.y
                    +
                    offsetY
                }px`;

        };


    const animateAlongPath =
        (
            path,
            dot,
            duration,
            parkOffsetY = 0,
        ) => {

            return new Promise(
                (
                    resolve,
                ) => {

                    if (
                        !path
                        ||
                        !dot
                        ||
                        mobileMedia.matches
                    ) {

                        resolve();
                        return;
                    }


                    const totalLength =
                        path
                            .getTotalLength();


                    let startTime =
                        null;


                    path
                        .classList
                        .add(
                            'is-flow',
                        );


                    dot
                        .classList
                        .add(
                            'is-visible',
                        );


                    const frame =
                        (
                            timestamp,
                        ) => {

                            if (
                                startTime
                                ===
                                null
                            ) {

                                startTime =
                                    timestamp;

                            }


                            const elapsed =
                                timestamp
                                -
                                startTime;


                            const progress =
                                Math.min(
                                    elapsed
                                    /
                                    duration,
                                    1,
                                );


                            const eased =
                                1
                                -
                                Math.pow(
                                    1
                                    -
                                    progress,
                                    3,
                                );


                            const point =
                                path
                                    .getPointAtLength(
                                        totalLength
                                        *
                                        eased,
                                    );


                            setDotPosition(
                                dot,
                                point,
                            );


                            if (
                                progress
                                <
                                1
                            ) {

                                window
                                    .requestAnimationFrame(
                                        frame,
                                    );


                                return;
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Source 1/2/3 berhenti di area tengah,
                            | tapi sedikit dipisah biar tidak menumpuk persis.
                            |--------------------------------------------------------------------------
                            */

                            const finalPoint =
                                path
                                    .getPointAtLength(
                                        totalLength,
                                    );


                            setDotPosition(
                                dot,
                                finalPoint,
                                parkOffsetY,
                            );


                            dot
                                .classList
                                .add(
                                    'is-arrived',
                                );


                            resolve();

                        };


                    window
                        .requestAnimationFrame(
                            frame,
                        );

                },
            );

        };


    const runDesktopCycle =
        async () => {

            clearStates();


            legends[0]
                ?.classList
                .add(
                    'is-active',
                );


            /*
            |--------------------------------------------------------------------------
            | 1 — Jurnal
            |--------------------------------------------------------------------------
            */

            sourceCards[0]
                ?.classList
                .add(
                    'is-active',
                );


            await animateAlongPath(
                paths[0],
                dots[0],
                820,
                -11,
            );


            await wait(
                180,
            );


            /*
            |--------------------------------------------------------------------------
            | 2 — Buku
            |--------------------------------------------------------------------------
            */

            sourceCards[1]
                ?.classList
                .add(
                    'is-active',
                );


            await animateAlongPath(
                paths[1],
                dots[1],
                760,
                0,
            );


            await wait(
                180,
            );


            /*
            |--------------------------------------------------------------------------
            | 3 — Prosiding
            |--------------------------------------------------------------------------
            */

            sourceCards[2]
                ?.classList
                .add(
                    'is-active',
                );


            await animateAlongPath(
                paths[2],
                dots[2],
                820,
                11,
            );


            await wait(
                250,
            );


            /*
            |--------------------------------------------------------------------------
            | Semua source sampai.
            | Analysis node baru menyala.
            |--------------------------------------------------------------------------
            */

            analysisNode
                ?.classList
                .add(
                    'is-active',
                );


            legends[1]
                ?.classList
                .add(
                    'is-active',
                );


            await wait(
                650,
            );


            /*
            |--------------------------------------------------------------------------
            | Analysis → Insight
            |--------------------------------------------------------------------------
            */

            await animateAlongPath(
                paths[3],
                dots[3],
                950,
                0,
            );


            insightNode
                ?.classList
                .add(
                    'is-active',
                );


            legends[2]
                ?.classList
                .add(
                    'is-active',
                );


            await wait(
                1400,
            );

        };


    const runMobileCycle =
        async () => {

            clearStates();


            legends[0]
                ?.classList
                .add(
                    'is-active',
                );


            for (
                let index = 0;
                index < sourceCards.length;
                index += 1
            ) {

                sourceCards[index]
                    ?.classList
                    .add(
                        'is-active',
                    );


                await wait(
                    650,
                );

            }


            analysisNode
                ?.classList
                .add(
                    'is-active',
                );


            legends[1]
                ?.classList
                .add(
                    'is-active',
                );


            await wait(
                850,
            );


            insightNode
                ?.classList
                .add(
                    'is-active',
                );


            legends[2]
                ?.classList
                .add(
                    'is-active',
                );


            await wait(
                1200,
            );

        };


    const loop =
        async () => {

            while (
                document
                    .documentElement
                    .contains(
                        panel,
                    )
            ) {

                if (
                    document.hidden
                ) {

                    await wait(
                        700,
                    );


                    continue;

                }


                if (
                    mobileMedia
                        .matches
                ) {

                    await runMobileCycle();

                } else {

                    await runDesktopCycle();

                }


                await wait(
                    350,
                );

            }

        };


    loop();

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