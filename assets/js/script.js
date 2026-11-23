// assets/js/script.js - poore project ka common JavaScript
// Sab kuch ek hi DOMContentLoaded (page tayyar hone par) ke andar hai.

document.addEventListener('DOMContentLoaded', function () {

  // 1) Navbar shadow + Back-to-top button (scroll par)
  const navbar = document.querySelector('.site-navbar');
  const backToTop = document.getElementById('backToTop');
  window.addEventListener('scroll', function () {
    if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 10);
    if (backToTop) backToTop.style.display = window.scrollY > 300 ? 'block' : 'none';
  });
  if (backToTop) {
    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // 3) Confirm: <a/button data-confirm="Are you sure?">  (achha sa modal, browser ka purana confirm() nahi)
  function showConfirm(message, onOk) {
    let modalEl = document.getElementById('confirmModal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.id = 'confirmModal';
      modalEl.className = 'modal fade';
      modalEl.tabIndex = -1;
      modalEl.innerHTML =
        '<div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-body text-center p-4">' +
        '<div class="confirm-ico"><i class="bi bi-exclamation-triangle"></i></div>' +
        '<h5 class="mb-2">Are you sure?</h5><p class="text-muted small confirm-msg mb-4"></p>' +
        '<div class="d-flex gap-2 justify-content-center">' +
        '<button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>' +
        '<button type="button" class="btn btn-danger px-4 confirm-ok">Yes, continue</button>' +
        '</div></div></div></div>';
      document.body.appendChild(modalEl);
    }
    modalEl.querySelector('.confirm-msg').textContent = message;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const oldBtn = modalEl.querySelector('.confirm-ok');
    const okBtn = oldBtn.cloneNode(true);                 // purane click listeners hat jayen
    oldBtn.parentNode.replaceChild(okBtn, oldBtn);
    okBtn.addEventListener('click', function () { modal.hide(); onOk(); });
    modal.show();
  }
  function proceed(el) {
    const form = el.closest('form');
    if (el.tagName === 'A') { window.location.href = el.href; }
    else if (form) {
      // form.submit() button ka name/value nahi bhejta, isliye usay hidden field bana kar saath bhejo
      if (el.name) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = el.name; hidden.value = el.value;
        form.appendChild(hidden);
      }
      form.submit();
    }
  }
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (event) {
      event.preventDefault();
      const message = el.getAttribute('data-confirm');
      if (window.bootstrap) { showConfirm(message, function () { proceed(el); }); }
      else if (confirm(message)) { proceed(el); }          // bootstrap na chale to purana tareeqa
    });
  });

  // 4) Table live search: <input data-table-search="#tableId">
  document.querySelectorAll('[data-table-search]').forEach(function (input) {
    input.addEventListener('keyup', function () {
      const keyword = input.value.toLowerCase();
      document.querySelectorAll(input.getAttribute('data-table-search') + ' tbody tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(keyword) ? '' : 'none';
      });
    });
  });

  // 5) Password dikhao/chhupao: <button data-toggle-password="#password">
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const input = document.querySelector(btn.getAttribute('data-toggle-password'));
      const icon = btn.querySelector('i');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });

  // 6) Register page: role (Parent / Hospital) ke hisaab se fields, labels aur hints badlo
  const hospitalRadio = document.getElementById('role_hospital');
  if (hospitalRadio) {
    const applyRole = function () {
      const role = hospitalRadio.checked ? 'hospital' : 'parent';
      document.querySelectorAll('[data-show-for]').forEach(function (el) {
        el.classList.toggle('d-none', el.getAttribute('data-show-for') !== role);
      });
      document.querySelectorAll('[data-required-for]').forEach(function (el) {
        el.required = (el.getAttribute('data-required-for') === role);
      });
      document.querySelectorAll('[data-text-parent]').forEach(function (el) {
        el.textContent = el.getAttribute('data-text-' + role);
      });
      document.querySelectorAll('[data-ph-parent]').forEach(function (el) {
        el.setAttribute('placeholder', el.getAttribute('data-ph-' + role));
      });
    };
    document.querySelectorAll('input[name="role"]').forEach(function (r) {
      r.addEventListener('change', applyRole);
    });
    applyRole();
  }

  // 7) Form validation + password match
  document.querySelectorAll('.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      const pass = form.querySelector('#password');
      const confirmBox = form.querySelector('#confirm_password');
      if (pass && confirmBox) {
        confirmBox.setCustomValidity(pass.value !== confirmBox.value ? 'Passwords do not match' : '');
      }
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });

  // 8) Admin panel: mobile par sidebar kholna/band karna
  const sidebar = document.querySelector('.admin-sidebar');
  const overlay = document.querySelector('.admin-overlay');
  if (sidebar && overlay) {
    const toggleSidebar = function () {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    };
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
      btn.addEventListener('click', toggleSidebar);
    });
    overlay.addEventListener('click', toggleSidebar);
  }

  // 9) Print button: <button data-print>
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  // 10) List filter: <input data-filter-items="#list"> aur list ke har item par class "filter-item"
  document.querySelectorAll('[data-filter-items]').forEach(function (input) {
    input.addEventListener('input', function () {
      const keyword = input.value.toLowerCase();
      document.querySelectorAll(input.getAttribute('data-filter-items') + ' .filter-item').forEach(function (item) {
        item.style.display = item.textContent.toLowerCase().includes(keyword) ? '' : 'none';
      });
    });
  });

  // 11) Scroll par elements ka halke se aana: <div data-reveal data-delay="100">
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealEls = document.querySelectorAll('[data-reveal]');
  if ('IntersectionObserver' in window && !reduceMotion) {
    const revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.01, rootMargin: '0px 0px 80px 0px' });   // thoda pehle hi dikha do, intezar nahi
    revealEls.forEach(function (el) {
      el.classList.add('reveal');
      revealObserver.observe(el);
    });
  }

  // 12) Ginti 0 se barhna: <span data-count="25">25</span>  (JS na ho to asli number dikhta rahe)
  function runCounter(el) {
    const target = parseInt(el.getAttribute('data-count'), 10) || 0;
    if (reduceMotion || target === 0) { el.textContent = target; return; }
    const duration = 600;
    const start = performance.now();
    function step(now) {
      const progress = Math.min((now - start) / duration, 1);
      el.textContent = Math.floor(progress * target);
      if (progress < 1) { requestAnimationFrame(step); } else { el.textContent = target; }
    }
    requestAnimationFrame(step);
  }
  const counters = document.querySelectorAll('[data-count]');
  if ('IntersectionObserver' in window) {
    const counterObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { runCounter(entry.target); counterObserver.unobserve(entry.target); }
      });
    }, { threshold: 0.3 });
    counters.forEach(function (c) { counterObserver.observe(c); });
  }

  // 15) Landing page hospital directory: area chips, search aur "Hospitals near me"
  const hospItems = document.querySelectorAll('.lp-hosp-item');
  if (hospItems.length) {
    let activeCity = 'all';
    let maxKm = 0;            // 0 = koi had nahi
    const hospSearch = document.getElementById('hospSearch');
    const hospEmpty  = document.getElementById('hospEmpty');
    const applyHosp = function () {
      const q = (hospSearch ? hospSearch.value : '').toLowerCase().trim();
      let shown = 0;
      hospItems.forEach(function (card) {
        const d = card._km;
        const show = (activeCity === 'all' || card.getAttribute('data-city') === activeCity)
                  && card.textContent.toLowerCase().includes(q)
                  && (!maxKm || (d !== undefined && d <= maxKm));
        card.classList.toggle('d-none', !show);
        if (show) { shown++; }
      });
      if (hospEmpty) { hospEmpty.classList.toggle('d-none', shown !== 0); }
    };
    document.querySelectorAll('[data-hosp-city]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        activeCity = chip.getAttribute('data-hosp-city');
        document.querySelectorAll('[data-hosp-city]').forEach(function (c) { c.classList.toggle('active', c === chip); });
        applyHosp();
      });
    });
    if (hospSearch) { hospSearch.addEventListener('input', applyHosp); }

    // Phone par lambi list: pehle 6 hospitals, baqi "Show all hospitals" se (search, filter ya near me par sab dikhte hain)
    const hospGrid = hospItems[0].parentNode;
    const moreWrap = document.getElementById('hospMoreWrap');
    const markExtra = function () {
      let n = 0;
      hospGrid.querySelectorAll('.lp-hosp-item').forEach(function (card) {
        const visible = !card.classList.contains('d-none');
        card.classList.toggle('is-extra', visible && ++n > 6);
      });
      if (moreWrap) { moreWrap.classList.toggle('d-none', n <= 6 || !hospGrid.classList.contains('lp-hosp-list-short')); }
    };
    if (moreWrap && hospItems.length > 6) {
      hospGrid.classList.add('lp-hosp-list-short');
      document.getElementById('hospMore').addEventListener('click', function () {
        hospGrid.classList.remove('lp-hosp-list-short');
        moreWrap.classList.add('d-none');
      });
      new MutationObserver(markExtra).observe(hospGrid, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
      markExtra();
    } else if (moreWrap) { moreWrap.classList.add('d-none'); }

    // ---- Hospitals near me ----
    const nearBtn = document.getElementById('hospNearMe');
    const panel   = document.getElementById('nearPanel');
    const title   = document.getElementById('nearTitle');
    const sub     = document.getElementById('nearSub');
    const radius  = document.getElementById('nearRadius');
    const grid    = hospItems[0].parentNode;
    const haversine = function (a, b, c, d) {
      const r = Math.PI / 180, x = Math.sin((c - a) * r / 2), y = Math.sin((d - b) * r / 2);
      return 2 * 6371 * Math.asin(Math.sqrt(x * x + Math.cos(a * r) * Math.cos(c * r) * y * y));
    };
    const fmt = function (km) { return km < 1 ? Math.round(km * 1000) + ' m' : km.toFixed(km < 10 ? 1 : 0) + ' km'; };
    const setBusy = function (busy, label) {
      nearBtn.disabled = busy;
      nearBtn.querySelector('.spinner-border').classList.toggle('d-none', !busy);
      nearBtn.querySelector('.bi').classList.toggle('d-none', busy);
      nearBtn.querySelector('.lbl').textContent = label;
    };
    const showError = function (msg) {
      panel.classList.add('is-error');
      title.textContent = 'We could not get your location';
      sub.textContent = msg;
      setBusy(false, 'Try again');
    };

    const sortByDistance = function (lat, lng) {
      const items = Array.prototype.slice.call(hospItems);
      items.forEach(function (card) {
        const hl = parseFloat(card.dataset.lat), hg = parseFloat(card.dataset.lng);
        const tags = card.querySelector('.near-tags');
        if (isNaN(hl) || isNaN(hg)) { card._km = undefined; tags.classList.add('d-none'); return; }
        card._km = haversine(lat, lng, hl, hg);
        tags.classList.remove('d-none');
        tags.querySelector('.near-dist').innerHTML = '<i class="bi bi-signpost-split me-1"></i>' +
          (card.dataset.approx === '1' ? '~' : '') + fmt(card._km) + ' away';
        tags.querySelector('.near-best').classList.add('d-none');
        const dir = card.querySelector('.hosp-dir');
        if (dir) {
          dir.href = 'https://www.google.com/maps/dir/?api=1&origin=' + lat + ',' + lng +
                     '&destination=' + encodeURIComponent(dir.dataset.dest);
        }
      });
      items.sort(function (a, b) {
        return (a._km === undefined ? 1e9 : a._km) - (b._km === undefined ? 1e9 : b._km);
      }).forEach(function (card) { grid.appendChild(card); });

      const withKm = items.filter(function (c) { return c._km !== undefined; });
      if (withKm.length) { withKm[0].querySelector('.near-best').classList.remove('d-none'); }

      // Reset city filter, show results
      activeCity = 'all';
      document.querySelectorAll('[data-hosp-city]').forEach(function (c) { c.classList.toggle('active', c.getAttribute('data-hosp-city') === 'all'); });
      applyHosp();

      panel.classList.remove('is-error');
      panel.classList.add('is-done');
      radius.classList.remove('d-none');
      if (withKm.length) {
        title.textContent = 'Nearest: ' + withKm[0].querySelector('h6').textContent.trim() + ' (' + fmt(withKm[0]._km) + ')';
        const in10 = withKm.filter(function (c) { return c._km <= 10; }).length;
        sub.textContent = in10 + ' hospital' + (in10 === 1 ? '' : 's') + ' within 10 km. Sorted from closest to farthest.';
      } else {
        title.textContent = 'Location found';
        sub.textContent = 'None of the listed hospitals has a location yet, so we could not measure distance.';
      }
      setBusy(false, 'Refresh');
      grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    if (nearBtn) {
      radius.querySelectorAll('[data-km]').forEach(function (b) {
        b.addEventListener('click', function () {
          maxKm = parseInt(b.dataset.km, 10);
          radius.querySelectorAll('[data-km]').forEach(function (x) { x.classList.toggle('active', x === b); });
          applyHosp();
        });
      });
      nearBtn.addEventListener('click', function () {
        if (!window.isSecureContext) {
          showError('Location only works on a secure (https://) site or on localhost. Please open the site with https.');
          return;
        }
        if (!navigator.geolocation) { showError('Your browser does not support location.'); return; }
        setBusy(true, 'Locating...');
        navigator.geolocation.getCurrentPosition(function (pos) {
          sortByDistance(pos.coords.latitude, pos.coords.longitude);
        }, function (err) {
          showError(err.code === 1
            ? 'Location permission is blocked. Click the lock icon in the address bar, allow Location and try again.'
            : 'Your location is not available right now. Please check that location (GPS) is on and try again.');
        }, { enableHighAccuracy: false, timeout: 15000, maximumAge: 300000 });
      });
    }
  }

  // 16) Double-click se bachao: form bhejte hi button band aur "Please wait" (ek booking do baar na ban jaye)
  document.querySelectorAll('form[method="post"]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      setTimeout(function () {
        if (event.defaultPrevented) { return; }              // validation ne rok diya to button band nahi
        form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
          btn.dataset.orig = btn.innerHTML;
          btn.disabled = true;
          if (!btn.classList.contains('btn-sm')) {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Please wait';
          }
        });
      }, 0);
    });
  });
  window.addEventListener('pageshow', function (event) {   // "Back" dabane par button wapas theek
    if (!event.persisted) { return; }
    document.querySelectorAll('button[type="submit"][data-orig]').forEach(function (btn) {
      btn.disabled = false;
      btn.innerHTML = btn.dataset.orig;
    });
  });

  // 17) Copy: <button data-copy="#id"> input ki value (ya kisi box ka text) copy karta hai
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const el = document.querySelector(btn.getAttribute('data-copy'));
      if (!el) { return; }
      const text = ('value' in el) ? el.value : el.textContent;
      const done = function () { if (window.showToast) { window.showToast('success', 'Copied to clipboard'); } };
      if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
      const tmp = document.createElement('textarea');
      tmp.value = text; tmp.style.position = 'fixed'; tmp.style.opacity = '0';
      document.body.appendChild(tmp); tmp.select(); document.execCommand('copy'); tmp.remove(); done();
    });
  });

  // 14) Textarea ki akshar ginti: <textarea data-counter="#msgCount" maxlength="2000">
  document.querySelectorAll('textarea[data-counter]').forEach(function (ta) {
    const out = document.querySelector(ta.getAttribute('data-counter'));
    const update = function () { if (out) { out.textContent = ta.value.length + ' / ' + ta.maxLength; } };
    ta.addEventListener('input', update);
    update();
  });

  // 13) Toast notifications: server ke flash message (class .toast) ko dikhao
  document.querySelectorAll('.toast').forEach(function (el) {
    if (window.bootstrap) {
      const toast = bootstrap.Toast.getOrCreateInstance(el);   // waqt toast ke data-bs-delay se
      el.addEventListener('click', function (event) {         // toast par kahin bhi click: foran band
        if (!event.target.closest('.btn-close')) { toast.hide(); }
      });
      toast.show();
    }
  });
});

// JavaScript se kahin bhi toast dikhane ke liye:  showToast('success', 'Copied!')
window.showToast = function (type, message) {
  const icons  = { success: ['bi-check-circle-fill', 'Success'], danger: ['bi-x-octagon-fill', 'Error'],
                   warning: ['bi-exclamation-triangle-fill', 'Attention'], info: ['bi-info-circle-fill', 'Info'] };
  const t = icons[type] ? type : 'info';
  const delays = { success: 1800, info: 2200, warning: 2800, danger: 3200 };
  let box = document.querySelector('.toast-container.toast-pos');
  if (!box) {
    box = document.createElement('div');
    box.className = 'toast-container toast-pos position-fixed end-0 p-3';
    document.body.appendChild(box);
  }
  const el = document.createElement('div');
  el.className = 'toast toast-vax toast-' + t;
  el.setAttribute('role', 'alert');
  el.setAttribute('data-bs-delay', delays[t]);
  el.style.setProperty('--dur', (delays[t] / 1000) + 's');
  el.innerHTML = '<div class="d-flex align-items-start gap-3 p-3"><i class="bi ' + icons[t][0] + ' toast-ico"></i>' +
    '<div class="flex-fill"><div class="fw-semibold">' + icons[t][1] + '</div><div class="small text-muted"></div></div>' +
    '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div><div class="bar"></div>';
  el.querySelector('.text-muted').textContent = message;      // textContent: koi HTML nahi chalta
  while (box.children.length >= 3) { box.removeChild(box.firstChild); }   // ziyada se ziyada 3 toast
  box.appendChild(el);
  if (window.bootstrap) {
    const toast = bootstrap.Toast.getOrCreateInstance(el);
    el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    el.addEventListener('click', function (event) { if (!event.target.closest('.btn-close')) { toast.hide(); } });
    toast.show();
  }
};
