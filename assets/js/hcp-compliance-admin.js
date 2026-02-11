jQuery(function($){
  var frame;
  function updatePreview(id, url, title){
    $('#hcp_file_id').val(id || '');
    if(id && url){
      $('#hcp-file-preview').html('<a href="'+url+'" target="_blank" rel="noopener noreferrer">'+(title||url)+'</a>');
    }else{
      $('#hcp-file-preview').html('<em>No file selected yet.</em>');
    }
  }
  $('#hcp-file-select').on('click', function(e){
    e.preventDefault();
    if(frame){ frame.open(); return; }
    frame = wp.media({
      title: 'Select or upload a file',
      button: { text: 'Use this file' },
      multiple: false
    });
    frame.on('select', function(){
      var attachment = frame.state().get('selection').first().toJSON();
      updatePreview(attachment.id, attachment.url, attachment.title);
    });
    frame.open();
  });
  $('#hcp-file-clear').on('click', function(e){
    e.preventDefault();
    updatePreview('', '', '');
  });
});