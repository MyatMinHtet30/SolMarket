// Only the homepage disclosure is enhanced. GET forms/links still handle filtering.
export function initMarketFilters(root, {
    reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    activeElement = () => document.activeElement,
    schedule = setTimeout,
    cancel = clearTimeout,
} = {}) {
    if (!root) return;
    const toggle = root.querySelector('[data-filter-toggle]');
    const panel = root.querySelector('[data-filter-panel]');
    let expanded = false;
    let closingTimer;

    root.classList.add('filters-enhanced');
    toggle.hidden = false;
    panel.hidden = true;
    panel.inert = true;

    const finishClosing = () => { if (!expanded) panel.hidden = true; };
    const setExpanded = (next) => {
        cancel(closingTimer);
        expanded = next;
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', expanded ? toggle.dataset.collapseLabel : toggle.dataset.expandLabel);
        if (expanded) {
            panel.hidden = false;
            // Establish the collapsed frame before transitioning to the natural height.
            panel.getBoundingClientRect();
        } else if (panel.contains(activeElement())) {
            toggle.focus();
        }
        panel.inert = !expanded;
        panel.classList.toggle('is-open', expanded);
        if (!expanded) {
            if (reducedMotion()) finishClosing();
            else closingTimer = schedule(finishClosing, 260);
        }
    };
    const onClick = () => setExpanded(!expanded);
    const onKeydown = (event) => {
        if (event.key === 'Escape' && expanded) {
            event.preventDefault();
            setExpanded(false);
            toggle.focus();
        }
    };
    const onTransitionEnd = (event) => {
        if (event.target === panel && event.propertyName === 'grid-template-rows') finishClosing();
    };
    toggle.addEventListener('click', onClick);
    root.addEventListener('keydown', onKeydown);
    panel.addEventListener('transitionend', onTransitionEnd);

    return () => {
        cancel(closingTimer);
        toggle.removeEventListener('click', onClick);
        root.removeEventListener('keydown', onKeydown);
        panel.removeEventListener('transitionend', onTransitionEnd);
        root.classList.remove('filters-enhanced');
        panel.classList.remove('is-open');
        panel.hidden = false;
        panel.inert = false;
        toggle.hidden = true;
    };
}
