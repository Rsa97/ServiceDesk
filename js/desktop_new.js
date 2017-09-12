var userData = null;
var userDataExpire = null;
var filterData = null;

// Кэширование в localStorage
function fromStorage(name) {
	var value = null;
	var data = null; 
	try {
		if ((time = localStorage.getItem(name+'_expireAt')) !== null && time > Date.now()/1000 && 
			(data = localStorage.getItem(name)) !== null) {
			value = JSON.parse(data);
		}
	} catch (e) {
		console.log(e);
	}
	return value;
}

function toStorage(name, value, expireTime) {
	try {
		localStorage.setItem(name, JSON.stringify(value));
		localStorage.setItem(name+'_expireAt', Date.now()/1000 + expireTime);
	} catch (e) {
		console.log(e);
	}
}

function myAlert(text) {
  $.blockUI({message: "<div style='padding: 1em'>"+text+"<br><input type='button' id='myAlertOk' value='Ок'></div>",
			 backgroundColor: '#000', 
        	 opacity:         0.2, 
        	 css: 			  { border: '3px solid #a00' } 
			 });
}

var blockNum = 0;
function block() {
	if (0 == blockNum) {
		$.blockUI({
			message: 	'<img src="img/busy.gif"> Подождите...', 
  			overlayCSS: { 
        		backgroundColor: '#000', 
        		opacity:         0.2, 
        		cursor:          'wait' 
    		} 
    	});
	}
	blockNum++;
}

function unblock() {
	blockNum--;
	if (0 == blockNum) {
      $.unblockUI();
	}
}

function getCached(name, request, onDone, onRequestDone) {
	data = fromStorage(name);
	if (null == filterData) {
  		block();
  		$.ajax(request)
  		.done(function(data) {
  			unblock();
      		if (data !== null) {
        		if ('error' == data.result) {
        			myAlert(data.error);
          			return;
          		}
          		toStorage(name, data.value, data.expireTime);
          		if (null !== onRequestDone) {
          			data = onRequestDone(data);
          		}
          		if (null !== onDone) {
          			onDone(data);
          		}
        	}
		}).fail(function() {
			unblock();
			myAlert('Ошибка связи с сервером');
    	});

  	} else {
		if (null !== onDone) {
          	onDone(data);
		}
  	}
}

function storeFilter() {
	userData.filter = {
		contract:	('contract' == $('#filterDivision :selected').data('type') ? $('#filterDivision').val() : null),
		division: 	('division' == $('#filterDivision :selected').data('type') ? $('#filterDivision').val() : null), 
		service: 	$('#filterService').val(),
  		onlyMy:		$('#chkMyTickets :checked'),
  		text: 		$('#fltByText').val().trim(),
  		from:		$('#fltByFromDate').datepicker('getDate'),
  		to:			$('#fltByToDate').datepicker('getDate')
	};
}

function resetFilter() {
  $('#filterDivision :selected').removeProp('selected');
  $('#filterService :selected').removeProp('selected');
  $('#chkMyTickets :checked').removeProp('checked');
  $('#fltByText').val('');
  $('#fltByToDate').datepicker('setDate', '0');
  $('#fltByFromDate').datepicker('setDate', '-3m');
  storeFilter();
}

function restoreFilter() {
  $('#filterDivision :selected').removeProp('selected');
  filter.fltByDiv.prop('selected', 'selected');
  $('#filterService :selected').removeProp('selected');
  filter.fltBySrv.prop('selected', 'selected');
  $('#chkMyTickets :checked').removeProp('checked');
  filter.fltOnlyMy.prop('checked', 'checked');
  $('#fltByText').val(filter.fltText);
  $('#chkMyTickets').buttonset('refresh');
  $('#fltByToDate').datepicker('setDate', filter.fltToDate);
  $('#fltByFromDate').datepicker('setDate', filter.fltFromDate);
}

function setUserData(userData) {
	if ('admin' == userData.rights) {
		$('#name').html(userData.fullName);
    	$("#user").append("&nbsp;<button id='admin'>Администрирование</button>");
		$('#admin').button();
	}
}

function buildFilter() {
	var group;
	var select = $('<select>', {class: 'ui-widget ui-corner-all ui-widget-content', id: 'filterDivision'});
	select.append($('<option>', {value: null, text: '    --- Все ---', selected: true}));
	filterData.divisions.forEach(function(contragent) {
		group = $('<optgroup>', {label: contragent.name});
		select.append(group);
		contragent.contracts.forEach(function(contract) {
			group.append($('<option>', {'data-type': 'contract', value: contract.guid, text: 'Договор '+contract.name}));
			contract.divisions.forEach(function(division) {
				group.append($('<option>', {'data-type': 'division', value: division.guid, text: '      '+division.name}));
			});
		});
	});
	$('#filterDivision').replaceWith(select);
	select = $('<select>', {class: 'ui-widget ui-corner-all ui-widget-content', id: 'filterService'});
	select.append($('<option>', {value: null, text: '    --- Все ---', selected: true}));
	filterData.services.forEach(function(service) {
		select.append($('<option>', {value: service.guid, text: service.name}));
	});
	$('#filterService').replaceWith(select);
	if (null != userData && 'undefined' !== typeof userData.filter) {
		if (null != userData.filter.contract) {
			$('#filterDivision :selected').removeProp('selected');
			$('#filterDivision [value="'+userData.filter.division+'"]').prop('selected', true);
		}
		if (null != userData.filter.division) {
			$('#filterDivision :selected').removeProp('selected');
			$('#filterDivision [value="'+userData.filter.contract+'"]').prop('selected', true);
		}
		if (null != userData.filter.service) {
			$('#filterService :selected').removeProp('selected');
			$('#filterService [value="'+userData.filter.service+'"]').prop('selected', true);
		}
	}
}

var token = fromStorage('token');
if (null == token) {
	location.replace('index_new.html');
} 

// Кнопки фильтра заявок
var filterBtn = [{
	text: 	'Принять', 
    click: 	function() {
    	$(this).dialog("close");
        setFilter();
    }
}, {
   	text: 	'Сбросить', 
    click: 	function() { 
		resetFilter();
    }
}, {
	text: 	'Отменить', 
	click: 	function() { 
		restoreFilter();
       	$(this).dialog("close");
	}
}];

/*

var cardBtnLook = [{
	text: 	'Сервисный лист',
	click: 	function() {
				var cell1 = $('tr#'+openCard+' .cell1').find('.ui-icon');
				if (cell1.hasClass('ui-icon-help') || cell1.hasClass('ui-icon-check') ||
					cell1.hasClass('ui-icon-mail-open') || cell1.hasClass('ui-icon-wrench')) {
// ToDo
					window.open('/ajax/serviceList/get/'+openCard, 'Сервисный лист');
				}
			}
}, {
	text: 	'Закрыть', 
	click: 	function() {
				$(this).dialog("close");
			}
}];

// Кнопки карточки заявки в режиме создания
var cardBtnNew = [{
	text:	'Отменить', 
	click:	function() {
				$(this).dialog("close");
			}
}, {
	text:	'Создать', 
	click:	function() {
				if ($('#division').val() == '*') {
                	myAlert('Не выбран филиал');
                    return;
				}
                if ($('#problem').val().trim() == '') {
					myAlert('Не указана проблема');
					return;
				}
                            if ($('#contact').val() == '*') {
                              myAlert('Не выбран ответственный');
                              return;
                            }
                            if ($('#service').val() == '*') {
                              myAlert('Не выбрана услуга');
                              return;
                            }
                            myPostJson('/ajax/request/new/'+$('#division').val()+'/'+$('#service').val()+'/'+$('#level').val()+'/'+$('#contact').val(),
										{equipment: $('#servNum').data('id'), problem: $('#problem').val().trim()},
							  function() {
                                $('#workflow').tabs('option', 'active', 0);
                                setFilter();
                               	$('#card').dialog("close");
                              });
                          }}
                 ];




function serviceNumSet() {
  if ($('#servNum').data('serv') != $('#servNum').val())
    myPostJson('/ajax/request/changeEq/'+openCard, {equipment: $('#servNum').data('id')}, null,
	  function() {
	    myAlert('Ошибка связи с сервером');
	  },
	  function() {
		$('#card').dialog('close');
	  });
  else
    $('#card').dialog('close');
}

function contactSet() {
  if ($('#contact').data('id') != $('#service').val())
    myPostJson('/ajax/request/contact/set/'+openCard+'/'+$('#contact').val(), null,
      function() {
        serviceNumSet();
	  },
	  function() {
	    myAlert('Ошибка связи с сервером');
	  },
	  function() {
		$('#card').dialog('close');
	  });
  else
    serviceNumSet();
}

function serviceSet() {
  if ($('#service').data('id') != $('#service').val() || $('#level').data('id') != $('#level').val()) {
  	if (0 != $('#service option:selected').data('autoonly')) {
  	  myAlert('Услуга "'+$('#service option:selected').text()+'" служебная. Измените её.');
  	  return;
  	}
    myPostJson('/ajax/request/sla/set/'+openCard+'/'+$('#service').val()+'/'+$('#level').val(), null, 
	  function() {
	    contactSet();
	  },
	  function() {
	    myAlert('Ошибка связи с сервером');
	  },
	  function() {
	    $('#card').dialog('close');
	  });
  } else
	contactSet();
}

var cardBtnChange = [{text: 'Отменить',
					  click: function() {
					    $(this).dialog("close");
					  }},
					 {text: 'Изменить', 
                      click: function() {
                      	serviceSet();
                     }}
                  ];

var userSetupBtn = [{text: 'Отменить',
				     click: function() {
					   $(this).dialog("close");
				     }},
				     {text: 'Изменить', 
                      click: function() {
                      	var cellPhone = $('#cellPhone').val().trim();
                      	if ('' != cellPhone && !cellPhone.match(/^\+?[78]9\d{9}$/)) {
                      		myAlert("Неверный номер сотового телефона");
                      		return;
                      	}
                      	cellPhone = cellPhone.substr(-10);
                      	var jid = $('#jabberUID').val().trim();
                      	if ('' != jid && !jid.match(/^\S+@\S+/)) {
                      		myAlert("Неверный адрес Jabber");
                      		return;
                      	} 
                      	var data = '';
                  	    $('#sendMethods tbody tr').each(function() {
                  	    	var evt = $(this).data('id');
                  	    	$(this).find('td').each(function() {
                  	    		if ($(this).children('input').prop('checked'))
                  	    			data += evt+','+$(this).data('id')+'|';
                  	    	});
                  	    });
                  	   	myPostJson('/ajax/user/messageConfig/set/', {cellPhone:cellPhone, jid:jid, data:data},
                  	   				null, null,
                  	   				function() {
                  	   					$('#userSetup').dialog('close');
                  	   				}
                  	   			  );
                     }}
                   ];

// Кнопки карточки заявки в режиме обмена с базой
var cardBtnWait = [{text: 'Идёт запрос'}];

// Кнопки списка оборудования
var selectEqBtn = [{text: 'Отменить', 
                   click: function() { 
                            $(this).dialog("close");
                          }}
                 ];

// Кнопки списка партнёров
var selectPartnerBtn = [{text: 'Отменить', 
        	       	     click: function() { 
                        	       $(this).dialog("close");
	                             }}
    	               ];
                 
// Кнопки добавления задач в плановые
var addProblemBtn = [{text: 'Отменить', 
                      click: function() { 
                            $(this).dialog("close");
                          }},
	                 {text: 'Сохранить',
	                  click: function() {
	                  	myPostJson('/ajax/problem/set/'+$('#apContract').val()+'/'+$('#apDivision').val(),
	                  				{problem: $('#apProblem').val().trim()}, null, null, 
	                  				function() { 
									  $('#addProblem').dialog('close');
									});
	                  }} 
                    ];
       

// Кнопки решения
var solutionBtn = [{text: 'Отменить', 
                   click: function() { 
                            $(this).dialog("close");
                          }},
                   {text: 'Принять', 
                   click: function() {
                   			var Problem = $('#solProblem').val().trim();
                   			var Solution = $('#solSolution').val().trim();
                   			var Recomend = $('#solRecomendation').val().trim();
                   			if ('' == Recomend)
                   				Recomend = 'Без рекомендаций';
                   			if (Problem.length < 10 || Solution.length < 10) {
                   				myAlert('Минимальная длина текста - 10 символов!');
                   				return;
                   			}
                   			if (Problem == Solution || Problem == Recomend || Solution == Recomend) {
                   				myAlert('Тексты в полях не должны совпадать!');
                   				return;
                   			}
    						myPostJson('/ajax/request/Repaired/'+$('#solution').data('id'),
    							{solProblem: Problem, sol: Solution, solRecomend: Recomend},
               					null,
               					null,
               					function() {
									$('#solution').dialog("close");
                 					setFilter();
               					});
               				}}
                 ];

function checkChanges() {
	if ('new' == cardMode)
		return;
	if ($('#service').data('id') != $('#service').val() ||
		$('#contact').data('id') != $('#contact').val() || 
		$('#servNum').data('serv') != $('#servNum').val() || 
		$('#level').data('id') != $('#level').val()) 
		$('#card').dialog('option', 'buttons', cardBtnChange);
	else
		$('#card').dialog('option', 'buttons', cardBtnLook);
}

function myPostJson(url, param, onReady, onError, onAlways, nonStandard) {
  $.blockUI({message: '<img src="img/busy.gif"> Подождите...', 
  			 overlayCSS:  { 
        	  backgroundColor: '#000', 
        	  opacity:         0.2, 
        	  cursor:          'wait' 
    		 } 
    	});
  $.post(url, param, 'json')
    .done(function(data) {
      $.unblockUI();
      if (data !== null) {
        if (typeof data.error !== 'undefined') {
          myAlert(data.error);
	      if (typeof onError === 'function')
    	   	onError();
        } else {
          if (typeof nonStandard === 'function')
          	nonStandard(data);
          else
            for (var key in data)
              if(data.hasOwnProperty(key)) {
                if (key.substr(0, 1) == '_')
                  $('#'+key.substr(1)).val(data[key]);
                else if (key.substr(0, 1) == '!') {
              	  if (data[key] == 1)
              	    $('#'+key.substr(1)).show();
              	  else
              	    $('#'+key.substr(1)).hide();
              	  $('#'+key.substr(1)).val(data[key]);
                } else
                  $('#'+key).html(data[key]);
              }
          if (typeof onReady === 'function')
            onReady(data);
        }
        if (typeof data.redirect != 'undefined')
          location.replace(data.redirect);
      }
    })
    .fail(function() {
      $.unblockUI();
      if (typeof onError === 'function')
        onError();
      else
        myAlert('Ошибка связи с сервером');
    })
    .always(function() {
      if (typeof onAlways === 'function')
        onAlways();
    });
}

// Установка фильтра на сервере
function setFilter() {
  myPostJson('/ajax/filter/set', {byDiv: $('#selectDivision :selected').val(),
                                  bySrv: $('#filterService :selected').val(),
                                  byText: $('#fltByText').val(),
                                  byFrom: $('#fltFromDateSQL').val(),
                                  byTo: $('#fltToDateSQL').val(),
                                  onlyMy: $('#chkMyTickets :checked').val()}, 
             null, null, 
             function() { 
               $('.list tr:nth-child(2n+1)').addClass('odd');
               $('.list td:nth-child(1), .list th:nth-child(1)').addClass('cell1');
               $('.list td:nth-child(2)').addClass('cell2');
               $('.list td:nth-child(9)').addClass('cell9');
             });
}

function servNumLookup() {
	myPostJson('/ajax/dir/equipment/'+$('#division').val(), {servNum: $('#selectServNum').val().trim()}, 
	function() {
	  $('#selectEqList ul ul').hide();
	  $('#selectEqList ul ul.single').show();
	},
	function() {
	  myAlert('Ошибка связи с сервером');
	  $('#selectEq').dialog('close');
	});       
  timeoutSet = 0;
}

*/
  
$(function() {
// Обход ошибки с селектом в модальных окнах
	$.ui.dialog.prototype._allowInteraction = function(e) {
   		return !!$(e.target).closest('.ui-dialog, .ui-datepicker, .select2-dropdown').length;
 	};
 	
// Стартовая инициализация интерфейса
	$('#chkMyTickets').buttonset();
	$('#workflow').tabs({
		active: 0,
		activate: function(event, ui) {
			console.log(event);
			console.log(ui);
		}
	});
	
	$('#cardTabs').tabs({active: 0});

	$('button').button();
  	$('button').each(function() {
    	if ($(this).data('icon') != '')
      		$(this).button('option', 'icons', {primary: $(this).data('icon')});
  		});
  	$('#refresh').button('option', 'text', false);  

  	$.datepicker.setDefaults({
		monthNames: 		['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 
							 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
		monthNamesShort: 	['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
		dayNamesMin: 		['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
		firstDay: 			1
  	});

// Инициализируем диалоги
  	$('#filter').dialog({
  		autoOpen: 		false, 
        position: 		{my: 'right top', at: 'right bottom', of: '#showFilter'}, 
        resizable: 		false,
        dialogClass: 	'no-close',
        draggable: 		false,
        title: 			'Отбор заявок',
        modal: 			true,
        buttons: 		filterBtn
	});

  	$('#fltByFromDate').datepicker({
  		changeMonth: 	true,
  		numberOfMonths: 1,
  		dateFormat: 	'd MM yy',
  		altField: 		'#fltFromDateSQL',
  		altFormat: 		'yy-mm-dd',
  		constrainInput: false,
  		onClose: 		function(selectedDate) {
  							$('#fltByToDate').datepicker('option', 'minDate', selectedDate);
  						}
  	});

  	$('#fltByToDate').datepicker({
  		changeMonth: 	true,
  		numberOfMonths: 1,
  		dateFormat: 	'd MM yy',
  		altField: 		'#fltToDateSQL',
  		altFormat: 		'yy-mm-dd',
  		constrainInput: false,
  		onClose: 		function(selectedDate) {
  							$('#fltByFromDate').datepicker('option', 'maxDate', selectedDate);
  						}
  	});

/*  	getCached('filterData', {
  					url:		'/api/v2/filter/token/'+token,
  					method:		'GET',
					cache:		false,
  					dataType: 	'json',
  					statusCode: { 
  									401: function() { 
  										location.replace('index_new.html'); 
  									}
  								}
  				}, 
  				function(data) {
  					filterData = {divisions: data.division_filter, services: data.service_filter};
  					return filterData;
  				},
  				function(data) {
  					buildFilter();
  				}
			); */
	getCached('userData', {
  					url:		'/api/v2/me/token/'+token,
  					method:		'GET',
  					cache:		false,
  					dataType: 	'json',
  					statusCode: { 
  									401: function() { 
  										location.replace('index_new.html'); 
  									}
  								}
  				},
  				function(data) {
  					userData = data.info;
  					return userData;
  				},
  				function(data) {
					setUserData(data);
  				}
  		);
	userData = fromStorage('userData');
	console.log('userData', userData);
	if (null == userData) {
		block();
  		$.ajax({
  			url:		'/api/v2/me/token/'+token,
  			method:		'GET',
  			cache:		false,
  			dataType: 	'json',
  			statusCode: { 
  							401: function() { 
  								location.replace('index_new.html'); 
  							}
  						}
  		}).done(function(data) {
  			unblock();
      		if (data !== null) {
        		if ('error' == data.result) {
        			unblock();
          			myAlert(data.error);
          			return;
          		}
          		userData = data.info;
          		toStorage('userData', userData, data.expireTime);
				console.log('userData', userData);
        	}
		}).fail(function() {
			unblock();
			myAlert('Ошибка связи с сервером');
    	});
    } else {
    	setUserData(userData);
    }
 	
// Описываем нажатия/выборы 
  	$('#showFilter').click(function() {
    	$(this).blur();
    	if ($('#filter').dialog('isOpen')) {
      		restoreFilter();
      		$('#filter').dialog('close');
    	} else {
      		$('#filter').dialog('open');
      		storeFilter();
    	}
  	});
	

  
/*
  	$('#card').dialog({
  		autoOpen: 		false,
        resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
		draggable: 		false,
        buttons: 		cardBtnNew,
// ToDo
        close: 			function() {
                     		$(this).find('select').each(function() {
                     			$(this).select2("close");
                     		});
                     		setFilter();
                     	}
	});
  	$('#selectEq').dialog({
  		autoOpen: 		false,
    	title: 			'Выберите оборудование',
		resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		selectEqBtn
    });
	$('#selectPartner').dialog({
		autoOpen: 		false,
    	title: 			'Выберите партнёра',
        resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		selectPartnerBtn
	});
  	$('#solution').dialog({
  		autoOpen: 		false,
        resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		solutionBtn
	});
  	$('#addProblem').dialog({
  		autoOpen: 		false,
		title: 			'Добавить задание в плановый выезд',
        resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		addProblemBtn
    });
  	$('#userSetup').dialog({
  		autoOpen:		false,
		title: 			'Способы отправки сообщений',
        resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		userSetupBtn
	});
  
 	
	$('#logout').click(function() {
		var login = localStorage.getItem('loginName');
		localStorage.clear();
		if (null !== login) {
			localStorage.setItem('loginName', login);
		}
		location.replace('index_new.html');
  	});


  myPostJson('/ajax/filter/build', {}, function() {
    $('.oper button').each(function() {
      $(this).button({icons:{primary:$(this).data('icon')}});
    });
    setFilter();
  }, null, null);

  $('#refresh').click(function() {
    setFilter();
  });
  
  $('#user').on('click', '#admin', function() {
  	location.replace('newadmin.html');
  });

  $('#lookServNum').click(function() {
  	if ($('#division').val() == '*')
  	  return;
    $('#selectEq select').html('');
    $('#selectEq').dialog('open');
    //$('#selectServNum').val($('#servNum').val());
    $('#selectServNum').val('');
	myPostJson('/ajax/dir/equipment/'+$('#division').val(), {servNum: $('#selectServNum').val().trim()},
	  function() {
		$('#selectEqList ul ul').hide();
		$('#selectEqList ul ul.single').show();
	  },
	  function() {
		myAlert('Ошибка связи с сервером');
		$('#selectEq').dialog('close');
	  });       
  });

  $('#lookPartner').click(function() {
    $('#selectPartnerList').html('');
    $('#selectPartner').dialog('open');
	myPostJson('/ajax/dir/partners/'+openCard, null, null,
	  function() {
		myAlert('Ошибка связи с сервером');
		$('#selectPartner').dialog('close');
	  });       
  });
  
  $('#selectPartnerList').on('click', 'li', function() {
	  $('#selectPartner').dialog('close');
	  if ('0' == $(this).data('id'))location.replace('index_new.html');
	  	$('#partner').val('');
	  else
	  	$('#partner').val($(this).text());
	  myPostJson('/ajax/request/partner/set/'+openCard+'/'+$(this).data('id'), null, null, 
	  function() {
		myAlert('Ошибка связи с сервером');
		$('#card').dialog('close');
	  });
  });

  $('#workflow').on('click', '.btnNew', function() {
	$('#card').dialog('open');
	$('#card').dialog('option', 'title', 'Новая заявка');
    $('#card .ro').removeProp('readonly');
	$('#cardTabs').tabs('option', 'active', 0);
  	$('#card').dialog('option', 'buttons', cardBtnNew);
	$('#servNum').val('').data('serv', null).data('id', null);
	$('#card input').val('');
	$('#card select').html('').select2();
	$('#service').data('id', null);
	$('#contact').data('id', null);
	$('#card textarea').val('');
	$('#card .active').removeClass('active');
	$('#lookServNum').hide();
	$('#lookPartner').hide();
	myPostJson('/ajax/dir/contragents', null,
  	  function() {
		$('#contragent').select2();
		if ($('#contragent').val() != 0)
		  $('#contragent').trigger('change');
  	  },
  	  function() {
		myAlert('Ошибка связи с сервером');
		$('#card').dialog('close');
 	  });
    cardMode = 'new';
  });

  $('#card').on('change', '#contragent', function() {
  	if (cardMode != 'new')
  		return;
	if ($('#contragent').val() == '*') {
      $('#card .ro').removeProp('readonly');
	  $('#servNum').val('').data('id', null);
	  $('#card input').val('');
	  $('#contract').html('').select2();
	  $('#division').html('').select2();
	  $('#service').html('').select2();
	  $('#level').html('').select2();
	  $('#contact').html('').select2();
	  $('#card textarea').val('');
	  $('#card .active').removeClass('active');
	  $('#lookServNum').hide();
  	  return;
    }
	myPostJson('/ajax/dir/contracts/'+$('#contragent').val(), null,
	  function() {
		$('#contract').select2();
		if ($('#contract').val() != 0)
	  		$('#contract').trigger('change');
	  },
	  function() {
		myAlert('Ошибка связи с сервером');
		$('#card').dialog('close');
	  });
  });
  
  $('#card').on('change', '#contract', function() {
  	if (cardMode != 'new')
  		return;
	if ($('#contract').val() == '*') {
      $('#card .ro').removeProp('readonly');
	  $('#servNum').val('').data('id', null);
	  $('#card input').val('');
	  $('#division').html('').select2();
	  $('#service').html('').select2();
	  $('#level').html('').select2();
	  $('#contact').html('').select2();
	  $('#card textarea').val('');
	  $('#card .active').removeClass('active');
	  $('#lookServNum').hide();
  	  return;
    }
	myPostJson('/ajax/dir/divisions/'+$('#contract').val(), null,
	  function() {
		$('#division').select2();
		if ($('#division').val() != 0)
	  		$('#division').trigger('change');
	  },
	  function() {
		myAlert('Ошибка связи с сервером');
		$('#card').dialog('close');
	  });
  });
  
  $('#card').on('change', '#division', function() {
  	if (cardMode != 'new')
  		return;
	if ($('#division').val() == '*') {
      $('#card .ro').removeProp('readonly');
	  $('#card input').val('');
	  $('#card textarea').val('');
	  $('#card .active').removeClass('active');
	  $('#lookServNum').hide();
	  $('#lookPartner').hide();
    }
    $('#service').html('').select2();
  	$('#servNum').val('').data('id', null);
	$('#contact').html('').select2();
    $('#level').html('');
  	$('#SN').val('');
  	$('#eqType').val('');
  	$('#manufacturer').val('');
  	$('#model').val('');
   	myPostJson('ajax/time', null,
   	  function(data) {
   	  	if (typeof data !== 'undefined') {
   	  	  if (typeof data.time !== 'undefined')
   	        $('#createdAt').val(data.time);
   	  	  if (typeof data.timeEn !== 'undefined')
   	        $('#createTime').val(data.timeEn);
	      $('#lookServNum').show();
	      $('#division').parent().prev().removeClass('active');
	      $('#problem').parent().prev().addClass('active');
	      $('#level').parent().prev().addClass('active');
	      $('#contact').parent().prev().addClass('active');
          if ($('#division').val() != '*')
	        myPostJson('/ajax/dir/services/'+$('#division').val(), null,
	          function() {
	            $('#service').parent().prev().addClass('active');
  	            $('#service').trigger('change');
         	    myPostJson('/ajax/dir/contacts/'+$('#division').val(), null,
          	      function(data) {
          	        $('#contact').trigger('change');
          	  	  },
	  			  function() {
				    myAlert('Ошибка связи с сервером');
				    $('#card').dialog('close');
	  			  });
	          },
              function() {
	            myAlert('Ошибка связи с сервером');
	            $('#card').dialog('close');
              });
   	    }
   	  },
	  function() {
		myAlert('Ошибка связи с сервером');
		$('#card').dialog('close');
	  });
  });
  
  $('#card').on('change', '#service', function() {
  	if ($(this).val() == '*') {
  		$('#level').html('').select2();
  		return;
  	}
	myPostJson('/ajax/dir/slas/'+$('#division').val()+'/'+$('#service').val(), null, 
	  function() {
		$('#level').trigger('change');
	  },
	  function() {
		myAlert('Ошибка связи с сервером');
		$('#card').dialog('close');
	  });
  });
  
  $('#card').on('change', '#contact', function() {
  	console.log($(this));
  	if ($(this).val() == '*') {
  	  $('#email').val('');
  	  $('#phone').val('');
  	  $('#address').val('');
  	} else {
  	  var sel = $(this).find('option:selected');
  	  $('#email').val(sel.data('email'));
  	  $('#phone').val(sel.data('phone'));
  	  $('#address').val(sel.data('address'));
  	  checkChanges();
  	}
  });
  
  $('#card').on('change', '#level', function() {
    var cardNum = ('new' == cardMode ? null : {id: openCard});
	myPostJson('/ajax/request/calcTime/'+$('#division').val()+'/'+$('#service').val()+'/'+$('#level').val(), cardNum, 
	  function() {
	  	if ('new' != cardMode && $('#service').val() != '*')
       		checkChanges();
	  },
	  function() {
		myAlert('Ошибка связи с сервером');  
		$('#card').dialog('close');
	  });
  });

  $('#selectServNum').keyup(function() {
  	if (timeoutSet == 1)
  		clearTimeout(servNumLookupInterval);
    servNumLookupInterval = setTimeout(servNumLookup, 1000);
    timeoutSet = 1;      
  });

  $('#selectServNum').change(function() {
  	if (timeoutSet == 1)
  		clearTimeout(servNumLookupInterval);
    servNumLookupInterval = setTimeout(servNumLookup, 1000);
    timeoutSet = 1;      
  });

  $('#workflow').on('click', '.btnAccept', function() {
    var cmd = $(this).data('cmd');
    var list = '';
    var errList = '';
    var errs = 0;
    $('.tab'+$('#workflow').tabs('option', 'active')+' :checked').each(function() {
      if (0 != $(this).parents('tr').data('autoonly')) {
        errs++;
      	errList += ('' == errList ? '' : ', ')+$(this).parents('tr').attr('id');
      } else
        list += $(this).parents('tr').attr('id')+',';
    });
    if (errs > 0)
      myAlert('Невозможно принять заявк'+(errs > 1 ? 'и' : 'у')+' '+errList+'. '+(errs > 1 ? 'Указанные услуги являются служебными.' : 'Указанная услуга является служебной.'));
    if (list == '')
      return;
    myPostJson('/ajax/request/'+cmd+'/'+list, null, null, null,
               function() {
                 setFilter();
               });
  });

  $('#workflow').on('click', '.btnFixed, .btnClose, .btnDoNow', function() {
    var cmd = $(this).data('cmd');
    var list = '';
    $('.tab'+$('#workflow').tabs('option', 'active')+' :checked').each(function() {
      list += $(this).parents('tr').attr('id')+',';
    });
    if (list == '')
      return;
    myPostJson('/ajax/request/'+cmd+'/'+list, null, null, null,
               function() {
                 setFilter();
               });
  });
  
  $('#workflow').on('click', '.btnRepaired', function() {
    var rows = $('.tab'+$('#workflow').tabs('option', 'active')+' :checked');
    if (rows.length > 1)
      myAlert('Выберите только одну заявку');
    if (rows.length != 1)
      return;
  	var id = rows.first().parents('tr').attr('id');
  	$('#solution').data('id', id);
    $('#solution').dialog('open');
	$('#solution').dialog('option', 'title', 'Решение заявки '+('0000000'+id).substr(-7));
    myPostJson('/ajax/request/getSolution/'+id, null, null,
	  function() {
		myAlert('Ошибка связи с сервером');
		$('#solution').dialog('close');
	  });       
	
  });  

  $('#workflow').on('click', '.btnCancel, .btnWait, .btnUnClose, .btnUnCancel', function() {
    var cmd = $(this).data('cmd');
    var rows = $('.tab'+$('#workflow').tabs('option', 'active')+' :checked');
    if (rows.length > 1)
      myAlert('Выберите только одну заявку');
    if (rows.length != 1)
      return;
    var list = rows.first().parents('tr').attr('id');
    var cause;
    if ((cause = prompt('Причина:', '')) == null || cause == '')
	    return;
    myPostJson('/ajax/request/'+cmd+'/'+list, {cause: cause}, null, null,
               function() {
                 setFilter();
               });
  });

  $('.list').on('click', 'td', function() {
    if ($(this).hasClass('cell1'))
      return;
    if ($(this).parents('table').hasClass('planned'))
      return;
    openCard = ($(this).parents('tr').attr('id'));
    if (openCard != 'n0') {
      $('#card').dialog('option', 'title', 'Заявка '+$(this).siblings('.cell2').text());
      $('#cardTabs').tabs('option', 'active', 0);
      $('#card .ro').prop('readonly', 'readonly');
      $('#card input, #card select, #card textarea').val('');
      $('#card input, #card select, #card textarea').each(function() {
        $(this).parent().prev().removeClass('active');
      });
      $('#card select').html('').select2({width: '100%'});
      $('#card').dialog('option', 'buttons', cardBtnLook);
      $('#card').dialog('open');
      cardMode = 'look';
      myPostJson('/ajax/request/view/'+openCard, null,
        function(data) {
          $('#cardDocTbl tr:nth-child(2n+1)').addClass('odd');
          $('#cardDocTbl td:nth-child(1)').addClass('cell1');
          $('#cardDocTbl td:nth-child(4)').addClass('cell4');
          $('#card select').select2();
          $('#service').data('id', $('#service').val());
		  $('#contact').data('id', $('#contact').val());
		  $('#servNum').data('serv', $('#servNum').val()).data('id', data.equipment_guid);
		  $('#level').data('id', $('#level').val());
        },
        function() {
          myAlert('Ошибка связи с сервером');
          $('#card').dialog('close');
        },
        function() {
	      $('#cardTabs').tabs('option', 'disabled', []);
        });       
    }
  });

  $('#addComm').click(function() {
    if (cardMode == 'look' && $('#addComment').val() != '') {
      myPostJson('/ajax/request/addComment/'+openCard, {comment: $('#addComment').val()},
                 function() {
                     $('#addComment').val('');
		             myPostJson('/ajax/request/view/'+openCard, null,
                         function() {
                         	$('#cardDocTbl tr:nth-child(2n+1)').addClass('odd');
                         	$('#cardDocTbl td:nth-child(1)').addClass('cell1');
                         	$('#cardDocTbl td:nth-child(3)').addClass('cell3');
                       	 });
                 });
    }
  });

  $('#addFile').click(function() {
    if (cardMode == 'look' && typeof ($('#file')[0].files[0]) !== 'undefined') {
      var fd = new FormData();
      fd.append('file', $('#file')[0].files[0]);
      $('#card').dialog('option', 'buttons', cardBtnWait);
      $.ajax({type: 'POST',
              url: '/ajax/request/addFile/'+openCard,
              data: fd,
              processData: false,
              contentType: false,
              dataType: 'json'})
        .done(function(data) {
          if (data === null || typeof data.error !== 'undefined') {
            myAlert((typeof data.error !== 'undefined') ? data.error : 'Ошибка передачи файла');
          } else {
            $('#file').val('');
            myPostJson('/ajax/request/view/'+openCard, null,
                       function() {
                         $('#cardDocTbl tr:nth-child(2n+1)').addClass('odd');
                         $('#cardDocTbl td:nth-child(1)').addClass('cell1');
                         $('#cardDocTbl td:nth-child(3)').addClass('cell3');
                       });
          }
        })
        .fail(function() {
            myAlert('Ошибка передачи файла');
        });
    }
  });

	$('body').on('mouseleave', 'button:focus', function() {
    $(this).blur();
  });
  
  $('body').on('change', '.checkAll', function() {  
   	if ($(this).prop('checked'))
      $(this).parents('table').first().find('.checkOne').not(':disabled').prop('checked', 'checked');
    else
      $(this).parents('table').first().find('.checkOne').not(':disabled').removeProp('checked');
  });	
	
  $('#selectEqList').on('click', '.open>ul>li', function (evt) {
	evt.stopPropagation();
	$('#selectEq').dialog('close');
	$('#servNum').data('id', $(this).data('id'));
	$('#servNum').val($(this).data('servnum'));
	$('#SN').val($(this).data('sn'));
	$('#eqType').val($(this).data('eqtype'));
	$('#manufacturer').val($(this).data('mfg'));
	$('#model').val($(this).data('model'));
	checkChanges(); 
  });
	
  $('#selectEqList').on('click', '.collapsed', function () {
	$(this).children('span').removeClass('ui-icon-folder-collapsed').addClass('ui-icon-folder-open');
	$(this).children('ul').show();
	$(this).removeClass('collapsed').addClass('open');
  });

  $('#selectEqList').on('click', '.open', function () {
	$(this).children('span').removeClass('ui-icon-folder-open').addClass('ui-icon-folder-collapsed');
	$(this).children('ul').hide();
	$(this).removeClass('open').addClass('collapsed');
  });
	
  $('#workflow').on('click', '.btnAddProblem', function() {
	$('#addProblem').dialog('open');
	myPostJson('/ajax/dir/contragents', null, null, null, null,
	  function(data) {
	  	if (typeof data.contragent !== 'undefined')
	  	  $('#apContragent').html(data.contragent).trigger('change');
	  });
  });

  $('#apContragent').change(function() {
  	if ('*' == $(this).val()) {
  	  $('#apContract').html('');
  	  $('#apDivision').html('');
  	  $('#apProblem').val('');
  	} else
	  myPostJson('/ajax/dir/contracts/'+$('#apContragent').val(), null, null, null, null, 
	    function(data) {
	  	  if (typeof data.contract !== 'undefined')
		    $('#apContract').html(data.contract).trigger('change');
	    });
  });

  $('#apContract').change(function() {
  	if ('*' == $(this).val()) {
  	  $('#apDivision').html('');
  	  $('#apProblem').val('');
  	} else
	  myPostJson('/ajax/dir/divisions/'+$('#apContract').val(), null, null, null, null, 
	    function(data) {
	  	  if (typeof data.division !== 'undefined') {
		    $('#apDivision').html(data.division);
		    $('#apDivision option[value="*"]').remove();
		    $('#apDivision').prepend('<option value="*" selected>Все').trigger('change');
		  }
	    });
  });

  $('#apDivision').change(function() {
  	if ('*' == $(this).val())
  	  $('#apProblem').val('');
  	else
	  myPostJson('/ajax/problem/get/'+$('#apDivision').val());
  });
  
  $(document).on('click', '#myAlertOk', function() { 
    $.unblockUI(); 
    return false; 
  });
  
  $('#setup').click(function() {
  	$('#userSetup').dialog('open');
  	myPostJson('/ajax/user/messageConfig/get/', null, null,
  	  function() {
  		$('#userSetup').dialog('close');
  	  });
  });
*/	
});
