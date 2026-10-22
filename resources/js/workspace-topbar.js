(() => {
    const initWorkspaceTopbar = () => {
        const topbar =
            document.querySelector(
                '[data-brx-topbar]',
            );

        if (!topbar) {
            return;
        }

        const workspaceTrigger =
            topbar.querySelector(
                '[data-brx-workspace-trigger]',
            );

        const workspaceMenu =
            topbar.querySelector(
                '[data-brx-workspace-menu]',
            );

        const helpTrigger =
            topbar.querySelector(
                '[data-brx-help-trigger]',
            );

        const helpMenu =
            topbar.querySelector(
                '[data-brx-help-menu]',
            );

        const helpClose =
            topbar.querySelector(
                '[data-brx-help-close]',
            );


        const closeWorkspace = () => {
            if (!workspaceMenu) {
                return;
            }

            workspaceMenu.hidden = true;

            workspaceTrigger?.classList.remove(
                'is-open',
            );

            workspaceTrigger?.setAttribute(
                'aria-expanded',
                'false',
            );
        };


        const openWorkspace = () => {
            if (!workspaceMenu) {
                return;
            }

            closeHelp();

            workspaceMenu.hidden = false;

            workspaceTrigger?.classList.add(
                'is-open',
            );

            workspaceTrigger?.setAttribute(
                'aria-expanded',
                'true',
            );
        };


        const closeHelp = () => {
            if (!helpMenu) {
                return;
            }

            helpMenu.hidden = true;

            helpTrigger?.classList.remove(
                'is-open',
            );

            helpTrigger?.setAttribute(
                'aria-expanded',
                'false',
            );
        };


        const openHelp = () => {
            if (!helpMenu) {
                return;
            }

            closeWorkspace();

            helpMenu.hidden = false;

            helpTrigger?.classList.add(
                'is-open',
            );

            helpTrigger?.setAttribute(
                'aria-expanded',
                'true',
            );
        };


        workspaceTrigger?.addEventListener(
            'click',
            (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (workspaceMenu?.hidden) {
                    openWorkspace();

                    return;
                }

                closeWorkspace();
            },
        );


        helpTrigger?.addEventListener(
            'click',
            (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (helpMenu?.hidden) {
                    openHelp();

                    return;
                }

                closeHelp();
            },
        );


        helpClose?.addEventListener(
            'click',
            (event) => {
                event.preventDefault();

                closeHelp();

                helpTrigger?.focus({
                    preventScroll: true,
                });
            },
        );


        workspaceMenu?.addEventListener(
            'click',
            (event) => {
                event.stopPropagation();
            },
        );


        helpMenu?.addEventListener(
            'click',
            (event) => {
                event.stopPropagation();
            },
        );


        document.addEventListener(
            'click',
            () => {
                closeWorkspace();
                closeHelp();
            },
        );


        document.addEventListener(
            'keydown',
            (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                const workspaceWasOpen =
                    workspaceMenu
                    && !workspaceMenu.hidden;

                const helpWasOpen =
                    helpMenu
                    && !helpMenu.hidden;

                closeWorkspace();
                closeHelp();

                if (workspaceWasOpen) {
                    workspaceTrigger?.focus({
                        preventScroll: true,
                    });

                    return;
                }

                if (helpWasOpen) {
                    helpTrigger?.focus({
                        preventScroll: true,
                    });
                }
            },
        );


        window.addEventListener(
            'resize',
            () => {
                closeWorkspace();
                closeHelp();
            },
            {
                passive: true,
            },
        );
    };


    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            initWorkspaceTopbar,
            {
                once: true,
            },
        );
    } else {
        initWorkspaceTopbar();
    }
})();