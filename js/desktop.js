var fltByDiv, fltBySrv, fltOnlyMy;
var cardMode, openCard, servNumStore;
var servNumLookupTimeout, timeoutSet = 0;

// Кнопки карточки заявки в режиме просмотра
var cardBtnLook = [ {text: 'Сервисный лист',
					 click: function() {
					 			document.location = '/ajax/cardOps.php?op=serviceList&n='+openCard;
					 		}},
					{text: 'Закрыть', 
                    click: function() { 
                             $('#card button').removeProp('disabled');
                             $(this).dialog("close");
                           }}
                  ];

// Кнопки карточки заявки в режиме обмена с базой
var cardBtnWait = [{text: 'Идёт запрос'}];

// Кнопки карточки заявки в режиме создания
var cardBtnNew = [{text: 'Отменить', 
                   click: function() { 
                            $('#card button').removeProp('disabled');
                            $(this).dialog("close");
                          }},
                   {text: 'Создать', 
                   click: function() {
                            if ($('#division').val() == 0) {
                              alert('Не выбран филиал');
                              return;
                            }
                            if ($('#problem').val().trim() == '') {
                              alert('Не указана проблема');
                              return;
                            }
                            if ($('#contact').val() == '0') {
                              alert('Не выбран ответственный');
                              return;
                            }
                            $('#card button').prop('disabled', 'disabled');
                            myPostJson('/ajax/newCard.php', {op: 'newCard',
                            								 div: $('#division').val(),
                            								 srvNum: $('#servNum').val().trim(),
                                                             problem: $('#problem').val().trim(),
                                                             serv: $('#service').val(),
                                                             sla: $('#level').val(),
                                                             contact: $('#contact').val()},
                                       function() {
                                         $('#workflow').tabs('option', 'active', 0);
                                         setFilter();
                                         $('#card').dialog("close");
                                       },
                                       null,
                                       function() {
                                         $('#card button').removeProp('disabled');
                                       });
                          }}
                 ];

// Кнопки карточки заявки в режиме перепривязки оборудования
var cardBtnMod = [{text: 'Отменить', 
                   click: function() { 
                            $('#card button').removeProp('disabled');
                            $(this).dialog("close");
                          }},
                   {text: 'Изменить', 
                   click: function() {
                            if ($('#eqType').val() == '') {
                              alert('Не указаны обязательные поля');
                              return;
                            }
                            $('#card button').prop('disabled', 'disabled');
                            myPostJson('/ajax/newCard.php', {op: 'changeEq', n: openCard,
                            								 srvNum: $('#servNum').val().trim()},
                                       function() {
                                         $('#workflow').tabs('option', 'active', 0);
                                         setFilter();
                                         $('#card').dialog("close");
                                       },
                                       null,
                                       function() {
                                         $('#card button').removeProp('disabled');
                                       }); 
                          }}
                 ];

// Кнопки списка оборудования
var selectEqBtn = [{text: 'Отменить', 
                   click: function() { 
                            $('#selectEq button').removeProp('disabled');
                            $(this).dialog("close");
                          }}
                 ];
                 
// Кнопки добавления задач в плановые
var addProblemBtn = [{text: 'Отменить', 
                      click: function() { 
                            $('#addProblem button').removeProp('disabled');
                            $(this).dialog("close");
                          }},
	                 {text: 'Сохранить',
	                  click: function() {
						$('#addProblem').dialog('option', 'buttons', cardBtnWait);
	                  	myPostJson('/ajax/addProblem.php', {op: 'setProblem', cId: $('#apContract').val(), divId: $('#apDivision').val(),
	                  				problem: $('#apProblem').val().trim()},	null, null, function() { 
							$('#addProblem').dialog('option', 'buttons', addProblemBtn);
							$('#addProblem').dialog('close');
						});
	                  }} 
                    ];
       

// Кнопки решения
var solutionBtn = [{text: 'Отменить', 
                   click: function() { 
                            $('#solution button').removeProp('disabled');
                            $(this).dialog("close");
                          }},
                   {text: 'Принять', 
                   click: function() {
					     	$('button').prop('disabled', 'disabled');
    						myPostJson('/ajax/cardState.php', {op: 'Repaired', list: 't'+$('#solution').data('id'),
    														   solProblem: $('#solProblem').val().trim(),
    														   sol: $('#solSolution').val().trim(),
    														   solRecomend: $('#solRecomendation').val().trim(),
    														   },
               					null,
               					null,
               					function() {
									$('#solution').dialog("close");
                 					setFilter();
                 					$('button').removeProp('disabled');
               					});
               				}}
                 ];

function storeFilter() {
  fltByDiv = $('#selectDivision :selected');
  fltBySrv = $('#selectService :selected');
  fltOnlyMy = $('#chkMyTickets :checked');
}

function restoreFilter() {
  $('#selectDivision :selected').removeProp('selected');
  fltByDiv.prop('selected', 'selected');
  $('#selectService :selected').removeProp('selected');
  fltBySrv.prop('selected', 'selected');
  $('#chkMyTickets :checked').removeProp('checked');
  fltOnlyMy.prop('checked', 'checked');
  $('#chkMyTickets').buttonset('refresh');
}

function myPostJson(url, param, onReady, onError, onAlways) {
  $.post(url, param, 'json')
    .done(function(data) {
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
            onReady();
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

// Установка фильтра на сервере
function setFilter() {
  $('#filter input').prop('disabled', 'disabled');
  myPostJson('/ajax/setFilter.php', { byDiv: $('#selectDivision :selected').val(),
                                  bySrv: $('#selectService :selected').val(),
                                  onlyMy: $('#chkMyTickets :checked').val() }, 
             null, null, 
             function() { 
               $('#filter input').removeProp('disabled');
               $('.list tr:nth-child(2n+1)').addClass('odd');
               $('.list td:nth-child(1), .list th:nth-child(1)').addClass('cell1');
               $('.list td:nth-child(2)').addClass('cell2');
               $('.list td:nth-child(9)').addClass('cell9');
             });
}

function servNumLookup() {
	myPostJson('/ajax/selectDivsEq.php', {op: 'getList', div:$('#division').val(), num:$('#selectServNum').val().trim()}, 
	function() {
	  $('#selectEqList ul ul').hide();
	  $('#selectEqList ul ul.single').show();
	},
	function() {
	  alert('Ошибка связи с сервером');
	  $('#selectEq').dialog('close');
	},
	function() {
	  $('#selectEq button').removeProp('disabled'); 
	  $('#selectEq').dialog('option', 'buttons', selectEqBtn);
	});       
  timeoutSet = 0;
}
  
$(function() {
// Стартовая инициализация интерфейса
  $('#chkMyTickets').buttonset();
  $('#workflow').tabs({active: 0});
  $('#cardTabs').tabs({active: 0});
  $.post('ajax/isAdmin', '{}', 'json')
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
                     buttons: cardBtnWait
              });
  $('#selectEq').dialog({autoOpen: false,
    				 title: 'Выберите оборудование',
                     resizable: false,
                     dialogClass: 'no-close',
                     width: 660,
                     modal: true,
                     draggable: false,
                     buttons: cardBtnWait
              });
  $('#solution').dialog({autoOpen: false,
                     resizable: false,
                     dialogClass: 'no-close',
                     width: 660,
                     modal: true,
                     draggable: false,
                     buttons: cardBtnWait
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
                                                            restoreFilter();
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
  
  myPostJson('/ajax/buildFilter.php', {}, function() {
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

  $('#contact').change(function() {
    var opt = $('#contact :selected');
    $('#email').val(opt.data('email'));
    $('#phone').val(opt.data('phone'));
    $('#address').val(opt.data('address'));
  });

  $('#lookServNum').click(function() {
  	if ($('#division').val() == 0)
  	  return;
    $('#selectEq').dialog('option', 'buttons', cardBtnWait);
    $('#selectEq select').html('');
    $('#selectEq').dialog('open');
    //$('#selectServNum').val($('#servNum').val());
    $('#selectServNum').val('');
	myPostJson('/ajax/selectDivsEq.php', {op: 'getList', div: $('#division').val(), num: $('#selectServNum').val()},
	  function() {
		$('#selectEqList ul ul').hide();
		$('#selectEqList ul ul.single').show();
	  },
	  function() {
		alert('Ошибка связи с сервером');
		$('#selectEq').dialog('close');
	  },
	  function() {
		$('#selectEq button').removeProp('disabled'); 
		$('#selectEq').dialog('option', 'buttons', selectEqBtn);
	  });       
  });

  $('#workflow').on('click', '.btnNew', function() {
	$('#card').dialog('open');
	$('#card').dialog('option', 'title', 'Новая заявка');
    $('#card .ro').removeProp('readonly');
	$('#cardTabs').tabs('option', 'active', 0);
   	$('#card').dialog('option', 'buttons', cardBtnWait);
	$('#servNum').val('');
	$('#card input').val('');
	$('#card select').html('');
	$('#card textarea').val('');
	$('#card .active').removeClass('active');
	$('#lookServNum').hide();
	$('#division').parent().prev().addClass('active');
    cardMode = 'new';
	myPostJson('/ajax/newCard.php', {op: 'divsList'},
	  function() {
		$('#card').dialog('option', 'buttons', cardBtnNew);
	  	$('#division').trigger('change');
	  },
	  function() {
		alert('Ошибка связи с сервером');
		$('#card').dialog('close');
	  });
  });
  
  $('#card').on('change', '#division', function() {
  	if ($('#division').val() == 0 || cardMode != 'new')
  	  return;
  	$('#servNum').val('');
  	$('#SN').val('');
  	$('#eqType').val('');
  	$('#manufacturer').val('');
  	$('#model').val('');
   	$('#card').dialog('option', 'buttons', cardBtnWait);
	myPostJson('/ajax/newCard.php', {op: 'fillNewCard1', div: $('#division').val()}, 
	  function() {
		$('#division').parent().prev().removeClass('active');
		$('#problem').parent().prev().addClass('active');
		$('#service').parent().prev().addClass('active');
		$('#level').parent().prev().addClass('active');
		$('#contact').parent().prev().addClass('active');
		$('#card button').removeProp('disabled');
		$('#lookServNum').show();
		$('#card').dialog('option', 'buttons', cardBtnNew);
		$('#contact').trigger('change');
		$('#service').trigger('change');
	  },
	  function() {
		alert('Ошибка связи с сервером');
		$('#card').dialog('close');
	  });
  });
  
  $('#card').on('change', '#service', function() {
  	if ($('#service').val() == 0 || cardMode != 'new')
  	  return;
   	$('#card').dialog('option', 'buttons', cardBtnWait);
	myPostJson('/ajax/newCard.php', {op: 'fillNewCard2', div: $('#division').val(), serv: $('#service').val()}, 
	  function() {
		$('#card').dialog('option', 'buttons', cardBtnNew);
		$('#level').trigger('change');
	  },
	  function() {
		alert('Ошибка связи с сервером');
		$('#card').dialog('close');
	  });
  });
  
  $('#card').on('change', '#level', function() {
  	if (cardMode != 'new')
  		return;
   	$('#card').dialog('option', 'buttons', cardBtnWait);
	myPostJson('/ajax/newCard.php', {op: 'calcTime', div: $('#division').val(), serv: $('#service').val(), sla: $('#level').val()}, 
	  function() {
		$('#card').dialog('option', 'buttons', cardBtnNew);
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

  $('#workflow').on('click', '.btnAccept, .btnFixed, .btnClose, .btnDoNow', function() {
    var cmd = $(this).data('cmd');
    var list = '';
    console.log('.tab'+$('#workflow').tabs('option', 'active')+' :checked');
    console.log($('.tab'+$('#workflow').tabs('option', 'active')+' :checked'));
    $('.tab'+$('#workflow').tabs('option', 'active')+' :checked').each(function() {
      list += $(this).parents('tr').attr('id')+',';
    });
    console.log('list = '+list);
    if (list == '')
      return;
    $('button').prop('disabled', 'disabled');
    myPostJson('/ajax/cardState.php', {op: cmd, list: list},
               null,
               null,
               function() {
                 setFilter();
                 $('button').removeProp('disabled');
               });
  });
  
  $('#workflow').on('click', '.btnRepaired', function() {
  	var req = $('.tab1 tbody :checked').first();
  	if (req.length == 0 || req.parent('td').find('.ui-icon-clock').length == 1 ||
  	    req.parent('td').find('.ui-icon-mail-open, .ui-icon-wrench').length == 0)
  	    return;
  	var id = req.parents('tr').prop('id').substr(1);
  	$('#solution').data('id', id);
    $('#solution').dialog('open');
	$('#solution').dialog('option', 'title', 'Решение заявки '+('0000000'+id).substr(-7));
   	$('#solution').dialog('option', 'buttons', cardBtnWait);
	$('#solution button').prop('disabled', 'disabled');
    myPostJson('/ajax/cardOps.php', {op:'getSolution', n:id}, null,
	  function() {
		alert('Ошибка связи с сервером');
		$('#solution').dialog('close');
	  },
      function() {
		$('#solution button').removeProp('disabled');
		$('#solution').dialog('option', 'buttons', solutionBtn); 
	  });       
	
  });

  $('#logout').click(function() {
    myPostJson('/ajax/login.php', {Op: 'out'}, null, null, null);
  });

  $('#workflow').on('click', '.btnCancel, .btnWait, .btnUnClose, .btnUnCancel', function() {
    var cmd = $(this).data('cmd');
    console.log('.tab'+$('#workflow').tabs('option', 'active')+' :checked');
    var rows = $('.tab'+$('#workflow').tabs('option', 'active')+' :checked');
    if (rows.length > 1)
      alert('Выберите только одну заявку');
    if (rows.length != 1)
      return;
    var list = rows.first().parents('tr').attr('id');
    var cause;
    if ((cause = prompt('Причина:', '')) == null || cause == '')
	    return;
    $('button').prop('disabled', 'disabled');
    myPostJson('/ajax/cardState.php', {op: cmd, list: list, cause: cause},
               null,
               null,
               function() {
                 setFilter();
                 $('button').removeProp('disabled');
               });
  });

  $('.list').on('click', 'td', function() {
    if ($(this).hasClass('cell1'))
      return;
    if ($(this).parents('table').hasClass('planned'))
      return;
    openCard = +($(this).parents('tr').attr('id').substr(1));
    if (openCard != 0) {
      $('#card').dialog('option', 'title', 'Заявка '+$(this).siblings('.cell2').text());
      $('#card').dialog('option', 'buttons', cardBtnWait);
      $('#cardTabs').tabs('option', 'active', 0);
      $('#card .ro').prop('readonly', 'readonly');
      $('#card input, #card select, #card textarea').val('');
      $('#card input, #card select, #card textarea').each(function() {
        $(this).parent().prev().removeClass('active');
      });
      $('#card select').html('');
      $('#card button').prop('disabled', 'disabled');
      $('#card').dialog('open');
      cardMode = 'look';
      myPostJson('/ajax/cardOps.php', {n: openCard},
        function() {
          $('#cardDocTbl tr:nth-child(2n+1)').addClass('odd');
          $('#cardDocTbl td:nth-child(1)').addClass('cell1');
          $('#cardDocTbl td:nth-child(4)').addClass('cell4');
        },
        function() {
          alert('Ошибка связи с сервером');
          $('#card').dialog('close');
        },
        function() {
          $('#card button').removeProp('disabled'); 
          $('#card').dialog('option', 'buttons', cardBtnLook);
	      $('#cardTabs').tabs('option', 'disabled', []);
	      $('#lookServNum').hide();
	      myPostJson('/ajax/newCard.php', {op: 'isChangeAllowed', n: openCard});
        });       
    }
  });

  $('#addComm').click(function() {
    if (cardMode == 'look' && $('#addComment').val() != '') {
      $('#card button').prop('disabled', 'disabled'); 
      $('#card').dialog('option', 'buttons', cardBtnWait);
      myPostJson('/ajax/cardOps.php', {op: 'addComment', n: openCard, comment: $('#addComment').val()},
                 function() {
                   $('#cardDocTbl tr:nth-child(2n+1)').addClass('odd');
                   $('#cardDocTbl td:nth-child(1)').addClass('cell1');
                   $('#cardDocTbl td:nth-child(3)').addClass('cell3');
                   $('#addComment').val('');
                 },
                 null,
                 function() { 
                   $('#card').dialog('option', 'buttons', cardBtnLook); 
                   $('#card button').removeProp('disabled'); 
                  });
    }
  });

  $('#addFile').click(function() {
    if (cardMode == 'look' && typeof ($('#file')[0].files[0]) !== 'undefined') {
      var fd = new FormData();
      fd.append('op', 'addFile');
      fd.append('n', openCard);
      fd.append('file', $('#file')[0].files[0]);
      $('#card button').prop('disabled', 'disabled'); 
      $('#card').dialog('option', 'buttons', cardBtnWait);
      $.ajax({type: 'POST',
              url: '/ajax/cardOps.php',
              data: fd,
              processData: false,
              contentType: false,
              dataType: 'json'})
        .done(function(data) {
          if (data === null || typeof data.error !== 'undefined') {
            alert((typeof data.error !== 'undefined') ? data.error : 'Ошибка передачи файла');
            $('#card').dialog('option', 'buttons', cardBtnLook); 
            $('#card button').removeProp('disabled'); 
          } else {
            $('#file').val('');
            myPostJson('/ajax/cardOps.php', {n: openCard},
                       function() {
                         $('#cardDocTbl tr:nth-child(2n+1)').addClass('odd');
                         $('#cardDocTbl td:nth-child(1)').addClass('cell1');
                         $('#cardDocTbl td:nth-child(3)').addClass('cell3');
                       },
                       null,
                       function() { 
                         $('#card').dialog('option', 'buttons', cardBtnLook); 
                         $('#card button').removeProp('disabled'); 
                        });
          }
        })
        .fail(function() {
            alert('Ошибка передачи файла');
            $('#card').dialog('option', 'buttons', cardBtnLook); 
            $('#card button').removeProp('disabled'); 
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
  
	$('body').on('click', '.newTicket', function() {
		$.post('newcard.php',
			function(output) {
				$('#newCardDiv').html(output).show();
			}); 
		$('#newCardDiv').css("visibility", 'visible');
	});	

	$('body').on('change', '.checkAll', function() {  
    if ($(this).prop('checked'))
      $(this).parents('table').first().find('.checkOne').not(':disabled').prop('checked', 'checked');
    else
      $(this).parents('table').first().find('.checkOne').not(':disabled').removeProp('checked');
	});	

	$('#workflowDiv').on('change', '#selectDivision, #chkMyTickets', function() {
		$('#ticketFilter').find('input').each(function() {
			$(this).prop('disabled', 'disabled');
		});
		var divFilterValue = $('#selectDivision').val().substring(1);
		var divTypeValue = $('#selectDivision').val().charAt(0);
		var statusTicket = $('#statusTicket').val();
		var onlyMy = $('#chkMyTickets').prop('checked') ? 1 : 0;
		$.post('workflowMenuTickets.php', { statusTicket: statusTicket , divFilter: divFilterValue, divType: divTypeValue, onlyMy: onlyMy })
			.done(function(output) {
				$('#workflowMenuTicketsDiv').html(output).show();
			})
			.always(function() {
				$('#ticketFilter').find('input').each(function() {
					$(this).removeProp('disabled');
				});
			});
  	});

	$('#newCardDiv').on('click', '#btnSearchEquipment', function() {
		$(this).prop('disabled', 'disabled');
		$.post('cardFunctions.php', {op: 'getEq', num: $('#newEdISN').val()}, 'json')
			.done(function(result) {
				if (result === null)
					return;
				if (result.num !== 'undefined')
					$('#newEdISN').val(result.num);
				if (result.sn !== 'undefined')
					$('#newEdSN').val(result.sn);
				if (result.brand !== 'undefined')
					$('#newEdMaker').val(result.brand);
				if (result.model !== 'undefined')
					$('#newEdModel').val(result.model);
				if (result.type !== 'undefined')
					$('#newEdEquip').val(result.type);
			})
			.always(function() {
				$('#btnSearchEquipment').removeProp('disabled');
			});
	});
	
	$('#selectEqList').on('click', '.open>ul>li', function (evt) {
	  evt.stopPropagation();
	  $('#selectEq').dialog('close');
  	  $('#servNum').val($(this).data('id'));
	  $('#card').dialog('option', 'buttons', cardBtnWait);
	  myPostJson('/ajax/newCard.php', {op: 'getEqData', div: $('#division').val(), num: $('#servNum').val()}, 
	  function() {
	  	if (cardMode == 'new')
		  $('#card').dialog('option', 'buttons', cardBtnNew);
		else
		  $('#card').dialog('option', 'buttons', cardBtnMod);
	  },
	  function() {
		alert('Ошибка связи с сервером');
		$('#card').dialog('close');
	  });
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
		$('#addProblem').dialog('option', 'buttons', cardBtnWait);
		myPostJson('/ajax/addProblem.php', {op: 'getContragents'}, function() {
			$('#addProblem').dialog('option', 'buttons', addProblemBtn);
			$('#apContragent').trigger('change');
		}, function() {
			$('#addProblem').dialog('option', 'buttons', addProblemBtn);
		});
	});

	$('#apContragent').change(function() {
		$('#addProblem').dialog('option', 'buttons', cardBtnWait);
		myPostJson('/ajax/addProblem.php', {op: 'getContracts', caId: $('#apContragent').val()}, function() {
			$('#addProblem').dialog('option', 'buttons', addProblemBtn);
			$('#apContract').trigger('change');
		}, function() {
			$('#addProblem').dialog('option', 'buttons', addProblemBtn);
		});
	});

	$('#apContract').change(function() {
		$('#addProblem').dialog('option', 'buttons', cardBtnWait);
		myPostJson('/ajax/addProblem.php', {op: 'getDivisions', cId: $('#apContract').val()}, function() {
			$('#addProblem').dialog('option', 'buttons', addProblemBtn);
			$('#apDivision').trigger('change');
		}, function() {
			$('#addProblem').dialog('option', 'buttons', addProblemBtn);
		});
	});

	$('#apDivision').change(function() {
		$('#addProblem').dialog('option', 'buttons', cardBtnWait);
		myPostJson('/ajax/addProblem.php', {op: 'getProblem', cId: $('#apContract').val(), divId: $('#apDivision').val()},
					null, null, function() { $('#addProblem').dialog('option', 'buttons', addProblemBtn); });
	});
	
});
