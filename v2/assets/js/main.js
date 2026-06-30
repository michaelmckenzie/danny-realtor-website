/* main.js — frontend interactions */
'use strict';

// ─── Mobile nav toggle ─────────────────────────────────────────────────────
const navToggle = document.getElementById('navToggle');
const mainNav   = document.querySelector('.main-nav');
const headerSearch = document.querySelector('.header-search-form');
if (navToggle && mainNav) {
  navToggle.addEventListener('click', () => {
    const open = mainNav.classList.toggle('is-open');
    if (headerSearch) headerSearch.classList.toggle('is-open', open);
    navToggle.setAttribute('aria-expanded', open);
  });
}

// ─── Hero tab switching ─────────────────────────────────────────────────────
document.querySelectorAll('.hero-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.hero-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    // Update hidden input if present
    const typeInput = document.getElementById('heroListingType');
    if (typeInput) typeInput.value = tab.dataset.type || 'sale';
  });
});

// ─── Photo upload preview (admin) ──────────────────────────────────────────
const photoInput = document.getElementById('photoInput');
const previewWrap = document.getElementById('photoPreviews');
if (photoInput && previewWrap) {
  photoInput.addEventListener('change', () => {
    previewWrap.innerHTML = '';
    const files = Array.from(photoInput.files).slice(0, 20);
    files.forEach((file, i) => {
      if (!file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = e => {
        const div = document.createElement('div');
        div.className = 'photo-preview-item';
        div.innerHTML = `<img src="${e.target.result}" alt="preview ${i+1}">
          <button type="button" class="photo-remove-btn" data-index="${i}" aria-label="Remove photo">&#x2715;</button>`;
        previewWrap.appendChild(div);
      };
      reader.readAsDataURL(file);
    });
  });

  // Remove a preview (visual only — doesn't affect FileList)
  previewWrap.addEventListener('click', e => {
    const btn = e.target.closest('.photo-remove-btn');
    if (btn) btn.closest('.photo-preview-item').remove();
  });
}

// ─── Lightbox ──────────────────────────────────────────────────────────────
(function () {
  const overlay  = document.getElementById('lightboxOverlay');
  const imgEl    = document.getElementById('lightboxImg');
  const closeBtn = document.getElementById('lightboxClose');
  const prevBtn  = document.getElementById('lightboxPrev');
  const nextBtn  = document.getElementById('lightboxNext');

  if (!overlay || !imgEl) return;

  let images = [];
  let current = 0;

  function openAt(idx) {
    current = Math.max(0, Math.min(idx, images.length - 1));
    imgEl.src = images[current].src;
    imgEl.alt = images[current].alt || '';
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
    imgEl.src = '';
  }

  document.querySelectorAll('[data-lightbox]').forEach((el, i) => {
    images.push({ src: el.dataset.src || el.src, alt: el.alt });
    el.style.cursor = 'pointer';
    el.addEventListener('click', () => openAt(i));
  });

  if (closeBtn) closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  if (prevBtn) prevBtn.addEventListener('click', () => openAt(current - 1));
  if (nextBtn) nextBtn.addEventListener('click', () => openAt(current + 1));
  document.addEventListener('keydown', e => {
    if (!overlay.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft')  openAt(current - 1);
    if (e.key === 'ArrowRight') openAt(current + 1);
  });
})();

// ─── Photo delete checkboxes (edit listing) ────────────────────────────────
document.querySelectorAll('.photo-delete-check').forEach(cb => {
  cb.addEventListener('change', () => {
    const wrap = cb.closest('.photo-preview-item');
    if (wrap) wrap.style.opacity = cb.checked ? '.35' : '1';
  });
});

// ─── Filter form auto-submit on select change ──────────────────────────────
document.querySelectorAll('.filter-select[data-auto-submit]').forEach(sel => {
  sel.addEventListener('change', () => sel.closest('form').submit());
});

// ─── Contact form (property detail) — basic client validation ─────────────
const contactForm = document.getElementById('contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', e => {
    const name  = contactForm.querySelector('[name=contact_name]');
    const email = contactForm.querySelector('[name=contact_email]');
    let ok = true;
    [name, email].forEach(f => {
      if (!f) return;
      f.classList.remove('is-error');
      if (!f.value.trim()) { f.classList.add('is-error'); ok = false; }
    });
    if (!ok) { e.preventDefault(); }
  });
}

// ─── Admin: confirm before delete ──────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});
