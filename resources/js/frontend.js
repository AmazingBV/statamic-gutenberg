function onReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
        return;
    }

    callback();
}

function directChildren(element, className) {
    return Array.from(element.children).filter((child) => child.classList.contains(className));
}

function initFitText(root = document) {
    const headings = Array.from(root.querySelectorAll('.wp-block-heading.has-fit-text'));

    if (! headings.length) {
        return;
    }

    const resizeHeading = (heading) => {
        const parentWidth = heading.parentElement?.clientWidth || heading.clientWidth;

        if (! parentWidth) {
            return;
        }

        heading.style.fontSize = '';
        const currentSize = parseFloat(window.getComputedStyle(heading).fontSize);

        if (! currentSize || heading.scrollWidth <= parentWidth) {
            return;
        }

        const nextSize = Math.max(16, Math.floor(currentSize * (parentWidth / heading.scrollWidth)));
        heading.style.fontSize = `${nextSize}px`;
    };

    const resizeAll = () => headings.forEach(resizeHeading);
    resizeAll();
    window.addEventListener('resize', resizeAll, { passive: true });
}

function initLightbox(root = document) {
    let overlay = document.querySelector('[data-sgb-lightbox-overlay]');
    let activeIndex = -1;
    let previousBodyOverflow = '';

    const figures = () => Array.from(document.querySelectorAll('[data-sgb-lightbox]'))
        .filter((figure) => figure.querySelector('img'));

    const ensureOverlay = () => {
        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.className = 'sgb-lightbox';
        overlay.dataset.sgbLightboxOverlay = 'true';
        overlay.hidden = true;
        overlay.innerHTML = [
            '<button class="sgb-lightbox__close" type="button" data-sgb-lightbox-close aria-label="Close image">&times;</button>',
            '<button class="sgb-lightbox__nav sgb-lightbox__nav--previous" type="button" data-sgb-lightbox-previous aria-label="Previous image">&lsaquo;</button>',
            '<img class="sgb-lightbox__image" alt="">',
            '<button class="sgb-lightbox__nav sgb-lightbox__nav--next" type="button" data-sgb-lightbox-next aria-label="Next image">&rsaquo;</button>',
        ].join('');
        document.body.appendChild(overlay);

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay || event.target.closest('[data-sgb-lightbox-close]')) {
                closeLightbox();
            } else if (event.target.closest('[data-sgb-lightbox-previous]')) {
                showLightbox(activeIndex - 1);
            } else if (event.target.closest('[data-sgb-lightbox-next]')) {
                showLightbox(activeIndex + 1);
            }
        });

        return overlay;
    };

    const showLightbox = (index) => {
        const lightboxFigures = figures();

        if (! lightboxFigures.length) {
            return;
        }

        activeIndex = (index + lightboxFigures.length) % lightboxFigures.length;
        const image = lightboxFigures[activeIndex].querySelector('img');
        const overlayElement = ensureOverlay();
        const overlayImage = overlayElement.querySelector('.sgb-lightbox__image');

        overlayImage.src = image.currentSrc || image.src;
        overlayImage.alt = image.alt || '';
        overlayElement.querySelector('[data-sgb-lightbox-previous]').hidden = lightboxFigures.length < 2;
        overlayElement.querySelector('[data-sgb-lightbox-next]').hidden = lightboxFigures.length < 2;
    };

    function openLightbox(figure) {
        const index = figures().indexOf(figure);

        if (index === -1) {
            return;
        }

        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        ensureOverlay().hidden = false;
        showLightbox(index);
        overlay.querySelector('[data-sgb-lightbox-close]')?.focus();
    }

    function closeLightbox() {
        if (! overlay || overlay.hidden) {
            return;
        }

        overlay.hidden = true;
        document.body.style.overflow = previousBodyOverflow;
        activeIndex = -1;
    }

    root.addEventListener('click', (event) => {
        if (event.defaultPrevented) {
            return;
        }

        const trigger = event.target.closest('[data-sgb-lightbox-trigger], [data-sgb-lightbox] img');

        if (! trigger) {
            return;
        }

        const figure = trigger.closest('[data-sgb-lightbox]');

        if (! figure) {
            return;
        }

        event.preventDefault();
        openLightbox(figure);
    });

    document.addEventListener('keydown', (event) => {
        if (! overlay || overlay.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            closeLightbox();
        } else if (event.key === 'ArrowLeft') {
            showLightbox(activeIndex - 1);
        } else if (event.key === 'ArrowRight') {
            showLightbox(activeIndex + 1);
        }
    });
}

function initAccordions(root = document) {
    root.querySelectorAll('.wp-block-accordion').forEach((accordion, accordionIndex) => {
        if (accordion.dataset.sgbAccordionReady === 'true') {
            return;
        }

        accordion.dataset.sgbAccordionReady = 'true';
        const autoclose = accordion.dataset.sgbAccordionAutoclose === 'true';
        const items = directChildren(accordion, 'wp-block-accordion-item');

        const setOpen = (item, open) => {
            const button = item.querySelector('.wp-block-accordion-heading__toggle');
            const panel = item.querySelector('.wp-block-accordion-panel');

            item.classList.toggle('is-open', open);
            button?.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (panel) {
                panel.hidden = ! open;
                panel.toggleAttribute('inert', ! open);
                panel.setAttribute('aria-hidden', open ? 'false' : 'true');
            }
        };

        items.forEach((item, itemIndex) => {
            const heading = item.querySelector('.wp-block-accordion-heading');
            const button = item.querySelector('.wp-block-accordion-heading__toggle');
            const panel = item.querySelector('.wp-block-accordion-panel');

            if (! button || ! panel) {
                return;
            }

            const headingId = heading?.id || `sgb-accordion-${accordionIndex}-heading-${itemIndex}`;
            const panelId = panel.id || `sgb-accordion-${accordionIndex}-panel-${itemIndex}`;

            if (heading && ! heading.id) {
                heading.id = headingId;
            }

            if (! panel.id) {
                panel.id = panelId;
            }

            button.setAttribute('aria-controls', panelId);
            panel.setAttribute('role', 'region');
            panel.setAttribute('aria-labelledby', headingId);

            setOpen(item, item.classList.contains('is-open'));

            button.addEventListener('click', () => {
                const nextOpen = ! item.classList.contains('is-open');

                if (autoclose && nextOpen) {
                    items.forEach((otherItem) => setOpen(otherItem, false));
                }

                setOpen(item, nextOpen);
            });
        });
    });
}

function initTabs(root = document) {
    root.querySelectorAll('.wp-block-tabs').forEach((tabs, tabsIndex) => {
        if (tabs.dataset.sgbTabsReady === 'true') {
            return;
        }

        tabs.dataset.sgbTabsReady = 'true';
        const tabButtons = Array.from(tabs.querySelectorAll('.wp-block-tab'));
        const panels = Array.from(tabs.querySelectorAll('.wp-block-tab-panel'));

        if (! tabButtons.length || ! panels.length) {
            return;
        }

        const tabList = tabs.querySelector('.wp-block-tab-list, .wp-block-tabs-list');
        tabList?.setAttribute('role', 'tablist');

        const activeFromMarkup = Number.parseInt(tabs.dataset.sgbActiveTabIndex || '0', 10);
        let activeIndex = Number.isFinite(activeFromMarkup) ? activeFromMarkup : 0;

        const setActive = (nextIndex, shouldFocus = false) => {
            activeIndex = Math.max(0, Math.min(nextIndex, tabButtons.length - 1));

            tabButtons.forEach((button, index) => {
                const active = index === activeIndex;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.setAttribute('tabindex', active ? '0' : '-1');

                if (shouldFocus && active) {
                    button.focus();
                }
            });

            panels.forEach((panel, index) => {
                const active = index === activeIndex;
                panel.hidden = ! active;
                panel.setAttribute('tabindex', '0');
            });
        };

        tabButtons.forEach((button, index) => {
            const panel = panels[index];
            const panelId = panel?.id || `sgb-tabs-${tabsIndex}-panel-${index}`;
            const buttonId = button.id || `sgb-tabs-${tabsIndex}-tab-${index}`;
            const label = panel?.dataset.sgbTabLabel || panel?.getAttribute('aria-label') || `Tab ${index + 1}`;

            if (! button.id) {
                button.id = buttonId;
            }

            if (panel && ! panel.id) {
                panel.id = panelId;
            }

            if (! button.textContent.trim()) {
                button.textContent = label;
            }

            button.setAttribute('type', 'button');
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-controls', panelId);
            panel?.setAttribute('role', 'tabpanel');
            panel?.setAttribute('aria-labelledby', buttonId);

            button.addEventListener('click', () => setActive(index));
            button.addEventListener('keydown', (event) => {
                if (! ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) {
                    return;
                }

                event.preventDefault();

                if (event.key === 'Home') {
                    setActive(0, true);
                } else if (event.key === 'End') {
                    setActive(tabButtons.length - 1, true);
                } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    setActive(activeIndex <= 0 ? tabButtons.length - 1 : activeIndex - 1, true);
                } else {
                    setActive(activeIndex >= tabButtons.length - 1 ? 0 : activeIndex + 1, true);
                }
            });
        });

        setActive(activeIndex);
    });
}

onReady(() => {
    initFitText();
    initLightbox();
    initAccordions();
    initTabs();
});
