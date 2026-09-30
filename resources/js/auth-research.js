import { gsap } from 'gsap';


const ready = (callback) => {
    if (
        document.readyState
        === 'loading'
    ) {
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
    const page =
        document.querySelector(
            '[data-research-auth]',
        );


    if (!page) {
        return;
    }


    const reduceMotion =
        window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;


    initJourneyLoops(
        page,
        reduceMotion,
    );


    if (reduceMotion) {
        return;
    }


    initAmbientMotion(
        page,
    );


    initLoginWorkspaceMotion(
        page,
    );


    initRegisterFlowMotion(
        page,
    );
});


/*
|--------------------------------------------------------------------------
| Journey Loop
|--------------------------------------------------------------------------
|
| 01 → 02 → 03 → 04 → kembali 01.
|
*/

function initJourneyLoops(
    page,
    reduceMotion,
) {
    page
        .querySelectorAll(
            '[data-auth-journey]',
        )
        .forEach(
            (journey) => {
                const steps = [
                    ...journey
                        .querySelectorAll(
                            '[data-auth-journey-step]',
                        ),
                ];


                const progress =
                    journey
                        .querySelector(
                            '[data-auth-journey-progress]',
                        );


                if (!steps.length) {
                    return;
                }


                let activeIndex = 0;

                let timer = null;


                const paint = () => {
                    const denominator =
                        Math.max(
                            steps.length - 1,
                            1,
                        );


                    const percentage =
                        `${
                            (
                                activeIndex
                                / denominator
                            )
                            * 100
                        }%`;


                    journey
                        .style
                        .setProperty(
                            '--journey-progress',
                            percentage,
                        );


                    if (progress) {
                        progress
                            .style
                            .width =
                            percentage;
                    }


                    steps.forEach(
                        (
                            step,
                            index,
                        ) => {
                            step
                                .classList
                                .toggle(
                                    'is-past',
                                    index
                                    < activeIndex,
                                );


                            step
                                .classList
                                .toggle(
                                    'is-current',
                                    index
                                    === activeIndex,
                                );


                            step
                                .classList
                                .toggle(
                                    'is-future',
                                    index
                                    > activeIndex,
                                );
                        },
                    );


                    if (
                        !reduceMotion
                    ) {
                        const current =
                            steps[
                                activeIndex
                            ]
                                ?.querySelector(
                                    'i',
                                );


                        if (current) {
                            gsap
                                .fromTo(
                                    current,

                                    {
                                        scale:
                                            .82,
                                    },

                                    {
                                        scale:
                                            1.15,

                                        duration:
                                            .34,

                                        ease:
                                            'back.out(2)',

                                        clearProps:
                                            'transform',
                                    },
                                );
                        }
                    }
                };


                const start = () => {
                    if (
                        reduceMotion
                        || timer
                    ) {
                        return;
                    }


                    timer =
                        window
                            .setInterval(
                                () => {
                                    activeIndex =
                                        (
                                            activeIndex
                                            + 1
                                        )
                                        % steps.length;


                                    paint();
                                },

                                1450,
                            );
                };


                const stop = () => {
                    if (!timer) {
                        return;
                    }


                    window
                        .clearInterval(
                            timer,
                        );


                    timer =
                        null;
                };


                paint();

                start();


                document
                    .addEventListener(
                        'visibilitychange',
                        () => {
                            if (
                                document.hidden
                            ) {
                                stop();

                                return;
                            }


                            start();
                        },
                    );
            },
        );
}


/*
|--------------------------------------------------------------------------
| Background
|--------------------------------------------------------------------------
*/

function initAmbientMotion(
    page,
) {
    const blobMotion = [
        [
            '.research-auth-final__blob--coral',

            {
                x: 26,
                y: 18,
                scale: 1.04,
                duration: 7.2,
            },
        ],

        [
            '.research-auth-final__blob--teal',

            {
                x: -22,
                y: 18,
                scale: 1.03,
                duration: 6.5,
            },
        ],

        [
            '.research-auth-final__blob--blue',

            {
                x: -25,
                y: 25,
                scale: 1.035,
                duration: 8.1,
            },
        ],

        [
            '.research-auth-final__blob--peach',

            {
                x: -22,
                y: -19,
                scale: 1.03,
                duration: 9.2,
            },
        ],
    ];


    blobMotion
        .forEach(
            (
                [
                    selector,
                    values,
                ],
            ) => {
                const element =
                    page
                        .querySelector(
                            selector,
                        );


                if (!element) {
                    return;
                }


                gsap.to(
                    element,
                    {
                        ...values,

                        repeat: -1,

                        yoyo: true,

                        ease:
                            'sine.inOut',
                    },
                );
            },
        );


    const bubbles =
        page
            .querySelectorAll(
                '.research-auth-final__bubble',
            );


    if (
        bubbles.length
    ) {
        gsap.to(
            bubbles,
            {
                y: -13,

                x: 4,

                rotation: 8,

                duration: 3.1,

                stagger: {
                    each: .33,

                    repeat: -1,

                    yoyo: true,
                },

                ease:
                    'sine.inOut',
            },
        );
    }


    const grid =
        page
            .querySelector(
                '[data-auth-grid]',
            );


    if (grid) {
        gsap.to(
            grid,
            {
                backgroundPosition:
                    '30px 30px',

                duration: 13,

                repeat: -1,

                ease: 'none',
            },
        );
    }
}


/*
|--------------------------------------------------------------------------
| Login Workspace Motion
|--------------------------------------------------------------------------
*/

function initLoginWorkspaceMotion(
    page,
) {
    const workspace =
        page
            .querySelector(
                '[data-auth-workspace]',
            );


    if (!workspace) {
        return;
    }


    /*
     * Main workspace floating.
     */

    gsap.to(
        workspace,
        {
            y: -7,

            duration: 3.6,

            repeat: -1,

            yoyo: true,

            ease:
                'sine.inOut',
        },
    );


    /*
     * Search orb.
     */

    const orb =
        page
            .querySelector(
                '[data-auth-search-orb]',
            );


    if (orb) {
        gsap.to(
            orb,
            {
                y: -8,

                x: 3,

                rotation: 5,

                duration: 2.8,

                repeat: -1,

                yoyo: true,

                ease:
                    'sine.inOut',
            },
        );
    }


    /*
     * Evidence card pulse.
     */

    const evidence =
        page
            .querySelector(
                '[data-auth-evidence-card]',
            );


    if (evidence) {
        gsap
            .timeline({
                repeat: -1,

                repeatDelay:
                    .7,
            })

            .to(
                evidence,
                {
                    x: 4,

                    backgroundColor:
                        'rgba(32, 170, 166, .14)',

                    boxShadow:
                        '0 9px 20px rgba(32, 170, 166, .08)',

                    duration:
                        .55,

                    ease:
                        'power2.out',
                },
            )

            .to(
                evidence,
                {
                    x: 0,

                    backgroundColor:
                        'rgba(32, 170, 166, .09)',

                    boxShadow:
                        '0 0 0 rgba(32, 170, 166, 0)',

                    duration:
                        .6,

                    ease:
                        'power2.inOut',
                },
            );
    }


    /*
     * Active Evidence navigation.
     */

    const activeNav =
        page
            .querySelector(
                '[data-auth-evidence-nav]',
            );


    if (activeNav) {
        gsap.to(
            activeNav,
            {
                x: 3,

                duration: 1.7,

                repeat: -1,

                yoyo: true,

                ease:
                    'sine.inOut',
            },
        );
    }


    /*
     * Source lines breathe.
     */

    const lines = [
        ...page
            .querySelectorAll(
                '[data-auth-lines] span',
            ),
    ];


    lines
        .forEach(
            (
                line,
                index,
            ) => {
                gsap.to(
                    line,
                    {
                        scaleX:
                            index === 0
                                ? .82
                                : .90,

                        opacity:
                            .72,

                        duration:
                            1.7
                            + (
                                index
                                * .22
                            ),

                        repeat:
                            -1,

                        yoyo:
                            true,

                        ease:
                            'sine.inOut',
                    },
                );
            },
        );
}


/*
|--------------------------------------------------------------------------
| Register Research Flow
|--------------------------------------------------------------------------
*/

function initRegisterFlowMotion(
    page,
) {
    const flow =
        page
            .querySelector(
                '[data-auth-flow]',
            );


    if (!flow) {
        return;
    }


    const routes = [
        ...flow
            .querySelectorAll(
                '[data-auth-route]',
            ),
    ];


    const packets = [
        ...flow
            .querySelectorAll(
                '[data-auth-packet]',
            ),
    ];


    const cards = [
        ...flow
            .querySelectorAll(
                '[data-auth-flow-card]',
            ),
    ];


    const note =
        flow
            .querySelector(
                '[data-auth-note]',
            );


    /*
     * Dashed connection animation.
     */

    routes.forEach(
        (route) => {
            gsap.set(
                route,
                {
                    strokeDasharray:
                        '7 8',

                    strokeDashoffset:
                        28,
                },
            );


            gsap.to(
                route,
                {
                    strokeDashoffset:
                        0,

                    duration:
                        2.15,

                    repeat:
                        -1,

                    ease:
                        'none',
                },
            );
        },
    );


    /*
     * Packets moving source → note → output.
     */

    packets.forEach(
        (
            packet,
            index,
        ) => {
            const startX =
                Number(
                    packet
                        .dataset
                        .startX,
                );


            const startY =
                Number(
                    packet
                        .dataset
                        .startY,
                );


            const endX =
                Number(
                    packet
                        .dataset
                        .endX,
                );


            const endY =
                Number(
                    packet
                        .dataset
                        .endY,
                );


            gsap.set(
                packet,
                {
                    attr: {
                        cx:
                            startX,

                        cy:
                            startY,
                    },

                    autoAlpha:
                        0,
                },
            );


            gsap
                .timeline({
                    repeat:
                        -1,

                    repeatDelay:
                        .72,

                    delay:
                        index
                        * .38,
                })

                .to(
                    packet,
                    {
                        autoAlpha:
                            1,

                        scale:
                            1,

                        duration:
                            .12,
                    },
                )

                .to(
                    packet,
                    {
                        attr: {
                            cx:
                                endX,

                            cy:
                                endY,
                        },

                        duration:
                            1.18,

                        ease:
                            'power1.inOut',
                    },
                )

                .to(
                    packet,
                    {
                        autoAlpha:
                            0,

                        scale:
                            .55,

                        duration:
                            .18,
                    },
                )

                .set(
                    packet,
                    {
                        attr: {
                            cx:
                                startX,

                            cy:
                                startY,
                        },
                    },
                );
        },
    );


    /*
     * Cards subtle floating.
     */

    cards.forEach(
        (
            card,
            index,
        ) => {
            gsap.to(
                card,
                {
                    y:
                        index % 2 === 0
                            ? -5
                            : 5,

                    x:
                        index % 2 === 0
                            ? 2
                            : -2,

                    duration:
                        3
                        + (
                            index
                            * .28
                        ),

                    repeat:
                        -1,

                    yoyo:
                        true,

                    ease:
                        'sine.inOut',
                },
            );
        },
    );


    /*
     * Center research note.
     */

    if (note) {
        gsap.to(
            note,
            {
                y: -6,

                duration:
                    3.5,

                repeat:
                    -1,

                yoyo:
                    true,

                ease:
                    'sine.inOut',
            },
        );
    }
}