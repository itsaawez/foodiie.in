/* FOODIIE â€” public interactions. Vanilla JS, no frameworks. */
(function () {
  'use strict';

  var base = (document.body && document.body.dataset.base) || '';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function resolveUrl(url) {
    url = String(url || '').trim();
    if (!url) return '#';
    if (/^(https?:|mailto:|#)/i.test(url)) return url;
    if (base && url.indexOf(base + '/') === 0) return url;
    return base + '/' + url.replace(/^\/+/, '');
  }

  /* ---------- Mobile drawer menu ---------- */
  var drawerToggle = document.querySelector('.nav-toggle');
  var drawer = document.getElementById('mobile-drawer');
  var overlay = document.getElementById('drawer-overlay');
  var drawerClose = drawer ? drawer.querySelector('.drawer-close') : null;
  var lastFocused = null;
  var closeTimer = null;

  function openDrawer() {
    if (!drawer || !drawer.hidden) return; // no drawer, or already open
    if (closeTimer) { window.clearTimeout(closeTimer); closeTimer = null; }
    lastFocused = document.activeElement;
    drawer.hidden = false;
    if (overlay) overlay.hidden = false;
    // Force reflow so the transition runs.
    void drawer.offsetWidth;
    drawer.classList.add('open');
    if (overlay) overlay.classList.add('visible');
    document.body.classList.add('drawer-open');
    if (drawerToggle) {
      drawerToggle.setAttribute('aria-expanded', 'true');
      drawerToggle.setAttribute('aria-label', 'Close menu');
    }
    if (drawerClose) drawerClose.focus();
  }

  function closeDrawer() {
    if (!drawer || drawer.hidden) return;
    drawer.classList.remove('open');
    if (overlay) overlay.classList.remove('visible');
    document.body.classList.remove('drawer-open');
    if (drawerToggle) {
      drawerToggle.setAttribute('aria-expanded', 'false');
      drawerToggle.setAttribute('aria-label', 'Open menu');
    }
    if (closeTimer) { window.clearTimeout(closeTimer); }
    closeTimer = window.setTimeout(function () {
      closeTimer = null;
      // Only hide if a reopen hasn't happened meanwhile.
      if (!drawer.classList.contains('open')) {
        drawer.hidden = true;
        if (overlay) overlay.hidden = true;
        if (lastFocused && lastFocused.focus) lastFocused.focus();
      }
    }, 280);
  }

  function drawerIsOpen() {
    return !!drawer && !drawer.hidden;
  }

  if (drawerToggle && drawer) {
    drawerToggle.addEventListener('click', function () {
      if (drawerIsOpen()) { closeDrawer(); } else { openDrawer(); }
    });
  }
  if (drawerClose) {
    drawerClose.addEventListener('click', closeDrawer);
  }
  if (overlay) {
    overlay.addEventListener('click', closeDrawer);
  }
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Escape' || e.key === 'Esc') && drawerIsOpen()) {
      closeDrawer();
    }
  });

  // If the viewport grows to desktop size while the drawer is open, close it.
  if (window.matchMedia) {
    var desktopMq = window.matchMedia('(min-width: 940px)');
    var onMqChange = function () {
      if (desktopMq.matches && drawerIsOpen()) closeDrawer();
    };
    if (desktopMq.addEventListener) {
      desktopMq.addEventListener('change', onMqChange);
    } else if (desktopMq.addListener) {
      desktopMq.addListener(onMqChange);
    }
  }

  /* ---------- Mega Menu Accessibility ---------- */
  document.querySelectorAll('.nav-item-dropdown').forEach(function(item) {
    var trigger = item.querySelector('.nav-link');
    if (!trigger) return;

    item.addEventListener('mouseenter', function() {
      trigger.setAttribute('aria-expanded', 'true');
    });
    item.addEventListener('mouseleave', function() {
      trigger.setAttribute('aria-expanded', 'false');
    });
    item.addEventListener('focusin', function() {
      trigger.setAttribute('aria-expanded', 'true');
    });
    item.addEventListener('focusout', function(e) {
      if (!item.contains(e.relatedTarget)) {
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
    item.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        trigger.setAttribute('aria-expanded', 'false');
        trigger.focus();
      }
    });
  });

  /* ---------- Copy-link buttons ---------- */
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy') || window.location.href;
      function done() {
        var original = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = original; }, 1600);
      }
      function fallback() {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* noop */ }
        document.body.removeChild(ta);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, fallback);
      } else {
        fallback();
      }
    });
  });

  /* ---------- YouTube facades ---------- */
  document.querySelectorAll('.yt-facade').forEach(function (facade) {
    function play() {
      if (facade.classList.contains('playing')) return;
      var id = facade.getAttribute('data-id');
      if (!id) return;
      var iframe = document.createElement('iframe');
      iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';
      iframe.title = facade.getAttribute('aria-label') || 'YouTube video player';
      iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
      iframe.setAttribute('allowfullscreen', '');
      facade.innerHTML = '';
      facade.appendChild(iframe);
      facade.classList.add('playing');
      facade.removeAttribute('role');
      facade.removeAttribute('tabindex');
    }
    facade.addEventListener('click', play);
    facade.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        play();
      }
    });
  });

  /* ---------- Advanced Search & Filter System ---------- */
  var searchForm = document.getElementById('search-form');
  var searchInput = document.getElementById('search-input');
  var searchResults = document.getElementById('search-results');
  var searchFilterGroups = document.getElementById('search-filter-groups');
  var searchIndex = null;

  var activeFilters = {
    type: '',
    cuisine: '',
    diet: '',
    course: ''
  };

  function searchCardHtml(item) {
    var img = item.image ? resolveUrl(item.image) : base + '/assets/images/placeholder.jpg';
    var badge = item.category || (item.type ? item.type.toUpperCase() : '');
    var metaDetails = [];
    if (item.cuisine) metaDetails.push('ðŸŒ ' + esc(item.cuisine));
    if (item.time) metaDetails.push('â±ï¸ ' + esc(item.time));
    if (item.diet) metaDetails.push('ðŸŒ± ' + esc(item.diet));

    return '<article class="card">' +
      '<a class="card-media" href="' + esc(resolveUrl(item.url)) + '" tabindex="-1" aria-hidden="true">' +
      '<img src="' + esc(img) + '" alt="' + esc(item.title) + '" loading="lazy" decoding="async" width="400" height="250"></a>' +
      '<div class="card-body">' +
      (badge ? '<span class="card-tag">' + esc(badge) + '</span>' : '') +
      '<h3 class="card-title"><a href="' + esc(resolveUrl(item.url)) + '">' + esc(item.title) + '</a></h3>' +
      (item.description ? '<p class="card-desc">' + esc(item.description) + '</p>' : '') +
      (metaDetails.length ? '<div class="card-meta">' + metaDetails.join(' &bull; ') + '</div>' : '') +
      '</div></article>';
  }

  function filterMatches(item, q) {
    if (activeFilters.type && item.type !== activeFilters.type) return false;
    if (activeFilters.cuisine && String(item.cuisine || '').toLowerCase().indexOf(activeFilters.cuisine) === -1) return false;
    if (activeFilters.diet && String(item.diet || '').toLowerCase().indexOf(activeFilters.diet) === -1) return false;
    if (activeFilters.course && String(item.course || '').toLowerCase().indexOf(activeFilters.course) === -1) return false;

    if (q) {
      var hay = [
        item.title, item.description, item.category,
        item.cuisine, item.course, item.diet, (item.tags || []).join(' ')
      ].join(' ').toLowerCase();
      if (hay.indexOf(q) === -1) return false;
    }
    return true;
  }

  function renderSearch(query, items) {
    var q = (query || '').toLowerCase().trim();
    var hasFilter = !!(activeFilters.type || activeFilters.cuisine || activeFilters.diet || activeFilters.course);

    if (!q && !hasFilter) {
      searchResults.innerHTML = '<p class="muted">Type keywords or click any filter pill above to browse items.</p>';
      return;
    }

    var hits = items.filter(function (it) {
      return filterMatches(it, q);
    }).slice(0, 40);

    if (!hits.length) {
      searchResults.innerHTML = '<div style="text-align:center;padding:2.5rem 1rem;"><p>No results found matching your criteria. Try loosening your filters or search keywords.</p></div>';
      return;
    }

    var label = 'Found <strong>' + hits.length + '</strong> ' + (hits.length === 1 ? 'item' : 'items');
    if (q) label += ' for &ldquo;' + esc(query) + '&rdquo;';
    if (hasFilter) {
      var activeNames = Object.keys(activeFilters).filter(function(k) { return !!activeFilters[k]; }).map(function(k) { return activeFilters[k]; });
      label += ' (filtered by: ' + esc(activeNames.join(', ')) + ')';
    }

    searchResults.innerHTML =
      '<p class="muted" style="margin-bottom:1.25rem;">' + label + '</p>' +
      '<div class="card-grid">' + hits.map(searchCardHtml).join('') + '</div>';
  }

  function loadIndex(callback) {
    if (searchIndex) { callback(searchIndex); return; }
    searchResults.innerHTML = '<p class="muted">Loading search directory&hellip;</p>';
    fetch(base + '/search-index.json', { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('index');
        return r.json();
      })
      .then(function (json) {
        searchIndex = json.items || json || [];
        callback(searchIndex);
      })
      .catch(function () {
        searchResults.innerHTML = '<p>Search directory is temporarily unavailable. Please try again.</p>';
      });
  }

  function executeSearch() {
    var q = searchInput ? searchInput.value : '';
    loadIndex(function (items) { renderSearch(q, items); });
  }

  if (searchInput && searchResults) {
    var debounce = null;
    searchInput.addEventListener('input', function () {
      clearTimeout(debounce);
      debounce = setTimeout(executeSearch, 220);
    });
    if (searchForm) {
      searchForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounce);
        executeSearch();
      });
    }

    // Filter pill click listeners
    if (searchFilterGroups) {
      searchFilterGroups.querySelectorAll('.filter-pill').forEach(function (pill) {
        pill.addEventListener('click', function () {
          var group = pill.closest('[data-filter-group]');
          if (!group) return;
          var groupName = group.getAttribute('data-filter-group');
          var val = pill.getAttribute('data-filter-val') || '';

          group.querySelectorAll('.filter-pill').forEach(function (p) { p.classList.remove('active'); });
          pill.classList.add('active');

          activeFilters[groupName] = val.toLowerCase();
          executeSearch();
        });
      });
    }

    var preset = new URLSearchParams(window.location.search).get('q');
    if (preset) {
      searchInput.value = preset;
      executeSearch();
    }
  }

  /* ---------- Bookmarks / Saved Recipes Tab View ---------- */
  var tabSearch = document.getElementById('tab-search');
  var tabBookmarks = document.getElementById('tab-bookmarks');
  var searchView = document.getElementById('search-view');
  var bookmarksView = document.getElementById('bookmarks-view');
  var bookmarksResults = document.getElementById('bookmarks-results');
  var savedCounter = document.getElementById('saved-recipes-count');

  function updateBookmarksCounter() {
    try {
      var arr = JSON.parse(window.localStorage.getItem('foodiie_saved_recipes') || '[]');
      if (savedCounter) savedCounter.textContent = String(arr.length);
    } catch (e) {}
  }
  updateBookmarksCounter();

  function renderBookmarks() {
    if (!bookmarksResults) return;
    var list = [];
    try {
      list = JSON.parse(window.localStorage.getItem('foodiie_saved_recipes') || '[]');
    } catch (e) {}

    updateBookmarksCounter();

    if (!list.length) {
      bookmarksResults.innerHTML = '<div style="text-align:center;padding:3rem 1rem;">' +
        '<p style="font-size:1.15rem;margin-bottom:.5rem;">You haven\'t saved any recipes yet.</p>' +
        '<p class="muted">Click the &ldquo;Save&rdquo; heart button on any recipe card to keep it here for quick access.</p>' +
        '<a href="' + esc(resolveUrl('recipes/')) + '" class="btn btn-primary" style="margin-top:.75rem;">Browse Recipes</a>' +
        '</div>';
      return;
    }

    var html = '<div class="card-grid">' + list.map(function (it, idx) {
      return '<article class="card">' +
        '<div class="card-body">' +
        '<span class="card-tag">Saved Recipe</span>' +
        '<h3 class="card-title"><a href="' + esc(resolveUrl(it.url)) + '">' + esc(it.title) + '</a></h3>' +
        '<button type="button" class="bookmark-item-remove" data-remove-idx="' + idx + '">Remove from Saved &times;</button>' +
        '</div></article>';
    }).join('') + '</div>';

    bookmarksResults.innerHTML = html;

    bookmarksResults.querySelectorAll('.bookmark-item-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idx = parseInt(btn.getAttribute('data-remove-idx'), 10);
        list.splice(idx, 1);
        try {
          window.localStorage.setItem('foodiie_saved_recipes', JSON.stringify(list));
        } catch (e) {}
        renderBookmarks();
      });
    });
  }

  if (tabSearch && tabBookmarks) {
    tabSearch.addEventListener('click', function () {
      tabSearch.classList.add('active');
      tabBookmarks.classList.remove('active');
      if (searchView) searchView.style.display = 'block';
      if (bookmarksView) bookmarksView.style.display = 'none';
    });
    tabBookmarks.addEventListener('click', function () {
      tabBookmarks.classList.add('active');
      tabSearch.classList.remove('active');
      if (searchView) searchView.style.display = 'none';
      if (bookmarksView) bookmarksView.style.display = 'block';
      renderBookmarks();
    });
  }

  /* ---------- Newsletter / contact forms ---------- */
  function setMsg(form, text, kind) {
    var msg = form.querySelector('.form-msg');
    if (!msg) return;
    msg.textContent = text;
    msg.className = 'form-msg' + (kind ? ' ' + kind : '');
  }

  function bindAjaxForm(form) {
    var action = form.getAttribute('action');
    if (!action) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var honeypot = form.querySelector('input[name="website"]');
      if (honeypot && honeypot.value) return; // bots: silently drop
      setMsg(form, 'Sending\u2026', '');
      var submitBtn = form.querySelector('[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;
      fetch(base + '/api/csrf.php', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (csrf) {
          var fd = new FormData(form);
          fd.append('csrf_token', csrf.token || csrf.csrf_token || csrf.csrf || '');
          return fetch(action, { method: 'POST', body: fd, credentials: 'same-origin' });
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json.ok || json.success) {
            setMsg(form, json.message || 'Done! Please check your inbox to confirm.', 'ok');
            form.reset();
          } else {
            setMsg(form, json.message || json.error || 'Something went wrong. Please try again.', 'err');
          }
        })
        .catch(function () {
          setMsg(form, 'Something went wrong. Please try again.', 'err');
        })
        .finally(function () {
          if (submitBtn) submitBtn.disabled = false;
        });
    });
  }

  document.querySelectorAll('form[data-newsletter]').forEach(bindAjaxForm);
  document.querySelectorAll('form[data-contact]').forEach(bindAjaxForm);

  /* ---------- Recipe Actions: Print, Save, Jump ---------- */
  document.querySelectorAll('[data-action="print"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      window.print();
    });
  });

  // Save / Bookmark recipe via localStorage
  var saveBtns = document.querySelectorAll('.btn-save-recipe');
  if (saveBtns.length) {
    var getSaved = function () {
      try {
        return JSON.parse(window.localStorage.getItem('foodiie_saved_recipes') || '[]');
      } catch (e) {
        return [];
      }
    };
    var setSaved = function (arr) {
      try {
        window.localStorage.setItem('foodiie_saved_recipes', JSON.stringify(arr));
      } catch (e) {}
    };

    saveBtns.forEach(function (btn) {
      var slug = btn.getAttribute('data-slug');
      var title = btn.getAttribute('data-title');
      var url = btn.getAttribute('data-url') || window.location.href;
      var textSpan = btn.querySelector('.save-btn-text');

      var list = getSaved();
      var isSaved = list.some(function (it) { return it.slug === slug; });
      if (isSaved) {
        btn.classList.add('is-saved');
        if (textSpan) textSpan.textContent = 'Saved â¤ï¸';
      }

      btn.addEventListener('click', function () {
        var current = getSaved();
        var idx = current.findIndex(function (it) { return it.slug === slug; });
        if (idx > -1) {
          current.splice(idx, 1);
          btn.classList.remove('is-saved');
          if (textSpan) textSpan.textContent = 'Save';
        } else {
          current.push({ slug: slug, title: title, url: url });
          btn.classList.add('is-saved');
          if (textSpan) textSpan.textContent = 'Saved â¤ï¸';
        }
        setSaved(current);
      });
    });
  }

  /* ---------- Ingredient Checklist Actions ---------- */
  var ingList = document.getElementById('recipe-ingredients-list');
  if (ingList) {
    var cbs = ingList.querySelectorAll('.recipe-ingredient-cb');
    document.querySelectorAll('[data-ing-action="check-all"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        cbs.forEach(function (cb) { cb.checked = true; });
      });
    });
    document.querySelectorAll('[data-ing-action="uncheck-all"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        cbs.forEach(function (cb) { cb.checked = false; });
      });
    });
  }

  /* ---------- Star Rating Interactive Widget ---------- */
  var ratingWidget = document.querySelector('[data-recipe-rating-widget]');
  if (ratingWidget) {
    var rSlug = ratingWidget.getAttribute('data-slug') || '';
    var rDefault = parseFloat(ratingWidget.getAttribute('data-current') || '4.8');
    var rLabel = ratingWidget.querySelector('[data-rating-label]');
    var stars = ratingWidget.querySelectorAll('.star-icon');

    var userRating = null;
    try {
      userRating = window.localStorage.getItem('foodiie_rating_' + rSlug);
    } catch (e) {}

    function highlightStars(count) {
      stars.forEach(function (s) {
        var val = parseInt(s.getAttribute('data-star') || '0', 10);
        if (val <= count) {
          s.classList.add('filled');
          s.classList.remove('empty');
        } else {
          s.classList.remove('filled');
          s.classList.add('empty');
        }
      });
    }

    if (userRating) {
      var n = parseInt(userRating, 10);
      highlightStars(n);
      if (rLabel) rLabel.innerHTML = 'Your rating: <strong>' + n + '</strong>/5 â­';
    }

    stars.forEach(function (star) {
      var starVal = parseInt(star.getAttribute('data-star') || '0', 10);
      star.addEventListener('mouseenter', function () {
        highlightStars(starVal);
      });
      star.addEventListener('click', function () {
        try {
          window.localStorage.setItem('foodiie_rating_' + rSlug, String(starVal));
        } catch (e) {}
        userRating = starVal;
        highlightStars(starVal);
        if (rLabel) rLabel.innerHTML = 'Rated <strong>' + starVal + '</strong>/5! Thank you â¤ï¸';
      });
    });

    var starsContainer = ratingWidget.querySelector('.recipe-rating-stars');
    if (starsContainer) {
      starsContainer.addEventListener('mouseleave', function () {
        if (userRating) {
          highlightStars(parseInt(userRating, 10));
        } else {
          highlightStars(Math.round(rDefault));
        }
      });
    }
  }

  /* ---------- Cookie banner ---------- */
  var banner = document.getElementById('cookie-banner');
  if (banner) {
    var accepted = false;
    try {
      accepted = !!window.localStorage.getItem('fd_consent');
    } catch (e) { /* storage unavailable */ }
    if (!accepted) {
      banner.hidden = false;
      banner.classList.add('visible');
    }
    var acceptBtn = banner.querySelector('[data-accept-cookies]');
    if (acceptBtn) {
      acceptBtn.addEventListener('click', function () {
        try { window.localStorage.setItem('fd_consent', '1'); } catch (e) { /* noop */ }
        banner.classList.remove('visible');
        banner.hidden = true;
      });
    }
  }

  /* ---------- YouTube Recipe Video Embed Facade ---------- */
  function initYouTubeEmbeds() {
    var wraps = document.querySelectorAll('[data-youtube-embed]');
    wraps.forEach(function (wrap) {
      var facade = wrap.querySelector('.recipe-video-facade');
      var embedSrc = wrap.getAttribute('data-embed-src');
      var videoTitle = wrap.getAttribute('data-title') || 'Recipe Video';
      var videoId = wrap.getAttribute('data-video-id') || '';

      if (!facade || !embedSrc) return;

      function loadIframe() {
        if (wrap.querySelector('.recipe-video-iframe')) return;

        var iframe = document.createElement('iframe');
        iframe.className = 'recipe-video-iframe';
        var sep = embedSrc.indexOf('?') !== -1 ? '&' : '?';
        iframe.src = embedSrc + sep + 'autoplay=1';
        iframe.title = videoTitle;
        iframe.setAttribute('loading', 'lazy');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        iframe.setAttribute('allowfullscreen', 'true');

        if (typeof window.gtag === 'function') {
          try {
            window.gtag('event', 'youtube_video_click', {
              'video_id': videoId,
              'video_title': videoTitle
            });
          } catch (e) {}
        }

        wrap.appendChild(iframe);
        facade.style.display = 'none';
      }

      facade.addEventListener('click', loadIframe);
      facade.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32) {
          e.preventDefault();
          loadIframe();
        }
      });
    });

    document.addEventListener('click', function (e) {
      var link = e.target.closest('[data-action="youtube-video-click"]');
      if (link && typeof window.gtag === 'function') {
        try {
          window.gtag('event', 'youtube_video_click', {
            'video_id': link.getAttribute('data-video-id') || '',
            'destination': 'youtube_watch'
          });
        } catch (err) {}
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initYouTubeEmbeds);
  } else {
    initYouTubeEmbeds();
  }
})();


