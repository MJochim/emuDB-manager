<?php

// (c) 2016-2017 Markus Jochim <markusjochim@phonetik.uni-muenchen.de>

// This script should be included and not called directly.
// However, it is no security issue if it is called directly, because it only
// contains functions (thus, no code is executed).

//
// ===================
// Brief documentation
// ===================
//
// This file offers functions for managing a git repository.
// They all work by calling the git command-line tool.
//
// This is designed for and tested against git version 1.8.
//


require_once 'helpers/result_helper.php';

$gitExecutable = '/usr/bin/git';


/**
 * Form a git command line string and and execute it.
 *
 * This is the base function for all other functions in this file. Do not
 * call this function directly.
 *
 * @param $command string The git command to execute (e.g. commit, status, …),
 *                        including the options to that command
 * @param $repoPath string The path to the repository (not to the .git
 *                         directory)
 * @param &$output string[] The output of the command (see exec())
 *                          (pass-by-reference)
 * @param &$result int The return code of the git process
 *                     (pass-by-reference)
 */
function execGit ($command, $repoPath, &$output, &$result) {
	global $gitExecutable;

	$output = array();
	$commandLine = $gitExecutable . ' -C "' . $repoPath . '" ' . $command;

	exec($commandLine, $output, $result);
}

function gitInit ($path) {
	execGit('init', $path, $output, $result);

	if ($result !== 0) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Failed to initialise git repository in database.'
		);
	}

	return positiveResult(null);
}

function gitCommitEverything ($path, $commitMessage) {
	execGit('add --all .', $path, $output, $result);

	if ($result !== 0) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Failed to add files to git repository.'
		);
	}

	execGit('commit --allow-empty --message "' . $commitMessage . '"', $path,
		$output, $result);

	if ($result !== 0) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Failed to commit files to git repository.'
		);
	}

	return positiveResult(null);
}

function gitLog ($path) {
	execGit('log "--pretty=format:%H/%ad/%s" --date=iso', $path, $output,
		$result);

	if ($result !== 0) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Failed to list git commits in database.'
		);
	}

	return positiveResult(
		$output
	);
}

function gitShowRefTags ($path) {
	execGit('show-ref --tags', $path, $output, $result);

	if ($result !== 0 && $result !== 1) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Failed to list git tags in database.'
		);
	}

	return positiveResult(
		$output
	);
}

function gitTag ($path, $tag, $commit) {
	execGit('tag -am "Created by emuDB Manager" ' . $tag . ' ' . $commit,
		$path, $output, $result);

	if ($result !== 0) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Failed to add git tag to database.'
		);
	}

	return positiveResult(
		null
	);
}

function gitArchive ($path, $dbName, $treeish, $filename) {
	execGit(
		'archive --format=zip -o ' . $filename . ' --prefix=' . $dbName .
		'_emuDB/ -0 ' . $treeish,
		$path,
		$output,
		$result
	);

	if ($result !== 0) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Failed to create ZIP file for download.'
		);
	}

	return positiveResult($filename);
}

function gitHeadRevision ($path) {
	execGit('show-ref -s refs/heads/master', $path, $output, $result);

	if ($result !== 0) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Failed to list git commits in database.'
		);
	}

	return positiveResult(
		$output[0]
	);
}

/**
 * Try copying changes from $srcDir to $targetDir by doing a fast-forward
 * pull in $targetDir.
 *
 * @param $srcDir
 * @param $targetDir
 * @return Result An object to indicate failure or success.
 */
function gitFastForwardPull ($srcDir, $targetDir) {
	execGit('pull --ff-only "' . $srcDir . '"', $targetDir, $output, $result);

	if ($result !== 0) {
		return negativeResult(
			'E_INTERNAL_SERVER_ERROR',
			'Failed to pull changes into the target repository.'
		);
	}

	return positiveResult(null);
}
