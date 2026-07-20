import {
    activateLivePreviewLayout,
    calculateLivePreviewEditorWidth,
    findLivePreviewLayout,
    resolveOverlayLayout,
} from './overlayLayout';

describe('overlay layout', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('keeps a useful live preview width at common viewport sizes', () => {
        expect(calculateLivePreviewEditorWidth(1920)).toBe(1190);
        expect(calculateLivePreviewEditorWidth(1440)).toBe(893);
        expect(calculateLivePreviewEditorWidth(1024)).toBe(588);
    });

    it('only detects a complete Statamic live preview layout', () => {
        document.body.innerHTML = '<div class="live-preview"><div class="live-preview-editor"></div></div>';
        const origin = document.createElement('div');
        document.querySelector('.live-preview-editor').appendChild(origin);

        expect(findLivePreviewLayout(document, origin)).toBeNull();

        document.querySelector('.live-preview').insertAdjacentHTML(
            'beforeend',
            '<div class="live-preview-contents"></div>',
        );

        expect(findLivePreviewLayout(document, origin)?.editor).toBe(document.querySelector('.live-preview-editor'));
    });

    it('widens and restores the live preview editor pane', () => {
        document.body.innerHTML = `
            <div class="live-preview">
                <div class="live-preview-editor" style="width: 400px"></div>
                <div class="live-preview-contents"></div>
            </div>
        `;
        const livePreview = document.querySelector('.live-preview');
        livePreview.getBoundingClientRect = () => ({ width: 1440 });
        const origin = document.createElement('div');
        document.querySelector('.live-preview-editor').appendChild(origin);
        const layout = findLivePreviewLayout(document, origin);
        const active = activateLivePreviewLayout(layout, window);

        expect(active.mode).toBe('live-preview');
        expect(active.parent).toBe(layout.editor);
        expect(layout.editor.style.width).toBe('893px');
        expect(layout.editor.classList.contains('sgb-live-preview-editor--active')).toBe(true);

        active.restore();

        expect(layout.editor.style.width).toBe('400px');
        expect(layout.editor.classList.contains('sgb-live-preview-editor--active')).toBe(false);
    });

    it('falls back to the normal entry overlay parent outside Live Preview', () => {
        document.body.innerHTML = `
            <div class="live-preview">
                <div class="live-preview-editor"></div>
                <div class="live-preview-contents"></div>
            </div>
            <div class="regular-entry-field"></div>
        `;
        const layout = resolveOverlayLayout(
            document,
            window,
            document.querySelector('.regular-entry-field'),
        );

        expect(layout.mode).toBe('entry');
        expect(layout.parent).toBe(document.body);
    });
});
