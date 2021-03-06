<?php

// (c) 2016 Markus Jochim <markusjochim@phonetik.uni-muenchen.de>
// Based on https://github.com/jkuri/ng2-uploader

// This script should be included and not called directly.
// However, it is no security issue if it is called directly, because it only
// contains functions (thus, no code is executed).

require_once 'helpers/result_helper.php';
require_once 'helpers/uuid.php';
require_once 'helpers/validate.php';

/**
 * Save an uploaded file (with the simple key 'file') to a uniquely named
 * directory within the project dir. In case of success, a Result object with
 * the data field set to the unique identifier is returned.
 *
 * @param $projectDir string The project directory for which the client has
 *        been authorized.
 * @return Result An object with 'data' set to the generated UUID of the new
 *         upload.
 */
function upload ($projectDir) {
	$uploadUUID = generateUUID();

	$targetPath = $projectDir . '/uploads/' . $uploadUUID;

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return negativeResult(
			'E_REQUEST_METHOD'
		);
	}

	if (!isset($_FILES['file'])) {
		return negativeResult(
			'E_USER_INPUT',
			'UPLOAD'
		);
	}

	$originalName = $_FILES['file']['name'];
	$baseName = basename($originalName, '.zip'); // this splits off .zip if
	// it is there and leaves the basename intact otherwise

	$result = validateUploadFilename($baseName);
	if ($result->success !== true) {
		return negativeResult(
			'E_USER_INPUT',
			'FILE_NAME'
		);
	}

	if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'An unknown upload error was indicated (error code '
			. $_FILES['file']['error'] . ')'
		);
	}

	if (!mkdir($targetPath)) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Creating the directory for the upload failed.'
		);
	}

	$targetName = $targetPath . '/' . $originalName;
	$result = move_uploaded_file($_FILES['file']['tmp_name'], $targetName);

	if ($result !== true) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Saving the uploaded file failed.'
		);
	}

	// Create data directory to unzip the uploaded file
	$dataPath = $targetPath . '/data';

	if (!mkdir($dataPath)) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Creating the unzip directory for the upload failed.'
		);
	}

	// Unzip uploaded file
	$zip = new ZipArchive();
	$res = $zip->open($targetName);

	if ($res !== true) {
		return negativeResult(
			'E_UPLOAD',
			'UNZIP'
		);
	}

	// Find emu DB in the zip file
	$databaseName = '';
	for ($i = 0; $i < $zip->numFiles; ++$i) {
		$entry = $zip->getNameIndex($i);
		if (substr($entry, -7) === '_emuDB/') {
			$databaseName = substr($entry, 0, -7);
			break;
		}
	}

	if ($databaseName === '') {
		return negativeResult(
			'E_UPLOAD',
			'NO_DATABASE_IN_ZIP'
		);
	}

	$stat = validateDatabaseName($databaseName);

	if ($stat->success !== true) {
		return negativeResult(
			'E_UPLOAD',
			'INVALID_DB_NAME'
		);
	}

	$res = $zip->extractTo($dataPath);

	if ($res !== true) {
		return negativeResult(
			'E_UPLOAD',
			'UNZIP'
		);
	}

	$zip->close();

	return positiveResult($uploadUUID);
}
