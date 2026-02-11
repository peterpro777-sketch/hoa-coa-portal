(function(){
  function initPortal(root){
    var tabs = root.querySelectorAll('.hcp-tab');
    var panels = root.querySelectorAll('.hcp-panel');
    function activate(id){
      tabs.forEach(t=>t.setAttribute('aria-selected', t.dataset.panel===id ? 'true':'false'));
      panels.forEach(p=>p.classList.toggle('is-active', p.id===id));
    }
    tabs.forEach(function(t){
      t.addEventListener('click', function(){
        activate(t.dataset.panel);
        try {
          var u = new URL(window.location.href);
          u.searchParams.set('hcp_panel', t.dataset.panel);
          window.history.replaceState({}, document.title, u.toString());
        } catch(e){}
      });
    });
    if (tabs.length){
      try {
        var u = new URL(window.location.href);
        var p = u.searchParams.get('hcp_panel');
        if (p){ activate(p); return; }
      } catch(e){}
      activate(tabs[0].dataset.panel);
    }
  }
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.hcp-portal').forEach(initPortal);
  });
})();
