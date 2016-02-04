<?php
/**
 * @package	Payment Pagseguro HikaShop 2.2 / Joomla 3.x
 * @version	1.1.0
 * @author	jobadoo.com.br
 * @copyright	(C) 2006-2013 Jobadoo Webdesign. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><div class="hikashop_pagseguro_end" id="hikashop_pagseguro_end">
	<span id="hikashop_pagseguro_end_message" class="hikashop_pagseguro_end_message">
		<?php echo JText::sprintf('PLEASE_WAIT_BEFORE_REDIRECTION_TO_X',$this->payment_name).'<br/>'. JText::_('CLICK_ON_BUTTON_IF_NOT_REDIRECTED');?>
	</span>
	<span id="hikashop_pagseguro_end_spinner" class="hikashop_pagseguro_end_spinner">
		<img src="<?php echo HIKASHOP_IMAGES.'spinner.gif';?>" />
	</span>
	<br/>
	<form id="hikashop_pagseguro_form" name="hikashop_pagseguro_form" action="<?php echo $this->payment_params->url;?>" method="post">
		<div id="hikashop_pagseguro_end_image" class="hikashop_pagseguro_end_image">
			<input id="hikashop_pagseguro_button" class="btn btn-primary" type="submit" value="<?php echo JText::_('PAY_NOW');?>" name="" alt="<?php echo JText::_('PAY_NOW');?>" />
		</div>
		<?php
			$doc =& JFactory::getDocument();
			$doc->addScriptDeclaration("window.hikashop.ready( function() {document.getElementById('hikashop_pagseguro_form').submit();});");
			JRequest::setVar('noform',1);
		?>
	</form>
</div>
