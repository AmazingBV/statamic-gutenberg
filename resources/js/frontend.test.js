import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

async function loadFrontend() {
    vi.resetModules();
    await import('./frontend.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));
}

describe('frontend block interactions', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('toggles accordion panels with autoclose state', async () => {
        document.body.innerHTML = `
            <div class="wp-block-accordion" data-sgb-accordion-autoclose="true">
                <div class="wp-block-accordion-item is-open">
                    <h3 class="wp-block-accordion-heading">
                        <button class="wp-block-accordion-heading__toggle" type="button">First</button>
                    </h3>
                    <div class="wp-block-accordion-panel">First panel</div>
                </div>
                <div class="wp-block-accordion-item">
                    <h3 class="wp-block-accordion-heading">
                        <button class="wp-block-accordion-heading__toggle" type="button">Second</button>
                    </h3>
                    <div class="wp-block-accordion-panel">Second panel</div>
                </div>
            </div>
        `;

        await loadFrontend();

        const items = document.querySelectorAll('.wp-block-accordion-item');
        const firstButton = items[0].querySelector('button');
        const firstPanel = items[0].querySelector('.wp-block-accordion-panel');
        const secondButton = items[1].querySelector('button');
        const secondPanel = items[1].querySelector('.wp-block-accordion-panel');

        expect(firstButton.getAttribute('aria-expanded')).toBe('true');
        expect(firstPanel.hidden).toBe(false);
        expect(secondButton.getAttribute('aria-expanded')).toBe('false');
        expect(secondPanel.hidden).toBe(true);
        expect(secondPanel.hasAttribute('inert')).toBe(true);

        secondButton.click();

        expect(firstButton.getAttribute('aria-expanded')).toBe('false');
        expect(firstPanel.hidden).toBe(true);
        expect(firstPanel.hasAttribute('inert')).toBe(true);
        expect(secondButton.getAttribute('aria-expanded')).toBe('true');
        expect(secondPanel.hidden).toBe(false);
        expect(secondPanel.hasAttribute('inert')).toBe(false);
    });

    it('keeps only the first initially open accordion item when autoclose is enabled', async () => {
        document.body.innerHTML = `
            <div class="wp-block-accordion" data-sgb-accordion-autoclose="true">
                <div class="wp-block-accordion-item is-open">
                    <h3 class="wp-block-accordion-heading"><button class="wp-block-accordion-heading__toggle" type="button">First</button></h3>
                    <div class="wp-block-accordion-panel">First panel</div>
                </div>
                <div class="wp-block-accordion-item is-open">
                    <h3 class="wp-block-accordion-heading"><button class="wp-block-accordion-heading__toggle" type="button">Second</button></h3>
                    <div class="wp-block-accordion-panel">Second panel</div>
                </div>
            </div>
        `;

        await loadFrontend();

        const items = document.querySelectorAll('.wp-block-accordion-item');

        expect(items[0].classList.contains('is-open')).toBe(true);
        expect(items[0].querySelector('.wp-block-accordion-panel').hidden).toBe(false);
        expect(items[1].classList.contains('is-open')).toBe(false);
        expect(items[1].querySelector('.wp-block-accordion-panel').hidden).toBe(true);
    });

    it('keeps nested tabs independent from their parent tabs', async () => {
        document.body.innerHTML = `
            <div class="wp-block-tabs" data-sgb-active-tab-index="0" id="outer">
                <div class="wp-block-tab-list">
                    <button class="wp-block-tab" type="button">Outer first</button>
                    <button class="wp-block-tab" type="button">Outer second</button>
                </div>
                <div class="wp-block-tab-panels">
                    <section class="wp-block-tab-panel" data-sgb-tab-label="Outer first panel">
                        <div class="wp-block-tabs" data-sgb-active-tab-index="0" id="inner">
                            <div class="wp-block-tab-list">
                                <button class="wp-block-tab" type="button">Inner first</button>
                                <button class="wp-block-tab" type="button">Inner second</button>
                            </div>
                            <div class="wp-block-tab-panels">
                                <section class="wp-block-tab-panel" data-sgb-tab-label="Inner first panel">Inner first panel</section>
                                <section class="wp-block-tab-panel" data-sgb-tab-label="Inner second panel">Inner second panel</section>
                            </div>
                        </div>
                    </section>
                    <section class="wp-block-tab-panel" data-sgb-tab-label="Outer second panel">Outer second panel</section>
                </div>
            </div>
        `;

        await loadFrontend();

        const outer = document.querySelector('#outer');
        const inner = document.querySelector('#inner');
        const outerPanels = Array.from(outer.querySelector(':scope > .wp-block-tab-panels').children);
        const innerButtons = Array.from(inner.querySelector(':scope > .wp-block-tab-list').children);
        const innerPanels = Array.from(inner.querySelector(':scope > .wp-block-tab-panels').children);

        expect(outerPanels[0].hidden).toBe(false);
        expect(outerPanels[1].hidden).toBe(true);
        expect(innerPanels[0].hidden).toBe(false);
        expect(innerPanels[1].hidden).toBe(true);

        innerButtons[1].click();

        expect(outerPanels[0].hidden).toBe(false);
        expect(outerPanels[1].hidden).toBe(true);
        expect(innerPanels[0].hidden).toBe(true);
        expect(innerPanels[1].hidden).toBe(false);
        expect(innerButtons[1].getAttribute('aria-selected')).toBe('true');
    });

    it('ignores extra tab buttons that have no matching panel', async () => {
        document.body.innerHTML = `
            <div class="wp-block-tabs">
                <div class="wp-block-tab-list">
                    <button class="wp-block-tab" type="button">First</button>
                    <button class="wp-block-tab" type="button">Second</button>
                    <button class="wp-block-tab" type="button">Missing panel</button>
                </div>
                <div class="wp-block-tab-panels">
                    <section class="wp-block-tab-panel" data-sgb-tab-label="First panel">First panel</section>
                    <section class="wp-block-tab-panel" data-sgb-tab-label="Second panel">Second panel</section>
                </div>
            </div>
        `;

        await loadFrontend();

        const buttons = Array.from(document.querySelectorAll('.wp-block-tab'));
        const panels = Array.from(document.querySelectorAll('.wp-block-tab-panel'));

        expect(buttons[2].disabled).toBe(true);
        expect(buttons[2].getAttribute('aria-controls')).toBeNull();

        buttons[2].click();

        expect(panels[0].hidden).toBe(false);
        expect(panels[1].hidden).toBe(true);

        buttons[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));

        expect(buttons[1].getAttribute('aria-selected')).toBe('true');
        expect(buttons[2].getAttribute('aria-selected')).toBe('false');
        expect(panels[0].hidden).toBe(true);
        expect(panels[1].hidden).toBe(false);
    });
});
