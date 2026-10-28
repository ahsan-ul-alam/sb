/* Sellbees dashboard — everything is loaded and mutated over fetch/AJAX. */
const $ = s => document.querySelector(s);
const isAdmin = APP.role === 'admin';
const STATUSES = ['pending', 'reviewing', 'approved', 'processing', 'completed', 'rejected', 'cancelled'];
const money = n => (APP.currency || '$') + Number(n).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
const ago = iso => new Date(iso).toLocaleString([], {dateStyle: 'medium', timeStyle: 'short'});
const csrf = () => $('meta[name=csrf-token]').content;

async function api(url, method = 'GET', body = null) {
  const opts = {method, headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf()}};
  if (body instanceof FormData) opts.body = body;
  else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  const res = await fetch(url, opts);
  if (res.status === 401 || res.status === 419) { location.href = '/login'; throw new Error('auth'); }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const msg = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Request failed');
    toast(msg, 'bad');
    throw new Error(msg);
  }
  return data;
}

function toast(title, kind = '', body = '') {
  const t = document.createElement('div');
  t.className = 'toast ' + kind;
  t.innerHTML = `<b>${esc(title)}</b>${body ? `<p>${esc(body)}</p>` : ''}`;
  $('#toasts').append(t);
  setTimeout(() => t.remove(), 5000);
}

const thumb = p => p.image_url ? `<img src="${esc(p.image_url)}" alt="${esc(p.name)}">` : esc((p.name || '?')[0].toUpperCase());
const statusPill = s => `<span class="st ${esc(s)}">${esc(s)}</span>`;
const itemsHtml = items => items.map(i => `<div class="line"><span>${esc(i.product_name)} × ${i.quantity}</span><b>${money(i.price * i.quantity)}</b></div>`).join('');

/* ---------- modal ---------- */
const modal = {
  open(html) { $('#mbody').innerHTML = html; $('#modal').classList.add('open'); },
  close() { $('#modal').classList.remove('open'); },
};
$('#mx').onclick = modal.close;
$('#modal').onclick = e => { if (e.target.id === 'modal') modal.close(); };

/* ---------- tabs ---------- */
const tabs = isAdmin
  ? [['requests', 'Purchase requests'], ['selections', 'Product selections'], ['products', 'Products'], ['site', 'Landing page & SEO'], ['subscribers', 'Subscribers'], ['maintenance', 'Maintenance']]
  : [['products', 'Products'], ['cart', 'My selection'], ['orders', 'My requests']];
let current = tabs[0][0];
const views = {};

function renderTabs() {
  $('#tabs').innerHTML = tabs.map(([k, l]) => `<button data-k="${k}" class="${k === current ? 'on' : ''}">${l}${k === 'requests' && pendingCount ? `<span class="count">${pendingCount}</span>` : ''}</button>`).join('');
  document.querySelectorAll('#tabs button').forEach(b => b.onclick = () => go(b.dataset.k));
}
async function go(k) {
  current = k; renderTabs();
  $('#view').innerHTML = '<div class="empty">Loading…</div>';
  await views[k]();
}
let pendingCount = 0;

/* =================== USER =================== */
let cartMap = {};

views.products = isAdmin ? adminProducts : userProducts;
async function userProducts() {
  $('#view').innerHTML = `<div class="toolbar"><input id="q" placeholder="Search products…" aria-label="Search"></div><div class="pgrid" id="grid"></div>`;
  let timer;
  const load = async () => {
    const list = await api('/shop/products?q=' + encodeURIComponent($('#q').value));
    list.forEach(p => cartMap[p.id] = p.selected_qty);
    $('#grid').innerHTML = list.length ? list.map(card).join('') : '<div class="empty" style="grid-column:1/-1">No products found.</div>';
  };
  $('#q').oninput = () => { clearTimeout(timer); timer = setTimeout(load, 250); };
  $('#grid').onclick = async e => {
    const el = e.target.closest('[data-act]'); if (!el) return;
    const id = el.dataset.id;
    if (el.dataset.act === 'detail') return showDetail(id);
    const q = (cartMap[id] || 0) + (el.dataset.act === 'plus' ? 1 : -1);
    await setQty(id, Math.max(0, q));
    const prod = el.closest('.prod');
    prod.outerHTML = card({...JSON.parse(prod.dataset.p), selected_qty: cartMap[id]});
  };
  await load();
}
function card(p) {
  const q = p.selected_qty || 0;
  return `<article class="prod ${q ? 'sel' : ''}" data-p='${esc(JSON.stringify(p))}'>
    <div class="thumb" data-act="detail" data-id="${p.id}">${thumb(p)}</div>
    <div class="body"><h3 data-act="detail" data-id="${p.id}">${esc(p.name)}</h3>
      <div class="desc">${esc(p.description)}</div>
      <div class="row"><span class="price">${money(p.price)}</span>
      ${q ? `<span class="qty"><button data-act="minus" data-id="${p.id}">−</button><span>${q}</span><button data-act="plus" data-id="${p.id}">+</button></span>`
          : `<button class="btn sm" data-act="plus" data-id="${p.id}">Select</button>`}
      </div></div></article>`;
}
async function setQty(id, qty) {
  const res = await api('/shop/selections/' + id, 'PUT', {quantity: qty});
  cartMap = {}; res.items.forEach(i => cartMap[i.product.id] = i.quantity);
  return res;
}
async function showDetail(id) {
  const p = await api('/shop/products/' + id);
  const q = cartMap[id] || 0;
  modal.open(`<div class="thumb big">${thumb(p)}</div><div class="pad"><h2>${esc(p.name)}</h2>
    <p class="price" style="margin:8px 0">${money(p.price)}</p>
    <p style="color:var(--muted);white-space:pre-line">${esc(p.description || 'No description provided.')}</p>
    <div class="actions"><button class="btn" id="dsel">${q ? `Selected (${q}) — add one more` : 'Select this product'}</button>${q ? '<button class="btn ghost" id="drem">Remove</button>' : ''}</div></div>`);
  $('#dsel').onclick = async () => { await setQty(id, (cartMap[id] || 0) + 1); toast('Added to your selection'); modal.close(); if (current === 'products') go('products'); };
  if ($('#drem')) $('#drem').onclick = async () => { await setQty(id, 0); modal.close(); if (current === 'products') go('products'); };
}

views.cart = async function () {
  const draw = res => {
    if (!res.items.length) { $('#view').innerHTML = '<div class="empty">You have not selected any products yet.<br><br><button class="btn" id="browse">Browse products</button></div>'; $('#browse').onclick = () => go('products'); return; }
    $('#view').innerHTML = `<div class="panel">${res.items.map(r => `<div class="line"><div class="t"><div class="mt">${thumb(r.product)}</div><div><b>${esc(r.product.name)}</b><br><small>${money(r.product.price)} each</small></div></div>
      <div style="display:flex;gap:14px;align-items:center"><span class="qty"><button data-id="${r.product.id}" data-d="-1">−</button><span>${r.quantity}</span><button data-id="${r.product.id}" data-d="1">+</button></span><b>${money(r.product.price * r.quantity)}</b></div></div>`).join('')}
      <div class="total"><span>Total</span><span>${money(res.total)}</span></div>
      <label for="note">Note to Sellbees (optional)</label><textarea id="note" rows="3" placeholder="Anything we should know?"></textarea>
      <div class="actions"><button class="btn" id="send">Request purchase</button></div></div>`;
    $('#view').querySelectorAll('.qty button').forEach(b => b.onclick = async () => {
      const id = b.dataset.id; draw(await setQty(id, Math.max(0, (cartMap[id] || 0) + Number(b.dataset.d))));
    });
    $('#send').onclick = async e => {
      e.target.disabled = true;
      try { await api('/shop/requests', 'POST', {note: $('#note').value}); cartMap = {}; toast('Purchase request sent!', '', 'We will notify you as it progresses.'); go('orders'); }
      finally { e.target.disabled = false; }
    };
  };
  draw(await api('/shop/selections'));
};

views.orders = async function () {
  const list = await api('/shop/requests');
  $('#view').innerHTML = list.length ? list.map(r => `<div class="panel" data-id="${r.id}">
    <div class="meta"><b>Request #${r.id}</b>${statusPill(r.status)}<small>${ago(r.created_at)}</small></div>
    ${itemsHtml(r.items)}<div class="total"><span>Total</span><span>${money(r.total)}</span></div>
    ${r.note ? `<p><small>Your note: ${esc(r.note)}</small></p>` : ''}
    ${r.admin_note ? `<p style="margin-top:6px"><b>Sellbees:</b> ${esc(r.admin_note)}</p>` : ''}
    ${r.status === 'pending' ? `<div class="actions"><button class="btn danger sm" data-cancel="${r.id}">Cancel request</button></div>` : ''}</div>`).join('')
    : '<div class="empty">No purchase requests yet.</div>';
  $('#view').querySelectorAll('[data-cancel]').forEach(b => b.onclick = async () => {
    if (!confirm('Cancel this request?')) return;
    await api(`/shop/requests/${b.dataset.cancel}/cancel`, 'POST'); toast('Request cancelled'); go('orders');
  });
};

/* =================== ADMIN =================== */
views.requests = async function () {
  const filter = views.requests.filter || '';
  const [stats, list] = await Promise.all([api('/admin/stats'), api('/admin/requests?status=' + filter)]);
  pendingCount = stats.pending; renderTabs();
  $('#view').innerHTML = `<div class="kpis"><div class="kpi"><b>${stats.pending}</b><span>Pending requests</span></div><div class="kpi"><b>${stats.requests}</b><span>Total requests</span></div>
    <div class="kpi"><b>${stats.selections}</b><span>Users with selections</span></div><div class="kpi"><b>${stats.products}</b><span>Products</span></div></div>
    <div class="toolbar" style="margin-top:14px"><select id="flt"><option value="">All statuses</option>${STATUSES.map(s => `<option ${s === filter ? 'selected' : ''}>${s}</option>`).join('')}</select></div>
    <div id="rl">${list.length ? list.map(reqCard).join('') : '<div class="empty">No requests.</div>'}</div>`;
  $('#flt').onchange = e => { views.requests.filter = e.target.value; views.requests(); };
  $('#rl').querySelectorAll('[data-save]').forEach(b => b.onclick = async () => {
    const box = b.closest('.panel'); b.disabled = true;
    try {
      const r = await api('/admin/requests/' + b.dataset.save, 'PUT', {status: box.querySelector('select').value, admin_note: box.querySelector('input').value});
      box.outerHTML = reqCard(r); toast(`Request #${r.id} marked ${r.status}`); views.requests();
    } finally { b.disabled = false; }
  });
};
function reqCard(r) {
  return `<div class="panel"><div class="meta"><b>#${r.id} · ${esc(r.user.name)}</b>${statusPill(r.status)}<small>${esc(r.user.email)} · ${ago(r.created_at)}</small></div>
    ${itemsHtml(r.items)}<div class="total"><span>Total</span><span>${money(r.total)}</span></div>
    ${r.note ? `<p><small>Customer note: ${esc(r.note)}</small></p>` : ''}
    <div class="actions"><select>${STATUSES.map(s => `<option ${s === r.status ? 'selected' : ''}>${s}</option>`).join('')}</select>
    <input placeholder="Message to customer (optional)" value="${esc(r.admin_note)}"><button class="btn sm" data-save="${r.id}">Update</button></div></div>`;
}

views.selections = async function () {
  const list = await api('/admin/selections');
  $('#view').innerHTML = list.length ? list.map(s => `<div class="panel"><div class="meta"><b>${esc(s.user.name)}</b><small>${esc(s.user.email)} · updated ${ago(s.updated_at)}</small></div>
    ${s.items.map(i => `<div class="line"><span>${esc(i.product.name)} × ${i.quantity}</span><b>${money(i.product.price * i.quantity)}</b></div>`).join('')}
    <div class="total"><span>Selection value</span><span>${money(s.total)}</span></div></div>`).join('')
    : '<div class="empty">No users have products selected right now.</div>';
};

async function adminProducts() {
  const list = await api('/admin/products');
  $('#view').innerHTML = `<div class="toolbar"><b>${list.length} product(s)</b><button class="btn" id="add">+ Add product</button></div>
    <div class="panel" style="overflow:auto"><table><thead><tr><th></th><th>Name</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody>
    ${list.map(p => `<tr><td><div class="mt line" style="padding:0;border:0;width:44px"><div class="mt">${thumb(p)}</div></div></td><td>${esc(p.name)}</td><td>${money(p.price)}</td><td>${p.is_active ? statusPill('approved').replace('approved', 'active') : statusPill('cancelled').replace('cancelled', 'hidden')}</td>
    <td style="white-space:nowrap"><button class="btn ghost sm" data-edit="${p.id}">Edit</button> <button class="btn danger sm" data-del="${p.id}">Delete</button></td></tr>`).join('')}
    </tbody></table></div>`;
  $('#add').onclick = () => productForm();
  $('#view').querySelectorAll('[data-edit]').forEach(b => b.onclick = () => productForm(list.find(p => p.id == b.dataset.edit)));
  $('#view').querySelectorAll('[data-del]').forEach(b => b.onclick = async () => {
    if (!confirm('Delete this product?')) return;
    await api('/admin/products/' + b.dataset.del, 'DELETE'); toast('Product deleted'); go('products');
  });
}
function productForm(p = null) {
  modal.open(`<div class="pad"><h2>${p ? 'Edit' : 'Add'} product</h2><form id="pf">
    <label>Name</label><input name="name" required value="${esc(p?.name)}">
    <label>Description</label><textarea name="description" rows="4">${esc(p?.description)}</textarea>
    <label>Price (USD)</label><input name="price" type="number" step="0.01" min="0" required value="${p ? p.price : ''}">
    <label>Image ${p?.image_url ? '(leave empty to keep current)' : ''}</label><input name="image" type="file" accept="image/*">
    <label class="check"><input type="checkbox" name="is_active" value="1" ${!p || p.is_active ? 'checked' : ''}> Visible to users</label>
    <div class="actions" style="margin-top:20px"><button class="btn">${p ? 'Save changes' : 'Create product'}</button></div></form></div>`);
  $('#pf').onsubmit = async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if (!fd.has('is_active')) fd.set('is_active', '0');
    const btn = e.target.querySelector('.btn'); btn.disabled = true;
    try {
      await api(p ? '/admin/products/' + p.id : '/admin/products', 'POST', fd);
      modal.close(); toast(p ? 'Product updated' : 'Product created'); go('products');
    } finally { btn.disabled = false; }
  };
}

/* ---------- landing page editor (schema-driven) ---------- */
let site = null, siteSel = 'g:visibility';

views.site = async function () { site = await api('/admin/site'); drawSite(); };

function drawSite() {
  const nav = (id, label, extra = '') => `<button data-s="${id}" class="${siteSel === id ? 'on' : ''}">${esc(label)}${extra}</button>`;
  $('#view').innerHTML = `<div class="site"><aside class="snav">
    <h4>Settings</h4>${Object.entries(site.groups).map(([k, g]) => nav('g:' + k, g.label)).join('')}
    <h4>Content lists</h4>${Object.entries(site.types).map(([k, t]) => nav('t:' + k, t.label, ` <small>${(site.items[k] || []).length}</small>`)).join('')}
    <a class="btn ghost sm" href="/" target="_blank" rel="noopener">View landing page ↗</a></aside><div class="spane" id="spane"></div></div>`;
  $('#view').querySelectorAll('.snav button').forEach(b => b.onclick = () => { siteSel = b.dataset.s; drawSite(); });
  const [kind, key] = siteSel.split(':');
  kind === 'g' ? drawGroup(key) : drawItems(key);
}

function fieldHtml(f, val) {
  const n = esc(f.key), label = `<label for="f_${n}">${esc(f.label)}</label>`, v = val ?? '';
  switch (f.type) {
    case 'textarea': return `${label}<textarea id="f_${n}" name="${n}" rows="3">${esc(v)}</textarea>`;
    case 'number': return `${label}<input id="f_${n}" name="${n}" type="number" step="any" min="0" value="${esc(v)}">`;
    case 'color': return `${label}<input id="f_${n}" name="${n}" type="color" value="${esc(v || '#f5a300')}" style="height:44px;padding:4px">`;
    case 'select': return `${label}<select id="f_${n}" name="${n}">${Object.entries(f.options).map(([k, l]) => `<option value="${esc(k)}" ${String(v) === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
    case 'bool': return `<label class="check"><input type="checkbox" name="${n}" value="1" ${v === true || v === 1 || v === '1' ? 'checked' : ''}> ${esc(f.label)}</label>`;
    case 'image': return `${label}${v ? `<div style="display:flex;gap:12px;align-items:center;margin-bottom:8px"><img src="${esc(site.image_base + v)}" alt="" style="height:56px;border-radius:8px;border:1px solid var(--line)"><label class="check" style="margin:0"><input type="checkbox" name="${n}_remove" value="1"> Remove</label></div>` : ''}<input id="f_${n}" name="${n}" type="file" accept="image/*">`;
    default: return `${label}<input id="f_${n}" name="${n}" value="${esc(v)}" ${f.required ? 'required' : ''}>`;
  }
}

function drawGroup(key) {
  const g = site.groups[key], vals = site.settings[key];
  if (key === 'visibility') {
    // on/off switches: each change saves immediately
    $('#spane').innerHTML = `<form id="sf" class="panel"><h2>${esc(g.label)}</h2><p style="color:var(--muted)">${esc(g.hint)}</p>
      <div class="togs">${g.fields.map(f => `<label class="tog"><span>${esc(f.label)}</span><input type="checkbox" name="${esc(f.key)}" value="1" ${vals[f.key] ? 'checked' : ''}><i></i></label>`).join('')}</div></form>`;
    $('#sf').onchange = async e => {
      const box = e.target;
      try { site = await api('/admin/site/settings/visibility', 'POST', new FormData($('#sf'))); toast(`${box.closest('.tog').firstChild.textContent} is now ${box.checked ? 'visible' : 'hidden'}`); }
      catch { box.checked = !box.checked; }
    };
    return;
  }
  $('#spane').innerHTML = `<form id="sf" class="panel"><h2>${esc(g.label)}</h2>${g.hint ? `<p style="color:var(--muted)">${esc(g.hint)}</p>` : ''}
    <div class="fgrid">${g.fields.map(f => `<div class="${f.type === 'textarea' ? 'full' : ''}">${fieldHtml(f, vals[f.key])}</div>`).join('')}</div>
    <div class="actions" style="margin-top:20px"><button class="btn">Save changes</button></div></form>`;
  $('#sf').onsubmit = async e => {
    e.preventDefault(); const b = e.target.querySelector('.btn'); b.disabled = true;
    try { site = await api('/admin/site/settings/' + key, 'POST', new FormData(e.target)); toast('Saved — landing page updated'); drawSite(); }
    finally { b.disabled = false; }
  };
}

function drawItems(type) {
  const t = site.types[type], list = site.items[type] || [];
  $('#spane').innerHTML = `<div class="panel"><div class="toolbar"><h2>${esc(t.label)}</h2><button class="btn" id="addi">+ Add</button></div>
    ${list.length ? `<div style="overflow:auto"><table><thead><tr><th>Order</th><th>${esc(t.fields.find(f => f.key === t.title).label)}</th><th>Status</th><th></th></tr></thead><tbody>
    ${list.map(i => `<tr><td>${i.sort}</td><td>${esc(i[t.title])}</td><td>${i.is_active ? '<span class="st approved">visible</span>' : '<span class="st cancelled">hidden</span>'}</td>
    <td style="white-space:nowrap"><button class="btn ghost sm" data-e="${i.id}">Edit</button> <button class="btn danger sm" data-d="${i.id}">Delete</button></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Nothing here yet.</div>'}</div>`;
  $('#addi').onclick = () => itemForm(type);
  $('#spane').querySelectorAll('[data-e]').forEach(b => b.onclick = () => itemForm(type, list.find(i => i.id == b.dataset.e)));
  $('#spane').querySelectorAll('[data-d]').forEach(b => b.onclick = async () => {
    if (!confirm('Delete this item?')) return;
    site = await api(`/admin/site/items/${type}/${b.dataset.d}`, 'DELETE'); toast('Deleted'); drawSite();
  });
}

function itemForm(type, item = null) {
  const t = site.types[type];
  modal.open(`<div class="pad"><h2>${item ? 'Edit' : 'Add'} — ${esc(t.label)}</h2><form id="itf">
    ${t.fields.map(f => fieldHtml(f, item ? item[f.key] : f.default)).join('')}
    <label for="f_sort">Order (lower shows first)</label><input id="f_sort" name="sort" type="number" min="0" value="${item ? item.sort : (list => list.length ? Math.max(...list.map(i => i.sort)) + 1 : 1)(site.items[type] || [])}">
    <label class="check"><input type="checkbox" name="is_active" value="1" ${!item || item.is_active ? 'checked' : ''}> Visible on the landing page</label>
    <div class="actions" style="margin-top:20px"><button class="btn">${item ? 'Save changes' : 'Add'}</button></div></form></div>`);
  $('#itf').onsubmit = async e => {
    e.preventDefault(); const b = e.target.querySelector('.btn'); b.disabled = true;
    try { site = await api(`/admin/site/items/${type}${item ? '/' + item.id : ''}`, 'POST', new FormData(e.target)); modal.close(); toast('Saved — landing page updated'); drawSite(); }
    finally { b.disabled = false; }
  };
}

views.subscribers = async function () {
  const list = await api('/admin/subscribers');
  $('#view').innerHTML = `<div class="panel"><div class="toolbar"><h2>Newsletter subscribers (${list.length})</h2></div>${list.length ? `<div style="overflow:auto"><table><thead><tr><th>Email</th><th>Subscribed</th><th></th></tr></thead><tbody>
    ${list.map(s => `<tr><td>${esc(s.email)}</td><td>${ago(s.created_at)}</td><td><button class="btn danger sm" data-d="${s.id}">Remove</button></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">No subscribers yet.</div>'}</div>`;
  $('#view').querySelectorAll('[data-d]').forEach(b => b.onclick = async () => { await api('/admin/subscribers/' + b.dataset.d, 'DELETE'); toast('Removed'); views.subscribers(); });
};

views.maintenance = function () {
  const tasks = [
    ['key', 'Generate app key', 'Only if APP_KEY is empty — an existing key is never overwritten'],
    ['cache', 'Clear all cache', 'Config, route, view and landing-page cache'],
    ['seed', 'Seed starter data', 'Landing content, demo accounts and products — only fills what is missing'],
    ['storage', 'Storage link', 'Create public/storage so uploaded images are served'],
    ['migrate', 'Run migrations', 'Apply new database changes (--force)'],
    ['composer', 'Composer install', 'Install PHP dependencies (can take a few minutes)'],
    ['artisan', 'App key + migrate + storage link + clear cache', 'Artisan calls only (no composer) — the usual deploy step'],
    ['all', 'Run everything', 'composer → app key → migrate → storage link → clear cache'],
  ];
  $('#view').innerHTML = `<div class="panel"><h2>Maintenance</h2><p style="color:var(--muted)">Run server tasks without SSH. Each task stops on the first error.</p>
    <div class="pgrid" style="margin-top:14px">${tasks.map(([k, l, d]) => `<div class="panel" style="margin:0"><b>${l}</b><p style="color:var(--muted);font-size:.88rem;margin:4px 0 12px">${d}</p><button class="btn ${k === 'artisan' ? '' : 'ghost'} sm" data-t="${k}">Run</button></div>`).join('')}</div>
    <pre id="mout" style="display:none;margin-top:16px;background:var(--dark);color:#e8dfc8;padding:16px;border-radius:12px;max-height:380px;overflow:auto;white-space:pre-wrap;font-size:.82rem"></pre></div>`;
  $('#view').querySelectorAll('[data-t]').forEach(b => b.onclick = async () => {
    if (b.dataset.t === 'composer' || b.dataset.t === 'all') if (!confirm('This replaces files in /vendor and may take a few minutes. Continue?')) return;
    const out = $('#mout'); out.style.display = 'block'; out.textContent = 'Running…';
    document.querySelectorAll('[data-t]').forEach(x => x.disabled = true);
    try {
      const res = await fetch('/admin/maintenance/' + b.dataset.t, {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf()}});
      const d = await res.json().catch(() => ({message: 'Server error (' + res.status + ')'}));
      out.textContent = d.results ? d.results.map(r => `$ ${r.task} — ${r.ok ? 'OK' : 'FAILED'}\n${r.output}`).join('\n\n') : (d.message || 'Failed');
      toast(d.ok ? 'Done' : 'Finished with errors', d.ok ? '' : 'bad');
    } finally { document.querySelectorAll('[data-t]').forEach(x => x.disabled = false); }
  });
};

/* =================== NOTIFICATIONS (polling) =================== */
let lastUnread = null, seen = new Set();
async function pollNotifications(first = false) {
  try {
    const res = await fetch('/notifications', {headers: {'Accept': 'application/json'}});
    if (res.status === 401) return location.href = '/login';
    const data = await res.json();
    $('#bellN').textContent = data.unread; $('#bellN').hidden = !data.unread;
    $('#ddList').innerHTML = data.items.length ? data.items.map(n => `<div class="nt ${n.read ? '' : 'unread'}"><b>${esc(n.title)}</b><p>${esc(n.body)}</p><small>${ago(n.created_at)}</small></div>`).join('') : '<div class="empty">Nothing yet.</div>';
    data.items.filter(n => !n.read && !seen.has(n.id)).forEach(n => { if (!first) toast(n.title, '', n.body); });
    data.items.forEach(n => seen.add(n.id));
    // a new notification means data changed: refresh the live list in place
    if (!first && lastUnread !== null && data.unread > lastUnread) {
      if (isAdmin && current === 'requests') views.requests();
      if (!isAdmin && current === 'orders') views.orders();
      if (isAdmin) { const s = await api('/admin/stats'); pendingCount = s.pending; renderTabs(); }
    }
    lastUnread = data.unread;
  } catch (e) { /* ignore transient errors */ }
}
$('#bell').onclick = e => { e.stopPropagation(); $('#dd').classList.toggle('open'); };
document.addEventListener('click', e => { if (!e.target.closest('#dd')) $('#dd').classList.remove('open'); });
$('#readAll').onclick = async () => { await api('/notifications/read', 'POST'); lastUnread = 0; pollNotifications(true); };
$('#logout').onclick = async () => { const r = await api('/logout', 'POST'); location.href = r.redirect; };

/* ---------- boot ---------- */
renderTabs();
go(current);
pollNotifications(true);
setInterval(pollNotifications, 8000);
