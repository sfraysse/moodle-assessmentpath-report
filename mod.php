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

if (!defined('MOODLE_INTERNAL')) {
	die('Direct access to this script is forbidden.'); // It must be included from a Moodle page
}

// P1 report
if (has_capability('mod/scormlite:viewmyreport', $context)) {
	echo '<p>';
	echo '<a href="'.$CFG->wwwroot.'/course/report/assessmentpath/report/P1.php?courseid='.$course->id.'">'.get_string('P1','assessmentpath').'</a>';
	echo '</p>';
}
// P2 report
if (has_capability('mod/scormlite:viewotherreport', $context)) {
	echo '<p>';
	echo '<a href="'.$CFG->wwwroot.'/course/report/assessmentpath/report/P2.php?courseid='.$course->id.'">'.get_string('P2','assessmentpath').'</a>';
	echo '</p>';
}





