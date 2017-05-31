var fltByDiv, fltBySrv, fltOnlyMy;
var cardMode, openCard, servNumStore;
var servNumLookupTimeout, timeoutSet = 0;

// Кнопки карточки заявки в режиме просмотра
var cardBtnLook = [ {text: 'Сервисный лист',
					 click: function() {
					 			var cell1 = $('tr#'+openCard+' .cell1').find('.ui-icon');
					 			if (cell1.hasClass('ui-icon-help') || cell1.hasClass('ui-icon-check'))
									window.open('/ajax/serviceList/get/'+openCard, 'Сервисный лист');
					 		}},
					{text: 'Закрыть', 
                    click: function() { 
                             $(this).dialog("close");
                           }}
                  ];

function serviceNumSet() {
  if ($('#servNum').data('serv') != $('#servNum').val())
    myPostJson('/ajax/request/changeEq/'+openCard, {equipment: $('#servNum').data('id')}, null,
	  function() {
	    alert('Ошибка связи с сервером');
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
	    alert('Ошибка связи с сервером');
	  },
	  function() {
		$('#card').dialog('close');
	  });
  else
    serviceNumSet();
}

function serviceSet() {
  if ($('#service').data('id') != $('#service').val() ||
    $('#level').data('id') != $('#level').val())
  myPostJson('/ajax/request/sla/set/'+openCard+'/'+$('#service').val()+'/'+$('#level').val(), null, 
	function() {
	  contactSet();
	},
	function() {
	  alert('Ошибка связи с сервером');
	},
	function() {
	  $('#card').dialog('close');
	});
  else
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

// Кнопки карточки заявки в режиме обмена с базой
var cardBtnWait = [{text: 'Идёт запрос'}];

// Кнопки карточки заявки в режиме создания
var cardBtnNew = [{text: 'Отменить', 
                   click: function() { 
                            $(this).dialog("close");
                          }},
                   {text: 'Создать', 
                   click: function() {
                            if ($('#division').val() == '*') {
                              alert('Не выбран филиал');
                              return;
                            }
                            if ($('#problem').val().trim() == '') {
                              alert('Не указана проблема');
                              return;
                            }
                            if ($('#contact').val() == '*') {
                              alert('Не выбран ответственный');
                              return;
                            }
                            if ($('#service').val() == '*') {
                              alert('Не выбрана услуга');
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
//                            $('#addProblem button').removeProp('disabled');
                            $(this).dialog("close");
                          }},
	                 {text: 'Сохранить',
	                  click: function() {
//						$('#addProblem').dialog('option', 'buttons', cardBtnWait);
	                  	myPostJson('/ajax/addProblem.php', {op: 'setProblem', cId: $('#apContract').val(), divId: $('#apDivision').val(),
	                  				problem: $('#apProblem').val().trim()},	null, null, function() { 
//							$('#addProblem').dialog('option', 'buttons', addProblemBtn);
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
                   				alert('Минимальная длина текста - 10 символов!');
                   				return;
                   			}
                   			if (Problem == Solution || Problem == Recomend || Solution == Recomend) {
                   				alert('Тексты в полях не должны совпадать!');
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

function storeFilter() {
  fltByDiv = $('#selectDivision :selected');
  fltBySrv = $('#filterService :selected');
  fltOnlyMy = $('#chkMyTickets :checked');
  fltText = $('fltByText').val();
  fltToDate = $('#fltByToDate').datepicker('getDate');
  fltFromDate = $('#fltByFromDate').datepicker('getDate');
}

function restoreFilter() {
  $('#selectDivision :selected').removeProp('selected');
  fltByDiv.prop('selected', 'selected');
  $('#filterService :selected').removeProp('selected');
  fltBySrv.prop('selected', 'selected');
  $('#chkMyTickets :checked').removeProp('checked');
  fltOnlyMy.prop('checked', 'checked');
  $('fltByText').val(fltText);
  $('#chkMyTickets').buttonset('refresh');
  $('#fltByToDate').datepicker('setDate', fltToDate );
  $('#fltByFromDate').datepicker('setDate', fltFromDate);
}

function resetFilter() {
  $('#selectDivision :selected').removeProp('selected');
  fltByDiv = $('#selectDivision :selected');
  $('#filterService :selected').removeProp('selected');
  fltBySrv = $('#filterService :selected');
  $('#chkMyTickets :checked').removeProp('checked');
  fltOnlyMy = false;
  $('fltByText').val('');
  fltText = '';
  $('#fltByToDate').datepicker('setDate', '0');
  $('#fltByFromDate').datepicker('setDate', '-3m');
  fltToDate = $('#fltByToDate').datepicker('getDate');
  fltFromDate = $('#fltByFromDate').datepicker('getDate');
}

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

function myPostJson(url, param, onReady, onError, onAlways) {
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
          alert(data.error);
	      if (typeof onError === 'function')
    	   	onError();
        } else {
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
        alert('Ошибка связи с сервером');
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
	  alert('Ошибка связи с сервером');
	  $('#selectEq').dialog('close');
	});       
  timeoutSet = 0;
}
  
$(function() {
  $.ui.dialog.prototype._allowInteraction = function(e) {
   	return !!$(e.target).closest('.ui-dialog, .ui-datepicker, .select2-dropdown').length;
 };
// Стартовая инициализация интерфейса
  $('#chkMyTickets').buttonset();
  $('#workflow').tabs({active: 0});
  $('#cardTabs').tabs({active: 0});
  $.post('ajax/user/isAdmin', '{}', 'json')
  	.done(function(data) {
      if (data !== null) {
        if (typeof data.error !== 'undefined')
          alert(data.error);
        if (typeof(data.isAdmin !== 'undefined') && data.isAdmin == 'ok') {
        	$("#user").append("&nbsp;<button id='admin'>Администрирование</button>");
			$('#admin').button();
        }
      }
	})
    .fail(function() {
        alert('Ошибка связи с сервером');
    });
  $('button').button();
  $('button').each(function() {
    if ($(this).data('icon') != '')
      $(this).button('option', 'icons', {primary: $(this).data('icon')});
  });
  $('#refresh').button('option', 'text', false);  
  $('#card').dialog({autoOpen: false,
                     resizable: false,
                     dialogClass: 'no-close',
                     width: 660,
                     modal: true,
                     draggable: false,
                     buttons: cardBtnNew,
                     close: function() {
                     	$(this).find('select').each(function() {
                     		$(this).select2("close");
                     	});
                     	setFilter();
                     }
              });
  $('#selectEq').dialog({autoOpen: false,
    				 title: 'Выберите оборудование',
                     resizable: false,
                     dialogClass: 'no-close',
                     width: 660,
                     modal: true,
                     draggable: false,
                     buttons: selectEqBtn
              });
  $('#selectPartner').dialog({autoOpen: false,
    				 title: 'Выберите партнёра',
                     resizable: false,
                     dialogClass: 'no-close',
                     width: 660,
                     modal: true,
                     draggable: false,
                     buttons: selectPartnerBtn
              });
  $('#solution').dialog({autoOpen: false,
                     resizable: false,
                     dialogClass: 'no-close',
                     width: 660,
                     modal: true,
                     draggable: false,
                     buttons: solutionBtn
              });
  $('#filter').dialog({autoOpen: false, 
                       position: {my: 'right top', at: 'right bottom', of: '#showFilter'}, 
                       resizable: false,
                       dialogClass: 'no-close',
                       draggable: false,
                       title: 'Отбор заявок',
                       modal: true,
                       buttons: [{text: 'Принять', click: function() {
                                                            $(this).dialog("close");
                                                            setFilter();
                                                          }},
                                 {text: 'Сбросить', click: function() { 
                                                            resetFilter();
                                                          }},
                                 {text: 'Отменить', click: function() { 
                                                            restoreFilter();
                                                            $(this).dialog("close");
                                                          }}
                                ]
                });
  $('#addProblem').dialog({autoOpen: false,
  					 title: 'Добавить задание в плановый выезд',
                     resizable: false,
                     dialogClass: 'no-close',
                     width: 660,
                     modal: true,
                     draggable: false,
                     buttons: addProblemBtn
              });
  
  $.datepicker.setDefaults({
	monthNames: ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
	monthNamesShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
	dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
	firstDay: 1
  });
  
  $('#fltByFromDate').datepicker({
  	changeMonth: true,
  	numberOfMonths: 1,
  	dateFormat: 'd MM yy',
  	altField: '#fltFromDateSQL',
  	altFormat: 'yy-mm-dd',
  	constrainInput: false,
  	onClose: function(selectedDate) {
  		$('#fltByToDate').datepicker('option', 'minDate', selectedDate);
  	}
  });

  $('#fltByToDate').datepicker({
  	changeMonth: true,
  	numberOfMonths: 1,
  	dateFormat: 'd MM yy',
  	altField: '#fltToDateSQL',
  	altFormat: 'yy-mm-dd',
  	constrainInput: false,
  	onClose: function(selectedDate) {
  		$('#fltByFromDate').datepicker('option', 'maxDate', selectedDate);
  	}
  });
  
  $('#fltByToDate').datepicker('setDate', '0');
  $('#fltByFromDate').datepicker('setDate', '-3m');
  
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
		alert('Ошибка связи с сервером');
		$('#selectEq').dialog('close');
	  });       
  });

  $('#lookPartner').click(function() {
    $('#selectPartnerList').html('');
    $('#selectPartner').dialog('open');
	myPostJson('/ajax/dir/partners/'+openCard, null, null,
	  function() {
		alert('Ошибка связи с сервером');
		$('#selectPartner').dialog('close');
	  });       
  });
  
  $('#selectPartnerList').on('click', 'li', function() {
	  $('#selectPartner').dialog('close');
	  if ('0' == $(this).data('id'))
	  	$('#partner').val('');
	  else
	  	$('#partner').val($(this).text());
	  myPostJson('/ajax/request/partner/set/'+openCard+'/'+$(this).data('id'), null, null, 
	  function() {
		alert('Ошибка связи с сервером');
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
		alert('Ошибка связи с сервером');
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
		alert('Ошибка связи с сервером');
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
		alert('Ошибка связи с сервером');
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
				    alert('Ошибка связи с сервером');
				    $('#card').dialog('close');
	  			  });
	          },
              function() {
	            alert('Ошибка связи с сервером');
	            $('#card').dialog('close');
              });
   	    }
   	  },
	  function() {
		alert('Ошибка связи с сервером');
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
		alert('Ошибка связи с сервером');
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
		alert('Ошибка связи с сервером');
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

  $('#workflow').on('click', '.btnAccept, .btnFixed, .btnClose', function() {
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
  
  $('#workflow').on('click', '.btnDoNow', function() {
    var cmd = $(this).data('cmd');
    var list = '';
    $('.tab'+$('#workflow').tabs('option', 'active')+' :checked').each(function() {
      if ($(this).data('preStart') == 1)
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
      alert('Выберите только одну заявку');
    if (rows.length != 1)
      return;
  	var id = rows.first().parents('tr').attr('id');
  	$('#solution').data('id', id);
    $('#solution').dialog('open');
	$('#solution').dialog('option', 'title', 'Решение заявки '+('0000000'+id).substr(-7));
    myPostJson('/ajax/request/getSolution/'+id, null, null,
	  function() {
		alert('Ошибка связи с сервером');
		$('#solution').dialog('close');
	  });       
	
  });

  $('#logout').click(function() {
    myPostJson('/ajax/user/logout');
  });

  $('#workflow').on('click', '.btnCancel, .btnWait, .btnUnClose, .btnUnCancel', function() {
    var cmd = $(this).data('cmd');
    var rows = $('.tab'+$('#workflow').tabs('option', 'active')+' :checked');
    if (rows.length > 1)
      alert('Выберите только одну заявку');
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
          alert('Ошибка связи с сервером');
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
            alert((typeof data.error !== 'undefined') ? data.error : 'Ошибка передачи файла');
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
            alert('Ошибка передачи файла');
        });
    }
  });

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
	myPostJson('/ajax/addProblem.php', {op: 'getContragents'}, 
	  function() {
		$('#apContragent').trigger('change');
	  });
  });

  $('#apContragent').change(function() {
	myPostJson('/ajax/addProblem.php', {op: 'getContracts', caId: $('#apContragent').val()}, 
	  function() {
		$('#apContract').trigger('change');
	  });
  });

  $('#apContract').change(function() {
	myPostJson('/ajax/addProblem.php', {op: 'getDivisions', cId: $('#apContract').val()}, 
	  function() {
		$('#apDivision').trigger('change');
	  });
  });

  $('#apDivision').change(function() {
	myPostJson('/ajax/addProblem.php', {op: 'getProblem', cId: $('#apContract').val(), divId: $('#apDivision').val()},
				null, null, function() { $('#addProblem').dialog('option', 'buttons', addProblemBtn); });
  });
	
});
