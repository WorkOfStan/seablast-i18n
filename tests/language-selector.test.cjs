const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function deferred() {
  let resolve;
  let reject;
  const native = new Promise((yes, no) => { resolve = yes; reject = no; });
  const api = {
    then: native.then.bind(native),
    done(callback) { native.then((value) => { callback(value); }, () => {}); return api; },
    fail(callback) { native.then(() => {}, (error) => { callback(error); }); return api; },
  };
  const result = {
    resolve(value) { resolve(value); return result; },
    reject(error) { reject(error); return result; },
    promise() { return api; },
  };
  return result;
}

async function setup({ token = null, enabled = true, helper = true, failure = false, ajaxFailure = false } = {}) {
  const requests = [];
  let tokenCalls = 0;
  const $ = (argument) => {
    if (typeof argument === 'function') { argument(); return; }
    return { uls() {} };
  };
  $.Deferred = deferred;
  $.uls = { data: { getAutonym: (code) => code } };
  $.ajax = (options) => {
    requests.push(options);
    const pending = deferred();
    if (ajaxFailure) pending.reject(new Error('API failed'));
    else pending.resolve({ language: 'cs' });
    return pending.promise();
  };
  const modules = {
    ErrorLogger: class { log() {} },
    getCsrfToken: helper ? async () => {
      tokenCalls++;
      if (failure) throw new Error('Token failed');
      return 'lazy-token';
    } : undefined,
  };
  const window = { sbI18nLanguageList: ['en', 'cs'] };
  const context = vm.createContext({
    $, modules, window, API_BASE: '/app', csrfToken: token,
    flags: enabled ? ['I18n:SHOW_LANGUAGE_SELECTOR'] : [],
    document: { dispatchEvent() {} }, CustomEvent: class {},
  });
  const template = fs.readFileSync(path.join(__dirname, '../views/uls.js.latte'), 'utf8');
  const script = template.match(/<script[^>]*n:syntax=off[^>]*>([\s\S]+?)<\/script>/)[1]
    .replace(/await import\([^\n]+\)/, 'modules');
  await vm.runInContext(script, context);
  return { select: window.languageSelector, requests, tokenCalls: () => tokenCalls };
}

async function main() {
  const lazy = await setup();
  assert.equal(lazy.tokenCalls(), 0);
  await lazy.select();
  assert.equal(lazy.tokenCalls(), 0);
  assert.equal(lazy.requests.length, 1);
  assert.equal(lazy.requests[0].method, undefined);
  const changing = lazy.select('cs');
  assert.equal(typeof changing.done, 'function');
  assert.equal(typeof changing.fail, 'function');
  await changing;
  assert.equal(lazy.tokenCalls(), 1);
  assert.deepEqual(JSON.parse(lazy.requests[1].data), { language: 'cs', csrfToken: 'lazy-token' });
  await lazy.select('en');
  assert.equal(lazy.tokenCalls(), 1);
  const oldCore = await setup({ token: 'existing-token', helper: false });
  await oldCore.select('cs');
  assert.equal(JSON.parse(oldCore.requests[0].data).csrfToken, 'existing-token');
  for (const options of [{ failure: true }, { helper: false }, { enabled: false }]) {
    const test = await setup(options);
    await assert.rejects(Promise.resolve(test.select('cs')));
    assert.equal(test.requests.length, 0);
  }
  const failedPost = await setup({ ajaxFailure: true });
  await assert.rejects(Promise.resolve(failedPost.select('cs')), /API failed/);
  console.log('Language selector lazy CSRF, reads, compatibility, and failure tests passed.');
}
main().catch((error) => { console.error(error); process.exitCode = 1; });
