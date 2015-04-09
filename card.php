<head>
	<script lang="javascript">
			$('.closeTicket').click(function(){  
				$('#cardDiv').css('visibility', 'hidden');
			});
			$(document).ready(init);
			function init(){
				$("#cardDiv").draggable({
					handle: '#titleCardDiv'
				});
			}
	</script>
</head>

<body>

<?php
include_once('config.php');
echo "
<table id='titleCardDiv' width=100% style='text-align:center;background-color:#C8C8C8;cursor:move'>
	<tr>
		<td>
			<bold>Заявка №" . sprintf('%07d', $_POST['ticketID']) . "</bold>
		</td>
		<td width=12px style='cursor:default'>
			<img src='img/close.png' class='closeTicket' width='12px'>
		</td>
	</tr>
</table>
";
$ticketInfo = getTicketInfo($_POST['ticketID']);
echo "

<div id='plCard0' style='background-color:#F0F0F0;border-color:#7F9DB9;border-width:1px;border-style:Dashed;overflow:auto;text-align:center; padding: 5px 5px 5px 5px;'>
			 
          <table cellspacing='1' cellpadding='0' border='0' width='99%'>
          <tr><td colspan='5' style='height:3px'></td></tr>
          <tr>
            <td align='left' style='width:24%'>Индивидуальный сервисный №:</td>
            <td align='left' style='width:32%'>
               <input name='edISN' type='text' value='" . $ticketInfo['serviceNumber'] . "' readonly='readonly' id='edISN' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
            <td style='width:2%'></td>
            <td align='left' style='width:10%'>Серийный №:</td>
            <td align='left' style='width:32%'>
               <input name='edSN' type='text' value='" . $ticketInfo['serialNumber'] . "' readonly='readonly' id='edSN' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>            
          </tr>  
          <tr>
            <td align='left'>Тип оборудования:</td>
            <td colspan='4' align='left'>
               <input name='edEquip' type='text' value='" . $ticketInfo['equipmentSubType'] . "' readonly='readonly' id='edEquip' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left'>Производитель:</td>
            <td align='left'>
               <input name='edMaker' type='text' value='" . $ticketInfo['manufacturer'] . "' readonly='readonly' id='edMaker' class='editd' style='font-size:11px;height:20px;width:100%;' />

            </td>
            <td></td>
            <td align='left'>Модель:</td>
            <td align='left'>
               <input name='edModel' type='text' value='" . $ticketInfo['model'] . "' readonly='readonly' id='edModel' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>            
          </tr>
          <tr>
            <td align='left' valign='top'>Описание проблемы:</td>
            <td colspan='4' align='left'>
               <textarea name='edDescript' rows='2' cols='20' readonly='readonly' id='edDescript' class='editds' style='font-size:11px;height:46px;width:100%;overflow:auto'>" . $ticketInfo['problem'] . "</textarea>
            </td>
          </tr>  
          <tr>
            <td align='left'>Услуга:</td>
            <td colspan='4' align='left'>
              <input name='edBenef' type='text' value='" . $ticketInfo['serviceName'] . "' readonly='readonly' id='edBenef' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr> 
          
          <tr><td colspan='5' style='height:3px'></td></tr>
          <tr><td colspan='5' class='main_div' style='height:1px'></td></tr>
          <tr><td colspan='5' style='height:3px'></td></tr>            
          </table>
          
          <table cellspacing='1' cellpadding='0' border='0' width='99%'>
          <tr>
            <td align='left' style='width:130px'>Категория критичности:</td>
            <td align='left' style='width:50px'>
               <input name='edUrgency' type='text' value='" . $ticketInfo['slaCriticalLevels_id'] . "' readonly='readonly' id='edUrgency' class='editd' style='font-size:11px;height:20px;width:15px;' />
            </td>
            <td align='left' style='width:102px'>Дата регистрации:</td>          
            <td style='width:155px'>
              <input name='edDtbegin' type='text' value='" . dateFormat($ticketInfo['createdAt'],'d.m.Y H:i') . "' readonly='readonly' id='edDtbegin' class='editd' style='font-size:11px;height:20px;width:100px;' />        
            </td>             
            <td align='left' style='width:112px'>Срок исполнения до:</td>          
            <td>
              <input name='edDtend' type='text' value='" . dateFormat($ticketInfo['repairBefore'],'d.m.Y H:i') . "' readonly='readonly' id='edDtend' class='editd' style='font-size:11px;height:20px;width:100px;' />        
            </td>
          </tr>
          <tr><td colspan='6' style='height:3px'></td></tr>
          <tr><td colspan='6' class='main_div' style='height:1px'></td></tr>
          <tr><td colspan='6' style='height:3px'></td></tr>         
          </table> 
          
          
          <table cellspacing='1' cellpadding='0' border='0' width='99%'>
          <tr>
            <td align='left'>Заказчик:</td>
            <td colspan='4' align='left'>
               <input name='edDiv' type='text' value='" . $ticketInfo['division'] . "' readonly='readonly' id='edDiv' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left'>Адрес нахождения оборудования:</td>
            <td colspan='4' align='left'>
               <input name='edAddress' type='text' value='" . $ticketInfo['contactAddress'] . "' readonly='readonly' id='edAddress' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left'>Ответственное лицо:</td>
            <td colspan='4' align='left'>
               <input name='edFace' type='text' value='" . $ticketInfo['contactSecondName'] . " " . $ticketInfo['contactFirstName'] . " " . $ticketInfo['contactMiddleName'] . "' readonly='readonly' id='edFace' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left' style='width:24%'>Телефон:</td>
            <td align='left' style='width:32%'>
               <input name='edPhone' type='text' value='" . $ticketInfo['contactPhone'] . "' readonly='readonly' id='edPhone' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
            <td style='width:2%'></td>
            <td align='left' style='width:10%'>E-mail:</td>
            <td align='left' style='width:32%'>
               <input name='edMail' type='text' value='" . $ticketInfo['contactEmail'] . "' readonly='readonly' id='edMail' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr><td colspan='5' style='height:3px'>
              
              </td></tr>          
          </table>                   
      
		</div>



";
?>
</body>