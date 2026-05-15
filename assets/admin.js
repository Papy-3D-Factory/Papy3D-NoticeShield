

(function(){
	'use strict';
	if (!window.papy3dNs || !window.papy3dNs.ajaxUrl || !window.papy3dNs.captureNonce) { return; }
	if (window.papy3dNs.pausedUntil && Math.floor(Date.now() / 1000) < Number(window.papy3dNs.pausedUntil)) { return; }
	if (document.body && document.body.classList && document.body.classList.contains('tools_page_papy3d-noticeshield')) { return; }

	function isCoreAdminWorkflowUrl(url) {
		try {
			var u = new URL(url || window.location.href, window.location.href);
			var file = u.pathname.split('/').pop();
			if (['update.php', 'update-core.php', 'plugin-install.php', 'theme-install.php', 'site-health.php'].indexOf(file) !== -1) { return true; }
			if (file === 'plugins.php' && u.searchParams.get('action') === 'upload-plugin') { return true; }
			if (file === 'themes.php' && u.searchParams.get('action') === 'upload-theme') { return true; }
		} catch (e) {}
		return false;
	}


	var processed = new WeakSet();
	var signatures = new Set();

	/* Only root containers may receive Papy3D NoticeShield controls. Broad selectors are
	 * discovery-only; they are resolved to a root before processing. */
	var rootSelectors = [
		'.notice', '.updated', '.error', '.update-nag', '.admin-notice',
		'.papy3d-ns-captured-notice',
		'div[data-notice]', 'div[data-notification]', 'div[data-notice-id]',
		'div[data-cp-notification-name]',
		'section[data-notice]', 'section[data-notification]', 'aside[data-notice]'
	].join(',');
	var discoverySelectors = [
		rootSelectors,
		'div[class*="notice"]', 'div[class*="notification"]', 'div[class*="nag"]',
		'div[class*="alert"]', 'div[class*="promo"]',
		'section[class*="notice"]', 'section[class*="notification"]', 'aside[class*="notice"]'
	].join(',');
	var excluded = [
		'#adminmenumain', '#wpadminbar', '#screen-meta', '#screen-meta-links',
		'.papy3d-ns-wrap', '.papy3d-ns-captured-notice', '.papy3d-ns-decision', '.papy3d-ns-card', '.papy3d-ns-actions', '.papy3d-ns-preview',
		'.papy3d-ns-card-preview', '.papy3d-ns-notice-template', '.notice-dismiss',
		'.update-message', '.plugin-update-tr', '.theme-overlay', '.media-modal',
		'.components-modal__frame', '.interface-interface-skeleton__sidebar'
	].join(',');

	function isPapy3DAncElement(el) {
		return !!(
			el && el.nodeType === 1 && (
				(el.className && String(el.className).indexOf('papy3d-ns-') !== -1) ||
				(el.closest && el.closest('.papy3d-ns-wrap, .papy3d-ns-captured-notice, .papy3d-ns-decision, .papy3d-ns-card, .papy3d-ns-actions, .papy3d-ns-preview, .papy3d-ns-card-preview, .papy3d-ns-notice-template, #papy3d-ns-app, [data-papy3d-ns-internal="1"]'))
			)
		);
	}

	function isRootNoticeElement(el) {
		return !!(el && el.nodeType === 1 && el.matches && el.matches(rootSelectors));
	}

	function normalizeNoticeRoot(el) {
		if (!el || el.nodeType !== 1 || isPapy3DAncElement(el)) { return null; }
		if (el.closest && el.closest('.papy3d-ns-captured-notice')) { return null; }

		var root = el.closest ? el.closest(rootSelectors) : null;
		if (root && !isPapy3DAncElement(root)) { return root; }

		var tag = String(el.tagName || '').toLowerCase();
		if (!/^(div|section|aside)$/i.test(tag)) { return null; }
		if (!/(notice|notification|admin-notice|nag|alert|promo)/i.test(String(el.className || ''))) { return null; }

		var parent = el.parentElement;
		while (parent && parent !== document.body) {
			if (isPapy3DAncElement(parent)) { return null; }
			if (parent.matches && (parent.matches(rootSelectors) || /(notice|notification|admin-notice|nag|alert|promo)/i.test(String(parent.className || '')))) {
				return null;
			}
			parent = parent.parentElement;
		}
		return el;
	}

	function normalizeClientText(text) {
		return String(text || '')
			.toLowerCase()
			.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
			.replace(/https?:\/\/\S+/g, '')
			.replace(/[?&](?:_wpnonce|nonce|token|key|signature|ver|version|cache|time|timestamp|rand|r|papy3d_ns_updated|gt_int|gtranslate_admin_notice_ignore|gtranslate_admin_notice_temp_ignore)=[^\s&]+/g, '')
			.replace(/\b[a-f0-9]{8,}\b/gi, '')
			.replace(/\b\d{4}-\d{2}-\d{2}\b/g, '')
			.replace(/\b\d{1,2}:\d{2}(?::\d{2})?\b/g, '')
			.replace(/\b\d{4,}\b/g, '')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function noticeFamilyKey(el) {
		var html = String(el.outerHTML || '').toLowerCase();
		var text = normalizeClientText(el.textContent || '');
		if (html.indexOf('gt-admin-notice') !== -1 || text.indexOf('upgrading your gtranslate') !== -1) { return 'family:gtranslate-upgrade-tips'; }
		if (html.indexOf('ctc-welcome-notice') !== -1 || (text.indexOf('copy anything to clipboard updated') !== -1 && text.indexOf('telemetry opt-in') !== -1)) { return 'family:ctc-welcome-updated'; }
		if (html.indexOf('fs-notice') !== -1 && (html.indexOf('fs-slug-ctc') !== -1 || text.indexOf('copy anything to clipboard') !== -1)) { return 'family:freemius-ctc-notice'; }
		if (html.indexOf('filebird-empty-folder-notice') !== -1) { return 'family:filebird-empty-folder-notice'; }
		if (html.indexOf('adbc-rating-notice') !== -1 || text.indexOf('advanced db cleaner') !== -1) { return 'family:advanced-db-cleaner-rating'; }
		if (html.indexOf('dev-warning-notice') !== -1 || text.indexOf('site de developpement') !== -1) { return 'family:development-site-warning'; }
		return '';
	}

	function isCoreNoticeText(text) {
		var normalized = normalizeClientText(text);
		var patterns = [
			'plugin activated', 'plugin deactivated', 'plugin deleted', 'plugin updated successfully', 'plugin installed successfully',
			'theme activated', 'theme updated successfully', 'theme installed successfully',
			'wordpress has been updated', 'automatic update', 'update complete', 'settings saved',
			'this plugin is already installed', 'do you want to replace the current', 'upload plugin',
			'upload theme', 'installation de l extension', 'extension activee', 'extension activée',
			'extension desactivee', 'extension désactivée', 'extension supprimee', 'extension supprimée',
			'extension mise a jour', 'extension mise à jour', 'extension installee', 'extension installée',
			'cette extension est deja installee', 'cette extension est déjà installée', 'voulez vous remplacer',
			'voulez-vous remplacer', 'televerser une extension', 'téléverser une extension',
			'mise a jour de wordpress', 'mise à jour de wordpress', 'theme active', 'thème activé',
			'theme installe', 'thème installé', 'parametres enregistres', 'paramètres enregistrés'
		];
		return patterns.some(function(pattern){ return normalized.indexOf(pattern) !== -1; });
	}


	function isCoreNoticeElement(el) {
		if (!el || el.nodeType !== 1) { return false; }
		var id = String(el.id || '').toLowerCase();
		var cls = String(el.className || '').toLowerCase();
		var text = normalizeClientText(el.textContent || '');
		if (id === 'message' && /^(extension activee|extension activée|plugin activated|settings saved|parametres enregistres|paramètres enregistrés)/.test(text)) { return true; }
		if (cls.indexOf('settings-error') !== -1 && /settings saved|parametres enregistres|paramètres enregistrés/.test(text)) { return true; }
		return false;
	}

	function localKey(el) {
		var family = noticeFamilyKey(el);
		if (family) { return family; }
		var source = sourceFromElement(el);
		var text = normalizeClientText(el.textContent);
		if (/(upgrade|premium|pro|pricing|forfaits|rate|review|telemetry|opt in|help improve|dismiss|remind|plus tard|ne plus afficher)/i.test(text) && source && source !== 'client-dom') { return 'auto-source:' + source; }
		var idBits = [el.getAttribute('data-cp-notification-name'), el.getAttribute('data-notice'), el.getAttribute('data-notification'), el.getAttribute('data-notice-id')].filter(Boolean).join('|');
		return idBits + '|' + sourceFromElement(el) + '|' + normalizeClientText(el.textContent).slice(0, 260);
	}

	function clientKnownDecision(key) {
		var map = (window.papy3dNs && window.papy3dNs.knownDecisions) ? window.papy3dNs.knownDecisions : {};
		return map && Object.prototype.hasOwnProperty.call(map, key) ? String(map[key]) : '';
	}

	function isVisible(el) {
		var style = window.getComputedStyle(el);
		return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && el.getClientRects().length > 0;
	}

	function isValidNotice(el) {
		if (!el || el.nodeType !== 1 || processed.has(el)) { return false; }
		if (isPapy3DAncElement(el)) { return false; }
		if (!isRootNoticeElement(el) && !/^(div|section|aside)$/i.test(String(el.tagName || ''))) { return false; }
		if (el.getAttribute('data-papy3d-ns-processed-root') === '1') { return false; }
		if (el.closest && el.closest('[data-papy3d-ns-processed-root="1"]')) { return false; }
		if (el.closest && el.closest(excluded)) { return false; }
		if (el.querySelector && el.querySelector('.papy3d-ns-decision, [data-papy3d-ns-internal="1"]')) { return false; }
		if (!isVisible(el)) { return false; }
		var text = normalizeClientText(el.textContent || '');
		if (text.length < 15) { return false; }
		if (isCoreNoticeElement(el)) { return false; }
		if (isCoreNoticeText(el.textContent || '')) { return false; }
		var rect = el.getBoundingClientRect();
		if (rect.width < 180 || rect.height < 18) { return false; }
		if (el.querySelector && el.querySelector('form, table, .wp-list-table, #poststuff') && !/(notice|notification|nag|alert|promo|message|update-nag)/i.test(el.className || '')) { return false; }
		return true;
	}

	function sourceFromElement(el) {
		var attrs = ['data-cp-notification-name', 'data-notice', 'data-notification', 'data-notice-id'];
		for (var i = 0; i < attrs.length; i++) {
			var v = el.getAttribute(attrs[i]);
			if (v) { return v; }
		}
		var cls = String(el.className || '').match(/([a-z0-9-]+)-(?:notice|notification|admin-notice|nag|alert|promo|message)/i);
		return cls && cls[1] ? cls[1] : 'client-dom';
	}

	function appendHidden(form, name, value) {
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = name;
		input.value = value || '';
		form.appendChild(input);
	}

	function addDecisionButton(form, value, label, primary) {
		var button = document.createElement('button');
		button.type = 'submit';
		button.name = 'decision';
		button.value = value;
		button.className = primary ? 'button button-primary' : 'button button-secondary';
		button.title = label;
		button.textContent = label;
		form.appendChild(button);
	}

	function addDecisionBox(el, response) {
		if (!response || !response.signature || !el || isPapy3DAncElement(el)) { return; }
		if (el.querySelector('.papy3d-ns-decision')) {
			el.setAttribute('data-papy3d-ns-status', 'pending');
			return;
		}

		var labels = (window.papy3dNs && window.papy3dNs.i18n) ? window.papy3dNs.i18n : {};
		var wrapper = document.createElement('div');
		wrapper.className = 'papy3d-ns-decision';
		wrapper.setAttribute('role', 'region');
		wrapper.setAttribute('aria-label', String(labels.noticeDecision || 'Notice decision'));
		wrapper.setAttribute('data-papy3d-ns-internal', '1');

		var form = document.createElement('form');
		form.className = 'papy3d-ns-decision-form';
		form.method = 'post';
		form.action = (window.papy3dNs && window.papy3dNs.adminPostUrl) ? window.papy3dNs.adminPostUrl : 'admin-post.php';

		appendHidden(form, 'action', 'papy3d_ns_notice_decision_post');
		appendHidden(form, 'signature', String(response.signature || ''));
		appendHidden(form, 'nonce', String(response.nonce || (window.papy3dNs ? window.papy3dNs.decisionNonce : '') || ''));
		appendHidden(form, 'redirect_to', window.location.href || '');
		addDecisionButton(form, 'allow', String(labels.allow || 'Allow'), true);
		addDecisionButton(form, 'block', String(labels.block || 'Block'), false);

		var result = document.createElement('span');
		result.className = 'papy3d-ns-result papy3d-ns-muted';
		result.setAttribute('aria-live', 'polite');
		form.appendChild(result);
		wrapper.appendChild(form);

		el.classList.add('papy3d-ns-has-decision');
		el.setAttribute('data-papy3d-ns-status', 'pending');
		el.setAttribute('data-papy3d-ns-signature', String(response.signature || ''));
		el.appendChild(wrapper);
	}

	function handleElement(el) {
		if (!isValidNotice(el)) { return; }
		var key = localKey(el);
		if (signatures.has(key)) { processed.add(el); el.setAttribute('data-papy3d-ns-processed-root', '1'); return; }
		signatures.add(key);
		processed.add(el);
		el.setAttribute('data-papy3d-ns-processed-root', '1');
		var previousVisibility = el.style.visibility || '';
		var knownDecision = clientKnownDecision(key);
		if (knownDecision === 'block') {
			el.setAttribute('data-papy3d-ns-status', 'block');
			el.style.setProperty('display', 'none', 'important');
			el.setAttribute('aria-hidden', 'true');
		} else if (knownDecision === 'allow') {
			el.setAttribute('data-papy3d-ns-status', 'allow');
			el.classList.remove('papy3d-ns-has-decision');
		} else {
			el.setAttribute('data-papy3d-ns-status', 'pending');
			el.style.visibility = previousVisibility;
		}

		var body = new window.FormData();
		body.append('action', 'papy3d_ns_capture_client_notice');
		body.append('nonce', window.papy3dNs.captureNonce);
		body.append('html', el.outerHTML || '');
		body.append('source', sourceFromElement(el));
		body.append('context', 'client_dom');
		body.append('url', window.location.href);

		window.fetch(window.papy3dNs.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function(r){ return r.json(); })
			.then(function(resp){
				if (!resp || !resp.success || !resp.data) { el.style.visibility = previousVisibility; return; }
				el.setAttribute('data-papy3d-ns-signature', resp.data.signature || '');
				if (resp.data.decision === 'block') {
					el.setAttribute('data-papy3d-ns-status', 'block');
					el.style.setProperty('display', 'none', 'important');
					el.setAttribute('aria-hidden', 'true');
					return;
				}
				if (resp.data.decision === 'allow') { el.setAttribute('data-papy3d-ns-status', 'allow'); }
				if (resp.data.decision === 'pending') { el.setAttribute('data-papy3d-ns-status', 'pending'); addDecisionBox(el, resp.data); }
				el.style.visibility = previousVisibility;
			})
			.catch(function(){ el.style.visibility = previousVisibility; });
	}

	function hasAcceptedAncestor(el, accepted) {
		return accepted.some(function(parent){ return parent !== el && parent.contains(el); });
	}

	function scan(root) {
		var base = root && root.querySelectorAll ? root : document;
		if (isPapy3DAncElement(base)) { return; }
		var found = [];
		if (base.matches && base.matches(discoverySelectors)) { found.push(base); }
		base.querySelectorAll(discoverySelectors).forEach(function(el){ found.push(el); });

		var roots = [];
		var seen = new WeakSet();
		found.forEach(function(el){
			var root = normalizeNoticeRoot(el);
			if (!root || seen.has(root)) { return; }
			seen.add(root);
			roots.push(root);
		});

		var accepted = [];
		roots.forEach(function(el){
			if (!isValidNotice(el)) { return; }
			if (hasAcceptedAncestor(el, accepted)) { return; }
			accepted.push(el);
		});
		accepted.forEach(handleElement);
	}

	function schedule(root) {
		window.requestAnimationFrame(function(){ scan(root || document); });
	}

	schedule(document);
	if (window.MutationObserver) {
		new window.MutationObserver(function(mutations){
			mutations.forEach(function(mutation){
				mutation.addedNodes.forEach(function(node){ if (node && node.nodeType === 1) { schedule(node); } });
			});
		}).observe(document.getElementById('wpbody-content') || document.body, { childList: true, subtree: true });
	}
}());

(function(){
	'use strict';
	document.addEventListener('submit', function(e){
		var btn = e.submitter || (e.target && e.target.querySelector ? e.target.querySelector('[data-confirm]') : null);
		if (btn && btn.getAttribute && btn.getAttribute('data-confirm')) {
			var msg = btn.getAttribute('data-confirm') || (window.papy3dNs && window.papy3dNs.i18n ? window.papy3dNs.i18n.confirmReset : 'Continue?');
			if (!window.confirm(msg)) { e.preventDefault(); }
		}
	}, true);
})();

(function(){
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
			return;
		}
		fn();
	}

	function normalize(value) {
		return String(value || '')
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '')
			.trim();
	}

	ready(function(){
		var wrap = document.querySelector('.papy3d-ns-wrap');
		if (!wrap) { return; }

		var activeStatus = 'all';
		var searchInput = wrap.querySelector('#papy3d-ns-search');
		var sourceSelect = wrap.querySelector('#papy3d-ns-source-filter');
		var tabs = Array.prototype.slice.call(wrap.querySelectorAll('.papy3d-ns-tab'));
		var cards = Array.prototype.slice.call(wrap.querySelectorAll('.papy3d-ns-card'));
		var noResult = wrap.querySelector('.papy3d-ns-no-results');

		if (!noResult) {
			noResult = document.createElement('div');
			noResult.className = 'papy3d-ns-empty papy3d-ns-no-results';
			noResult.hidden = true;
			noResult.innerHTML = '<p>No notice matches the current filters.</p>';
			if (cards.length && cards[cards.length - 1].parentNode) {
				cards[cards.length - 1].parentNode.insertBefore(noResult, cards[cards.length - 1].nextSibling);
			}
		}

		function fillSources() {
			if (!sourceSelect) { return; }
			var existing = {};
			Array.prototype.slice.call(sourceSelect.options).forEach(function(option){ existing[option.value] = true; });
			var sources = [];
			cards.forEach(function(card){
				var value = normalize(card.getAttribute('data-source'));
				var label = card.getAttribute('data-source-label') || value;
				if (value && !existing[value]) {
					existing[value] = true;
					sources.push({ value: value, label: label });
				}
			});
			sources.sort(function(a, b){ return a.label.localeCompare(b.label); });
			sources.forEach(function(source){
				var option = document.createElement('option');
				option.value = source.value;
				option.textContent = source.label;
				sourceSelect.appendChild(option);
			});
		}

		function applyFilters() {
			var query = normalize(searchInput ? searchInput.value : '');
			var selectedSource = normalize(sourceSelect ? sourceSelect.value : '');
			var visibleCount = 0;

			cards.forEach(function(card){
				var status = normalize(card.getAttribute('data-status') || 'pending');
				var source = normalize(card.getAttribute('data-source'));
				var searchable = normalize(card.getAttribute('data-search') || card.textContent || '');
				var statusMatch = activeStatus === 'all' || status === activeStatus;
				var sourceMatch = !selectedSource || source === selectedSource;
				var searchMatch = !query || searchable.indexOf(query) !== -1;
				var show = statusMatch && sourceMatch && searchMatch;
				card.hidden = !show;
				card.classList.toggle('is-filtered-out', !show);
				if (show) { visibleCount++; }
			});

			if (noResult) { noResult.hidden = visibleCount !== 0 || cards.length === 0; }
		}

		function togglePreview(button) {
			var card = button.closest('.papy3d-ns-card');
			if (!card) { return; }
			var preview = card.querySelector('.papy3d-ns-card-preview');
			var template = card.querySelector('.papy3d-ns-notice-template');
			if (!preview || !template) { return; }

			var willOpen = preview.hidden;
			if (willOpen && !preview.getAttribute('data-loaded')) {
				preview.innerHTML = template.value || '';
				preview.setAttribute('data-loaded', '1');
			}
			preview.hidden = !willOpen;
			button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			button.textContent = willOpen ? (button.getAttribute('data-hide-label') || 'Hide notice') : (button.getAttribute('data-show-label') || 'View notice');
		}

		fillSources();
		applyFilters();

		tabs.forEach(function(tab){
			tab.addEventListener('click', function(){
				activeStatus = normalize(tab.getAttribute('data-filter') || 'all');
				tabs.forEach(function(item){
					var isActive = item === tab;
					item.classList.toggle('is-active', isActive);
					item.setAttribute('aria-selected', isActive ? 'true' : 'false');
				});
				applyFilters();
			});
		});

		if (searchInput) {
			searchInput.addEventListener('input', applyFilters);
			searchInput.addEventListener('search', applyFilters);
		}

		if (sourceSelect) {
			sourceSelect.addEventListener('change', applyFilters);
		}

		wrap.addEventListener('click', function(event){
			var button = event.target && event.target.closest ? event.target.closest('.papy3d-ns-toggle-preview') : null;
			if (!button || !wrap.contains(button)) { return; }
			event.preventDefault();
			togglePreview(button);
		});
	});
}());
