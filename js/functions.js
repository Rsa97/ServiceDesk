$(function() {
	var browserWindow = $(window);
	var width = browserWindow.width();
	var height = browserWindow.height();
	if (width > 1024) {
		widthWorkflowDiv = 1024;
		heightWorkflowDiv = widthWorkflowDiv/13*7;
	} else {
		widthWorkflowDiv = width/100*90;
		heightWorkflowDiv = height/100*70;
	}
	$('#workflowDiv').css( { marginLeft : "-" + widthWorkflowDiv / 2 + "px", marginTop : "-" + heightWorkflowDiv / 2 + "px", width: widthWorkflowDiv + "px", height: heightWorkflowDiv + "px" } );
	$('#bottomDiv').css( { marginTop: height - 20 + 'px' } );
	$('#topDiv').load('top.php');
	$('#workflowDiv').load('workflow.php', function() {
		$('#workflowMenuTicketsDiv').load('workflowMenuTickets.php');
	});		

	$('#workflowDiv').on('click', '.newTicket', function() {  
		$.post('newcard.php',
			function(output) {
				$('#newCardDiv').html(output).show();
			}); 
		$('#newCardDiv').css("visibility", 'visible');
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
		$(this).prop('disabled', 'disabled')
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
});
