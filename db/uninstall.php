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
 * Uninstall function for video assessment module.
 *
 * This function is called when the module is uninstalled.
 * It cleans up the default rubric template that was created during installation.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Uninstall cleanup function for video assessment module.
 *
 * Removes the default rubric template that was created during installation.
 *
 * @return void
 */
function xmldb_videoassessment_uninstall() {
    global $DB, $CFG;
    
    // Clean up the default rubric template if it exists.
    require_once($CFG->dirroot . '/grade/grading/lib.php');
    require_once($CFG->dirroot . '/grade/grading/form/rubric/lib.php');
    
    $systemcontext = context_system::instance();
    
    // Find and delete the default template areas.
    $templateareas = $DB->get_records_sql(
        "SELECT ga.id 
         FROM {grading_areas} ga
         JOIN {grading_definitions} gd ON gd.areaid = ga.id
         WHERE ga.contextid = ? 
         AND ga.component = 'core_grading'
         AND ga.areaname LIKE 'rubric_videoassessment_default%'
         AND gd.method = 'rubric'",
        [$systemcontext->id]
    );
    
    foreach ($templateareas as $area) {
        try {
            $manager = get_grading_manager($area->id);
            $controller = $manager->get_controller('rubric');
            if ($controller && $controller->is_form_defined()) {
                $controller->delete_definition();
            }
        } catch (Exception $e) {
            // Log error but continue cleanup
            debugging('Failed to delete rubric template during uninstall: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
}

