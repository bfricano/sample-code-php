/* ═══════════════════════════════════════════════════════════════
   APEX Trader — Main SPA
   ═══════════════════════════════════════════════════════════════ */

'use strict';

// ─── API CLIENT ─────────────────────────────────────────────────────────────
const API = {
  base: '/api',
  async req(method, path, body = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const res  = await fetch(this.base + path, opts);
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'API error');
    return json.data;
  },
  get: (p, q = {}) => {
    const qs = new URLSearchParams(q).toString();
    return API.req('GET', p + (qs ? '?' + qs : ''));
  },
  post:   (p, b) => API.req('POST',   p, b),
  put:    (p, b) => API.req('PUT',    p, b),
  delete: (p)    => API.req('DELETE', p),
};

// ─── GLOBAL STATE ───────────────────────────────────────────────────────────
const State = {
  currentView:  'dashboard',
  dashboardData: null,
  tradesPage:   1,
  tradesPeriod: 'ALL',
  analyticsPeriod: 'ALL',
  charts: {},
};

// ─── UTILITIES ──────────────────────────────────────────────────────────────
const fmt = {
  dollar: (v, always = false) => {
    const n = parseFloat(v) || 0;
    const s = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 }).format(Math.abs(n));
    if (n > 0) return always ? `+${s}` : s;
    if (n < 0) return `-${s}`;
    return s;
  },
  pct:  (v, dp = 2) => `${parseFloat(v || 0) >= 0 ? '+' : ''}${parseFloat(v || 0).toFixed(dp)}%`,
  num:  (v, dp = 2) => parseFloat(v || 0).toFixed(dp),
  r:    (v) => {
    const n = parseFloat(v || 0);
    const s = (n >= 0 ? '+' : '') + n.toFixed(2) + 'R';
    const cls = n > 0 ? 'r-pos' : n < 0 ? 'r-neg' : 'r-zero';
    return `<span class="r-chip ${cls}">${s}</span>`;
  },
  pnlClass: (v) => parseFloat(v) > 0 ? 'pos' : parseFloat(v) < 0 ? 'neg' : 'neu',
  date: (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: '2-digit' }) : '—',
};

function toast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  const icons = { success: '✓', error: '✕', info: 'ℹ' };
  el.innerHTML = `<span style="font-size:16px;line-height:1">${icons[type]}</span><span>${msg}</span>`;
  document.getElementById('toast-container').append(el);
  setTimeout(() => el.remove(), 3500);
}

function openModal(title, html) {
  document.getElementById('modal-title').textContent = title;
  document.getElementById('modal-body').innerHTML = html;
  document.getElementById('modal-overlay').classList.remove('hidden');
}
function closeModal() { document.getElementById('modal-overlay').classList.add('hidden'); }

function destroyChart(key) {
  if (State.charts[key]) { State.charts[key].destroy(); delete State.charts[key]; }
}

// ─── CLOCK & SESSION ────────────────────────────────────────────────────────
function startClock() {
  const tick = () => {
    const now = new Date();
    document.getElementById('clock').textContent =
      now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'America/New_York' }) + ' ET';
    updateSessionBadge(now);
  };
  tick();
  setInterval(tick, 1000);
}

function updateSessionBadge(now) {
  const badge = document.getElementById('market-session');
  const et = new Date(now.toLocaleString('en-US', { timeZone: 'America/New_York' }));
  const h  = et.getHours(), m = et.getMinutes();
  const min = h * 60 + m;
  const dow = et.getDay();

  if (dow === 0 || dow === 6) {
    badge.textContent = 'WEEKEND'; badge.className = 'session-badge session-closed'; return;
  }
  if (min >= 240 && min < 390) {
    badge.textContent = 'PRE-MARKET'; badge.className = 'session-badge session-pre';
  } else if (min >= 390 && min < 960) {
    badge.textContent = '● MARKET OPEN'; badge.className = 'session-badge session-market';
  } else if (min >= 960 && min < 1200) {
    badge.textContent = 'AFTER-HOURS'; badge.className = 'session-badge session-after';
  } else {
    badge.textContent = 'MARKET CLOSED'; badge.className = 'session-badge session-closed';
  }
}

// ─── NAVIGATION ─────────────────────────────────────────────────────────────
function showView(name) {
  State.currentView = name;
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('view-' + name)?.classList.add('active');
  document.querySelector(`[data-view="${name}"]`)?.classList.add('active');

  const titles = {
    dashboard: 'Command Center', journal: 'Trade Journal',
    resilience: 'Resilience Lab', allocator: 'Capital Allocator',
    risk: 'Risk Control', analytics: 'Analytics', settings: 'Settings',
  };
  document.getElementById('view-title').textContent = titles[name] || name;

  const loaders = {
    dashboard:  loadDashboard,  journal:   loadJournal,
    resilience: loadResilience, allocator: loadAllocator,
    risk:       loadRisk,       analytics: loadAnalytics,
    settings:   loadSettings,
  };
  loaders[name]?.();
}

document.querySelectorAll('.nav-item').forEach(el => {
  el.addEventListener('click', e => { e.preventDefault(); showView(el.dataset.view); });
});

// ─── ALERTS ─────────────────────────────────────────────────────────────────
async function loadAlerts() {
  try {
    const alerts = await API.get('/alerts');
    const badge  = document.getElementById('alert-count');
    badge.textContent = alerts.length;
    alerts.length > 0 ? badge.classList.remove('hidden') : badge.classList.add('hidden');

    if (alerts.length === 0) {
      openModal('Alerts', '<div class="empty-state"><div class="empty-icon">🔔</div><p>No unread alerts</p></div>');
      return;
    }
    const html = alerts.map(a => `
      <div class="alert-card alert-${a.severity.toLowerCase()}" style="margin-bottom:8px">
        <div class="alert-title">${a.title}</div>
        <div class="alert-msg">${a.message}</div>
        <div style="margin-top:6px;font-size:10px;color:var(--text-muted)">${a.created_at}</div>
      </div>
    `).join('');
    openModal(`Alerts (${alerts.length})`, html);
  } catch (e) { toast(e.message, 'error'); }
}

async function refreshAlertCount() {
  try {
    const alerts = await API.get('/alerts');
    const badge  = document.getElementById('alert-count');
    badge.textContent = alerts.length;
    alerts.length > 0 ? badge.classList.remove('hidden') : badge.classList.add('hidden');
  } catch {}
}

// ══════════════════════════════════════════════════════════════════════════════
// DASHBOARD
// ══════════════════════════════════════════════════════════════════════════════
async function loadDashboard() {
  const view = document.getElementById('view-dashboard');
  view.innerHTML = `<div class="loading"><div class="spinner"></div>Loading command center…</div>`;
  try {
    const d = await API.get('/dashboard');
    State.dashboardData = d;
    renderDashboard(d);
    checkHaltBadge(d.risk);
  } catch (e) { view.innerHTML = `<div class="loading">${e.message}</div>`; }
}

function checkHaltBadge(risk) {
  const badge = document.getElementById('halt-badge');
  if (risk?.halt_trading?.halt) {
    badge.classList.remove('hidden');
  } else {
    badge.classList.add('hidden');
  }
}

function renderDashboard(d) {
  const risk     = d.risk || {};
  const res      = d.resilience || {};
  const gate     = res.gate || {};
  const trader   = d.trader || {};
  const ytd      = d.ytd || {};
  const view     = document.getElementById('view-dashboard');

  const todayPnlClass = fmt.pnlClass(risk.today_pnl);
  const weekPnlClass  = fmt.pnlClass(risk.week_pnl);
  const monthPnlClass = fmt.pnlClass(risk.month_pnl);
  const ddPct         = parseFloat(risk.drawdown_pct || 0);
  const dailyLossPct  = parseFloat(risk.daily_loss_pct || 0);
  const dailyLimit    = parseFloat(risk.daily_limit || 3);

  view.innerHTML = `
    <!-- TOP STATS ROW -->
    <div class="grid-4 mb-14">
      ${statCard('ACCOUNT EQUITY', fmt.dollar(risk.equity), `Peak: ${fmt.dollar(risk.peak_equity)}`, 'accent')}
      ${statCard('TODAY P&L', fmt.dollar(risk.today_pnl, true), fmt.pct(risk.today_pnl_pct), todayPnlClass)}
      ${statCard('WEEK P&L', fmt.dollar(risk.week_pnl, true), 'This week', weekPnlClass)}
      ${statCard('YTD P&L', fmt.dollar(risk.month_pnl, true), 'Month to date', monthPnlClass)}
    </div>

    <div class="grid-3 mb-14">
      <!-- RESILIENCE CARD -->
      <div class="card card-accent">
        <div class="card-header">
          <span class="card-title">Mental Resilience</span>
          <button class="btn btn-ghost btn-sm" onclick="showView('resilience')">Check In</button>
        </div>
        <div class="resilience-ring-wrap">
          <div class="resilience-ring">
            <svg viewBox="0 0 120 120" width="120" height="120">
              <circle class="ring-bg" cx="60" cy="60" r="52"/>
              <circle class="ring-fill" id="dash-ring-fill" cx="60" cy="60" r="52"
                stroke-dasharray="326.7" stroke-dashoffset="326.7"/>
            </svg>
            <div class="ring-label">
              <span class="ring-score" id="dash-res-score" style="color:${gate.color || '#888'}">${Math.round(res.score || 0)}</span>
              <span class="ring-level">${gate.level || '—'}</span>
            </div>
          </div>
          <div style="text-align:center;max-width:160px">
            <div style="font-size:11px;color:${gate.color || 'var(--text-secondary)'};">${gate.message || 'No check-in today'}</div>
          </div>
        </div>
      </div>

      <!-- RISK GAUGE -->
      <div class="card card-${ddPct > 7 ? 'red' : ddPct > 3 ? 'amber' : 'green'}">
        <div class="card-header">
          <span class="card-title">Risk Status</span>
          <button class="btn btn-ghost btn-sm" onclick="showView('risk')">Manage</button>
        </div>
        <div>
          <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:8px">
            <div>
              <div class="stat-val mono" style="color:${ddPct > 7 ? 'var(--red)' : ddPct > 3 ? 'var(--amber)' : 'var(--green)'}">${fmt.num(ddPct, 1)}%</div>
              <div class="stat-label">Current Drawdown</div>
            </div>
            <div style="text-align:right">
              <div class="stat-val" style="font-size:16px">${risk.today_trades || 0}</div>
              <div class="stat-label">Trades Today</div>
            </div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted);margin-bottom:3px">
              <span>Daily Loss</span><span>${fmt.num(dailyLossPct, 1)}% / ${dailyLimit}%</span>
            </div>
            <div class="gauge-bar">
              <div class="gauge-fill" style="width:${Math.min(100, dailyLossPct / dailyLimit * 100)}%;background:${dailyLossPct >= dailyLimit ? 'var(--red)' : dailyLossPct >= dailyLimit * 0.8 ? 'var(--amber)' : 'var(--green)'}"></div>
            </div>
          </div>
          ${(risk.violations?.length > 0) ? `
          <div style="margin-top:8px">
            ${risk.violations.map(v => `<div class="alert-card alert-critical" style="padding:6px 10px;font-size:11px">⛔ ${v.rule_name}</div>`).join('')}
          </div>` : `<div style="margin-top:8px;font-size:11px;color:var(--green)">✓ All rules within limits</div>`}
        </div>
      </div>

      <!-- YTD PERFORMANCE -->
      <div class="card card-purple">
        <div class="card-header">
          <span class="card-title">YTD Performance</span>
          <button class="btn btn-ghost btn-sm" onclick="showView('analytics')">Analyze</button>
        </div>
        <div class="grid-2" style="gap:10px;margin-bottom:10px">
          <div>
            <div class="stat-val mono" style="font-size:20px">${ytd.trades || 0}</div>
            <div class="stat-label">Total Trades</div>
          </div>
          <div>
            <div class="stat-val mono ${ytd.pnl >= 0 ? 'pos' : 'neg'}" style="font-size:20px">${fmt.dollar(ytd.pnl, true)}</div>
            <div class="stat-label">Net P&L</div>
          </div>
        </div>
        <div>
          <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted);margin-bottom:3px">
            <span>Win Rate</span><span>${ytd.win_rate || 0}%</span>
          </div>
          <div class="gauge-bar">
            <div class="gauge-fill" style="width:${ytd.win_rate || 0}%;background:var(--purple)"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- EQUITY CURVE -->
    <div class="card mb-14">
      <div class="card-header">
        <span class="card-title">Equity Curve (90 Days)</span>
        <div class="pill-tabs">
          <button class="pill-tab" onclick="loadDashboard()">Refresh</button>
        </div>
      </div>
      <div class="chart-wrap" style="height:200px">
        <canvas id="dash-equity-chart"></canvas>
      </div>
    </div>

    <div class="grid-2">
      <!-- RECENT TRADES -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Trades</span>
          <button class="btn btn-primary btn-sm" onclick="openTradeForm()">+ New Trade</button>
        </div>
        <table class="data-table">
          <thead><tr>
            <th>Date</th><th>Instrument</th><th>Setup</th><th>Dir</th><th>R-Mult</th><th>P&L</th>
          </tr></thead>
          <tbody>
            ${(d.recent_trades || []).slice(0, 8).map(t => `
              <tr style="cursor:pointer" onclick="editTrade(${t.id})">
                <td class="mono">${fmt.date(t.trade_date)}</td>
                <td><b>${t.instrument}</b></td>
                <td><span class="badge-setup">${t.setup_type || '—'}</span></td>
                <td><span class="badge-${t.direction?.toLowerCase()}">${t.direction}</span></td>
                <td>${fmt.r(t.r_multiple)}</td>
                <td class="${fmt.pnlClass(t.pnl)}" style="font-family:var(--font-mono);font-weight:600">${fmt.dollar(t.pnl, true)}</td>
              </tr>
            `).join('') || '<tr><td colspan="6" class="empty-state">No trades yet</td></tr>'}
          </tbody>
        </table>
        <div style="margin-top:10px;text-align:right">
          <button class="btn btn-ghost btn-sm" onclick="showView('journal')">View All Trades →</button>
        </div>
      </div>

      <!-- BEHAVIORAL ALERTS -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Behavioral Intelligence</span>
        </div>
        ${(d.behaviors || []).length > 0
          ? d.behaviors.map(b => `
            <div class="alert-card alert-${b.severity >= 4 ? 'critical' : b.severity >= 3 ? 'warning' : 'info'}">
              <div class="alert-title">
                <span class="sev-dot sev-${b.severity}" style="margin-right:6px"></span>
                ${b.title}
              </div>
              <div class="alert-msg">${b.message}</div>
              <div class="alert-action">${b.action}</div>
            </div>
          `).join('')
          : `<div class="alert-card alert-success">
              <div class="alert-title">✓ Clean Behavioral Profile</div>
              <div class="alert-msg">No behavioral patterns detected in recent trading. Maintain discipline.</div>
            </div>`
        }
      </div>
    </div>
  `;

  // Animate resilience ring
  setTimeout(() => {
    const fill = document.getElementById('dash-ring-fill');
    if (fill) {
      const circ = 2 * Math.PI * 52;
      const score = parseFloat(res.score || 0);
      const offset = circ - (score / 100) * circ;
      fill.style.stroke = gate.color || '#888';
      fill.style.strokeDashoffset = offset;
    }
  }, 50);

  // Equity chart
  renderEquityChart('dash-equity-chart', d.equity_curve || []);
}

function statCard(label, val, sub, colorClass = '') {
  const cls = colorClass === 'pos' ? 'pos' : colorClass === 'neg' ? 'neg' : colorClass === 'accent' ? 'acc' : colorClass === 'neu' ? 'neu' : '';
  const cardClass = colorClass === 'accent' ? 'card-accent' : colorClass === 'pos' ? 'card-green' : colorClass === 'neg' ? 'card-red' : '';
  return `<div class="card ${cardClass}">
    <div class="stat-val mono ${cls}">${val}</div>
    <div class="stat-label">${label}</div>
    <div class="stat-sub">${sub}</div>
  </div>`;
}

function renderEquityChart(canvasId, data) {
  destroyChart(canvasId);
  const canvas = document.getElementById(canvasId);
  if (!canvas || !data.length) return;

  const labels = data.map(d => d.date);
  const values = data.map(d => d.equity);
  const gradient = canvas.getContext('2d').createLinearGradient(0, 0, 0, 200);
  gradient.addColorStop(0, 'rgba(0, 212, 255, 0.2)');
  gradient.addColorStop(1, 'rgba(0, 212, 255, 0)');

  State.charts[canvasId] = new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        data: values,
        borderColor: '#00d4ff',
        borderWidth: 1.5,
        fill: true,
        backgroundColor: gradient,
        pointRadius: 0,
        tension: 0.3,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: {
        callbacks: { label: ctx => ' ' + fmt.dollar(ctx.raw) },
        backgroundColor: '#111c33',
        borderColor: '#1e2d4a',
        borderWidth: 1,
        titleColor: '#7a8cb0',
        bodyColor: '#00d4ff',
      }},
      scales: {
        x: { display: false, grid: { display: false } },
        y: {
          grid: { color: '#1e2d4a' },
          ticks: {
            color: '#4a5878',
            callback: v => '$' + (v/1000).toFixed(0) + 'k',
            maxTicksLimit: 5,
          }
        }
      }
    }
  });
}

// ══════════════════════════════════════════════════════════════════════════════
// TRADE JOURNAL
// ══════════════════════════════════════════════════════════════════════════════
async function loadJournal() {
  const view = document.getElementById('view-journal');
  view.innerHTML = `<div class="loading"><div class="spinner"></div>Loading journal…</div>`;
  try {
    const res = await API.get('/trades', { page: State.tradesPage, limit: 25, period: State.tradesPeriod });
    renderJournal(res);
  } catch (e) { view.innerHTML = `<div class="loading">${e.message}</div>`; }
}

function renderJournal(data) {
  const { trades = [], total = 0, page = 1, limit = 25 } = data;
  const totalPages = Math.ceil(total / limit);
  const view = document.getElementById('view-journal');

  const periodBtns = ['TODAY','WEEK','MONTH','QUARTER','YTD','ALL']
    .map(p => `<button class="pill-tab ${State.tradesPeriod === p ? 'active' : ''}" onclick="setTradesPeriod('${p}')">${p}</button>`)
    .join('');

  view.innerHTML = `
    <div class="section-header mb-14">
      <div class="pill-tabs">${periodBtns}</div>
      <button class="btn btn-primary" onclick="openTradeForm()">+ Log Trade</button>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">${total} Trades</span>
        <span style="font-size:11px;color:var(--text-muted)">Page ${page} of ${totalPages}</span>
      </div>
      <table class="data-table">
        <thead><tr>
          <th>Date</th><th>Instrument</th><th>Dir</th><th>Setup</th>
          <th>Entry</th><th>Exit</th><th>Size</th>
          <th>P&L</th><th>R-Mult</th><th>Exec</th><th></th>
        </tr></thead>
        <tbody>
          ${trades.length === 0
            ? '<tr><td colspan="11"><div class="empty-state"><div class="empty-icon">📋</div><p>No trades logged yet. Start tracking your performance.</p></div></td></tr>'
            : trades.map(t => `
              <tr>
                <td class="mono">${fmt.date(t.trade_date)}</td>
                <td><b>${t.instrument}</b></td>
                <td><span class="badge-${t.direction?.toLowerCase()}">${t.direction}</span></td>
                <td><span class="badge-setup">${t.setup_type || 'Other'}</span></td>
                <td class="mono">${fmt.num(t.entry_price, 2)}</td>
                <td class="mono">${t.exit_price ? fmt.num(t.exit_price, 2) : '—'}</td>
                <td class="mono">${fmt.num(t.position_size, 0)}</td>
                <td class="${fmt.pnlClass(t.pnl)} mono" style="font-weight:600">${fmt.dollar(t.pnl, true)}</td>
                <td>${fmt.r(t.r_multiple)}</td>
                <td>
                  <div style="display:flex;align-items:center;gap:4px">
                    <div class="gauge-bar" style="width:40px;height:4px;margin:0">
                      <div class="gauge-fill" style="width:${(t.execution_score||5)*10}%;background:var(--accent)"></div>
                    </div>
                    <span class="mono" style="font-size:10px">${t.execution_score||5}</span>
                  </div>
                </td>
                <td>
                  <button class="btn btn-ghost btn-sm" onclick="editTrade(${t.id})">Edit</button>
                  <button class="btn btn-danger btn-sm" onclick="deleteTrade(${t.id})" style="margin-left:4px">✕</button>
                </td>
              </tr>
            `).join('')}
        </tbody>
      </table>

      ${totalPages > 1 ? `
      <div class="pagination">
        <button class="page-btn" ${page <= 1 ? 'disabled' : ''} onclick="goPage(${page-1})">←</button>
        ${Array.from({length: Math.min(7, totalPages)}, (_, i) => {
          const p = i + 1;
          return `<button class="page-btn ${p === page ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
        }).join('')}
        <button class="page-btn" ${page >= totalPages ? 'disabled' : ''} onclick="goPage(${page+1})">→</button>
      </div>` : ''}
    </div>
  `;
}

function setTradesPeriod(p) { State.tradesPeriod = p; State.tradesPage = 1; loadJournal(); }
function goPage(p) { State.tradesPage = p; loadJournal(); }

function openTradeForm(existing = null) {
  const isEdit = !!existing;
  const t = existing || {
    trade_date: new Date().toISOString().split('T')[0],
    direction: 'LONG', setup_type: 'Breakout', status: 'CLOSED', execution_score: 7,
  };

  const html = `
    <form id="trade-form">
      <div class="form-grid-3">
        <div class="form-group">
          <label class="form-label">Date</label>
          <input type="date" class="form-input" name="trade_date" value="${t.trade_date || ''}" required>
        </div>
        <div class="form-group">
          <label class="form-label">Instrument</label>
          <input type="text" class="form-input" name="instrument" value="${t.instrument || ''}" placeholder="SPY, AAPL…" required>
        </div>
        <div class="form-group">
          <label class="form-label">Direction</label>
          <select class="form-select" name="direction">
            <option value="LONG"  ${t.direction === 'LONG'  ? 'selected' : ''}>LONG</option>
            <option value="SHORT" ${t.direction === 'SHORT' ? 'selected' : ''}>SHORT</option>
          </select>
        </div>
      </div>
      <div class="form-grid-3">
        <div class="form-group">
          <label class="form-label">Entry Price</label>
          <input type="number" step="0.01" class="form-input" name="entry_price" value="${t.entry_price || ''}" placeholder="0.00" required>
        </div>
        <div class="form-group">
          <label class="form-label">Exit Price</label>
          <input type="number" step="0.01" class="form-input" name="exit_price" value="${t.exit_price || ''}" placeholder="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Position Size (shares/contracts)</label>
          <input type="number" step="0.01" class="form-input" name="position_size" value="${t.position_size || ''}" placeholder="100" required>
        </div>
      </div>
      <div class="form-grid-3">
        <div class="form-group">
          <label class="form-label">Stop Loss</label>
          <input type="number" step="0.01" class="form-input" name="stop_loss" value="${t.stop_loss || ''}" placeholder="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Risk Amount ($)</label>
          <input type="number" step="0.01" class="form-input" name="risk_amount" value="${t.risk_amount || ''}" placeholder="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Setup Type</label>
          <select class="form-select" name="setup_type">
            ${['Breakout','Pullback','Reversal','Momentum','Gap Fill','VWAP Reclaim','Other']
              .map(s => `<option value="${s}" ${t.setup_type === s ? 'selected' : ''}>${s}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="form-grid-3">
        <div class="form-group">
          <label class="form-label">MAE ($)</label>
          <input type="number" step="0.01" class="form-input" name="mae" value="${t.mae || ''}" placeholder="Max adverse excursion">
        </div>
        <div class="form-group">
          <label class="form-label">MFE ($)</label>
          <input type="number" step="0.01" class="form-input" name="mfe" value="${t.mfe || ''}" placeholder="Max favorable excursion">
        </div>
        <div class="form-group">
          <label class="form-label">Duration (min)</label>
          <input type="number" class="form-input" name="duration_minutes" value="${t.duration_minutes || ''}" placeholder="Minutes held">
        </div>
      </div>
      <div class="form-group slider-group">
        <div class="slider-header">
          <label class="form-label" style="margin:0">Execution Score</label>
          <span class="slider-val" id="exec-val">${t.execution_score || 7}/10</span>
        </div>
        <input type="range" min="1" max="10" name="execution_score" value="${t.execution_score || 7}"
          oninput="document.getElementById('exec-val').textContent=this.value+'/10'">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-textarea" name="notes" placeholder="Setup rationale, what went well, what to improve…">${t.notes || ''}</textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:4px">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">${isEdit ? 'Update Trade' : 'Log Trade'}</button>
      </div>
    </form>
  `;
  openModal(isEdit ? `Edit Trade — ${t.instrument}` : 'Log New Trade', html);

  document.getElementById('trade-form').addEventListener('submit', async e => {
    e.preventDefault();
    const fd   = new FormData(e.target);
    const data = Object.fromEntries(fd);
    try {
      if (isEdit) {
        await API.put(`/trades/${t.id}`, data);
        toast('Trade updated', 'success');
      } else {
        await API.post('/trades', data);
        toast('Trade logged', 'success');
      }
      closeModal();
      loadJournal();
    } catch (err) { toast(err.message, 'error'); }
  });
}

async function editTrade(id) {
  try {
    const t = await API.get(`/trades/${id}`);
    openTradeForm(t);
  } catch (e) { toast(e.message, 'error'); }
}

async function deleteTrade(id) {
  if (!confirm('Delete this trade? This cannot be undone.')) return;
  try {
    await API.delete(`/trades/${id}`);
    toast('Trade deleted', 'success');
    loadJournal();
  } catch (e) { toast(e.message, 'error'); }
}

// ══════════════════════════════════════════════════════════════════════════════
// RESILIENCE LAB
// ══════════════════════════════════════════════════════════════════════════════
async function loadResilience() {
  const view = document.getElementById('view-resilience');
  view.innerHTML = `<div class="loading"><div class="spinner"></div>Loading resilience data…</div>`;
  try {
    const res = await API.get('/resilience', { days: 60 });
    renderResilience(res);
  } catch (e) { view.innerHTML = `<div class="loading">${e.message}</div>`; }
}

function renderResilience(data) {
  const score   = parseFloat(data.score || 0);
  const gate    = data.gate || {};
  const corr    = data.correlation || {};
  const coaching = data.coaching || [];
  const trend   = data.trend || [];
  const view    = document.getElementById('view-resilience');

  const corrCoeff = parseFloat(corr.coefficient || 0);
  const corrColor = corrCoeff >= 0.4 ? 'var(--green)' : corrCoeff >= 0.1 ? 'var(--amber)' : 'var(--text-secondary)';

  view.innerHTML = `
    <div class="grid-3 mb-14">
      <div class="card card-accent">
        <div class="card-header"><span class="card-title">Today's Score</span></div>
        <div style="display:flex;align-items:center;gap:16px">
          <div class="resilience-ring">
            <svg viewBox="0 0 120 120" width="100" height="100">
              <circle class="ring-bg" cx="60" cy="60" r="52"/>
              <circle class="ring-fill" id="res-ring-fill" cx="60" cy="60" r="52"
                stroke-dasharray="326.7" stroke-dashoffset="326.7"/>
            </svg>
            <div class="ring-label">
              <span class="ring-score" id="res-ring-score" style="color:${gate.color || '#888'};font-size:20px">${Math.round(score)}</span>
              <span class="ring-level">/100</span>
            </div>
          </div>
          <div>
            <div style="font-size:14px;font-weight:700;color:${gate.color || 'var(--text-secondary)'};">${gate.level || 'No Data'}</div>
            <div style="font-size:11px;color:var(--text-secondary);margin-top:4px;max-width:140px">${gate.message || 'Complete a check-in'}</div>
            <div style="margin-top:8px">
              <span style="font-size:10px;color:var(--text-muted)">Risk Multiplier:</span>
              <span class="mono" style="color:${gate.color};font-size:13px;margin-left:4px">${((gate.risk_multiplier || 0) * 100).toFixed(0)}%</span>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title">Perf Correlation</span></div>
        <div style="text-align:center;padding:8px 0">
          <div class="stat-val mono xl" style="color:${corrColor}">${corrCoeff.toFixed(3)}</div>
          <div class="stat-label">Pearson r</div>
          <div style="font-size:11px;color:var(--text-secondary);margin-top:8px;line-height:1.5">${corr.interpretation || '—'}</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">Pre-Market Check-In</span>
        </div>
        <button class="btn btn-primary" style="width:100%;margin-bottom:8px" onclick="openCheckinForm()">
          + New Check-In
        </button>
        <div style="font-size:11px;color:var(--text-secondary);line-height:1.6">
          Daily check-ins unlock your personalized resilience score, trading gate, and behavioral coaching. Consistency is the edge.
        </div>
      </div>
    </div>

    <!-- TREND CHART -->
    <div class="card mb-14">
      <div class="card-header"><span class="card-title">Resilience Score — 60-Day Trend</span></div>
      <div class="chart-wrap" style="height:160px"><canvas id="res-trend-chart"></canvas></div>
    </div>

    <!-- COACHING -->
    <div class="grid-2">
      <div class="card">
        <div class="card-header"><span class="card-title">Coaching Insights</span></div>
        ${coaching.map(c => `
          <div class="alert-card alert-${c.type === 'critical' ? 'critical' : c.type === 'warning' ? 'warning' : c.type === 'success' ? 'success' : 'info'}">
            <div class="alert-msg" style="color:var(--text-primary)">${c.text}</div>
          </div>
        `).join('') || '<div class="empty-state">Complete check-ins to unlock coaching.</div>'}
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title">Resilience Score Components</span></div>
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px">
          Weights: Sleep 15% · Emotion 15% · Focus 20% · Stress 15% (inv) · Energy 10% · Confidence 15%
        </div>
        ${['Sleep Quality','Emotional State','Focus Level','Stress Level (inv.)','Physical Energy','Confidence'].map((label, i) => {
          const keys = ['sleep_quality','emotional_state','focus_level','stress_level','physical_energy','confidence_level'];
          const val  = 7; // placeholder, updated on checkin
          return `
          <div class="score-row">
            <span class="score-label">${label}</span>
            <div class="score-bar-wrap">
              <div class="score-bar-fill" style="width:70%;background:var(--accent)"></div>
            </div>
            <span class="score-num acc">—</span>
          </div>`;
        }).join('')}
      </div>
    </div>
  `;

  // Animate ring
  setTimeout(() => {
    const fill = document.getElementById('res-ring-fill');
    if (fill) {
      const circ   = 2 * Math.PI * 52;
      const offset = circ - (score / 100) * circ;
      fill.style.stroke          = gate.color || '#888';
      fill.style.strokeDashoffset = offset;
    }
  }, 50);

  // Trend chart
  renderResilienceTrendChart(trend);
}

function renderResilienceTrendChart(trend) {
  destroyChart('res-trend-chart');
  const canvas = document.getElementById('res-trend-chart');
  if (!canvas || !trend.length) return;

  State.charts['res-trend-chart'] = new Chart(canvas, {
    type: 'line',
    data: {
      labels: trend.map(t => t.checkin_date),
      datasets: [{
        data: trend.map(t => parseFloat(t.score)),
        borderColor: '#7c3aed',
        borderWidth: 2,
        fill: true,
        backgroundColor: 'rgba(124,58,237,0.1)',
        pointRadius: 3,
        pointBackgroundColor: '#7c3aed',
        tension: 0.4,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { display: false },
        y: {
          min: 0, max: 100,
          grid: { color: '#1e2d4a' },
          ticks: { color: '#4a5878', maxTicksLimit: 5 },
        }
      }
    }
  });
}

function openCheckinForm() {
  const today = new Date().toISOString().split('T')[0];
  const sliders = [
    { name: 'sleep_quality',    label: 'Sleep Quality',    desc: 'Hours, depth, refreshed?', default: 7 },
    { name: 'emotional_state',  label: 'Emotional State',  desc: 'Calm, centered, grounded?', default: 7 },
    { name: 'focus_level',      label: 'Focus Level',      desc: 'Laser-focused on the plan?', default: 7 },
    { name: 'stress_level',     label: 'Stress Level',     desc: 'Lower is better — 1=calm, 10=maxed', default: 3 },
    { name: 'physical_energy',  label: 'Physical Energy',  desc: 'Body feels ready?', default: 7 },
    { name: 'confidence_level', label: 'Confidence',       desc: 'Trust in your edge and process?', default: 7 },
  ];

  const html = `
    <div class="live-score">
      <div>
        <div class="live-score-num acc" id="live-res-score">70</div>
        <div class="live-score-label">Resilience Score</div>
      </div>
      <div id="live-gate-msg" style="font-size:12px;color:var(--accent);max-width:200px;line-height:1.5"></div>
    </div>
    <form id="checkin-form">
      <input type="hidden" name="checkin_date" value="${today}">
      <input type="hidden" name="checkin_type" value="PRE_MARKET">

      ${sliders.map(s => `
        <div class="slider-group">
          <div class="slider-header">
            <span class="form-label" style="margin:0">${s.label}</span>
            <span class="slider-val" id="sv-${s.name}">${s.default}/10</span>
          </div>
          <div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">${s.desc}</div>
          <input type="range" min="1" max="10" name="${s.name}" value="${s.default}"
            oninput="updateCheckinScore()" id="sr-${s.name}">
        </div>
      `).join('')}

      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Market Bias</label>
          <select class="form-select" name="market_bias">
            <option>BULLISH</option><option selected>NEUTRAL</option><option>BEARISH</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Max Trades Today</label>
          <input type="number" class="form-input" name="planned_max_trades" value="3" min="1" max="20">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Today's Game Plan</label>
        <textarea class="form-textarea" name="game_plan" placeholder="Key levels, planned setups, rules for today…"></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Check-In</button>
      </div>
    </form>
  `;
  openModal('Pre-Market Check-In', html);
  updateCheckinScore();

  document.getElementById('checkin-form').addEventListener('submit', async e => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    try {
      const res = await API.post('/checkins', data);
      toast(`Check-in complete. Resilience: ${res.resilience_score}/100 — ${res.gate.level}`, 'success');
      closeModal();
      loadResilience();
    } catch (err) { toast(err.message, 'error'); }
  });
}

function updateCheckinScore() {
  const names = ['sleep_quality','emotional_state','focus_level','stress_level','physical_energy','confidence_level'];
  let score = 0;
  names.forEach(n => {
    const el = document.getElementById('sr-' + n);
    const vEl= document.getElementById('sv-' + n);
    if (el) {
      const v = parseInt(el.value);
      vEl.textContent = v + '/10';
      if (n === 'stress_level') score += (10 - v) * 1.5;
      else {
        const w = { sleep_quality: 1.5, emotional_state: 1.5, focus_level: 2.0, physical_energy: 1.0, confidence_level: 1.5 };
        score += v * (w[n] || 1);
      }
    }
  });
  score = Math.min(100, Math.round(score));
  const el = document.getElementById('live-res-score');
  const mg = document.getElementById('live-gate-msg');
  if (el) {
    el.textContent = score;
    el.style.color = score >= 85 ? '#10b981' : score >= 70 ? '#22c55e' : score >= 60 ? '#f59e0b' : '#ef4444';
    if (mg) mg.textContent = score >= 85 ? 'Peak state ✓' : score >= 70 ? 'Good to trade ✓' : score >= 60 ? 'Reduce size to 50%' : 'Do not trade today';
    if (mg) mg.style.color = el.style.color;
  }
}

// ══════════════════════════════════════════════════════════════════════════════
// CAPITAL ALLOCATOR
// ══════════════════════════════════════════════════════════════════════════════
async function loadAllocator() {
  const view = document.getElementById('view-allocator');
  try {
    const profile = await API.get('/profile');
    const risk    = await API.get('/risk');
    renderAllocator(profile, risk);
  } catch (e) { view.innerHTML = `<div class="loading">${e.message}</div>`; }
}

function renderAllocator(profile, risk) {
  const view = document.getElementById('view-allocator');
  view.innerHTML = `
    <div class="grid-2-1">
      <!-- CALCULATOR -->
      <div class="card card-accent">
        <div class="card-header"><span class="card-title">Position Sizing Calculator</span></div>
        <form id="alloc-form">
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Account Equity ($)</label>
              <input type="number" step="1" class="form-input" id="alloc-equity" name="account_equity" value="${fmt.num(risk.equity, 0)}">
            </div>
            <div class="form-group">
              <label class="form-label">Base Risk % per Trade</label>
              <input type="number" step="0.1" class="form-input" name="risk_pct" value="${profile.risk_per_trade || 1}" min="0.1" max="5">
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Entry Price</label>
              <input type="number" step="0.01" class="form-input" name="entry_price" placeholder="e.g. 450.25" required>
            </div>
            <div class="form-group">
              <label class="form-label">Stop Loss</label>
              <input type="number" step="0.01" class="form-input" name="stop_loss" placeholder="e.g. 447.50" required>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Direction</label>
              <select class="form-select" name="direction">
                <option>LONG</option><option>SHORT</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">ATR (optional)</label>
              <input type="number" step="0.01" class="form-input" name="atr" placeholder="Average True Range">
            </div>
          </div>
          <div class="form-group slider-group">
            <div class="slider-header">
              <label class="form-label" style="margin:0">Setup Confidence</label>
              <span class="slider-val" id="conf-val">1.0×</span>
            </div>
            <div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">A+: 1.5 · A: 1.0 · B: 0.75 · C: 0.5</div>
            <input type="range" min="25" max="150" value="100" name="setup_confidence_pct" step="25"
              oninput="document.getElementById('conf-val').textContent=(this.value/100).toFixed(2)+'×'">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%">Calculate Position Size</button>
        </form>
      </div>

      <!-- RESULTS -->
      <div>
        <div id="alloc-results" class="card" style="margin-bottom:14px">
          <div class="empty-state">
            <div class="empty-icon">◐</div>
            <p>Enter trade parameters to calculate optimal position size</p>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><span class="card-title">Kelly Criterion (Historical)</span></div>
          <div id="kelly-display" class="kelly-meters">
            <div class="kelly-meter">
              <div class="val acc" id="kelly-full">—</div>
              <div class="lbl">Full Kelly</div>
            </div>
            <div class="kelly-meter">
              <div class="val pos" id="kelly-half">—</div>
              <div class="lbl">Half Kelly ✓</div>
            </div>
            <div class="kelly-meter">
              <div class="val neu" id="kelly-quarter">—</div>
              <div class="lbl">Quarter Kelly</div>
            </div>
          </div>
          <div style="font-size:10px;color:var(--text-muted);margin-top:8px;line-height:1.5">
            Based on YTD win rate and expectancy. Half-Kelly is the institutional standard for maximizing geometric growth while controlling ruin risk.
          </div>
        </div>
      </div>
    </div>

    <!-- R-MULTIPLE SCENARIOS -->
    <div id="r-scenarios" class="card mt-14 hidden" style="margin-top:14px">
      <div class="card-header"><span class="card-title">R-Multiple Scenarios</span></div>
      <div id="r-scenarios-content"></div>
    </div>

    <!-- ATR STOP LEVELS -->
    <div id="atr-stops" class="card hidden" style="margin-top:14px">
      <div class="card-header"><span class="card-title">ATR-Based Levels</span></div>
      <div id="atr-stops-content"></div>
    </div>
  `;

  document.getElementById('alloc-form').addEventListener('submit', async e => {
    e.preventDefault();
    const fd   = new FormData(e.target);
    const data = Object.fromEntries(fd);
    data.setup_confidence = (parseFloat(data.setup_confidence_pct) / 100).toFixed(2);
    delete data.setup_confidence_pct;

    try {
      const res = await API.post('/allocate', data);
      renderAllocResults(res);
    } catch (err) { toast(err.message, 'error'); }
  });
}

function renderAllocResults(res) {
  const s = res.sizing;
  const k = res.kelly;
  const a = s.adjustments;

  // Kelly display
  document.getElementById('kelly-full').textContent    = k.full_kelly + '%';
  document.getElementById('kelly-half').textContent    = k.half_kelly + '%';
  document.getElementById('kelly-quarter').textContent = k.quarter_kelly + '%';

  document.getElementById('alloc-results').innerHTML = `
    <div class="card-header"><span class="card-title">Sizing Recommendation</span></div>
    <div class="grid-2" style="gap:12px;margin-bottom:14px">
      <div>
        <div class="stat-val mono xl acc">${s.shares > 0 ? Math.round(s.shares) : '—'}</div>
        <div class="stat-label">Shares / Contracts</div>
      </div>
      <div>
        <div class="stat-val mono" style="font-size:20px;color:var(--amber)">$${s.risk_amount?.toFixed(2)}</div>
        <div class="stat-label">Risk Amount</div>
      </div>
    </div>
    <div class="grid-2" style="gap:12px;margin-bottom:12px">
      <div>
        <div class="stat-val mono" style="font-size:16px">$${s.notional?.toLocaleString(undefined, {maximumFractionDigits:0})}</div>
        <div class="stat-label">Notional Value</div>
      </div>
      <div>
        <div class="stat-val mono" style="font-size:16px">${s.leverage?.toFixed(2)}×</div>
        <div class="stat-label">Leverage</div>
      </div>
    </div>
    <hr class="divider">
    <div style="font-size:11px;color:var(--text-secondary)">
      <div style="margin-bottom:4px;color:var(--text-muted);font-size:10px;letter-spacing:1px;text-transform:uppercase">ADJUSTMENTS APPLIED</div>
      ${adjRow('Resilience Gate', a.resilience.multiplier, `Score ${a.resilience.score?.toFixed(0)}/100`)}
      ${adjRow('Drawdown Scaling', a.drawdown.multiplier, `${a.drawdown.pct?.toFixed(1)}% in drawdown`)}
      ${adjRow('Half-Kelly', a.kelly.multiplier, `Raw Kelly ${(a.kelly.raw * 100).toFixed(1)}%`)}
      ${adjRow('Setup Confidence', a.confidence.multiplier, `${a.confidence.value?.toFixed(2)}× multiplier`)}
    </div>
    <hr class="divider">
    <div style="font-size:11px">
      Base Risk: <b>${s.base_risk_pct}%</b> → Effective Risk:
      <b style="color:var(--amber)">${s.effective_risk_pct}%</b> of equity
    </div>
    <div style="font-size:11px;margin-top:4px;color:var(--text-muted)">
      Resilience score: <span style="color:${res.resilience_score >= 70 ? 'var(--green)' : 'var(--amber)'}">${res.resilience_score?.toFixed(0)}/100</span>
    </div>
  `;

  // R scenarios
  if (res.r_scenarios?.length) {
    const wrap = document.getElementById('r-scenarios');
    wrap.classList.remove('hidden');
    document.getElementById('r-scenarios-content').innerHTML = `
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        ${res.r_scenarios.map(sc => `
          <div style="flex:1;min-width:80px;text-align:center;padding:10px;background:var(--bg-input);border-radius:var(--radius-sm);border:1px solid var(--border)">
            <div class="${sc.pnl >= 0 ? 'pos' : 'neg'} mono" style="font-size:15px;font-weight:700">${fmt.dollar(sc.pnl, true)}</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:3px">${sc.label}</div>
          </div>
        `).join('')}
      </div>
    `;
  }

  // ATR stops
  if (res.atr_stop) {
    const a = res.atr_stop;
    const wrap = document.getElementById('atr-stops');
    wrap.classList.remove('hidden');
    document.getElementById('atr-stops-content').innerHTML = `
      <div class="grid-3" style="gap:10px">
        <div style="text-align:center">
          <div class="neg mono" style="font-size:18px">${a.stop}</div>
          <div class="stat-label">Stop (${a.atr_mult}× ATR)</div>
        </div>
        <div style="text-align:center">
          <div class="pos mono" style="font-size:18px">${a.target_1r5}</div>
          <div class="stat-label">Target 1.5R</div>
        </div>
        <div style="text-align:center">
          <div class="pos mono" style="font-size:18px">${a.target_3r}</div>
          <div class="stat-label">Target 3R</div>
        </div>
      </div>
    `;
  }
}

function adjRow(label, mult, detail) {
  const color = mult >= 1.0 ? 'var(--green)' : mult >= 0.5 ? 'var(--amber)' : 'var(--red)';
  return `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
      <span>${label} <span style="color:var(--text-muted);font-size:10px">(${detail})</span></span>
      <span style="font-family:var(--font-mono);font-weight:700;color:${color}">${mult}×</span>
    </div>
  `;
}

// ══════════════════════════════════════════════════════════════════════════════
// RISK CONTROL
// ══════════════════════════════════════════════════════════════════════════════
async function loadRisk() {
  const view = document.getElementById('view-risk');
  view.innerHTML = `<div class="loading"><div class="spinner"></div>Loading risk data…</div>`;
  try {
    const [riskData, rules, behavior] = await Promise.all([
      API.get('/risk'),
      API.get('/risk/rules'),
      API.get('/behavior'),
    ]);
    renderRisk(riskData, rules, behavior);
    checkHaltBadge(riskData);
  } catch (e) { view.innerHTML = `<div class="loading">${e.message}</div>`; }
}

function renderRisk(risk, rules, behavior) {
  const view = document.getElementById('view-risk');
  const halt = risk.halt_trading || {};
  const ddPct= parseFloat(risk.drawdown_pct || 0);

  view.innerHTML = `
    <!-- HALT WARNING -->
    ${halt.halt ? `
    <div style="background:var(--red-dim);border:2px solid var(--red);border-radius:var(--radius);padding:16px 20px;margin-bottom:14px">
      <div style="font-size:16px;font-weight:700;color:var(--red)">⛔ TRADING HALTED</div>
      ${halt.reasons?.map(r => `<div style="font-size:12px;color:var(--text-primary);margin-top:4px">→ ${r.rule_name}: ${r.condition_type} at ${r.current_value} (limit: ${r.threshold})</div>`).join('')}
    </div>` : `
    <div style="background:var(--green-dim);border:1px solid var(--green);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:12px;color:var(--green)">
      ✓ All risk controls within limits — cleared to trade
    </div>`}

    <div class="grid-4 mb-14">
      ${statCard('CURRENT EQUITY', fmt.dollar(risk.equity), `Peak: ${fmt.dollar(risk.peak_equity)}`, 'accent')}
      ${statCard('DRAWDOWN', `${fmt.num(ddPct, 2)}%`, `$${fmt.dollar(risk.peak_equity - risk.equity, false).replace('$','')} from peak`, ddPct > 7 ? 'neg' : ddPct > 3 ? 'amb' : 'pos')}
      ${statCard('TODAY P&L', fmt.dollar(risk.today_pnl, true), `${fmt.num(risk.daily_loss_pct, 2)}% / ${risk.daily_limit}% limit`, fmt.pnlClass(risk.today_pnl))}
      ${statCard('WEEK P&L', fmt.dollar(risk.week_pnl, true), `${fmt.num(risk.weekly_loss_pct, 2)}% / ${risk.weekly_limit}% limit`, fmt.pnlClass(risk.week_pnl))}
    </div>

    <div class="grid-2 mb-14">
      <!-- RISK RULES -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Risk Rules (${rules.length})</span>
          <button class="btn btn-primary btn-sm" onclick="openRuleForm()">+ Add Rule</button>
        </div>
        <table class="data-table">
          <thead><tr><th>Rule</th><th>Type</th><th>Condition</th><th>Threshold</th><th>Action</th><th>Active</th><th></th></tr></thead>
          <tbody>
            ${rules.length === 0 ? '<tr><td colspan="7" class="empty-state">No rules configured</td></tr>' :
              rules.map(r => `
                <tr>
                  <td><b>${r.rule_name}</b></td>
                  <td><span class="badge-setup" style="${r.rule_type === 'HARD_STOP' ? 'background:var(--red-dim);color:var(--red)' : ''}">${r.rule_type}</span></td>
                  <td class="mono" style="font-size:10px">${r.condition_type}</td>
                  <td class="mono">${r.threshold_value}</td>
                  <td><span class="badge-setup" style="${r.action === 'HALT_TRADING' ? 'background:var(--red-dim);color:var(--red)' : ''}">${r.action}</span></td>
                  <td>
                    <span style="color:${r.is_active ? 'var(--green)' : 'var(--text-muted)'}">${r.is_active ? '●' : '○'}</span>
                  </td>
                  <td>
                    <button class="btn btn-danger btn-sm" onclick="deleteRule(${r.id})">✕</button>
                  </td>
                </tr>
              `).join('')}
          </tbody>
        </table>
      </div>

      <!-- BEHAVIORAL PATTERNS -->
      <div class="card">
        <div class="card-header"><span class="card-title">Active Behavioral Alerts</span></div>
        ${(behavior.patterns || []).length > 0
          ? behavior.patterns.map(b => `
            <div class="alert-card alert-${b.severity >= 4 ? 'critical' : b.severity >= 3 ? 'warning' : 'info'}">
              <div class="alert-title"><span class="sev-dot sev-${b.severity}" style="margin-right:6px"></span>${b.title}</div>
              <div class="alert-msg">${b.message}</div>
              <div class="alert-action">${b.action}</div>
            </div>
          `).join('')
          : '<div class="alert-card alert-success"><div class="alert-title">✓ No behavioral alerts</div><div class="alert-msg">Your recent trading shows disciplined behavior.</div></div>'
        }

        ${(behavior.summary || []).length > 0 ? `
        <hr class="divider">
        <div class="card-title" style="margin-bottom:8px">30-Day Pattern Summary</div>
        <table class="data-table">
          <thead><tr><th>Pattern</th><th>Occurrences</th><th>Avg Severity</th></tr></thead>
          <tbody>
            ${behavior.summary.map(s => `
              <tr>
                <td>${s.pattern_type.replace(/_/g,' ')}</td>
                <td class="mono">${s.count}</td>
                <td><span class="sev-dot sev-${Math.round(s.avg_severity)}" style="margin-right:6px"></span>${parseFloat(s.avg_severity).toFixed(1)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
        ` : ''}
      </div>
    </div>
  `;
}

function openRuleForm() {
  const html = `
    <form id="rule-form">
      <div class="form-group">
        <label class="form-label">Rule Name</label>
        <input type="text" class="form-input" name="rule_name" placeholder="e.g. Daily Loss Limit" required>
      </div>
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Rule Type</label>
          <select class="form-select" name="rule_type">
            <option>HARD_STOP</option><option selected>SOFT_ALERT</option><option>SIZING_RULE</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Condition</label>
          <select class="form-select" name="condition_type">
            <option>DAILY_LOSS_PCT</option>
            <option>WEEKLY_LOSS_PCT</option>
            <option>DAILY_TRADE_COUNT</option>
            <option>POSITION_RISK_PCT</option>
            <option>RESILIENCE_SCORE</option>
            <option>CONSECUTIVE_LOSS</option>
            <option>PORTFOLIO_HEAT</option>
          </select>
        </div>
      </div>
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Threshold Value</label>
          <input type="number" step="0.1" class="form-input" name="threshold_value" placeholder="e.g. 3.0" required>
        </div>
        <div class="form-group">
          <label class="form-label">Action</label>
          <select class="form-select" name="action">
            <option>HALT_TRADING</option><option selected>ALERT</option><option>REDUCE_SIZE</option>
          </select>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Rule</button>
      </div>
    </form>
  `;
  openModal('Add Risk Rule', html);
  document.getElementById('rule-form').addEventListener('submit', async e => {
    e.preventDefault();
    try {
      await API.post('/risk/rules', Object.fromEntries(new FormData(e.target)));
      toast('Rule saved', 'success');
      closeModal();
      loadRisk();
    } catch (err) { toast(err.message, 'error'); }
  });
}

async function deleteRule(id) {
  if (!confirm('Delete this risk rule?')) return;
  try {
    await API.delete(`/risk/rules/${id}`);
    toast('Rule deleted', 'success');
    loadRisk();
  } catch (e) { toast(e.message, 'error'); }
}

// ══════════════════════════════════════════════════════════════════════════════
// ANALYTICS
// ══════════════════════════════════════════════════════════════════════════════
async function loadAnalytics() {
  const view = document.getElementById('view-analytics');
  view.innerHTML = `<div class="loading"><div class="spinner"></div>Running analytics…</div>`;
  try {
    const data = await API.get('/analytics', { period: State.analyticsPeriod });
    renderAnalytics(data);
  } catch (e) { view.innerHTML = `<div class="loading">${e.message}</div>`; }
}

function renderAnalytics(data) {
  if (data.error) {
    document.getElementById('view-analytics').innerHTML = `<div class="loading">${data.error}</div>`;
    return;
  }
  const s = data.summary || {};
  const r = data.risk_adjusted || {};
  const dd= r.max_dd || {};
  const view = document.getElementById('view-analytics');
  const periodBtns = ['WEEK','MONTH','QUARTER','YTD','ALL']
    .map(p => `<button class="pill-tab ${State.analyticsPeriod === p ? 'active' : ''}" onclick="setAnalyticsPeriod('${p}')">${p}</button>`)
    .join('');

  const sharpeColor = r.sharpe >= 2 ? 'var(--green)' : r.sharpe >= 1 ? 'var(--amber)' : 'var(--red)';
  const sortColor   = r.sortino >= 2 ? 'var(--green)' : r.sortino >= 1 ? 'var(--amber)' : 'var(--red)';

  view.innerHTML = `
    <div class="section-header mb-14">
      <div class="pill-tabs">${periodBtns}</div>
    </div>

    <!-- CORE METRICS -->
    <div class="grid-4 mb-14">
      ${statCard('WIN RATE',      `${s.win_rate}%`, `${s.win_count}W / ${s.loss_count}L`, s.win_rate >= 55 ? 'pos' : 'neg')}
      ${statCard('EXPECTANCY',    fmt.dollar(s.expectancy, true), 'Per trade average', s.expectancy >= 0 ? 'pos' : 'neg')}
      ${statCard('PROFIT FACTOR', fmt.num(s.profit_factor, 2), s.profit_factor >= 1.5 ? 'Excellent' : s.profit_factor >= 1 ? 'Profitable' : 'Losing', s.profit_factor >= 1.5 ? 'pos' : s.profit_factor >= 1 ? 'amb' : 'neg')}
      ${statCard('AVG R-MULTIPLE', fmt.num(s.avg_r_mult, 3) + 'R', `${s.total_trades} trades`, s.avg_r_mult >= 0 ? 'pos' : 'neg')}
    </div>

    <div class="grid-4 mb-14">
      <div class="card">
        <div class="stat-val mono" style="color:${sharpeColor}">${fmt.num(r.sharpe, 2)}</div>
        <div class="stat-label">Sharpe Ratio</div>
        <div class="stat-sub">${r.sharpe >= 2 ? 'Excellent' : r.sharpe >= 1 ? 'Good' : 'Poor'} (annlzd)</div>
      </div>
      <div class="card">
        <div class="stat-val mono" style="color:${sortColor}">${fmt.num(r.sortino, 2)}</div>
        <div class="stat-label">Sortino Ratio</div>
        <div class="stat-sub">Downside-adj return</div>
      </div>
      <div class="card">
        <div class="stat-val mono ${r.calmar >= 1 ? 'pos' : 'neg'}">${fmt.num(r.calmar, 2)}</div>
        <div class="stat-label">Calmar Ratio</div>
        <div class="stat-sub">Return / Max DD</div>
      </div>
      <div class="card">
        <div class="stat-val mono neg">${dd.max_dd_pct}%</div>
        <div class="stat-label">Max Drawdown</div>
        <div class="stat-sub">${dd.trough_date ? fmt.date(dd.trough_date) : '—'}</div>
      </div>
    </div>

    <div class="grid-2 mb-14">
      <!-- EQUITY CURVE -->
      <div class="card">
        <div class="card-header"><span class="card-title">Equity Curve</span></div>
        <div class="chart-wrap" style="height:200px"><canvas id="analytics-equity"></canvas></div>
      </div>
      <!-- R-MULTIPLE DISTRIBUTION -->
      <div class="card">
        <div class="card-header"><span class="card-title">R-Multiple Distribution</span></div>
        <div class="chart-wrap" style="height:200px"><canvas id="r-dist-chart"></canvas></div>
      </div>
    </div>

    <div class="grid-2 mb-14">
      <!-- BY SETUP -->
      <div class="card">
        <div class="card-header"><span class="card-title">Performance by Setup</span></div>
        <table class="data-table">
          <thead><tr><th>Setup</th><th>Trades</th><th>Win%</th><th>Avg R</th><th>Total P&L</th></tr></thead>
          <tbody>
            ${(data.by_setup || []).map(b => `
              <tr>
                <td><span class="badge-setup">${b.setup}</span></td>
                <td class="mono">${b.trades}</td>
                <td class="${b.win_rate >= 50 ? 'pos' : 'neg'} mono">${b.win_rate}%</td>
                <td>${fmt.r(b.avg_r)}</td>
                <td class="${fmt.pnlClass(b.total_pnl)} mono">${fmt.dollar(b.total_pnl, true)}</td>
              </tr>
            `).join('') || '<tr><td colspan="5" class="empty-state">No data</td></tr>'}
          </tbody>
        </table>
      </div>

      <!-- BY INSTRUMENT -->
      <div class="card">
        <div class="card-header"><span class="card-title">Performance by Instrument</span></div>
        <table class="data-table">
          <thead><tr><th>Symbol</th><th>Trades</th><th>Win%</th><th>Total P&L</th></tr></thead>
          <tbody>
            ${(data.by_instrument || []).map(b => `
              <tr>
                <td><b>${b.instrument}</b></td>
                <td class="mono">${b.trades}</td>
                <td class="${b.win_rate >= 50 ? 'pos' : 'neg'} mono">${b.win_rate}%</td>
                <td class="${fmt.pnlClass(b.total_pnl)} mono">${fmt.dollar(b.total_pnl, true)}</td>
              </tr>
            `).join('') || '<tr><td colspan="4" class="empty-state">No data</td></tr>'}
          </tbody>
        </table>
      </div>
    </div>

    <!-- STREAKS & VAR -->
    <div class="grid-4">
      ${statCard('WIN STREAK', data.streaks?.max_win_streak || 0, `Current: ${data.streaks?.current_type === 'win' ? data.streaks.current : 0}`, 'pos')}
      ${statCard('LOSS STREAK', data.streaks?.max_loss_streak || 0, `Current: ${data.streaks?.current_type === 'loss' ? data.streaks.current : 0}`, 'neg')}
      ${statCard('LARGEST WIN',  fmt.dollar(s.largest_win),  'Best single trade', 'pos')}
      ${statCard('LARGEST LOSS', fmt.dollar(s.largest_loss), 'Worst single trade', 'neg')}
    </div>
  `;

  renderEquityChart('analytics-equity', data.equity_curve || []);
  renderRDistChart(data.r_distribution || {});
}

function renderRDistChart(rDist) {
  destroyChart('r-dist-chart');
  const canvas = document.getElementById('r-dist-chart');
  if (!canvas || !rDist.labels) return;

  const colors = rDist.values.map((_, i) =>
    i <= 1 ? '#ef4444' : i === 2 ? '#f59e0b' : i === 3 ? '#7a8cb0' : '#10b981'
  );

  State.charts['r-dist-chart'] = new Chart(canvas, {
    type: 'bar',
    data: {
      labels: rDist.labels,
      datasets: [{
        data: rDist.values,
        backgroundColor: colors,
        borderRadius: 3,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#4a5878', font: { size: 10 } }, grid: { display: false } },
        y: { ticks: { color: '#4a5878' }, grid: { color: '#1e2d4a' } },
      }
    }
  });
}

function setAnalyticsPeriod(p) { State.analyticsPeriod = p; loadAnalytics(); }

// ══════════════════════════════════════════════════════════════════════════════
// SETTINGS
// ══════════════════════════════════════════════════════════════════════════════
async function loadSettings() {
  const view = document.getElementById('view-settings');
  view.innerHTML = `<div class="loading"><div class="spinner"></div>Loading settings…</div>`;
  try {
    const profile = await API.get('/profile');
    renderSettings(profile);
  } catch (e) { view.innerHTML = `<div class="loading">${e.message}</div>`; }
}

function renderSettings(profile) {
  const view = document.getElementById('view-settings');
  view.innerHTML = `
    <div class="grid-2">
      <div class="card">
        <div class="card-header"><span class="card-title">Trader Profile</span></div>
        <form id="settings-form">
          <div class="form-group">
            <label class="form-label">Name</label>
            <input type="text" class="form-input" name="name" value="${profile.name || ''}">
          </div>
          <div class="form-group">
            <label class="form-label">Account Size ($)</label>
            <input type="number" step="1000" class="form-input" name="account_size" value="${profile.account_size || 100000}">
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Risk per Trade (%)</label>
              <input type="number" step="0.1" min="0.1" max="5" class="form-input" name="risk_per_trade" value="${profile.risk_per_trade || 1}">
            </div>
            <div class="form-group">
              <label class="form-label">Min Resilience to Trade</label>
              <input type="number" step="1" min="0" max="100" class="form-input" name="min_resilience_score" value="${profile.min_resilience_score || 65}">
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Max Daily Loss (%)</label>
              <input type="number" step="0.5" class="form-input" name="max_daily_loss" value="${profile.max_daily_loss || 3}">
            </div>
            <div class="form-group">
              <label class="form-label">Max Weekly Loss (%)</label>
              <input type="number" step="0.5" class="form-input" name="max_weekly_loss" value="${profile.max_weekly_loss || 6}">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Benchmark</label>
            <input type="text" class="form-input" name="benchmark" value="${profile.benchmark || 'SPY'}">
          </div>
          <button type="submit" class="btn btn-primary">Save Profile</button>
        </form>
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title">Platform Information</span></div>
        <div style="color:var(--text-secondary);font-size:13px;line-height:2">
          <div><span style="color:var(--text-muted)">Platform:</span> APEX Trader v1.0</div>
          <div><span style="color:var(--text-muted)">Engine:</span> PHP 8.4 + SQLite</div>
          <div><span style="color:var(--text-muted)">Category:</span> Institutional Performance</div>
        </div>
        <hr class="divider">
        <div class="card-title" style="margin-bottom:8px">System Features</div>
        <div style="font-size:11px;color:var(--text-secondary);line-height:2">
          ✓ Multi-factor resilience scoring<br>
          ✓ Kelly Criterion capital allocation<br>
          ✓ Drawdown-adjusted position sizing<br>
          ✓ Behavioral pattern detection<br>
          ✓ Real-time risk rule enforcement<br>
          ✓ Institutional-grade analytics (Sharpe, Sortino, Calmar)<br>
          ✓ MAE/MFE trade analysis<br>
          ✓ Performance-resilience correlation<br>
          ✓ Personalized coaching insights
        </div>
      </div>
    </div>
  `;

  document.getElementById('settings-form').addEventListener('submit', async e => {
    e.preventDefault();
    try {
      await API.put('/profile', Object.fromEntries(new FormData(e.target)));
      toast('Profile saved', 'success');
    } catch (err) { toast(err.message, 'error'); }
  });
}

// ─── INIT ────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  startClock();
  loadDashboard();
  refreshAlertCount();
  setInterval(refreshAlertCount, 60000);
});
