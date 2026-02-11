
// Admin tooltips
(function(){
  function closeAll(){
    document.querySelectorAll('.hcp-admin-tooltip.is-open').forEach(function(el){el.classList.remove('is-open');});
  }
  document.addEventListener('click', function(e){
    var tip = e.target.closest('.hcp-admin-tip');
    if(!tip){ closeAll(); return; }
    e.preventDefault();
    var wrap = tip.closest('.hcp-admin-tooltip');
    if(!wrap) return;
    var open = wrap.classList.contains('is-open');
    closeAll();
    if(!open){ wrap.classList.add('is-open'); }
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){ closeAll(); }
  });
})();

// Print audit summary
(function(){
  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-hcp-print-audit]');
    if(!btn) return;
    e.preventDefault();
    window.print();
  });
})();
