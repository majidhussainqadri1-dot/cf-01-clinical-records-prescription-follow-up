"""Browser-level regression for the protected CF-01 page lifecycle.

Uses Node's built-in vm and a minimal DOM fixture; no third-party dependencies.
All records are synthetic. These tests do not prove staging/live acceptance.
"""
from pathlib import Path
import shutil
import subprocess
import unittest


ROOT = Path(__file__).resolve().parents[1]
CLIENT = ROOT / "sabri-clinical-records" / "assets" / "js" / "clinical.js"


class ProtectedBrowserLifecycleTests(unittest.TestCase):
    def test_hidden_tab_races_and_bfcache_require_fresh_authorized_read(self):
        self.assertIsNotNone(shutil.which("node"), "Node is required for clinical browser privacy tests")
        javascript = r"""
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class FakeNode {
  constructor(tag, value = '') {
    this.tag = tag;
    this.value = value;
    this.children = [];
    this.attributes = {};
    this.dataset = {};
  }
  append(...nodes) { this.children.push(...nodes); }
  replaceChildren(...nodes) { this.children = nodes; }
  setAttribute(key, value) { this.attributes[key] = value; }
  focus() {}
  addEventListener() {}
}
const content = new FakeNode('section');
const app = new FakeNode('main');
app.dataset.view = 'my_record';
app.querySelector = (selector) => selector === '#cf01-content' ? content : null;
const listeners = { document: {}, window: {} };
const document = {
  visibilityState: 'visible',
  querySelector: (selector) => selector === '.cf01-app' ? app : null,
  createElement: (tag) => new FakeNode(tag),
  createElementNS: (_, tag) => new FakeNode(tag),
  createTextNode: (value) => new FakeNode('#text', String(value)),
  addEventListener: (name, handler) => { listeners.document[name] = handler; },
  referrer: '',
};
const window = {
  CF01_APP: {
    locale: 'ur-PK',
    nonce: 'synthetic-nonce',
    restRoot: 'https://example.test/wp-json/clinical/v1/',
  },
  location: {
    origin: 'https://example.test',
    assign() {},
  },
  history: { length: 1, back() {} },
  addEventListener: (name, handler) => { listeners.window[name] = handler; },
};
const requests = [];
const fetch = (url, options) => new Promise((resolve, reject) => {
  requests.push({ url: String(url), options, resolve, reject });
});

function settle(index) {
  requests[index].resolve({
    ok: true,
    json: async () => ({
      ok: true,
      data: {
        patient: { status: 'active', jurisdiction: 'synthetic' },
        encounters: [],
        prescriptions: [],
        followups: [],
        consents: [{ consent_uuid: 'synthetic-consent', purpose: 'clinical_care', status: 'granted', created_at: 'synthetic-date' }],
        access_history: [{ action: 'ClinicalRecordViewed', occurred_at: 'synthetic-date', result: 'success', actor_private_id: 'NEVER_SHOW' }],
      },
    }),
  });
}

const context = {
  document, window, CF01_APP: window.CF01_APP, Node: FakeNode,
  AbortController, DOMException, Headers, URL, fetch,
};
vm.runInNewContext(fs.readFileSync(process.argv[1], 'utf8'), context, { filename: 'clinical.js' });
const tick = () => new Promise((resolve) => setImmediate(resolve));
function collectText(node) {
  if (!(node instanceof FakeNode)) return '';
  return node.value + node.children.map(collectText).join('');
}

(async () => {
  assert.equal(requests.length, 1, 'Initial authorized request must start');
  assert.match(requests[0].url, /access_history/, 'Own-record read requests transparency fields');

  // Even a provider ignoring abort must not restore content after tab concealment.
  document.visibilityState = 'hidden';
  listeners.document.visibilitychange();
  assert.equal(requests[0].options.signal.aborted, true);
  assert.equal(content.children.length, 0);
  settle(0);
  await tick();
  assert.equal(content.children.length, 0, 'Late response must remain invisible');

  // Reopening requires a new request; it must not simply unhide a cached chart.
  document.visibilityState = 'visible';
  listeners.document.visibilitychange();
  assert.equal(requests.length, 2, 'Return to foreground must re-fetch');
  settle(1);
  await tick();
  assert.equal(content.children.length, 7, 'Own-record consent and access-history views must render');
  assert.match(collectText(content), /clinical_care/, 'Consent purpose must be visible to patient');
  assert.doesNotMatch(collectText(content), /NEVER_SHOW/, 'Private actor identity must not render');

  // Already-rendered data is also discarded on a second concealment.
  document.visibilityState = 'hidden';
  listeners.document.visibilitychange();
  assert.equal(content.children.length, 0);
  document.visibilityState = 'visible';
  listeners.document.visibilitychange();
  assert.equal(requests.length, 3);
  settle(2);
  await tick();
  assert.equal(content.children.length, 7);

  // Back-forward cache restore cannot reuse the previous sensitive DOM.
  listeners.window.pagehide();
  assert.equal(content.children.length, 0);
  listeners.window.pageshow({ persisted: true });
  assert.equal(requests.length, 4);
  settle(3);
  await tick();
  assert.equal(content.children.length, 7);

  // A failed transport after concealment must never repaint even a generic error.
  document.visibilityState = 'hidden';
  listeners.document.visibilitychange();
  document.visibilityState = 'visible';
  listeners.document.visibilitychange();
  assert.equal(requests.length, 5);
  document.visibilityState = 'hidden';
  listeners.document.visibilitychange();
  requests[4].reject(new TypeError('Synthetic delayed transport error'));
  await tick();
  assert.equal(content.children.length, 0, 'Hidden view must remain empty after stale error');

  // An older failed request must not overwrite a newer loading/successful view.
  document.visibilityState = 'visible';
  listeners.document.visibilitychange();
  listeners.document.visibilitychange();
  assert.equal(requests.length, 7);
  requests[5].reject(new TypeError('Synthetic superseded transport error'));
  await tick();
  assert.equal(content.children.length, 1, 'Newer loading state must survive old error');
  settle(6);
  await tick();
  assert.equal(content.children.length, 7, 'Newest authorized response must render');
})().catch((error) => {
  process.stderr.write(String(error.stack || error));
  process.exitCode = 1;
});
"""
        result = subprocess.run(
            ["node", "-e", javascript, str(CLIENT)],
            cwd=ROOT, capture_output=True, text=True, timeout=20, check=False,
        )
        self.assertEqual(result.returncode, 0, result.stderr or result.stdout)


if __name__ == "__main__":
    unittest.main()
