import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initMarketFilters } from '../../resources/js/market-filters.js';

function element() {
    const classes = new Set();
    return {
        hidden: false, inert: false, attributes: {}, listeners: {}, dataset: {}, focused: false,
        classList: {
            add: name => classes.add(name), remove: name => classes.delete(name),
            contains: name => classes.has(name),
            toggle(name, enabled) { if (enabled) classes.add(name); else classes.delete(name); },
        },
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        removeEventListener(name) { delete this.listeners[name]; },
        getBoundingClientRect() { return {}; }, contains() { return false; },
        focus() { this.focused = true; }, click() { this.listeners.click?.(); },
    };
}

function setup(reduced = false) {
    const root = element();
    const toggle = element();
    const panel = element();
    toggle.hidden = true;
    toggle.dataset = { expandLabel: 'Filter anzeigen', collapseLabel: 'Filter ausblenden' };
    toggle.setAttribute('aria-expanded', 'false');
    root.querySelector = selector => selector === '[data-filter-toggle]' ? toggle : panel;
    const timers = new Map();
    let nextTimer = 0;
    const cleanup = initMarketFilters(root, {
        reducedMotion: () => reduced, activeElement: () => 'select',
        schedule: callback => { timers.set(++nextTimer, callback); return nextTimer; },
        cancel: id => timers.delete(id),
    });
    return { root, toggle, panel, timers, cleanup };
}

test('disclosure starts collapsed without removing filter values or disabling GET controls', () => {
    const { root, panel, toggle } = setup();
    assert.equal(root.classList.contains('filters-enhanced'), true);
    assert.equal(toggle.hidden, false);
    assert.equal(toggle.attributes['aria-expanded'], 'false');
    assert.equal(panel.hidden, true);
    assert.equal(panel.inert, true);
    assert.equal(panel.disabled, undefined);
});

test('opening and closing keep translated labels, aria-expanded and focusability in sync', () => {
    const { panel, toggle } = setup();
    toggle.click();
    assert.equal(panel.hidden, false);
    assert.equal(panel.inert, false);
    assert.equal(panel.classList.contains('is-open'), true);
    assert.equal(toggle.attributes['aria-expanded'], 'true');
    assert.equal(toggle.attributes['aria-label'], 'Filter ausblenden');
    toggle.click();
    assert.equal(panel.hidden, false); // Still visible during its closing transition.
    assert.equal(panel.inert, true);
    assert.equal(toggle.attributes['aria-expanded'], 'false');
    assert.equal(toggle.attributes['aria-label'], 'Filter anzeigen');
    panel.listeners.transitionend({ target: panel, propertyName: 'grid-template-rows' });
    assert.equal(panel.hidden, true);
});

test('Escape closes the panel and returns keyboard focus to the toggle', () => {
    const { root, panel, toggle } = setup();
    toggle.click();
    let prevented = false;
    root.listeners.keydown({ key: 'Escape', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(toggle.focused, true);
    assert.equal(panel.inert, true);
    assert.equal(toggle.attributes['aria-expanded'], 'false');
});

test('rapid reopening cancels pending hiding and nested transitions cannot hide the panel', () => {
    const { panel, toggle, timers } = setup();
    toggle.click(); toggle.click();
    const oldCallback = [...timers.values()][0];
    toggle.click();
    assert.equal(timers.size, 0);
    oldCallback();
    assert.equal(panel.hidden, false);
    panel.listeners.transitionend({ target: {}, propertyName: 'grid-template-rows' });
    assert.equal(panel.hidden, false);
});

test('reduced motion hides immediately and closing has a timeout fallback', () => {
    const reduced = setup(true);
    reduced.toggle.click(); reduced.toggle.click();
    assert.equal(reduced.panel.hidden, true);
    assert.equal(reduced.timers.size, 0);
    const normal = setup();
    normal.toggle.click(); normal.toggle.click();
    [...normal.timers.values()][0]();
    assert.equal(normal.panel.hidden, true);
});

test('cleanup restores the usable non-JavaScript filter panel', () => {
    const { root, panel, toggle, cleanup } = setup();
    cleanup();
    assert.equal(root.classList.contains('filters-enhanced'), false);
    assert.equal(panel.hidden, false);
    assert.equal(panel.inert, false);
    assert.equal(toggle.hidden, true);
    assert.equal(Object.keys(toggle.listeners).length, 0);
});
