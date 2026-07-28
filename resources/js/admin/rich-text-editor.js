const ALLOWED_TAGS = new Set(['A', 'BLOCKQUOTE', 'BR', 'B', 'EM', 'I', 'LI', 'OL', 'P', 'STRONG', 'U', 'UL']);
const DROP_WITH_CONTENT_TAGS = 'script, style, iframe, object, embed, template';
const STATE_COMMANDS = new Set(['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList']);

const safeHref = (href) => /^(?:https?:|mailto:|tel:|#|\/(?!\/))/i.test(href.trim());

const sanitizeRichTextHtml = (html) => {
    const template = document.createElement('template');
    template.innerHTML = String(html || '');
    template.content.querySelectorAll(DROP_WITH_CONTENT_TAGS).forEach((element) => element.remove());

    Array.from(template.content.querySelectorAll('*')).forEach((element) => {
        if (element.tagName === 'SPAN') {
            const formats = [];
            const weight = element.style.fontWeight;
            const decoration = element.style.textDecorationLine || element.style.textDecoration;

            if (weight === 'bold' || Number.parseInt(weight, 10) >= 600) formats.push('STRONG');
            if (element.style.fontStyle === 'italic') formats.push('EM');
            if (decoration.includes('underline')) formats.push('U');

            let nodes = Array.from(element.childNodes);
            formats.forEach((tagName) => {
                const wrapper = document.createElement(tagName);
                wrapper.append(...nodes);
                nodes = [wrapper];
            });
            element.replaceWith(...nodes);

            return;
        }

        if (element.tagName === 'DIV') {
            const paragraph = document.createElement('p');
            paragraph.append(...element.childNodes);
            element.replaceWith(paragraph);

            return;
        }

        if (!ALLOWED_TAGS.has(element.tagName)) {
            element.replaceWith(...element.childNodes);

            return;
        }

        const href = element.tagName === 'A' ? element.getAttribute('href')?.trim() || '' : '';
        Array.from(element.attributes).forEach((attribute) => element.removeAttribute(attribute.name));

        if (element.tagName === 'A' && safeHref(href)) {
            element.setAttribute('href', href);
            if (/^https?:/i.test(href)) {
                element.setAttribute('rel', 'noopener noreferrer');
                element.setAttribute('target', '_blank');
            }
        }
    });

    return template.innerHTML.trim();
};

const visibleText = (html) => {
    const template = document.createElement('template');
    template.innerHTML = String(html || '');
    return (template.content.textContent || '').replace(/\s+/g, ' ').trim();
};

const escapeHtml = (value) => {
    const element = document.createElement('div');
    element.textContent = value;
    return element.innerHTML;
};

const applySemanticInlineFallback = (editor, range, command) => {
    const tagName = { bold: 'strong', italic: 'em', underline: 'u' }[command];

    if (!tagName || range.collapsed || !editor.contains(range.commonAncestorContainer)) return false;

    const wrapper = document.createElement(tagName);

    try {
        range.surroundContents(wrapper);
    } catch (error) {
        wrapper.append(range.extractContents());
        range.insertNode(wrapper);
    }

    const selection = window.getSelection();
    selection?.removeAllRanges();
    const formattedRange = document.createRange();
    formattedRange.selectNodeContents(wrapper);
    selection?.addRange(formattedRange);

    return true;
};

const initializeRichTextEditor = (composer) => {
    if (composer.dataset.richTextReady === 'true') return;

    const editor = composer.querySelector('[data-rich-text-editor]');
    const input = composer.querySelector('[data-rich-text-input]');
    const source = composer.querySelector('[data-rich-text-source]');
    const sourceToggle = composer.querySelector('[data-rich-text-source-toggle]');
    const toolbar = composer.querySelector('[data-rich-text-toolbar], .admin-rich-text-toolbar');
    const linkButton = composer.querySelector('[data-rich-text-link]');
    const linkPanel = composer.querySelector('[data-rich-text-link-panel]');
    const linkInput = composer.querySelector('[data-rich-text-link-input]');
    const counter = composer.querySelector('[data-rich-text-counter]');
    const error = composer.querySelector('[data-rich-text-error]');

    if (!(editor instanceof HTMLElement) || !(input instanceof HTMLTextAreaElement) || !(source instanceof HTMLTextAreaElement)) return;

    composer.dataset.richTextReady = 'true';
    composer.dataset.richTextMode = 'visual';
    composer.classList.add('is-enhanced');
    if (toolbar instanceof HTMLElement) toolbar.hidden = false;
    editor.hidden = false;
    source.hidden = true;
    source.classList.add('hidden');
    source.required = false;
    let savedRange = null;

    const updateCounter = () => {
        if (counter) counter.textContent = `${visibleText(input.value).length.toLocaleString('fa-IR')} ${composer.dataset.counterLabel || 'کاراکتر'}`;
    };

    const setValidity = () => {
        const invalid = composer.dataset.required === 'true' && visibleText(input.value) === '';
        editor.setAttribute('aria-invalid', invalid ? 'true' : 'false');
        error?.classList.toggle('hidden', !invalid);
        return !invalid;
    };

    const syncFromVisual = () => {
        const sanitized = sanitizeRichTextHtml(editor.innerHTML);
        input.value = sanitized;
        if (composer.dataset.richTextMode !== 'source') source.value = sanitized;
        updateCounter();
        setValidity();
    };

    const syncFromSource = () => {
        const sanitized = sanitizeRichTextHtml(source.value);
        source.value = sanitized;
        editor.innerHTML = sanitized;
        input.value = sanitized;
        updateCounter();
        setValidity();
    };

    const selectionInsideEditor = (range) => range && editor.contains(range.commonAncestorContainer);

    const saveSelection = () => {
        const selection = window.getSelection();
        if (selection?.rangeCount) {
            const range = selection.getRangeAt(0);
            if (selectionInsideEditor(range)) savedRange = range.cloneRange();
        }
    };

    const restoreSelection = () => {
        editor.focus();
        if (!savedRange || !selectionInsideEditor(savedRange)) return;
        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(savedRange);
    };

    const updateToolbarState = () => {
        composer.querySelectorAll('[data-rich-text-command]').forEach((button) => {
            const command = button.dataset.richTextCommand;
            if (STATE_COMMANDS.has(command)) button.setAttribute('aria-pressed', document.queryCommandState(command) ? 'true' : 'false');
        });
    };

    const closeLinkPanel = () => {
        if (linkPanel) linkPanel.hidden = true;
        linkButton?.setAttribute('aria-expanded', 'false');
    };

    const setSourceMode = (enabled) => {
        const label = sourceToggle?.querySelector('[data-rich-text-source-toggle-label]');
        closeLinkPanel();
        composer.querySelectorAll('[data-rich-text-command], [data-rich-text-link]').forEach((button) => { button.disabled = enabled; });

        if (enabled) {
            syncFromVisual();
            composer.dataset.richTextMode = 'source';
            editor.hidden = true;
            source.hidden = false;
            source.classList.remove('hidden');
            if (sourceToggle instanceof HTMLButtonElement) {
                sourceToggle.title = sourceToggle.dataset.visualLabel || '';
                sourceToggle.setAttribute('aria-label', sourceToggle.dataset.visualLabel || '');
            }
            if (label) label.textContent = sourceToggle?.dataset.visualLabel || '';
            source.focus();
            return;
        }

        syncFromSource();
        composer.dataset.richTextMode = 'visual';
        source.hidden = true;
        source.classList.add('hidden');
        editor.hidden = false;
        if (sourceToggle instanceof HTMLButtonElement) {
            sourceToggle.title = sourceToggle.dataset.sourceLabel || '';
            sourceToggle.setAttribute('aria-label', sourceToggle.dataset.sourceLabel || '');
        }
        if (label) label.textContent = sourceToggle?.dataset.sourceLabel || '';
        editor.focus();
    };

    editor.addEventListener('input', () => { syncFromVisual(); saveSelection(); updateToolbarState(); });
    editor.addEventListener('keyup', () => { saveSelection(); updateToolbarState(); });
    editor.addEventListener('mouseup', () => { saveSelection(); updateToolbarState(); });
    editor.addEventListener('focus', saveSelection);
    editor.addEventListener('paste', (event) => {
        event.preventDefault();
        const clipboard = event.clipboardData;
        const html = clipboard?.getData('text/html');
        const plain = clipboard?.getData('text/plain') || '';
        const safeHtml = html ? sanitizeRichTextHtml(html) : escapeHtml(plain).replace(/\r?\n/g, '<br>');
        document.execCommand('insertHTML', false, safeHtml);
        syncFromVisual();
        saveSelection();
    });

    source.addEventListener('input', () => {
        input.value = source.value;
        updateCounter();
        setValidity();
    });

    composer.querySelectorAll('[data-rich-text-command], [data-rich-text-link]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
    });

    composer.querySelectorAll('[data-rich-text-command]').forEach((button) => {
        button.addEventListener('click', () => {
            restoreSelection();
            const selection = window.getSelection();
            const range = selection?.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
            const command = button.dataset.richTextCommand;
            const before = editor.innerHTML;

            document.execCommand('styleWithCSS', false, false);
            const executed = document.execCommand(command, false, button.dataset.richTextCommandValue || null);

            if ((!executed || editor.innerHTML === before) && range) {
                applySemanticInlineFallback(editor, range, command);
            }

            syncFromVisual();
            saveSelection();
            updateToolbarState();
        });
    });

    linkButton?.addEventListener('click', () => {
        saveSelection();
        if (linkPanel) linkPanel.hidden = !linkPanel.hidden;
        linkButton.setAttribute('aria-expanded', linkPanel?.hidden ? 'false' : 'true');
        if (linkPanel && !linkPanel.hidden && linkInput instanceof HTMLInputElement) linkInput.focus();
    });

    composer.querySelector('[data-rich-text-link-apply]')?.addEventListener('click', () => {
        const href = linkInput instanceof HTMLInputElement ? linkInput.value.trim() : '';
        if (!safeHref(href)) {
            linkInput?.setAttribute('aria-invalid', 'true');
            linkInput?.focus();
            return;
        }

        linkInput?.removeAttribute('aria-invalid');
        restoreSelection();
        const selection = window.getSelection();
        if (selection?.isCollapsed) {
            document.execCommand('insertHTML', false, `<a href="${escapeHtml(href)}">${escapeHtml(href)}</a>`);
        } else {
            document.execCommand('createLink', false, href);
        }
        syncFromVisual();
        saveSelection();
        closeLinkPanel();
    });

    composer.querySelector('[data-rich-text-link-cancel]')?.addEventListener('click', () => { closeLinkPanel(); restoreSelection(); });
    sourceToggle?.addEventListener('click', () => setSourceMode(composer.dataset.richTextMode !== 'source'));

    const form = composer.closest('form');
    const syncForSubmission = () => {
        composer.dataset.richTextMode === 'source' ? syncFromSource() : syncFromVisual();
    };

    form?.addEventListener('formdata', (event) => {
        syncForSubmission();

        if (input.name) event.formData.set(input.name, input.value);
    });
    form?.addEventListener('submit', (event) => {
        syncForSubmission();
        if (!setValidity()) {
            event.preventDefault();
            editor.hidden = false;
            source.hidden = true;
            source.classList.add('hidden');
            composer.dataset.richTextMode = 'visual';
            editor.focus();
        }
    });

    const observer = new MutationObserver(() => {
        if (composer.dataset.richTextMode === 'visual') syncFromVisual();
    });
    observer.observe(editor, { childList: true, characterData: true, subtree: true });

    document.execCommand('defaultParagraphSeparator', false, 'p');
    syncFromVisual();
};

export const initializeRichTextEditors = (root = document) => {
    if (root instanceof HTMLElement && root.matches('[data-admin-rich-text]')) initializeRichTextEditor(root);
    root.querySelectorAll('[data-admin-rich-text]').forEach((composer) => {
        if (composer instanceof HTMLElement) initializeRichTextEditor(composer);
    });
};

export const bootRichTextEditors = () => initializeRichTextEditors(document);
