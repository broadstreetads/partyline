/**
 * Partyline PWA — client app.
 *
 * Flow: photo (+ filter) and/or voice -> record -> 16kHz WAV -> /transcribe
 * -> /generate (AI title/body) -> editable preview -> /submit -> draft post.
 */
(function () {
	'use strict';

	var CFG = window.PARTYLINE_PWA || {};
	var $ = function (sel) { return document.querySelector(sel); };
	var IS_ANON = !!CFG.anon; // anonymous submitter: no dictation, needs name/email + Turnstile

	function turnstileToken() {
		var el = document.querySelector('#pl-turnstile [name="cf-turnstile-response"]') ||
			document.querySelector('[name="cf-turnstile-response"]');
		return el ? el.value : '';
	}

	/* --------------------------------------------------------------- */
	/* REST helpers                                                     */
	/* --------------------------------------------------------------- */
	function api(path, opts) {
		opts = opts || {};
		opts.headers = opts.headers || {};
		opts.headers['X-WP-Nonce'] = CFG.nonce;
		opts.credentials = 'same-origin';
		return fetch(CFG.restBase + path, opts).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					throw new Error(data && data.message ? data.message : ('Request failed (' + res.status + ')'));
				}
				return data;
			});
		});
	}
	window.PartylineAPI = api;

	/* --------------------------------------------------------------- */
	/* State + screen navigation                                        */
	/* --------------------------------------------------------------- */
	var state = { photoImg: null, filter: 'none', transcript: '', title: '', body: '' };

	var FILTERS = {
		none:  'none',
		bw:    'grayscale(1) contrast(1.06)',
		warm:  'saturate(1.25) sepia(0.12) brightness(1.02)',
		cool:  'saturate(1.1) hue-rotate(-12deg) brightness(1.03)',
		vivid: 'saturate(1.55) contrast(1.12)'
	};

	function show(id) {
		var screens = document.querySelectorAll('.pl-screen');
		for (var i = 0; i < screens.length; i++) {
			screens[i].classList.toggle('pl-hidden', screens[i].id !== id);
		}
		window.scrollTo(0, 0);
	}

	/* --------------------------------------------------------------- */
	/* Photo capture + filters                                          */
	/* --------------------------------------------------------------- */
	var canvas, cctx;

	function handlePhotoFile(file) {
		var url = URL.createObjectURL(file);
		var img = new Image();
		img.onload = function () {
			URL.revokeObjectURL(url);
			var max = 1600;
			var scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
			canvas.width = Math.round(img.naturalWidth * scale);
			canvas.height = Math.round(img.naturalHeight * scale);
			cctx = canvas.getContext('2d');
			cctx.drawImage(img, 0, 0, canvas.width, canvas.height);
			state.photoImg = img;
			canvas.classList.remove('pl-hidden');
			$('#pl-filters').classList.remove('pl-hidden');
			$('#pl-photo-camera').innerHTML = '<span>🔄</span> Retake';
			$('#pl-photo-library').innerHTML = '<span>🖼️</span> Replace';
			applyFilter(state.filter);
			updateSubmit();
			scheduleSave();
		};
		img.onerror = function () { URL.revokeObjectURL(url); setStatus('Could not load that image.', 'error'); };
		img.src = url;
	}

	function applyFilter(name) {
		state.filter = name;
		// Live preview via CSS filter on the canvas element (works everywhere).
		canvas.style.filter = FILTERS[name] === 'none' ? '' : FILTERS[name];
		var chips = document.querySelectorAll('.pl-chip');
		for (var i = 0; i < chips.length; i++) {
			chips[i].classList.toggle('is-active', chips[i].getAttribute('data-filter') === name);
		}
		scheduleSave();
	}

	// Bake the current photo + filter into a JPEG blob for upload.
	function bakePhoto() {
		return new Promise(function (resolve) {
			if (!state.photoImg) { resolve(null); return; }
			var c = document.createElement('canvas');
			c.width = canvas.width;
			c.height = canvas.height;
			var x = c.getContext('2d');
			if ('filter' in x) { x.filter = FILTERS[state.filter]; } // no-op filter support on old Safari
			x.drawImage(state.photoImg, 0, 0, c.width, c.height);
			c.toBlob(function (blob) { resolve(blob); }, 'image/jpeg', 0.9);
		});
	}

	/* --------------------------------------------------------------- */
	/* Local drafts (IndexedDB): autosave, revisit, delete              */
	/* --------------------------------------------------------------- */
	var DB_NAME = 'partyline-drafts', STORE = 'drafts', MAX_DRAFTS = 20;
	var idbOK = !!window.indexedDB;
	var draftId = null;   // the draft currently being edited
	var saveTimer = null;
	var thumbUrls = [];   // object URLs to revoke on list re-render

	function openDB() {
		return new Promise(function (resolve, reject) {
			var req = indexedDB.open(DB_NAME, 1);
			req.onupgradeneeded = function () {
				var db = req.result;
				if (!db.objectStoreNames.contains(STORE)) { db.createObjectStore(STORE, { keyPath: 'id' }); }
			};
			req.onsuccess = function () { resolve(req.result); };
			req.onerror = function () { reject(req.error); };
		});
	}
	function dbPut(entry) {
		return openDB().then(function (db) { return new Promise(function (res, rej) {
			var tx = db.transaction(STORE, 'readwrite'); tx.objectStore(STORE).put(entry);
			tx.oncomplete = function () { res(entry); }; tx.onerror = function () { rej(tx.error); };
		}); });
	}
	function dbDelete(id) {
		return openDB().then(function (db) { return new Promise(function (res, rej) {
			var tx = db.transaction(STORE, 'readwrite'); tx.objectStore(STORE).delete(id);
			tx.oncomplete = function () { res(); }; tx.onerror = function () { rej(tx.error); };
		}); });
	}
	function dbAll() {
		return openDB().then(function (db) { return new Promise(function (res, rej) {
			var tx = db.transaction(STORE, 'readonly'); var r = tx.objectStore(STORE).getAll();
			r.onsuccess = function () { res(r.result || []); }; r.onerror = function () { rej(r.error); };
		}); });
	}

	function newId() { return 'd' + Date.now() + '-' + Math.random().toString(36).slice(2, 8); }

	// Unfiltered, downscaled JPEG of the current photo (or null).
	function currentPhotoBlob() {
		return new Promise(function (resolve) {
			if (!state.photoImg || !canvas || canvas.classList.contains('pl-hidden')) { resolve(null); return; }
			canvas.toBlob(function (b) { resolve(b); }, 'image/jpeg', 0.85);
		});
	}

	function hasContent() {
		return !!state.photoImg || $('#pl-title').value.trim() !== '' || $('#pl-body').value.trim() !== '';
	}

	function buildEntry(status, res) {
		return currentPhotoBlob().then(function (photo) {
			return {
				id: draftId,
				status: status,
				wpStatus: status === 'sent' ? ((res && res.published) ? 'publish' : 'draft') : null,
				title: $('#pl-title').value,
				body: $('#pl-body').value,
				filter: state.filter,
				photo: photo,
				editLink: (res && res.edit_link) || '',
				viewLink: (res && res.view_link) || '',
				updatedAt: Date.now()
			};
		});
	}

	function saveDraft() {
		if (!idbOK || !hasContent()) { return Promise.resolve(); }
		if (!draftId) { draftId = newId(); }
		return buildEntry('draft').then(dbPut).then(prune);
	}

	function markSent(res) {
		if (!idbOK) { return Promise.resolve(); }
		if (!draftId) { draftId = newId(); }
		return buildEntry('sent', res).then(dbPut).then(prune);
	}

	function scheduleSave() {
		if (!idbOK) { return; }
		clearTimeout(saveTimer);
		saveTimer = setTimeout(saveDraft, 600);
	}

	function prune() {
		return dbAll().then(function (all) {
			all.sort(function (a, b) { return b.updatedAt - a.updatedAt; });
			return Promise.all(all.slice(MAX_DRAFTS).map(function (e) { return dbDelete(e.id); }));
		});
	}

	function timeAgo(ts) {
		var s = Math.floor((Date.now() - ts) / 1000);
		if (s < 60) { return 'just now'; }
		var m = Math.floor(s / 60); if (m < 60) { return m + 'm ago'; }
		var h = Math.floor(m / 60); if (h < 24) { return h + 'h ago'; }
		return Math.floor(h / 24) + 'd ago';
	}

	function loadDraft(entry) {
		reset();
		draftId = entry.id;
		$('#pl-title').value = entry.title || '';
		$('#pl-body').value = entry.body || '';
		state.transcript = entry.body || '';
		if (entry.photo) {
			var url = URL.createObjectURL(entry.photo);
			var img = new Image();
			img.onload = function () {
				URL.revokeObjectURL(url);
				canvas.width = img.naturalWidth;
				canvas.height = img.naturalHeight;
				cctx = canvas.getContext('2d');
				cctx.drawImage(img, 0, 0, canvas.width, canvas.height);
				state.photoImg = img;
				canvas.classList.remove('pl-hidden');
				$('#pl-filters').classList.remove('pl-hidden');
				$('#pl-photo-camera').innerHTML = '<span>🔄</span> Retake';
				$('#pl-photo-library').innerHTML = '<span>🖼️</span> Replace';
				applyFilter(entry.filter || 'none');
				updateSubmit();
			};
			img.src = url;
		}
		updateSubmit();
		show('screen-capture');
	}

	function renderDrafts() {
		var list = $('#pl-drafts-list');
		thumbUrls.forEach(function (u) { URL.revokeObjectURL(u); });
		thumbUrls = [];
		if (!idbOK) { list.innerHTML = '<p class="pl-hint">Saved drafts aren\'t available on this browser.</p>'; return; }
		list.innerHTML = '<p class="pl-hint">Loading…</p>';
		dbAll().then(function (all) {
			all.sort(function (a, b) { return b.updatedAt - a.updatedAt; });
			all = all.slice(0, MAX_DRAFTS);
			if (!all.length) {
				list.innerHTML = '<p class="pl-hint">Nothing saved yet. Your drafts and sent Partylines show up here.</p>';
				return;
			}
			list.innerHTML = '';
			all.forEach(function (e) { list.appendChild(draftRow(e)); });
		}).catch(function () { list.innerHTML = '<p class="pl-hint">Couldn\'t load saved drafts.</p>'; });
	}

	function draftRow(e) {
		var row = document.createElement('div');
		row.className = 'pl-draft';

		if (e.photo) {
			var u = URL.createObjectURL(e.photo);
			thumbUrls.push(u);
			var im = document.createElement('img');
			im.className = 'pl-draft-thumb';
			im.src = u;
			row.appendChild(im);
		} else {
			var ph = document.createElement('div');
			ph.className = 'pl-draft-thumb pl-draft-noimg';
			ph.textContent = '📝';
			row.appendChild(ph);
		}

		var main = document.createElement('div');
		main.className = 'pl-draft-main';
		var t = document.createElement('div');
		t.className = 'pl-draft-title';
		t.textContent = (e.title && e.title.trim()) || (e.body && e.body.trim().slice(0, 50)) || 'Untitled';
		var meta = document.createElement('div');
		meta.className = 'pl-draft-meta';
		var badge = document.createElement('span');
		if (e.status === 'sent') {
			var pub = e.wpStatus === 'publish';
			badge.className = 'pl-badge ' + (pub ? 'is-published' : 'is-sent');
			badge.textContent = pub ? 'Published' : 'Sent';
		} else {
			badge.className = 'pl-badge is-draft';
			badge.textContent = 'Draft';
		}
		var time = document.createElement('span');
		time.className = 'pl-draft-time';
		time.textContent = timeAgo(e.updatedAt);
		meta.appendChild(badge);
		meta.appendChild(time);
		main.appendChild(t);
		main.appendChild(meta);
		row.appendChild(main);

		var del = document.createElement('button');
		del.className = 'pl-draft-del';
		del.setAttribute('aria-label', 'Delete');
		del.textContent = '✕';
		del.addEventListener('click', function (ev) {
			ev.stopPropagation();
			if (window.confirm('Delete this Partyline from this device?')) { dbDelete(e.id).then(renderDrafts); }
		});
		row.appendChild(del);

		row.addEventListener('click', function () {
			if (e.status === 'sent') {
				var link = e.viewLink || e.editLink;
				if (link) { window.open(link, '_blank'); }
			} else {
				loadDraft(e);
			}
		});
		return row;
	}

	/* --------------------------------------------------------------- */
	/* Voice record -> 16kHz WAV                                        */
	/* --------------------------------------------------------------- */
	var recorder = null, chunks = [], stream = null, recording = false, wakeLock = null;

	function setStatus(msg, kind) {
		var el = $('#pl-rec-status');
		if (!el) { return; } // no dictation UI (anonymous mode)
		el.textContent = msg;
		el.className = 'pl-status' + (kind ? ' is-' + kind : '');
	}

	// Keep the screen awake while recording so a long interview isn't cut off by
	// the device's auto-lock (which also suspends the page/recorder on iOS).
	function requestWakeLock() {
		if (!('wakeLock' in navigator)) { return; }
		navigator.wakeLock.request('screen').then(function (wl) {
			wakeLock = wl;
		}).catch(function () { /* non-fatal: not visible, or unsupported */ });
	}

	function releaseWakeLock() {
		if (wakeLock) {
			wakeLock.release().catch(function () {});
			wakeLock = null;
		}
	}

	// Acquire the mic once and keep it for the whole capture session, so tapping
	// record again doesn't re-prompt / re-acquire the device. Released only when
	// the contributor leaves the capture flow (submit / cancel / send another).
	function ensureStream() {
		if (stream && stream.active) { return Promise.resolve(stream); }
		return navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
			stream = s;
			return s;
		});
	}

	function releaseStream() {
		if (stream) {
			stream.getTracks().forEach(function (t) { t.stop(); });
			stream = null;
		}
	}

	function toggleRecord() {
		if (recording) { stopRecording(); return; }
		if (!navigator.mediaDevices || !window.MediaRecorder) {
			setStatus('Recording is not supported on this browser — tap "Write it myself" to type your story.', 'error');
			return;
		}
		ensureStream().then(function (s) {
			chunks = [];
			recorder = new MediaRecorder(s);
			recorder.ondataavailable = function (e) { if (e.data && e.data.size) { chunks.push(e.data); } };
			recorder.onstop = onRecordingStopped;
			recorder.start();
			recording = true;
			$('#pl-rec-btn').classList.add('is-recording');
			setStatus('Recording… tap to stop.', 'busy');
			requestWakeLock();
		}).catch(function () {
			setStatus('Microphone access was blocked. Allow it in your browser settings, or tap "Write it myself".', 'error');
		});
	}

	function stopRecording() {
		recording = false;
		$('#pl-rec-btn').classList.remove('is-recording');
		if (recorder && recorder.state !== 'inactive') { recorder.stop(); }
		releaseWakeLock();
		// Intentionally keep the mic stream open — released on exit via releaseStream().
	}

	function onRecordingStopped() {
		var type = chunks[0] ? chunks[0].type : ((recorder && recorder.mimeType) || 'audio/webm');
		var blob = new Blob(chunks, { type: type });
		setStatus('Transcribing…', 'busy');
		$('#pl-rec-btn').setAttribute('disabled', 'disabled');

		// Upload the native recording (webm/mp4/…) — OpenAI Whisper accepts it
		// directly, so no client-side resampling/encoding is needed.
		var fd = new FormData();
		fd.append('audio', blob, 'recording.' + extForType(type));

		api('transcribe', { method: 'POST', body: fd }).then(function (res) {
			state.transcript = res.text || '';
			setStatus('Writing it up…', 'busy');
			// Send the transcript + the photo (if any) so the model can use both.
			var gfd = new FormData();
			gfd.append('transcript', state.transcript);
			if (state.photoImg) {
				return bakePhoto().then(function (blob) {
					if (blob) { gfd.append('image', blob, 'photo.jpg'); }
					return api('generate', { method: 'POST', body: gfd });
				});
			}
			return api('generate', { method: 'POST', body: gfd });
		}).then(function (gen) {
			if (gen.title) { $('#pl-title').value = gen.title; }
			$('#pl-body').value = gen.body || state.transcript || '';
			setStatus('✓ Written up below — edit if needed, or tap record to redo.', null);
			updateSubmit();
			scheduleSave();
		}).catch(function (err) {
			setStatus(err.message || 'Transcription failed.', 'error');
		}).then(function () {
			$('#pl-rec-btn').removeAttribute('disabled');
		});
	}

	// Map a MediaRecorder mime type to a file extension Whisper recognizes.
	function extForType(t) {
		t = (t || '').toLowerCase();
		if (t.indexOf('webm') > -1) { return 'webm'; }
		if (t.indexOf('ogg') > -1 || t.indexOf('opus') > -1) { return 'ogg'; }
		if (t.indexOf('mp4') > -1 || t.indexOf('m4a') > -1 || t.indexOf('aac') > -1) { return 'mp4'; }
		if (t.indexOf('mpeg') > -1 || t.indexOf('mp3') > -1) { return 'mp3'; }
		if (t.indexOf('wav') > -1) { return 'wav'; }
		return 'webm';
	}

	/* --------------------------------------------------------------- */
	/* Submit gating + submit                                           */
	/* --------------------------------------------------------------- */

	// Step 3 is enabled only once the required steps are done.
	function updateSubmit() {
		var need = [];
		if (!state.photoImg) { need.push('a photo'); }
		if ($('#pl-body').value.trim().length === 0) { need.push('a story'); }

		if (IS_ANON) {
			var nameEl = $('#pl-name'), emailEl = $('#pl-email');
			var name = nameEl ? nameEl.value.trim() : '';
			var email = emailEl ? emailEl.value.trim() : '';
			var emailOk = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
			if (!name) { need.push('your name'); }
			if (!emailOk) { need.push('your email'); }
			if (CFG.turnstileKey && !turnstileToken()) { need.push('the verification'); }
		}

		$('#pl-submit').disabled = need.length > 0;

		var hint = $('#pl-submit-hint');
		if (!hint) { return; }
		if (need.length === 0) {
			hint.classList.add('pl-hidden');
		} else {
			hint.textContent = 'Add ' + need.join(', ') + ' to submit.';
			hint.classList.remove('pl-hidden');
		}
	}

	// Cloudflare Turnstile calls this when the check passes/expires.
	window.plTurnstileCb = function () { updateSubmit(); };

	function submit() {
		var btn = $('#pl-submit');
		btn.disabled = true;
		btn.textContent = 'Submitting…';

		// Bake the photo (with its chosen filter) into a JPEG for upload.
		bakePhoto().then(function (blob) {
			var fd = new FormData();
			fd.append('title', $('#pl-title').value);
			fd.append('body', $('#pl-body').value);
			if (blob) { fd.append('image', blob, 'partyline.jpg'); }
			var imm = $('#pl-immediate'); // only present for editors/admins
			if (imm && imm.checked) { fd.append('immediate', '1'); }
			if (IS_ANON) {
				if ($('#pl-name')) { fd.append('name', $('#pl-name').value); }
				if ($('#pl-email')) { fd.append('email', $('#pl-email').value); }
				fd.append('turnstile', turnstileToken());
			}
			return api('submit', { method: 'POST', body: fd });
		}).then(function (res) {
			releaseStream(); // done capturing — free the mic
			markSent(res);   // record it in the local "Your Partylines" list
			draftId = null;  // next capture starts a fresh entry
			if (res.message) { $('#pl-success-msg').textContent = res.message; }
			show('screen-success');
		}).catch(function (err) {
			alert(err.message || 'Submit failed.');
		}).then(function () {
			btn.textContent = 'Submit Partyline';
			updateSubmit();
		});
	}

	function reset() {
		releaseStream(); // leaving the capture flow — free the mic
		releaseWakeLock();
		draftId = null;
		state = { photoImg: null, filter: 'none', transcript: '', title: '', body: '' };
		if (canvas) { canvas.classList.add('pl-hidden'); canvas.style.filter = ''; }
		$('#pl-filters').classList.add('pl-hidden');
		$('#pl-photo-camera').innerHTML = '<span>📷</span> Take photo';
		$('#pl-photo-library').innerHTML = '<span>🖼️</span> Choose photo';
		$('#pl-input-camera').value = '';
		$('#pl-input-library').value = '';
		$('#pl-title').value = '';
		$('#pl-body').value = '';
		if ($('#pl-name')) { $('#pl-name').value = ''; }
		if ($('#pl-email')) { $('#pl-email').value = ''; }
		if (window.turnstile && CFG.turnstileKey) { try { window.turnstile.reset(); } catch (e) {} }
		setStatus('Tap to dictate — or type it below.', null);
		applyFilter('none');
		updateSubmit();
	}

	/* --------------------------------------------------------------- */
	/* Service worker + install prompt                                  */
	/* --------------------------------------------------------------- */
	function registerServiceWorker() {
		if (!('serviceWorker' in navigator) || !CFG.swUrl) { return; }
		navigator.serviceWorker.register(CFG.swUrl, { scope: CFG.scope || '/' })
			.catch(function (err) { console.warn('[Partyline] SW registration failed:', err); });
	}

	var deferredPrompt = null;
	function isStandalone() {
		return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
	}
	function isIos() {
		return /iphone|ipad|ipod/i.test(navigator.userAgent) ||
			(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPadOS
	}
	function setupInstall() {
		var banner = $('#pl-install'), button = $('#pl-install-btn'), msg = $('#pl-install-msg');
		// Already installed — nothing to prompt.
		if (isStandalone() || !banner || !button) { return; }

		// iOS Safari never fires beforeinstallprompt, so show manual instructions.
		if (isIos()) {
			msg.innerHTML = 'Install Partyline: tap the Share button, then <strong>Add to Home Screen</strong>.';
			button.classList.add('pl-hidden');
			banner.classList.add('show');
			return;
		}

		// Android/desktop Chromium: use the native prompt when offered.
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			deferredPrompt = e;
			banner.classList.add('show');
		});
		button.addEventListener('click', function () {
			if (!deferredPrompt) { return; }
			deferredPrompt.prompt();
			deferredPrompt.userChoice.finally(function () { deferredPrompt = null; banner.classList.remove('show'); });
		});
		window.addEventListener('appinstalled', function () { banner.classList.remove('show'); });
	}

	/* --------------------------------------------------------------- */
	/* Wire up                                                          */
	/* --------------------------------------------------------------- */
	function boot() {
		canvas = $('#pl-photo-canvas');
		registerServiceWorker();
		setupInstall();

		$('#pl-start').addEventListener('click', function () { reset(); show('screen-capture'); });
		$('#pl-again').addEventListener('click', function () { reset(); show('screen-home'); });
		$('#pl-submit').addEventListener('click', submit);

		// Save & close: persist the in-progress draft, then return home.
		$('#pl-cancel').addEventListener('click', function () {
			saveDraft().then(function () { reset(); show('screen-home'); });
		});

		// "Your Partylines" list.
		$('#pl-open-drafts').addEventListener('click', function () { renderDrafts(); show('screen-drafts'); });
		$('#pl-drafts-back').addEventListener('click', function () { show('screen-home'); });

		$('#pl-photo-camera').addEventListener('click', function () { $('#pl-input-camera').click(); });
		$('#pl-photo-library').addEventListener('click', function () { $('#pl-input-library').click(); });
		function onPick(e) { if (e.target.files && e.target.files[0]) { handlePhotoFile(e.target.files[0]); } }
		$('#pl-input-camera').addEventListener('change', onPick);
		$('#pl-input-library').addEventListener('change', onPick);

		// Typing the story live-updates the submit gate.
		$('#pl-body').addEventListener('input', function () { updateSubmit(); scheduleSave(); });
		$('#pl-title').addEventListener('input', scheduleSave);
		if ($('#pl-name')) { $('#pl-name').addEventListener('input', updateSubmit); }
		if ($('#pl-email')) { $('#pl-email').addEventListener('input', updateSubmit); }

		var chips = document.querySelectorAll('.pl-chip');
		for (var i = 0; i < chips.length; i++) {
			chips[i].addEventListener('click', function () { applyFilter(this.getAttribute('data-filter')); });
		}

		var recBtn = $('#pl-rec-btn'); // absent in anonymous mode
		if (recBtn) { recBtn.addEventListener('click', toggleRecord); }

		// The wake lock is auto-released when the page is hidden; re-acquire it
		// if we come back while still recording.
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible' && recording) { requestWakeLock(); }
		});

		updateSubmit(); // set the initial gated state
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
