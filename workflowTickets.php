<head>
	<link rel="stylesheet" type="text/css" href="style.css" />
	<script lang="javascript">
		$('.ticket').click(function() {  
			$.post('card.php', { ticketID: $(this).attr('id') },
				function(output) {
					$('#cardDiv').html(output).show();
				}); 
			$('#cardDiv').css("visibility", 'visible');
		});
	</script>
</head>
<body>
<?php
include_once 'config.php';
checkLoginStatus();
getTicketMenuInterface($_POST['statusTicket'], $_SESSION['userGroups']);	
		echo "<table id='hor-minimalist-a'>
			<thead>
				<tr>
					<th style='text-align: center' scope='col' width='27px'></th>
					<th style='text-align: center' scope='col' width='71px'>Номер</th>
					<th style='text-align: center' scope='col' width='63px'>Тип</th>
					<th style='text-align: center' scope='col' width='100px'>Дата регистр.</th>
					<th style='text-align: center' scope='col' width='100px'>Дата окон.</th>
					<th style='text-align: center' scope='col'>Заказчик</th>
					<th style='text-align: center' scope='col' width='150px'>Ответственный</th>
					<th style='text-align: center' scope='col' width='71px'>Оборудов.</th>
					<th id='lastcol' style='text-align: center' scope='col' width='100px'>Осталось</th>
				</tr>
			</thead>
			<tbody>
				<tr>";
					getTicketsList((($_POST['divType']=='d' && $_POST['divType'] != 'n')?$_POST['divFilter']:0), (($_POST['divType']=='g' && $_POST['divType'] != 'n')?$_POST['divFilter']:0), (($_SESSION['userGroups'] == 'admin' && $_POST['onlyMy'] == 1)?$_SESSION['myID']:0), (($_SESSION['userGroups'] == 'client' && $_POST['onlyMy'] == 1)?$_SESSION['myID']:0), $_POST['statusTicket'], 0, 0, $_SESSION['userGroups'], $_SESSION['myID']);
				echo "</tr>
			</tbody>
		</table>";	
?>
</body>