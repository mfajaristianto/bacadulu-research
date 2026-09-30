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
    initResearchOutputs();
    initSavedDocumentOutputs();
});

async function initResearchOutputs() {
    const actionContainers = [
        ...document.querySelectorAll(
            '.research-message__actions',
        ),
    ];

    if (!actionContainers.length) {
        return;
    }

    await Promise.all(
        actionContainers.map(
            async (container) => {
                const runInput =
                    container.querySelector(
                        'input[name="ai_run_id"]',
                    );

                const runId =
                    runInput?.value;

                if (!runId) {
                    return;
                }

                try {
                    const response =
                        await fetch(
                            `/research/runs/${encodeURIComponent(runId)}/presentation`,
                            {
                                headers: {
                                    Accept: 'application/json',
                                },
                                credentials:
                                    'same-origin',
                            },
                        );

                    if (!response.ok) {
                        return;
                    }

                    const payload =
                        await response.json();

                    renderResearchOutput(
                        container,
                        payload,
                    );
                } catch {
                    /*
                     * Presentation enhancement gagal
                     * tidak boleh mematikan workspace.
                     */
                }
            },
        ),
    );
}

function renderResearchOutput(
    actionContainer,
    payload,
) {
    const messageBody =
        actionContainer.closest(
            '.research-message__body',
        );

    if (!messageBody) {
        return;
    }

    const content =
        messageBody.querySelector(
            '.research-message__content',
        );

    if (
        payload.completed
        && content
        && !messageBody.querySelector(
            '.research-answer-label',
        )
    ) {
        const answerLabel =
            document.createElement('div');

        answerLabel.className =
            'research-answer-label';

        answerLabel.textContent =
            'ANSWER';

        content.before(
            answerLabel,
        );
    }

    const saveForm =
        actionContainer.querySelector(
            'form[action*="/saved"]',
        );

    if (!payload.can_save) {
        saveForm?.remove();
    } else if (
        payload.save?.saved
        && payload.save?.url
    ) {
        saveForm?.remove();

        const savedLink =
            createActionLink(
                payload.save.url,
                '✓ Tersimpan',
                'research-output-action is-saved',
            );

        actionContainer.prepend(
            savedLink,
        );
    }

    if (
        payload.can_generate
        && payload.generate_url
        && !actionContainer.querySelector(
            '[data-generate-document]',
        )
    ) {
        const generateLink =
            createActionLink(
                payload.generate_url,
                'Generate Document ↗',
                'research-output-action is-primary',
            );

        generateLink.dataset.generateDocument =
            'true';

        actionContainer.append(
            generateLink,
        );
    }

    if (
        payload.next_prompt
        && !actionContainer.querySelector(
            '[data-next-prompt]',
        )
    ) {
        const nextButton =
            document.createElement(
                'button',
            );

        nextButton.type =
            'button';

        nextButton.className =
            'research-output-action is-chain';

        nextButton.dataset.nextPrompt =
            'true';

        nextButton.innerHTML =
            `<span>Lanjut:</span> ${escapeHtml(payload.next_prompt.name)} →`;

        nextButton.addEventListener(
            'click',
            () => {
                applyNextPrompt(
                    payload.next_prompt,
                );
            },
        );

        actionContainer.append(
            nextButton,
        );
    }

    renderValidationPanel(
        messageBody,
        payload,
        actionContainer,
    );
}

function renderValidationPanel(
    messageBody,
    payload,
    actionContainer,
) {
    if (
        messageBody.querySelector(
            '.research-validation-panel',
        )
    ) {
        return;
    }

    const citations =
        Array.isArray(
            payload.citations,
        )
            ? payload.citations
            : [];

    const nextAction =
        payload.next_action;

    if (
        !citations.length
        && !nextAction
        && !payload.next_prompt
    ) {
        return;
    }

    const panel =
        document.createElement(
            'section',
        );

    panel.className =
        'research-validation-panel';

    if (citations.length) {
        const citationsSection =
            document.createElement(
                'div',
            );

        citationsSection.className =
            'research-validation-panel__citations';

        const label =
            document.createElement(
                'div',
            );

        label.className =
            'research-output-label';

        label.textContent =
            'VALIDATED CITATIONS';

        citationsSection.append(
            label,
        );

        const list =
            document.createElement(
                'div',
            );

        list.className =
            'research-citation-list';

        citations.forEach(
            (citation) => {
                const item =
                    document.createElement(
                        'article',
                    );

                item.className =
                    'research-citation-item';

                const meta = [
                    citation.source,
                    citation.year,
                ]
                    .filter(Boolean)
                    .join(' · ');

                const claim =
                    citation.claim
                    ? `<strong>${escapeHtml(citation.claim)}</strong>`
                    : '';

                const quote =
                    citation.quote
                    ? `<p>“${escapeHtml(citation.quote)}”</p>`
                    : '';

                const doi =
                    citation.doi
                    ? `<small>DOI: ${escapeHtml(citation.doi)}</small>`
                    : '';

                item.innerHTML = `
                    <span class="research-citation-item__key">
                        [${escapeHtml(citation.key || '')}]
                    </span>

                    <div>
                        ${claim}

                        <span class="research-citation-item__source">
                            ${escapeHtml(meta)}
                        </span>

                        ${quote}
                        ${doi}
                    </div>
                `;

                list.append(
                    item,
                );
            },
        );

        citationsSection.append(
            list,
        );

        panel.append(
            citationsSection,
        );
    }

    if (
        nextAction
        || payload.next_prompt
    ) {
        const next =
            document.createElement(
                'div',
            );

        next.className =
            'research-next-action';

        const label =
            document.createElement(
                'div',
            );

        label.className =
            'research-output-label';

        label.textContent =
            'NEXT ACTION';

        next.append(
            label,
        );

        if (nextAction) {
            const copy =
                document.createElement(
                    'p',
                );

            copy.textContent =
                nextAction;

            next.append(
                copy,
            );
        }

        if (payload.next_prompt) {
            const suggestion =
                document.createElement(
                    'button',
                );

            suggestion.type =
                'button';

            suggestion.className =
                'research-next-action__prompt';

            suggestion.innerHTML = `
                <small>
                    SUGGESTED RESEARCH TOOL
                </small>

                <strong>
                    ${escapeHtml(payload.next_prompt.name)}
                </strong>

                <span>
                    ${escapeHtml(payload.next_prompt.description || '')}
                </span>
            `;

            suggestion.addEventListener(
                'click',
                () => {
                    applyNextPrompt(
                        payload.next_prompt,
                    );
                },
            );

            next.append(
                suggestion,
            );
        }

        panel.append(
            next,
        );
    }

    actionContainer.before(
        panel,
    );
}

function applyNextPrompt(
    prompt,
) {
    const input =
        document.querySelector(
            '[data-prompt-input]',
        );

    const label =
        document.querySelector(
            '[data-prompt-label]',
        );

    const activePanel =
        document.querySelector(
            '[data-active-prompt]',
        );

    const activeName =
        activePanel?.querySelector(
            '[data-active-prompt-name]',
        );

    const activeDescription =
        activePanel?.querySelector(
            '[data-active-prompt-description]',
        );

    if (input) {
        input.value =
            prompt.id;
    }

    if (label) {
        label.textContent =
            prompt.name;
    }

    if (activePanel) {
        activePanel.hidden =
            false;

        activePanel.classList.add(
            'is-visible',
        );
    }

    if (activeName) {
        activeName.textContent =
            prompt.name;
    }

    if (activeDescription) {
        activeDescription.textContent =
            prompt.description
            || prompt.tool
            || '';
    }

    document
        .querySelectorAll(
            '[data-prompt-id]',
        )
        .forEach(
            (option) => {
                option.setAttribute(
                    'aria-selected',
                    String(
                        option.dataset.promptId
                            === String(
                                prompt.id,
                            ),
                    ),
                );
            },
        );

    const composer =
        document.querySelector(
            '.research-composer-wrap',
        );

    composer?.scrollIntoView({
        behavior:
            window.matchMedia(
                '(prefers-reduced-motion: reduce)',
            ).matches
                ? 'auto'
                : 'smooth',

        block:
            'end',
    });

    window.setTimeout(
        () => {
            document
                .querySelector(
                    '[data-chat-input]',
                )
                ?.focus();
        },
        180,
    );
}

async function initSavedDocumentOutputs() {
    const page =
        document.querySelector(
            '.saved-detail-page',
        );

    if (!page) {
        return;
    }

    const match =
        window.location.pathname.match(
            /\/saved\/(\d+)\/?$/,
        );

    const savedResultId =
        match?.[1];

    if (!savedResultId) {
        return;
    }

    try {
        const response =
            await fetch(
                `/saved/${encodeURIComponent(savedResultId)}/presentation`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                    credentials:
                        'same-origin',
                },
            );

        if (!response.ok) {
            return;
        }

        const payload =
            await response.json();

        renderSavedDocumentActions(
            page,
            payload,
        );
    } catch {
        /*
         * Enhancement gagal tidak boleh
         * merusak Saved Result.
         */
    }
}

function renderSavedDocumentActions(
    page,
    payload,
) {
    const head =
        page.querySelector(
            '.saved-detail-head',
        );

    if (
        head
        && payload.can_generate
        && payload.generate_url
        && !head.querySelector(
            '[data-saved-generate]',
        )
    ) {
        const actions =
            document.createElement(
                'div',
            );

        actions.className =
            'saved-document-head-actions';

        const generate =
            createActionLink(
                payload.generate_url,
                'Generate Document ↗',
                'btn btn-primary',
            );

        generate.dataset.savedGenerate =
            'true';

        actions.append(
            generate,
        );

        const favorite =
            head.querySelector(
                'form',
            );

        if (favorite) {
            favorite.before(
                actions,
            );
        } else {
            head.append(
                actions,
            );
        }
    }

    const documents =
        Array.isArray(
            payload.documents,
        )
            ? payload.documents
            : [];

    if (!documents.length) {
        return;
    }

    const side =
        page.querySelector(
            '.saved-detail-side',
        );

    if (
        !side
        || side.querySelector(
            '.saved-generated-documents',
        )
    ) {
        return;
    }

    const section =
        document.createElement(
            'section',
        );

    section.className =
        'saved-info-card saved-generated-documents';

    const label =
        document.createElement(
            'div',
        );

    label.className =
        'saved-section-label';

    label.textContent =
        'GENERATED DOCUMENTS';

    section.append(
        label,
    );

    const list =
        document.createElement(
            'div',
        );

    list.className =
        'saved-generated-documents__list';

    documents.forEach(
        (documentItem) => {
            const item =
                document.createElement(
                    'article',
                );

            item.className =
                'saved-generated-document';

            const status =
                escapeHtml(
                    String(
                        documentItem.status
                        || 'draft',
                    ).toUpperCase(),
                );

            const generated =
                documentItem.generated_at
                    ? escapeHtml(
                        documentItem.generated_at,
                    )
                    : '';

            item.innerHTML = `
                <div class="saved-generated-document__meta">
                    <span>${status}</span>
                    ${generated ? `<small>${generated}</small>` : ''}
                </div>

                <strong>
                    ${escapeHtml(documentItem.title || 'Research Document')}
                </strong>

                <p>
                    ${escapeHtml(documentItem.document_type || '')}
                    ${documentItem.citation_style
                        ? ` · ${escapeHtml(documentItem.citation_style)}`
                        : ''
                    }
                </p>

                <div class="saved-generated-document__actions">
                    <a href="${escapeAttribute(documentItem.show_url)}">
                        Preview / Edit
                    </a>

                    ${
                        documentItem.docx_url
                            ? `<a href="${escapeAttribute(documentItem.docx_url)}">DOCX ↓</a>`
                            : ''
                    }

                    ${
                        documentItem.pdf_url
                            ? `<a href="${escapeAttribute(documentItem.pdf_url)}">PDF ↓</a>`
                            : ''
                    }
                </div>
            `;

            list.append(
                item,
            );
        },
    );

    section.append(
        list,
    );

    side.prepend(
        section,
    );
}

function createActionLink(
    url,
    text,
    className,
) {
    const link =
        document.createElement(
            'a',
        );

    link.href =
        url;

    link.className =
        className;

    link.textContent =
        text;

    return link;
}

function escapeHtml(value) {
    return String(
        value ?? '',
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
}

function escapeAttribute(value) {
    return escapeHtml(
        value,
    );
}