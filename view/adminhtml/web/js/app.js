/*
 * Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.
 * Commercial licence, see LICENSE.txt. https://magebooster.com
 * Licensed per serial number for one production installation (one store domain); staging and
 * development copies of that store are allowed. Serial numbers and downloads are tied to the customer.
 * You may modify this code for your own licensed store; keep this notice.
 * Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing it publicly,
 * removing licence checks or notices, sharing serial numbers. Unauthorised use: the serial number is
 * revoked without refund, updates and support stop, damages and legal action may follow.
 * Estonian law applies; court: Harju County Court (Harju Maakohus).
 */
define([], function () {
    'use strict';

    var ICONS = {
        plus: '<path d="M12 5v14M5 12h14"/>',
        chart: '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>',
        star: '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        box: '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
        cart: '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12h11.2L21 7H6.2"/>',
        tag: '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        table: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/>',
        refund: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
        pencil: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/>',
        play: '<path d="M7 4l13 8-13 8z"/>',
        clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        check: '<path d="M5 12l5 5L20 7"/>',
        row: '<rect x="3" y="9" width="18" height="6" rx="1.5"/><path d="M3 4h18M3 20h18"/>',
        columns: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/>',
        sliders: '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        filter: '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        help: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01"/>',
        download: '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>'
    };
    var KEYWORDS = ('SELECT FROM WHERE AND OR NOT IN IS NULL AS ON JOIN LEFT RIGHT INNER OUTER CROSS FULL GROUP BY ' +
        'ORDER HAVING LIMIT OFFSET DISTINCT UNION ALL WITH CASE WHEN THEN ELSE END BETWEEN LIKE ASC DESC EXISTS ' +
        'INTERVAL DAY WEEK MONTH YEAR HOUR MINUTE TRUE FALSE USING OVER PARTITION ROLLUP RECURSIVE ANY SOME REGEXP DIV MOD XOR')
        .split(' ');
    var BREAK_BEFORE = ['FROM', 'WHERE', 'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'UNION ALL', 'UNION',
        'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'CROSS JOIN', 'JOIN'];

    function icon(name, size, stroke) {
        var fill = name === 'play' ? 'currentColor' : 'none';
        return '<svg width="' + (size || 16) + '" height="' + (size || 16) + '" viewBox="0 0 24 24" fill="' + fill +
            '" stroke="currentColor" stroke-width="' + (stroke || 2) + '" stroke-linecap="round" stroke-linejoin="round" ' +
            'aria-hidden="true">' + (ICONS[name] || ICONS.table) + '</svg>';
    }

    function esc(v) {
        return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;'}[c];
        });
    }

    function copy(o) {
        return JSON.parse(JSON.stringify(o));
    }

    /**
     * Splits SQL into pieces: code, string, ident, comment (same rules as the server validator)
     */
    function tokens(sql) {
        var out = [], i = 0, n = sql.length, start, ch, q;
        while (i < n) {
            ch = sql[i];
            start = i;
            if (ch === '-' && sql[i + 1] === '-' || ch === '#') {
                while (i < n && sql[i] !== '\n') { i++; }
                out.push(['comment', sql.slice(start, i)]);
            } else if (ch === '/' && sql[i + 1] === '*') {
                i = sql.indexOf('*/', i + 2);
                i = i < 0 ? n : i + 2;
                out.push(['comment', sql.slice(start, i)]);
            } else if (ch === '\'' || ch === '"' || ch === '`') {
                q = ch;
                i++;
                while (i < n) {
                    if (sql[i] === '\\' && q !== '`') { i += 2; continue; }
                    if (sql[i] === q) {
                        if (sql[i + 1] === q) { i += 2; continue; }
                        i++;
                        break;
                    }
                    i++;
                }
                out.push([q === '`' ? 'ident' : 'string', sql.slice(start, i)]);
            } else {
                while (i < n && !/['"`#]/.test(sql[i]) && !(sql[i] === '-' && sql[i + 1] === '-') &&
                    !(sql[i] === '/' && sql[i + 1] === '*')) { i++; }
                out.push(['code', sql.slice(start, i)]);
            }
        }
        return out;
    }

    function paramNames(sql) {
        var seen = {}, list = [], re = /(^|[^:\w]):([a-z_][a-z0-9_]{0,40})\b/gi, m;
        tokens(sql).forEach(function (t) {
            if (t[0] !== 'code') { return; }
            re.lastIndex = 0;
            while ((m = re.exec(t[1])) !== null) {
                var name = m[2].toLowerCase();
                if (!seen[name]) { seen[name] = 1; list.push(name); }
            }
        });
        return list;
    }

    function highlight(sql) {
        return tokens(sql).map(function (t) {
            if (t[0] === 'comment') { return '<span class="mbrl-c">' + esc(t[1]) + '</span>'; }
            if (t[0] === 'string') { return '<span class="mbrl-s">' + esc(t[1]) + '</span>'; }
            if (t[0] === 'ident') { return esc(t[1]); }
            return t[1].replace(/(::?[A-Za-z_][A-Za-z0-9_]*)|([A-Za-z_][A-Za-z0-9_$]*)(\s*\()?|(\b\d+(?:\.\d+)?\b)|([^A-Za-z_:\d]+|[\d:])/g,
                function (all, param, word, paren, num) {
                    if (param) {
                        return param[1] === ':' ? esc(param) : '<span class="mbrl-p">' + esc(param) + '</span>';
                    }
                    if (word) {
                        if (paren) { return '<span class="mbrl-f">' + esc(word) + '</span>' + esc(paren); }
                        return KEYWORDS.indexOf(word.toUpperCase()) >= 0 ? '<span class="mbrl-k">' + esc(word) + '</span>' : esc(word);
                    }
                    if (num) { return '<span class="mbrl-n">' + esc(num) + '</span>'; }
                    return esc(all);
                });
        }).join('');
    }

    /**
     * Light formatting: keywords upper case, main clauses on their own line, AND/OR indented
     */
    function formatSql(sql) {
        return tokens(sql).map(function (t) {
            if (t[0] !== 'code') { return t[1]; }
            var s = t[1].replace(/[A-Za-z_]+/g, function (w) {
                return KEYWORDS.indexOf(w.toUpperCase()) >= 0 ? w.toUpperCase() : w;
            });
            BREAK_BEFORE.forEach(function (k) {
                var re = new RegExp('[ \\t]*\\n?[ \\t]*\\b(' + k.replace(' ', '\\s+') + ')\\b', 'g');
                s = s.replace(re, function (m, kw, off, all) {
                    var before = all.slice(Math.max(0, off - 6), off).toUpperCase();
                    if (/(LEFT|RIGHT|INNER|CROSS|UNION)\s*$/.test(before)) { return m; }
                    return off === 0 ? kw.replace(/\s+/g, ' ') : '\n' + kw.replace(/\s+/g, ' ');
                });
            });
            s = s.replace(/[ \t]*\n?[ \t]*\b(AND|OR)\b(?![^(]*\))/g, function (m, kw, off) {
                return off === 0 ? m : '\n  ' + kw;
            });
            return s;
        }).join('').replace(/\n{3,}/g, '\n\n').trim();
    }

    function App(el, cfg) {
        this.el = el;
        this.cfg = cfg;
        this.body = el.querySelector('[data-role="body"]');
        this.sub = el.querySelector('[data-role="top-sub"]');
        this.st = {
            reports: cfg.reports || [], report: null, mode: 'empty', tab: 'query', draft: null, snap: '',
            example: null, result: null, error: null, running: false, chart: true, q: '', next: null,
            busy: '', notice: null
        };
        this.nextTimer = null;
        this.bind();
        var first = this.st.reports[0];
        if (first) {
            this.open(first.id, true);
        } else {
            this.startNew('blank', true);
        }
    }

    App.prototype.t = function (s) {
        var out = (this.cfg.i18n && this.cfg.i18n[s]) || s, args = arguments;
        return out.replace(/%(\d)/g, function (m, d) {
            return args[+d] !== undefined ? args[+d] : m;
        });
    };

    /* ---------- server ---------- */

    App.prototype.post = function (url, data, raw) {
        var fd = new FormData();
        fd.append('form_key', this.cfg.formKey);
        fd.append('isAjax', 'true');
        fd.append('data', JSON.stringify(data || {}));
        var self = this;
        return fetch(url, {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (res) {
                var type = res.headers.get('Content-Type') || '';
                if (raw && res.ok && type.indexOf('application/json') < 0) {
                    return res;
                }
                return res.json().catch(function () {
                    throw new Error(self.t('Something went wrong. Try again.'));
                }).then(function (json) {
                    if (!json || json.ok === false) {
                        throw new Error((json && json.error) || self.t('Something went wrong. Try again.'));
                    }
                    return json;
                });
            });
    };

    App.prototype.toast = function (text, isError) {
        var old = this.el.querySelector('.mbrl-toast');
        if (old) { old.remove(); }
        var box = document.createElement('div');
        box.className = 'mbrl-toast' + (isError ? ' is-error' : '');
        box.setAttribute('role', isError ? 'alert' : 'status');
        box.textContent = text;
        this.el.appendChild(box);
        setTimeout(function () { box.remove(); }, isError ? 7000 : 4000);
    };

    /* ---------- state ---------- */

    App.prototype.defaultValue = function (name) {
        var n = name.toLowerCase();
        if (n === 'store' || n === 'store_id') { return String(this.cfg.defaultStore || ''); }
        if (this.kind(name) === 'date') {
            return /^(to|end|until)$|_(to|end|until)$|^(to|end)_/.test(n) ? '@today' : '@days_ago_30';
        }
        return '';
    };

    App.prototype.kind = function (name) {
        var n = name.toLowerCase();
        if (n === 'store' || n === 'store_id') { return 'store'; }
        if (/^(from|to|start|end|since|until|date|day)$|_(from|to|date|start|end|since|until|day)$|^(from|to|date|start|end)_/.test(n)) {
            return 'date';
        }
        return 'text';
    };

    App.prototype.blankDraft = function () {
        var formats = this.cfg.formats.map(function (f) { return f.key; });
        return {
            id: null, name: '', description: '', query: '', params: {}, cron_status: 0,
            schedule: {frequency: 'weekly', day: 1, time: '08:00', range: 'saved', cron: '0 8 * * 1'},
            recipients: this.cfg.me ? [this.cfg.me] : [],
            export_type: formats.indexOf('xlsx') >= 0 ? 'xlsx' : 'csv', delivery: 'attach', skip_if_empty: 0
        };
    };

    App.prototype.fromReport = function (r) {
        var params = {};
        Object.keys(r.params || {}).forEach(function (k) { params[k] = r.params[k]; });
        return {
            id: r.id, name: r.name, description: r.description || '', query: r.query, params: params,
            cron_status: r.cron_status ? 1 : 0, schedule: copy(r.schedule), recipients: (r.recipients || []).slice(),
            export_type: r.export_type, delivery: r.delivery, skip_if_empty: r.skip_if_empty ? 1 : 0
        };
    };

    App.prototype.fillParams = function () {
        var d = this.st.draft, self = this;
        paramNames(d.query).forEach(function (p) {
            if (d.params[p] === undefined || d.params[p] === null) { d.params[p] = self.defaultValue(p); }
        });
    };

    App.prototype.signature = function (d) {
        var names = paramNames(d.query), params = {};
        names.forEach(function (p) { params[p] = d.params[p] || ''; });
        var s = d.schedule || {};
        return JSON.stringify([d.name.trim(), d.query.trim(), params, d.cron_status ? 1 : 0, s.frequency, +s.day, s.time,
            s.range, s.frequency === 'custom' ? s.cron : '', d.recipients, d.export_type, d.delivery, d.skip_if_empty ? 1 : 0]);
    };

    App.prototype.dirty = function () {
        var d = this.st.draft;
        if (!d) { return false; }
        if (!d.id) { return d.query.trim() !== '' || d.name.trim() !== ''; }
        return this.signature(d) !== this.st.snap;
    };

    App.prototype.leaveOk = function () {
        return !this.dirty() || window.confirm(this.t('You have unsaved changes. Leave them?'));
    };

    App.prototype.open = function (id, initial) {
        if (!initial && !this.leaveOk()) { return; }
        var self = this;
        this.st.busy = 'load';
        this.post(this.cfg.urls.load, {id: id}).then(function (res) {
            self.setReport(res.report);
            self.st.result = null;
            self.st.error = null;
            self.st.tab = 'query';
            self.st.busy = '';
            self.render();
        }).catch(function (e) {
            self.st.busy = '';
            self.toast(e.message, true);
        });
    };

    App.prototype.setReport = function (report) {
        this.st.report = report;
        this.st.mode = 'report';
        this.st.example = null;
        this.st.draft = this.fromReport(report);
        this.fillParams();
        this.st.snap = this.signature(this.st.draft);
        this.st.next = report.next;
    };

    App.prototype.startNew = function (exampleKey, initial) {
        if (!initial && !this.leaveOk()) { return; }
        this.st.mode = 'new';
        this.st.report = null;
        this.st.draft = this.blankDraft();
        this.st.result = null;
        this.st.error = null;
        this.st.tab = 'query';
        this.st.next = null;
        this.pickExample(exampleKey || 'blank', true);
        this.render();
    };

    App.prototype.example = function (key) {
        return (this.cfg.examples || []).filter(function (e) { return e.key === key; })[0] || null;
    };

    App.prototype.pickExample = function (key, silent) {
        var ex = this.example(key);
        if (!ex) { return; }
        var d = this.st.draft;
        var untouched = !d.query.trim() || (this.st.example && this.example(this.st.example) &&
            this.example(this.st.example).sql === d.query);
        if (!silent && !untouched && !window.confirm(this.t('You have unsaved changes. Leave them?'))) { return; }
        this.st.example = key;
        d.query = ex.sql;
        d.params = copy(ex.params || {});
        var prevEx = this.st.prevExampleName;
        if (!d.name || d.name === prevEx) { d.name = key === 'blank' ? '' : ex.name; }
        this.st.prevExampleName = key === 'blank' ? '' : ex.name;
        this.fillParams();
        this.st.result = null;
        this.st.error = null;
        if (!silent) { this.render(); }
    };

    App.prototype.duplicate = function () {
        if (!this.st.draft) { return; }
        var d = copy(this.st.draft);
        d.id = null;
        d.name = this.t('Copy of %1', d.name);
        d.cron_status = 0;
        this.st.mode = 'new';
        this.st.report = null;
        this.st.example = null;
        this.st.draft = d;
        this.st.tab = 'query';
        this.render();
        var input = this.body.querySelector('#mbrl-name');
        if (input) { input.focus(); input.select(); }
    };

    App.prototype.save = function () {
        var d = this.st.draft, self = this;
        if (!d.name.trim()) { d.name = this.t('Untitled report'); }
        this.st.busy = 'save';
        this.render();
        this.post(this.cfg.urls.save, d).then(function (res) {
            self.st.reports = res.items;
            self.setReport(res.report);
            self.st.busy = '';
            self.render();
            self.toast(self.t('Saved'));
        }).catch(function (e) {
            self.st.busy = '';
            self.render();
            self.toast(e.message, true);
        });
    };

    App.prototype.remove = function () {
        var d = this.st.draft, self = this;
        if (!d || !d.id || !window.confirm(this.t('Delete this report, its history and its files?'))) { return; }
        this.post(this.cfg.urls['delete'], {id: d.id}).then(function (res) {
            self.st.reports = res.items;
            self.st.draft = null;
            if (res.items.length) {
                self.open(res.items[0].id, true);
            } else {
                self.startNew('blank', true);
            }
        }).catch(function (e) { self.toast(e.message, true); });
    };

    App.prototype.runPayload = function () {
        var d = this.st.draft, params = {};
        paramNames(d.query).forEach(function (p) { params[p] = d.params[p] || ''; });
        return {id: d.id, name: d.name, query: d.query, params: params};
    };

    App.prototype.run = function () {
        var self = this;
        if (this.st.running) { return; }
        this.st.running = true;
        this.st.error = null;
        this.renderResult();
        this.post(this.cfg.urls.run, this.runPayload()).then(function (res) {
            self.st.running = false;
            self.st.result = res;
            self.renderResult();
        }).catch(function (e) {
            self.st.running = false;
            self.st.result = null;
            self.st.error = e.message;
            self.renderResult();
        });
    };

    App.prototype.exportFile = function (format, btn) {
        var self = this, payload = this.runPayload();
        payload.format = format;
        if (btn) { btn.disabled = true; }
        this.post(this.cfg.urls['export'], payload, true).then(function (res) {
            var cd = res.headers.get('Content-Disposition') || '', m = /filename="([^"]+)"/.exec(cd);
            return res.blob().then(function (blob) {
                var a = document.createElement('a'), url = URL.createObjectURL(blob);
                a.href = url;
                a.download = m ? m[1] : 'report.' + format;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
            });
        }).catch(function (e) {
            self.toast(e.message, true);
        }).then(function () {
            if (btn) { btn.disabled = false; }
        });
    };

    App.prototype.sendTest = function () {
        var d = this.st.draft, self = this;
        if (!d.id) { this.toast(this.t('Save the report first.'), true); return; }
        if (this.dirty()) { this.toast(this.t('Save your changes first.'), true); return; }
        this.st.busy = 'test';
        this.render();
        this.post(this.cfg.urls.test, {id: d.id}).then(function (res) {
            self.st.report = res.report;
            self.st.busy = '';
            self.render();
            self.toast(res.message);
        }).catch(function (e) {
            self.st.busy = '';
            self.post(self.cfg.urls.load, {id: d.id}).then(function (r) {
                self.st.report = r.report;
                self.render();
            }).catch(function () { self.render(); });
            self.toast(e.message, true);
        });
    };

    App.prototype.refreshNext = function () {
        var self = this, s = this.st.draft.schedule;
        clearTimeout(this.nextTimer);
        this.nextTimer = setTimeout(function () {
            self.post(self.cfg.urls.next, s).then(function (res) {
                self.st.next = res;
                self.st.nextError = null;
                if (s.frequency !== 'custom') { s.cron = res.cron; }
                self.renderNext();
            }).catch(function (e) {
                self.st.next = null;
                self.st.nextError = e.message;
                self.renderNext();
            });
        }, 250);
    };

    /* ---------- rendering ---------- */

    App.prototype.render = function () {
        var st = this.st, html = '';
        if (this.sub) { this.sub.textContent = st.mode === 'new' ? this.t('New report') : 'MageBooster'; }
        html += this.renderSide();
        html += '<main class="mbrl-main">' + (st.draft ? this.renderMain() : '') + '</main>';
        if (st.mode === 'new') { html += this.renderHelp(); }
        this.body.innerHTML = html;
        this.afterRender();
    };

    App.prototype.renderSide = function () {
        var self = this, st = this.st, q = st.q.trim().toLowerCase();
        var list = st.reports.filter(function (r) { return !q || r.name.toLowerCase().indexOf(q) >= 0; });
        var stateLabel = {scheduled: this.t('Scheduled'), paused: this.t('Paused'), manual: this.t('Manual')};
        var h = '<aside class="mbrl-side">';
        if (this.cfg.acl.edit) {
            h += '<button type="button" class="mbrl-btn mbrl-btn-new" data-act="new">' + icon('plus', 16, 2.4) + esc(this.t('New report')) + '</button>';
        }
        h += '<div><label for="mbrl-q" class="mbrl-sr">' + esc(this.t('Search reports')) + '</label>' +
            '<input id="mbrl-q" class="mbrl-search" type="search" placeholder="' + esc(this.t('Search reports')) + '" value="' + esc(st.q) + '"></div>';
        h += '<span class="mbrl-label">' + esc(this.t('My reports')) + '</span><div class="mbrl-list" data-role="list">';
        if (!st.reports.length) {
            h += '<div class="mbrl-empty">' + esc(this.t('No reports yet. Start with New report or pick one from the library.')) + '</div>';
        } else if (!list.length) {
            h += '<div class="mbrl-empty">' + esc(this.t('No report matches.')) + '</div>';
        }
        list.forEach(function (r) {
            var on = st.mode === 'report' && st.draft && st.draft.id === r.id;
            h += '<button type="button" class="mbrl-item' + (on ? ' is-on' : '') + '" data-act="open" data-id="' + r.id + '"' +
                (on ? ' aria-current="true"' : '') + '>' +
                '<span class="mbrl-ico mbrl-ico-' + esc(r.icon) + '">' + icon(r.icon) + '</span>' +
                '<span class="mbrl-item-text"><span class="mbrl-item-name">' + esc(r.name) + '</span>' +
                '<span class="mbrl-item-meta">' + esc(r.meta) + '</span></span>' +
                '<span class="mbrl-dot mbrl-dot-' + esc(r.state) + '" role="img" aria-label="' + esc(stateLabel[r.state]) + '" title="' +
                esc(stateLabel[r.state]) + '"></span></button>';
        });
        h += '</div>';
        if (this.cfg.acl.edit) {
            h += '<span class="mbrl-label">' + esc(this.t('Start from the library')) + '</span><div class="mbrl-chips">';
            (this.cfg.examples || []).forEach(function (e) {
                if (e.key !== 'blank') {
                    h += '<button type="button" class="mbrl-chip" data-act="library" data-key="' + esc(e.key) + '">' + esc(e.name) + '</button>';
                }
            });
            h += '</div>';
        }
        return h + '</aside>';
    };

    App.prototype.renderMain = function () {
        var st = this.st, h = '';
        h += this.renderHead();
        if (st.mode === 'new') {
            h += this.renderEditor() + this.renderAbout() + '<div data-role="result">' + this.resultHtml() + '</div>';
            return h;
        }
        h += '<nav class="mbrl-tabs" role="tablist">';
        [['query', this.t('Query')], ['schedule', this.t('Schedule')], ['history', this.t('History')]].forEach(function (t) {
            h += '<button type="button" role="tab" class="mbrl-tab' + (st.tab === t[0] ? ' is-on' : '') + '" aria-selected="' +
                (st.tab === t[0]) + '" data-act="tab" data-tab="' + t[0] + '">' + esc(t[1]) + '</button>';
        });
        h += '</nav>';
        if (st.tab === 'query') {
            h += this.renderEditor() + '<div data-role="result">' + this.resultHtml() + '</div>';
        } else if (st.tab === 'schedule') {
            h += this.renderSchedule();
        } else {
            h += this.renderHistory();
        }
        return h;
    };

    App.prototype.renderHead = function () {
        var st = this.st, d = st.draft, isNew = st.mode === 'new', edit = this.cfg.acl.edit, h;
        h = '<section class="mbrl-card' + (isNew ? ' mbrl-new' : '') + '"><div class="mbrl-head">' +
            '<div class="mbrl-name"><label for="mbrl-name">' + esc(this.t('Report name')) + '</label>' +
            '<input id="mbrl-name" type="text" maxlength="255" autocomplete="off" value="' + esc(d.name) + '" placeholder="' +
            esc(this.t('Untitled report')) + '"' + (edit ? '' : ' readonly') + '></div>';
        h += '<span class="mbrl-badge" data-role="dirty"' + (isNew || this.dirty() ? '' : ' hidden') + '>' +
            esc(isNew ? this.t('Not saved yet') : this.t('Unsaved changes')) + '</span>';
        if (!isNew && edit) {
            h += '<button type="button" class="mbrl-btn" data-act="duplicate">' + esc(this.t('Duplicate')) + '</button>';
        }
        if (edit) {
            h += '<button type="button" class="mbrl-btn mbrl-btn-dark" data-act="save"' + (st.busy === 'save' ? ' disabled' : '') + '>' +
                esc(st.busy === 'save' ? this.t('Saving…') : this.t('Save')) + '</button>';
        }
        h += '</div>';
        if (isNew) {
            if (st.example !== null) {
                h += '<div style="display:flex;flex-direction:column;gap:8px"><span class="mbrl-hint">' +
                    esc(this.t('Write your own query in the editor below, or start from an example and change it')) +
                    '</span><div class="mbrl-chips mbrl-chips-lg">';
                (this.cfg.examples || []).forEach(function (e) {
                    h += '<button type="button" class="mbrl-chip' + (st.example === e.key ? ' is-on' : '') + '" aria-pressed="' +
                        (st.example === e.key) + '" data-act="example" data-key="' + esc(e.key) + '">' + esc(e.name) + '</button>';
                });
                h += '</div></div>';
            }
        } else {
            h += this.renderStats();
        }
        if (!edit) {
            h += '<div class="mbrl-hint">' + esc(this.t('Your admin role can run saved reports but not change them.')) + '</div>';
        }
        return h + '</section>';
    };

    App.prototype.renderStats = function () {
        var r = this.st.report, s = r ? r.stats : {}, self = this, n = s.recipients || 0;
        var rows = s.last_rows === null || s.last_rows === undefined ? this.t('Never')
            : (+s.last_rows === 1 ? this.t('1 row') : this.t('%1 rows', s.last_rows));
        var cards = [
            [this.t('Last run'), s.last_run || this.t('Never')],
            [this.t('Next run'), s.next_run || this.t('Not scheduled')],
            [this.t('Recipients'), n === 0 ? this.t('Nobody yet') : (n === 1 ? this.t('1 person') : this.t('%1 people', n))],
            [this.t('Last result'), rows]
        ];
        return '<div class="mbrl-stats">' + cards.map(function (c, i) {
            var title = i === 3 && s.last_result ? ' title="' + esc(s.last_result) + '"' : '';
            return '<div class="mbrl-stat"' + title + '><span>' + esc(c[0]) + '</span><span>' + esc(c[1]) + '</span></div>';
        }).join('') + '</div>' + (self ? '' : '');
    };

    App.prototype.renderEditor = function () {
        var d = this.st.draft, edit = this.cfg.acl.edit;
        return '<section class="mbrl-editor"><div class="mbrl-ed-bar">' +
            '<span class="mbrl-ed-file">query.sql</span><span class="mbrl-ed-badge">' + esc(this.t('SELECT only')) + '</span>' +
            '<span class="mbrl-grow"></span>' +
            (edit ? '<button type="button" class="mbrl-ed-format" data-act="format">' + esc(this.t('Format')) + '</button>' : '') +
            '<button type="button" class="mbrl-ed-run" data-act="run" title="Ctrl+Enter"' + (this.st.running ? ' disabled' : '') + '>' +
            icon('play', 12) + esc(this.st.running ? this.t('Running…') : this.t('Run')) + '</button></div>' +
            '<div class="mbrl-ed-area"><div class="mbrl-ed-lines" data-role="lines" aria-hidden="true">' + this.lineNumbers(d.query) + '</div>' +
            '<div class="mbrl-ed-wrap"><div style="display:inline-block;min-width:100%;position:relative">' +
            '<pre class="mbrl-ed-hl" data-role="hl" aria-hidden="true">' + highlight(d.query) + '\n</pre>' +
            '<label for="mbrl-sql" class="mbrl-sr">' + esc(this.t('SQL query')) + '</label>' +
            '<textarea id="mbrl-sql" class="mbrl-ed-text" spellcheck="false" autocapitalize="off" autocomplete="off" wrap="off" ' +
            'placeholder="SELECT ... FROM sales_order WHERE created_at BETWEEN :from AND :to"' + (edit ? '' : ' readonly') + '>' +
            esc(d.query) + '</textarea></div></div></div>' +
            '<div class="mbrl-params" data-role="params">' + this.paramsHtml() + '</div></section>';
    };

    App.prototype.lineNumbers = function (sql) {
        var n = Math.max(6, sql.split('\n').length), out = [];
        for (var i = 1; i <= n; i++) { out.push(i); }
        return out.join('\n');
    };

    App.prototype.paramsHtml = function () {
        var d = this.st.draft, self = this, names = paramNames(d.query);
        var h = '<span class="mbrl-params-title">' + esc(this.t('Parameters')) + '</span>';
        if (!names.length) {
            return h + '<span class="mbrl-params-none">' + esc(this.t('No parameters. Write :from, :to or :name in the query to add one.')) + '</span>';
        }
        names.forEach(function (p) {
            var v = d.params[p] === undefined ? self.defaultValue(p) : String(d.params[p]), kind = self.kind(p), id = 'mbrl-p-' + p;
            h += '<label class="mbrl-param" for="' + id + '">:' + esc(p) + '<span class="mbrl-param-row">';
            if (kind === 'date') {
                var isDate = /^\d{4}-\d{2}-\d{2}$/.test(v);
                h += '<select id="' + id + '" data-param="' + esc(p) + '" data-role="preset">';
                self.cfg.presets.forEach(function (o) {
                    h += '<option value="' + esc(o.value) + '"' + (o.value === v ? ' selected' : '') + '>' + esc(o.label) + '</option>';
                });
                h += '<option value="date"' + (isDate ? ' selected' : '') + '>' + esc(self.t('Pick a date')) + '</option></select>';
                if (isDate) {
                    h += '<input type="date" data-param="' + esc(p) + '" value="' + esc(v) + '" aria-label=":' + esc(p) + '">';
                }
            } else if (kind === 'store') {
                h += '<select id="' + id + '" data-param="' + esc(p) + '">';
                self.cfg.stores.forEach(function (o) {
                    h += '<option value="' + esc(o.value) + '"' + (o.value === v ? ' selected' : '') + '>' + esc(o.label) + '</option>';
                });
                h += '</select>';
            } else {
                h += '<input id="' + id + '" type="text" maxlength="500" data-param="' + esc(p) + '" value="' + esc(v) + '">';
            }
            h += '</span></label>';
        });
        return h;
    };

    App.prototype.chartOf = function (res) {
        if (!res || res.rows.length < 2 || res.rows.length > 60 || res.columns.length < 2 || res.columns[0].numeric) { return null; }
        var col = -1;
        res.columns.forEach(function (c, i) { if (i > 0 && c.numeric) { col = i; } });
        if (col < 0) { return null; }
        var vals = res.rows.map(function (r) { return Math.max(0, parseFloat(r[col]) || 0); });
        var max = Math.max.apply(null, vals);
        return max > 0 ? {col: col, vals: vals, max: max} : null;
    };

    App.prototype.resultHtml = function () {
        var st = this.st, res = st.result, self = this, h = '<section class="mbrl-card">';
        h += '<div class="mbrl-res-head"><h3 class="mbrl-h">' + esc(this.t('Result')) + '</h3>';
        if (res) {
            var count = res.truncated ? this.t('more than %1', res.count) : res.count;
            h += '<span class="mbrl-hint">' + esc(this.t('%1 rows · %2 s', count, (res.ms / 1000).toFixed(2))) + '</span>';
        }
        h += '<span class="mbrl-grow"></span>';
        var chart = this.chartOf(res);
        if (chart) {
            h += '<button type="button" class="mbrl-link-btn" data-act="chart">' + esc(st.chart ? this.t('Hide chart') : this.t('Show chart')) + '</button>';
        }
        h += '<div class="mbrl-formats" role="group" aria-label="' + esc(this.t('Download')) + '">';
        this.cfg.formats.forEach(function (f) {
            h += '<button type="button" data-act="export" data-format="' + esc(f.key) + '" title="' + esc(self.t('Download')) + ' ' +
                esc(f.label) + '">' + esc(f.label) + '</button>';
        });
        h += '</div></div>';
        if (st.running) {
            h += '<div class="mbrl-msg mbrl-msg-info" role="status">' + esc(this.t('Running…')) + '</div>';
        } else if (st.error) {
            h += '<div class="mbrl-msg mbrl-msg-error" role="alert">' + esc(st.error) + '</div>';
        } else if (!res) {
            h += '<div class="mbrl-msg mbrl-msg-info">' + esc(this.t('Run the query to see the result.')) + '</div>';
        } else if (!res.rows.length) {
            h += '<div class="mbrl-msg mbrl-msg-info">' + esc(this.t('The query returned no rows.')) + '</div>';
        } else {
            if (chart && st.chart) {
                var label = this.t('Bar chart of %1 by %2', res.columns[chart.col].key, res.columns[0].key);
                h += '<div class="mbrl-bars" role="img" aria-label="' + esc(label) + '">';
                chart.vals.forEach(function (v, i) {
                    h += '<span style="height:' + Math.max(1, Math.round(v / chart.max * 100)) + '%" title="' +
                        esc(res.rows[i][0] + ': ' + res.rows[i][chart.col]) + '"></span>';
                });
                h += '</div>';
            }
            if (res.count > res.shown) {
                h += '<div class="mbrl-hint">' + esc(this.t('Showing the first %1 of %2 rows', res.shown, res.truncated ? this.t('more than %1', res.count) : res.count)) + '</div>';
            }
            h += '<div class="mbrl-table-wrap"><table class="mbrl-table"><thead><tr>';
            res.columns.forEach(function (c) {
                h += '<th scope="col"' + (c.numeric ? ' class="is-num"' : '') + '>' + esc(c.key) + '</th>';
            });
            h += '</tr></thead><tbody>';
            res.rows.forEach(function (r) {
                h += '<tr>';
                r.forEach(function (v, i) {
                    var cls = res.columns[i] && res.columns[i].numeric ? 'is-num' : '';
                    if (v === null) { cls += ' is-null'; }
                    h += '<td' + (cls ? ' class="' + cls.trim() + '"' : '') + '>' + (v === null ? 'NULL' : esc(v)) + '</td>';
                });
                h += '</tr>';
            });
            h += '</tbody></table></div>';
        }
        return h + '</section>';
    };

    App.prototype.renderResult = function () {
        var box = this.body.querySelector('[data-role="result"]'), btn = this.body.querySelector('[data-act="run"]');
        if (box) { box.innerHTML = this.resultHtml(); }
        if (btn) {
            btn.disabled = this.st.running;
            btn.innerHTML = icon('play', 12) + esc(this.st.running ? this.t('Running…') : this.t('Run'));
        }
    };

    App.prototype.renderAbout = function () {
        var ex = this.st.example ? this.example(this.st.example) : null;
        if (!ex) { return ''; }
        return '<section class="mbrl-card mbrl-about"><h3 class="mbrl-h">' + esc(this.t('About this example')) + '</h3>' +
            '<p>' + esc(ex.what) + '</p><div class="mbrl-about-grid">' +
            '<div class="mbrl-about-box"><b>' + esc(this.t('Columns you get')) + '</b><span class="is-mono">' + esc(ex.cols) + '</span></div>' +
            '<div class="mbrl-about-box"><b>' + esc(this.t('Change it like this')) + '</b><span>' + esc(ex.tip) + '</span></div>' +
            '</div></section>';
    };

    App.prototype.renderHelp = function () {
        var self = this, h = '<aside class="mbrl-help">';
        h += '<section class="mbrl-card"><span class="mbrl-help-title" style="color:#17111f"><span style="color:#7a0d8e">' +
            icon('help', 18) + '</span>' + esc(this.t('How to write a report query')) + '</span>';
        (this.cfg.steps || []).forEach(function (s) {
            h += '<div class="mbrl-step"><span class="mbrl-step-ico">' + icon(s.icon, 14, 2.4) + '</span><span class="mbrl-step-text"><b>' +
                esc(s.title) + '</b><span>' + esc(s.text) + '</span></span></div>';
        });
        h += '</section><section class="mbrl-card mbrl-tables"><span class="mbrl-help-title">' + esc(this.t('Where the data is')) + '</span>';
        (this.cfg.tables || []).forEach(function (t) {
            h += '<div class="mbrl-tbl"><code>' + esc(t.table) + '</code><span>' + esc(t.text) + '</span></div>';
        });
        h += '</section><section class="mbrl-traps"><b>' + esc(this.t('Common mistakes')) + '</b>';
        (this.cfg.traps || []).forEach(function (t) {
            h += '<span>• ' + esc(t) + '</span>';
        });
        return h + '</section></aside>' + (self ? '' : '');
    };

    App.prototype.renderSchedule = function () {
        var self = this, d = this.st.draft, s = d.schedule, can = this.cfg.acl.schedule, dis = can ? '' : ' disabled';
        var h = '<section class="mbrl-card mbrl-sched"><div class="mbrl-sched-head"><h3 class="mbrl-h">' + esc(this.t('Send automatically')) + '</h3>' +
            '<label class="mbrl-switch"><input type="checkbox" data-sched="on"' + (d.cron_status ? ' checked' : '') + dis + '> ' + esc(this.t('On')) + '</label></div>';
        h += '<div class="' + (d.cron_status ? '' : 'mbrl-off') + '" style="display:flex;flex-direction:column;gap:22px">';
        h += '<div style="display:flex;flex-direction:column;gap:10px"><span class="mbrl-field-title">' + esc(this.t('How often')) + '</span><div class="mbrl-freqs">';
        [['daily', 'Daily', 'every day'], ['weekly', 'Weekly', 'one day a week'], ['monthly', 'Monthly', 'one date a month'],
            ['custom', 'Custom', 'cron expression']].forEach(function (f) {
            h += '<button type="button" class="mbrl-freq' + (s.frequency === f[0] ? ' is-on' : '') + '" aria-pressed="' + (s.frequency === f[0]) +
                '" data-act="freq" data-freq="' + f[0] + '"' + dis + '><b>' + esc(self.t(f[1])) + '</b><span>' + esc(self.t(f[2])) + '</span></button>';
        });
        h += '</div></div><div class="mbrl-grid3">';
        if (s.frequency === 'custom') {
            h += '<label class="mbrl-field" style="grid-column:span 2">' + esc(this.t('Cron expression')) +
                '<input type="text" class="is-mono" data-sched="cron" value="' + esc(s.cron) + '" placeholder="0 8 * * 1"' + dis + '>' +
                '<small>' + esc(this.t('Times are in store time (%1).', this.cfg.timezone)) + '</small></label>';
        } else {
            h += '<label class="mbrl-field">' + esc(s.frequency === 'monthly' ? this.t('Day of the month') : this.t('Day')) +
                '<select data-sched="day"' + (s.frequency === 'daily' ? ' disabled' : dis) + '>';
            if (s.frequency === 'daily') {
                h += '<option>' + esc(this.t('every day')) + '</option>';
            } else if (s.frequency === 'weekly') {
                ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].forEach(function (n, i) {
                    h += '<option value="' + (i + 1) + '"' + (+s.day === i + 1 ? ' selected' : '') + '>' + esc(self.t(n)) + '</option>';
                });
            } else {
                for (var day = 1; day <= 28; day++) {
                    h += '<option value="' + day + '"' + (+s.day === day ? ' selected' : '') + '>' + day + '</option>';
                }
            }
            h += '</select></label><label class="mbrl-field">' + esc(this.t('Time')) +
                '<input type="time" data-sched="time" value="' + esc(s.time) + '" step="60"' + dis + '>' +
                '<small>' + esc(this.t('Store time (%1)', this.cfg.timezone)) + '</small></label>';
        }
        h += '<label class="mbrl-field">' + esc(this.t('Data covers')) + '<select data-sched="range"' + dis + '>';
        this.cfg.ranges.forEach(function (r) {
            h += '<option value="' + esc(r.value) + '"' + (s.range === r.value ? ' selected' : '') + '>' + esc(r.label) + '</option>';
        });
        h += '</select><small>' + esc(this.t('Only the scheduled run uses this. Run and downloads use the parameter values above.')) + '</small></label></div>';
        h += '<div class="mbrl-next"><span style="color:#7a0d8e;flex:none">' + icon('clock', 18) + '</span><span data-role="next">' +
            this.nextHtml() + '</span><button type="button" class="mbrl-link-btn" data-act="freq" data-freq="' +
            (s.frequency === 'custom' ? 'weekly' : 'custom') + '"' + dis + '>' +
            esc(s.frequency === 'custom' ? this.t('Use the simple schedule') : this.t('Use a cron expression')) + '</button></div>';
        h += '<div style="display:flex;flex-direction:column;gap:8px"><span class="mbrl-field-title" id="mbrl-rcpt-t">' + esc(this.t('Recipients')) + '</span>' +
            '<div class="mbrl-rcpt" data-role="rcpt">';
        d.recipients.forEach(function (m, i) {
            h += '<span class="mbrl-rcpt-chip">' + esc(m) + (can ? '<button type="button" data-act="unrcpt" data-i="' + i + '" aria-label="' +
                esc(self.t('Remove %1', m)) + '">×</button>' : '') + '</span>';
        });
        h += '<label for="mbrl-addm" class="mbrl-sr">' + esc(this.t('Add an e-mail…')) + '</label>' +
            '<input id="mbrl-addm" type="email" autocomplete="off" placeholder="' + esc(this.t('Add an e-mail…')) + '"' + dis + '></div></div>';
        h += '<div class="mbrl-grid2"><label class="mbrl-field">' + esc(this.t('File')) + '<select data-sched="export_type"' + dis + '>';
        var names = {csv: 'CSV (.csv)', xlsx: 'Excel (.xlsx)', json: 'JSON (.json)', html: 'HTML (.html)', pdf: 'PDF (.pdf)'};
        this.cfg.formats.forEach(function (f) {
            h += '<option value="' + esc(f.key) + '"' + (d.export_type === f.key ? ' selected' : '') + '>' + esc(names[f.key]) + '</option>';
        });
        h += '</select></label><label class="mbrl-field">' + esc(this.t('Delivery')) + '<select data-sched="delivery"' + dis + '>' +
            '<option value="attach"' + (d.delivery === 'attach' ? ' selected' : '') + '>' + esc(this.t('Attach to the e-mail')) + '</option>' +
            '<option value="link"' + (d.delivery === 'link' ? ' selected' : '') + '>' + esc(this.t('Download link (%1 days)', this.cfg.linkDays)) + '</option>' +
            '</select></label></div></div>';
        h += '<div class="mbrl-sched-foot"><label class="mbrl-check"><input type="checkbox" data-sched="skip"' + (d.skip_if_empty ? ' checked' : '') + dis + '> ' +
            esc(this.t("Don't send when there are no rows")) + '</label>' +
            (can ? '<button type="button" class="mbrl-btn" data-act="test"' + (this.st.busy === 'test' ? ' disabled' : '') + '>' +
                esc(this.st.busy === 'test' ? this.t('Sending…') : this.t('Send a test to me')) + '</button>' : '') + '</div>';
        return h + '</section>';
    };

    App.prototype.nextHtml = function () {
        var n = this.st.next;
        if (this.st.nextError) { return '<span style="color:#8a1c1c">' + esc(this.st.nextError) + '</span>'; }
        if (!this.st.draft.cron_status) { return esc(this.t('The schedule is off. Turn it on to send automatically.')); }
        if (!n || !n.next) { return '—'; }
        return esc(this.t('Next send:')) + ' <b>' + esc(n.next) + '</b>' + (n.covering ? ' — ' + esc(this.t('covering %1', n.covering)) : '');
    };

    App.prototype.renderNext = function () {
        var box = this.body.querySelector('[data-role="next"]');
        if (box) { box.innerHTML = this.nextHtml(); }
    };

    App.prototype.renderHistory = function () {
        var self = this, r = this.st.report, runs = r ? r.history : [];
        var h = '<section class="mbrl-card mbrl-hist"><h3 class="mbrl-h">' + esc(this.t('Run history')) + '</h3>';
        if (!runs.length) {
            h += '<div class="mbrl-empty">' + esc(this.t('No runs yet.')) + '</div>';
        }
        runs.forEach(function (x) {
            var dot = x.result === 'sent' && x.trigger === 'test' ? 'test' : x.result;
            h += '<div class="mbrl-run"><span class="mbrl-run-dot mbrl-run-' + esc(dot) + '"></span><span class="mbrl-run-text"><b>' +
                esc(x.message) + '</b><span>' + esc(x.when) + ' · ' + esc(self.t(x.trigger)) + ' · ' +
                esc(x.rows === 1 ? self.t('1 row') : self.t('%1 rows', x.rows)) + '</span></span>' +
                (x.download ? '<a href="' + esc(x.download) + '">' + icon('download', 14) + ' ' + esc(x.file.toUpperCase()) + '</a>'
                    : '<span class="mbrl-run-file">—</span>') + '</div>';
        });
        if (this.cfg.acl.edit && r) {
            h += '<div style="border-top:1px solid #f0ebf5;padding-top:12px;margin-top:6px"><button type="button" class="mbrl-btn mbrl-btn-quiet" data-act="delete">' +
                esc(this.t('Delete')) + '</button></div>';
        }
        return h + '</section>';
    };

    /* ---------- events ---------- */

    App.prototype.afterRender = function () {
        var ta = this.body.querySelector('#mbrl-sql');
        if (ta) { ta.style.height = ''; }
    };

    App.prototype.markDirty = function () {
        var b = this.body.querySelector('[data-role="dirty"]');
        if (b) { b.hidden = !(this.st.mode === 'new' || this.dirty()); }
    };

    App.prototype.addRecipient = function (input) {
        var self = this, parts = input.value.split(/[,;\s]+/).filter(Boolean), d = this.st.draft, bad = null;
        parts.forEach(function (p) {
            if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(p)) { bad = p; return; }
            if (d.recipients.map(function (x) { return x.toLowerCase(); }).indexOf(p.toLowerCase()) < 0) { d.recipients.push(p); }
        });
        if (bad) {
            this.toast(this.t('"%1" is not a valid e-mail address.', bad), true);
            input.value = bad;
        } else {
            input.value = '';
        }
        if (parts.length) {
            this.render();
            var again = self.body.querySelector('#mbrl-addm');
            if (again) { again.focus(); }
        }
    };

    App.prototype.bind = function () {
        var self = this, body = this.body;

        body.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-act]');
            if (!btn || btn.disabled) { return; }
            var act = btn.getAttribute('data-act'), st = self.st;
            if (act === 'open') { self.open(+btn.getAttribute('data-id')); }
            if (act === 'new') { self.startNew('blank'); }
            if (act === 'library') { self.startNew(btn.getAttribute('data-key')); }
            if (act === 'example') { self.pickExample(btn.getAttribute('data-key')); }
            if (act === 'tab') { st.tab = btn.getAttribute('data-tab'); self.render(); if (st.tab === 'schedule') { self.refreshNext(); } }
            if (act === 'save') { self.save(); }
            if (act === 'duplicate') { self.duplicate(); }
            if (act === 'delete') { self.remove(); }
            if (act === 'run') { self.run(); }
            if (act === 'export') { self.exportFile(btn.getAttribute('data-format'), btn); }
            if (act === 'chart') { st.chart = !st.chart; self.renderResult(); }
            if (act === 'test') { self.sendTest(); }
            if (act === 'format') {
                st.draft.query = formatSql(st.draft.query);
                self.render();
                self.markDirty();
            }
            if (act === 'freq') {
                var f = btn.getAttribute('data-freq'), s = st.draft.schedule;
                if (f === 'custom' && s.frequency !== 'custom') { s.cron = s.cron || '0 8 * * 1'; }
                if (f === 'monthly' && (+s.day < 1 || +s.day > 28)) { s.day = 1; }
                if (f === 'weekly' && (+s.day < 1 || +s.day > 7)) { s.day = 1; }
                s.frequency = f;
                self.render();
                self.markDirty();
                self.refreshNext();
            }
            if (act === 'unrcpt') {
                st.draft.recipients.splice(+btn.getAttribute('data-i'), 1);
                self.render();
                self.markDirty();
            }
        });

        body.addEventListener('input', function (e) {
            var t = e.target, st = self.st;
            if (t.id === 'mbrl-q') {
                st.q = t.value;
                var pos = t.selectionStart;
                self.render();
                var q = body.querySelector('#mbrl-q');
                q.focus();
                q.setSelectionRange(pos, pos);
                return;
            }
            if (!st.draft) { return; }
            if (t.id === 'mbrl-name') { st.draft.name = t.value; }
            if (t.id === 'mbrl-sql') {
                var before = paramNames(st.draft.query).join(',');
                st.draft.query = t.value;
                body.querySelector('[data-role="hl"]').innerHTML = highlight(t.value) + '\n';
                body.querySelector('[data-role="lines"]').textContent = self.lineNumbers(t.value);
                if (paramNames(t.value).join(',') !== before) {
                    self.fillParams();
                    body.querySelector('[data-role="params"]').innerHTML = self.paramsHtml();
                }
            }
            if (t.hasAttribute('data-param') && t.tagName === 'INPUT') { st.draft.params[t.getAttribute('data-param')] = t.value; }
            if (t.getAttribute('data-sched') === 'cron') { st.draft.schedule.cron = t.value; self.refreshNext(); }
            self.markDirty();
        });

        body.addEventListener('change', function (e) {
            var t = e.target, st = self.st, d = st.draft, k = t.getAttribute('data-sched');
            if (!d) { return; }
            if (t.hasAttribute('data-param')) {
                var p = t.getAttribute('data-param');
                if (t.getAttribute('data-role') === 'preset') {
                    if (t.value === 'date') {
                        var now = new Date();
                        d.params[p] = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
                    } else {
                        d.params[p] = t.value;
                    }
                    body.querySelector('[data-role="params"]').innerHTML = self.paramsHtml();
                } else {
                    d.params[p] = t.value;
                }
            }
            if (k === 'on') { d.cron_status = t.checked ? 1 : 0; self.render(); self.refreshNext(); }
            if (k === 'day') { d.schedule.day = +t.value; self.refreshNext(); }
            if (k === 'time') { d.schedule.time = t.value; self.refreshNext(); }
            if (k === 'range') { d.schedule.range = t.value; self.refreshNext(); }
            if (k === 'export_type') { d.export_type = t.value; }
            if (k === 'delivery') { d.delivery = t.value; }
            if (k === 'skip') { d.skip_if_empty = t.checked ? 1 : 0; }
            self.markDirty();
        });

        body.addEventListener('keydown', function (e) {
            var t = e.target;
            if (t.id === 'mbrl-sql') {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); self.run(); }
                if (e.key === 'Tab' && !e.shiftKey && !t.readOnly) {
                    e.preventDefault();
                    var a = t.selectionStart, b = t.selectionEnd;
                    t.value = t.value.slice(0, a) + '    ' + t.value.slice(b);
                    t.selectionStart = t.selectionEnd = a + 4;
                    t.dispatchEvent(new Event('input', {bubbles: true}));
                }
            }
            if (t.id === 'mbrl-addm') {
                if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); self.addRecipient(t); self.markDirty(); }
                if (e.key === 'Backspace' && t.value === '' && self.st.draft.recipients.length) {
                    self.st.draft.recipients.pop();
                    self.render();
                    body.querySelector('#mbrl-addm').focus();
                    self.markDirty();
                }
            }
            if (t.id === 'mbrl-name' && e.key === 'Enter' && self.cfg.acl.edit) { e.preventDefault(); self.save(); }
        });

        body.addEventListener('focusout', function (e) {
            if (e.target.id === 'mbrl-addm' && e.target.value.trim()) { self.addRecipient(e.target); self.markDirty(); }
        });

        window.addEventListener('beforeunload', function (e) {
            if (self.dirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's' && self.st.draft && self.cfg.acl.edit &&
                self.el.contains(document.activeElement)) {
                e.preventDefault();
                self.save();
            }
        });
    };

    return function (options, el) {
        var cfg = typeof options.config === 'string' ? JSON.parse(options.config) : options.config;
        return new App(el, cfg);
    };
});
