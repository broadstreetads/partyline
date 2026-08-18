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

	// Remember anonymous submitters' contact info on this device for next time.
	var CONTACT_KEY = 'partyline-contact';
	function applyContact() {
		if (!IS_ANON) { return; }
		var c;
		try { c = JSON.parse(localStorage.getItem(CONTACT_KEY) || '{}'); } catch (e) { c = {}; }
		if ($('#pl-name') && c.name) { $('#pl-name').value = c.name; }
		if ($('#pl-email') && c.email) { $('#pl-email').value = c.email; }
		if ($('#pl-phone') && c.phone) { $('#pl-phone').value = c.phone; }
	}
	function saveContact() {
		if (!IS_ANON) { return; }
		try {
			localStorage.setItem(CONTACT_KEY, JSON.stringify({
				name:  $('#pl-name')  ? $('#pl-name').value.trim()  : '',
				email: $('#pl-email') ? $('#pl-email').value.trim() : '',
				phone: $('#pl-phone') ? $('#pl-phone').value.trim() : ''
			}));
		} catch (e) {}
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
	// Photos: an array of { img: Image, filter: string }. The FIRST is the cover
	// (featured image + the one the AI looks at). `active` is the previewed photo.
	// `video` is an optional File (only when the server supports video).
	var state = { photos: [], active: 0, video: null, transcript: '', title: '', body: '' };

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
	var canvas;
	var MAX_DIM = 1600;
	var MAX_ZOOM = 5; // how far into a photo the crop editor lets you zoom

	// The source rectangle of a photo in natural pixels — the whole image, or the
	// contributor's crop (stored normalized 0..1 so it survives any resize).
	function srcRect(p) {
		var iw = p.img.naturalWidth, ih = p.img.naturalHeight;
		var c = p.crop;
		if (c && c.w > 0 && c.h > 0) {
			return { sx: c.x * iw, sy: c.y * ih, sw: c.w * iw, sh: c.h * ih };
		}
		return { sx: 0, sy: 0, sw: iw, sh: ih };
	}

	// Draw a photo (honoring its crop) into a canvas, scaled to fit within MAX_DIM.
	function drawPhoto(cv, p) {
		var r = srcRect(p);
		var scale = Math.min(1, MAX_DIM / Math.max(r.sw, r.sh));
		cv.width = Math.max(1, Math.round(r.sw * scale));
		cv.height = Math.max(1, Math.round(r.sh * scale));
		cv.getContext('2d').drawImage(p.img, r.sx, r.sy, r.sw, r.sh, 0, 0, cv.width, cv.height);
	}

	// Load one or more picked files into state.photos, then refresh the UI.
	function addFiles(fileList) {
		var files = Array.prototype.slice.call(fileList || []).filter(function (f) {
			return f && f.type && f.type.indexOf('image/') === 0;
		});
		if (!files.length) { return; }
		var remaining = files.length;
		var done = function () { if (--remaining === 0) { refreshPhotos(); updateSubmit(); scheduleSave(); } };
		files.forEach(function (file) {
			var url = URL.createObjectURL(file);
			var img = new Image();
			img.onload = function () {
				URL.revokeObjectURL(url);
				state.photos.push({ img: img, filter: 'none', crop: null });
				state.active = state.photos.length - 1;
				done();
			};
			img.onerror = function () { URL.revokeObjectURL(url); setStatus('Could not load one of the images.', 'error'); done(); };
			img.src = url;
		});
	}

	// Show the active photo (with its filter) in the big preview, or hide when empty.
	function drawActive() {
		if (!state.photos.length) {
			canvas.classList.add('pl-hidden'); canvas.style.filter = '';
			$('#pl-filters').classList.add('pl-hidden');
			$('#pl-photo-tools').classList.add('pl-hidden');
			$('#pl-make-cover').classList.add('pl-hidden');
			return;
		}
		if (state.active >= state.photos.length) { state.active = state.photos.length - 1; }
		var p = state.photos[state.active];
		drawPhoto(canvas, p);
		canvas.style.filter = FILTERS[p.filter] === 'none' ? '' : FILTERS[p.filter];
		canvas.classList.remove('pl-hidden');
		$('#pl-filters').classList.remove('pl-hidden');
		$('#pl-photo-tools').classList.remove('pl-hidden');
		var chips = document.querySelectorAll('#pl-filters .pl-chip');
		for (var i = 0; i < chips.length; i++) {
			chips[i].classList.toggle('is-active', chips[i].getAttribute('data-filter') === p.filter);
		}
		$('#pl-make-cover').classList.toggle('pl-hidden', state.active === 0 || state.photos.length < 2);
	}

	// Render the thumbnail strip (cover badge on the first, tap to preview, ✕ to remove).
	function renderThumbs() {
		var strip = $('#pl-thumbs');
		strip.innerHTML = '';
		if (!state.photos.length) { strip.classList.add('pl-hidden'); return; }
		strip.classList.remove('pl-hidden');
		state.photos.forEach(function (p, i) {
			var t = document.createElement('div');
			t.className = 'pl-thumb' + (i === state.active ? ' is-active' : '');
			var tc = document.createElement('canvas');
			drawPhoto(tc, p);
			tc.style.filter = FILTERS[p.filter] === 'none' ? '' : FILTERS[p.filter];
			t.appendChild(tc);
			if (i === 0) {
				var cov = document.createElement('div');
				cov.className = 'pl-thumb-cover';
				cov.textContent = 'Cover';
				t.appendChild(cov);
			}
			var del = document.createElement('button');
			del.className = 'pl-thumb-del';
			del.type = 'button';
			del.setAttribute('aria-label', 'Remove photo');
			del.textContent = '✕';
			del.addEventListener('click', function (ev) { ev.stopPropagation(); removePhoto(i); });
			t.appendChild(del);
			t.addEventListener('click', function () { state.active = i; refreshPhotos(); });
			strip.appendChild(t);
		});
	}

	function refreshPhotos() { drawActive(); renderThumbs(); }

	function applyFilter(name) {
		if (!state.photos.length) { return; }
		state.photos[state.active].filter = name;
		refreshPhotos();
		scheduleSave();
	}

	function removePhoto(i) {
		state.photos.splice(i, 1);
		if (state.active >= state.photos.length) { state.active = Math.max(0, state.photos.length - 1); }
		refreshPhotos();
		updateSubmit();
		scheduleSave();
	}

	// Promote the active photo to the cover (index 0).
	function makeCover() {
		if (state.active === 0 || !state.photos.length) { return; }
		var p = state.photos.splice(state.active, 1)[0];
		state.photos.unshift(p);
		state.active = 0;
		refreshPhotos();
		scheduleSave();
	}

	// Bake photo i into a JPEG blob. withFilter bakes in its chosen filter and
	// withCrop (default true) bakes in its crop — both used for upload. Draft
	// storage passes both false to keep the full raw image, storing the filter
	// and crop separately so a resumed draft stays fully adjustable.
	function bakePhotoAt(i, withFilter, withCrop) {
		if (withCrop === undefined) { withCrop = true; }
		return new Promise(function (resolve) {
			var p = state.photos[i];
			if (!p) { resolve(null); return; }
			var r = withCrop ? srcRect(p) : { sx: 0, sy: 0, sw: p.img.naturalWidth, sh: p.img.naturalHeight };
			var scale = Math.min(1, MAX_DIM / Math.max(r.sw, r.sh));
			var c = document.createElement('canvas');
			c.width = Math.max(1, Math.round(r.sw * scale));
			c.height = Math.max(1, Math.round(r.sh * scale));
			var x = c.getContext('2d');
			if (withFilter && 'filter' in x) { x.filter = FILTERS[p.filter]; }
			x.drawImage(p.img, r.sx, r.sy, r.sw, r.sh, 0, 0, c.width, c.height);
			c.toBlob(function (blob) { resolve(blob); }, 'image/jpeg', 0.9);
		});
	}

	/* --------------------------------------------------------------- */
	/* Crop & zoom editor — pinch to zoom, drag to reposition           */
	/* --------------------------------------------------------------- */
	// Aspect ratio (w/h); 0 means "use the image's own aspect" (Original).
	var ASPECTS = { 'orig': 0, '1:1': 1, '4:5': 4 / 5, '16:9': 16 / 9 };
	// Live editor geometry, all in CSS px within the crop frame. null when closed.
	var ed = null;
	var edPointers = {}, edPinch = null;

	function edEls() {
		return {
			overlay: $('#pl-editor'), frame: $('#pl-crop-frame'),
			stage: $('#pl-crop-stage'), cv: $('#pl-crop-canvas')
		};
	}

	// Frame size (CSS px) for an aspect, fit to the available stage/viewport.
	function edFrameSize(ratio, natW, natH) {
		var stage = edEls().stage;
		var maxW = stage.clientWidth || Math.min(window.innerWidth - 32, 460);
		var maxH = Math.min(window.innerHeight * 0.58, 540);
		var ar = ratio || (natW / natH);
		var w = maxW, h = w / ar;
		if (h > maxH) { h = maxH; w = h * ar; }
		return { w: Math.round(w), h: Math.round(h) };
	}

	// Render the photo into the crop canvas at its base (scale=1) display size.
	function edDrawCanvas() {
		var el = edEls(), p = state.photos[state.active];
		var dpr = Math.min(2, window.devicePixelRatio || 1);
		el.cv.width = Math.round(ed.baseW * dpr);
		el.cv.height = Math.round(ed.baseH * dpr);
		el.cv.style.width = ed.baseW + 'px';
		el.cv.style.height = ed.baseH + 'px';
		var x = el.cv.getContext('2d');
		x.setTransform(dpr, 0, 0, dpr, 0, 0);
		x.drawImage(p.img, 0, 0, ed.baseW, ed.baseH);
		el.cv.style.filter = FILTERS[p.filter] === 'none' ? '' : FILTERS[p.filter];
	}

	function edApply() {
		edEls().cv.style.transform = 'translate(' + ed.tx + 'px,' + ed.ty + 'px) scale(' + ed.scale + ')';
	}

	// Keep the image covering the frame — no empty gaps at the edges.
	function edClamp() {
		var dw = ed.baseW * ed.scale, dh = ed.baseH * ed.scale;
		ed.tx = Math.min(0, Math.max(ed.fw - dw, ed.tx));
		ed.ty = Math.min(0, Math.max(ed.fh - dh, ed.ty));
	}

	// Configure geometry for an aspect and center the image at cover (scale 1).
	function edSetAspect(aspect) {
		var el = edEls(), p = state.photos[state.active];
		var natW = p.img.naturalWidth, natH = p.img.naturalHeight;
		var f = edFrameSize(ASPECTS[aspect], natW, natH);
		ed.aspect = aspect; ed.fw = f.w; ed.fh = f.h;
		el.frame.style.width = f.w + 'px';
		el.frame.style.height = f.h + 'px';
		var imgAr = natW / natH, frameAr = f.w / f.h;
		if (imgAr >= frameAr) { ed.baseH = f.h; ed.baseW = f.h * imgAr; }
		else { ed.baseW = f.w; ed.baseH = f.w / imgAr; }
		ed.scale = 1;
		ed.tx = (f.w - ed.baseW) / 2;
		ed.ty = (f.h - ed.baseH) / 2;
		edDrawCanvas();
		edClamp();
		edApply();
		var chips = document.querySelectorAll('#pl-editor .pl-chip');
		for (var i = 0; i < chips.length; i++) {
			chips[i].classList.toggle('is-active', chips[i].getAttribute('data-aspect') === aspect);
		}
	}

	// If the photo already has a saved crop, frame it exactly on open.
	function edRestoreView() {
		var c = state.photos[state.active].crop;
		if (!c || c.w <= 0 || c.h <= 0) { return; }
		var s = (ed.fw / c.w) / ed.baseW;
		if (!isFinite(s) || s < 1) { return; } // can't represent here; leave at cover
		ed.scale = Math.min(MAX_ZOOM, s);
		ed.tx = -c.x * ed.baseW * ed.scale;
		ed.ty = -c.y * ed.baseH * ed.scale;
		edClamp();
		edApply();
	}

	function openEditor() {
		if (!state.photos.length) { return; }
		ed = {};
		edEls().overlay.classList.remove('pl-hidden');
		document.body.classList.add('pl-editing');
		var start = (state.photos[state.active].crop && state.photos[state.active].crop.aspect) || 'orig';
		edSetAspect(start);
		edRestoreView();
		edBind();
	}

	function closeEditor() {
		edUnbind();
		edEls().overlay.classList.add('pl-hidden');
		document.body.classList.remove('pl-editing');
		ed = null;
	}

	// Turn the frame's current view of the image into a normalized crop rect.
	function commitCrop() {
		var p = state.photos[state.active];
		var dw = ed.baseW * ed.scale, dh = ed.baseH * ed.scale;
		var x0 = Math.max(0, Math.min(1, (-ed.tx) / dw));
		var y0 = Math.max(0, Math.min(1, (-ed.ty) / dh));
		var w = Math.max(0, Math.min(1 - x0, ed.fw / dw));
		var h = Math.max(0, Math.min(1 - y0, ed.fh / dh));
		if (ed.aspect === 'orig' && x0 < 0.005 && y0 < 0.005 && w > 0.995 && h > 0.995) {
			p.crop = null; // full original, no zoom — treat as uncropped
		} else {
			p.crop = { aspect: ed.aspect, x: x0, y: y0, w: w, h: h };
		}
		closeEditor();
		refreshPhotos();
		updateSubmit();
		scheduleSave();
	}

	function edDist(a, b) { var dx = a.x - b.x, dy = a.y - b.y; return Math.sqrt(dx * dx + dy * dy); }

	// Zoom to newScale keeping the point under the finger(s) fixed in the frame.
	function edZoom(ns, clientX, clientY) {
		var rect = edEls().frame.getBoundingClientRect();
		var fx = clientX - rect.left, fy = clientY - rect.top;
		var ix = (fx - ed.tx) / ed.scale, iy = (fy - ed.ty) / ed.scale;
		ed.scale = ns;
		ed.tx = fx - ix * ns;
		ed.ty = fy - iy * ns;
		edClamp();
		edApply();
	}

	function edDown(e) {
		var cv = edEls().cv;
		if (cv.setPointerCapture) { try { cv.setPointerCapture(e.pointerId); } catch (err) {} }
		edPointers[e.pointerId] = { x: e.clientX, y: e.clientY };
		var ids = Object.keys(edPointers);
		if (ids.length === 2) {
			var a = edPointers[ids[0]], b = edPointers[ids[1]];
			edPinch = { d: edDist(a, b), s: ed.scale };
		}
		e.preventDefault();
	}
	function edMove(e) {
		if (!edPointers[e.pointerId]) { return; }
		var prev = edPointers[e.pointerId], cur = { x: e.clientX, y: e.clientY };
		var ids = Object.keys(edPointers);
		if (ids.length >= 2 && edPinch) {
			edPointers[e.pointerId] = cur;
			var a = edPointers[ids[0]], b = edPointers[ids[1]];
			var ns = Math.max(1, Math.min(MAX_ZOOM, edPinch.s * (edDist(a, b) / edPinch.d)));
			edZoom(ns, (a.x + b.x) / 2, (a.y + b.y) / 2);
		} else {
			ed.tx += cur.x - prev.x;
			ed.ty += cur.y - prev.y;
			edPointers[e.pointerId] = cur;
			edClamp();
			edApply();
		}
		e.preventDefault();
	}
	function edUp(e) {
		delete edPointers[e.pointerId];
		if (Object.keys(edPointers).length < 2) { edPinch = null; }
	}

	function edBind() {
		var cv = edEls().cv;
		cv.addEventListener('pointerdown', edDown);
		cv.addEventListener('pointermove', edMove);
		cv.addEventListener('pointerup', edUp);
		cv.addEventListener('pointercancel', edUp);
	}
	function edUnbind() {
		var cv = edEls().cv;
		cv.removeEventListener('pointerdown', edDown);
		cv.removeEventListener('pointermove', edMove);
		cv.removeEventListener('pointerup', edUp);
		cv.removeEventListener('pointercancel', edUp);
		edPointers = {}; edPinch = null;
	}

	/* --------------------------------------------------------------- */
	/* Optional video: pick, size-check against the server limit        */
	/* --------------------------------------------------------------- */
	function fmtMB(bytes) { return (bytes / 1048576).toFixed(1) + ' MB'; }

	function renderVideoChip() {
		var chip = $('#pl-video-chip');
		if (!chip) { return; }
		if (!state.video) { chip.className = 'pl-video-chip pl-hidden'; chip.innerHTML = ''; return; }
		var big = CFG.videoWarnBytes && state.video.size > CFG.videoWarnBytes;
		chip.className = 'pl-video-chip' + (big ? ' is-warn' : '');
		var text = (big ? '⚠️ ' : '🎬 ') + state.video.name + ' — ' + fmtMB(state.video.size);
		if (big && CFG.videoMaxSeconds) { text += '. That’s a big clip and may upload slowly or fail — around ' + CFG.videoMaxSeconds + ' seconds or less is safest.'; }
		chip.innerHTML = '';
		var span = document.createElement('span'); span.className = 'pl-video-name'; span.textContent = text;
		var del = document.createElement('button'); del.type = 'button'; del.className = 'pl-video-del';
		del.setAttribute('aria-label', 'Remove video'); del.textContent = '✕';
		del.addEventListener('click', clearVideo);
		chip.appendChild(span); chip.appendChild(del);
	}

	function clearVideo() {
		state.video = null;
		var inp = $('#pl-input-video'); if (inp) { inp.value = ''; }
		renderVideoChip();
		updateSubmit();
	}

	function onVideoPick(e) {
		var f = e.target.files && e.target.files[0];
		e.target.value = '';
		var chip = $('#pl-video-chip');
		if (!f) { return; }
		if (f.type.indexOf('video/') !== 0) {
			state.video = null;
			if (chip) { chip.className = 'pl-video-chip is-error'; chip.textContent = 'That file isn’t a video.'; }
			updateSubmit();
			return;
		}
		// Over the hard server upload limit: this would just fail, so refuse it.
		if (CFG.videoMaxBytes && f.size > CFG.videoMaxBytes) {
			state.video = null;
			if (chip) {
				chip.className = 'pl-video-chip is-error';
				chip.textContent = 'This clip is too large to upload (' + fmtMB(f.size) + '). Try one roughly ' + (CFG.videoMaxSeconds || 60) + ' seconds or shorter.';
			}
			updateSubmit();
			return;
		}
		state.video = f;
		renderVideoChip();
		updateSubmit();
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

	function hasContent() {
		return state.photos.length > 0 || $('#pl-title').value.trim() !== '' || $('#pl-body').value.trim() !== '';
	}

	function buildEntry(status, res, id) {
		// Store each photo's raw (unfiltered) blob plus its filter, so resuming a
		// draft restores both the images and their chosen looks. `id` is captured
		// by the caller so a later draftId reset can't corrupt the saved key.
		return Promise.all(state.photos.map(function (p, i) {
			return bakePhotoAt(i, false, false).then(function (blob) { return { blob: blob, filter: p.filter, crop: p.crop || null }; });
		})).then(function (photos) {
			return {
				id: id,
				status: status,
				wpStatus: status === 'sent' ? ((res && res.published) ? 'publish' : 'draft') : null,
				title: $('#pl-title').value,
				body: $('#pl-body').value,
				notes: $('#pl-notes') ? $('#pl-notes').value : '',
				photos: photos,
				editLink: (res && res.edit_link) || '',
				viewLink: (res && res.view_link) || '',
				updatedAt: Date.now()
			};
		});
	}

	function saveDraft() {
		if (!idbOK || !hasContent()) { return Promise.resolve(); }
		if (!draftId) { draftId = newId(); }
		return buildEntry('draft', null, draftId).then(dbPut).then(prune);
	}

	function markSent(res) {
		if (!idbOK) { return Promise.resolve(); }
		if (!draftId) { draftId = newId(); }
		return buildEntry('sent', res, draftId).then(dbPut).then(prune);
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
		if ($('#pl-notes')) { $('#pl-notes').value = entry.notes || ''; }
		state.transcript = entry.body || '';

		// New drafts store a `photos` array; older ones a single `photo` + `filter`.
		var photos = entry.photos;
		if (!photos && entry.photo) { photos = [{ blob: entry.photo, filter: entry.filter || 'none' }]; }

		if (photos && photos.length) {
			var remaining = photos.length;
			var done = function () { if (--remaining === 0) { state.active = 0; refreshPhotos(); updateSubmit(); } };
			photos.forEach(function (pd) {
				if (!pd || !pd.blob) { done(); return; }
				var url = URL.createObjectURL(pd.blob);
				var img = new Image();
				img.onload = function () {
					URL.revokeObjectURL(url);
					state.photos.push({ img: img, filter: pd.filter || 'none', crop: pd.crop || null });
					done();
				};
				img.onerror = function () { URL.revokeObjectURL(url); done(); };
				img.src = url;
			});
		}
		updateSubmit();
		updateRecUi();
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

		var thumbBlob = (e.photos && e.photos[0] && e.photos[0].blob) || e.photo || null;
		if (thumbBlob) {
			var u = URL.createObjectURL(thumbBlob);
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
			setStatus('Recording is not supported on this browser. Tap "Write it myself" to type your story.', 'error');
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
		setGenBusy(true);

		// Upload the native recording (webm/mp4/…) — OpenAI Whisper accepts it
		// directly, so no client-side resampling/encoding is needed.
		var fd = new FormData();
		fd.append('audio', blob, 'recording.' + extForType(type));

		api('transcribe', { method: 'POST', body: fd }).then(function (res) {
			return runGenerate(res.text || '');
		}).catch(function (err) {
			setStatus(err.message || 'Transcription failed.', 'error');
			setGenBusy(false);
		});
	}

	// Is there already a draft worth building on (vs. writing from scratch)?
	function hasDraft() {
		return $('#pl-body').value.trim() !== '' || $('#pl-title').value.trim() !== '';
	}

	// Disable/enable the dictation + "Add to story" controls during an AI call.
	function setGenBusy(on) {
		var rb = $('#pl-rec-btn');
		if (rb) { if (on) { rb.setAttribute('disabled', 'disabled'); } else { rb.removeAttribute('disabled'); } }
		var na = $('#pl-notes-apply'); if (na) { na.disabled = !!on; }
	}

	function collapseNotes() {
		var w = $('#pl-notes-wrap'), t = $('#pl-notes-toggle');
		if (w) { w.classList.add('pl-hidden'); }
		if (t) { t.setAttribute('aria-expanded', 'false'); t.innerHTML = '&#43; Paste notes'; }
	}

	// Reflect whether the mic starts a fresh write-up or adds to an existing draft.
	function updateRecUi() {
		var draft = hasDraft();
		var rb = $('#pl-rec-btn');
		if (rb) {
			rb.classList.toggle('is-refine', draft);
			rb.setAttribute('aria-label', draft ? 'Add to story by voice' : 'Record');
		}
		var st = $('#pl-rec-status');
		if (st && !recording && !st.classList.contains('is-busy') && !st.classList.contains('is-error')) {
			st.textContent = draft
				? 'Tap to add more by voice — it builds on your story.'
				: 'Tap to dictate, or type it below.';
		}
	}

	// Send new material (dictation and/or pasted notes) to the AI. With an
	// existing draft it refines/expands; otherwise it writes fresh. The server
	// falls back safely, so the reader's current text is never lost on error.
	function runGenerate(newTranscript) {
		newTranscript = (newTranscript || '').trim();
		var notes = $('#pl-notes') ? $('#pl-notes').value.trim() : '';
		if (!newTranscript && !notes && !state.photos.length) {
			setStatus('Add a photo, dictate, or paste some notes first.', 'error');
			setGenBusy(false);
			return Promise.resolve();
		}
		var refine = hasDraft();
		setStatus(refine ? 'Updating your story…' : 'Writing it up…', 'busy');
		setGenBusy(true);

		var gfd = new FormData();
		gfd.append('transcript', newTranscript);
		gfd.append('notes', notes);
		gfd.append('mode', refine ? 'refine' : 'fresh');
		gfd.append('current_title', $('#pl-title').value);
		gfd.append('current_body', $('#pl-body').value);

		var sendGen = function () { return api('generate', { method: 'POST', body: gfd }); };
		var chain = state.photos.length
			? bakePhotoAt(0, true).then(function (b) { if (b) { gfd.append('image', b, 'photo.jpg'); } return sendGen(); })
			: sendGen();

		return chain.then(function (gen) {
			if (gen.title) { $('#pl-title').value = gen.title; }
			$('#pl-body').value = gen.body || $('#pl-body').value || newTranscript;
			// Keep the raw dictation + notes around for the notification email.
			var addRaw = (newTranscript + (notes ? ('\n\n' + notes) : '')).trim();
			if (addRaw) { state.transcript = (state.transcript ? state.transcript + '\n\n' : '') + addRaw; }
			// Notes are now folded in — clear them so they aren't applied twice.
			if ($('#pl-notes')) { $('#pl-notes').value = ''; }
			collapseNotes();
			updateSubmit();
			updateRecUi();
			setStatus('✓ Ready below. Edit, dictate again, or paste more to build on it.', null);
			scheduleSave();
		}).catch(function (err) {
			setStatus(err.message || 'Something went wrong. Your text is safe — try again.', 'error');
		}).then(function () {
			setGenBusy(false);
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
		if (!state.photos.length && !state.video) { need.push('a photo or video'); }
		if ($('#pl-body').value.trim().length === 0) { need.push('a story'); }

		if (IS_ANON) {
			var nameEl = $('#pl-name'), emailEl = $('#pl-email'), phoneEl = $('#pl-phone');
			var name = nameEl ? nameEl.value.trim() : '';
			var email = emailEl ? emailEl.value.trim() : '';
			var phone = phoneEl ? phoneEl.value.trim() : '';
			var emailOk = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
			var phoneOk = phone.replace(/\D/g, '').length >= 7;
			var mathEl = $('#pl-math');
			var mathOk = mathEl && /^\d{1,3}$/.test(mathEl.value.trim());
			if (!name) { need.push('your name'); }
			if (!emailOk) { need.push('your email'); }
			if (!phoneOk) { need.push('your phone'); }
			if (!mathOk) { need.push('the spam check'); }
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

		// Bake every photo (with its chosen filter) into JPEGs for upload. The
		// first is the cover/featured image; the server sideloads them in order.
		Promise.all(state.photos.map(function (p, i) { return bakePhotoAt(i, true); })).then(function (blobs) {
			var fd = new FormData();
			fd.append('title', $('#pl-title').value);
			fd.append('body', $('#pl-body').value);
			blobs.forEach(function (blob, i) { if (blob) { fd.append('image_' + i, blob, 'partyline-' + i + '.jpg'); } });
			if (state.video) { fd.append('video', state.video, state.video.name || 'video'); }
			// The raw dictation, so the notification email can show the original.
			if (state.transcript) { fd.append('original', state.transcript); }
			var imm = $('#pl-immediate'); // only present for editors/admins
			if (imm && imm.checked) { fd.append('immediate', '1'); }
			if (IS_ANON) {
				if ($('#pl-name')) { fd.append('name', $('#pl-name').value); }
				if ($('#pl-email')) { fd.append('email', $('#pl-email').value); }
				if ($('#pl-phone')) { fd.append('phone', $('#pl-phone').value); }
				if ($('#pl-math')) { fd.append('math_answer', $('#pl-math').value); }
				fd.append('math_token', CFG.mathToken || '');
				fd.append('website', $('#pl-website') ? $('#pl-website').value : '');
				fd.append('turnstile', turnstileToken());
			}
			return api('submit', { method: 'POST', body: fd });
		}).then(function (res) {
			// Keep the mic grant for the app's lifetime (released on pagehide) so
			// the next Partyline doesn't re-prompt for microphone access.
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
		// Note: the mic stream is intentionally NOT released here — it's held for
		// the app's lifetime (freed on pagehide) so starting another Partyline
		// doesn't trigger a fresh microphone permission prompt.
		releaseWakeLock();
		if (ed) { closeEditor(); }
		draftId = null;
		state = { photos: [], active: 0, video: null, transcript: '', title: '', body: '' };
		if (canvas) { canvas.classList.add('pl-hidden'); canvas.style.filter = ''; }
		$('#pl-filters').classList.add('pl-hidden');
		$('#pl-photo-tools').classList.add('pl-hidden');
		$('#pl-make-cover').classList.add('pl-hidden');
		if ($('#pl-notes')) { $('#pl-notes').value = ''; }
		collapseNotes();
		var strip = $('#pl-thumbs'); if (strip) { strip.innerHTML = ''; strip.classList.add('pl-hidden'); }
		var vin = $('#pl-input-video'); if (vin) { vin.value = ''; }
		renderVideoChip();
		$('#pl-input-camera').value = '';
		$('#pl-input-library').value = '';
		$('#pl-title').value = '';
		$('#pl-body').value = '';
		// Keep the contact fields (name/email/phone) — they persist for next time.
		if (window.turnstile && CFG.turnstileKey) { try { window.turnstile.reset(); } catch (e) {} }
		setStatus('Tap to dictate, or type it below.', null);
		updateSubmit();
		updateRecUi();
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
		function onPick(e) { if (e.target.files && e.target.files.length) { addFiles(e.target.files); } e.target.value = ''; }
		$('#pl-input-camera').addEventListener('change', onPick);
		$('#pl-input-library').addEventListener('change', onPick);
		$('#pl-make-cover').addEventListener('click', makeCover);
		// Optional video picker (only present when the server supports video).
		var vbtn = $('#pl-video-btn'), vinput = $('#pl-input-video');
		if (vbtn && vinput) {
			vbtn.addEventListener('click', function () { vinput.click(); });
			vinput.addEventListener('change', onVideoPick);
		}

		// Typing the story live-updates the submit gate and the dictate/refine hint.
		$('#pl-body').addEventListener('input', function () { updateSubmit(); updateRecUi(); scheduleSave(); });
		$('#pl-title').addEventListener('input', function () { updateRecUi(); scheduleSave(); });
		function onContactInput() { updateSubmit(); saveContact(); }
		if ($('#pl-name')) { $('#pl-name').addEventListener('input', onContactInput); }
		if ($('#pl-email')) { $('#pl-email').addEventListener('input', onContactInput); }
		if ($('#pl-phone')) { $('#pl-phone').addEventListener('input', onContactInput); }
		if ($('#pl-math')) { $('#pl-math').addEventListener('input', function () { updateSubmit(); }); }
		applyContact(); // pre-fill saved contact info (anonymous)

		var chips = document.querySelectorAll('#pl-filters .pl-chip');
		for (var i = 0; i < chips.length; i++) {
			chips[i].addEventListener('click', function () { applyFilter(this.getAttribute('data-filter')); });
		}

		// Crop & zoom editor.
		$('#pl-edit-btn').addEventListener('click', openEditor);
		$('#pl-crop-done').addEventListener('click', commitCrop);
		$('#pl-crop-cancel').addEventListener('click', closeEditor);
		$('#pl-crop-reset').addEventListener('click', function () { edSetAspect('orig'); });
		var achips = document.querySelectorAll('#pl-editor .pl-chip');
		for (var a = 0; a < achips.length; a++) {
			achips[a].addEventListener('click', function () { edSetAspect(this.getAttribute('data-aspect')); });
		}

		var recBtn = $('#pl-rec-btn'); // absent in anonymous mode
		if (recBtn) { recBtn.addEventListener('click', toggleRecord); }

		// Paste-notes: expand/collapse, apply, and persist as you type.
		var notesToggle = $('#pl-notes-toggle');
		if (notesToggle) {
			notesToggle.addEventListener('click', function () {
				var wrap = $('#pl-notes-wrap');
				var open = wrap.classList.toggle('pl-hidden') === false;
				this.setAttribute('aria-expanded', open ? 'true' : 'false');
				this.innerHTML = open ? '&#8722; Hide notes' : '&#43; Paste notes';
				if (open && $('#pl-notes')) { $('#pl-notes').focus(); }
			});
		}
		if ($('#pl-notes-apply')) {
			$('#pl-notes-apply').addEventListener('click', function () {
				if (!$('#pl-notes').value.trim()) { setStatus('Paste some notes first, then tap Add to story.', 'error'); return; }
				runGenerate('');
			});
		}
		if ($('#pl-notes')) { $('#pl-notes').addEventListener('input', scheduleSave); }

		// The wake lock is auto-released when the page is hidden; re-acquire it
		// if we come back while still recording.
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible' && recording) { requestWakeLock(); }
		});

		// Free the held mic grant when the app is actually closing/backgrounded
		// for good (we deliberately keep it across submits within a session).
		window.addEventListener('pagehide', releaseStream);

		updateSubmit(); // set the initial gated state
		updateRecUi();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
