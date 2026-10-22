(() => {
    'use strict';

    const sidebar = document.querySelector('[data-br-sidebar]');
    if (!sidebar) return;

    const collapseButton = sidebar.querySelector('[data-br-sidebar-collapse]');
    const showMoreButton = sidebar.querySelector('[data-br-sidebar-show-more]');
    const showMoreLabel = sidebar.querySelector('[data-br-sidebar-show-more-label]');
    const extraList = sidebar.querySelector('[data-br-sidebar-more-list]');
    const openButtons = [...document.querySelectorAll('[data-chat-sidebar-open]')];
    const backdrop = document.querySelector('[data-chat-sidebar-backdrop]');

    const STORAGE_KEY = 'bacadulu.research.sidebar.collapsed.v4';
    const desktopMedia = window.matchMedia('(min-width: 981px)');
    const isDesktop = () => desktopMedia.matches;

    const readCollapsedPreference = () => {
        try {
            return localStorage.getItem(STORAGE_KEY) === 'true';
        } catch {
            return false;
        }
    };

    const writeCollapsedPreference = (collapsed) => {
        try {
            localStorage.setItem(STORAGE_KEY, String(collapsed));
        } catch {
            //
        }
    };

    const syncCollapseA11y = () => {
        if (!collapseButton) return;

        const collapsed = sidebar.classList.contains('is-collapsed');

        collapseButton.setAttribute('aria-pressed', String(!collapsed));
        collapseButton.setAttribute(
            'aria-label',
            collapsed ? 'Buka sidebar' : 'Kecilkan sidebar',
        );
        collapseButton.title = collapsed ? 'Buka sidebar' : 'Kecilkan sidebar';
    };

    const setCollapsed = (collapsed, { persist = true } = {}) => {
        if (!isDesktop()) {
            sidebar.classList.remove('is-collapsed');
            syncCollapseA11y();
            return;
        }

        sidebar.classList.toggle('is-collapsed', collapsed);

        if (persist) {
            writeCollapsedPreference(collapsed);
        }

        syncCollapseA11y();
    };

    const setExpandedList = (expanded) => {
        if (!showMoreButton || !extraList) return;

        extraList.hidden = !expanded;
        showMoreButton.classList.toggle('is-expanded', expanded);
        showMoreButton.setAttribute('aria-expanded', String(expanded));

        if (showMoreLabel) {
            showMoreLabel.textContent = expanded ? 'Show less' : 'Show more';
        }
    };

    const closeAllConversationMenus = (except = null) => {
        sidebar.querySelectorAll('[data-conversation-menu]').forEach((menu) => {
            if (menu !== except) {
                menu.open = false;
            }
        });
    };

    const placeMenu = (menu) => {
        const summary = menu.querySelector('summary');
        const panel = menu.querySelector('[data-conversation-menu-panel]');

        if (!summary || !panel || !menu.open) return;

        panel.style.visibility = 'hidden';
        panel.style.display = 'grid';

        const summaryRect = summary.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const gap = 10;
        const viewportPadding = 12;

        let left = summaryRect.right + gap;

        if (left + panelRect.width > window.innerWidth - viewportPadding) {
            left = summaryRect.left - panelRect.width - gap;
        }

        let top = summaryRect.top - 8;
        top = Math.max(
            viewportPadding,
            Math.min(top, window.innerHeight - panelRect.height - viewportPadding),
        );

        panel.style.setProperty('--conversation-menu-left', `${Math.round(left)}px`);
        panel.style.setProperty('--conversation-menu-top', `${Math.round(top)}px`);
        panel.style.visibility = '';
    };

    const setDrawerOpen = (open) => {
        if (isDesktop()) {
            open = false;
        }

        sidebar.classList.toggle('is-open', open);
        backdrop?.classList.toggle('is-open', open);
        document.body.classList.toggle('has-research-sidebar-open', open);

        openButtons.forEach((button) => {
            button.setAttribute('aria-expanded', String(open));
        });
    };

    const handleBreakpointChange = () => {
        setDrawerOpen(false);
        closeAllConversationMenus();

        if (isDesktop()) {
            setCollapsed(readCollapsedPreference(), { persist: false });
            return;
        }

        sidebar.classList.remove('is-collapsed');
        syncCollapseA11y();
    };

    if (isDesktop()) {
        setCollapsed(readCollapsedPreference(), { persist: false });
    } else {
        syncCollapseA11y();
    }

    collapseButton?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (!isDesktop()) return;

        const collapsed = sidebar.classList.contains('is-collapsed');
        setCollapsed(!collapsed);
    });

    showMoreButton?.addEventListener('click', () => {
        setExpandedList(showMoreButton.getAttribute('aria-expanded') !== 'true');
    });

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setDrawerOpen(!sidebar.classList.contains('is-open'));
        });
    });

    backdrop?.addEventListener('click', () => {
        setDrawerOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        setDrawerOpen(false);
        closeAllConversationMenus();
    });

    sidebar.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (!isDesktop()) {
                setDrawerOpen(false);
            }
        });
    });

    sidebar.querySelectorAll('[data-conversation-menu]').forEach((menu) => {
        menu.addEventListener('toggle', () => {
            if (!menu.open) return;

            closeAllConversationMenus(menu);
            window.requestAnimationFrame(() => placeMenu(menu));
        });
    });

    window.addEventListener(
        'resize',
        () => {
            sidebar.querySelectorAll('[data-conversation-menu]').forEach((menu) => {
                if (menu.open) {
                    placeMenu(menu);
                }
            });
        },
        { passive: true },
    );

    document.addEventListener('pointerdown', (event) => {
        if (event.target.closest('[data-conversation-menu]')) return;
        closeAllConversationMenus();
    });

    sidebar.querySelectorAll('[data-sidebar-conversation]').forEach((row) => {
        const link = row.querySelector('[data-conversation-link]');
        const menu = row.querySelector('[data-conversation-menu]');
        const renameButton = row.querySelector('[data-conversation-rename]');
        const renameForm = row.querySelector('[data-conversation-rename-form]');
        const renameInput = row.querySelector('[data-conversation-rename-input]');

        if (!renameButton || !renameForm || !renameInput || !link) {
            return;
        }

        const cancelRename = () => {
            renameForm.hidden = true;
            row.classList.remove('is-renaming');
            link.hidden = false;
        };

        renameButton.addEventListener('click', () => {
            if (menu) {
                menu.open = false;
            }

            closeAllConversationMenus();

            link.hidden = true;
            renameForm.hidden = false;
            row.classList.add('is-renaming');

            window.requestAnimationFrame(() => {
                renameInput.focus();
                renameInput.select();
            });
        });

        renameInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                cancelRename();
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                const value = renameInput.value.trim();

                if (!value) return;

                renameInput.value = value;
                renameForm.requestSubmit();
            }
        });

        renameInput.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (!renameForm.matches(':focus-within')) {
                    cancelRename();
                }
            }, 60);
        });
    });

    if (typeof desktopMedia.addEventListener === 'function') {
        desktopMedia.addEventListener('change', handleBreakpointChange);
    } else {
        desktopMedia.addListener?.(handleBreakpointChange);
    }
})();