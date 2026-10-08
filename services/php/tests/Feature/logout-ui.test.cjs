const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
const { join } = require('node:path');

function page(fetch, token = 'current-token') {
    const elements = Object.fromEntries(['account', 'status', 'error', 'logout', 'user-name'].map(id => [id, {
        hidden: ['account', 'error'].includes(id), disabled: false, textContent: '',
        addEventListener(event, handler) { this[event] = handler; },
    }]));
    const events = {};
    const storage = new Map(token ? [['auth_token', token]] : []);
    const redirects = [];
    const source = readFileSync(join(__dirname, '../../resources/views/auth/account.blade.php'), 'utf8')
        .match(/<script>([\s\S]*?)<\/script>/)[1].replace("@json(route('login'))", "'/login'");
    runInNewContext(source, {
        document: { getElementById: id => elements[id] },
        sessionStorage: { getItem: key => storage.get(key), removeItem: key => storage.delete(key) },
        window: { location: { replace: url => redirects.push(url) }, addEventListener: (event, handler) => { events[event] = handler; } },
        fetch,
    });
    return { elements, events, storage, redirects };
}

const response = (status, data = { name: 'Test user' }) => ({ status, ok: status >= 200 && status < 300, json: async () => data });

test('logout sends one POST, disables repeat clicks, clears token and redirects', async () => {
    let resolve;
    const calls = [];
    const ui = page((url, options) => {
        calls.push({ url, options });
        return new Promise(done => { resolve = done; });
    });
    const pending = ui.elements.logout.click();
    await ui.elements.logout.click();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/api/logout');
    assert.equal(calls[0].options.method, 'POST');
    assert.equal(calls[0].options.headers.Authorization, 'Bearer current-token');
    assert.equal(ui.elements.logout.disabled, true);
    assert.match(ui.elements.logout.textContent, /Đang đăng xuất/);
    resolve(response(200));
    await pending;
    assert.equal(ui.storage.has('auth_token'), false);
    assert.deepEqual(ui.redirects, ['/login']);
    assert.equal(ui.elements.account.hidden, true);
});

for (const [name, fetch] of [
    ['server failure', async () => response(500)],
    ['network failure', async () => { throw new Error('offline'); }],
]) {
    test(`${name} shows error, retains token and allows retry`, async () => {
        const ui = page(fetch);
        await ui.elements.logout.click();
        assert.equal(ui.elements.error.hidden, false);
        assert.equal(ui.elements.logout.disabled, false);
        assert.equal(ui.storage.get('auth_token'), 'current-token');
        assert.deepEqual(ui.redirects, []);
    });
}

test('already revoked token clears local login', async () => {
    const ui = page(async () => response(401));
    await ui.elements.logout.click();
    assert.equal(ui.storage.has('auth_token'), false);
    assert.deepEqual(ui.redirects, ['/login']);
});

test('Back with no token keeps private content hidden and redirects', async () => {
    const ui = page(() => { throw new Error('must not fetch'); }, null);
    await ui.events.pageshow();
    assert.equal(ui.elements.account.hidden, true);
    assert.deepEqual(ui.redirects, ['/login']);
});

test('Back validates revoked token before showing private content', async () => {
    const ui = page(async () => response(401));
    await ui.events.pageshow();
    assert.equal(ui.elements.account.hidden, true);
    assert.deepEqual(ui.redirects, ['/login']);
});

test('pagehide clears private content and pageshow revalidates login', async () => {
    const calls = [];
    const ui = page(async (url, options) => { calls.push({ url, options }); return response(200); });
    await ui.events.pageshow();
    assert.equal(ui.elements.account.hidden, false);
    assert.equal(ui.elements['user-name'].textContent, 'Test user');
    ui.events.pagehide();
    assert.equal(ui.elements.account.hidden, true);
    assert.equal(ui.elements['user-name'].textContent, '');
    await ui.events.pageshow();
    assert.equal(calls.length, 2);
    assert.equal(calls[1].options.cache, 'no-store');
    assert.equal(ui.elements.account.hidden, false);
});
