function myPostJson(url, param, onReady, onError, onAlways) {
  $.post(url, param, 'json')
    .done(function(data) {
      if (data !== null) {
        if (typeof data.error !== 'undefined') {
          alert(data.error);
        } else {
          for (var key in data)
            if(data.hasOwnProperty(key)) {
              if (key == 'message')
              	alert(data[key]);
              else {
              	if (key.substr(0, 1) == '_')
                  $('#'+key.substr(1)).val(data[key]);
              	else
                  $('#'+key).html(data[key]);
              }
            }
          if (typeof onReady === 'function')
            onReady(data);
        }
        if (typeof data.redirect != 'undefined')
          location.replace(data.redirect);
      }
    })
    .fail(function() {
      if (typeof onError === 'function')
        onError();
      else
        alert('Ошибка связи с сервером');
    })
    .always(function() {
      if (typeof onAlways === 'function')
        onAlways();
    });
}

$(function() {
	$('#getWsdl').click(function() {
		myPostJson('soapTest.php', {op:'getWsdl', url:$('#url').val(), login:$('#login').val(), pass:$('#pass').val()});
	});
});