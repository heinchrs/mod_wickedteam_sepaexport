<?php
/**
 * @version 1.0.0
 * @package WickedTeamSepaexport
 * @author  Heinl Christian <heinchrs@gmail.com>
 * @copyright  (C) 2015-2025 Heinl Christian
 * @license GNU General Public License version 2 or later
 */

// Safety check to ensure this file is being accessed through Joomla
$file = isset($_GET['file']) ? basename($_GET['file']) : '';

/*
 * Allow only specific file names that match the SEPA export pattern
 * The pattern is: sepa_export_YYYYMMDD_HHMMSS.xml
 * Example: sepa_export_20231001_123456.xml
 * YYYYMMDD is the date and HHMMSS is the time of export
 */
if (!preg_match('/^sepa_export_\d{8}_\d{6}\.xml$/', $file))
{
	header('HTTP/1.0 400 Bad Request');
	echo 'Invalid file name. ' . $file;
	exit;
}

// Absolute path to the file in the Joomla tmp directory
$filePath = __DIR__ . '/../../tmp/' . $file;

if (!file_exists($filePath))
{
	header('HTTP/1.0 404 Not Found');
	echo 'File not found. filePath: ' . $filePath;
	exit;
}

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
