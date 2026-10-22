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
    document.addEventListener('bacaresearch:result-updated', () => {
        initResearchOutputs();
    });
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
    return escapeHtml(value);
}
/* ==========================================================================
   BACADULU RESEARCH — ANSWER EXPERIENCE V3
   ========================================================================== */
(() => {
    const rawAnswerStore =
        new WeakMap();
    const onReady =
        (callback) => {
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
    onReady(
        () => {
            enhanceAllAnswers();
            /*
             * Kalau answer muncul setelah request/fetch,
             * tetap otomatis dirender.
             */
            if (
                'MutationObserver'
                in window
            ) {
                const observer =
                    new MutationObserver(
                        () => {
                            enhanceAllAnswers();
                        },
                    );
                observer.observe(
                    document.body,
                    {
                        childList: true,
                        subtree: true,
                    },
                );
            }
        },
    );
    /* ============================================================
       FIND ALL ANSWERS
       ============================================================ */
    function enhanceAllAnswers(
        root = document,
    ) {
        root
            .querySelectorAll(
                '.research-answer-card .research-answer-text',
            )
            .forEach(
                (answer) => {
                    enhanceAnswer(
                        answer,
                    );
                },
            );
    }
    /* ============================================================
       ENHANCE ONE ANSWER
       ============================================================ */
    function enhanceAnswer(
        answer,
    ) {
        if (
            !answer
            ||
            answer.dataset
                .brAnswerReady
            === 'true'
        ) {
            return;
        }
        const rawText =
            normalizeText(
                answer.innerText
                ||
                answer.textContent
                ||
                '',
            );
        if (
            !rawText
            ||
            rawText
                .toLowerCase()
                .includes(
                    'research sedang diproses',
                )
        ) {
            return;
        }
        answer.dataset
            .brAnswerReady =
            'true';
        answer.classList.add(
            'br-answer-text--enhanced',
        );
        rawAnswerStore.set(
            answer,
            rawText,
        );
        const parsed =
            parseAnswer(
                rawText,
            );
        renderAnswer(
            answer,
            parsed,
        );
        /*
         * Cari wrapper jawaban.
         */
        const scope =
            answer.closest(
                '.research-answer-card',
            )
            ||
            answer.closest(
                '.research-message__body',
            );
        scope?.classList.add(
            'br-answer-card--enhanced',
        );
        /*
         * Action lama:
         *
         * Simpan
         * Generate
         * Next Prompt
         *
         * disembunyikan.
         *
         * Toolbar user sekarang hanya:
         * Copy / Share / Reload.
         */
        const legacyActions =
            scope?.querySelector(
                '.research-message__actions',
            );
        if (
            legacyActions
        ) {
            legacyActions.hidden =
                true;
        }
        /*
         * Hindari toolbar dobel.
         */
        if (
            !scope?.querySelector(
                '[data-br-answer-actions]',
            )
        ) {
            answer
                .insertAdjacentElement(
                    'afterend',
                    createActionBar(
                        answer,
                    ),
                );
        }
    }
    /* ============================================================
       NORMALIZE RAW TEXT
       ============================================================ */
    function normalizeText(
        value,
    ) {
        return String(
            value
            ||
            '',
        )
            .replace(
                /\r\n?/g,
                '\n',
            )
            .replace(
                /[ \t]+\n/g,
                '\n',
            )
            .replace(
                /\n{3,}/g,
                '\n\n',
            )
            .trim();
    }
    /* ============================================================
       PARSE ANSWER
       ============================================================ */
    function parseAnswer(
        rawText,
    ) {
        const lines =
            normalizeText(
                rawText,
            )
                .split(
                    '\n',
                );
        const parsed = {
            intro: [],
            sections: [],
        };
        let currentSection =
            null;
        lines.forEach(
            (sourceLine) => {
                const line =
                    sourceLine.trim();
                /*
                 * Blank line.
                 */
                if (!line) {
                    (
                        currentSection?.lines
                        ??
                        parsed.intro
                    )
                        .push(
                            '',
                        );
                    return;
                }
                /*
                 * Markdown headings:
                 *
                 * # Heading
                 * ## Heading
                 * ### Heading
                 *
                 * termasuk:
                 *
                 * ##Heading
                 */
                const markdownHeading =
                    line.match(
                        /^#{1,6}\s*(.+)$/,
                    );
                /*
                 * **Heading**
                 */
                const boldHeading =
                    line.match(
                        /^\*\*([^*]{2,120})\*\*$/,
                    );
                /*
                 * __Heading__
                 */
                const underscoreHeading =
                    line.match(
                        /^__([^_]{2,120})__$/,
                    );
                let heading =
                    markdownHeading?.[1]
                    ||
                    boldHeading?.[1]
                    ||
                    underscoreHeading?.[1]
                    ||
                    null;
                /*
                 * Model kadang ngasih heading tanpa Markdown:
                 *
                 * Temuan yang Sudah Tersedia
                 * Keterbatasan atau Inkonsistensi
                 * Peluang Research Gap
                 * Kesimpulan
                 */
                if (
                    !heading
                    &&
                    looksLikeStandaloneSectionTitle(
                        line,
                    )
                ) {
                    heading =
                        line;
                }
                if (heading) {
                    currentSection = {
                        title:
                            cleanInlineMarkdown(
                                heading,
                            ),
                        lines: [],
                    };
                    parsed.sections.push(
                        currentSection,
                    );
                    return;
                }
                if (
                    currentSection
                ) {
                    currentSection
                        .lines
                        .push(
                            line,
                        );
                } else {
                    parsed
                        .intro
                        .push(
                            line,
                        );
                }
            },
        );
        return parsed;
    }
    /* ============================================================
       FALLBACK SECTION TITLE DETECTION
       ============================================================ */
    function looksLikeStandaloneSectionTitle(
        line,
    ) {
        if (
            line.length
            >
            110
        ) {
            return false;
        }
        const clean =
            cleanInlineMarkdown(
                line,
            );
        return /^(temuan|temuan utama|temuan yang sudah tersedia|keterbatasan|keterbatasan atau inkonsistensi|inkonsistensi|peluang research gap|research gap|peluang penelitian|kesimpulan|conclusion|limitations?|findings?|opportunities?)\b/i
            .test(
                clean,
            );
    }
    /* ============================================================
       RENDER ANSWER
       ============================================================ */
    function renderAnswer(
        answer,
        parsed,
    ) {
        const wrapper =
            document.createElement(
                'div',
            );
        wrapper.className =
            'br-answer';
        /*
         * Intro sebelum section.
         */
        const introLines =
            trimEdgeBlankLines(
                parsed.intro,
            );
        if (
            introLines.length
        ) {
            const intro =
                document.createElement(
                    'div',
                );
            intro.className =
                'br-answer__lead';
            renderLines(
                intro,
                introLines,
            );
            wrapper.append(
                intro,
            );
        }
        /*
         * Kalau AI tidak memberi section,
         * tetap render sebagai body bersih.
         */
        if (
            !parsed.sections.length
        ) {
            const body =
                document.createElement(
                    'div',
                );
            body.className =
                'br-answer__plain';
            renderLines(
                body,
                normalizeText(
                    rawAnswerStore
                        .get(
                            answer,
                        )
                    ||
                    '',
                )
                    .split(
                        '\n',
                    ),
            );
            wrapper.append(
                body,
            );
            answer.replaceChildren(
                wrapper,
            );
            return;
        }
        /*
         * Render section satu-satu.
         */
        parsed.sections.forEach(
            (section) => {
                const type =
                    detectSectionType(
                        section.title,
                    );
                if (
                    type
                    === 'conclusion'
                ) {
                    wrapper.append(
                        createConclusion(
                            section,
                        ),
                    );
                    return;
                }
                wrapper.append(
                    createSection(
                        section,
                        type,
                    ),
                );
            },
        );
        answer.replaceChildren(
            wrapper,
        );
    }
    /* ============================================================
       NORMAL SECTION
       ============================================================ */
    function createSection(
        section,
        type,
    ) {
        const element =
            document.createElement(
                'section',
            );
        element.className =
            `br-answer-section is-${type}`;
        /*
         * BUKAN ANGKA.
         *
         * Satu icon sesuai makna section.
         */
        const icon =
            document.createElement(
                'span',
            );
        icon.className =
            'br-answer-section__icon';
        icon.setAttribute(
            'aria-hidden',
            'true',
        );
        icon.innerHTML =
            getSectionIcon(
                type,
            );
        const content =
            document.createElement(
                'div',
            );
        content.className =
            'br-answer-section__content';
        const title =
            document.createElement(
                'h3',
            );
        title.textContent =
            section.title;
        content.append(
            title,
        );
        renderLines(
            content,
            trimEdgeBlankLines(
                section.lines,
            ),
        );
        element.append(
            icon,
            content,
        );
        return element;
    }
    /* ============================================================
       CONCLUSION
       ============================================================ */
    function createConclusion(
        section,
    ) {
        const element =
            document.createElement(
                'section',
            );
        element.className =
            'br-answer-conclusion';
        const head =
            document.createElement(
                'div',
            );
        head.className =
            'br-answer-conclusion__head';
        const icon =
            document.createElement(
                'span',
            );
        icon.className =
            'br-answer-conclusion__icon';
        icon.setAttribute(
            'aria-hidden',
            'true',
        );
        icon.innerHTML =
            getSectionIcon(
                'conclusion',
            );
        const title =
            document.createElement(
                'h3',
            );
        title.textContent =
            section.title
            ||
            'Kesimpulan';
        head.append(
            icon,
            title,
        );
        const body =
            document.createElement(
                'div',
            );
        body.className =
            'br-answer-conclusion__body';
        renderLines(
            body,
            trimEdgeBlankLines(
                section.lines,
            ),
        );
        element.append(
            head,
            body,
        );
        return element;
    }
    /* ============================================================
       BODY / PARAGRAPH / LIST
       ============================================================ */
    function renderLines(
        target,
        lines,
    ) {
        let paragraph =
            [];
        let list =
            null;
        const flushParagraph =
            () => {
                if (
                    !paragraph.length
                ) {
                    return;
                }
                const p =
                    document.createElement(
                        'p',
                    );
                appendInlineMarkup(
                    p,
                    paragraph.join(
                        ' ',
                    ),
                );
                target.append(
                    p,
                );
                paragraph =
                    [];
            };
        const closeList =
            () => {
                if (!list) {
                    return;
                }
                target.append(
                    list,
                );
                list =
                    null;
            };
        lines.forEach(
            (sourceLine) => {
                const line =
                    String(
                        sourceLine
                        ||
                        '',
                    )
                        .trim();
                if (!line) {
                    flushParagraph();
                    closeList();
                    return;
                }
                /*
                 * - item
                 */
                const unordered =
                    line.match(
                        /^[-*+]\s+(.+)$/,
                    );
                /*
                 * 1. item
                 */
                const ordered =
                    line.match(
                        /^\d+[.)]\s+(.+)$/,
                    );
                if (
                    unordered
                    ||
                    ordered
                ) {
                    flushParagraph();
                    const listTag =
                        ordered
                            ? 'OL'
                            : 'UL';
                    if (
                        !list
                        ||
                        list.tagName
                        !== listTag
                    ) {
                        closeList();
                        list =
                            document.createElement(
                                ordered
                                    ? 'ol'
                                    : 'ul',
                            );
                    }
                    const item =
                        document.createElement(
                            'li',
                        );
                    appendInlineMarkup(
                        item,
                        ordered?.[1]
                        ||
                        unordered?.[1]
                        ||
                        '',
                    );
                    list.append(
                        item,
                    );
                    return;
                }
                closeList();
                paragraph.push(
                    line,
                );
            },
        );
        flushParagraph();
        closeList();
    }
    /* ============================================================
       INLINE MARKDOWN
       ============================================================ */
    function appendInlineMarkup(
        target,
        value,
    ) {
        const source =
            String(
                value
                ||
                '',
            );
        /*
         * Support:
         *
         * **bold**
         * __bold__
         * `code`
         * [E01]
         * [link](url)
         */
        const tokenPattern =
            /(\*\*[^*]+\*\*|__[^_]+__|`[^`]+`|\[E\d{1,3}\]|\[[^\]]+\]\([^)]+\))/gi;
        let cursor =
            0;
        let match;
        while (
            (
                match =
                    tokenPattern.exec(
                        source,
                    )
            )
            !== null
        ) {
            if (
                match.index
                >
                cursor
            ) {
                target.append(
                    document.createTextNode(
                        cleanInlineMarkdown(
                            source.slice(
                                cursor,
                                match.index,
                            ),
                        ),
                    ),
                );
            }
            const token =
                match[0];
            /*
             * Citation.
             */
            if (
                /^\[E\d{1,3}\]$/i
                    .test(
                        token,
                    )
            ) {
                const citation =
                    document.createElement(
                        'span',
                    );
                citation.className =
                    'br-answer-citation';
                citation.textContent =
                    token.toUpperCase();
                target.append(
                    citation,
                );
            }
            /*
             * Markdown link.
             */
            else if (
                /^\[[^\]]+\]\([^)]+\)$/
                    .test(
                        token,
                    )
            ) {
                const markdownLink =
                    token.match(
                        /^\[([^\]]+)\]\(([^)]+)\)$/,
                    );
                const link =
                    document.createElement(
                        'a',
                    );
                link.href =
                    markdownLink?.[2]
                    ||
                    '#';
                link.textContent =
                    markdownLink?.[1]
                    ||
                    token;
                link.target =
                    '_blank';
                link.rel =
                    'noopener noreferrer';
                target.append(
                    link,
                );
            }
            /*
             * Bold.
             */
            else if (
                token.startsWith(
                    '**',
                )
                ||
                token.startsWith(
                    '__',
                )
            ) {
                const strong =
                    document.createElement(
                        'strong',
                    );
                strong.textContent =
                    token.slice(
                        2,
                        -2,
                    );
                target.append(
                    strong,
                );
            }
            /*
             * Code.
             */
            else if (
                token.startsWith(
                    '`',
                )
            ) {
                const code =
                    document.createElement(
                        'code',
                    );
                code.textContent =
                    token.slice(
                        1,
                        -1,
                    );
                target.append(
                    code,
                );
            }
            cursor =
                tokenPattern.lastIndex;
        }
        if (
            cursor
            <
            source.length
        ) {
            target.append(
                document.createTextNode(
                    cleanInlineMarkdown(
                        source.slice(
                            cursor,
                        ),
                    ),
                ),
            );
        }
    }
    /* ============================================================
       SECTION TYPE
       ============================================================ */
    function detectSectionType(
        title,
    ) {
        const value =
            cleanInlineMarkdown(
                title,
            )
                .toLowerCase();
        /*
         * Kesimpulan.
         */
        if (
            value.includes(
                'kesimpulan',
            )
            ||
            value.includes(
                'conclusion',
            )
        ) {
            return 'conclusion';
        }
        /*
         * Keterbatasan / inkonsistensi.
         */
        if (
            value.includes(
                'keterbatas',
            )
            ||
            value.includes(
                'inkonsisten',
            )
            ||
            value.includes(
                'limitation',
            )
            ||
            value.includes(
                'inconsisten',
            )
        ) {
            return 'limitation';
        }
        /*
         * Research gap / peluang.
         */
        if (
            value.includes(
                'research gap',
            )
            ||
            value.includes(
                'peluang',
            )
            ||
            value.includes(
                'opportunity',
            )
            ||
            value.includes(
                'rekomendasi',
            )
        ) {
            return 'opportunity';
        }
        /*
         * Default = finding/evidence.
         */
        return 'finding';
    }
    /* ============================================================
       SVG ICONS
       ============================================================ */
    function getSectionIcon(
        type,
    ) {
        /*
         * KETERBATASAN / INKONSISTENSI
         *
         * Warning triangle.
         */
        if (
            type
            === 'limitation'
        ) {
            return `
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <path
                        d="M12 3 2.9 19h18.2L12 3Z"
                    ></path>
                    <path
                        d="M12 8v5"
                    ></path>
                    <path
                        d="M12 16.5h.01"
                    ></path>
                </svg>
            `;
        }
        /*
         * PELUANG RESEARCH GAP
         *
         * Lightbulb.
         */
        if (
            type
            === 'opportunity'
        ) {
            return `
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <path
                        d="M9 18h6"
                    ></path>
                    <path
                        d="M10 22h4"
                    ></path>
                    <path
                        d="
                            M8.2 14.5
                            C6.8 13.4 6 11.8 6 10
                            a6 6 0 1 1 12 0
                            c0 1.8-.8 3.4-2.2 4.5
                            -.8.7-1.3 1.5-1.5 2.5
                            H9.7
                            c-.2-1-.7-1.8-1.5-2.5Z
                        "
                    ></path>
                </svg>
            `;
        }
        /*
         * KESIMPULAN
         *
         * Document + check.
         */
        if (
            type
            === 'conclusion'
        ) {
            return `
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <path
                        d="M5 3h10l4 4v14H5z"
                    ></path>
                    <path
                        d="M15 3v5h5"
                    ></path>
                    <path
                        d="m8.5 14 2 2 4.5-5"
                    ></path>
                </svg>
            `;
        }
        /*
         * TEMUAN / EVIDENCE
         *
         * Research document + check.
         */
        return `
            <svg
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M6 3h9l4 4v14H6z"
                ></path>
                <path
                    d="M15 3v5h5"
                ></path>
                <path
                    d="m9 13 1.7 1.7L15 10.5"
                ></path>
                <path
                    d="M9 18h6"
                ></path>
            </svg>
        `;
    }
    /* ============================================================
       BOTTOM ACTION BAR
       ============================================================ */
    function createActionBar(
        answer,
    ) {
        const bar =
            document.createElement(
                'div',
            );
        bar.className =
            'br-answer-actions';
        bar.dataset
            .brAnswerActions =
            'true';
        /*
         * COPY
         */
        bar.append(
            createIconButton({
                label:
                    'Salin',
                tooltip:
                    'Salin jawaban',
                icon:
                    `
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <rect
                            x="8"
                            y="8"
                            width="11"
                            height="11"
                            rx="2"
                        ></rect>
                        <path
                            d="
                                M16 8V6
                                a2 2 0 0 0-2-2
                                H6
                                a2 2 0 0 0-2 2
                                v8
                                a2 2 0 0 0 2 2
                                h2
                            "
                        ></path>
                    </svg>
                    `,
                onClick:
                    async (
                        button,
                    ) => {
                        const success =
                            await copyAnswer(
                                answer,
                            );
                        showActionFeedback(
                            bar,
                            success
                                ? 'Tersalin'
                                : 'Gagal menyalin',
                        );
                        button.classList.toggle(
                            'is-success',
                            success,
                        );
                        window.setTimeout(
                            () => {
                                button.classList.remove(
                                    'is-success',
                                );
                            },
                            1200,
                        );
                    },
            }),
        );
        /*
         * SHARE
         */
        bar.append(
            createIconButton({
                label:
                    'Bagikan',
                tooltip:
                    'Bagikan jawaban',
                icon:
                    `
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <circle
                            cx="18"
                            cy="5"
                            r="2.5"
                        ></circle>
                        <circle
                            cx="6"
                            cy="12"
                            r="2.5"
                        ></circle>
                        <circle
                            cx="18"
                            cy="19"
                            r="2.5"
                        ></circle>
                        <path
                            d="m8.2 10.8 7.5-4.4"
                        ></path>
                        <path
                            d="m8.2 13.2 7.5 4.4"
                        ></path>
                    </svg>
                    `,
                onClick:
                    async () => {
                        await shareAnswer(
                            answer,
                            bar,
                        );
                    },
            }),
        );
        /*
         * RELOAD
         */
        bar.append(
            createIconButton({
                label:
                    'Muat ulang',
                tooltip:
                    'Muat ulang jawaban',
                icon:
                    `
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <path
                            d="M20 6v5h-5"
                        ></path>
                        <path
                            d="
                                M19 11
                                a7.5 7.5 0 1 0 .4 5
                            "
                        ></path>
                    </svg>
                    `,
                onClick:
                    (
                        button,
                    ) => {
                        button.classList.add(
                            'is-loading',
                        );
                        button.disabled =
                            true;
                        window.location.reload();
                    },
            }),
        );
        /*
         * Tiny feedback text.
         */
        const feedback =
            document.createElement(
                'span',
            );
        feedback.className =
            'br-answer-actions__feedback';
        feedback.dataset
            .brAnswerFeedback =
            'true';
        feedback.setAttribute(
            'aria-live',
            'polite',
        );
        bar.append(
            feedback,
        );
        return bar;
    }
    /* ============================================================
       TOOLBAR BUTTON
       ============================================================ */
    function createIconButton({
        label,
        tooltip,
        icon,
        onClick,
    }) {
        const button =
            document.createElement(
                'button',
            );
        button.type =
            'button';
        button.className =
            'br-answer-action';
        button.setAttribute(
            'aria-label',
            label,
        );
        button.setAttribute(
            'title',
            tooltip,
        );
        button.dataset.tooltip =
            tooltip;
        button.innerHTML =
            icon;
        button.addEventListener(
            'click',
            async () => {
                try {
                    await onClick(
                        button,
                    );
                } catch {
                    button.classList.remove(
                        'is-loading',
                    );
                }
            },
        );
        return button;
    }
    /* ============================================================
       COPY ANSWER
       ============================================================ */
    async function copyAnswer(
        answer,
    ) {
        const rawText =
            rawAnswerStore
                .get(
                    answer,
                )
            ||
            '';
        const plainText =
            cleanForClipboard(
                rawText,
            );
        const rendered =
            answer.querySelector(
                '.br-answer',
            );
        /*
         * Rich clipboard.
         *
         * Word / Google Docs:
         * heading masih bold,
         * tapi ## / ** hilang.
         */
        if (
            rendered
            &&
            navigator.clipboard
                ?.write
            &&
            typeof ClipboardItem
            !== 'undefined'
        ) {
            try {
                const clone =
                    rendered.cloneNode(
                        true,
                    );
                /*
                 * Icon jangan ikut ke clipboard.
                 */
                clone
                    .querySelectorAll(
                        '.br-answer-section__icon, .br-answer-conclusion__icon',
                    )
                    .forEach(
                        (node) => {
                            node.remove();
                        },
                    );
                const html =
                    `<div>${clone.innerHTML}</div>`;
                await navigator
                    .clipboard
                    .write([
                        new ClipboardItem({
                            'text/plain':
                                new Blob(
                                    [
                                        plainText,
                                    ],
                                    {
                                        type:
                                            'text/plain',
                                    },
                                ),
                            'text/html':
                                new Blob(
                                    [
                                        html,
                                    ],
                                    {
                                        type:
                                            'text/html',
                                    },
                                ),
                        }),
                    ]);
                return true;
            } catch {
                /*
                 * Fallback plain text.
                 */
            }
        }
        return copyPlainText(
            plainText,
        );
    }
    /* ============================================================
       SHARE
       ============================================================ */
    async function shareAnswer(
        answer,
        bar,
    ) {
        const rawText =
            rawAnswerStore
                .get(
                    answer,
                )
            ||
            '';
        const plainText =
            cleanForClipboard(
                rawText,
            );
        /*
         * Mobile native share.
         */
        if (
            navigator.share
        ) {
            try {
                await navigator.share({
                    title:
                        document.title,
                    text:
                        plainText,
                });
                return;
            } catch (
                error
            ) {
                if (
                    error?.name
                    === 'AbortError'
                ) {
                    return;
                }
            }
        }
        /*
         * Browser desktop fallback:
         * copy.
         */
        const copied =
            await copyPlainText(
                plainText,
            );
        showActionFeedback(
            bar,
            copied
                ? 'Share tidak tersedia — jawaban disalin'
                : 'Gagal menyiapkan jawaban',
        );
    }
    /* ============================================================
       PLAIN COPY FALLBACK
       ============================================================ */
    async function copyPlainText(
        text,
    ) {
        if (
            navigator.clipboard
                ?.writeText
        ) {
            try {
                await navigator
                    .clipboard
                    .writeText(
                        text,
                    );
                return true;
            } catch {
                /*
                 * Fallback bawah.
                 */
            }
        }
        const textarea =
            document.createElement(
                'textarea',
            );
        textarea.value =
            text;
        textarea.setAttribute(
            'readonly',
            '',
        );
        textarea.style.position =
            'fixed';
        textarea.style.left =
            '-9999px';
        textarea.style.top =
            '0';
        textarea.style.opacity =
            '0';
        document.body.append(
            textarea,
        );
        textarea.select();
        let copied =
            false;
        try {
            copied =
                document.execCommand(
                    'copy',
                );
        } catch {
            copied =
                false;
        }
        textarea.remove();
        return copied;
    }
    /* ============================================================
       CLEAN MARKDOWN FOR COPY
       ============================================================ */
    function cleanForClipboard(
        value,
    ) {
        return normalizeText(
            value,
        )
            /*
             * ```
             */
            .replace(
                /^```[a-z0-9_-]*\s*$/gim,
                '',
            )
            /*
             * # / ## / ###
             */
            .replace(
                /^\s{0,3}#{1,6}\s*/gm,
                '',
            )
            /*
             * >
             */
            .replace(
                /^\s{0,3}>\s?/gm,
                '',
            )
            /*
             * **bold**
             */
            .replace(
                /\*\*([^*]+)\*\*/g,
                '$1',
            )
            /*
             * __bold__
             */
            .replace(
                /__([^_]+)__/g,
                '$1',
            )
            /*
             * `code`
             */
            .replace(
                /`([^`]+)`/g,
                '$1',
            )
            /*
             * [Judul](url)
             */
            .replace(
                /\[([^\]]+)\]\(([^)]+)\)/g,
                '$1',
            )
            /*
             * Markdown bullet → normal bullet.
             */
            .replace(
                /^\s*[-*+]\s+/gm,
                '• ',
            )
            .replace(
                /\n{3,}/g,
                '\n\n',
            )
            .trim();
    }
    /* ============================================================
       CLEAN INLINE
       ============================================================ */
    function cleanInlineMarkdown(
        value,
    ) {
        return String(
            value
            ||
            '',
        )
            .replace(
                /^#{1,6}\s*/,
                '',
            )
            .replace(
                /\*\*([^*]+)\*\*/g,
                '$1',
            )
            .replace(
                /__([^_]+)__/g,
                '$1',
            )
            .replace(
                /`([^`]+)`/g,
                '$1',
            )
            .replace(
                /^>\s?/,
                '',
            )
            .trim();
    }
    /* ============================================================
       FEEDBACK
       ============================================================ */
    function showActionFeedback(
        bar,
        message,
    ) {
        const feedback =
            bar.querySelector(
                '[data-br-answer-feedback]',
            );
        if (!feedback) {
            return;
        }
        feedback.textContent =
            message;
        feedback.classList.add(
            'is-visible',
        );
        window.clearTimeout(
            Number(
                feedback.dataset
                    .timer
                ||
                0,
            ),
        );
        const timer =
            window.setTimeout(
                () => {
                    feedback.textContent =
                        '';
                    feedback.classList.remove(
                        'is-visible',
                    );
                },
                1800,
            );
        feedback.dataset.timer =
            String(
                timer,
            );
    }
    /* ============================================================
       ARRAY HELPERS
       ============================================================ */
    function trimEdgeBlankLines(
        value,
    ) {
        const lines =
            Array.isArray(
                value,
            )
                ? [
                    ...value,
                ]
                : [];
        while (
            lines.length
            &&
            !String(
                lines[0],
            )
                .trim()
        ) {
            lines.shift();
        }
        while (
            lines.length
            &&
            !String(
                lines[
                    lines.length - 1
                ],
            )
                .trim()
        ) {
            lines.pop();
        }
        return lines;
    }
})();