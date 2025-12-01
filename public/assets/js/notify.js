(function(){
  function ensureRoot() {
    var root = document.getElementById('toast-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'toast-root';
      document.body.appendChild(root);
    }
    return root;
  }

  function push(opts) {
    var o = opts || {};
    var message = o.message || '';
    var from = o.from || 'System';
    var accent = o.color || '#2563eb'; // default blue
  // default duration increased to 8000ms so toasts remain visible longer
  var duration = Math.max(1000, parseInt(o.duration || 8000, 10));

    var root = ensureRoot();

    var el = document.createElement('div');
    el.className = 'toast';
    el.style.setProperty('--accent', accent);

    el.innerHTML =
      '<div class="toast__body">' +
        '<div>' +
          '<div class="toast__title">' + escapeHtml(from) + '</div>' +
          '<div class="toast__msg">' + escapeHtml(message) + '</div>' +
        '</div>' +
        '<button class="toast__close" aria-label="Close" title="Close">&times;</button>' +
      '</div>' +
      '<div class="toast__progress"></div>';

    var closer = el.querySelector('.toast__close');
    var progress = el.querySelector('.toast__progress');

    // Set progress duration
    progress.style.animationDuration = duration + 'ms';

    // Insert and animate in
    root.appendChild(el);
    // ensure style application
    requestAnimationFrame(function(){ el.classList.add('show'); });

    var hideTimer = setTimeout(hide, duration);
    function hide() {
      try { clearTimeout(hideTimer); } catch(e){}
      el.classList.add('hide');
      el.addEventListener('transitionend', function(){
        if (el && el.parentNode) el.parentNode.removeChild(el);
      }, { once: true });
    }

    closer.addEventListener('click', function(ev){
      ev.preventDefault();
      hide();
    });

    return el;
  }

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(ch){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]);
    });
  }

  window.Notify = { push: push };
})();