/*
|--------------------------------------------------------------------------
| Admin console behaviour
|--------------------------------------------------------------------------
| Loaded by layouts/admin.blade.php and layouts/admin-auth.blade.php.
| These used to be inline <script> blocks, which the Content Security
| Policy (script-src without 'unsafe-inline') blocked from running.
*/

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
};

ready(() => {
    initAdminSidebar();
    initFileInputLabels();
    initAdminPasswordToggles();
    initSourcePdfPreview();
});

/* Off-canvas sidebar below 900px (layouts/admin.blade.php). */
function initAdminSidebar() {
    const sidebar = document.querySelector('[data-admin-sidebar]');

    if (!sidebar) {
        return;
    }

    const overlay = document.querySelector('[data-admin-overlay]');
    const openButton = document.querySelector('[data-admin-sidebar-open]');
    const closeButton = document.querySelector('[data-admin-sidebar-close]');
    const mobileQuery = window.matchMedia('(max-width: 900px)');

    const setSidebar = (open) => {
        const nextOpen = Boolean(open && mobileQuery.matches);

        sidebar.classList.toggle('is-open', nextOpen);
        overlay?.classList.toggle('is-visible', nextOpen);
        document.body.classList.toggle('admin-menu-open', nextOpen);
        openButton?.setAttribute('aria-expanded', String(nextOpen));
    };

    openButton?.addEventListener('click', () => setSidebar(true));
    closeButton?.addEventListener('click', () => setSidebar(false));
    overlay?.addEventListener('click', () => setSidebar(false));

    document.querySelectorAll('.bd-admin__nav a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileQuery.matches) {
                setSidebar(false);
            }
        });
    });

    window.addEventListener('resize', () => {
        if (!mobileQuery.matches) {
            setSidebar(false);
        }
    }, { passive: true });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebar(false);
        }
    });

    setSidebar(false);
}

/* Shows the chosen file name next to [data-file-input] fields. */
function initFileInputLabels() {
    document.querySelectorAll('[data-file-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const target = document.querySelector(input.dataset.fileTarget);
            const file = input.files?.[0];

            if (!target) {
                return;
            }

            if (!file) {
                target.textContent = 'Belum ada PDF dipilih';

                return;
            }

            const sizeMb = (file.size / 1024 / 1024).toFixed(1);

            target.textContent = `${file.name} · ${sizeMb} MB`;
        });
    });
}

/* "Lihat / Sembunyikan" on the admin login (layouts/admin-auth.blade.php). */
function initAdminPasswordToggles() {
    document.querySelectorAll('[data-admin-password-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const input = document.getElementById(button.getAttribute('aria-controls'));

            if (!input) {
                return;
            }

            const show = input.type === 'password';

            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Sembunyikan' : 'Lihat';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });
}

/* PDF dropzone + live paper preview (admin/library/form.blade.php). */
function initSourcePdfPreview() {
    const wrapper = document.querySelector('[data-source-upload]');
    const input = document.querySelector('[data-source-pdf-input]');
    const titleInput = document.querySelector('[data-source-title-input]');
    const dropzone = document.querySelector('[data-source-dropzone]');
    const previewTitle = document.querySelector('[data-pdf-preview-title]');
    const previewFile = document.querySelector('[data-pdf-preview-file]');
    const previewMeta = document.querySelector('[data-pdf-preview-meta]');
    const fileText = document.querySelector('#source-file-name');

    if (!wrapper || !input || !titleInput || !previewTitle) {
        return;
    }

    let currentFileName = previewFile?.textContent?.trim() || '';

    const isPdf = (file) => file.type === 'application/pdf' || /\.pdf$/i.test(file.name);

    const cleanFileName = (name) => String(name || '')
        .replace(/\.pdf$/i, '')
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const titleForPreview = () => {
        const typedTitle = titleInput.value.trim();

        if (typedTitle) {
            return typedTitle;
        }

        const fromFile = cleanFileName(currentFileName);

        if (fromFile && fromFile !== 'Belum ada PDF') {
            return fromFile;
        }

        return 'Judul Source';
    };

    const syncTitle = () => {
        previewTitle.textContent = titleForPreview();
    };

    const animateReceived = () => {
        wrapper.classList.remove('is-receiving');
        void wrapper.offsetWidth;
        wrapper.classList.add('is-receiving');

        window.setTimeout(() => wrapper.classList.remove('is-receiving'), 1000);
    };

    const applyFile = (file, updateInput = false) => {
        if (!file) {
            return;
        }

        if (!isPdf(file)) {
            window.alert('File harus berupa PDF.');

            return;
        }

        if (updateInput) {
            try {
                const transfer = new DataTransfer();

                transfer.items.add(file);
                input.files = transfer.files;
            } catch (error) {
                /* Some browsers restrict assigning a FileList. */
            }
        }

        currentFileName = file.name;

        const cleanTitle = cleanFileName(file.name);

        if (!titleInput.value.trim() && cleanTitle) {
            titleInput.value = cleanTitle;
        }

        const sizeMb = (file.size / 1024 / 1024).toFixed(2);

        if (previewFile) {
            previewFile.textContent = file.name;
        }

        if (previewMeta) {
            previewMeta.textContent = `${sizeMb} MB · PDF dipilih`;
        }

        if (fileText) {
            fileText.textContent = `${file.name} · ${sizeMb} MB`;
        }

        wrapper.classList.add('has-file');

        syncTitle();
        animateReceived();
    };

    titleInput.addEventListener('input', syncTitle);

    input.addEventListener('change', () => {
        const file = input.files?.[0];

        if (!file) {
            syncTitle();

            return;
        }

        applyFile(file);
    });

    if (dropzone) {
        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('is-dragging'));
        });

        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();

            const pdf = Array.from(event.dataTransfer?.files || []).find(isPdf);

            if (!pdf) {
                window.alert('Drag file PDF ke area upload.');

                return;
            }

            applyFile(pdf, true);
        });
    }

    syncTitle();
}
