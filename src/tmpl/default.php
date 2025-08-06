<?php

/**
 * @version    1.0.0
 * @package    WickedTeamSepaexport
 * @author     Heinl Christian <heinchrs@gmail.com>
 * @copyright  (C) 2015-2025 Heinl Christian
 * @license    GNU General Public License version 2 or later
 */

// -- No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Helper\ModuleHelper;

// Vereinsname aus Parameter holen
$clubName = $params->get('club_name', '');

// Dynamischer Defaultwert
$defaultPurpose = Text::_('MOD_WICKEDTEAM_SEPAEXPORT_PURPOSE') . ' ' . date('Y') . ' ' . $clubName;
?>



<!-- Beginn WickedTeam Sepaexport -->
<div class="sepa-box">
	<h2><?php echo $module->title; ?></h2>
	<form method="post" class="sepa-form">
		<label for="execution_date"><?php echo Text::_('MOD_WICKEDTEAM_SEPAEXPORT_EXEC_LABEL'); ?>:</label>
		<input
			type="date"
			name="execution_date"
			id="execution_date"
			placeholder="<?php echo Text::_('MOD_WICKEDTEAM_SEPAEXPORT_EXEC_PLACEHOLDER'); ?>"
			required
		>

		<label for="purpose"><?php echo Text::_('MOD_WICKEDTEAM_SEPAEXPORT_PURPOSE_LABEL'); ?>:</label>
		<input
			type="text"
			name="purpose"
			id="purpose"
			value="<?php echo htmlspecialchars($defaultPurpose); ?>"
			placeholder="<?php echo Text::_('MOD_WICKEDTEAM_SEPAEXPORT_PURPOSE_PLACEHOLDER'); ?>"
			required
		>
		<button type="submit" name="generate_sepa" class="sepa-button">
			<?php echo Text::_('MOD_WICKEDTEAM_SEPAEXPORT_BUTTON_LABEL'); ?>
		</button>
	</form>
</div>
<!-- Ende WickedTeam Sepaexport -->
