<?php

/**
 * @version 1.0.0
 * @package WickedTeamSepaexport
 * @author  Heinl Christian <heinchrs@gmail.com>
 * @copyright  (C) 2015-2025 Heinl Christian
 * @license GNU General Public License version 2 or later
 */

// -- No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

$document = Factory::getDocument();

// Prüfen ob Komponente WickedTeam installiert ist
if (JComponentHelper::isEnabled('com_wickedteam', true))
{
	// Include the helper-php
	require_once dirname(__FILE__) . DS . 'helper.php';

	// Check if the module parameters are set correctly and report errors if not
	if (!WickedTeamSepaexportHelper::checkModuleParams($params))
	{
		return;
	}

	// If form is submitted via POST method, process the SEPA export
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_sepa']))
	{
		$executionDate = $_POST['execution_date'] ?? null;
		$purpose = $_POST['purpose'] ?? '';
		$paramDebug = (int) $params->get('debug');

		// Check if execution date has a valid format
		if ($executionDate)
		{
			$executionDate = date('Y-m-d', strtotime($executionDate));

			// Validate the date format
			if (!$executionDate || $executionDate === '1970-01-01')
			{
				JFactory::getApplication()->enqueueMessage(Text::_('MOD_WICKEDTEAM_SEPAEXPORT_ERROR_EXEC_DATE_NOT_SET'), 'error');

				return;
			}

			// Set the club parameter for the SEPA export from the module parameters
			$club['name'] = $params->get('club_name');
			$club['iban'] = $params->get('iban');
			$club['bic'] = $params->get('bic');
			$club['creditor_id'] = $params->get('creditor_id');
			$club['execution_date'] = $executionDate;
			$club['purpose'] = $purpose;


			// Get the WickedTeam groups id's and the assigned fee from the module parameters
			$groupFees = $params->get('group_fee_mapping', array());
			$feeMap = array();

			// Map group fees to group IDs
			foreach ($groupFees as $entry)
			{
				$element = $entry->groupfee;

				if (!empty($element->fee_group_id))
				{
					$groupid = (int) $element->fee_group_id;
					$feevalue = (float) $element->fee_group_value ?? 0.0;

					// Ensure fee_group_value is set
					$feeMap[$groupid] = $feevalue;
				}
			}

			// Assign group fee mapping to the club array
			// This will be used in the SEPA XML generation to determine which fees to export
			$club['group_fee_mapping'] = $feeMap;

			// Create the file path for the SEPA XML file
			$tmpPath = JPATH_ROOT . '/tmp';
			$fileName = 'sepa_export_' . date('Ymd_His') . '.xml';
			$filePath = $tmpPath . '/' . $fileName;

			// Generate the SEPA XML file
			$success = WickedTeamSepaexportHelper::generateXml($club, $filePath, $params);

			if ($paramDebug)
			{
				$debugFilePath = __DIR__ . '/debug.txt';

				// Check if the debug file exists
				if (file_exists($debugFilePath))
				{
    				// Get the absolute URL to the debug file
					// Use Uri::root(true) to get the absolute URL
					// This will create a link like: /joomla/modules/mod_wickedteam_sepaexport/debug.txt
    				$uri = \Joomla\CMS\Uri\Uri::root(true);
    				$debugFileUrl = $uri . '/modules/mod_wickedteam_sepaexport/debug.txt';
				}
				else
				{
					$debugFileUrl = '';
				}
			}

			if ($success)
			{
				/*
				 * Set the download URL
				 * Use Uri::root(true) to get the absolute URL
				 * and append the download.php script with the file parameter
				 * Ensure the file name is URL-encoded
				 * This will create a link like: /modules/mod_wickedteam_sepaexport/download.php?file=sepa_export_20231001_123456.xml
				 */
				$downloadUrl = Uri::root(true) . '/modules/mod_wickedteam_sepaexport/download.php?file=' . urlencode($fileName);

				// Show success message with download link
				?>
				<!-- Use Joomla's alert component to show the success message -->
				<joomla-alert type="info" dismiss="true" close-text="<?php echo Text::_('JCLOSE'); ?>" role="alert">
					<div class="alert-heading"><span class="info"></span></div>
					<div class="alert-wrapper">
						<div class="alert-message">
							<p><?php echo Text::_('MOD_WICKEDTEAM_SEPAEXPORT_GENERATION_MSG'); ?></p>
							<p><a href="<?php echo htmlspecialchars($downloadUrl); ?>" target="_blank">
								<button class="sepa-download-button"><?php echo Text::_('MOD_WICKEDTEAM_SEPAEXPORT_DOWNLOAD_LINK'); ?></button>
								</a>
							</p>
							<?php if ($paramDebug) : ?>
								<p><a href="<?php echo htmlspecialchars($debugFileUrl); ?>" target="_blank" class="btn btn-primary btn-lg d-block mt-3" download>
									<span class="icon-download" aria-hidden="true"></span> Debug file
									</a>
								</p>
							<?php endif; ?>
						</div>
					</div>
				</joomla-alert>
				<?php
			}
			else
			{
				// Show error message
				?>
				<!-- Use Joomla's alert component to show the error message -->
				<joomla-alert type="danger" dismiss="true" close-text="<?php echo Text::_('JCLOSE'); ?>" role="alert">
					<div class="alert-heading"><span class="danger"></span></div>
					<div class="alert-wrapper">
						<div class="alert-message">
							<p><?php echo Text::_('MOD_WICKEDTEAM_SEPAEXPORT_ERROR_SEPA_FILE_NOT_CREATED'); ?></p>

							<?php if ($paramDebug) : ?>
								<p><a href="<?php echo htmlspecialchars($debugFileUrl); ?>" target="_blank" class="btn btn-primary btn-lg d-block mt-3" download>
									<span class="icon-download" aria-hidden="true"></span> Debug file
									</a>
								</p>
							<?php endif; ?>
						</div>
					</div>
				</joomla-alert>
				<?php
			}
		}
	}

	// Show formular data
	require JModuleHelper::getLayoutPath('mod_wickedteam_sepaexport');

	// Include CSS
	if ($params->get('load_css') == 1)
	{
		JHTML::_('stylesheet', 'media/mod_wickedteam_sepaexport/css/mod_wickedteam_sepaexport.css');
	}
}
else
{
	// Report error if WickedTeam component is not found
	JFactory::getApplication()->enqueueMessage(Text::_('MOD_WICKEDTEAM_SEPAEXPORT_ERROR_WICKEDTEAM_NOT_FOUND'), 'error');
}
