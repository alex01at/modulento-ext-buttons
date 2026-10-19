(() => {
  const root = document.getElementById('buttons-app');
  if (!root) return;

  const DIAMETER_MM = Number(root.dataset.diameter) || 57;
  const exportUrl = root.dataset.exportUrl;
  const csrfToken = root.dataset.csrf;

  const canvas = document.getElementById('buttons-canvas');
  const ctx = canvas.getContext('2d');
  const fileInput = document.getElementById('buttons-file');
  const zoomRange = document.getElementById('buttons-zoom');
  const zoomVal = document.getElementById('buttons-zoom-val');
  const qtyInput = document.getElementById('buttons-qty');
  const addBtn = document.getElementById('buttons-add');
  const exportBtn = document.getElementById('buttons-export');
  const guideCheck = document.getElementById('buttons-guide');
  const autofillCheck = document.getElementById('buttons-autofill');
  const listEl = document.getElementById('buttons-list');
  const summaryEl = document.getElementById('buttons-summary');
  const previewPanel = document.getElementById('buttons-preview-panel');
  const pdfPreview = document.getElementById('buttons-preview-frame');
  const downloadBtn = document.getElementById('buttons-download');

  let img = null;
  let scale = 1;       // additional zoom factor on top of baseScale (1 = image just fills the circle)
  let baseScale = 1;   // scale so the image fully covers the circle
  let offsetX = 0, offsetY = 0; // pan, in canvas pixels
  let dragging = false, lastX = 0, lastY = 0;
  let previewUrl = null;

  const items = []; // { dataUrl, qty }

  function drawPlaceholder() {
    ctx.save();
    ctx.beginPath();
    ctx.arc(canvas.width / 2, canvas.height / 2, canvas.width / 2, 0, Math.PI * 2);
    ctx.clip();
    ctx.fillStyle = '#e5e7eb';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.restore();
  }

  function render() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (!img) { drawPlaceholder(); return; }

    ctx.save();
    ctx.beginPath();
    ctx.arc(canvas.width / 2, canvas.height / 2, canvas.width / 2, 0, Math.PI * 2);
    ctx.clip();

    const w = img.width * baseScale * scale;
    const h = img.height * baseScale * scale;
    const cx = canvas.width / 2 + offsetX;
    const cy = canvas.height / 2 + offsetY;
    ctx.drawImage(img, cx - w / 2, cy - h / 2, w, h);
    ctx.restore();
  }

  function clampOffset() {
    const w = img.width * baseScale * scale;
    const h = img.height * baseScale * scale;
    const maxX = Math.max(0, (w - canvas.width) / 2);
    const maxY = Math.max(0, (h - canvas.height) / 2);
    offsetX = Math.min(maxX, Math.max(-maxX, offsetX));
    offsetY = Math.min(maxY, Math.max(-maxY, offsetY));
  }

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      const image = new Image();
      image.onload = () => {
        img = image;
        baseScale = Math.max(canvas.width / img.width, canvas.height / img.height);
        scale = 1;
        offsetX = 0;
        offsetY = 0;
        zoomRange.value = 100;
        zoomVal.textContent = '100%';
        addBtn.disabled = false;
        render();
      };
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });

  zoomRange.addEventListener('input', () => {
    scale = Number(zoomRange.value) / 100;
    zoomVal.textContent = zoomRange.value + '%';
    clampOffset();
    render();
  });

  function pointerPos(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const point = e.touches ? e.touches[0] : e;
    return { x: (point.clientX - rect.left) * scaleX, y: (point.clientY - rect.top) * scaleY };
  }

  canvas.addEventListener('pointerdown', (e) => {
    if (!img) return;
    dragging = true;
    const p = pointerPos(e);
    lastX = p.x; lastY = p.y;
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', (e) => {
    if (!dragging || !img) return;
    const p = pointerPos(e);
    offsetX += p.x - lastX;
    offsetY += p.y - lastY;
    lastX = p.x; lastY = p.y;
    clampOffset();
    render();
  });
  canvas.addEventListener('pointerup', () => { dragging = false; });
  canvas.addEventListener('pointercancel', () => { dragging = false; });
  canvas.addEventListener('wheel', (e) => {
    if (!img) return;
    e.preventDefault();
    const next = Math.min(400, Math.max(100, Number(zoomRange.value) - e.deltaY / 4));
    zoomRange.value = next;
    scale = next / 100;
    zoomVal.textContent = Math.round(next) + '%';
    clampOffset();
    render();
  }, { passive: false });

  addBtn.addEventListener('click', () => {
    if (!img) return;
    const qty = Math.max(1, Math.min(500, parseInt(qtyInput.value, 10) || 1));
    const dataUrl = canvas.toDataURL('image/png');
    items.push({ dataUrl, qty });
    renderList();
  });

  function renderList() {
    if (items.length === 0) {
      listEl.innerHTML = `<p class="buttons-empty">${root.dataset.emptyText}</p>`;
      summaryEl.textContent = '';
      exportBtn.disabled = true;
      return;
    }
    listEl.innerHTML = '';
    let total = 0;
    items.forEach((item, idx) => {
      total += item.qty;
      const row = document.createElement('div');
      row.className = 'buttons-item';
      row.innerHTML = `
        <img src="${item.dataUrl}" alt="">
        <input type="number" class="buttons-qty" min="1" max="500" value="${item.qty}">
        <span class="buttons-spacer"></span>
        <button class="buttons-btn-danger" type="button">${root.dataset.removeText}</button>
      `;
      row.querySelector('.buttons-qty').addEventListener('input', (e) => {
        const v = Math.max(1, Math.min(500, parseInt(e.target.value, 10) || 1));
        items[idx].qty = v;
        renderList();
      });
      row.querySelector('.buttons-btn-danger').addEventListener('click', () => {
        items.splice(idx, 1);
        renderList();
      });
      listEl.appendChild(row);
    });
    summaryEl.textContent = root.dataset.summaryText
      .replace('%designs%', items.length)
      .replace('%total%', total);
    exportBtn.disabled = false;
  }

  exportBtn.addEventListener('click', async () => {
    exportBtn.disabled = true;
    const originalLabel = exportBtn.textContent;
    exportBtn.textContent = root.dataset.buildingText;
    try {
      const res = await fetch(exportUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({
          guide: guideCheck.checked,
          autofill: autofillCheck.checked,
          items: items.map((i) => ({ image: i.dataUrl, qty: i.qty })),
        }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => null);
        throw new Error(data && data.error ? data.error : res.statusText);
      }
      const blob = await res.blob();
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
      }
      previewUrl = URL.createObjectURL(blob);
      pdfPreview.src = previewUrl;
      previewPanel.style.display = 'block';
      previewPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (err) {
      alert(root.dataset.errorText + ' ' + err.message);
    } finally {
      exportBtn.disabled = false;
      exportBtn.textContent = originalLabel;
    }
  });

  downloadBtn.addEventListener('click', () => {
    if (!previewUrl) return;
    const a = document.createElement('a');
    a.href = previewUrl;
    a.download = 'buttons.pdf';
    document.body.appendChild(a);
    a.click();
    a.remove();
  });

  render();
})();
