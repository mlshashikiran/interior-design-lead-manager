/* Browser-handler regressions without dependencies. Builder: Shashi. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/settings.js'), 'utf8');
function fixture(selected = ['222']) {
    class Element {
        constructor(items = [{}]) { this.items = items; this.length = items.length; this.props = {}; this.attrs = {}; this.handlers = {}; this.children = {}; this.value = ''; this.message = ''; this.events = []; }
        find(selector) { return this.children[selector] || new Element([]); }
        closest() { return wizard; }
        prop(key, value) { if (arguments.length === 1) return this.props[key]; this.props[key] = value; return this; }
        attr(key, value) { if (arguments.length === 1) return this.attrs[key]; this.attrs[key] = value; return this; }
        text(value) { if (!arguments.length) return this.message; this.message = value; return this; }
        val() { return this.value; }
        on(event, selector, callback) { this.handlers[event + ':' + selector] = callback; return this; }
        trigger(event) { this.events.push(event); return this; }
        removeAttr(key) { delete this.attrs[key]; return this; }
        map(callback) { return {get: () => this.items.map(item => callback.call(item))}; }
    }
    const root = new Element(), wizard = new Element(), status = new Element(), syncStatus = new Element();
    const pages = new Element(), forms = new Element(), inputs = new Element(selected.map(value => ({value}))), review = new Element(), button = new Element(), document = new Element();
    pages.value = '111';
    Object.assign(root.children, {'[data-idlm-facebook-status]': status, '[data-idlm-facebook-sync-status]': syncStatus, '[data-idlm-facebook-pages]': pages, '[data-idlm-facebook-forms]': forms});
    forms.children['input:checked'] = inputs; forms.children.input = inputs;
    wizard.children['[data-idlm-manual-next="4"], [data-idlm-wizard-go="4"]'] = review;
    const ready = [], requests = [], navigations = {reloads: 0, replaces: 0};
    const $ = value => { if (typeof value === 'function') { ready.push(value); return; } return value === '[data-idlm-facebook-connect]' ? root : value; };
    $.extend = Object.assign;
    $.ajax = options => {
        const done = [], failed = [], always = [];
        const request = {options, done(fn) { done.push(fn); return this; }, fail(fn) { failed.push(fn); return this; }, always(fn) { always.push(fn); return this; }, resolve(value) { done.forEach(fn => fn(value)); always.forEach(fn => fn()); }, reject(value) { failed.forEach(fn => fn(value)); always.forEach(fn => fn()); }};
        requests.push(request); return request;
    };
    const location = {href: 'https://crm.example.test/workspace/?idlm_view=settings#tab-webhooks', reload() { navigations.reloads++; }, assign() { throw new Error('Fragment assignment must not replace a document reload'); }};
    const window = {location, history: {replaceState(a, b, url) { location.href = url; navigations.replaces++; }}};
    vm.runInNewContext(source, {jQuery: $, window, document, URL, idlm_vars: {ajax_url: '/admin-ajax.php', nonce: 'synthetic'}});
    ready.find(fn => fn.toString().includes("var $root = $('[data-idlm-facebook-connect]')"))();
    return {click: () => root.handlers['click:[data-idlm-facebook-save]'].call(button), requests, navigations, button, inputs, pages, review, syncStatus, document};
}
const cases = [
    ['success reloads the document at the existing anchor and announces saved source', () => {
        const f = fixture(); f.click();
        assert.equal(f.button.props.disabled, true); assert.equal(f.pages.props.disabled, true); assert.equal(f.review.props.disabled, true);
        assert.equal(f.requests[0].options.data.page_id, '111'); assert.equal(f.requests[0].options.data.forms.join(','), '222');
        f.requests[0].resolve({success: true, data: {connected: true}});
        assert.equal(f.navigations.reloads, 1); assert.equal(f.navigations.replaces, 1);
        assert.equal(f.button.props.disabled, true); assert.ok(f.document.events.includes('idlm-facebook-source-saved'));
    }],
    ['provider error is visible beside Start sync and selection stays editable', () => {
        const f = fixture(); f.click(); f.requests[0].reject({responseJSON: {data: {message: 'Synthetic permission failure'}}});
        assert.equal(f.navigations.reloads, 0); assert.equal(f.syncStatus.message, 'Synthetic permission failure');
        assert.equal(f.syncStatus.attrs.role, 'alert'); assert.ok(f.syncStatus.events.includes('focus'));
        assert.equal(f.button.props.disabled, false); assert.equal(f.inputs.props.disabled, false); assert.equal(f.review.props.disabled, false);
    }],
    ['unconfirmed success never opens stale review', () => {
        const f = fixture(); f.click(); f.requests[0].resolve({success: true, data: {}});
        assert.equal(f.navigations.reloads, 0); assert.equal(f.syncStatus.attrs.role, 'alert'); assert.equal(f.button.props.disabled, false);
    }],
    ['JSON failure preserves the server message for retry', () => {
        const f = fixture(); f.click(); f.requests[0].resolve({success: false, data: {message: 'Session expired'}});
        assert.equal(f.syncStatus.message, 'Session expired'); assert.equal(f.navigations.reloads, 0);
    }],
    ['no checked forms sends no request and gives focused feedback', () => {
        const f = fixture([]); f.click(); assert.equal(f.requests.length, 0); assert.equal(f.syncStatus.attrs.role, 'alert');
    }]
];
let failures = 0;
for (const [name, run] of cases) { try { run(); process.stdout.write('PASS ' + name + '\n'); } catch (error) { failures++; process.stderr.write('FAIL ' + name + ': ' + error.message + '\n'); } }
process.stdout.write((cases.length - failures) + ' passed, ' + failures + ' failed\n');
process.exitCode = failures ? 1 : 0;
