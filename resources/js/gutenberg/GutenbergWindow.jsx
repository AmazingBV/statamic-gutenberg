import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@wordpress/components';
import { close as closeIcon, check } from '@wordpress/icons';
import { GutenbergEditor } from './GutenbergEditor.jsx';

const SOURCE = 'statamic-gutenberg-window';

function readPayload(channel) {
    try {
        return JSON.parse(localStorage.getItem(`statamic-gutenberg:${channel}`) || 'null');
    } catch (error) {
        return null;
    }
}

function writePayload(channel, payload) {
    localStorage.setItem(`statamic-gutenberg:${channel}`, JSON.stringify(payload));
}

export function GutenbergWindow({
    channel,
    embedded = false,
    initialPayload = null,
    onApply,
    onChange,
    onClose,
    onSave,
    title,
}) {
    const storedPayload = useMemo(() => readPayload(channel), [channel]);
    const startingPayload = initialPayload || storedPayload;
    const [payload, setPayload] = useState(startingPayload);
    const [value, setValue] = useState(startingPayload?.value || '');
    const [lastAppliedValue, setLastAppliedValue] = useState(startingPayload?.value || '');
    const [status, setStatus] = useState(startingPayload ? 'Connected to Statamic' : 'Waiting for field data');
    const [busyAction, setBusyAction] = useState(null);
    const [editorValid, setEditorValid] = useState(true);

    const sendToOpener = useCallback((type, nextValue = '') => {
        if (embedded) {
            return;
        }

        if (! window.opener || window.opener.closed) {
            return;
        }

        window.opener.postMessage({
            source: SOURCE,
            type,
            channel,
            value: nextValue,
        }, window.location.origin);
    }, [channel, embedded]);

    useEffect(() => {
        if (embedded) {
            return undefined;
        }

        function handleMessage(event) {
            const data = event.data || {};

            if (event.origin !== window.location.origin || data.channel !== channel || data.source !== 'statamic-gutenberg-field') {
                return;
            }

            if (data.type === 'hydrate' && data.payload) {
                setPayload(data.payload);
                setValue(data.payload.value || '');
                writePayload(channel, data.payload);
                setStatus('Connected to Statamic');
            }
        }

        window.addEventListener('message', handleMessage);
        sendToOpener('ready');

        return () => window.removeEventListener('message', handleMessage);
    }, [channel, embedded, sendToOpener]);

    useEffect(() => {
        if (embedded) {
            return undefined;
        }

        function notifyClosed() {
            sendToOpener('closed');
        }

        window.addEventListener('beforeunload', notifyClosed);

        return () => window.removeEventListener('beforeunload', notifyClosed);
    }, [embedded, sendToOpener]);

    useEffect(() => {
        if (! embedded || ! initialPayload) {
            return;
        }

        setPayload(initialPayload);
        setValue(initialPayload.value || '');
        setLastAppliedValue(initialPayload.value || '');
        setStatus((current) => current === 'Waiting for field data' ? 'Connected to Statamic' : current);
    }, [embedded, initialPayload]);

    const handleChange = useCallback((nextValue) => {
        setValue(nextValue);
        const nextPayload = {
            ...(payload || {}),
            value: nextValue,
        };

        setPayload(nextPayload);
        writePayload(channel, nextPayload);

        if (embedded) {
            onChange?.(nextValue);
            setStatus('Edited in overlay');
        } else {
            sendToOpener('change', nextValue);
            setStatus('Synced to Statamic form');
        }
    }, [channel, embedded, onChange, payload, sendToOpener]);

    const handleValidityChange = useCallback((isValid) => {
        setEditorValid(isValid);

        if (! isValid) {
            setStatus('Code editor has invalid block syntax');
        }
    }, []);

    const apply = useCallback(async () => {
        if (! editorValid) {
            setStatus('Fix code editor syntax before applying');
            return;
        }

        setBusyAction('apply');

        try {
            if (embedded) {
                await onApply?.(value);
            } else {
                sendToOpener('apply', value);
            }

            setLastAppliedValue(value);
            setStatus('Applied to Statamic form');
        } finally {
            setBusyAction(null);
        }
    }, [editorValid, embedded, onApply, sendToOpener, value]);

    const applyAndClose = useCallback(async () => {
        if (! editorValid) {
            setStatus('Fix code editor syntax before applying');
            return;
        }

        setBusyAction('close');

        try {
            if (embedded) {
                await onApply?.(value);
                setLastAppliedValue(value);
                onClose?.();
            } else {
                sendToOpener('apply', value);
                sendToOpener('closed', value);
                window.close();
            }
        } finally {
            setBusyAction(null);
        }
    }, [editorValid, embedded, onApply, onClose, sendToOpener, value]);

    const applyAndSave = useCallback(async () => {
        if (! editorValid) {
            setStatus('Fix code editor syntax before saving');
            return;
        }

        setBusyAction('save');

        try {
            if (embedded) {
                const saveTriggered = await onSave?.(value);
                setLastAppliedValue(value);
                setStatus(saveTriggered === false ? 'Applied; use the Statamic save button' : 'Applied and save requested');
            } else {
                sendToOpener('apply', value);
                sendToOpener('save', value);
                setLastAppliedValue(value);
                setStatus('Applied to Statamic form');
            }
        } finally {
            setBusyAction(null);
        }
    }, [editorValid, embedded, onSave, sendToOpener, value]);

    const closeWithoutApplying = useCallback(() => {
        if (value !== lastAppliedValue && window.confirm && ! window.confirm('Close without applying? Your block editor changes will be lost.')) {
            return;
        }

        if (embedded) {
            onClose?.();
        } else {
            sendToOpener('closed', value);
            window.close();
        }
    }, [embedded, lastAppliedValue, onClose, sendToOpener, value]);

    if (! channel || ! payload) {
        return (
            <div className="sgb-window sgb-window--empty">
                <div className="sgb-window__empty">
                    <h1>Open this editor from a block editor field</h1>
                    <p>The full-size editor needs field data from the Statamic entry form.</p>
                </div>
            </div>
        );
    }

    return (
        <div className={`sgb-window${embedded ? ' sgb-window--embedded' : ''}`}>
            <header className="sgb-window__header">
                <div>
                    <div className="sgb-window__eyebrow">{payload.fieldLabel || 'Content'}</div>
                    <h1>Block Editor</h1>
                </div>

                <div className="sgb-window__actions">
                    <span className={`sgb-window__status${editorValid ? '' : ' sgb-window__status--error'}`}>{status}</span>
                    {embedded ? (
                        <Button icon={check} variant="primary" onClick={applyAndSave} disabled={busyAction !== null || ! editorValid}>
                            Apply and save
                        </Button>
                    ) : null}
                    <Button icon={closeIcon} variant="secondary" onClick={applyAndClose} disabled={busyAction !== null || ! editorValid}>
                        Apply and close
                    </Button>
                    <Button icon={check} variant="tertiary" onClick={apply} disabled={busyAction !== null || ! editorValid}>
                        Apply
                    </Button>
                    {embedded ? (
                        <Button icon={closeIcon} variant="tertiary" onClick={closeWithoutApplying} disabled={busyAction !== null}>
                            Close
                        </Button>
                    ) : null}
                </div>
            </header>

            <GutenbergEditor
                value={value}
                config={payload.config || {}}
                meta={payload.meta || {}}
                onChange={handleChange}
                onValidityChange={handleValidityChange}
                variant="fullscreen"
            />
        </div>
    );
}
