<script setup>
import { Fieldtype } from '@statamic/cms';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { resolveOverlayLayout } from './overlayLayout';

const editorOpeners = window.StatamicGutenbergEditorOpeners ||= new Map();

if (! window.StatamicGutenbergDelegatedOpenInstalled) {
    document.addEventListener('click', (event) => {
        const button = event.target?.closest?.('[data-sgb-open-editor]');
        const opener = button ? editorOpeners.get(button.getAttribute('data-sgb-open-editor')) : null;

        opener?.(button);
    }, true);
    window.StatamicGutenbergDelegatedOpenInstalled = true;
}

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update } = Fieldtype.use(emit, props);
defineExpose(expose);

const channel = `sgb-${Date.now()}-${Math.random().toString(36).slice(2)}`;
const isOpen = ref(false);
const editorLoading = ref(false);
const editorError = ref('');
const lastSyncedAt = ref(null);
const editorMeta = ref(null);
const fieldRoot = ref(null);
let overlayHost = null;
let overlayRoot = null;
let unmountEditor = null;
let mountGutenbergWindow = null;
let activeOverlayLayout = null;
let overlayResizeObserver = null;
let overlayConnectionObserver = null;

const value = computed(() => (typeof props.value === 'string' ? props.value : ''));
const blockCount = computed(() => (value.value.match(/<!--\s+wp:/g) || []).length);
const title = computed(() => findEntryTitle());
const preview = computed(() => {
    const text = value.value
        .replace(/<!--[\s\S]*?-->/g, ' ')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    return text.length > 180 ? `${text.slice(0, 177)}...` : text;
});

function plain(value) {
    return JSON.parse(JSON.stringify(value || {}));
}

function findEntryTitle() {
    const titleInput = document.querySelector('[aria-label^="Title"], input[name="title"], textarea[name="title"]');
    const inputValue = titleInput?.value?.trim();

    if (inputValue) {
        return inputValue;
    }

    return document.querySelector('h1')?.textContent?.trim() || 'Block Editor';
}

function payload(nextValue = value.value) {
    const meta = plain(editorMeta.value || props.meta);

    return {
        value: nextValue,
        config: plain(props.config),
        meta,
        title: title.value,
        fieldLabel: props.display || props.handle || 'Content',
    };
}

function exposeEditorMeta(meta) {
    if (meta?.iconsUrl) {
        window.StatamicGutenbergIconsUrl = meta.iconsUrl;
    }

    if (meta?.blockRendererUrl) {
        window.StatamicGutenbergBlockRendererUrl = meta.blockRendererUrl;
    }

    if (Array.isArray(meta?.allowedBlocks)) {
        window.StatamicGutenbergAllowedBlocks = meta.allowedBlocks;
    }

    if (meta?.patterns) {
        window.StatamicGutenbergPatterns = meta.patterns;
    }

    if (Array.isArray(meta?.blockStyles)) {
        window.StatamicGutenbergBlockStyles = meta.blockStyles;
    }
}

async function refreshEditorMeta() {
    const meta = plain(props.meta);

    if (! meta.patternsUrl) {
        return meta;
    }

    try {
        const response = await fetch(meta.patternsUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const json = await response.json();

        if (json?.data) {
            meta.patterns = json.data;
        }
    } catch (error) {
        console.warn('Unable to load block editor patterns.', error);
    }

    return meta;
}

function updateLastSyncedAt() {
    lastSyncedAt.value = new Date().toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function chromeOffset() {
    const header = document.querySelector('.global-header, header.bg-global-header-bg, header[class*="bg-global-header-bg"]');
    const nav = document.querySelector('.nav-main');
    const headerRect = header?.getBoundingClientRect();
    const navRect = nav?.getBoundingClientRect();
    const top = headerRect?.bottom > 0 ? headerRect.bottom : 56;
    let left = 0;

    if (navRect && navRect.right > 24 && navRect.left > -24 && window.matchMedia('(min-width: 1024px)').matches) {
        left = navRect.right;
    }

    return {
        top: Math.max(0, Math.round(top)),
        left: Math.max(0, Math.round(left)),
    };
}

function positionOverlay() {
    if (! overlayHost) {
        return;
    }

    if (activeOverlayLayout?.mode === 'live-preview') {
        return;
    }

    const offset = chromeOffset();
    overlayHost.style.setProperty('--sgb-overlay-top', `${offset.top}px`);
    overlayHost.style.setProperty('--sgb-overlay-left', `${offset.left}px`);
}

function ensureOverlayRoot(origin = fieldRoot.value) {
    if (overlayRoot) {
        return overlayRoot;
    }

    activeOverlayLayout = resolveOverlayLayout(document, window, origin);
    overlayHost = document.createElement('div');
    overlayHost.className = [
        'sgb-overlay-host',
        activeOverlayLayout.mode === 'live-preview' ? 'sgb-overlay-host--live-preview' : '',
    ].filter(Boolean).join(' ');
    overlayHost.setAttribute('data-sgb-overlay', 'true');
    overlayRoot = document.createElement('div');
    overlayRoot.className = 'sgb-overlay-root';
    overlayHost.appendChild(overlayRoot);
    activeOverlayLayout.parent.appendChild(overlayHost);
    document.body.classList.add('sgb-overlay-open');
    positionOverlay();
    window.addEventListener('resize', positionOverlay);

    if (activeOverlayLayout.mode === 'live-preview' && typeof ResizeObserver === 'function') {
        overlayResizeObserver = new ResizeObserver(() => {
            window.dispatchEvent(new CustomEvent('sgb:overlay-resize'));
        });
        overlayResizeObserver.observe(activeOverlayLayout.parent);
    }

    if (activeOverlayLayout.mode === 'live-preview' && typeof MutationObserver === 'function') {
        overlayConnectionObserver = new MutationObserver(() => {
            if (! activeOverlayLayout?.parent?.isConnected || ! overlayHost?.isConnected) {
                closeEditor();
            }
        });
        overlayConnectionObserver.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    return overlayRoot;
}

async function loadEditorMount() {
    if (mountGutenbergWindow) {
        return mountGutenbergWindow;
    }

    ({ mountGutenbergWindow } = await import('../../gutenberg/mount.jsx'));

    return mountGutenbergWindow;
}

function renderEditor() {
    if (! isOpen.value || ! overlayRoot || ! mountGutenbergWindow) {
        return;
    }

    const nextPayload = payload();
    exposeEditorMeta(nextPayload.meta);

    unmountEditor = mountGutenbergWindow(overlayRoot, {
        channel,
        embedded: true,
        initialPayload: nextPayload,
        title: title.value,
        onApply: applyValue,
        onClose: closeEditor,
        onSave: applyAndSave,
        layoutMode: activeOverlayLayout?.mode || 'entry',
    });
}

async function openEditor(trigger = null) {
    if (isOpen.value || editorLoading.value) {
        return;
    }

    const origin = trigger?.currentTarget || trigger || fieldRoot.value;
    editorError.value = '';
    editorLoading.value = true;
    isOpen.value = true;

    try {
        ensureOverlayRoot(origin);
        await loadEditorMount();
        editorMeta.value = await refreshEditorMeta();
        renderEditor();
    } catch (error) {
        console.warn('Unable to open block editor overlay.', error);
        editorError.value = 'Unable to open the block editor overlay.';
        closeEditor();
    } finally {
        editorLoading.value = false;
    }
}

function closeEditor() {
    unmountEditor?.();
    unmountEditor = null;

    if (overlayHost) {
        overlayHost.remove();
    }

    overlayHost = null;
    overlayRoot = null;
    isOpen.value = false;
    editorMeta.value = null;
    overlayResizeObserver?.disconnect();
    overlayResizeObserver = null;
    overlayConnectionObserver?.disconnect();
    overlayConnectionObserver = null;
    activeOverlayLayout?.restore();
    activeOverlayLayout = null;
    window.removeEventListener('resize', positionOverlay);
    document.body.classList.remove('sgb-overlay-open');
    cleanupDetachedEditorOpener();
}

function cleanupDetachedEditorOpener() {
    setTimeout(() => {
        const channelStillExists = Array.from(document.querySelectorAll('[data-sgb-open-editor]'))
            .some((button) => button.getAttribute('data-sgb-open-editor') === channel);

        if (! channelStillExists) {
            editorOpeners.delete(channel);
        }
    });
}

async function applyValue(nextValue = '') {
    update(typeof nextValue === 'string' ? nextValue : '');
    updateLastSyncedAt();
    await nextTick();
    return true;
}

function visibleText(element) {
    return [
        element.textContent,
        element.getAttribute('aria-label'),
        element.getAttribute('title'),
    ].filter(Boolean).join(' ').trim();
}

function triggerStatamicSave() {
    const buttons = Array.from(document.querySelectorAll('button, [role="button"]'));
    const saveButton = buttons.find((button) => {
        if (overlayHost?.contains(button) || button.disabled || button.getAttribute('aria-disabled') === 'true') {
            return false;
        }

        if (button.offsetParent === null) {
            return false;
        }

        return /\b(save|opslaan)\b/i.test(visibleText(button));
    });

    if (saveButton) {
        saveButton.click();
        return true;
    }

    window.Statamic?.$events?.$emit?.('root-form-save');

    return false;
}

async function applyAndSave(nextValue = '') {
    await applyValue(nextValue);
    await nextTick();

    return triggerStatamicSave();
}

onBeforeUnmount(() => {
    if (
        isOpen.value
        && activeOverlayLayout?.mode === 'live-preview'
        && overlayHost?.isConnected
    ) {
        return;
    }

    closeEditor();
});

onMounted(() => {
    editorOpeners.set(channel, openEditor);
});

watch(value, () => {
    renderEditor();
});
</script>

<template>
    <div ref="fieldRoot" class="sgb-fieldtype">
        <div class="sgb-fieldtype__panel">
            <div class="sgb-fieldtype__summary">
                <div>
                    <div class="sgb-fieldtype__eyebrow">Block Editor</div>
                    <div class="sgb-fieldtype__title">Full-size page overlay</div>
                    <div class="sgb-fieldtype__meta">
                        <span>{{ blockCount }} blocks</span>
                        <span v-if="lastSyncedAt">Synced {{ lastSyncedAt }}</span>
                        <span v-else-if="isOpen">Editor overlay open</span>
                    </div>
                </div>

                <button
                    type="button"
                    class="sgb-fieldtype__button"
                    :data-sgb-open-editor="channel"
                    :disabled="editorLoading"
                    @click="openEditor"
                >
                    {{ editorLoading ? 'Opening...' : 'Open Block Editor' }}
                </button>
            </div>

            <p v-if="preview" class="sgb-fieldtype__preview">{{ preview }}</p>
            <p v-else class="sgb-fieldtype__preview">Open the full-size overlay to start building this page.</p>

            <p v-if="editorError" class="sgb-fieldtype__notice">{{ editorError }}</p>
        </div>
    </div>
</template>
