<?php
include_once('config.php');
echo "<input type='hidden' value='received' id='statusTicket' />";
?>
<head>
	<link rel="stylesheet" type="text/css" href="style.css" />
<!--	<script language="javascript">
		$(document).ready(function() {
			$('#workflowMenuTicketsDiv').load('workflowMenuTickets.php');	
		});
		$('#selectDivision').on('change', function() {
			var divFilterValue = document.getElementById('selectDivision').value.substring(1);
			var divTypeValue = document.getElementById('selectDivision').value.charAt(0);
			var statusTicket = document.getElementById('statusTicket').value;
			if(document.getElementById('chkMyTickets').checked) {
				var onlyMy = 1;
			};
			$.post('workflowMenuTickets.php', { statusTicket: statusTicket , divFilter: divFilterValue, divType: divTypeValue, onlyMy: onlyMy },
				function(output) {
					$('#workflowMenuTicketsDiv').html(output).show();
			});
  		});
  		
  		$('#chkMyTickets').change(function(){ 
	  		if(this.checked){ 
		  		var divFilterValue = document.getElementById('selectDivision').value.substring(1);
		  		var divTypeValue = document.getElementById('selectDivision').value.charAt(0);
		  		var statusTicket = document.getElementById('statusTicket').value;
		  		if(document.getElementById('chkMyTickets').checked) {
					var onlyMy = 1;
				};
				$.post('workflowMenuTickets.php', { statusTicket: statusTicket , divFilter: divFilterValue, divType: divTypeValue, onlyMy: onlyMy },
					function(output) {
						$('#workflowMenuTicketsDiv').html(output).show();
				});
		  	}else{ 
		  		var divFilterValue = document.getElementById('selectDivision').value.substring(1);
		  		var divTypeValue = document.getElementById('selectDivision').value.charAt(0);
		  		var statusTicket = document.getElementById('statusTicket').value;
		  		if(document.getElementById('chkMyTickets').checked) {
					var onlyMy = 0;
				};
				$.post('workflowMenuTickets.php', { statusTicket: statusTicket , divFilter: divFilterValue, divType: divTypeValue, onlyMy: onlyMy },
					function(output) {
						$('#workflowMenuTicketsDiv').html(output).show();
				});
		}}); 
	</script> -->
</head>
<body>
<div class='titleTickets'>Перечень заявок</div>
<div id='ticketFilter' style='background-color:#e9f1f7'>
<table>
	<tr>
		<td style='padding-left: 10px'>
			<div>
				Заказчик: 
			</div>
		</td>
		<td style='border-right: 1px dashed #6e6e6e; padding-right: 10px'>
			<?php
				switch($_SESSION['userGroups']) {
					case 'admin':
						$limit = 1;
					break;
					case 'client':
						$limit = 0;
					break;
					case 'engeneer':
						$limit = 1;
					break;
					case 'partner':
						$limit = 0;
					break;
				}
				getFilterOptions($_SESSION['myID'], $limit, 'division');
			?>
		</td>
		<td style='padding-left: 10px;padding-right: 10px;border-right: 1px dashed #6e6e6e;'>
			<input type='checkbox' id='chkMyTickets' style='border: 1px #6e6e6e solid; vertical-align:middle;'><label style='vertical-align:middle;' for='chkMyTickets'>Только мои</label>
		</td>
		<td style='padding-left: 10px'>
			Группы работ: 
		</td>
		<td style='border-right: 1px dashed #6e6e6e; padding-right: 10px'>
			<select class='dropMenu' disabled>
				<option id="filter_work_all">--- Все группы ---</option>
				<option id="filter_work_1">Восстановление работоспособности</option>
				<option id="filter_work_2">Настройка ПО</option>
			</select>
		</td>
		<td style='padding-left: 10px'>
			Номер заявки: 
		</td>
		<td>
			<input type='text' class='edit2'>
		</td>
		<td>
			<input type='button' id='btnSearchTicket' value='Найти' class='button3' onclick=""/>
		</td>
	</tr>	
</table>
</div>

<div id='workflowMenuTicketsDiv'></div>
</body>
