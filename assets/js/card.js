/* Kartu QR member: simpan QR sebagai gambar PNG */
(function () {
  var btn = document.getElementById('saveQr');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var c = document.querySelector('#memberQr canvas');
    if (!c) return;
    var pad = 40;
    var out = document.createElement('canvas');
    out.width = c.width + pad * 2;
    out.height = c.height + pad * 2 + 60;
    var ctx = out.getContext('2d');
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, out.width, out.height);
    ctx.drawImage(c, pad, pad);
    ctx.fillStyle = '#10284A';
    ctx.font = 'bold 28px monospace';
    ctx.textAlign = 'center';
    ctx.fillText(btn.dataset.name || '', out.width / 2, out.height - 30);
    var a = document.createElement('a');
    a.download = 'kartu-' + (btn.dataset.name || 'member') + '.png';
    a.href = out.toDataURL('image/png');
    document.body.appendChild(a);
    a.click();
    a.remove();
  });
})();
