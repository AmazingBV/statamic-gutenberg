import React from 'react';
import { createRoot } from 'react-dom/client';
import { GutenbergEditor } from './GutenbergEditor.jsx';
import { GutenbergWindow } from './GutenbergWindow.jsx';

const roots = new WeakMap();

export function mountGutenbergEditor(element, props) {
    let root = roots.get(element);

    if (! root) {
        root = createRoot(element);
        roots.set(element, root);
    }

    root.render(<GutenbergEditor {...props} />);

    return () => {
        root.unmount();
        roots.delete(element);
    };
}

export function mountGutenbergWindow(element, props) {
    let root = roots.get(element);

    if (! root) {
        root = createRoot(element);
        roots.set(element, root);
    }

    root.render(<GutenbergWindow {...props} />);

    return () => {
        root.unmount();
        roots.delete(element);
    };
}
