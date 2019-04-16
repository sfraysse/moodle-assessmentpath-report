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
$userid = $USER->id;
$course = $DB->get_record('course',array('id'=>$courseid), '*', MUST_EXIST);
$groupingid = required_param('groupingid', PARAM_INT);

// KD2015-31 - End of "group members only" option
// $grouping = $DB->get_record('groupings',array('id'=>$groupingid), '*', MUST_EXIST);
$grouping = $DB->get_record('groups',array('id'=>$groupingid), '*', MUST_EXIST);

//
// Page setup 
//

// Permissions
$context = context_course::instance($courseid, MUST_EXIST);
require_login($course);
require_capability('mod/scormlite:viewmyreport', $context);
$fullmode = has_capability('mod/scormlite:viewotherreport', $context);
if (!$fullmode) scormlite_check_user_grouping($courseid, $userid, $groupingid, 'assessmentpath');

// Page
$url = new moodle_url('/course/report/assessmentpath/report/P0.php', array('courseid'=>$courseid, 'groupingid'=>$groupingid));
$PAGE->set_url($url);

// Header
coursereport_assessmentpath_print_header($course, 'P0');

//
// Fetch data
//

$activities = array();
$users = array();
$scoids = assessmentpath_report_populate_activities($activities, $courseid, $groupingid);
$activities = assessmentpath_report_sort_course_activities($activities, $courseid);
$userids = scormlite_report_populate_users($users, $courseid, $groupingid);
$statistics = assessmentpath_report_populate_course_progress($activities, $users, $scoids, $userids);

if ($statistics == false) {
	echo '<p>'.get_string('noreportdata', 'scormlite').'</p>';
} else {

	//
	// Progress bar
	//
	$progress = $statistics->progress;
	$progresslabel = sprintf("%01.1f", $progress).'%';
	echo $OUTPUT->box_start('generalbox mdl-align');
	echo '<h3 class="mdl-align">'.get_string('groupprogress', 'scormlite', $grouping->name).'</h3>';
	echo "<table class='progressbar_container'><tr>
			<td class='progressbar'><div class='bar'><div class='progress' style='width:{$progress}%'></div></div></td>
			<td class='percent'>{$progresslabel}</td>
		</tr></table>";
	echo $OUTPUT->box_end();	
	
	//
	// Progress table
	//
	$table = new assessmentpath_progress_table();
	$table->add_activities($activities);
	$table->display('lms_P0');
}

//
// Print footer
//

echo $OUTPUT->footer();

?>
