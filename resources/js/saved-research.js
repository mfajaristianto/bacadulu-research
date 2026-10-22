(() => {
    const page =
        document.querySelector(
            '[data-saved-library]',
        );

    if (!page) {
        return;
    }


    const userId =
        page.dataset.savedUser
        || 'guest';

    let items = [
        ...page.querySelectorAll(
            '[data-saved-item]',
        ),
    ];

    let activeFilter =
        'all';

    let activeTopic =
        '';

    let activeItem =
        null;


    const csrf =
        document.querySelector(
            'meta[name="csrf-token"]',
        )?.content
        || '';


    const searchInput =
        page.querySelector(
            '[data-saved-search]',
        );

    const emptyState =
        page.querySelector(
            '[data-saved-empty]',
        );

    const renameModal =
        page.querySelector(
            '[data-saved-rename-modal]',
        );

    const renameInput =
        page.querySelector(
            '[data-saved-rename-input]',
        );

    const appearanceModal =
        page.querySelector(
            '[data-saved-appearance-modal]',
        );

    const deleteModal =
        page.querySelector(
            '[data-saved-delete-modal]',
        );


    initTopbar();
    initMobileSidebar();
    initFilters();
    initMenus();
    initModals();
    initRename();
    initDelete();
    initAppearance();
    initTopicExpansion();

    restoreAppearances();
    updateCounts();


    /* =========================================================
       TOPBAR
       ========================================================= */

    function initTopbar() {
        const workspaceTrigger =
            page.querySelector(
                '[data-saved-workspace-trigger]',
            );

        const workspaceMenu =
            page.querySelector(
                '[data-saved-workspace-menu]',
            );

        const helpTrigger =
            page.querySelector(
                '[data-saved-help-trigger]',
            );

        const helpMenu =
            page.querySelector(
                '[data-saved-help-menu]',
            );

        const helpClose =
            page.querySelector(
                '[data-saved-help-close]',
            );


        const closeWorkspace =
            () => {
                if (!workspaceMenu) {
                    return;
                }

                workspaceMenu.hidden =
                    true;

                workspaceTrigger
                    ?.classList
                    .remove(
                        'is-open',
                    );

                workspaceTrigger
                    ?.setAttribute(
                        'aria-expanded',
                        'false',
                    );
            };


        const closeHelp =
            () => {
                if (!helpMenu) {
                    return;
                }

                helpMenu.hidden =
                    true;
            };


        workspaceTrigger
            ?.addEventListener(
                'click',
                (event) => {
                    event.stopPropagation();

                    const shouldOpen =
                        workspaceMenu
                        ?.hidden;

                    closeHelp();

                    if (!workspaceMenu) {
                        return;
                    }

                    workspaceMenu.hidden =
                        !shouldOpen;

                    workspaceTrigger
                        .classList
                        .toggle(
                            'is-open',
                            Boolean(
                                shouldOpen,
                            ),
                        );

                    workspaceTrigger
                        .setAttribute(
                            'aria-expanded',
                            String(
                                Boolean(
                                    shouldOpen,
                                ),
                            ),
                        );
                },
            );


        helpTrigger
            ?.addEventListener(
                'click',
                (event) => {
                    event.stopPropagation();

                    const shouldOpen =
                        helpMenu
                        ?.hidden;

                    closeWorkspace();

                    if (!helpMenu) {
                        return;
                    }

                    helpMenu.hidden =
                        !shouldOpen;
                },
            );


        workspaceMenu
            ?.addEventListener(
                'click',
                (event) =>
                    event.stopPropagation(),
            );


        helpMenu
            ?.addEventListener(
                'click',
                (event) =>
                    event.stopPropagation(),
            );


        helpClose
            ?.addEventListener(
                'click',
                closeHelp,
            );


        document
            .addEventListener(
                'click',
                () => {
                    closeWorkspace();
                    closeHelp();
                },
            );
    }


    /* =========================================================
       MOBILE SIDEBAR
       ========================================================= */

    function initMobileSidebar() {
        const sidebar =
            page.querySelector(
                '[data-saved-sidebar]',
            );

        const open =
            page.querySelector(
                '[data-saved-sidebar-open]',
            );

        const backdrop =
            page.querySelector(
                '[data-saved-sidebar-backdrop]',
            );


        const closeSidebar =
            () => {
                sidebar
                    ?.classList
                    .remove(
                        'is-open',
                    );

                backdrop
                    ?.classList
                    .remove(
                        'is-open',
                    );
            };


        open?.addEventListener(
            'click',
            () => {
                sidebar
                    ?.classList
                    .toggle(
                        'is-open',
                    );

                backdrop
                    ?.classList
                    .toggle(
                        'is-open',
                    );
            },
        );


        backdrop
            ?.addEventListener(
                'click',
                closeSidebar,
            );
    }


    /* =========================================================
       FILTER
       ========================================================= */

    function initFilters() {
        page
            .querySelectorAll(
                '[data-saved-filter]',
            )
            .forEach(
                (button) => {
                    button.addEventListener(
                        'click',
                        () => {
                            page
                                .querySelectorAll(
                                    '[data-saved-filter]',
                                )
                                .forEach(
                                    (other) =>
                                        other
                                            .classList
                                            .remove(
                                                'is-active',
                                            ),
                                );

                            button
                                .classList
                                .add(
                                    'is-active',
                                );

                            activeFilter =
                                button
                                    .dataset
                                    .savedFilter
                                || 'all';

                            applyFilters();
                        },
                    );
                },
            );


        searchInput
            ?.addEventListener(
                'input',
                applyFilters,
            );


        page
            .querySelectorAll(
                '[data-topic-filter]',
            )
            .forEach(
                (button) => {
                    button.addEventListener(
                        'click',
                        () => {
                            const topic =
                                button
                                    .dataset
                                    .topicFilter
                                || '';

                            activeTopic =
                                activeTopic
                                === topic
                                    ? ''
                                    : topic;

                            page
                                .querySelectorAll(
                                    '[data-topic-filter]',
                                )
                                .forEach(
                                    (other) => {
                                        other
                                            .classList
                                            .toggle(
                                                'is-active',
                                                other
                                                    .dataset
                                                    .topicFilter
                                                === activeTopic,
                                            );
                                    },
                                );

                            applyFilters();
                        },
                    );
                },
            );
    }


    function applyFilters() {
        const query =
            (
                searchInput
                    ?.value
                || ''
            )
                .trim()
                .toLowerCase();


        let visible =
            0;


        items.forEach(
            (item) => {
                const itemType =
                    item.dataset.savedType
                    || 'answer';

                const searchText =
                    item.dataset.savedSearch
                    || '';

                const topicText =
                    item.dataset.savedTopics
                    || '';


                const typeMatches =
                    activeFilter === 'all'
                    || itemType
                        === activeFilter;

                const queryMatches =
                    !query
                    || searchText
                        .includes(
                            query,
                        );

                const topicMatches =
                    !activeTopic
                    || topicText
                        .includes(
                            activeTopic,
                        );


                const show =
                    typeMatches
                    && queryMatches
                    && topicMatches;


                item.hidden =
                    !show;


                if (show) {
                    visible += 1;
                }
            },
        );


        page
            .querySelectorAll(
                '[data-saved-group]',
            )
            .forEach(
                (group) => {
                    const hasVisible =
                        [
                            ...group
                                .querySelectorAll(
                                    '[data-saved-item]',
                                ),
                        ]
                            .some(
                                (item) =>
                                    !item.hidden,
                            );

                    group.hidden =
                        !hasVisible;
                },
            );


        if (emptyState) {
            emptyState.hidden =
                visible > 0;
        }
    }


    /* =========================================================
       ITEM MENU
       ========================================================= */

    function initMenus() {
        items.forEach(
            (item) => {
                const trigger =
                    item.querySelector(
                        '[data-saved-menu-trigger]',
                    );

                const menu =
                    item.querySelector(
                        '[data-saved-menu]',
                    );


                trigger
                    ?.addEventListener(
                        'click',
                        (event) => {
                            event.stopPropagation();

                            const shouldOpen =
                                menu?.hidden;

                            closeMenus();

                            if (!menu) {
                                return;
                            }

                            menu.hidden =
                                !shouldOpen;
                        },
                    );


                menu
                    ?.addEventListener(
                        'click',
                        (event) =>
                            event.stopPropagation(),
                    );


                item
                    .querySelector(
                        '[data-saved-rename]',
                    )
                    ?.addEventListener(
                        'click',
                        () => {
                            activeItem =
                                item;

                            closeMenus();

                            openRenameModal();
                        },
                    );


                item
                    .querySelector(
                        '[data-saved-appearance]',
                    )
                    ?.addEventListener(
                        'click',
                        () => {
                            activeItem =
                                item;

                            closeMenus();

                            openAppearanceModal();
                        },
                    );


                item
                    .querySelector(
                        '[data-saved-delete]',
                    )
                    ?.addEventListener(
                        'click',
                        () => {
                            activeItem =
                                item;

                            closeMenus();

                            openModal(
                                deleteModal,
                            );
                        },
                    );
            },
        );


        document
            .addEventListener(
                'click',
                closeMenus,
            );


        document
            .addEventListener(
                'keydown',
                (event) => {
                    if (
                        event.key
                        !== 'Escape'
                    ) {
                        return;
                    }

                    closeMenus();
                    closeAllModals();
                },
            );
    }


    function closeMenus() {
        page
            .querySelectorAll(
                '[data-saved-menu]',
            )
            .forEach(
                (menu) => {
                    menu.hidden =
                        true;
                },
            );
    }


    /* =========================================================
       MODALS
       ========================================================= */

    function initModals() {
        page
            .querySelectorAll(
                '[data-saved-modal-close]',
            )
            .forEach(
                (button) => {
                    button.addEventListener(
                        'click',
                        closeAllModals,
                    );
                },
            );
    }


    function openModal(
        modal,
    ) {
        if (!modal) {
            return;
        }

        closeAllModals();

        modal.hidden =
            false;

        document.body.style.overflow =
            'hidden';
    }


    function closeAllModals() {
        page
            .querySelectorAll(
                '.saved-modal',
            )
            .forEach(
                (modal) => {
                    modal.hidden =
                        true;
                },
            );

        document.body.style.overflow =
            '';
    }


    /* =========================================================
       RENAME
       ========================================================= */

    function openRenameModal() {
        if (
            !activeItem
            || !renameInput
        ) {
            return;
        }

        renameInput.value =
            activeItem
                .querySelector(
                    '[data-saved-title]',
                )
                ?.textContent
                ?.trim()
            || '';

        openModal(
            renameModal,
        );

        window.setTimeout(
            () => {
                renameInput.focus();
                renameInput.select();
            },
            70,
        );
    }


    function initRename() {
        page
            .querySelector(
                '[data-saved-rename-save]',
            )
            ?.addEventListener(
                'click',
                async () => {
                    if (
                        !activeItem
                        || !renameInput
                    ) {
                        return;
                    }

                    const id =
                        activeItem
                            .dataset
                            .savedId;

                    const title =
                        renameInput
                            .value
                            .trim();


                    if (
                        title.length
                        < 2
                    ) {
                        renameInput.focus();
                        return;
                    }


                    try {
                        const response =
                            await fetch(
                                `/saved/${encodeURIComponent(id)}`,
                                {
                                    method:
                                        'PATCH',

                                    credentials:
                                        'same-origin',

                                    headers: {
                                        Accept:
                                            'application/json',

                                        'Content-Type':
                                            'application/json',

                                        'X-CSRF-TOKEN':
                                            csrf,
                                    },

                                    body:
                                        JSON.stringify({
                                            title,
                                        }),
                                },
                            );


                        if (
                            !response.ok
                        ) {
                            throw new Error(
                                'rename_failed',
                            );
                        }


                        const titleElement =
                            activeItem
                                .querySelector(
                                    '[data-saved-title]',
                                );


                        if (
                            titleElement
                        ) {
                            titleElement.textContent =
                                title;
                        }


                        activeItem
                            .dataset
                            .savedSearch =
                            `${title} ${
                                activeItem
                                    .dataset
                                    .savedSearch
                                || ''
                            }`
                                .toLowerCase();


                        closeAllModals();
                    } catch {
                        window.alert(
                            'Nama Saved belum berhasil diubah.',
                        );
                    }
                },
            );


        renameInput
            ?.addEventListener(
                'keydown',
                (event) => {
                    if (
                        event.key
                        !== 'Enter'
                    ) {
                        return;
                    }

                    event.preventDefault();

                    page
                        .querySelector(
                            '[data-saved-rename-save]',
                        )
                        ?.click();
                },
            );
    }


    /* =========================================================
       DELETE
       ========================================================= */

    function initDelete() {
        page
            .querySelector(
                '[data-saved-delete-confirm]',
            )
            ?.addEventListener(
                'click',
                async () => {
                    if (!activeItem) {
                        return;
                    }


                    const id =
                        activeItem
                            .dataset
                            .savedId;


                    try {
                        const response =
                            await fetch(
                                `/saved/${encodeURIComponent(id)}`,
                                {
                                    method:
                                        'DELETE',

                                    credentials:
                                        'same-origin',

                                    headers: {
                                        Accept:
                                            'application/json',

                                        'X-CSRF-TOKEN':
                                            csrf,
                                    },
                                },
                            );


                        if (
                            !response.ok
                        ) {
                            throw new Error(
                                'delete_failed',
                            );
                        }


                        await removeCustomImage(
                            imageKey(
                                id,
                            ),
                        );


                        localStorage
                            .removeItem(
                                appearanceKey(
                                    id,
                                ),
                            );


                        activeItem.remove();

                        items =
                            items.filter(
                                (item) =>
                                    item
                                    !== activeItem,
                            );


                        closeAllModals();

                        updateCounts();
                        applyFilters();
                    } catch {
                        window.alert(
                            'Item belum berhasil dihapus.',
                        );
                    }
                },
            );
    }


    /* =========================================================
       APPEARANCE
       ========================================================= */

    function initAppearance() {
        page
            .querySelectorAll(
                '[data-appearance-icon]',
            )
            .forEach(
                (button) => {
                    button.addEventListener(
                        'click',
                        async () => {
                            if (!activeItem) {
                                return;
                            }

                            const settings =
                                getSettings(
                                    activeItem
                                        .dataset
                                        .savedId,
                                );


                            settings.icon =
                                button
                                    .dataset
                                    .appearanceIcon;

                            settings.useImage =
                                false;


                            saveSettings(
                                activeItem
                                    .dataset
                                    .savedId,

                                settings,
                            );


                            await applyAppearance(
                                activeItem,
                                settings,
                            );


                            syncAppearanceModal(
                                settings,
                            );
                        },
                    );
                },
            );


        page
            .querySelectorAll(
                '[data-appearance-style]',
            )
            .forEach(
                (button) => {
                    button.addEventListener(
                        'click',
                        async () => {
                            if (!activeItem) {
                                return;
                            }


                            const settings =
                                getSettings(
                                    activeItem
                                        .dataset
                                        .savedId,
                                );


                            settings.style =
                                button
                                    .dataset
                                    .appearanceStyle;


                            saveSettings(
                                activeItem
                                    .dataset
                                    .savedId,

                                settings,
                            );


                            await applyAppearance(
                                activeItem,
                                settings,
                            );


                            syncAppearanceModal(
                                settings,
                            );
                        },
                    );
                },
            );


        page
            .querySelectorAll(
                '[data-appearance-accent]',
            )
            .forEach(
                (button) => {
                    button.addEventListener(
                        'click',
                        async () => {
                            if (!activeItem) {
                                return;
                            }


                            const settings =
                                getSettings(
                                    activeItem
                                        .dataset
                                        .savedId,
                                );


                            settings.accent =
                                button
                                    .dataset
                                    .appearanceAccent;


                            saveSettings(
                                activeItem
                                    .dataset
                                    .savedId,

                                settings,
                            );


                            await applyAppearance(
                                activeItem,
                                settings,
                            );


                            syncAppearanceModal(
                                settings,
                            );
                        },
                    );
                },
            );


        page
            .querySelector(
                '[data-appearance-upload]',
            )
            ?.addEventListener(
                'change',
                async (event) => {
                    if (!activeItem) {
                        return;
                    }


                    const file =
                        event
                            .target
                            .files?.[0];


                    if (!file) {
                        return;
                    }


                    const types = [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ];


                    if (
                        !types.includes(
                            file.type,
                        )
                    ) {
                        window.alert(
                            'Gunakan JPG, PNG, atau WEBP.',
                        );

                        event.target.value =
                            '';

                        return;
                    }


                    if (
                        file.size
                        >
                        2 * 1024 * 1024
                    ) {
                        window.alert(
                            'Ukuran gambar maksimum 2 MB.',
                        );

                        event.target.value =
                            '';

                        return;
                    }


                    const id =
                        activeItem
                            .dataset
                            .savedId;


                    await saveCustomImage(
                        imageKey(
                            id,
                        ),

                        file,
                    );


                    const settings =
                        getSettings(
                            id,
                        );


                    settings.useImage =
                        true;


                    saveSettings(
                        id,
                        settings,
                    );


                    await applyAppearance(
                        activeItem,
                        settings,
                    );


                    event.target.value =
                        '';
                },
            );


        page
            .querySelector(
                '[data-appearance-reset]',
            )
            ?.addEventListener(
                'click',
                async () => {
                    if (!activeItem) {
                        return;
                    }


                    const id =
                        activeItem
                            .dataset
                            .savedId;


                    localStorage
                        .removeItem(
                            appearanceKey(
                                id,
                            ),
                        );


                    await removeCustomImage(
                        imageKey(
                            id,
                        ),
                    );


                    const defaults =
                        defaultSettings();


                    await applyAppearance(
                        activeItem,
                        defaults,
                    );


                    syncAppearanceModal(
                        defaults,
                    );
                },
            );
    }


    function openAppearanceModal() {
        if (!activeItem) {
            return;
        }


        const settings =
            getSettings(
                activeItem
                    .dataset
                    .savedId,
            );


        syncAppearanceModal(
            settings,
        );


        openModal(
            appearanceModal,
        );
    }


    function syncAppearanceModal(
        settings,
    ) {
        page
            .querySelectorAll(
                '[data-appearance-icon]',
            )
            .forEach(
                (button) => {
                    button
                        .classList
                        .toggle(
                            'is-selected',

                            button
                                .dataset
                                .appearanceIcon
                            === settings.icon,
                        );
                },
            );


        page
            .querySelectorAll(
                '[data-appearance-style]',
            )
            .forEach(
                (button) => {
                    button
                        .classList
                        .toggle(
                            'is-selected',

                            button
                                .dataset
                                .appearanceStyle
                            === settings.style,
                        );
                },
            );


        page
            .querySelectorAll(
                '[data-appearance-accent]',
            )
            .forEach(
                (button) => {
                    button
                        .classList
                        .toggle(
                            'is-selected',

                            button
                                .dataset
                                .appearanceAccent
                            === settings.accent,
                        );
                },
            );
    }


    async function restoreAppearances() {
        for (
            const item
            of items
        ) {
            const icon =
                item.querySelector(
                    '[data-saved-default-icon]',
                );

            if (icon) {
                item.__savedDefaultIcon =
                    icon.innerHTML;
            }


            await applyAppearance(
                item,

                getSettings(
                    item
                        .dataset
                        .savedId,
                ),
            );
        }
    }


    async function applyAppearance(
        item,
        settings,
    ) {
        item.dataset.style =
            settings.style;

        item.dataset.accent =
            settings.accent;


        const icon =
            item.querySelector(
                '[data-saved-default-icon]',
            );

        const image =
            item.querySelector(
                '[data-saved-custom-image]',
            );


        if (
            settings.useImage
        ) {
            const blob =
                await loadCustomImage(
                    imageKey(
                        item
                            .dataset
                            .savedId,
                    ),
                );


            if (
                blob
                && image
            ) {
                image.src =
                    URL.createObjectURL(
                        blob,
                    );

                image.hidden =
                    false;


                if (icon) {
                    icon.hidden =
                        true;
                }


                return;
            }
        }


        if (image) {
            image.hidden =
                true;

            image.removeAttribute(
                'src',
            );
        }


        if (!icon) {
            return;
        }


        icon.hidden =
            false;


        if (
            settings.icon
            === 'auto'
        ) {
            icon.innerHTML =
                item.__savedDefaultIcon
                || icon.innerHTML;

            return;
        }


        icon.innerHTML =
            iconSvg(
                settings.icon,
            );
    }


    function defaultSettings() {
        return {
            icon:
                'auto',

            style:
                'academic',

            accent:
                'coral',

            useImage:
                false,
        };
    }


    function getSettings(
        id,
    ) {
        const defaults =
            defaultSettings();


        try {
            const saved =
                JSON.parse(
                    localStorage
                        .getItem(
                            appearanceKey(
                                id,
                            ),
                        )
                    || '{}',
                );


            return {
                ...defaults,
                ...saved,
            };
        } catch {
            return defaults;
        }
    }


    function saveSettings(
        id,
        settings,
    ) {
        localStorage
            .setItem(
                appearanceKey(
                    id,
                ),

                JSON.stringify(
                    settings,
                ),
            );
    }


    function appearanceKey(
        id,
    ) {
        return (
            `bacadulu:saved-appearance:`
            + `${userId}:${id}`
        );
    }


    function imageKey(
        id,
    ) {
        return (
            `bacadulu:saved-image:`
            + `${userId}:${id}`
        );
    }


    function iconSvg(
        icon,
    ) {
        const icons = {
            search: `
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="10" cy="10" r="6"/>
                    <path d="m15 15 5 5"/>
                </svg>
            `,

            theory: `
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="6" cy="12" r="2"/>
                    <circle cx="18" cy="6" r="2"/>
                    <circle cx="18" cy="18" r="2"/>
                    <path d="m8 11 8-4"/>
                    <path d="m8 13 8 4"/>
                </svg>
            `,

            chart: `
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M5 20V12"/>
                    <path d="M12 20V5"/>
                    <path d="M19 20V9"/>
                </svg>
            `,

            link: `
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1 1"/>
                    <path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1-1"/>
                </svg>
            `,
        };


        return (
            icons[icon]
            || icons.search
        );
    }


    /* =========================================================
       TOPIC EXPAND
       ========================================================= */

    function initTopicExpansion() {
        page
            .querySelectorAll(
                '[data-show-all-topics]',
            )
            .forEach(
                (button) => {
                    button.addEventListener(
                        'click',
                        () => {
                            const extras =
                                [
                                    ...page
                                        .querySelectorAll(
                                            '[data-extra-topic]',
                                        ),
                                ];


                            if (
                                !extras.length
                            ) {
                                return;
                            }


                            const shouldShow =
                                extras
                                    .some(
                                        (item) =>
                                            item.hidden,
                                    );


                            extras
                                .forEach(
                                    (item) => {
                                        item.hidden =
                                            !shouldShow;
                                    },
                                );


                            const label =
                                button
                                    .querySelector(
                                        'span',
                                    );


                            if (label) {
                                label.textContent =
                                    shouldShow
                                        ? 'Tampilkan lebih sedikit'
                                        : 'Lihat semua topik';
                            }
                        },
                    );
                },
            );
    }


    /* =========================================================
       COUNTS
       ========================================================= */

    function updateCounts() {
        const current =
            [
                ...page
                    .querySelectorAll(
                        '[data-saved-item]',
                    ),
            ];


        const documents =
            current
                .filter(
                    (item) =>
                        item
                            .dataset
                            .savedType
                        === 'document',
                )
                .length;


        const answers =
            current
                .filter(
                    (item) =>
                        item
                            .dataset
                            .savedType
                        === 'answer',
                )
                .length;


        setCount(
            '[data-count-all]',
            current.length,
        );

        setCount(
            '[data-count-document]',
            documents,
        );

        setCount(
            '[data-count-answer]',
            answers,
        );
    }


    function setCount(
        selector,
        value,
    ) {
        const target =
            page.querySelector(
                selector,
            );


        if (target) {
            target.textContent =
                String(
                    value,
                );
        }
    }


    /* =========================================================
       INDEXED DB
       ========================================================= */

    function openDatabase() {
        return new Promise(
            (
                resolve,
                reject,
            ) => {
                const request =
                    indexedDB.open(
                        'BacaDuluSavedAppearance',
                        1,
                    );


                request.onupgradeneeded =
                    () => {
                        const db =
                            request.result;


                        if (
                            !db
                                .objectStoreNames
                                .contains(
                                    'images',
                                )
                        ) {
                            db.createObjectStore(
                                'images',
                            );
                        }
                    };


                request.onsuccess =
                    () =>
                        resolve(
                            request.result,
                        );


                request.onerror =
                    () =>
                        reject(
                            request.error,
                        );
            },
        );
    }


    async function saveCustomImage(
        key,
        blob,
    ) {
        const db =
            await openDatabase();


        return new Promise(
            (
                resolve,
                reject,
            ) => {
                const tx =
                    db.transaction(
                        'images',
                        'readwrite',
                    );


                tx
                    .objectStore(
                        'images',
                    )
                    .put(
                        blob,
                        key,
                    );


                tx.oncomplete =
                    () =>
                        resolve();


                tx.onerror =
                    () =>
                        reject(
                            tx.error,
                        );
            },
        );
    }


    async function loadCustomImage(
        key,
    ) {
        const db =
            await openDatabase();


        return new Promise(
            (
                resolve,
                reject,
            ) => {
                const tx =
                    db.transaction(
                        'images',
                        'readonly',
                    );


                const request =
                    tx
                        .objectStore(
                            'images',
                        )
                        .get(
                            key,
                        );


                request.onsuccess =
                    () =>
                        resolve(
                            request.result
                            || null,
                        );


                request.onerror =
                    () =>
                        reject(
                            request.error,
                        );
            },
        );
    }


    async function removeCustomImage(
        key,
    ) {
        const db =
            await openDatabase();


        return new Promise(
            (
                resolve,
                reject,
            ) => {
                const tx =
                    db.transaction(
                        'images',
                        'readwrite',
                    );


                tx
                    .objectStore(
                        'images',
                    )
                    .delete(
                        key,
                    );


                tx.oncomplete =
                    () =>
                        resolve();


                tx.onerror =
                    () =>
                        reject(
                            tx.error,
                        );
            },
        );
    }
})();