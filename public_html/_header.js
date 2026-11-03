/* ═══════════════════════════════════════════════════════════════
   CANONICAL HEADER LOADER — VietnamSkilled
   Fetches _header.html, injects into #site-header, attaches handlers.
   Used by ALL pages. Update _header.html once → all pages sync.
═══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  function init(target) {
    // 1) Highlight current page in nav
    // (Paths align naturally per environment — local serves under /brand/, VPS under /)
    var path = location.pathname.replace(/\/+$/, '') || '/';
    target.querySelectorAll('a[href]').forEach(function (a) {
      var href = (a.getAttribute('href') || '').replace(/\/+$/, '') || '/';
      if (href === path) {
        a.classList.add('w--current');
        a.setAttribute('aria-current', 'page');
      }
    });

    // 2) Lang switcher
    var langWrap = target.querySelector('[data-lang-toggle]');
    if (langWrap) {
      var dropdown = langWrap.querySelector('.lang-dropdown');
      var txtEl = langWrap.querySelector('[data-lang-txt]');
      var imgEl = langWrap.querySelector('[data-lang-img]');

      // Toggle on click
      langWrap.addEventListener('click', function (e) {
        e.stopPropagation();
        if (dropdown) dropdown.classList.toggle('open');
      });

      // Close on outside click
      document.addEventListener('click', function () {
        document.querySelectorAll('.lang-dropdown.open').forEach(function (el) {
          el.classList.remove('open');
        });
      });

      // Lang select
      langWrap.querySelectorAll('[data-lang]').forEach(function (opt) {
        opt.addEventListener('click', function (e) {
          e.stopPropagation();
          var lang = opt.getAttribute('data-lang');
          setLangGlobal(lang);
        });
      });

      // Sync display from localStorage
      var lang = localStorage.getItem('user_lang') || 'vi';
      if (txtEl && imgEl) {
        if (lang === 'en') { txtEl.innerText = 'EN'; imgEl.src = 'https://flagcdn.com/w20/us.png'; }
        else if (lang === 'de') { txtEl.innerText = 'DE'; imgEl.src = 'https://flagcdn.com/w20/de.png'; }
        else { txtEl.innerText = 'VN'; imgEl.src = 'https://flagcdn.com/w20/vn.png'; }
      }
    }

    // 3) Hook CTA "Tư vấn miễn phí" to openModal() if exists, else fallback href
    var cta = target.querySelector('[data-header-cta]');
    if (cta && typeof window.openModal === 'function') {
      cta.addEventListener('click', function (e) {
        e.preventDefault();
        window.openModal();
      });
    }

    // 4) Mobile hamburger toggle
    var burger  = target.querySelector('.menu-mob');
    var navMenu = target.querySelector('.navi-link-wrap');
    if (burger && navMenu) {
      burger.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = navMenu.classList.toggle('vs-nav-open');
        burger.classList.toggle('vs-nav-active', isOpen);
      });
      document.addEventListener('click', function (e) {
        if (!target.contains(e.target)) {
          navMenu.classList.remove('vs-nav-open');
          burger.classList.remove('vs-nav-active');
          target.querySelectorAll('.custom-dropdown').forEach(function (d) { d.classList.remove('vs-sub-open'); });
        }
      });
    }

    // 5) Mobile submenu: tách ▼ thành button riêng — link cha điều hướng bình thường
    target.querySelectorAll('.custom-dropdown').forEach(function (dd) {
      var link = dd.querySelector(':scope > .navigation-link');
      if (!link) return;

      // Ẩn ▼ built-in trong link text (chỉ ảnh hưởng mobile qua CSS)
      var arrowSpan = link.querySelector('span');
      if (arrowSpan) arrowSpan.classList.add('vs-link-arrow');

      // Tạo button ▼ riêng biệt
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'vs-dd-arrow';
      btn.setAttribute('aria-label', 'Mở/đóng menu con');
      btn.innerHTML = '&#9660;';
      dd.insertBefore(btn, link.nextSibling);

      // Click ▼: toggle submenu (chỉ mobile)
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (window.innerWidth > 991) return;
        var wasOpen = dd.classList.contains('vs-sub-open');
        target.querySelectorAll('.custom-dropdown').forEach(function (d) { d.classList.remove('vs-sub-open'); });
        if (!wasOpen) dd.classList.add('vs-sub-open');
      });
    });
  }

  // Lang switcher: sets cookie + reloads
  window.setLangGlobal = function (lang) {
    localStorage.setItem('user_lang', lang);
    document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + location.hostname;
    if (lang !== 'vi') {
      document.cookie = 'googtrans=/vi/' + lang + '; path=/;';
      document.cookie = 'googtrans=/vi/' + lang + '; path=/; domain=' + location.hostname;
    }
    window.location.reload();
  };

  // Load header HTML and inject
  // Auto-detect base path: local XAMPP serves project under /brand/, VPS serves at root
  var BASE = location.pathname.indexOf('/brand/') === 0 ? '/brand' : '';

  // Inject _mobile.css globally on all pages
  function loadMobileCSS() {
    if (document.getElementById('vs-mobile-css')) return;
    var lk = document.createElement('link');
    lk.id   = 'vs-mobile-css';
    lk.rel  = 'stylesheet';
    lk.href = BASE + '/_mobile.css?v=1';
    document.head.appendChild(lk);
  }
  loadMobileCSS();

  function loadHeader() {
    var slot = document.getElementById('site-header');
    if (!slot) return;
    fetch(BASE + '/_header.html', { cache: 'no-cache' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status + ' fetching _header.html');
        return r.text();
      })
      .then(function (html) {
        slot.innerHTML = html;
        init(slot);
      })
      .catch(function (err) {
        console.error('[header] Failed to load:', err);
      });
  }

  function loadFooter() {
    var slot = document.getElementById('site-footer');
    if (!slot) return;
    fetch(BASE + '/_footer.html', { cache: 'no-cache' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status + ' fetching _footer.html');
        return r.text();
      })
      .then(function (html) {
        slot.innerHTML = html;
      })
      .catch(function (err) {
        console.error('[footer] Failed to load:', err);
      });
  }

  // Back-to-top button: hiện khi cuộn xuống, bấm cuộn mượt lên đầu
  function createBackToTop() {
    if (document.getElementById('vs-back-top')) return;
    var css = '#vs-back-top{position:fixed;left:24px;bottom:28px;width:48px;height:48px;border-radius:50%;border:none;background:#47979F;color:#fff;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,0.22);opacity:0;visibility:hidden;transform:translateY(12px);transition:opacity .25s ease,transform .25s ease,background .2s;z-index:100010;display:flex;align-items:center;justify-content:center;padding:0;pointer-events:all;}'
      + '#vs-back-top.show{opacity:1;visibility:visible;transform:translateY(0);}'
      + '#vs-back-top:hover{background:#1A3A5C;transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,0.28);}'
      + '#vs-back-top svg{pointer-events:none;}'
      + '@media(max-width:480px){#vs-back-top{left:14px;bottom:18px;width:44px;height:44px;}}';
    var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
    var btn = document.createElement('button');
    btn.id = 'vs-back-top';
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Lên đầu trang');
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>';
    document.documentElement.appendChild(btn);
    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    function onScroll() {
      if ((window.pageYOffset || document.documentElement.scrollTop) > 400) btn.classList.add('show');
      else btn.classList.remove('show');
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  function load() {
    loadHeader();
    loadFooter();
    createBackToTop();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
