<?php
include_once('config.php');
if (!isset($_POST['divFilter'])) $_POST['divFilter'] = '0';
if (!isset($_POST['divType'])) $_POST['divType'] = 'n';
if (!isset($_POST['onlyMy'])) $_POST['onlyMy'] = '0';
?>
<head>
	<link rel="stylesheet" type="text/css" href="style.css" />
	<script lang="javascript">
		$('#_buttonTicketsMenuNew').click(function() {
			$.post('workflowTickets.php', { statusTicket: 'received', divFilter: '<?php echo $_POST['divFilter']; ?>', divType: '<?php echo $_POST['divType']; ?>', onlyMy: '<?php echo $_POST['onlyMy']; ?>' },
				function(output) {
					$('#workflowTicketsDiv').html(output).show();
				}); 
			$('#_buttonTicketsMenuNew').removeClass('selected');
			$('#_buttonTicketsMenuNew').addClass('selected'); 
			$('#_buttonTicketsMenuClosed').removeClass('selected');
			$('#_buttonTicketsMenuInWork').removeClass('selected');
			$('#_buttonTicketsMenuPlan').removeClass('selected');
			$('#_buttonTicketsMenuCanceled').removeClass('selected');
			document.getElementById('statusTicket').value='received';
		});
		
		$('#_buttonTicketsMenuPlan').click(function() {
			$.post('workflowTickets.php', { statusTicket: 'planned', divFilter: '<?php echo $_POST['divFilter']; ?>', divType: '<?php echo $_POST['divType']; ?>', onlyMy: '<?php echo $_POST['onlyMy']; ?>' },
				function(output) {
					$('#workflowTicketsDiv').html(output).show();
				}); 
			$('#_buttonTicketsMenuPlan').removeClass('selected');
			$('#_buttonTicketsMenuPlan').addClass('selected'); 
			$('#_buttonTicketsMenuNew').removeClass('selected');
			$('#_buttonTicketsMenuInWork').removeClass('selected');
			$('#_buttonTicketsMenuClosed').removeClass('selected');
			$('#_buttonTicketsMenuCanceled').removeClass('selected');
			document.getElementById('statusTicket').value='planned';
		});
		
		$('#_buttonTicketsMenuInWork').click(function() {
			$.post('workflowTickets.php', { statusTicket: 'accepted', divFilter: '<?php echo $_POST['divFilter']; ?>', divType: '<?php echo $_POST['divType']; ?>', onlyMy: '<?php echo $_POST['onlyMy']; ?>' },
				function(output) {
					$('#workflowTicketsDiv').html(output).show();
				}); 
			$('#_buttonTicketsMenuInWork').removeClass('selected');
			$('#_buttonTicketsMenuInWork').addClass('selected'); 
			$('#_buttonTicketsMenuNew').removeClass('selected');
			$('#_buttonTicketsMenuClosed').removeClass('selected');
			$('#_buttonTicketsMenuPlan').removeClass('selected');
			$('#_buttonTicketsMenuCanceled').removeClass('selected');
			document.getElementById('statusTicket').value='accepted';
		});
		
		$('#_buttonTicketsMenuClosed').click(function() {  
			$.post('workflowTickets.php', { statusTicket: 'closed', divFilter: '<?php echo $_POST['divFilter']; ?>', divType: '<?php echo $_POST['divType']; ?>', onlyMy: '<?php echo $_POST['onlyMy']; ?>' },
				function(output) {
					$('#workflowTicketsDiv').html(output).show();
				}); 
			$('#_buttonTicketsMenuClosed').removeClass('selected');
			$('#_buttonTicketsMenuClosed').addClass('selected'); 
			$('#_buttonTicketsMenuNew').removeClass('selected');
			$('#_buttonTicketsMenuInWork').removeClass('selected');
			$('#_buttonTicketsMenuPlan').removeClass('selected');
			$('#_buttonTicketsMenuCanceled').removeClass('selected');
			document.getElementById('statusTicket').value='closed';
		});
		
		$('#_buttonTicketsMenuCanceled').click(function() {  
			$.post('workflowTickets.php', { statusTicket: 'canceled', divFilter: '<?php echo $_POST['divFilter']; ?>', divType: '<?php echo $_POST['divType']; ?>', onlyMy: '<?php echo $_POST['onlyMy']; ?>' },
				function(output) {
					$('#workflowTicketsDiv').html(output).show();
				});
			$('#_buttonTicketsMenuCanceled').removeClass('selected');
			$('#_buttonTicketsMenuCanceled').addClass('selected'); 
			$('#_buttonTicketsMenuNew').removeClass('selected');
			$('#_buttonTicketsMenuInWork').removeClass('selected');
			$('#_buttonTicketsMenuPlan').removeClass('selected');
			$('#_buttonTicketsMenuClosed').removeClass('selected');
			document.getElementById('statusTicket').value='canceled';
		});
		
		///		
		$(document).ready(function() {			
			$.post('workflowTickets.php', { statusTicket: document.getElementById('statusTicket').value, divFilter: '<?php echo $_POST['divFilter']; ?>', divType: '<?php echo $_POST['divType']; ?>', onlyMy: '<?php echo $_POST['onlyMy']; ?>' },
				function(output) {
					$('#workflowTicketsDiv').html(output).show();
				});		
		});
	</script>
</head>
<body>
<?php
		echo "
			<ul class='navi'>
				<li><a id='_buttonTicketsMenuNew' href='#' class='selected'>Новые (" . getTicketsNum('received', $_POST['divType'], $_POST['divFilter'], $_SESSION['userGroups'], $_SESSION['myID']) . ")</a></li>
				<li><a id='_buttonTicketsMenuInWork' href='#'>Текущие (" . getTicketsNum('accepted', $_POST['divType'], $_POST['divFilter'], $_SESSION['userGroups'], $_SESSION['myID']) . ")</a></li>
				<li><a id='_buttonTicketsMenuPlan' href='#'>Плановые (" . getTicketsNum('planned', $_POST['divType'], $_POST['divFilter'], $_SESSION['userGroups'], $_SESSION['myID']) . ")</a></li>
				<li><a id='_buttonTicketsMenuClosed' href='#'>Выполненные (" . getTicketsNum('closed', $_POST['divType'], $_POST['divFilter'], $_SESSION['userGroups'], $_SESSION['myID']) . ")</a></li>
				<li><a id='_buttonTicketsMenuCanceled' href='#'>Отмененные (" . getTicketsNum('canceled', $_POST['divType'], $_POST['divFilter'], $_SESSION['userGroups'], $_SESSION['myID']) . ")</a></li>
			</ul>
		";
		echo "<div id='workflowTicketsDiv'></div>";
	?>
</body>