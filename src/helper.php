<?php

/**
 * @version    1.0.0
 * @package    WickedTeamSepaexport
 * @subpackage Helper
 * @author     Heinl Christian <heinchrs@gmail.com>
 * @copyright  (C) 2015-2025 Heinl Christian
 * @license    GNU General Public License version 2 or later
 */

// -- No direct access
defined('_JEXEC') or die;

// Import Joomla! libraries
//use Dom\Text;
use Joomla\CMS\Language\Text;

/**
 * Helper class to extract birth dates of the corresponding
 * WickedTeam members.
 *
 * @author  Heinl Christian <heinchrs@gmail.com>
 * @since   1.0
 */
class WickedTeamSepaexportHelper
{
	/**
	 * Generate the XML file for SEPA export.
	 *
	 * @param   array     $club     Club information including bank parameter, group fee mapping, execution date, purpose and creditor ID
	 * @param   string    $filePath Path to the XML file to be generated
	 * @param   type      $params   Module parameters
	 * @return  boolean   True on success, false on failure
	 * @throws  Exception If an error occurs during XML generation
	 * @since   1.0
	 */
	public static function generateXml($club, $filePath, $params): bool
	{
		$paramWickedGroups = $params->get('wickedteam_groups');
		$paramWickedMemberLastname = $params->get('member_lastname');
		$paramWickedMemberFirstname = $params->get('member_firstname');
		$paramWickedMemberIban = $params->get('member_iban');
		$paramWickedMemberBic  = $params->get('member_bic');
		$paramWickedMemberBank = $params->get('member_bank');
		$paramDebug        = (int) $params->get('debug');

		// Set Debug
		$debug = ($paramDebug == 1) ? true : false;

		if ($debug)
		{
			$df = fopen(dirname(__FILE__) . DS . 'debug.txt', 'w');
			fwrite($df, "Start Debugging at " . date('Y-m-d H:i:s') . "\n");
			fwrite($df, "Club: " . print_r($club, true) . "\n");
			fwrite($df, "File Path: " . $filePath . "\n");
			fwrite($df, "WickedTeam Groups: " . print_r($paramWickedGroups, true) . "\n");
		}

		// Open datenbase connection
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);

		// Set SQL_BIG_SELECTS to 1 to avoid errors with large datasets
		$db->setQuery('SET SQL_BIG_SELECTS=1');
		$db->execute();

		$query->select(
			[
				'a.id',
				'c.title AS Status',
				'f.value AS Nachname',
				'h.value AS Vorname',
				'p.value AS IBAN',
				'q.value AS BIC',
				's.value AS Bank',
				"GROUP_CONCAT(DISTINCT t.catid SEPARATOR ', ') AS Kategorie",
			]
		);

		// Generate the WHERE clause based on the selected WickedTeam groups separated by ' OR '
		// e.g. 'm.catid = 1 OR m.catid = 2 OR m.catid = 3'
		$categorySelection = implode(' OR ',
			array_map(
				function ($id) {
					return 'm.catid = ' . (int) $id;
				}, $paramWickedGroups
			)
		);

		// Set the FROM clause and JOINs
		$query->from('#__wickedteam_members as a');
		$query->leftjoin('#__wickedteam_member_category AS m ON ((m.member_id = a.id) AND (' . $categorySelection . '))');  // Aktiv oder Passiv
		$query->leftjoin('#__fields_values as f on f.item_id=a.id and f.field_id = ' . $paramWickedMemberLastname);         // Nachname
		$query->leftjoin('#__fields_values as h on h.item_id=a.id and h.field_id = ' . $paramWickedMemberFirstname);        // Vorname
		$query->leftjoin('#__fields_values as p ON p.item_id=a.id and p.field_id = ' . $paramWickedMemberIban);             // IBAN
		$query->leftjoin('#__fields_values as q ON q.item_id=a.id and q.field_id = ' . $paramWickedMemberBic);              // BIC
		$query->leftjoin('#__fields_values as s ON s.item_id=a.id and s.field_id = ' . $paramWickedMemberBank);             // Bank
		$query->leftjoin('#__wickedteam_member_category AS t ON t.member_id = a.id');                                       // Kategorien
		$query->leftjoin('#__categories AS c ON c.id = m.catid');

		$query->where('a.published = 1');
		$query->group('f.item_id');
		$db->setQuery($query);
		$result = $db->loadAssoclist();

		// Debug:
		if ($debug)
		{
			fwrite($df, "### SQL Query ##########\n");
			fwrite($df, $query->dump() . "\n");
			fwrite($df, "### Result ##########\n");
			fwrite($df, "Count: " . count($result) . "\n");

			fwrite($df, "\n### MEMBERS ##########\n");
			fwrite($df, "Database Result: " . print_r($result, true) . "\n");
		}

		// Alter ermitteln, Daten aussortieren, Arrayeinträge definieren
		foreach ($result as $mitglied)
		{
			// Check if the member's category is in the group fee mapping
			$groupIds    = explode(',', $mitglied['Kategorie']);
			$hasGroupFee = false;
			$groupFee    = 0.0;

			foreach ($groupIds as $groupId)
			{
				if (isset($club['group_fee_mapping'][(int) $groupId]))
				{
					$hasGroupFee = true;
					$groupFee    = $club['group_fee_mapping'][(int) $groupId];

					// If a group fee is found, use it
					$mitglied['betrag'] = $groupFee;
					break;
				}
			}

			if (! $hasGroupFee)
			{
				// Report error
				JFactory::getApplication()->enqueueMessage(Text::sprintf(
					'MOD_WICKEDTEAM_SEPAEXPORT_WARNING_NO_FEE_GROUP_ASSIGNED',
					$mitglied['Nachname'],
					$mitglied['Vorname']),
					'warning'
				);

				// Skip members without a group fee mapping
				continue;
			}

			// Validate IBAN
			if (self::isValidIban($mitglied['IBAN']) === false)
			{
				JFactory::getApplication()->enqueueMessage(
					Text::sprintf('MOD_WICKEDTEAM_SEPAEXPORT_WARNING_INVALID_IBAN_ASSIGNED', $mitglied['Nachname'] . ' ' . $mitglied['Vorname'], $mitglied['IBAN']),
					'warning'
				);
				// Skip members without invalid IBAN
				continue;
			}

			// Validate BIC
			if(! preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $mitglied['BIC']))
			{
				JFactory::getApplication()->enqueueMessage(
					Text::sprintf('MOD_WICKEDTEAM_SEPAEXPORT_WARNING_INVALID_BIC_ASSIGNED', $mitglied['Nachname'] . ' ' . $mitglied['Vorname'], $mitglied['BIC']),
					'warning'
				);

				// Skip members without invalid BIC
				continue;
			}

			$mitglieder[] =
			[
				'name'               => $mitglied['Nachname'] . ' ' . $mitglied['Vorname'],
				'iban'               => $mitglied['IBAN'],
				'bic'                => $mitglied['BIC'],
				'betrag'             => (float) $mitglied['betrag'], // Betrag
				'verwendungszweck'   => htmlspecialchars($club['purpose'], ENT_XML1, 'UTF-8'),
				'mandatsreferenz'    => $club['creditor_id'],                              // Gläubiger-ID
				'datum_unterschrift' => date('Y-m-d', strtotime($club['execution_date'])), // Ausführungsdatum
			];
		}

		// Debug:
		if ($debug)
		{
			fwrite($df, "### Mitglieder ##########\n");
			fwrite($df, "Count: " . count($mitglieder) . "\n");
			fwrite($df, print_r($mitglieder, true) . "\n");
		}

		try
		{
			$doc               = new DOMDocument('1.0', 'UTF-8');
			$doc->formatOutput = true;

			$root = $doc->createElement('Document');
			$root->setAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
			$root->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.002.02');
			$root->setAttribute('xsi:schemaLocation', 'urn:iso:std:iso:20022:tech:xsd:pain.008.002.02 pain.008.002.02.xsd');

			$doc->appendChild($root);

			$cstmrDrctDbtInitn = $doc->createElement('CstmrDrctDbtInitn');
			$root->appendChild($cstmrDrctDbtInitn);

			$grpHdr = $doc->createElement('GrpHdr');
			$grpHdr->appendChild($doc->createElement('MsgId', 'MSG-' . date('YmdHis')));
			$grpHdr->appendChild($doc->createElement('CreDtTm', date('c')));
			$grpHdr->appendChild($doc->createElement('NbOfTxs', count((array) $mitglieder)));
			$totalAmount = array_sum(array_column((array) $mitglieder, 'betrag'));
			$grpHdr->appendChild($doc->createElement('CtrlSum', number_format($totalAmount, 2, '.', '')));

			$initgPty = $doc->createElement('InitgPty');
			$initgPty->appendChild($doc->createElement('Nm', $club['name']));
			$grpHdr->appendChild($initgPty);
			$cstmrDrctDbtInitn->appendChild($grpHdr);

			$pmtInf = $doc->createElement('PmtInf');
			$pmtInf->appendChild($doc->createElement('PmtInfId', 'PMT-' . date('Ymd')));
			$pmtInf->appendChild($doc->createElement('PmtMtd', 'DD'));
			$pmtInf->appendChild($doc->createElement('BtchBookg', 'true'));
			$pmtInf->appendChild($doc->createElement('NbOfTxs', count((array) $mitglieder)));
			$pmtInf->appendChild($doc->createElement('CtrlSum', number_format($totalAmount, 2, '.', '')));

			$pmtTpInf = $doc->createElement('PmtTpInf');
			$svcLvl   = $doc->createElement('SvcLvl');
			$svcLvl->appendChild($doc->createElement('Cd', 'SEPA'));
			$pmtTpInf->appendChild($svcLvl);
			$pmtTpInf->appendChild($doc->createElement('SeqTp', 'RCUR'));
			$pmtInf->appendChild($pmtTpInf);

			$pmtInf->appendChild($doc->createElement('ReqdColltnDt', $club['execution_date']));
			$cdtr = $doc->createElement('Cdtr');
			$cdtr->appendChild($doc->createElement('Nm', $club['name']));
			$pmtInf->appendChild($cdtr);

			$cdtrAcct = $doc->createElement('CdtrAcct');
			$id       = $doc->createElement('Id');
			$id->appendChild($doc->createElement('IBAN', trim($club['iban'])));
			$cdtrAcct->appendChild($id);
			$pmtInf->appendChild($cdtrAcct);

			$cdtrAgt    = $doc->createElement('CdtrAgt');
			$finInstnId = $doc->createElement('FinInstnId');
			$finInstnId->appendChild($doc->createElement('BIC', trim($club['bic'])));
			$cdtrAgt->appendChild($finInstnId);
			$pmtInf->appendChild($cdtrAgt);

			$pmtInf->appendChild($doc->createElement('ChrgBr', 'SLEV'));

			// Add creditor ID and scheme name
			$cdtrSchmeId = $doc->createElement('CdtrSchmeId');
			$id          = $doc->createElement('Id');
			$orgId       = $doc->createElement('PrvtId');
			$othr        = $doc->createElement('Othr');
			$othr->appendChild($doc->createElement('Id', trim($club['creditor_id'])));
			$schmeNm = $doc->createElement('SchmeNm');
			$schmeNm->appendChild($doc->createElement('Prtry', 'SEPA'));
			$othr->appendChild($schmeNm);
			$orgId->appendChild($othr);
			$id->appendChild($orgId);
			$cdtrSchmeId->appendChild($id);
			$pmtInf->appendChild($cdtrSchmeId);

			foreach ($mitglieder as $mitglied)
			{
				$tx    = $doc->createElement('DrctDbtTxInf');
				$pmtId = $doc->createElement('PmtId');
				$pmtId->appendChild($doc->createElement('EndToEndId', 'E2E-' . uniqid()));
				$tx->appendChild($pmtId);

				$instdAmt = $doc->createElement('InstdAmt', number_format($mitglied['betrag'], 2, '.', ''));
				$instdAmt->setAttribute('Ccy', 'EUR');
				$tx->appendChild($instdAmt);

				$drctDbtTx = $doc->createElement('DrctDbtTx');
				$mndt      = $doc->createElement('MndtRltdInf');
				$mndt->appendChild($doc->createElement('MndtId', trim($mitglied['mandatsreferenz'])));
				$mndt->appendChild($doc->createElement('DtOfSgntr', $mitglied['datum_unterschrift']));
				$drctDbtTx->appendChild($mndt);
				$tx->appendChild($drctDbtTx);

				$dbtrAgt    = $doc->createElement('DbtrAgt');
				$finInstnId = $doc->createElement('FinInstnId');
				$finInstnId->appendChild($doc->createElement('BIC', trim($mitglied['bic'])));
				$dbtrAgt->appendChild($finInstnId);
				$tx->appendChild($dbtrAgt);

				$dbtr = $doc->createElement('Dbtr');
				$dbtr->appendChild($doc->createElement('Nm', $mitglied['name']));
				$tx->appendChild($dbtr);

				$dbtrAcct = $doc->createElement('DbtrAcct');
				$id       = $doc->createElement('Id');
				$id->appendChild($doc->createElement('IBAN', trim($mitglied['iban'])));
				$dbtrAcct->appendChild($id);
				$tx->appendChild($dbtrAcct);

				$rmtInf = $doc->createElement('RmtInf');
				$rmtInf->appendChild($doc->createElement('Ustrd', $mitglied['verwendungszweck']));
				$tx->appendChild($rmtInf);

				$pmtInf->appendChild($tx);
			}

			$cstmrDrctDbtInitn->appendChild($pmtInf);

			// Close Debugfile
			if ($debug)
			{
				fwrite($df, "### XML Document ##########\n");
				fwrite($df, $doc->saveXML() . "\n");
				fwrite($df, "End Debugging at " . date('Y-m-d H:i:s') . "\n");
				fwrite($df, "############################\n");
				fclose($df);
			}

			// Save XML to file
			return $doc->save($filePath);
		}
		catch (Exception $e)
		{
			// Close Debugfile
			if ($debug)
			{
				fwrite($df, "### Error ##########\n");
				fwrite($df, "Error Message: " . $e->getMessage() . "\n");
				fwrite($df, "End Debugging at " . date('Y-m-d H:i:s') . "\n");
				fwrite($df, "############################\n");
				fclose($df);
			}

			return false;
		}
	}

	/**
	 * Check if the given IBAN is valid.
	 * @param   string  $iban  The IBAN to validate
	 * @return  boolean        True if valid, false otherwise
	 * @since   1.0
	 */
	public static function isValidIban($iban): bool
	{
		// Remove spaces and make uppercase
		$iban = strtoupper(str_replace(' ', '', $iban));

		// Basic format check (only letters and digits)
		if (! preg_match('/^[A-Z0-9]+$/', $iban))
		{
			return false;
		}

		// IBAN must be between 15 and 34 characters (depending on the country)
		$length = strlen($iban);

		if ($length < 15 || $length > 34)
		{
			return false;
		}

		// Move the first four characters to the end of the string
		$rearranged = substr($iban, 4) . substr($iban, 0, 4);

		// Replace each letter with two digits (A=10, B=11, ..., Z=35)
		$converted = '';

		foreach (str_split($rearranged) as $char)
		{
			if (ctype_alpha($char))
			{
				$converted .= ord($char) - 55;
			}
			else
			{
				$converted .= $char;
			}
		}

		// Perform mod-97 using bcmod for large numbers
		$remainder = '';

		foreach (str_split($converted, 7) as $part)
		{
			$remainder = bcmod($remainder . $part, 97);
		}

		// IBAN is valid if modulo 97 == 1
		return $remainder == 1;
	}

	/**
	 * Check if the module parameters are set correctly.
	 *
	 * @param   object  $params  Module parameters
	 * @return  boolean          True if parameters are valid, false otherwise
	 * @since   1.0
	 */
	public static function checkModuleParams($params): bool
	{
		/*
		 *Check if the creditor ID is set and valid
		 * The creditor ID must be in the format: DEkkZZZ12345678901234567890
		 * where 'DE' is the country code, 'kk' is the check digits, 'ZZZ' is a fixed part and the rest is the creditor's identifier
		 * The creditor ID must be between 15 and 34 characters long
		 * It should only contain uppercase letters and digits
		 * Example: DEkkZZZ12345678901234567890
		 **/
		if (empty(trim($params->get('creditor_id'))) || ! preg_match('/^[A-Z]{2}[0-9]{2}ZZZ[0-9A-Z]{1,28}$/', $params->get('creditor_id'))) {
			JFactory::getApplication()->enqueueMessage(Text::_('MOD_WICKEDTEAM_SEPAEXPORT_ERROR_NO_CREDITOR_ID'), 'error');

			return false;
		}

		// Check if the club name is set
		if (empty(trim($params->get('club_name')))) {
			JFactory::getApplication()->enqueueMessage(Text::_('MOD_WICKEDTEAM_SEPAEXPORT_ERROR_NO_CLUB_NAME'), 'error');

			return false;
		}

		// Check if the IBAN is set and valid
		if (empty(trim($params->get('iban'))) || ! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $params->get('iban')) || ! self::isValidIban($params->get('iban'))) {
			JFactory::getApplication()->enqueueMessage(Text::_('MOD_WICKEDTEAM_SEPAEXPORT_ERROR_NO_IBAN'), 'error');

			return false;
		}

		// Check if the BIC is set and valid
		if (empty(trim($params->get('bic'))) || ! preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $params->get('bic'))) {
			JFactory::getApplication()->enqueueMessage(Text::_('MOD_WICKEDTEAM_SEPAEXPORT_ERROR_NO_BIC'), 'error');

			return false;
		}

		// Check if WickedTeam groups are selected in the module parameters
		// By this groups the membmers are selected which should be exported
		if (empty($params->get('wickedteam_groups'))) {
			JFactory::getApplication()->enqueueMessage(Text::_('MOD_WICKEDTEAM_SEPAEXPORT_ERROR_NO_GROUPS_SELECTED'), 'error');

			return false;
		}

		// Check if the group fee mapping is set
		if (empty($params->get('group_fee_mapping'))) {
			JFactory::getApplication()->enqueueMessage(Text::_('MOD_WICKEDTEAM_SEPAEXPORT_ERROR_NO_FEE_GROUP_SELECTED'), 'warning');

			return false;
		}

		return true;
	}

}
