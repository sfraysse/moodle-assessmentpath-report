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

// Includes
require_once('../../../../config.php');
require_once($CFG->dirroot.'/course/report/assessmentpath/report/reportlib.php');
require_once($CFG->dirroot.'/mod/scormlite/report/reportlib.php');
require_once($CFG->dirroot.'/mod/assessmentpath/report/reportlib.php');

// Params
$courseid = required_param('courseid', PARAM_INT);
$userids = optional_param('userid', $USER->id, PARAM_TEXT);
$format  = optional_param('format', 'lms', PARAM_ALPHA);  // 'lms', 'csv', 'html', 'xls'
$groupingid = optional_param('groupingid', null, PARAM_INT);

// User List
$userids = explode(',', $userids);
$userid = $userids[0];

// Useful objects and vars
$course = $DB->get_record('course',array('id'=>$courseid), '*', MUST_EXIST);


//
// Page setup 
//

// Permissions
$context = context_course::instance($courseid, MUST_EXIST);
require_login($course);
require_capability('mod/scormlite:viewmyreport', $context);

if ($format == 'lms' || $format == 'html') {
	if ($USER->id == $userid) {
		$user = $USER;
		$fullmode = has_capability('mod/scormlite:viewotherreport', $context);
	} else {
		require_capability('mod/scormlite:viewotherreport', $context);
		$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
		$fullmode = true;
	}
} else {
	$fullmode = false;
}

// Page

$url = new moodle_url('/course/report/assessmentpath/report/P1.php', array('courseid'=>$courseid, 'userid'=>$userid, 'groupingid'=>$groupingid));
if ($format == 'lms') $PAGE->set_url($url);


//
// Print the page
//

// Print HTML title
if ($format == 'lms') $title = coursereport_assessmentpath_print_header($course, 'P1');
else if ($format == 'html') $title = coursereport_assessmentpath_print_header_html($course, 'P1', null, 'path-course-report-assessmentpath-report');

//
// Groupings
//

$groupings = scormlite_report_get_user_groupings($courseid, $userid, 'assessmentpath');
if (count($groupings) == 1) { // Do it for the link
	$keys = array_keys($groupings);
	$groupingid = $keys[0];
}
if ($format == 'lms' || $format == 'html') {
	$strtitle = '<h2 class="mdl-align user">'.get_string('learnerresults', 'scormlite', fullname($user)).'</h2>';
	$prestr1 = '<h3 class="mdl-align group">';	
	$prestr2 = '<h3 class="mdl-align group">'.get_string('groupresults', 'scormlite', '');	
	if ($fullmode && $format == 'lms') $poststr = ' '.assessmentpath_report_get_link_P2($courseid, $groupingid).'</h3>';
	else $poststr = '</h3>';
	$groupingid = scormlite_print_usergroup_box($courseid, $groupings, $groupingid, null, null, $userid, $strtitle, $prestr1, $prestr2, $poststr, ($format == 'lms'), ($format != 'csv'));
}
$url = new moodle_url('/course/report/assessmentpath/report/P1.php', array('courseid'=>$courseid, 'userid'=>$userid, 'groupingid'=>$groupingid));  // Update

//
// Print the page
//

// Prepare Excel title

// KD2015-31 - End of "group members only" option
// $grouping = $DB->get_record('groupings', array('id'=>$groupingid), 'id,name', MUST_EXIST);
$grouping = $DB->get_record('groups', array('id'=>$groupingid), 'id,name', MUST_EXIST);

$titles = array();
$titles[] = get_string('groupresults_nostyle', 'scormlite', $grouping->name);
$titles[] = $course->fullname;

//
// Fetch data
//

// Start workbook
$sheetindex = 1;
$workbook = new assessmentpath_workbook($format, 'P1');
foreach ($userids as $userid) {
	$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
	$sheettitles = $titles;
	$sheettitles[] = fullname($user);

	// Data
	$activities = array();
	$scoids = assessmentpath_report_populate_activities($activities, $courseid, $groupingid);
	$activities = assessmentpath_report_sort_course_activities($activities, $courseid);
	if (empty($activities)) {
		echo '<p>'.get_string('noreportdata', 'scormlite').'</p>';
	} else {
		assessmentpath_report_populate_user_results($activities, $user, $scoids);
		
		//
		// Build worksheet
		//
	
		// Start worksheet
		$colnumber = 0;
		foreach ($activities as $activityid => $activity) {
			$colnumber = max($colnumber, count($activity->initial_tests));
		}
		$worksheet = $workbook->add_worksheet(strval($sheetindex), $sheettitles, null, $colnumber+2);
		
		// Start comments
		$commentform = new assessmentpath_comment_form();
		$content = $commentform->start($url, ($fullmode && $format == 'lms'));
		$worksheet->add_pre_worksheet($content);
		
		// Loop on activities
		$index = 1;
		foreach ($activities as $activityid => $activity) {
			
			// Start activity
			$activitytitle = '['.$activity->code.'] '.$activity->title;
			if ($fullmode && $format == 'lms') {
				$cm = get_coursemodule_from_instance('assessmentpath', $activityid, 0, false, MUST_EXIST);
				$activitytitle = assessmentpath_report_get_link_P3($cm->id, $groupingid).' '.$activitytitle;
			}
			$worksheet->start_section($activitytitle, $index);
	
			// Build table
			$table = new assessmentpath_report_table($courseid, $groupingid, array('testcaption', $activity->initial_tests, 'avg'), $url, $activity->remediation_tests);
			$table->define_presentation($activity->colors, $fullmode);
			$table->add_tests(get_string('initial', 'assessmentpath'), $activity->initial_scores, $activity->initial_avg, $userid, 0, ($format == 'lms'));
			if (isset($activity->remediation_avg)) {
				$table->add_tests(get_string('remediation', 'assessmentpath'), $activity->remediation_scores, $activity->remediation_avg, $userid, 1, ($format == 'lms'));
			}
			$worksheet->add_table($table, $index);
			
			// Path comments
			$content = $commentform->addcomment($format, get_string("pathcomments", "assessmentpath"), COMMENT_CONTEXT_USER_PATH, $activityid, $userid, $index, "comment pathcomment", 'cols="70" rows="3"');
			$worksheet->add_comment($content, $index);
	
			// Next
			$index += 1;
		}
		
		// Course comments
		$content = $commentform->addcomment($format, get_string("coursecomments", "assessmentpath"), COMMENT_CONTEXT_USER_COURSE, $courseid, $userid, null, "comment coursecomment");
		$content .= $commentform->finish();
		$worksheet->add_post_worksheet($content);
	
		// Display worksheet
		$worksheet->display();
		
		// Export buttons
		if ($format == 'lms') {
			scormlite_print_exportbuttons(array('html'=>$url, 'xls'=>$url));
		}
	}
	$sheetindex++;
}

// Close workbook
$workbook->close();

//
// Print footer
//

if ($format == 'lms') echo $OUTPUT->footer();
else if ($format == 'html') scormlite_print_footer_html();

?>
