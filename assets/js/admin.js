jQuery(function($){
  function parseIds(val){
    val = (val||'').toString().trim();
    if(!val) return [];
    return val.split(',').map(v=>parseInt(v,10)).filter(n=>!isNaN(n) && n>0);
  }
  function setIds($input, ids){ $input.val(ids.join(',')); }

  $('.hcp-add-files').on('click', function(e){
    e.preventDefault();
    var $wrap = $(this).closest('.hcp-attachments');
    var $input = $wrap.find('input.hcp-attachment-ids');
    var $list  = $wrap.find('ul.hcp-attachment-list');
    var frame = wp.media({
      title: 'Select attachments (PDF or images)',
      multiple: true,
      library: { type: ['image','application/pdf'] }
    });
    frame.on('select', function(){
      var sel = frame.state().get('selection').toJSON();
      var ids = parseIds($input.val());
      sel.forEach(function(item){
        if(ids.indexOf(item.id) === -1){ ids.push(item.id); }
      });
      setIds($input, ids);
      renderList();
    });
    function renderList(){
      $list.empty();
      var ids = parseIds($input.val());
      if(!ids.length){ $list.append('<li><em>No files selected.</em></li>'); return; }
      ids.forEach(function(id){
        $list.append('<li data-id="'+id+'">#'+id+' <a href="#" class="hcp-remove-file">Remove</a></li>');
      });
    }
    renderList();
    frame.open();
  });

  $(document).on('click', '.hcp-remove-file', function(e){
    e.preventDefault();
    var $li = $(this).closest('li');
    var id = parseInt($li.data('id'),10);
    var $wrap = $(this).closest('.hcp-attachments');
    var $input = $wrap.find('input.hcp-attachment-ids');
    var ids = parseIds($input.val()).filter(v=>v!==id);
    setIds($input, ids);
    $li.remove();
  });

  // ===== Logo picker (Settings) =====
  var logoFrame;
  $(document).on('click', '.hcp-select-logo', function(e){
    e.preventDefault();
    var $row = $(this).closest('.hcp-meta-row');
    var $id  = $row.find('input.hcp-logo-id');
    var $prev = $row.find('.hcp-logo-preview');

    if (logoFrame) {
      logoFrame.open();
      return;
    }

    logoFrame = wp.media({
      title: 'Select Logo',
      button: { text: 'Use this logo' },
      multiple: false,
      library: { type: 'image' }
    });

    logoFrame.on('select', function(){
      var item = logoFrame.state().get('selection').first().toJSON();
      if (!item || !item.id) { return; }
      $id.val(item.id);
      if (item.sizes && item.sizes.medium) {
        $prev.replaceWith('<img class="hcp-logo-preview" src="'+item.sizes.medium.url+'" alt="" style="max-height:64px;max-width:220px;"/>');
      } else {
        $prev.replaceWith('<img class="hcp-logo-preview" src="'+item.url+'" alt="" style="max-height:64px;max-width:220px;"/>');
      }
    });
    logoFrame.open();
  });

  $(document).on('click', '.hcp-remove-logo', function(e){
    e.preventDefault();
    var $row = $(this).closest('.hcp-meta-row');
    $row.find('input.hcp-logo-id').val('0');
    $row.find('.hcp-logo-preview').replaceWith('<div class="hcp-logo-preview" style="opacity:.7">No logo selected.</div>');
  });
});
