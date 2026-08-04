(() => {
  'use strict';

  const app = document.querySelector('.cf01-app');
  if (!app || !window.CF01_APP) return;

  const content = app.querySelector('#cf01-content');
  const state = { controller: null, requestId: 0 };

  const text = (value) => document.createTextNode(String(value ?? ''));
  const element = (tag, attrs = {}, children = []) => {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([key, value]) => {
      if (key === 'class') node.className = String(value);
      else if (key.startsWith('aria-')) node.setAttribute(key, String(value));
      else if (key === 'hidden') node.hidden = Boolean(value);
      else node.setAttribute(key, String(value));
    });
    children.forEach((child) => node.append(child instanceof Node ? child : text(child)));
    return node;
  };

  const replace = (...nodes) => {
    content.replaceChildren(...nodes);
    content.focus({ preventScroll: true });
  };

  const safeFetch = async (path, options = {}) => {
    if (state.controller) state.controller.abort();
    state.controller = new AbortController();
    const requestId = ++state.requestId;
    const headers = new Headers(options.headers || {});
    headers.set('X-WP-Nonce', CF01_APP.nonce);
    headers.set('Accept', 'application/json');
    headers.set('Cache-Control', 'no-store');
    const response = await fetch(new URL(path, CF01_APP.restRoot), {
      ...options,
      credentials: 'same-origin',
      cache: 'no-store',
      redirect: 'error',
      referrerPolicy: 'no-referrer',
      headers,
      signal: state.controller.signal,
    });
    if (requestId !== state.requestId) throw new DOMException('Superseded', 'AbortError');
    const payload = await response.json().catch(() => ({ ok: false, code: 'invalid_response' }));
    if (!response.ok || payload.ok === false) {
      const error = new Error(payload.message || 'Protected clinical operation failed.');
      error.code = payload.code || 'request_failed';
      error.traceId = payload.trace_id || '';
      throw error;
    }
    return payload.data ?? payload;
  };

  const renderError = (error) => {
    if (error?.name === 'AbortError') return;
    const title = element('h2', {}, ['Protected record unavailable']);
    const message = element('p', {}, ['The requested clinical information could not be displayed. No record existence has been disclosed.']);
    const retry = element('button', { type: 'button', class: 'cf01-button' }, ['Retry']);
    retry.addEventListener('click', load);
    const box = element('div', { class: 'cf01-state cf01-state--error', role: 'alert' }, [title, message, retry]);
    if (error?.traceId) box.append(element('p', { class: 'cf01-trace' }, [`Trace: ${error.traceId}`]));
    replace(box);
  };

  const renderHealth = (data) => {
    const list = element('dl', { class: 'cf01-summary' });
    [['Runtime', data.runtime_version], ['Activation', data.activation_state], ['Status', data.status]].forEach(([label, value]) => {
      list.append(element('dt', {}, [label]), element('dd', {}, [value]));
    });
    replace(element('section', { class: 'cf01-card' }, [element('h2', {}, ['Clinical system status']), list]));
  };

  const renderRecordShell = () => {
    const notice = element('div', { class: 'cf01-state', role: 'status' }, [
      element('h2', {}, ['Protected clinical workspace']),
      element('p', {}, ['Select an authorized record or action. Clinical data is fetched only after click-time authorization and is never stored offline by this interface.']),
    ]);
    replace(notice);
  };

  async function load() {
    replace(element('div', { class: 'cf01-loading', role: 'status' }, ['Loading protected clinical content…']));
    try {
      if (app.dataset.view === 'governance') {
        renderHealth(await safeFetch('health'));
      } else {
        renderRecordShell();
      }
    } catch (error) {
      renderError(error);
    }
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && state.controller) state.controller.abort();
  });
  window.addEventListener('pagehide', () => {
    if (state.controller) state.controller.abort();
    content.replaceChildren();
  });

  load();
})();
