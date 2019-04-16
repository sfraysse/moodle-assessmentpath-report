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

require_once($CFG->libdir.'/completionlib.php');

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
 
function assessmentpath_report_extend_navigation($navigation, $course, $context) {
	global $CFG, $OUTPUT;
	// P1
	if (has_capability('mod/scormlite:viewmyreport', $context)) {
		$url = new moodle_url('/course/report/assessmentpath/report/P1.php', array('courseid'=>$course->id));
		$navigation->add(get_string('P1','assessmentpath'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
	}
	// P2
	if (has_capability('mod/scormlite:viewotherreport', $context)) {
		$url = new moodle_url('/course/report/assessmentpath/report/P2.php', array('courseid'=>$course->id));
		$navigation->add(get_string('P2','assessmentpath'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
	}
}
 
/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function assessmentpath_report_page_type_list($pagetype, $parentcontext, $currentcontext) {
	$array = array(
		'*' => get_string('page-x', 'pagetype'),
		'course-report-*' => get_string('page-course-report-x', 'pagetype'),
		'course-report-assessmentpath-index' => get_string('pluginpagetype', 'coursereport_assessmentpath')
	);
	return $array;
}
