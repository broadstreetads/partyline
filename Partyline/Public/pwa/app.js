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
	function apiJson(path, obj) {
		return api(path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(obj)
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
			$('#pl-photo-btn').innerHTML = '<span>🔄</span> Retake photo';
			applyFilter(state.filter);
			updateContinue();
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
	/* Voice record -> 16kHz WAV                                        */
	/* --------------------------------------------------------------- */
	var recorder = null, chunks = [], stream = null, recording = false;

	function setStatus(msg, kind) {
		var el = $('#pl-rec-status');
		el.textContent = msg;
		el.className = 'pl-status' + (kind ? ' is-' + kind : '');
	}

	function toggleRecord() {
		if (recording) { stopRecording(); return; }
		if (!navigator.mediaDevices || !window.MediaRecorder) {
			setStatus('Recording is not supported on this browser — you can type the story on the next screen.', 'error');
			updateContinue();
			return;
		}
		navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
			stream = s;
			chunks = [];
			recorder = new MediaRecorder(stream);
			recorder.ondataavailable = function (e) { if (e.data && e.data.size) { chunks.push(e.data); } };
			recorder.onstop = onRecordingStopped;
			recorder.start();
			recording = true;
			$('#pl-rec-btn').classList.add('is-recording');
			setStatus('Recording… tap to stop.', 'busy');
		}).catch(function () {
			setStatus('Microphone permission denied.', 'error');
		});
	}

	function stopRecording() {
		recording = false;
		$('#pl-rec-btn').classList.remove('is-recording');
		if (recorder && recorder.state !== 'inactive') { recorder.stop(); }
		if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
	}

	function onRecordingStopped() {
		var blob = new Blob(chunks, { type: chunks[0] ? chunks[0].type : 'audio/webm' });
		setStatus('Transcribing…', 'busy');
		$('#pl-rec-btn').setAttribute('disabled', 'disabled');

		blobToWav16kBase64(blob).then(function (b64) {
			return apiJson('transcribe', { audio: b64 });
		}).then(function (res) {
			state.transcript = res.text || '';
			setStatus('Writing it up…', 'busy');
			return apiJson('generate', { transcript: state.transcript });
		}).then(function (gen) {
			state.title = gen.title || '';
			state.body = gen.body || '';
			setStatus('✓ Got it — tap Continue to review.', null);
			updateContinue();
		}).catch(function (err) {
			setStatus(err.message || 'Transcription failed.', 'error');
		}).then(function () {
			$('#pl-rec-btn').removeAttribute('disabled');
		});
	}

	// Decode -> resample to 16kHz mono -> 16-bit PCM WAV -> base64.
	function blobToWav16kBase64(blob) {
		var AC = window.AudioContext || window.webkitAudioContext;
		var OAC = window.OfflineAudioContext || window.webkitOfflineAudioContext;
		return blob.arrayBuffer().then(function (buf) {
			var ac = new AC();
			return new Promise(function (resolve, reject) {
				ac.decodeAudioData(buf, resolve, reject);
			}).then(function (decoded) { ac.close(); return decoded; });
		}).then(function (decoded) {
			var frames = Math.ceil(decoded.duration * 16000);
			var off = new OAC(1, frames, 16000);
			var src = off.createBufferSource();
			src.buffer = decoded;
			src.connect(off.destination);
			src.start(0);
			return off.startRendering();
		}).then(function (rendered) {
			return base64FromBytes(new Uint8Array(encodeWav(rendered.getChannelData(0), 16000)));
		});
	}

	function encodeWav(samples, rate) {
		var buffer = new ArrayBuffer(44 + samples.length * 2);
		var view = new DataView(buffer);
		function str(off, s) { for (var i = 0; i < s.length; i++) { view.setUint8(off + i, s.charCodeAt(i)); } }
		str(0, 'RIFF');
		view.setUint32(4, 36 + samples.length * 2, true);
		str(8, 'WAVE');
		str(12, 'fmt ');
		view.setUint32(16, 16, true);
		view.setUint16(20, 1, true);   // PCM
		view.setUint16(22, 1, true);   // mono
		view.setUint32(24, rate, true);
		view.setUint32(28, rate * 2, true);
		view.setUint16(32, 2, true);
		view.setUint16(34, 16, true);
		str(36, 'data');
		view.setUint32(40, samples.length * 2, true);
		var offset = 44;
		for (var i = 0; i < samples.length; i++) {
			var s = Math.max(-1, Math.min(1, samples[i]));
			view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
			offset += 2;
		}
		return buffer;
	}

	function base64FromBytes(bytes) {
		var bin = '';
		var chunk = 0x8000;
		for (var i = 0; i < bytes.length; i += chunk) {
			bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
		}
		return btoa(bin);
	}

	/* --------------------------------------------------------------- */
	/* Continue -> preview -> submit                                    */
	/* --------------------------------------------------------------- */
	function updateContinue() {
		var ready = !!state.photoImg || !!state.body || !!state.transcript;
		$('#pl-continue').disabled = !ready;
	}

	function goToPreview() {
		var img = $('#pl-preview-img');
		bakePhoto().then(function (blob) {
			state.photoBlob = blob;
			if (blob) {
				img.src = URL.createObjectURL(blob);
				img.classList.remove('pl-hidden');
			} else {
				img.classList.add('pl-hidden');
			}
			$('#pl-title').value = state.title;
			$('#pl-body').value = state.body;
			show('screen-preview');
		});
	}

	function submit() {
		var btn = $('#pl-submit');
		btn.disabled = true;
		btn.textContent = 'Submitting…';

		var fd = new FormData();
		fd.append('title', $('#pl-title').value);
		fd.append('body', $('#pl-body').value);
		if (state.photoBlob) { fd.append('image', state.photoBlob, 'partyline.jpg'); }

		api('submit', { method: 'POST', body: fd }).then(function (res) {
			if (res.message) { $('#pl-success-msg').textContent = res.message; }
			show('screen-success');
		}).catch(function (err) {
			alert(err.message || 'Submit failed.');
		}).then(function () {
			btn.disabled = false;
			btn.textContent = 'Submit Partyline';
		});
	}

	function reset() {
		state = { photoImg: null, filter: 'none', transcript: '', title: '', body: '' };
		if (canvas) { canvas.classList.add('pl-hidden'); canvas.style.filter = ''; }
		$('#pl-filters').classList.add('pl-hidden');
		$('#pl-photo-btn').innerHTML = '<span>📷</span> Take a photo';
		$('#pl-photo-input').value = '';
		$('#pl-title').value = '';
		$('#pl-body').value = '';
		setStatus('Tap to record — dictate or interview.', null);
		applyFilter('none');
		updateContinue();
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
	function setupInstall() {
		var banner = $('#pl-install'), button = $('#pl-install-btn');
		if (isStandalone() || !banner || !button) { return; }
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

		$('#pl-start').addEventListener('click', function () { show('screen-capture'); });
		$('#pl-cancel').addEventListener('click', function () { reset(); show('screen-home'); });
		$('#pl-again').addEventListener('click', function () { reset(); show('screen-home'); });
		$('#pl-back').addEventListener('click', function () { show('screen-capture'); });
		$('#pl-continue').addEventListener('click', goToPreview);
		$('#pl-submit').addEventListener('click', submit);

		$('#pl-photo-btn').addEventListener('click', function () { $('#pl-photo-input').click(); });
		$('#pl-photo-input').addEventListener('change', function (e) {
			if (e.target.files && e.target.files[0]) { handlePhotoFile(e.target.files[0]); }
		});

		var chips = document.querySelectorAll('.pl-chip');
		for (var i = 0; i < chips.length; i++) {
			chips[i].addEventListener('click', function () { applyFilter(this.getAttribute('data-filter')); });
		}

		$('#pl-rec-btn').addEventListener('click', toggleRecord);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
