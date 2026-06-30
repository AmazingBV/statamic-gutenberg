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

        let hasOpenAutocloseItem = false;

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

            const shouldOpen = item.classList.contains('is-open') && (! autoclose || ! hasOpenAutocloseItem);
            hasOpenAutocloseItem = hasOpenAutocloseItem || shouldOpen;
            setOpen(item, shouldOpen);

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
        const tabList = tabs.querySelector(':scope > .wp-block-tab-list, :scope > .wp-block-tabs-list');
        const panelGroup = tabs.querySelector(':scope > .wp-block-tab-panels');
        const tabButtons = tabList
            ? directChildren(tabList, 'wp-block-tab')
            : directChildren(tabs, 'wp-block-tab');
        const panels = panelGroup
            ? directChildren(panelGroup, 'wp-block-tab-panel')
            : directChildren(tabs, 'wp-block-tab-panel');
        const pairCount = Math.min(tabButtons.length, panels.length);

        if (! pairCount) {
            return;
        }

        tabList?.setAttribute('role', 'tablist');

        const activeFromMarkup = Number.parseInt(tabs.dataset.sgbActiveTabIndex || '0', 10);
        let activeIndex = Number.isFinite(activeFromMarkup) ? activeFromMarkup : 0;

        const setActive = (nextIndex, shouldFocus = false) => {
            activeIndex = Math.max(0, Math.min(nextIndex, pairCount - 1));

            tabButtons.forEach((button, index) => {
                const active = index === activeIndex;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active && index < pairCount ? 'true' : 'false');
                button.setAttribute('tabindex', active && index < pairCount ? '0' : '-1');
                button.toggleAttribute('disabled', index >= pairCount);

                if (shouldFocus && active && index < pairCount) {
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
            if (index < pairCount) {
                button.setAttribute('aria-controls', panelId);
            } else {
                button.removeAttribute('aria-controls');
            }
            panel?.setAttribute('role', 'tabpanel');
            panel?.setAttribute('aria-labelledby', buttonId);

            button.addEventListener('click', () => {
                if (index < pairCount) {
                    setActive(index);
                }
            });
            button.addEventListener('keydown', (event) => {
                if (! ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) {
                    return;
                }

                event.preventDefault();

                if (event.key === 'Home') {
                    setActive(0, true);
                } else if (event.key === 'End') {
                    setActive(pairCount - 1, true);
                } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    setActive(activeIndex <= 0 ? pairCount - 1 : activeIndex - 1, true);
                } else {
                    setActive(activeIndex >= pairCount - 1 ? 0 : activeIndex + 1, true);
                }
            });
        });

        setActive(activeIndex);
    });
}

function initSearchBlocks(root = document) {
    root.querySelectorAll('.wp-block-search.wp-block-search__button-only').forEach((search) => {
        if (search.dataset.sgbSearchReady === 'true') {
            return;
        }

        search.dataset.sgbSearchReady = 'true';
        const input = search.querySelector('.wp-block-search__input');
        const button = search.querySelector('.wp-block-search__button');

        if (! input || ! button) {
            return;
        }

        const closeSearch = () => {
            if (! input.value) {
                search.classList.add('wp-block-search__searchfield-hidden');
            }
        };

        button.addEventListener('click', (event) => {
            if (! search.classList.contains('wp-block-search__searchfield-hidden')) {
                return;
            }

            event.preventDefault();
            search.classList.remove('wp-block-search__searchfield-hidden');
            input.focus();
        });

        search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSearch();
                button.focus();
            }
        });

        search.addEventListener('focusout', (event) => {
            if (! search.contains(event.relatedTarget)) {
                closeSearch();
            }
        });
    });
}

function initNavigationBlocks(root = document) {
    const syncModalClass = () => {
        const hasOpenMenu = Boolean(document.querySelector('.wp-block-navigation__responsive-container.is-menu-open'));
        document.documentElement.classList.toggle('has-modal-open', hasOpenMenu);
    };

    const setOverlayOpen = (container, open) => {
        container.classList.toggle('is-menu-open', open);
        container.setAttribute('aria-hidden', open ? 'false' : 'true');
        syncModalClass();

        if (open) {
            container.querySelector('.wp-block-navigation__responsive-container-close, a, button')?.focus();
        }
    };

    root.querySelectorAll('.wp-block-navigation').forEach((navigation) => {
        if (navigation.dataset.sgbNavigationReady === 'true') {
            return;
        }

        navigation.dataset.sgbNavigationReady = 'true';
        navigation.querySelectorAll('.wp-block-navigation-submenu__toggle, button.wp-block-navigation-item__content[aria-expanded]')
            .forEach((toggle) => {
                if (! toggle.hasAttribute('aria-expanded')) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
    });

    root.addEventListener('click', (event) => {
        const openButton = event.target.closest('.wp-block-navigation__responsive-container-open');

        if (openButton) {
            const navigation = openButton.closest('.wp-block-navigation');
            const container = navigation?.querySelector('.wp-block-navigation__responsive-container');

            if (container) {
                event.preventDefault();
                setOverlayOpen(container, true);
            }

            return;
        }

        const closeButton = event.target.closest('.wp-block-navigation__responsive-container-close');

        if (closeButton) {
            const container = closeButton.closest('.wp-block-navigation__responsive-container');

            if (container) {
                event.preventDefault();
                setOverlayOpen(container, false);
            }

            return;
        }

        const submenuToggle = event.target.closest('.wp-block-navigation-submenu__toggle, button.wp-block-navigation-item__content[aria-expanded]');

        if (! submenuToggle) {
            return;
        }

        const item = submenuToggle.closest('.has-child, .wp-block-navigation-submenu');
        const submenu = item?.querySelector(':scope > .wp-block-navigation__submenu-container');

        if (! item || ! submenu) {
            return;
        }

        event.preventDefault();
        const open = submenuToggle.getAttribute('aria-expanded') !== 'true';
        submenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        item.classList.toggle('is-open', open);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.wp-block-navigation__responsive-container.is-menu-open')
            .forEach((container) => setOverlayOpen(container, false));
    });
}

function initFileBlocks(root = document) {
    root.querySelectorAll('.wp-block-file__embed').forEach((embed) => {
        if (embed.dataset.sgbFileReady === 'true' || embed.textContent.trim()) {
            return;
        }

        embed.dataset.sgbFileReady = 'true';
        const url = embed.getAttribute('data');

        if (! url) {
            return;
        }

        const link = document.createElement('a');
        link.href = url;
        link.textContent = 'Open file';
        embed.appendChild(link);
    });
}

function initFormBlocks(root = document) {
    root.querySelectorAll('form.wp-block-form').forEach((form) => {
        if (form.dataset.sgbFormReady === 'true') {
            return;
        }

        form.dataset.sgbFormReady = 'true';

        form.addEventListener('submit', (event) => {
            const action = form.getAttribute('action') || '';

            if (action && (action.startsWith('mailto:') || action.startsWith('http') || action.startsWith('/'))) {
                return;
            }

            event.preventDefault();
            let status = form.querySelector('[data-sgb-form-status]');

            if (! status) {
                status = document.createElement('p');
                status.dataset.sgbFormStatus = 'true';
                status.setAttribute('role', 'status');
                form.appendChild(status);
            }

            status.textContent = 'Form endpoint is not configured.';
        });
    });
}

onReady(() => {
    initFitText();
    initLightbox();
    initAccordions();
    initTabs();
    initSearchBlocks();
    initNavigationBlocks();
    initFileBlocks();
    initFormBlocks();
});
