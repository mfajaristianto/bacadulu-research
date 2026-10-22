(() => {
    const page =
        document.querySelector(
            '[data-profile-page]',
        );

    if (!page) {
        return;
    }

    const modal =
        page.querySelector(
            '[data-profile-modal]',
        );

    const sidebar =
        page.querySelector(
            '[data-profile-sidebar]',
        );

    const sidebarBackdrop =
        page.querySelector(
            '[data-profile-sidebar-backdrop]',
        );

    const avatarInput =
        page.querySelector(
            '[data-profile-avatar-input]',
        );

    const avatarPreview =
        page.querySelector(
            '[data-profile-avatar-preview]',
        );

    const organizationType =
        page.querySelector(
            '[data-organization-type]',
        );

    const divisionLabel =
        page.querySelector(
            '[data-division-label]',
        );

    const departmentLabel =
        page.querySelector(
            '[data-department-label]',
        );


    /* =========================================================
       MODAL
       ========================================================= */

    function openModal(
        target = 'personal',
    ) {
        if (!modal) {
            return;
        }

        modal.hidden =
            false;

        document.body.style.overflow =
            'hidden';

        window.setTimeout(
            () => {
                page
                    .querySelector(
                        `[data-profile-section="${target}"]`,
                    )
                    ?.scrollIntoView({
                        behavior:
                            'smooth',

                        block:
                            'start',
                    });
            },
            80,
        );
    }


    function closeModal() {
        if (!modal) {
            return;
        }

        modal.hidden =
            true;

        document.body.style.overflow =
            '';
    }


    page
        .querySelectorAll(
            '[data-profile-edit]',
        )
        .forEach(
            (button) => {
                button.addEventListener(
                    'click',
                    () => {
                        openModal(
                            button
                                .dataset
                                .profileEdit
                            || 'personal',
                        );
                    },
                );
            },
        );


    page
        .querySelectorAll(
            '[data-profile-modal-close]',
        )
        .forEach(
            (button) => {
                button.addEventListener(
                    'click',
                    closeModal,
                );
            },
        );


    document.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key
                === 'Escape'
            ) {
                closeModal();
                closeSidebar();
            }
        },
    );


    if (
        page.dataset.profileOpenEditor
        === '1'
    ) {
        openModal(
            'personal',
        );
    }


    /* =========================================================
       SIDEBAR MOBILE
       ========================================================= */

    function closeSidebar() {
        sidebar
            ?.classList
            .remove(
                'is-open',
            );

        sidebarBackdrop
            ?.classList
            .remove(
                'is-open',
            );
    }


    page
        .querySelector(
            '[data-profile-sidebar-open]',
        )
        ?.addEventListener(
            'click',
            () => {
                sidebar
                    ?.classList
                    .toggle(
                        'is-open',
                    );

                sidebarBackdrop
                    ?.classList
                    .toggle(
                        'is-open',
                    );
            },
        );


    sidebarBackdrop
        ?.addEventListener(
            'click',
            closeSidebar,
        );


    /* =========================================================
       AVATAR PREVIEW
       ========================================================= */

    avatarInput
        ?.addEventListener(
            'change',
            () => {
                const file =
                    avatarInput
                        .files?.[0];

                if (!file) {
                    return;
                }

                const validTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ];

                if (
                    !validTypes.includes(
                        file.type,
                    )
                ) {
                    window.alert(
                        'Gunakan JPG, PNG, atau WEBP.',
                    );

                    avatarInput.value =
                        '';

                    return;
                }

                if (
                    file.size
                    >
                    5 * 1024 * 1024
                ) {
                    window.alert(
                        'Ukuran foto maksimal 5 MB.',
                    );

                    avatarInput.value =
                        '';

                    return;
                }

                const url =
                    URL.createObjectURL(
                        file,
                    );

                if (avatarPreview) {
                    avatarPreview.innerHTML =
                        '';

                    const image =
                        document.createElement(
                            'img',
                        );

                    image.src =
                        url;

                    image.alt =
                        '';

                    avatarPreview
                        .appendChild(
                            image,
                        );
                }

                const main =
                    page.querySelector(
                        '.br-profile-avatar',
                    );

                if (main) {
                    main.innerHTML =
                        '';

                    const image =
                        document.createElement(
                            'img',
                        );

                    image.src =
                        url;

                    image.alt =
                        '';

                    image.setAttribute(
                        'data-profile-main-avatar',
                        '',
                    );

                    main.appendChild(
                        image,
                    );
                }
            },
        );


    /* =========================================================
       ORGANIZATION LABELS
       ========================================================= */

    const organizationLabels = {
        education: {
            division:
                'Fakultas / Unit',

            department:
                'Program Studi / Bidang',
        },

        company: {
            division:
                'Divisi / Unit',

            department:
                'Departemen / Bidang',
        },

        government: {
            division:
                'Unit / Direktorat',

            department:
                'Bagian / Bidang',
        },

        research: {
            division:
                'Unit / Pusat',

            department:
                'Bidang / Program',
        },

        nonprofit: {
            division:
                'Divisi / Unit',

            department:
                'Program / Bidang',
        },

        independent: {
            division:
                'Unit / Peran',

            department:
                'Bidang Keahlian',
        },

        other: {
            division:
                'Divisi / Unit',

            department:
                'Departemen / Bidang',
        },
    };


    function updateOrganizationLabels() {
        if (
            !organizationType
            || !divisionLabel
            || !departmentLabel
        ) {
            return;
        }

        const type =
            organizationType.value
            || 'other';

        const labels =
            organizationLabels[type]
            || organizationLabels.other;

        divisionLabel.textContent =
            labels.division;

        departmentLabel.textContent =
            labels.department;
    }


    organizationType
        ?.addEventListener(
            'change',
            updateOrganizationLabels,
        );


    updateOrganizationLabels();


    /* =========================================================
       HELP
       ========================================================= */

    page
        .querySelector(
            '[data-profile-help]',
        )
        ?.addEventListener(
            'click',
            () => {
                window.alert(
                    'Gunakan Edit Profil untuk melengkapi data pribadi, afiliasi profesional, dan fokus riset Anda.',
                );
            },
        );
})();