import { mountGutenbergWindow } from './gutenberg/mount'

function mountStandaloneEditor() {
    const root = document.getElementById('sgb-window-root')

    if (! root || (root.dataset.mounted && root.childElementCount > 0)) {
        return
    }

    root.dataset.mounted = 'true'
    const params = new URLSearchParams(window.location.search)
    const channel = root.dataset.channel
        || params.get('channel')
        || window.sessionStorage.getItem('statamic-gutenberg:last-channel')
        || ''
    const title = root.dataset.title || params.get('title') || document.title || 'Gutenberg Editor'

    if (channel) {
        window.sessionStorage.setItem('statamic-gutenberg:last-channel', channel)

        if (! params.get('channel')) {
            params.set('channel', channel)
            if (title) params.set('title', title)
            window.history.replaceState(null, '', `${window.location.pathname}?${params.toString()}`)
        }
    }

    mountGutenbergWindow(root, {
        channel,
        title,
    })
}

function scheduleStandaloneEditorMount() {
    mountStandaloneEditor()
    window.requestAnimationFrame(mountStandaloneEditor)
    window.setTimeout(mountStandaloneEditor, 100)
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleStandaloneEditorMount)
} else {
    scheduleStandaloneEditorMount()
}
