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
$format  = optional_param('format', 'lms', PARAM_ALPHA);  // 'lms', 'csv', 'html', 'xls'
$groupingid = optional_param('groupingid', null, PARAM_INT);

// Useful objects and vars
$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);

//
// Page setup 
//

// Permissions
$context = context_course::instance($courseid, MUST_EXIST);
require_login($course);
require_capability('mod/scormlite:viewotherreport', $context);

// Page URL
$url = new moodle_url('/course/report/assessmentpath/report/P2.php', array('courseid'=>$courseid, 'groupingid'=>$groupingid));
if ($format == 'lms') $PAGE->set_url($url);

//
// Print the page
//

// Print HTML title
if ($format == 'lms') $title = coursereport_assessmentpath_print_header($course, 'P2');
else if ($format == 'html') $title = coursereport_assessmentpath_print_header_html($course, 'P2', null, 'path-course-report-assessmentpath-report');
else $title = '';

//
// Groupings
//

$groupings = scormlite_report_get_course_groupings($courseid, 'assessmentpath');
$strtitle = '';
$prestr1 = '<h2 class="main">'.$title.'</h2><h3 class="mdl-align group">';	
$prestr2 = '<h2 class="main">'.$title.'</h2><h3 class="mdl-align group">'.get_string('groupresults', 'scormlite', '');	
$poststr = '</h3>';
if ($format == 'lms' || $format == 'html') {
	$groupingid = scormlite_print_usergroup_box($courseid, $groupings, $groupingid, null, null, null, $strtitle, $prestr1, $prestr2, $poststr, ($format == 'lms'), ($format != 'csv'));
}
$url = new moodle_url('/course/report/assessmentpath/report/P2.php', array('courseid'=>$courseid, 'groupingid'=>$groupingid)); // Update

//
// Prepare Excel 
//

$grouping = $DB->get_record('groups', array('id'=>$groupingid), 'id,name', MUST_EXIST);
$titles = array();
$titles[] = get_string('groupresults_nostyle', 'scormlite', $grouping->name);
$titles[] = $course->fullname;

//
// Fetch data
//

// Start workbook
$workbook = new assessmentpath_workbook($format, 'P2');

// Data
$activities = array();
$users = array();
$scoids = assessmentpath_report_populate_activities($activities, $courseid, $groupingid);
$activities = assessmentpath_report_sort_course_activities($activities, $courseid);
$userids = scormlite_report_populate_users($users, $courseid, $groupingid);
if (empty($activities) || empty($users)) {
	echo '<p>'.get_string('noreportdata', 'scormlite').'</p>';
} else {
	$global_avg = assessmentpath_report_populate_course_results($activities, $users, $scoids, $userids);
	
	//
	// Build worksheet
	//

	// Start worksheet
	$worksheet = $workbook->add_worksheet('', $titles, null, count($activities)+2);
	
	// Build table

	// Cols	
	if ($format == 'lms') $cols = array('picture', 'fullname', $activities, 'avg');
	else $cols = array('fullname', $activities, 'avg');
	// Rank
	$config = get_config('assessmentpath');
	$displayrank = $config->displayrank;
	if ($displayrank) $cols[] = 'rank';
	// Table
	$table = new assessmentpath_report_table($courseid, $groupingid, $cols, $url);
	$table->define_presentation(scormlite_get_config_colors("assessmentpath"));
	$table->add_users($users, ($format == 'lms'));
	$table->add_average($activities, $global_avg);
	$worksheet->add_table($table);
		
	// Comments
	$commentform = new assessmentpath_comment_form();
	$content = $commentform->start($url, ($format == 'lms'));
	$content .= $commentform->addcomment($format, get_string("comments", "assessmentpath"), COMMENT_CONTEXT_GROUP_COURSE, $courseid, $groupingid);
	$content .= $commentform->finish();
	$worksheet->add_post_worksheet($content);
			
	// Display worksheet
	$worksheet->display();
	
	// Export buttons
	if ($format == 'lms') {
		
	}
	// Export buttons
	if ($format == 'lms') {
		// Export activities
		$activityids = array_keys($activities);
		$cmids = array();
		foreach($activityids as $activityid) {
			$tmpcm = get_coursemodule_from_instance('assessmentpath', $activityid, 0, false, MUST_EXIST);
			$cmids[] = $tmpcm->id;
		}
		$paramcmids = implode(',', $cmids);
		$P3url = new moodle_url($CFG->wwwroot.'/mod/assessmentpath/report/P3.php', array('id'=>$paramcmids, 'groupingid'=>$groupingid));
		// Export users
		$paramuserids = implode(',', $userids);
		$P1url = new moodle_url($CFG->wwwroot.'/course/report/assessmentpath/report/P1.php', array('courseid'=>$courseid, 'userid'=>$paramuserids, 'groupingid'=>$groupingid));
		scormlite_print_exportbuttons(array('html'=>$url, 'csv'=>$url, 'xls'=>$url, 'P3'=>$P3url, 'P1'=>$P1url));
	}
}

// Close workbook
$workbook->close();

//
// Print footer
//

if ($format == 'lms') echo $OUTPUT->footer();
else if ($format == 'html') scormlite_print_footer_html();

?>
