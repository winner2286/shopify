// Isolated reproduction using the actual service worker; no browser or network.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const handlers = {};
const entries = new Map();
const key = request => typeof request === 'string' ? request : request.url;
let online = true;
const response = { status: 200, body: 'PRIVATE CUSTOMER DATA', clone() { return { ...this }; } };
const sandbox = {
    self: { addEventListener(type, callback) { handlers[type] = callback; } },
    location: { origin: 'https://audit.example' },
    URL, console,
    caches: {
        open: async () => ({ put: async (request, value) => entries.set(key(request), value) }),
        match: async request => entries.get(key(request)),
    },
    fetch: async () => { if (!online) throw new Error('offline'); return response; },
};
vm.runInNewContext(readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8'), sandbox);
const request = { method: 'GET', url: 'https://audit.example/commandes/42', destination: 'document' };
let result;
handlers.fetch({ request, respondWith(promise) { result = promise; } });
await result;
await new Promise(resolve => setImmediate(resolve));
assert.equal(result, undefined, 'Private requests must not be intercepted.');
assert.equal(entries.has(request.url), false);
online = false;
handlers.fetch({ request, respondWith(promise) { result = promise; } });
assert.equal(result, undefined);
const signedAsset = { method: 'GET', url: 'https://audit.example/images/private.png?token=secret', destination: 'image' };
handlers.fetch({ request: signedAsset, respondWith(promise) { result = promise; } });
assert.equal(result, undefined, 'Signed resources must never enter Cache Storage.');
console.log(JSON.stringify({ private_response_cached: false, private_response_returned_offline: false, signed_resource_intercepted: false }));
