// Navigation only: existing page controllers own data loading and all mutations.
const rail = document.querySelector('[data-ed-current]');
if (rail) {
  // Fixed navigation must share the app bars' stacking context, outside page shells.
  document.body.prepend(...[rail, document.querySelector('[data-admin-rail-toggle]'), document.querySelector('[data-admin-rail-backdrop]')].filter(Boolean));
  const map = JSON.parse(document.getElementById('ed-navigation-map').textContent);
  const pages = new Map(map.pages.map(page => [page.id, page]));
  const dialog = document.querySelector('[data-ed-search]');
  const input = document.querySelector('[data-ed-search-input]');
  const results = document.querySelector('[data-ed-search-results]');
  const root = document.querySelector('[data-admin-dashboard]');
  let current = pages.get(rail.dataset.edCurrent) || pages.get('overview');
  let resultIndex = 0;
  const parentOf = page => page.parent ? parentOf(pages.get(page.parent)) : page;
  const normalizeView = view => view === 'home' ? 'campaigns' : view;
  const areaTitle = id => map.areas.find(area => area.id === id).title;
  const groups = [...rail.querySelectorAll('[data-ed-group]')];
  const setGroupOpen = (group, open) => {
    group.classList.toggle('is-open', open);
    group.querySelector('[data-ed-group-toggle]').setAttribute('aria-expanded', String(open));
  };
  // Arriving in an area shows that area's pages and folds the others away; the
  // chevrons then let the owner peek into any other area without leaving the page.
  let shownArea = '';
  const showArea = id => {
    rail.querySelectorAll('[data-ed-area]').forEach(button => {
      button.classList.toggle('is-selected', button.dataset.edArea === id);
      if (button.dataset.edArea === id) button.setAttribute('aria-current', 'location');
      else button.removeAttribute('aria-current');
    });
    if (shownArea !== id) groups.forEach(group => setGroupOpen(group, group.dataset.edGroup === id));
    else setGroupOpen(groups.find(group => group.dataset.edGroup === id), true);
    shownArea = id;
  };
  groups.forEach(group => group.querySelector('[data-ed-group-toggle]').addEventListener('click', () => setGroupOpen(group, !group.classList.contains('is-open'))));
  const searchKey = rail.querySelector('[data-ed-search-key]');
  if (searchKey && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) searchKey.textContent = '⌘ /';
  const host = document.querySelector('.admin-shell-main') || (document.body.classList.contains('ed-standalone') ? document.querySelector('main') : null);
  const breadcrumb = document.createElement('nav');
  breadcrumb.className = 'ed-breadcrumb';
  breadcrumb.setAttribute('aria-label', 'Page location');
  const tools = document.querySelector('[data-ed-page-tools]');
  const backLink = document.querySelector('[data-ed-back]');
  const budgetAlert = document.querySelector('[data-ed-budget-alert]');
  tools.append(breadcrumb);
  host?.prepend(tools, budgetAlert);
  const trailKey = 'executive-navigation-trail-v1';
  let trail = [];
  try { trail = JSON.parse(sessionStorage.getItem(trailKey) || '[]'); } catch (_) {}
  if (!Array.isArray(trail)) trail = [];
  trail = trail.filter(path => typeof path === 'string' && path.startsWith('/') && !path.startsWith('//')).slice(-50);
  // Consume a Back request only after navigation succeeds (unsaved-change guards may cancel it).
  try {
    const pending = sessionStorage.getItem(trailKey + '-back');
    if (pending === location.pathname + location.search + location.hash && trail.at(-2) === pending) trail.pop();
    sessionStorage.removeItem(trailKey + '-back');
  } catch (_) {}
  let trackedPage = '';
  const syncBack = () => {
    const url = new URL(window.location.href);
    if (root) url.searchParams.set('view', new URL(current.href, location.origin).searchParams.get('view'));
    const visit = url.pathname + url.search + url.hash;
    if (trackedPage !== current.id) {
      if (trail.at(-1) !== visit) trail.push(visit);
      trackedPage = current.id;
    } else if (trail.length) trail[trail.length - 1] = visit;
    trail = trail.slice(-50);
    const areaHome = pages.get(map.areas.find(area => area.id === current.area).landing);
    const fallback = current.parent ? parentOf(current).href : areaHome.id !== current.id ? areaHome.href : current.id !== 'overview' ? pages.get('overview').href : '/';
    backLink.href = trail.length > 1 ? trail[trail.length - 2] : fallback;
    try { sessionStorage.setItem(trailKey, JSON.stringify(trail)); } catch (_) {}
  };
  backLink.addEventListener('click', event => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    try { sessionStorage.setItem(trailKey + '-back', new URL(backLink.href).pathname + new URL(backLink.href).search + new URL(backLink.href).hash); } catch (_) {}
  });
  // Capture filters changed through replaceState before following another page.
  document.addEventListener('click', event => {
    if (event.target instanceof Element && event.target.closest('a')) syncBack();
  }, true);
  window.addEventListener('pagehide', syncBack);
  const link = (title, href) => { const a = document.createElement('a'); a.textContent = title; a.href = href; return a; };
  const sync = () => {
    const view = root?.dataset.activeView;
    if (view) current = map.pages.find(page => page.href === '/dashboard/?view=' + normalizeView(view)) || current;
    const parent = parentOf(current);
    syncBack();
    showArea(current.area);
    rail.querySelectorAll('[data-ed-page]').forEach(a => {
      const active = a.dataset.edPage === parent.id;
      a.classList.toggle('is-current', active);
      if (active) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    breadcrumb.replaceChildren();
    const area = document.createElement('button'); area.type = 'button'; area.textContent = areaTitle(current.area);
    area.addEventListener('click', () => { showArea(current.area); if (matchMedia('(max-width: 767px)').matches) document.querySelector('[data-admin-rail-toggle]')?.click(); });
    breadcrumb.append(area);
    if (current.parent) { const sep = document.createElement('span'); sep.textContent = '/'; breadcrumb.append(sep, link(parent.title, parent.href)); }
    const slash = document.createElement('span'); slash.textContent = '/';
    const title = document.createElement('span'); title.textContent = current.title; title.setAttribute('aria-current', 'page');
    breadcrumb.append(slash, title);
  };
  if (root) new MutationObserver(sync).observe(root, {attributes:true, attributeFilter:['data-active-view']});
  sync();
  // Record pages keep their existing URLs and IDs. Search leads to the owning list,
  // where a real record is selected; it never invents an ID or opens an empty record.
  const aliases = {activation:'hard set big set',connections:'back dash api ingest authorization',ads:'ad view shopee',inventory:'inventory recap stock',customers:'repeat customers',accounting:'cash control profit-loss', 'direct-history':'whatsapp history walk in', 'direct-new':'direct orders whatsapp orders walk in'};
  const drawResults = () => {
    const query = input.value.trim().toLowerCase();
    const matches = map.pages.filter(page => `${page.title} ${page.description} ${areaTitle(page.area)} ${aliases[page.id] || ''}`.toLowerCase().includes(query));
    results.replaceChildren(); resultIndex = 0;
    matches.forEach((page, index) => {
      const destination = page.parent ? parentOf(page) : page;
      const a = link(page.title, destination.href);
      a.className = 'ed-search-result' + (index === 0 ? ' is-selected' : '');
      const context = document.createElement('small'); context.textContent = `${areaTitle(page.area)} / ${page.parent ? destination.title + ' · select a record' : page.section.toLowerCase()}`;
      a.append(context); a.addEventListener('click', () => dialog.close()); results.append(a);
    });
    if (!matches.length) { const p = document.createElement('p'); p.textContent = 'No matching pages.'; results.append(p); }
  };
  const openSearch = () => { input.value = ''; drawResults(); dialog.showModal(); input.focus(); };
  document.querySelectorAll('[data-ed-search-open]').forEach(button => button.addEventListener('click', openSearch));
  document.querySelector('[data-ed-search-close]').addEventListener('click', () => dialog.close());
  input.addEventListener('input', drawResults);
  // Cmd/Ctrl+/ avoids taking over the existing dashboard's order/content search.
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key === '/') { event.preventDefault(); if (dialog.open) dialog.close(); else openSearch(); }
    if (!dialog.open || !['ArrowDown','ArrowUp','Enter'].includes(event.key)) return;
    const links = [...results.querySelectorAll('a')]; if (!links.length) return;
    event.preventDefault();
    if (event.key === 'Enter') { links[resultIndex].click(); return; }
    resultIndex = (resultIndex + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
    links.forEach((a,i) => a.classList.toggle('is-selected', i === resultIndex));
    links[resultIndex].scrollIntoView({block:'nearest'});
  });
  // Sidebar width. From 1200px up the owner chooses full or icon rail and the choice
  // is remembered on this device. From 768px to 1199px it is always the rail, and
  // the same button opens the full panel over the page until a page is chosen.
  const docEl = document.documentElement;
  const wideQuery = matchMedia('(min-width: 1200px)');
  const railQuery = matchMedia('(min-width: 768px)');
  const collapseButton = rail.querySelector('[data-ed-collapse]');
  const collapseKey = 'ed-nav-collapsed';
  const storedCollapsed = () => { try { return localStorage.getItem(collapseKey) === '1'; } catch (_) { return false; } };
  const isPeek = () => rail.classList.contains('is-peek');
  const isRail = () => railQuery.matches && docEl.dataset.edNav === 'rail' && !isPeek();
  const syncCollapseButton = () => {
    const expanded = !isRail();
    collapseButton.setAttribute('aria-expanded', String(expanded));
    collapseButton.setAttribute('aria-label', expanded ? 'Collapse sidebar' : 'Expand sidebar');
    collapseButton.title = expanded ? 'Collapse sidebar' : 'Expand sidebar';
  };
  let flyout = null, flyoutTimer = 0, keepFlyoutClosed = false;
  const closeFlyout = () => {
    clearTimeout(flyoutTimer);
    flyout?.classList.remove('is-flyout');
    flyout = null;
  };
  const openFlyout = group => {
    clearTimeout(flyoutTimer);
    if (!isRail() || flyout === group) return;
    closeFlyout();
    flyout = group;
    group.classList.add('is-flyout');
    // Line the panel up with its icon, then pull it up if it would leave the window.
    const panel = group.querySelector('.ed-group-pages');
    panel.style.top = '8px';
    panel.style.top = Math.max(8, Math.min(group.getBoundingClientRect().top, innerHeight - panel.offsetHeight - 8)) + 'px';
  };
  groups.forEach(group => {
    group.addEventListener('pointerenter', event => {
      if (event.pointerType === 'touch') return;
      clearTimeout(flyoutTimer);
      if (flyout) openFlyout(group); else flyoutTimer = setTimeout(() => openFlyout(group), 90);
    });
    // Leaving one area must not close another area's flyout that has since opened.
    group.addEventListener('pointerleave', () => { clearTimeout(flyoutTimer); flyoutTimer = setTimeout(() => { if (flyout === group) closeFlyout(); }, 220); });
    group.addEventListener('focusin', () => { if (!keepFlyoutClosed) openFlyout(group); });
    group.addEventListener('focusout', event => { if (flyout === group && !group.contains(event.relatedTarget)) closeFlyout(); });
  });
  rail.querySelector('.ed-navigation-columns').addEventListener('scroll', closeFlyout, {passive: true});
  const setPeek = open => {
    if (isPeek() === open) return;
    closeFlyout();
    rail.classList.toggle('is-peek', open);
    syncCollapseButton();
  };
  const applyNavMode = () => {
    docEl.dataset.edNav = !wideQuery.matches || storedCollapsed() ? 'rail' : 'full';
    if (wideQuery.matches || !railQuery.matches) rail.classList.remove('is-peek');
    closeFlyout();
    syncCollapseButton();
  };
  collapseButton.addEventListener('click', () => {
    if (!wideQuery.matches) { setPeek(!isPeek()); return; }
    try { localStorage.setItem(collapseKey, docEl.dataset.edNav === 'rail' ? '0' : '1'); } catch (_) {}
    applyNavMode();
    // Charts and tables that size themselves on resize need to hear about the new width.
    window.dispatchEvent(new Event('resize'));
  });
  document.addEventListener('pointerdown', event => { if (isPeek() && !rail.contains(event.target)) setPeek(false); });
  rail.addEventListener('click', event => {
    if (event.target instanceof Element && event.target.closest('a')) { setPeek(false); closeFlyout(); }
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || (!flyout && !isPeek())) return;
    // Escape from inside a flyout returns focus to its area icon without reopening it.
    const focused = flyout?.contains(document.activeElement) ? flyout : null;
    closeFlyout();
    setPeek(false);
    if (focused) { keepFlyoutClosed = true; focused.querySelector('[data-ed-area]').focus(); keepFlyoutClosed = false; }
  });
  wideQuery.addEventListener('change', applyNavMode);
  railQuery.addEventListener('change', applyNavMode);
  applyNavMode();
  // Preserve unpaid-order, stock and low-ad-credit awareness when an area is closed.
  const syncAlerts = () => {
    const lowCredit = rail.querySelector('[data-ed-page="ads"]')?.classList.contains('is-credit-alert') || false;
    budgetAlert.hidden = !lowCredit;
    rail.querySelector('[data-ed-budget-link]').hidden = !lowCredit;
    const growth = rail.querySelector('[data-ed-area="growth"]');
    growth.classList.toggle('has-budget-alert', lowCredit);
    growth.setAttribute('aria-label', lowCredit ? 'Growth — ad credit threshold reached' : 'Growth');
    for (const [area,selector,flag] of [['sales','[data-ed-page="orders"]','has-unpaid-direct-order'],['growth','[data-ed-page="ads"]','is-credit-alert'],['products','[data-ed-page="inventory"]','has-critical-dot']]) {
      rail.querySelector(`[data-ed-area="${area}"]`).classList.toggle('has-alert', rail.querySelector(selector)?.classList.contains(flag) || false);
    }
  };
  for (const selector of ['[data-ed-page="orders"]','[data-ed-page="ads"]','[data-ed-page="inventory"]']) {
    const source = rail.querySelector(selector);
    if (source) new MutationObserver(syncAlerts).observe(source,{attributes:true,attributeFilter:['class']});
  }
  syncAlerts();
  const toggle = document.querySelector('[data-admin-rail-toggle]');
  const moreButton = document.querySelector('[data-mobile-nav-more]');
  const tabbar = document.querySelector('.admin-mobile-tabbar');
  const mobileQuery = matchMedia('(max-width: 767px)');
  let wasMobileOpen = false;
  let hostWasInert = false;
  const syncMobile = () => {
    const open = mobileQuery.matches && document.body.classList.contains('admin-rail-open');
    const focusWasInside = rail.contains(document.activeElement);
    rail.inert = mobileQuery.matches && !open;
    moreButton?.setAttribute('aria-expanded', String(open));
    if (open && !wasMobileOpen) {
      hostWasInert = host?.inert || false;
      if (host) host.inert = true;
      if (tabbar) tabbar.inert = true;
      rail.querySelector('.ed-brand')?.focus();
    }
    if (!open && wasMobileOpen) {
      if (host) host.inert = hostWasInert;
      if (tabbar) tabbar.inert = false;
      if (mobileQuery.matches && (focusWasInside || document.activeElement === toggle)) (moreButton?.getClientRects().length ? moreButton : toggle)?.focus();
    }
    wasMobileOpen = open;
  };
  document.addEventListener('keydown', event => {
    if (event.key !== 'Tab' || !wasMobileOpen || dialog.open) return;
    const controls = [...rail.querySelectorAll('a,button,input')].filter(el => el.offsetParent !== null);
    controls.push(toggle);
    const first = controls[0], last = controls[controls.length-1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  new MutationObserver(syncMobile).observe(document.body,{attributes:true,attributeFilter:['class']});
  mobileQuery.addEventListener('change',syncMobile); syncMobile();
  dialog.addEventListener('close', () => { if (mobileQuery.matches && rail.inert) (moreButton?.getClientRects().length ? moreButton : toggle)?.focus(); });
}
