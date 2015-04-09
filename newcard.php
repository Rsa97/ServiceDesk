<head>
	<script lang="javascript">
			$('.closeNewTicket').click(function(){  
				$('#newCardDiv').css('visibility', 'hidden');
			});
			$(document).ready(init);
			function init(){
				$("#newCardDiv").draggable({
					handle: '#titleNewCardDiv'
				});
			};
	</script>
</head>

<body>

<?php
include_once('config.php');
echo "
<table id='titleNewCardDiv' width=100% style='text-align:center;background-color:#C8C8C8;cursor:move'>
	<tr>
		<td>
			<bold>Новая заявка</bold>
		</td>
		<td width=12px style='cursor:default'>
			<img src='img/close.png' class='closeNewTicket' width='12px'>
		</td>
	</tr>
</table>
";

echo "

<div ='newPlCard0' style='background-color:#F0F0F0;border-color:#7F9DB9;border-width:1px;border-style:Dashed;overflow:auto;text-align:center; padding: 5px 5px 5px 5px;'>
			 
          <table cellspacing='1' cellpadding='0' border='0' width='99%'>
          <tr><td colspan='5' style='height:3px'></td></tr>
          <tr>
            <td align='left' style='width:24%'>Индивидуальный сервисный №:</td>
            <td align='left' style='width:32%'>
               <input name='newEdISN' type='text' value='' id='newEdISN' class='editd' style='font-size:11px;height:20px;width:70%;' />
               <input type='button' id='btnSearchEquipment' value='Найти' class='button4' onclick=''/>
            </td>
            <td style='width:2%'></td>
            <td align='left' style='width:10%'>Серийный №:</td>
            <td align='left' style='width:32%'>
               <input name='newEdSN' type='text' value='' id='newEdSN' class='editd' style='font-size:11px;height:20px;width:95%;' readonly/>
            </td>            
          </tr>  
          <tr>
            <td align='left'>Тип оборудования:</td>
            <td colspan='4' align='left'>
               <input name='newEdEquip' type='text' value='' id='newEdEquip' class='editd' style='font-size:11px;height:20px;width:98%;' readonly/>
            </td>
          </tr>
          <tr>
            <td align='left'>Производитель:</td>
            <td align='left'>
               <input name='newEdMaker' type='text' value='' id='newEdMaker' class='editd' style='font-size:11px;height:20px;width:95%;' readonly/>

            </td>
            <td></td>
            <td align='left'>Модель:</td>
            <td align='left'>
               <input name='newEdModel' type='text' value='' id='newEdModel' class='editd' style='font-size:11px;height:20px;width:95%;' readonly/>
            </td>            
          </tr>
          <tr>
            <td align='left' valign='top'>Описание проблемы:</td>
            <td colspan='4' align='left'>
               <textarea name='newEdDescript' rows='2' cols='20' id='newEdDescript' class='editds' style='font-size:11px;height:46px;width:98%;overflow:auto'></textarea>
            </td>
          </tr>  
          <tr>
            <td align='left'>Услуга:</td>
            <td colspan='4' align='left'>
              <input name='newEdBenef' type='text' value='' readonly='readonly' id='newEdBenef' class='editd' style='font-size:11px;height:20px;width:98%;' />
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
               <input name='newEdUrgency' type='text' value='' readonly='readonly' id='newEdUrgency' class='editd' style='font-size:11px;height:20px;width:15px;' />
            </td>
            <td align='left' style='width:102px'>Дата регистрации:</td>          
            <td style='width:155px'>
              <input name='newEdDtbegin' type='text' value='' readonly='readonly' id='newEdDtbegin' class='editd' style='font-size:11px;height:20px;width:100px;' />        
            </td>             
            <td align='left' style='width:112px'>Срок исполнения до:</td>          
            <td>
              <input name='newEdDtend' type='text' value='' readonly='readonly' id='newEdDtend' class='editd' style='font-size:11px;height:20px;width:100px;' />        
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
               <input name='newEdDiv' type='text' value='' readonly='readonly' id='newEdDiv' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left'>Адрес нахождения оборудования:</td>
            <td colspan='4' align='left'>
               <input name='newEdAddress' type='text' value='' readonly='readonly' id='newEdAddress' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left'>Ответственное лицо:</td>
            <td colspan='4' align='left'>
               <input name='newEdFace' type='text' value='' readonly='readonly' id='newEdFace' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left' style='width:24%'>Телефон:</td>
            <td align='left' style='width:32%'>
               <input name='newEdPhone' type='text' value='' readonly='readonly' id='newEdPhone' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
            <td style='width:2%'></td>
            <td align='left' style='width:10%'>E-mail:</td>
            <td align='left' style='width:32%'>
               <input name='newEdMail' type='text' value='' readonly='readonly' id='newEdMail' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr><td colspan='5' style='height:3px'>
              
              </td></tr>          
          </table>                   
      
		</div>



";
?>
</body>