(() => {
  'use strict';

  const app = document.querySelector('.cf01-app');
  if (!app || !window.CF01_APP) return;

  const content = app.querySelector('#cf01-content');
  const state = { controller: null, requestId: 0 };
  const isUrdu = String(CF01_APP.locale || '').toLowerCase().startsWith('ur');
  const copy = {
    back: isUrdu ? 'واپس' : 'Back',
    home: isUrdu ? 'ہوم' : 'Home',
    retry: isUrdu ? 'دوبارہ کوشش کریں' : 'Retry',
    unavailable: isUrdu ? 'محفوظ ریکارڈ دستیاب نہیں' : 'Protected record unavailable',
    unavailableDetail: isUrdu
      ? 'درخواست کردہ طبی معلومات دکھائی نہیں جاسکیں۔ ریکارڈ کے وجود کے متعلق کوئی غیر مجاز اطلاع ظاہر نہیں کی گئی۔'
      : 'The requested clinical information could not be displayed. No record existence has been disclosed.',
    workspace: isUrdu ? 'محفوظ طبی ورک اسپیس' : 'Protected clinical workspace',
    workspaceDetail: isUrdu
      ? 'طبی معلومات ہر مرتبہ تازہ اختیار کی جانچ کے بعد حاصل ہوتی ہیں اور اس انٹرفیس میں آف لائن محفوظ نہیں کی جاتیں۔'
      : 'Clinical data is fetched only after click-time authorization and is never stored offline by this interface.',
    myRecord: isUrdu ? 'میرا طبی ریکارڈ' : 'My health record',
    patientRecord: isUrdu ? 'مریض کا طبی ریکارڈ' : 'Patient clinical record',
    encounter: isUrdu ? 'معائنہ / ملاقات' : 'Clinical encounter',
    prescription: isUrdu ? 'نسخہ' : 'Prescription',
    followup: isUrdu ? 'فالو اَپ' : 'Follow-up',
    status: isUrdu ? 'حیثیت' : 'Status',
    jurisdiction: isUrdu ? 'قانونی دائرہ' : 'Jurisdiction',
    created: isUrdu ? 'تاریخِ تخلیق' : 'Created',
    updated: isUrdu ? 'آخری تبدیلی' : 'Updated',
    empty: isUrdu ? 'اس حصے میں کوئی مجاز اندراج موجود نہیں۔' : 'No authorized entries are available in this section.',
    loading: isUrdu ? 'محفوظ طبی معلومات لوڈ ہورہی ہیں…' : 'Loading protected clinical content…',
    systemStatus: isUrdu ? 'طبی نظام کی حالت' : 'Clinical system status',
    open: isUrdu ? 'کھولیں' : 'Open',
    summary: isUrdu ? 'خلاصہ' : 'Summary',
    content: isUrdu ? 'طبی مواد' : 'Clinical content',
    plan: isUrdu ? 'فالو اَپ منصوبہ' : 'Follow-up plan',
    questionnaire: isUrdu ? 'سوال نامہ' : 'Questionnaire',
    privacy: isUrdu ? 'نجی، no-store طبی جگہ؛ ہنگامی علاج اس ویب سائٹ کے ذریعے فراہم نہیں کیا جاتا۔' : 'Private, no-store clinical workspace. Emergency care is not provided through this website.',
  };

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

  const iconPaths = {
    back: ['M15 18l-6-6 6-6', 'M9 12h10'],
    home: ['M3 11l9-8 9 8', 'M5 10v10h14V10', 'M9 20v-6h6v6'],
    record: ['M6 3h9l3 3v15H6z', 'M15 3v4h4', 'M9 12h6', 'M9 16h6'],
    encounter: ['M4 5h16v15H4z', 'M8 3v4', 'M16 3v4', 'M4 9h16'],
    prescription: ['M8 3h8v18H8z', 'M10 7h4', 'M10 11h4', 'M10 15h3'],
    followup: ['M12 6v6l4 2', 'M21 12a9 9 0 1 1-3-6.7'],
    shield: ['M12 3l8 4v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V7z', 'M9 12l2 2 4-4'],
    retry: ['M20 6v6h-6', 'M20 12a8 8 0 1 0-2.3 5.7'],
  };

  const icon = (name) => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    svg.setAttribute('class', 'cf01-icon');
    (iconPaths[name] || iconPaths.record).forEach((d) => {
      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', d);
      svg.append(path);
    });
    return svg;
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

  const routeHref = (type, uuid) => {
    const encoded = encodeURIComponent(String(uuid || ''));
    if (type === 'encounter') return `/clinic/encounters/${encoded}/`;
    if (type === 'prescription') return `/clinic/prescriptions/${encoded}/`;
    if (type === 'followup') return `/clinic/follow-ups/${encoded}/`;
    return '#';
  };

  const navigation = () => {
    const toolbar = element('nav', { class: 'cf01-toolbar', 'aria-label': isUrdu ? 'صفحہ نیویگیشن' : 'Page navigation' });
    const back = element('button', { type: 'button', class: 'cf01-button' }, [icon('back'), copy.back]);
    back.addEventListener('click', () => {
      const referrer = document.referrer;
      let sameOrigin = false;
      try { sameOrigin = Boolean(referrer) && new URL(referrer).origin === window.location.origin; } catch (_) { sameOrigin = false; }
      if (sameOrigin && window.history.length > 1) window.history.back();
      else window.location.assign('/clinic/records/');
    });
    const home = element('a', { class: 'cf01-link-button', href: '/' }, [icon('home'), copy.home]);
    toolbar.append(back, home);
    return toolbar;
  };

  const labeledValue = (label, value) => {
    const dl = element('dl', { class: 'cf01-kv' });
    dl.append(element('dt', {}, [label]), element('dd', {}, [value === '' || value == null ? '—' : String(value)]));
    return dl;
  };

  const valueNode = (value) => {
    if (value == null || value === '') return element('span', { class: 'cf01-empty' }, ['—']);
    if (Array.isArray(value)) {
      if (!value.length) return element('span', { class: 'cf01-empty' }, [copy.empty]);
      const list = element('ul', { class: 'cf01-list' });
      value.forEach((item) => list.append(element('li', { class: 'cf01-list-item' }, [typeof item === 'object' ? objectDetails(item) : String(item)])));
      return list;
    }
    if (typeof value === 'object') return objectDetails(value);
    return element('span', {}, [String(value)]);
  };

  const objectDetails = (object) => {
    const dl = element('dl', { class: 'cf01-kv' });
    Object.entries(object || {}).forEach(([key, value]) => {
      dl.append(element('dt', {}, [key.replaceAll('_', ' ')]), element('dd', {}, [valueNode(value)]));
    });
    return dl;
  };

  const renderError = (error) => {
    if (error?.name === 'AbortError') return;
    const retry = element('button', { type: 'button', class: 'cf01-button' }, [icon('retry'), copy.retry]);
    retry.addEventListener('click', load);
    const box = element('div', { class: 'cf01-state cf01-state--error', role: 'alert' }, [
      element('h2', {}, [copy.unavailable]),
      element('p', {}, [copy.unavailableDetail]),
      retry,
    ]);
    if (error?.traceId) box.append(element('p', { class: 'cf01-trace' }, [`Trace: ${error.traceId}`]));
    replace(navigation(), box);
  };

  const renderHealth = (data) => {
    const list = element('dl', { class: 'cf01-summary' });
    [['Runtime', data.runtime_version], ['Activation', data.activation_state], ['Status', data.status]].forEach(([label, value]) => {
      list.append(element('dt', {}, [label]), element('dd', {}, [value]));
    });
    replace(navigation(), element('section', { class: 'cf01-card' }, [element('h2', {}, [copy.systemStatus]), list]));
  };

  const sectionList = (title, type, rows) => {
    const section = element('section', { class: 'cf01-card' }, [element('h2', {}, [title])]);
    if (!Array.isArray(rows) || !rows.length) {
      section.append(element('p', { class: 'cf01-empty' }, [copy.empty]));
      return section;
    }
    const list = element('ul', { class: 'cf01-list' });
    rows.forEach((row) => {
      const uuidKey = `${type}_uuid`;
      const href = routeHref(type, row[uuidKey]);
      const item = element('li', { class: 'cf01-list-item' });
      item.append(
        element('span', { class: 'cf01-badge' }, [String(row.status || '')]),
        element('p', {}, [String(row.created_at || row.due_at || '')])
      );
      if (href !== '#') item.append(element('a', { href }, [copy.open]));
      list.append(item);
    });
    section.append(list);
    return section;
  };

  const renderPatient = (data, own = false) => {
    const patient = data.patient || {};
    const title = own ? copy.myRecord : copy.patientRecord;
    const summary = element('section', { class: 'cf01-card' }, [
      element('h2', {}, [title]),
      labeledValue(copy.status, patient.status || data.summary?.status),
      labeledValue(copy.jurisdiction, patient.jurisdiction || data.summary?.jurisdiction),
      labeledValue(copy.created, patient.created_at),
      labeledValue(copy.updated, patient.updated_at),
    ]);
    replace(
      navigation(),
      summary,
      sectionList(copy.encounter, 'encounter', data.encounters),
      sectionList(copy.prescription, 'prescription', data.prescriptions),
      sectionList(copy.followup, 'followup', data.followups)
    );
  };

  const renderClinicalObject = (title, iconName, data, bodyKey) => {
    const metadata = data.metadata || {};
    const card = element('section', { class: 'cf01-card' }, [
      element('h2', {}, [icon(iconName), title]),
      objectDetails(metadata),
    ]);
    const body = element('section', { class: 'cf01-card' }, [
      element('h2', {}, [bodyKey === 'order' ? copy.prescription : bodyKey === 'plan' ? copy.plan : copy.content]),
      valueNode(data[bodyKey] || {}),
    ]);
    const nodes = [navigation(), card, body];
    if (bodyKey === 'plan') {
      nodes.push(element('section', { class: 'cf01-card' }, [element('h2', {}, [copy.questionnaire]), valueNode(data.questionnaire || [])]));
    }
    replace(...nodes);
  };

  const renderWorkspace = () => {
    const own = element('a', { class: 'cf01-link-button cf01-button--primary', href: '/my-health-record/' }, [icon('record'), copy.myRecord]);
    const notice = element('section', { class: 'cf01-state', role: 'status' }, [
      element('h2', {}, [icon('shield'), copy.workspace]),
      element('p', {}, [copy.workspaceDetail]),
      element('p', {}, [copy.privacy]),
      own,
    ]);
    replace(navigation(), notice);
  };

  async function load() {
    replace(element('div', { class: 'cf01-loading', role: 'status' }, [copy.loading]));
    try {
      const view = String(app.dataset.view || 'records');
      const object = String(app.dataset.object || '');
      if (view === 'governance') {
        renderHealth(await safeFetch('health'));
      } else if (view === 'my_record') {
        renderPatient(await safeFetch('me?fields=summary,encounters,prescriptions,followups,consents'), true);
      } else if (view === 'patient' && object) {
        renderPatient(await safeFetch(`patients/${encodeURIComponent(object)}?fields=summary,encounters,prescriptions,followups,consents`));
      } else if (view === 'encounter' && object) {
        renderClinicalObject(copy.encounter, 'encounter', await safeFetch(`encounters/${encodeURIComponent(object)}`), 'content');
      } else if (view === 'prescription' && object) {
        renderClinicalObject(copy.prescription, 'prescription', await safeFetch(`prescriptions/${encodeURIComponent(object)}`), 'order');
      } else if (view === 'followup' && object) {
        renderClinicalObject(copy.followup, 'followup', await safeFetch(`followups/${encodeURIComponent(object)}`), 'plan');
      } else {
        renderWorkspace();
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
