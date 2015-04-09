$(function() {
    
  $('#login').click(function() {
    $('input').prop('disabled', 'disabled');
  	var newpass = $('#newpasswd').val().trim();
  	if (newpass.length < 6) {
  	  alert('Пароль должен быть не короче 6 символов');
  	  $('input').removeProp('disabled');
  	  return;
  	}
  	if (newpass != $('#checkpasswd').val().trim()) {
  	  alert('Введённые пароли не совпадают');
  	  $('input').removeProp('disabled');
  	  return;
  	}
  	var crypt = new JSEncrypt();
  	crypt.setPublicKey($('#key').val());
  	newpass = crypt.encrypt(newpass);
  	$.post('/newpwd.php', {op: "cp", newpass: newpass})
      .done(function(data){
        if (data !== null) {
          if (typeof data.redirect !== 'undefined') {
            location.replace(data.redirect);
            return;
          }
          if (typeof data.error !== 'undefined') {
            alert(data.error);
            return;
          }
        }
      })
      .error(function(data) {
        alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
      })
      .always(function() {
     	$('input').removeProp('disabled');
  	  });
  });

  $('input').keypress(function(event) {
    if (event.which == 0xD) {
      switch ($(this).attr('id')) {
        case 'newpasswd':
          $('#checkpasswd').focus();
          break;
        case 'checkpasswd':
        case 'login':
          $('#login').trigger('click');
          break;
      }
      event.preventDefault();
    }
  });
});
