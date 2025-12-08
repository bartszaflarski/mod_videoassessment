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

namespace mod_videoassessment;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Rubric management class for video assessment grading.
 *
 * This class handles the management of grading managers and controllers
 * for rubric-based assessment across different grading areas and timings.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rubric {
    /**
     * Collection of grading managers for different areas.
     *
     * Stores grading manager instances indexed by grading area names.
     *
     * @var \stdClass
     */
    private $managers;

    /**
     * Collection of grading controllers for different areas.
     *
     * Stores grading controller instances indexed by grading area names.
     *
     * @var \stdClass
     */
    private $controllers;

    /**
     * Initialize rubric management for video assessment.
     *
     * Sets up grading managers and controllers for all grading areas
     * in the video assessment, optionally filtered by specific areas.
     *
     * @param va $va Video assessment instance object
     * @param array|null $gradingareas Optional array of specific grading areas to initialize
     * @return void
     */
    public function __construct(va $va, array $gradingareas = null) {
        $this->managers = new \stdClass();
        $this->controllers = new \stdClass();

        foreach ($va->gradingareas as $gradingarea) {
            if ($gradingareas && !in_array($gradingarea, $gradingareas)) {
                continue;
            }

            $this->managers->$gradingarea = get_grading_manager($va->context, 'mod_videoassessment', $gradingarea);
            $this->controllers->$gradingarea = null;
            if ($gradingmethod = $this->get_manager($gradingarea)->get_active_method()) {
                $this->controllers->$gradingarea = $this->get_manager($gradingarea)->get_controller($gradingmethod);
            }
        }
    }

    /**
     * Get grading manager for a specific grading area.
     *
     * Retrieves the grading manager instance for the specified
     * grading area if it exists.
     *
     * @param string $gradingarea The grading area identifier
     * @return \grading_manager|null Grading manager instance or null if not found
     */
    public function get_manager($gradingarea) {
        if (isset($this->managers->$gradingarea)) {
            return $this->managers->$gradingarea;
        }
        return null;
    }

    /**
     * Get grading controller for a specific grading area.
     *
     * Retrieves the grading controller instance for the specified
     * grading area if it exists and is configured.
     *
     * @param string $gradingarea The grading area identifier
     * @return \gradingform_rubric_controller|null Grading controller instance or null if not found
     */
    public function get_controller($gradingarea) {
        if (isset($this->controllers->$gradingarea)) {
            return $this->controllers->$gradingarea;
        }
        return null;
    }

    /**
     * Get available grading controller for a specific grading area.
     *
     * Retrieves the grading controller instance only if it exists,
     * is configured, and has an available form for grading.
     *
     * @param string $gradingarea The grading area identifier
     * @return \gradingform_rubric_controller|null Available grading controller or null if not available
     */
    public function get_available_controller($gradingarea) {
        $controller = $this->get_controller($gradingarea);
        if ($controller && $controller->is_form_available()) {
            return $controller;
        }
        return null;
    }
}
