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

/**
 * Allows viewing/use of a particular instance of videoassessment.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoassessment\va;

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');

if (optional_param('ajax', null, PARAM_ALPHANUM)) {
    require_login();
    $action = optional_param('action', null, PARAM_ALPHANUM);

    if ($action == 'getcoursesbycategory') {
        $catid = optional_param('catid', null, PARAM_INT);
        $currentcourseid = optional_param('currentcourseid', 0, PARAM_INT);
        $courseopts = [];

        if (!empty($catid)) {
            $context = context_coursecat::instance($catid);
            require_capability('mod/videoassessment:fetchcourses', $context);

            $courses = va::get_courses_managed_by($USER->id, $catid);
            array_walk($courses, function (\stdClass $a) use (&$courseopts) {
                $courseopts[$a->id] = $a->fullname;
            });

            $courseoptions = [];
            foreach ($courseopts as $courseid => $coursename) {
                $courseoptions[] = [
                    'id' => $courseid,
                    'fullname' => $coursename,
                    'selected' => ($currentcourseid == $courseid),
                ];
            }
        }

        $templatecontext = [
            'courses' => $courseoptions,
        ];
        $html = $OUTPUT->render_from_template('mod_videoassessment/course_options', $templatecontext);
        echo json_encode([
            'html' => $html,
        ]);
        die;
    } else if ($action == 'getsectionsbycourse') {
        $courseid = optional_param('courseid', null, PARAM_INT);
        $currentsectionid = optional_param('currentsectionid', null, PARAM_INT);
        $sectionopts = [];

        if (!empty($courseid)) {
            $context = context_course::instance($courseid);
            require_capability('mod/videoassessment:fetchsections', $context);

            $modinfo = get_fast_modinfo($courseid);
            $sections = $modinfo->get_section_info_all();

            if (!empty($sections)) {
                foreach ($sections as $key => $section) {
                    $sectionopts[] = [
                        'id'       => $section->id,
                        'name'     => get_section_name($courseid, $section->section),
                        'selected' => ($currentsectionid == $section->id),
                    ];
                }
            }
        }

        $templatecontext = [
            'sections' => $sectionopts,
        ];
        $html = $OUTPUT->render_from_template('mod_videoassessment/section_options', $templatecontext);

        echo json_encode([
            'html' => $html,
        ]);
        die;
    } else if ($action == "getallcomments") {
        global $OUTPUT, $DB, $PAGE;
        $cmid = optional_param('cmid', null, PARAM_INT);
        $userid = optional_param('userid', null, PARAM_INT);
        $timing = optional_param('timing', null, PARAM_RAW);
        $id = optional_param('id', null, PARAM_RAW);
        $context = context_module::instance($cmid);
        require_capability('mod/videoassessment:viewcomments', $context);
        $va = $DB->get_record('videoassessment', ['id' => $cmid]);

        $comments = [];
        $gradertypes = ['self', 'peer', 'teacher'];

        foreach ($gradertypes as $gradertype) {
            $gradingarea = $timing . $gradertype;
            $grades = va::get_grade_items_by_id($gradingarea, $userid, $va->id);
            foreach ($grades as $item => $gradeitem) {
                if ($gradeitem->id == $id) {
                    $comment = '<label class="mobile-submissioncomment">' . $gradeitem->submissioncomment . '</label>';
                    if ($gradertype == "peer") {
                        $label = '<span class="blue box">' . get_string($gradertype, 'videoassessment') . '</span>';
                    } else if ($gradertype == "teacher") {
                        $label = '<span class="green box">' . get_string($gradertype, 'videoassessment') . '</span>';
                    } else if ($gradertype == "self") {
                        $label = '<span class="red box">' . get_string($gradertype, 'videoassessment') . '</span>';
                    }
                    $o .= $OUTPUT->heading($label . $comment);
                }
            }

        }
        $o .= \html_writer::end_tag('div');

        echo json_encode([
            'html' => $o,
        ]);
        die;
    }
}
global $DB, $PAGE;
$id = required_param('id', PARAM_INT);
$url = new moodle_url('/mod/videoassessment/view.php', ['id' => $id]);
$ismailsent = optional_param('ismailsent', 0, PARAM_INT);
if ($action = optional_param('action', null, PARAM_ALPHA)) {
    $url->param('action', $action);
}
$cm = get_coursemodule_from_id('videoassessment', $id);
$course = $DB->get_record('course', ['id' => $cm->course]);
require_login($cm->course, true, $cm);
$PAGE->set_url($url);
$PAGE->set_heading($cm->name);
$PAGE->requires->jquery();
if ($action == "") {
    $PAGE->requires->js_call_amd('mod_videoassessment/videoassessment', 'init_message_sent_window', [$ismailsent]);
}
$context = context_module::instance($cm->id);
require_capability('mod/videoassessment:view', $context);

// Check if we need to redirect to advanced grading page after activity creation.
global $SESSION, $CFG;
if (!empty($SESSION->videoassessment_redirect_to_grading) && $SESSION->videoassessment_redirect_to_grading == $cm->instance) {
    unset($SESSION->videoassessment_redirect_to_grading);

    // Get the grading area ID for the teacher grading area.
    require_once($CFG->dirroot . '/grade/grading/lib.php');
    $gradingmanager = get_grading_manager($context, 'mod_videoassessment', 'beforeteacher');
    $areaid = $gradingmanager->get_areaid();

    if ($areaid) {
        redirect(new moodle_url('/grade/grading/manage.php', ['areaid' => $areaid]));
    }
}

// Trigger standard "viewed" event.
$videoassessment = $DB->get_record('videoassessment', ['id' => $cm->instance], '*', MUST_EXIST);
$event = \mod_videoassessment\event\course_module_viewed::create([
    'objectid' => $videoassessment->id,
    'context' => $context,
    'courseid' => $course->id,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('videoassessment', $videoassessment);
$event->trigger();

$va = new mod_videoassessment\va($context, $cm, $course);
echo $va->view($action);
