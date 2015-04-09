var fltByDiv, fltBySrv, fltOnlyMy;
var cardMode, openCard, servNumStore;
var servNumLookupTimeout, timeoutSet = 0;

// Кнопки карточки заявки в режиме просмотра
var cardBtnLook = [{text: 'Закрыть', 
                    click: function() { 
                             $('#card button').removeProp('disabled');
                             $(this).dialog("close");
                           }}
                  ];

// Кнопки карточки заявки в режиме обмена с базой
var cardBtnWait = [{text: 'Идёт запрос', 
                    click: function() {
                           }}
                  ];

// Кнопки карточки заявки в режиме создания
var cardBtnNew = [{text: 'Отменить', 
                   click: function() { 
                            $('#card button').removeProp('disabled');
                            $(this).dialog("close");
                          }},
                   {text: 'Принять', 
                   click: function() {
                            if ($('#eqType').val() == '' || $('#problem').val().trim() == '') {
                              alert('Не указаны обязательные поля');
                              return;
                            }
                            $('#card button').prop('disabled', 'disabled');
                            myPostJson('/ajax/newCard.php', {srvNum: $('#servNum').val().trim(),
                                                             problem: $('#problem').val().trim(),
                                                             service: $('#service').val(),
                                                             level: $('#level').val(),
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
                   {text: 'Принять', 
                   click: function() {
                            if ($('#eqType').val() == '') {
                              alert('Не указаны обязательные поля');
                              return;
                            }
                            $('#card button').prop('disabled', 'disabled');
                            myPostJson('/ajax/newCard.php', {n: openCard,
                            								 srvNum: $('#servNum').val().trim(),
                                                             level: $('#level').val()},
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
	myPostJson('/ajax/selectDivsEq.php', {op: 'getDivList', div:$('#selectEqOrg').val(), num:$('#selectServNum').val().trim()}, 
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
	  $('#cardEq').dialog('option', 'buttons', cardBtnMod); 
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
        if (typeof(data.isAdmin !== 'undefined')) {
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
  	location.replace('admin.html');
  });

  $('#contact').change(function() {
    var opt = $('#contact :selected');
    $('#email').val(opt.data('email'));
    $('#phone').val(opt.data('phone'));
    $('#address').val(opt.data('address'));
  });

  $('#level').change(function() {
    var t = $('#level :selected').data('time');
    if (t != '') {
      var date = new Date(Date.parse($('#createTime').val())+t*60*1000);
      $('#repairBefore').val(('0'+date.getDate()).substr(-2)+'.'+('0'+(date.getMonth()+1)).substr(-2)+'.'+date.getFullYear()+' '+
                             ('0'+date.getHours()).substr(-2)+':'+('0'+date.getMinutes()).substr(-2));
    }
  });
  
  $('#lookServNum').click(function() {
  	if (cardMode == 'new') {
  	  $('#card').dialog('close');
  	  $('.btnNew').trigger('click');
  	} else {
      $('#selectEq').dialog('option', 'buttons', cardBtnWait);
      $('#selectEq select').html('');
	  $('#selectEq').dialog('open');
	  myPostJson('/ajax/selectDivsEq.php', {op: 'modDivList', n:openCard, div:$('#selectEqOrg').val(), num:$('#selectServNum').val().trim()}, 
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
		$('#cardEq').dialog('option', 'buttons', cardBtnMod); 
	  });       
  	}
  });

  $('#workflow').on('click', '.btnNew', function() {
    $('#selectEq').dialog('option', 'buttons', cardBtnWait);
    $('#selectEq input').val('');
    $('#selectEq select').html('');
	$('#selectEq').dialog('open');
	myPostJson('/ajax/selectDivsEq.php', {op: 'getDivList', div:$('#selectEqOrg').val(), num:$('#selectServNum').val().trim()}, 
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
  
  $('#selectServNum').keypress(function() {
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
//          $('#lookServNum').hide();
          $('#card').dialog('option', 'buttons', cardBtnLook);
	      $('#cardTabs').tabs('option', 'disabled', [])
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
      $(this).parents('table').first().find('.checkOne').prop('checked', 'checked');
    else
      $(this).parents('table').first().find('.checkOne').removeProp('checked');
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
	
	$('#selectEqOrg').change(function() {
	    $('#selectEq').dialog('option', 'buttons', cardBtnWait);
		myPostJson('/ajax/selectDivsEq.php', {op:'getEqList', div:$(this).val(), num:$('#selectServNum').val().trim()},
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
	
	$('#selectEqList').on('click', '.open>ul>li', function (evt) {
		evt.stopPropagation();
		$('#selectEq').dialog('close');
		$('#card').dialog('open');
		$('#card').dialog('option', 'title', 'Новая заявка');
		$('#cardTabs').tabs('option', 'active', 0);
    	$('#card').dialog('option', 'buttons', cardBtnWait);
  		$('#servNum').val($(this).data('id'));
	    if ($('#card').dialog('isOpen') && cardMode != 'new') {
			myPostJson('/ajax/newCard.php', {srvNum: $(this).data('id')},
				function() {
					$('#contact').trigger('change');
					$('#level').trigger('change');
					$('#servNum').parent().prev().removeClass('active');
					$('#problem').parent().prev().addClass('active');
					$('#service').parent().prev().addClass('active');
					$('#level').parent().prev().addClass('active');
					$('#contact').parent().prev().addClass('active');
				},
				function() {
					alert('Ошибка связи с сервером');
					$('#card').dialog('close');
				},
				function() {
					$('#card').dialog('option', 'buttons', cardBtnMod);
					$('#card button').removeProp('disabled');
				});
	    } else {
	    	$('#card .ro').prop('readonly', 'readonly');
	    	$('#card input, #card select, #card textarea').val('');
    		$('#card input, #card select, #card textarea').each(function() {
      			$(this).parent().prev().removeClass('active');
	    	});
    		$('#card select').html('');
    		$('#card button').removeProp('disabled');
    		cardMode = 'new';
      		$('#card button').prop('disabled', 'disabled');
      		$('#cardTabs').tabs('option', 'disabled', [1, 2, 3]);
			myPostJson('/ajax/newCard.php', {srvNum: $(this).data('id')},
				function() {
					$('#card .ro').removeProp('readonly');
					$('#contact').trigger('change');
					$('#level').trigger('change');
					$('#servNum').parent().prev().removeClass('active');
					$('#problem').parent().prev().addClass('active');
					$('#service').parent().prev().addClass('active');
					$('#level').parent().prev().addClass('active');
					$('#contact').parent().prev().addClass('active');
				},
				function() {
					alert('Ошибка связи с сервером');
					$('#card').dialog('close');
				},
				function() {
					$('#lookServNum').show();
					$('#card').dialog('option', 'buttons', cardBtnNew); 
					$('#card button').removeProp('disabled');
				});
		}       
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
});
