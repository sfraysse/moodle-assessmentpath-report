<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace coursereport_assessmentpath\privacy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assessmentpath/report/reportlib.php');

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy class for requesting user data.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider
{

    /**
     * Return the fields which contain personal data.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection
    {
        $collection->add_database_table('assessmentpath_comments', [
            'contexttype' => 'privacy:metadata:comments:contexttype',
            'contextid' => 'privacy:metadata:comments:contextid',
            'userid' => 'privacy:metadata:comments:userid',
            'comment' => 'privacy:metadata:comments:comment'
        ], 'privacy:metadata:assessmentpath_comments');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist
    {
        $contextlist = new contextlist();

        // Select from activity level comments

        $sql = "SELECT ctx.id
                  FROM {%s} comment
                  JOIN {modules} m
                    ON m.name = 'assessmentpath'
                  JOIN {course_modules} cm
                    ON cm.instance = comment.contextid
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                   AND ctx.contextlevel = :contextlevel
                 WHERE comment.userid = :userid
                   AND comment.contexttype = :contexttype";

        $params = ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid, 'contexttype' => COMMENT_CONTEXT_USER_PATH];
        $contextlist->add_from_sql(sprintf($sql, 'assessmentpath_comments'), $params);

        // Select from course level comments

        $sql = "SELECT ctx.id
                  FROM {%s} comment
                  JOIN {context} ctx
                    ON ctx.instanceid = comment.contextid
                   AND ctx.contextlevel = :contextlevel
                 WHERE comment.userid = :userid
                   AND comment.contexttype = :contexttype";

        $params = ['contextlevel' => CONTEXT_COURSE, 'userid' => $userid, 'contexttype' => COMMENT_CONTEXT_USER_COURSE];
        $contextlist->add_from_sql(sprintf($sql, 'assessmentpath_comments'), $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist)
    {
        $context = $userlist->get_context();

        // Select from activity level comments

        if (is_a($context, \context_module::class)) {

            $sql = "SELECT comment.userid
                  FROM {%s} comment
                  JOIN {modules} m
                    ON m.name = 'assessmentpath'
                  JOIN {course_modules} cm
                    ON cm.instance = comment.contextid
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                   AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid
                   AND comment.contexttype = :contexttype";

            $params = ['modlevel' => CONTEXT_MODULE, 'contextid' => $context->id, 'contexttype' => COMMENT_CONTEXT_USER_PATH];
            $userlist->add_from_sql('userid', sprintf($sql, 'assessmentpath_comments'), $params);
        }

        // Select from course level comments

        if (is_a($context, \context_course::class)) {

            $sql = "SELECT comment.userid
                    FROM {%s} comment
                    JOIN {context} ctx
                        ON ctx.instanceid = comment.contextid
                    AND ctx.contextlevel = :modlevel
                    WHERE ctx.id = :contextid
                    AND comment.contexttype = :contexttype";

            $params = ['modlevel' => CONTEXT_COURSE, 'contextid' => $context->id, 'contexttype' => COMMENT_CONTEXT_USER_COURSE];
            $userlist->add_from_sql('userid', sprintf($sql, 'assessmentpath_comments'), $params);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist)
    {
        global $DB;
        $userid = $contextlist->get_user()->id;

        // Activity level comments.

        $contexts = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->id;
            }
            return $carry;
        }, []);

        if (!empty($contexts)) {
            list($insql, $inparams) = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);

            $sql = "SELECT comment.id,
                        comment.comment,
                        ctx.id as contextid
                    FROM {assessmentpath_comments} comment
                    JOIN {course_modules} cm
                        ON cm.instance = comment.contextid
                    JOIN {context} ctx
                        ON ctx.instanceid = cm.id
                    WHERE ctx.id $insql
                        AND comment.contexttype = :contexttype
                        AND comment.userid = :userid";
            $params = array_merge($inparams, ['userid' => $userid, 'contexttype' => COMMENT_CONTEXT_USER_PATH]);

            $alldata = [];
            $comments = $DB->get_recordset_sql($sql, $params);
            foreach ($comments as $comment) {
                $alldata[$comment->contextid] = (object) [
                    'comment' => $comment->comment,
                ];
            }
            $comments->close();

            // The comments data is organised in: {Course name}/{AssessmentPath activity name}/data.json
            array_walk($alldata, function ($commentdata, $contextid) {
                $context = \context::instance_by_id($contextid);
                $subcontext = [
                    get_string('comments', 'assessmentpath')
                ];
                writer::with_context($context)->export_data(
                    $subcontext,
                    (object) ['comment' => $commentdata]
                );
            });
        }

        // Course level comments.

        $contexts = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $carry[] = $context->id;
            }
            return $carry;
        }, []);

        if (!empty($contexts)) {
            list($insql, $inparams) = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);

            $sql = "SELECT comment.id,
                        comment.comment,
                        ctx.id as contextid
                    FROM {assessmentpath_comments} comment
                    JOIN {context} ctx
                        ON ctx.instanceid = comment.contextid
                    WHERE ctx.id $insql
                        AND comment.contexttype = :contexttype
                        AND comment.userid = :userid";
            $params = array_merge($inparams, ['userid' => $userid, 'contexttype' => COMMENT_CONTEXT_USER_COURSE]);

            $alldata = [];
            $comments = $DB->get_recordset_sql($sql, $params);
            foreach ($comments as $comment) {
                $alldata[$comment->contextid] = (object) [
                    'comment' => $comment->comment,
                ];
            }
            $comments->close();

            // The comments data is organised in: {Course name}/data.json
            array_walk($alldata, function ($commentdata, $contextid) {
                $context = \context::instance_by_id($contextid);
                $subcontext = [
                    get_string('comments', 'assessmentpath')
                ];
                writer::with_context($context)->export_data(
                    $subcontext,
                    (object) ['comment' => $commentdata]
                );
            });
        }
    }

    /**
     * Delete all user data which matches the specified context.
     *
     * @param context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context)
    {
        // Delete activity level comments

        if ($context->contextlevel == CONTEXT_MODULE) {

            $sql = "SELECT comment.id
                  FROM {%s} comment
                  JOIN {modules} m
                    ON m.name = 'assessmentpath'
                  JOIN {course_modules} cm
                    ON cm.instance = comment.contextid
                   AND cm.module = m.id
                 WHERE cm.id = :cmid
                    AND comment.contexttype = :contexttype";

            $params = ['cmid' => $context->instanceid, 'contexttype' => COMMENT_CONTEXT_USER_PATH];
            static::delete_data('assessmentpath_comments', $sql, $params);
        }

        // Delete course level comments

        if ($context->contextlevel == CONTEXT_COURSE) {

            $sql = "SELECT comment.id
                  FROM {%s} comment
                 WHERE comment.contextid = :courseid
                    AND comment.contexttype = :contexttype";

            $params = ['courseid' => $context->instanceid, 'contexttype' => COMMENT_CONTEXT_USER_COURSE];
            static::delete_data('assessmentpath_comments', $sql, $params);
        }
   }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist)
    {
        global $DB;
        $userid = $contextlist->get_user()->id;

        // Delete activity level comments

        $contextids = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->id;
            }
            return $carry;
        }, []);

        if (!empty($contextids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

            $sql = "SELECT comment.id
                    FROM {%s} comment
                    JOIN {modules} m
                        ON m.name = 'assessmentpath'
                    JOIN {course_modules} cm
                        ON cm.instance = comment.contextid
                    AND cm.module = m.id
                    JOIN {context} ctx
                        ON ctx.instanceid = cm.id
                    WHERE comment.userid = :userid
                        AND comment.contexttype = :contexttype
                        AND ctx.id $insql";

            $params = array_merge($inparams, ['userid' => $userid, 'contexttype' => COMMENT_CONTEXT_USER_PATH]);
            static::delete_data('assessmentpath_comments', $sql, $params);
        }

        // Delete course level comments

        $contextids = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $carry[] = $context->id;
            }
            return $carry;
        }, []);

        if (!empty($contextids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

            $sql = "SELECT comment.id
                    FROM {%s} comment
                    JOIN {context} ctx
                        ON ctx.instanceid = comment.contextid
                    WHERE comment.userid = :userid
                        AND comment.contexttype = :contexttype
                        AND ctx.id $insql";

            $params = array_merge($inparams, ['userid' => $userid, 'contexttype' => COMMENT_CONTEXT_USER_COURSE]);
            static::delete_data('assessmentpath_comments', $sql, $params);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist)
    {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete activity level comments

        if (is_a($context, \context_module::class)) {

            $sql = "SELECT comment.id
                    FROM {%s} comment
                    JOIN {modules} m
                        ON m.name = 'assessmentpath'
                    JOIN {course_modules} cm
                        ON cm.instance = comment.contextid
                       AND cm.module = m.id
                    JOIN {context} ctx
                        ON ctx.instanceid = cm.id
                    WHERE ctx.id = :contextid
                        AND comment.contexttype = :contexttype
                        AND comment.userid $insql";

            $params = array_merge($inparams, ['contextid' => $context->id, 'contexttype' => COMMENT_CONTEXT_USER_PATH]);
            static::delete_data('assessmentpath_comments', $sql, $params);
        }

        // Delete course level comments

        if (is_a($context, \context_course::class)) {

            $sql = "SELECT comment.id
                    FROM {%s} comment
                    JOIN {context} ctx
                        ON ctx.instanceid = comment.contextid
                    WHERE ctx.id = :contextid
                        AND comment.contexttype = :contexttype
                        AND comment.userid $insql";

            $params = array_merge($inparams, ['contextid' => $context->id, 'contexttype' => COMMENT_CONTEXT_USER_COURSE]);
            static::delete_data('assessmentpath_comments', $sql, $params);
        }
    }

    /**
     * Delete data from $tablename with the IDs returned by $sql query.
     *
     * @param  string $tablename  Table name where executing the SQL query.
     * @param  string $sql    SQL query for getting the IDs of the scoestrack entries to delete.
     * @param  array  $params SQL params for the query.
     */
    protected static function delete_data(string $tablename, string $sql, array $params)
    {
        global $DB;

        $ids = $DB->get_fieldset_sql(sprintf($sql, $tablename), $params);
        if (!empty($ids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select($tablename, "id $insql", $inparams);
        }
    }
}
