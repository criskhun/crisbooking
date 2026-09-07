import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const app = readFileSync(new URL('../../public/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf('        const calendarBookingDialog =');
const end = app.indexOf('        const unitProfitReportDialog =', start);
assert.ok(start >= 0 && end > start);

function fixture() {
    const elements = new Map();
    const element = () => ({
        dataset: {}, handlers: {}, hidden: false,
        classList: { add() {}, remove() {} },
        addEventListener(name, callback) { this.handlers[name] = callback; },
    });
    const close = element();
    const dialog = Object.assign(element(), {
        querySelector(selector) {
            if (!elements.has(selector)) elements.set(selector, element());
            return elements.get(selector);
        },
        querySelectorAll() { return [close]; },
        showModal() { this.open = true; },
        close() { this.open = false; },
    });
    const links = [element(), element()];
    links[0].dataset = {
        unit: 'Test Condo', client: 'Test Guest', categoryIcon: 'building',
        start: 'Sep 7', end: 'Sep 9', status: 'Confirmed', statusKey: 'confirmed',
        source: 'Direct', notes: 'Arrival at noon', bookingUrl: '/bookings/123',
    };
    links[1].dataset = { unit: 'Test Car', client: 'Second Guest' };
    links[1].href = '/bookings/456';
    runInNewContext(app.slice(start, end), { document: {
        querySelector() { return dialog; }, querySelectorAll() { return links; },
    } });
    return { dialog, links, close };
}

test('calendar and timeline booking clicks populate, open, close, and refresh the dialog', () => {
    const { dialog, links, close } = fixture();
    let prevented = false;
    links[0].handlers.click({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(dialog.open, true);
    assert.equal(dialog.querySelector('[data-calendar-dialog-client]').textContent, 'Test Guest');
    assert.equal(dialog.querySelector('[data-calendar-dialog-icon]').className, 'fa-solid fa-building calendar-dialog-icon');
    assert.equal(dialog.querySelector('[data-calendar-dialog-link]').href, '/bookings/123');
    assert.equal(dialog.querySelector('[data-calendar-dialog-notes-wrap]').hidden, false);
    close.handlers.click();
    assert.equal(dialog.open, false);
    links[1].handlers.click({ preventDefault() {} });
    assert.equal(dialog.querySelector('[data-calendar-dialog-unit]').textContent, 'Test Car');
    assert.equal(dialog.querySelector('[data-calendar-dialog-link]').href, '/bookings/456');
    assert.equal(dialog.querySelector('[data-calendar-dialog-notes-wrap]').hidden, true);
    assert.equal(dialog.querySelector('[data-calendar-dialog-source-wrap]').hidden, true);
    dialog.handlers.click({ target: links[1] });
    assert.equal(dialog.open, true);
    dialog.handlers.click({ target: dialog });
    assert.equal(dialog.open, false);
});

test('booking link navigation remains available without native dialog support', () => {
    const { dialog, links } = fixture();
    dialog.showModal = undefined;
    links[0].handlers.click({ preventDefault() { assert.fail('Navigation was blocked'); } });
});
