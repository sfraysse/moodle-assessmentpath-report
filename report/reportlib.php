<?php

/* * *************************************************************
 *  This script has been developed for Moodle - http://moodle.org/
 *
 *  You can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
  *
 * ************************************************************* */

// 
// Print functions
// 

// Print report header

function coursereport_assessmentpath_print_header($course, $reportcode) {
	global $PAGE, $OUTPUT;
	$PAGE->set_pagelayout('report');
	$reporttitle = get_string($reportcode, 'assessmentpath');
	$fulltitle = $course->fullname . ': '.$reporttitle;
	$PAGE->set_title($fulltitle);
	$PAGE->set_heading($course->fullname);
	echo $OUTPUT->header();
	return $fulltitle;
}

function coursereport_assessmentpath_print_header_html($course, $reportcode, $bodyid = null, $bodyclass = null) {
	global $CFG;
	$reporttitle = get_string($reportcode, 'assessmentpath');
	$pagetitle = $course->fullname . ': '.$reporttitle;
	$encoding = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
	$body = '<body';
	if (isset($bodyid)) $body .= ' id="'.$bodyid.'"';
	if (isset($bodyclass)) $body .= ' class="'.$bodyclass.'"';
	$body .= '>';
	$cssbegin = '<style type="text/css">';
	$cssend = '</style>';
	$cssstandard = file_get_contents($CFG->dirroot.'/mod/scormlite/report/export.css');
	echo '<html><head>'.$encoding.'<title>'.$pagetitle.'</title>';
	echo $cssbegin.$cssstandard.$cssend;
	echo '</head>'.$body.'<div id="region-main">';
	return $pagetitle;
}

