/* Rich text editor (ported from sb-hrm) — used by the Analytics notes popup.
 *
 * Markup contract, per editor:
 *   <div data-rich-editor>
 *     <div class="rich-editor-toolbar"><button data-cmd="bold">…</button></div>
 *     <div contenteditable data-rich-area></div>
 *     <textarea data-rich-input hidden></textarea>
 *   </div>
 *
 * The contenteditable is mirrored into the textarea on every change, so the
 * form posts plain HTML and the server cleans it with sanitizeHtml().
 *
 * Everything is delegated off `document`, so editors added to the page later
 * (e.g. inside a modal) work without re-binding.
 */
(function () {
    'use strict';

    if (window.__richEditorLoaded) return;
    window.__richEditorLoaded = true;

    // document.execCommand is deprecated but is still the only dependency-free
    // way to do this, and is supported everywhere this app runs.

    document.addEventListener('mousedown', function (e) {
        // Keep the caret in the editor when a toolbar button is pressed.
        if (e.target.closest('.rich-editor-toolbar button')) e.preventDefault();
    });

    document.addEventListener('click', function (e) {
        var button = e.target.closest('.rich-editor-toolbar button');
        if (!button) return;

        e.preventDefault();

        var editor = button.closest('[data-rich-editor]');
        var area   = editor.querySelector('[data-rich-area]');
        var cmd    = button.dataset.cmd;
        var value  = button.dataset.value || null;

        area.focus();

        if (cmd === 'createLink') {
            var url = window.prompt('Link URL', 'https://');
            if (!url) return;
            if (!/^(https?:|mailto:|\/)/i.test(url)) url = 'https://' + url;
            document.execCommand('createLink', false, url);
        } else if (cmd === 'formatBlock') {
            // Second press on the same block type toggles back to a paragraph.
            var current = (document.queryCommandValue('formatBlock') || '').toLowerCase();
            document.execCommand('formatBlock', false, current === value ? 'p' : value);
        } else {
            document.execCommand(cmd, false, value);
        }

        syncEditor(editor);
    });

    document.addEventListener('input', function (e) {
        var area = e.target.closest('[data-rich-area]');
        if (area) syncEditor(area.closest('[data-rich-editor]'));
    });

    // Paste as plain text, so pasted styles can't fight the app's theme.
    document.addEventListener('paste', function (e) {
        var area = e.target.closest('[data-rich-area]');
        if (!area) return;

        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
        syncEditor(area.closest('[data-rich-editor]'));
    });

    function syncEditor(editor) {
        if (!editor) return;
        var area  = editor.querySelector('[data-rich-area]');
        var input = editor.querySelector('[data-rich-input]');
        if (!area || !input) return;

        var html = area.innerHTML.trim();
        // An "empty" contenteditable still reports <br> or an empty paragraph.
        input.value = (html === '<br>' || html === '<p><br></p>' || html === '<div><br></div>') ? '' : html;
    }

    /** Shared with work.js and chat.js, which flush editors before submitting. */
    window.syncRichEditor = syncEditor;
})();
