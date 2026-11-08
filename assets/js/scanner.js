/* Pemindai QR: kamera (BarcodeDetector / jsQR) + pemindai USB (keyboard wedge) */
(function () {
  'use strict';

  function Scanner(opts) {
    this.video = opts.video;
    this.onCode = opts.onCode;
    this.onStatus = opts.onStatus || function () {};
    this.canvas = document.createElement('canvas');
    this.ctx = this.canvas.getContext('2d', { willReadFrequently: true });
    this.running = false;
    this.last = { code: '', at: 0 };
    this.devices = [];
    this.deviceIndex = 0;
    this.detector = null;
    var self = this;
    var wanted = opts.formats || ['qr_code'];
    if ('BarcodeDetector' in window) {
      try {
        window.BarcodeDetector.getSupportedFormats().then(function (f) {
          var use = wanted.filter(function (x) { return f.indexOf(x) >= 0; });
          if (use.length) self.detector = new window.BarcodeDetector({ formats: use });
        }).catch(function () {});
      } catch (e) { /* ignore */ }
    }
  }

  Scanner.prototype.emit = function (code) {
    code = String(code || '').trim();
    if (!code) return;
    var now = Date.now();
    if (code === this.last.code && now - this.last.at < 4000) return;
    this.last = { code: code, at: now };
    this.onCode(code);
  };

  Scanner.prototype.start = function () {
    var self = this;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      self.onStatus(false, 'Kamera tidak tersedia. Gunakan pemindai USB.');
      return Promise.resolve();
    }
    var constraints = { audio: false, video: { width: { ideal: 1280 }, height: { ideal: 720 } } };
    var dev = self.devices[self.deviceIndex];
    if (dev) constraints.video.deviceId = { exact: dev.deviceId };
    else constraints.video.facingMode = 'environment';
    return navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
      self.stop();
      self.stream = stream;
      self.video.srcObject = stream;
      return self.video.play();
    }).then(function () {
      self.running = true;
      self.onStatus(true, 'Kamera aktif');
      if (!self.devices.length) {
        navigator.mediaDevices.enumerateDevices().then(function (list) {
          self.devices = list.filter(function (d) { return d.kind === 'videoinput'; });
        });
      }
      self.loop();
    }).catch(function (err) {
      var msg = location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1'
        ? 'Kamera butuh HTTPS. Gunakan pemindai USB.' : 'Izin kamera ditolak. Gunakan pemindai USB.';
      if (err && err.name === 'NotFoundError') msg = 'Kamera tidak ditemukan. Gunakan pemindai USB.';
      self.onStatus(false, msg);
    });
  };

  Scanner.prototype.stop = function () {
    this.running = false;
    if (this.stream) this.stream.getTracks().forEach(function (t) { t.stop(); });
    this.stream = null;
  };

  Scanner.prototype.next = function () {
    if (this.devices.length < 2) return this.start();
    this.deviceIndex = (this.deviceIndex + 1) % this.devices.length;
    return this.start();
  };

  Scanner.prototype.loop = function () {
    var self = this;
    if (!self.running) return;
    var v = self.video;
    if (v.readyState >= 2 && v.videoWidth) {
      if (self.detector) {
        self.detector.detect(v).then(function (codes) {
          if (codes && codes[0]) self.emit(codes[0].rawValue);
        }).catch(function () {}).finally(function () { setTimeout(function () { self.loop(); }, 180); });
        return;
      }
      var w = Math.min(640, v.videoWidth);
      var h = Math.round(v.videoHeight * (w / v.videoWidth));
      self.canvas.width = w; self.canvas.height = h;
      self.ctx.drawImage(v, 0, 0, w, h);
      if (typeof jsQR === 'function') {
        var img = self.ctx.getImageData(0, 0, w, h);
        var res = jsQR(img.data, w, h, { inversionAttempts: 'dontInvert' });
        if (res && res.data) self.emit(res.data);
      }
    }
    setTimeout(function () { self.loop(); }, 200);
  };

  /** USB barcode/QR scanners type characters quickly then press Enter. */
  Scanner.wedge = function (onCode) {
    var buf = '';
    var lastAt = 0;
    document.addEventListener('keydown', function (e) {
      var t = e.target;
      if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT') && !t.classList.contains('wedge')) return;
      var now = Date.now();
      if (now - lastAt > 120) buf = '';
      lastAt = now;
      if (e.key === 'Enter') {
        if (buf.length >= 6) { onCode(buf); e.preventDefault(); }
        buf = '';
        return;
      }
      if (e.key.length === 1) buf += e.key;
    });
  };

  window.Scanner = Scanner;
})();
