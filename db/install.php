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
 * This file replaces the legacy STATEMENTS section in:
 *
 * db/install.xml,
 * lib.php/modulename_install()
 * post installation hook and partially defaults.php
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Install function for video assessment module.
 *
 * This function is called when the module is installed.
 * It checks if the ffmpeg command exists and displays a notification.
 * It also creates a default rubric template.
 *
 * @return void
 */
function xmldb_videoassessment_install() {
    global $OUTPUT, $CFG, $DB, $USER;
    
    // Check ffmpeg
    $cmdline = '/usr/local/bin/ffmpeg -version';
    ignore_user_abort(true);
    set_time_limit(0);
    $output = array();
    $retval = 0;
    putenv('PATH=');
    putenv('LD_LIBRARY_PATH=');
    putenv('DYLD_LIBRARY_PATH=');
    exec($cmdline, $output, $retval);
    if ($retval == 1 || empty($output)) {
        echo $OUTPUT->notification(get_string('installerrorffmpegdoesnotexist', 'videoassessment'), 'notifyproblem');
    } else {
        $arr = explode("\n", $output[0]);
        $ffmpegversioninfo = $arr[0];
        echo $OUTPUT->notification($ffmpegversioninfo, 'notifysuccess');
    }
    
    // Create default rubric template (only if grading tables exist)
    // Note: We check if tables exist because during initial installation,
    // the rubric plugin tables may not be created yet.
    if ($DB->get_manager()->table_exists('gradingform_rubric_criteria')) {
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        require_once($CFG->dirroot . '/grade/grading/form/rubric/lib.php');
        create_default_rubric_template();
    }
}

/**
 * Creates a default rubric template for video assessment.
 *
 * This creates a system-wide template that can be reused across all courses.
 *
 * @return void
 */
function create_default_rubric_template() {
    global $CFG, $DB, $USER;
    
    // Ensure we have a valid user (admin)
    if (empty($USER->id)) {
        $admin = get_admin();
        if ($admin) {
            $USER = $admin;
        } else {
            return; // Cannot create template without a user
        }
    }
    
    // Check if a default template already exists
    $systemcontext = context_system::instance();
    $existingarea = $DB->get_record_sql(
        "SELECT ga.id 
         FROM {grading_areas} ga
         JOIN {grading_definitions} gd ON gd.areaid = ga.id
         WHERE ga.contextid = ? 
         AND ga.component = 'core_grading'
         AND ga.areaname LIKE 'rubric_videoassessment_default%'
         AND gd.method = 'rubric'
         AND gd.name = ?",
        [$systemcontext->id, get_string('defaultrubrictemplate', 'mod_videoassessment')]
    );
    
    if ($existingarea) {
        return; // Template already exists
    }
    
    // Create a shared area for the template using the grading manager
    $manager = get_grading_manager($systemcontext, 'core_grading', 'rubric_videoassessment_default_' . time());
    $newareaid = $manager->create_shared_area('rubric');
    
    // Get the manager for the new shared area
    $targetmanager = get_grading_manager($newareaid);
    $targetmanager->set_active_method('rubric');
    
    // Get the controller
    $controller = $targetmanager->get_controller('rubric');
    
    // Create default rubric definition structure
    // The structure must match what the rubric edit form expects
    $definition = new stdClass();
    $definition->name = get_string('defaultrubrictemplate', 'mod_videoassessment');
    
    // Description editor format - use draft itemid for new content
    $draftitemid = file_get_unused_draft_itemid();
    $definition->description_editor = array(
        'text' => get_string('defaultrubrictemplatedesc', 'mod_videoassessment'),
        'format' => FORMAT_HTML,
        'itemid' => $draftitemid
    );
    
    // Status must be READY for templates to be visible
    $definition->status = gradingform_controller::DEFINITION_STATUS_READY;
    $definition->saverubric = 'Save rubric and make it ready';
    
    // Default criteria - structure matches rubric edit form
    // 5 criteria: Voice, Gestures, Content, Visuals, Overall
    // Each with 6 levels: 0-5 points
    $definition->rubric = array(
        'criteria' => array(
            'NEWID1' => array(  // Voice
                'sortorder' => 1,
                'description' => get_string('defaultcriterionvoice', 'mod_videoassessment'),
                'descriptionformat' => FORMAT_HTML,
                'levels' => array(
                    'NEWID1-1' => array(
                        'score' => 0,
                        'definition' => get_string('defaultvoice0', 'mod_videoassessment'),
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID1-2' => array(
                        'score' => 1,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID1-3' => array(
                        'score' => 2,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID1-4' => array(
                        'score' => 3,
                        'definition' => get_string('defaultvoice3', 'mod_videoassessment'),
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID1-5' => array(
                        'score' => 4,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID1-6' => array(
                        'score' => 5,
                        'definition' => get_string('defaultvoice5', 'mod_videoassessment'),
                        'definitionformat' => FORMAT_HTML
                    )
                )
            ),
            'NEWID2' => array(  // Gestures
                'sortorder' => 2,
                'description' => get_string('defaultcriteriongestures', 'mod_videoassessment'),
                'descriptionformat' => FORMAT_HTML,
                'levels' => array(
                    'NEWID2-1' => array(
                        'score' => 0,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID2-2' => array(
                        'score' => 1,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID2-3' => array(
                        'score' => 2,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID2-4' => array(
                        'score' => 3,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID2-5' => array(
                        'score' => 4,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID2-6' => array(
                        'score' => 5,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    )
                )
            ),
            'NEWID3' => array(  // Content
                'sortorder' => 3,
                'description' => get_string('defaultcriterioncontent', 'mod_videoassessment'),
                'descriptionformat' => FORMAT_HTML,
                'levels' => array(
                    'NEWID3-1' => array(
                        'score' => 0,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID3-2' => array(
                        'score' => 1,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID3-3' => array(
                        'score' => 2,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID3-4' => array(
                        'score' => 3,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID3-5' => array(
                        'score' => 4,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID3-6' => array(
                        'score' => 5,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    )
                )
            ),
            'NEWID4' => array(  // Visuals
                'sortorder' => 4,
                'description' => get_string('defaultcriterionvisuals', 'mod_videoassessment'),
                'descriptionformat' => FORMAT_HTML,
                'levels' => array(
                    'NEWID4-1' => array(
                        'score' => 0,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID4-2' => array(
                        'score' => 1,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID4-3' => array(
                        'score' => 2,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID4-4' => array(
                        'score' => 3,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID4-5' => array(
                        'score' => 4,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID4-6' => array(
                        'score' => 5,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    )
                )
            ),
            'NEWID5' => array(  // Overall
                'sortorder' => 5,
                'description' => get_string('defaultcriterionoverall', 'mod_videoassessment'),
                'descriptionformat' => FORMAT_HTML,
                'levels' => array(
                    'NEWID5-1' => array(
                        'score' => 0,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID5-2' => array(
                        'score' => 1,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID5-3' => array(
                        'score' => 2,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID5-4' => array(
                        'score' => 3,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID5-5' => array(
                        'score' => 4,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    ),
                    'NEWID5-6' => array(
                        'score' => 5,
                        'definition' => '-',
                        'definitionformat' => FORMAT_HTML
                    )
                )
            )
        ),
        'options' => array(
            'sortlevelsasc' => 1,
            'lockzeropoints' => 1,
            'showdescriptionteacher' => 1,
            'showdescriptionstudent' => 1,
            'showscoreteacher' => 1,
            'showscorestudent' => 1,
            'enableremarks' => 1,
            'showremarksstudent' => 1
        )
    );
    
    // Update the definition - this will create the rubric
    // Use update_or_check_rubric which is the proper method for rubric controller
    try {
        $controller->update_or_check_rubric($definition, $USER->id, true);
    } catch (Exception $e) {
        // Log error but don't fail installation
        debugging('Failed to create default rubric template: ' . $e->getMessage(), DEBUG_NORMAL);
        // Try alternative method
        try {
            $controller->update_definition($definition);
        } catch (Exception $e2) {
            debugging('Alternative method also failed: ' . $e2->getMessage(), DEBUG_NORMAL);
        }
    }
}
