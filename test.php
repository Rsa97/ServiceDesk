<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Language" content="ru">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<head>
</head>
<body>

<?php
echo "<div style='width=200px'>
<table id='titleCardDiv' width=100% style='text-align:center;background-color:#C8C8C8;'>
	<tr>
		<td>
			<bold>Новая заявка</bold>
		</td>
		<td width=12px>
			<img src='img/close.png' class='closeTicket' width='12px'>
		</td>
	</tr>
</table>
";
echo "

<div id='plCard0' style='background-color:#F0F0F0;border-color:#7F9DB9;border-width:1px;border-style:Dashed;overflow:auto;text-align:center; padding: 5px 5px 5px 5px;'>
			 
          <table cellspacing='1' cellpadding='0' border='0' width='99%'>
          <tr><td colspan='5' style='height:3px'></td></tr>
          <tr>
            <td align='left' style='width:24%'>Индивидуальный сервисный №:</td>
            <td align='left' style='width:32%'>
               <input name='edISN' type='text' value='' id='edISN' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
            <td style='width:2%'></td>
            <td align='left' style='width:10%'>Серийный №:</td>
            <td align='left' style='width:32%'>
               <input name='edSN' type='text' value='' readonly='readonly' id='edSN' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>            
          </tr>  
          <tr>
            <td align='left'>Тип оборудования:</td>
            <td colspan='4' align='left'>
               <input name='edEquip' type='text' value='' readonly='readonly' id='edEquip' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left'>Производитель:</td>
            <td align='left'>
               <input name='edMaker' type='text' value='' readonly='readonly' id='edMaker' class='editd' style='font-size:11px;height:20px;width:100%;' />

            </td>
            <td></td>
            <td align='left'>Модель:</td>
            <td align='left'>
               <input name='edModel' type='text' value='' readonly='readonly' id='edModel' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>            
          </tr>
          <tr>
            <td align='left' valign='top'>Описание проблемы:</td>
            <td colspan='4' align='left'>
               <textarea name='edDescript' rows='2' cols='20' id='edDescript' class='editds' style='font-size:11px;height:46px;width:100%;overflow:auto'></textarea>
            </td>
          </tr>  
          <tr>
            <td align='left'>Услуга:</td>
            <td colspan='4' align='left'>
              <input name='edBenef' type='text' value='' readonly='readonly' id='edBenef' class='editd' style='font-size:11px;height:20px;width:100%;' />
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
               <input name='edUrgency' type='text' value='' readonly='readonly' id='edUrgency' class='editd' style='font-size:11px;height:20px;width:15px;' />
            </td>
            <td align='left' style='width:102px'>Дата регистрации:</td>          
            <td style='width:155px'>
              <input name='edDtbegin' type='text' value='' readonly='readonly' id='edDtbegin' class='editd' style='font-size:11px;height:20px;width:100px;' />        
            </td>             
            <td align='left' style='width:112px'>Срок исполнения до:</td>          
            <td>
              <input name='edDtend' type='text' value='' readonly='readonly' id='edDtend' class='editd' style='font-size:11px;height:20px;width:100px;' />        
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
               <input name='edDiv' type='text' value='' readonly='readonly' id='edDiv' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left'>Адрес нахождения оборудования:</td>
            <td colspan='4' align='left'>
               <input name='edAddress' type='text' value='' readonly='readonly' id='edAddress' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left'>Ответственное лицо:</td>
            <td colspan='4' align='left'>
               <input name='edFace' type='text' value='' readonly='readonly' id='edFace' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr>
            <td align='left' style='width:24%'>Телефон:</td>
            <td align='left' style='width:32%'>
               <input name='edPhone' type='text' value='' readonly='readonly' id='edPhone' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
            <td style='width:2%'></td>
            <td align='left' style='width:10%'>E-mail:</td>
            <td align='left' style='width:32%'>
               <input name='edMail' type='text' value='' readonly='readonly' id='edMail' class='editd' style='font-size:11px;height:20px;width:100%;' />
            </td>
          </tr>
          <tr><td colspan='5' style='height:3px'>
              
              </td></tr>          
          </table>                   
      
		</div>


</div>
";
?>
</body>
