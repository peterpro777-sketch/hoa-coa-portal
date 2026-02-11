
// Tooltips
(function(){
  function closeAll(){
    document.querySelectorAll('.hcp-tooltip.is-open').forEach(function(el){el.classList.remove('is-open');});
  }
  document.addEventListener('click', function(e){
    var tip = e.target.closest('.hcp-tip');
    if(!tip){ closeAll(); return; }
    e.preventDefault();
    var wrap = tip.closest('.hcp-tooltip');
    if(!wrap) return;
    var open = wrap.classList.contains('is-open');
    closeAll();
    if(!open){ wrap.classList.add('is-open'); }
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){ closeAll(); }
  });
})();
