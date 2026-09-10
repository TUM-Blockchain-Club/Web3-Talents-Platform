// Run with: node moodle/tooling/validate-room-moves-js.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../plugins/local_web3talents/room_assignments.js'), 'utf8');

function setup(fetchImpl) {
    let handler;
    let focused = false;
    const controls = [{disabled: false}, {disabled: false}];
    const status = {textContent: '', className: ''};
    const form = {dataset: {roomMove: '12'}};
    const panel = {
        innerHTML: 'original rooms',
        dataset: {moveUrl: '/move_room_group.php', saving: 'Saving', failure: 'Check saved assignment'},
        addEventListener: (_type, fn) => { handler = fn; },
        setAttribute: () => {}, removeAttribute: () => {}, querySelectorAll: () => controls,
    };
    vm.runInNewContext(source, {
        document: {getElementById: id => id === 'room-assignment-panel' ? panel :
            id === 'room-move-status' ? status : {focus: () => { focused = true; }}},
        FormData: class { constructor(value) { assert.equal(value, form); } },
        fetch: fetchImpl,
    });
    return {panel, status, controls, focused: () => focused,
        submit: () => handler({target: {closest: () => form}, preventDefault: () => {}})};
}

(async() => {
    let resolve;
    let calls = 0;
    const pending = new Promise(done => { resolve = done; });
    const success = setup(async(_url, options) => {
        calls++;
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        await pending;
        return {ok: true, json: async() => ({success: true, html: 'saved rooms + updated warnings', message: 'Moved'})};
    });
    const request = success.submit();
    assert.ok(success.controls.every(control => control.disabled));
    await success.submit();
    assert.equal(calls, 1, 'duplicate submissions are suppressed');
    resolve();
    await request;
    assert.equal(success.panel.innerHTML, 'saved rooms + updated warnings');
    assert.equal(success.status.textContent, 'Moved');
    assert.ok(success.focused());
    assert.ok(success.controls.every(control => !control.disabled));
    console.log('PASS: saved fragment replaces rooms/warnings; duplicate requests blocked; controls/focus restored');

    for (const fetchImpl of [
        async() => { throw new Error('offline'); },
        async() => ({ok: false, json: async() => ({success: false})}),
        async() => ({ok: true, json: async() => { throw new Error('Session expired: HTML response'); }}),
    ]) {
        const failure = setup(fetchImpl);
        await failure.submit();
        assert.equal(failure.panel.innerHTML, 'original rooms');
        assert.equal(failure.status.textContent, 'Check saved assignment');
        assert.ok(failure.controls.every(control => !control.disabled));
    }
    console.log('PASS: network, rejected request and expired-session responses preserve the displayed rooms and show an error');
})().catch(error => { console.error(error); process.exitCode = 1; });
