// После загрузки страницы запрашиваем крипто-ключ
$(function() {
  $('#newpass').hide();
  
  $.post('/ajax/login.php', {}, 'json')
    .done(function(data) {
      if (data === null) {
        alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
        return;
      }
      if (typeof data.key !== 'undefined') {
        $('#key').val(data.key);
        $('input').removeProp('disabled');
      }
    })
    .always(function() {
      if ($('#key').val() == '')
        alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
	  try {
		if ((l = localStorage.getItem('loginName')) !== null) {
  		  $("#username").val(l);
  		  $("#password").focus();
		} else {
  		  $("#username").val('').focus();
  		}
  	  } catch (e) {
  	  	$("#username").val('').focus();
  	  }
    });
    
  $('#changePasswd').change(function(){
  	if ($(this).prop('checked'))
  	  $('#newpass').show();
  	else
  	  $('#newpass').hide();
  });

  $('#login').click(function() {
    $('input').prop('disabled', 'disabled');
	try {
	  localStorage.setItem('loginName', $("#username").val());
	} catch (e) {}
	var newpass = '';
	var change = 0;
  	var crypt = new JSEncrypt();
  	crypt.setPublicKey($('#key').val());
  	if ($('#changePasswd').prop('checked')) {
  	  newpass = $('#newpasswd').val().trim();
  	  if (newpass.length < 6) {
  		alert('Новый пароль должен быть не короче 6 символов');
  		$('input').removeProp('disabled');
  		return;
  	  }
  	  if (newpass != $('#checkpasswd').val().trim()) {
  		alert('Введённые новые пароли не совпадают');
  		$('input').removeProp('disabled');
  		return;
  	  }
  	  change = 1;
  	  newpass = crypt.encrypt(newpass);
  	}
  	var encrypted = crypt.encrypt($('#password').val());
  	$.post('/ajax/login.php', {Op: "in", user: $('#username').val(), pass: encrypted, change: change, newpass: newpass})
      .done(function(data){
        if (data !== null) {
          if (typeof data.redirect !== 'undefined') {
            location.replace(data.redirect);
            return;
          }
          if (typeof data.error !== 'undefined') {
            alert(data.error);
            $("#password").val('');
            return;
          }
        }
        alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
      })
      .error(function(data) {
        alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
      })
      .always(function(data) {
        $('input').removeProp('disabled');
        $("#password").focus();
      });
  });

  $('input').keypress(function(event) {
    if (event.which == 0xD) {
      switch ($(this).attr('id')) {
        case 'username':
          $('#password').focus();
          break;
        case 'password':
        case 'login':
          $('#login').trigger('click');
          break;
      }
      event.preventDefault();
    }
  });
});

