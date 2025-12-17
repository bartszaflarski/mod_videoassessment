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
 * This file contains the moodle hooks for the videoassessment module.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoassessment\va;

defined('MOODLE_INTERNAL') || die();

// Event types.
define('VIDEOASSESS_EVENT_TYPE_DUE', 'due');
define('VIDEOASSESS_EVENT_TYPE_GRADINGDUE', 'gradingdue');

/**
 * Add a new video assessment instance to the database.
 *
 * Creates a new video assessment activity with proper configuration
 * for assessment types, grading, and calendar events.
 *
 * @param stdClass $va Video assessment instance data
 * @param mod_videoassessment_mod_form $form Form data for validation
 * @return int The ID of the newly created video assessment instance
 * @throws moodle_exception If database insertion fails
 */
function videoassessment_add_instance($va, $form) {
    global $DB;
    if ($va->isquickSetup == 1) {
        if ($va->isselfassesstype == 1 || $va->ispeerassesstype == 1 || $va->isteacherassesstype == 1 || $va->isclassassesstype == 1) {
            if ($va->isselfassesstype == 1) {
                $va->ratingself = $va->selfassess;
            } else {
                $va->ratingself = 0;
            }
            if ($va->ispeerassesstype == 1) {
                $va->ratingpeer = $va->peerassess;
                if ($va->peerassess == 0) {
                    $va->numberofpeers = 0;
                }
            } else {
                $va->ratingpeer = 0;
            }
            if ($va->isteacherassesstype == 1) {
                $va->ratingteacher = $va->teacherassess;
            } else {
                $va->ratingteacher = 0;
            }
            if ($va->isclassassesstype == 1) {
                $va->ratingclass = $va->classassess;
            } else {
                $va->ratingclass = 0;
            }
        }

        if ($va->numberofpeers >= 0) {
            $va->usedpeers = $va->numberofpeers;
        }
        if ($va->gradingsimpledirect > 0) {
            $va->gradepass_videoassessment = $va->gradingsimpledirect;
            $va->gradepass = $va->gradingsimpledirect;
        }
    }
    if (!isset($va->gradepass_videoassessment) || !isset($va->gradepass)) {
        $va->gradepass_videoassessment = 0;
        $va->gradepass = 0;
    }
    $va->id = $DB->insert_record('videoassessment', $va);
    videoassessment_update_calendar($va);

    // Process peer assignments from the form.
    if (isset($va->peerassignments)) {
        // Debug: Log the peer assignments value.
        debugging('Peer assignments value: ' . $va->peerassignments, DEBUG_DEVELOPER);
        if ($va->peerassignments !== '' && $va->peerassignments !== '{}') {
            videoassessment_save_peer_assignments($va->id, $va->peerassignments);
        }
    } else {
        debugging('Peer assignments not set in form data', DEBUG_DEVELOPER);
    }

    // Check if rubric grading method is selected and set session flag for redirect.
    $rubricselected = false;
    foreach (['beforeteacher', 'beforetraining', 'beforepeer', 'beforeclass', 'beforeself'] as $area) {
        $fieldname = 'advancedgradingmethod_' . $area;
        if (isset($va->$fieldname) && $va->$fieldname === 'rubric') {
            $rubricselected = true;
            break;
        }
    }

    if ($rubricselected) {
        global $SESSION;
        $SESSION->videoassessment_redirect_to_grading = $va->id;
    }

    return $va->id;
}

/**
 * Update an existing video assessment instance in the database.
 *
 * Modifies video assessment configuration, handles assessment type changes,
 * and triggers regrading when necessary.
 *
 * @param stdClass $va Video assessment instance data
 * @param mod_videoassessment_mod_form $form Form data for validation
 * @return boolean True if update was successful
 * @throws moodle_exception If database update fails
 */
function videoassessment_update_instance($va, $form) {
    global $DB, $CFG;

    $va->id = $va->instance;
    $cm = get_coursemodule_from_instance('videoassessment', $va->id, 0, false, MUST_EXIST);
    if ($va->isquickSetup == 1) {
        if ($va->isselfassesstype == 1 || $va->ispeerassesstype == 1 || $va->isteacherassesstype == 1 || $va->isclassassesstype == 1) {
            if ($va->isselfassesstype == 1) {
                $va->ratingself = $va->selfassess;
            } else {
                $va->ratingself = 0;
                $va->selfassess = 0;
            }
            if ($va->ispeerassesstype == 1) {
                $va->ratingpeer = $va->peerassess;
            } else {
                $va->ratingpeer = 0;
                $va->peerassess = 0;
            }
            if ($va->isteacherassesstype == 1) {
                $va->ratingteacher = $va->teacherassess;
            } else {
                $va->ratingteacher = 0;
                $va->teacherassess = 0;
            }
            if ($va->isclassassesstype == 1) {
                $va->ratingclass = $va->classassess;
            } else {
                $va->ratingclass = 0;
                $va->classassess = 0;
            }
        }
        if ($va->numberofpeers > 0) {
            $va->usedpeers = $va->numberofpeers;
        }
        if ($va->gradingsimpledirect > 0) {
            $cm->completionusegrade = 1;
            $cm->completion = COMPLETION_TRACKING_AUTOMATIC;
            $DB->update_record('course_modules', $cm);
            $va->gradepass_videoassessment = $va->gradingsimpledirect;
            $va->gradepass = $va->gradingsimpledirect;
        } else {
            $va->gradepass_videoassessment = 0;
            $va->gradepass = 0;
        }

        if ($va->advancedgradingmethod_beforeteacher == 'rubric') {
            $va->advancedgradingmethod_beforeteacher = '';
        }
        if ($va->advancedgradingmethod_beforetraining == 'rubric') {
            $va->advancedgradingmethod_beforetraining = '';
        }
        if ($va->advancedgradingmethod_beforepeer == 'rubric') {
            $va->advancedgradingmethod_beforepeer = '';
        }
        if ($va->advancedgradingmethod_beforeclass == 'rubric') {
            $va->advancedgradingmethod_beforeclass = '';
        }
        if ($va->advancedgradingmethod_beforeself == 'rubric') {
            $va->advancedgradingmethod_beforeself = '';
        }
    } else {
        if ($va->ratingself > 0) {
            $va->selfassess = $va->ratingself;
            $va->isselfassesstype = 1;
        } else {
            $va->selfassess = 0;
            $va->isselfassesstype = 0;
        }
        if ($va->ratingpeer > 0) {
            $va->peerassess = $va->ratingpeer;
            $va->ispeerassesstype = 1;
        } else {
            $va->peerassess = 0;
            $va->ispeerassesstype = 0;
        }

        if ($va->ratingteacher > 0) {
            $va->teacherassess = $va->ratingteacher;
            $va->isteacherassesstype = 1;
        } else {
            $va->teacherassess = 0;
            $va->isteacherassesstype = 0;
        }
        if ($va->ratingclass > 0) {
            $va->classassess = $va->ratingclass;
            $va->isclassassesstype = 1;
        } else {
            $va->classassess = 0;
            $va->isclassassesstype = 0;
        }
        $va->numberofpeers = $va->usedpeers;
    }

    $oldva = $DB->get_record('videoassessment', array('id' => $va->id));

    $DB->update_record('videoassessment', $va);
    videoassessment_update_calendar($va);
    if ($oldva->ratingteacher != $va->ratingteacher
        || $oldva->ratingself != $va->ratingself
        || $oldva->ratingpeer != $va->ratingpeer) {
        require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');

        $course = $DB->get_record('course', array('id' => $va->course), '*', MUST_EXIST);
        $vaobj = new mod_videoassessment\va(context_module::instance($cm->id), $cm, $course);
        $vaobj->regrade();
    }

    // Process peer assignments from the form.
    if (isset($va->peerassignments) && $va->peerassignments !== '' && $va->peerassignments !== '{}') {
        videoassessment_save_peer_assignments($va->id, $va->peerassignments);
    }

    return true;
}

/**
 * Save peer assignments from the form data.
 *
 * Processes the JSON-encoded peer assignments and updates the database.
 * Clears existing assignments and creates new ones based on form data.
 *
 * @param int $videoassessmentid Video assessment instance ID
 * @param string $peerassignmentsjson JSON-encoded peer assignments
 * @return void
 */
function videoassessment_save_peer_assignments($videoassessmentid, $peerassignmentsjson) {
    global $DB;

    $peerassignments = json_decode($peerassignmentsjson, true);
    if (empty($peerassignments) || !is_array($peerassignments)) {
        return;
    }

    // Delete existing peer assignments for this activity.
    $DB->delete_records('videoassessment_peers', ['videoassessment' => $videoassessmentid]);

    // Insert new peer assignments.
    foreach ($peerassignments as $userid => $peers) {
        if (!is_array($peers)) {
            continue;
        }
        foreach ($peers as $peerid) {
            $record = new stdClass();
            $record->videoassessment = $videoassessmentid;
            $record->userid = (int)$userid;
            $record->peerid = (int)$peerid;
            $DB->insert_record('videoassessment_peers', $record);
        }
    }
}

/**
 * Delete a video assessment instance and all associated data.
 *
 * Removes the video assessment activity and cleans up all related
 * database records including grades, videos, and peer assignments.
 *
 * @param int $id Video assessment instance ID
 * @return boolean True if deletion was successful
 * @throws moodle_exception If database deletion fails
 */
function videoassessment_delete_instance($id) {
    global $DB;

    $DB->delete_records('videoassessment', array('id' => $id));
    $DB->delete_records('videoassessment_aggregation', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_grades', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_grade_items', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_peers', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_videos', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_video_assocs', array('videoassessment' => $id));

    return true;
}

/**
 * Check which Moodle features are supported by video assessment module.
 *
 * Returns feature support flags for groups, grading, completion tracking,
 * and other Moodle core functionality.
 *
 * @param string $feature Feature name to check support for
 * @return boolean|null True if supported, false if not, null if unknown
 */
function videoassessment_supports($feature) {
    switch ($feature){
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_IDNUMBER:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Get list of available grading areas for video assessment.
 *
 * Returns an array mapping grading area keys to their display names
 * for use in advanced grading configuration.
 *
 * @return array Associative array of grading area keys and names
 */
function videoassessment_grading_areas_list() {
    return array(
        'beforeteacher' => get_string('teacher', 'videoassessment'),
        'beforetraining' => get_string('trainingpretest', 'videoassessment'),
        'beforeself' => get_string('self', 'videoassessment'),
        'beforepeer' => get_string('peer', 'videoassessment'),
        'beforeclass' => get_string('class', 'videoassessment'),
    );
}

/**
 * Handle file serving for video assessment module files.
 *
 * Serves uploaded video files and other module assets with proper
 * security checks and capability validation.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param stdClass $context Context object
 * @param string $filearea File area identifier
 * @param array $args Additional file path arguments
 * @param bool $forcedownload Force download instead of inline display
 * @return void
 * @throws moodle_exception If user lacks required capabilities
 */
function mod_videoassessment_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

    // Allow Self Assessment/Peer Assessment to view other people's files.
    if (!has_capability('mod/videoassessment:gradepeer', $context)) {
        send_file_not_found();
    }

    $fullpath = "/{$context->id}/mod_videoassessment/$filearea/" . implode('/', $args);

    $fs = get_file_storage();
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    \core\session\manager::write_close(); // Unlock session during fileserving.
    send_stored_file($file, HOURSECS, 0, $forcedownload);
}

/**
 * Convert training video files to appropriate format.
 *
 * Processes uploaded training videos through bulk upload system
 * and converts them to web-compatible formats.
 *
 * @param stdClass $event Event object containing instance information
 * @param stdClass $va Video assessment instance data
 * @return void
 * @throws moodle_exception If video conversion fails
 */
function videoassessment_convert_video($event, $va) {
    global $CFG, $DB, $USER;

    require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');

    if ($va->training && !empty($va->trainingvideo)) {
        $fs = get_file_storage();
        $upload = new \videoassessment_bulkupload($event->instanceid);

        $files = $fs->get_area_files(\context_user::instance($USER->id)->id, 'user', 'draft', $va->trainingvideo);

        if (!empty($files)) {
            foreach ($files as $file) {
                if ($file->get_filename() == '.') {
                    continue;
                }

                $upload->create_temp_dirs();
                $tmpname = $upload->get_temp_name($file->get_filename());
                $tmppath = $upload->get_tempdir() . '/upload/' . $tmpname;
                $file->copy_content_to($tmppath);

                $videoid = $upload->video_data_add($tmpname, $file->get_filename());

                $upload->convert($tmpname);

                $DB->execute("UPDATE {videoassessment} SET trainingvideoid = ?, trainingvideo = 0 WHERE id = ?",
                    array($videoid, $va->id));
            }
        }
    }
}

/**
 * Check if video assessment has grades for specific grading areas.
 *
 * Verifies which grading types have been configured and have
 * associated grade items in the database.
 *
 * @param int $videoassessment Video assessment instance ID
 * @return array Associative array of grading area keys and boolean values
 */
function videoassessment_check_has_grade($videoassessment) {
    global $DB;

    $hasgrade = array();
    $gradetypes = videoassessment_grading_areas_list();
    foreach ($gradetypes as $key => $gradetype) {
        $sql = 'SELECT * from {videoassessment_grade_items} WHERE videoassessment=? AND type like ?';
        $params = array($videoassessment, $key);
        $hasgrade[$key] = $DB->record_exists_sql($sql, $params);
    }

    return $hasgrade;
}

/**
 * Get grading areas for a specific context.
 *
 * Retrieves all grading areas associated with a given context
 * for advanced grading configuration.
 *
 * @param int $contextid Context ID to get areas for
 * @return array Associative array of area IDs and names
 */
function videoassessment_get_areas($contextid) {
    global $DB;

    $areas = array();
    $sql = 'SELECT id, areaname FROM {grading_areas} WHERE contextid = ?';
    $params = array($contextid);

    if ($arealists = $DB->get_records_sql($sql, $params)) {
        foreach ($arealists as $area) {
            $areas[$area->id] = $area->areaname;
        }
    }

    return $areas;
}

/**
 * Get grading area name by its ID.
 *
 * Retrieves the display name of a specific grading area
 * for use in user interfaces.
 *
 * @param int $id Grading area ID
 * @return string Area name or empty string if not found
 */
function videoassessment_get_areaname_by_id($id) {
    global $DB;

    return $DB->get_field('grading_areas', 'areaname', array('id' => $id));
}

/**
 * Determine if video assessment intro should be displayed.
 *
 * Checks timing and configuration settings to determine
 * whether the activity introduction should be visible to users.
 *
 * @param stdClass $va Video assessment instance data
 * @return boolean True if intro should be shown, false otherwise
 */
function videoassessment_show_intro($va) {
    if ($va->showdescription ||
        time() > $va->allowsubmissionsfromdate) {
        return true;
    }
    return false;
}

/**
 * Update calendar events for video assessment activity.
 *
 * Creates or updates calendar events for due dates and grading
 * deadlines associated with the video assessment activity.
 *
 * @param stdClass $va Video assessment instance data
 * @return boolean True if calendar update was successful
 * @throws moodle_exception If calendar event creation fails
 */
function videoassessment_update_calendar($va) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/calendar/lib.php');

    // Special case for add_instance as the coursemodule has not been set yet.
    $instance = $va;

    // Start with creating the event.
    $event = new stdClass();
    $event->modulename = 'videoassessment';
    $event->courseid = $instance->course;
    $event->groupid = 0;
    $event->userid = 0;
    $event->instance = $instance->id;
    $event->type = CALENDAR_EVENT_TYPE_ACTION;

    // Convert the links to pluginfile. It is a bit hacky but at this stage the files
    // might not have been saved in the module area yet.
    $intro = $instance->intro;
    if ($draftid = file_get_submitted_draft_itemid('introeditor')) {
        $intro = file_rewrite_urls_to_pluginfile($intro, $draftid);
    }

    // We need to remove the links to files as the calendar is not ready
    // to support module events with file areas.
    $intro = strip_pluginfile_content($intro);
    if (videoassessment_show_intro($va)) {
        $event->description = array(
            'text' => $intro,
            'format' => $instance->introformat,
        );
    } else {
        $event->description = array(
            'text' => '',
            'format' => $instance->introformat,
        );
    }

    $eventtype = VIDEOASSESS_EVENT_TYPE_DUE;
    if ($instance->duedate) {
        $event->name = get_string('calendardue', 'videoassessment', $instance->name);
        $event->eventtype = $eventtype;
        $event->timestart = $instance->duedate;
        $event->timesort = $instance->duedate;
        $select = "modulename = :modulename
                       AND instance = :instance
                       AND eventtype = :eventtype
                       AND groupid = 0
                       AND courseid <> 0";
        $params = array('modulename' => 'videoassessment', 'instance' => $instance->id, 'eventtype' => $eventtype);
        $event->id = $DB->get_field_select('event', 'id', $select, $params);

        // Now process the event.
        if ($event->id) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            calendar_event::create($event, false);
        }
    } else {
        $DB->delete_records('event', array('modulename' => 'videoassessment', 'instance' => $instance->id,
            'eventtype' => $eventtype));
    }

    $eventtype = VIDEOASSESS_EVENT_TYPE_GRADINGDUE;
    if ($instance->gradingduedate) {
        $event->name = get_string('calendargradingdue', 'videoassessment', $instance->name);
        $event->eventtype = $eventtype;
        $event->timestart = $instance->gradingduedate;
        $event->timesort = $instance->gradingduedate;
        $event->id = $DB->get_field('event', 'id', array('modulename' => 'videoassessment',
            'instance' => $instance->id, 'eventtype' => $event->eventtype));

        // Now process the event.
        if ($event->id) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            calendar_event::create($event, false);
        }
    } else {
        $DB->delete_records('event', array('modulename' => 'videoassessment', 'instance' => $instance->id,
            'eventtype' => $eventtype));
    }

    return true;
}

/**
 * Extend settings navigation for video assessment module.
 *
 * Adds grade management interface elements to the settings navigation
 * block for advanced grading configuration.
 *
 * @param settings_navigation $settings Settings navigation object
 * @param navigation_node $videoassessmentnode Video assessment navigation node
 * @return void
 */
function videoassessment_extend_settings_navigation($settings, navigation_node $videoassessmentnode) {
    global $PAGE;
    $areaname = '';
    if (optional_param('areaid', null, PARAM_INT)) {
        $areaname = videoassessment_get_areaname_by_id(required_param('areaid', PARAM_INT));
    }
    $hasgrade = videoassessment_check_has_grade($PAGE->cm->instance);
    $areas = videoassessment_get_areas($PAGE->cm->context->id);

    echo "<div class='check-has-grade hidden " . ($areaname ? $areaname : '') . "'>";
    echo '<input name="videoassessmentid" text="' . $PAGE->cm->instance . '">';
    if ($hasgrade) {
        foreach ($hasgrade as $key => $grade) {
            if ($areas) {
                foreach ($areas as $k => $area) {
                    if ($area == $key) {
                        echo "<input name='$key' value='$grade' text='$k'>";
                    }
                }
            } else {
                echo "<input name='$key' value='$grade'>";
            }
        }
    }

    echo "</div>";
    $PAGE->requires->jquery();
    $PAGE->requires->js_call_amd('mod_videoassessment/grademanage', 'init_grademanage', array());

}

/**
 * Returns a map of video assessment actions to FontAwesome icon classes.
 *
 * Provides icon mappings for various video assessment interface elements
 * to maintain consistent visual design.
 *
 * @return array Action to icon class mapping
 */
function mod_videoassessment_get_fontawesome_icon_map() {
    return [
        'mod_book:chapter' => 'fa-bookmark-o',
        'mod_book:nav_prev' => 'fa-arrow-left',
        'mod_book:nav_sep' => 'fa-minus',
        'mod_book:add' => 'fa-plus',
        'mod_book:nav_next' => 'fa-arrow-right',
        'mod_book:nav_exit' => 'fa-arrow-up',
    ];
}

/**
 * Get association (userid, timing) from a stored video file.
 *
 * Extracts user ID and timing information from video file path
 * structure for proper file organization and access control.
 *
 * @param stored_file $file Stored file object to analyze
 * @return array|false Array with [userid, timing] if found, false otherwise
 */
function videoassessment_get_assoc(stored_file $file) {
    $path = trim($file->get_filepath(), '/');
    $parts = explode('/', $path);

    if (count($parts) >= 2) {
        $userid = (int)$parts[0];
        $timing = $parts[1]; // 'before' or 'after'

        if ($userid > 0 && in_array($timing, ['before', 'after'])) {
            return [$userid, $timing];
        }
    }

    return false;
}
