<?php
/**
 * @package	Payment Pagseguro HikaShop 2.2 / Joomla 3.x
 * @version	1.1.0
 * @author	jobadoo.com.br
 * @copyright	(C) 2006-2013 Jobadoo Webdesign. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><tr>
	<td class="key">
		<label for="data[payment][payment_params][pagseguro_email]">
			Email
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][pagseguro_email]" value="<?php echo @$this->element->payment_params->pagseguro_email; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][pagseguro_token]">
			Token
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][pagseguro_token]" value="<?php echo @$this->element->payment_params->pagseguro_token; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][invalid_status]">
			<?php echo JText::_( 'INVALID_STATUS' ); ?>
		</label>
	</td>
	<td>
		<?php echo $this->data['order_statuses']->display("data[payment][payment_params][invalid_status]",@$this->element->payment_params->invalid_status); ?>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][verified_status]">
			<?php echo JText::_( 'VERIFIED_STATUS' ); ?>
		</label>
	</td>
	<td>
		<?php echo $this->data['order_statuses']->display("data[payment][payment_params][verified_status]",@$this->element->payment_params->verified_status); ?>
	</td>
</tr>
