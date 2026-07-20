const LIVE_PREVIEW_EDITOR_RATIO = 0.62;
const LIVE_PREVIEW_EDITOR_MAX = 1200;
const LIVE_PREVIEW_PREVIEW_MIN = 420;
const LIVE_PREVIEW_RESIZER_WIDTH = 16;
const LIVE_PREVIEW_EDITOR_FALLBACK_MIN = 320;

export function calculateLivePreviewEditorWidth(viewportWidth) {
    const width = Number.isFinite(viewportWidth) ? Math.max(0, viewportWidth) : 0;
    const preferred = Math.min(LIVE_PREVIEW_EDITOR_MAX, Math.round(width * LIVE_PREVIEW_EDITOR_RATIO));
    const previewConstrained = width - LIVE_PREVIEW_PREVIEW_MIN - LIVE_PREVIEW_RESIZER_WIDTH;

    if (previewConstrained >= LIVE_PREVIEW_EDITOR_FALLBACK_MIN) {
        return Math.min(preferred, previewConstrained);
    }

    return Math.max(
        0,
        Math.min(
            Math.max(LIVE_PREVIEW_EDITOR_FALLBACK_MIN, preferred),
            width - LIVE_PREVIEW_RESIZER_WIDTH,
        ),
    );
}

export function findLivePreviewLayout(documentRef = document, origin = null) {
    const livePreview = origin?.closest?.('.live-preview') || null;
    const editor = livePreview?.querySelector('.live-preview-editor');
    const preview = livePreview?.querySelector('.live-preview-contents');

    if (! livePreview || ! editor || ! preview) {
        return null;
    }

    return { livePreview, editor, preview };
}

export function activateLivePreviewLayout(layout, windowRef = window) {
    if (! layout?.editor || ! layout?.livePreview) {
        return null;
    }

    const { editor, livePreview } = layout;
    const original = {
        width: editor.style.width,
        maxWidth: editor.style.maxWidth,
        minWidth: editor.style.minWidth,
    };
    const previewWidth = livePreview.getBoundingClientRect?.().width || windowRef.innerWidth || 0;
    const width = calculateLivePreviewEditorWidth(previewWidth);

    editor.classList.add('sgb-live-preview-editor--active');
    editor.style.width = `${width}px`;
    editor.style.maxWidth = `${LIVE_PREVIEW_EDITOR_MAX}px`;
    editor.style.minWidth = '0';

    return {
        mode: 'live-preview',
        parent: editor,
        restore() {
            editor.classList.remove('sgb-live-preview-editor--active');
            editor.style.width = original.width;
            editor.style.maxWidth = original.maxWidth;
            editor.style.minWidth = original.minWidth;
        },
    };
}

export function resolveOverlayLayout(documentRef = document, windowRef = window, origin = null) {
    const livePreview = findLivePreviewLayout(documentRef, origin);

    if (livePreview) {
        return activateLivePreviewLayout(livePreview, windowRef);
    }

    return {
        mode: 'entry',
        parent: documentRef.body,
        restore() {},
    };
}
