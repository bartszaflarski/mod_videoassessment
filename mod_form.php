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
 * This file contains the forms to create and edit an instance of this module.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_videoassessment\va;

use core_grades\component_gradeitems;

/**
 * Settings form for the videoassessment module.
 *
 * Provides comprehensive configuration interface for video assessment activities
 * including grading, notifications, training materials, and assessment types.
 *
 * @see moodleform_mod
 */
class mod_videoassessment_mod_form extends moodleform_mod {

    /** @var int Maximum number of peers allowed for assessment */
    const MAX_USED_PEERS_LIMIT = 3;

    /** @var int Default number of peers for assessment */
    const DEFAULT_USED_PEERS = 1;

    /** @var object|null Video assessment instance data */
    protected $_videoassessmentinstance = null;

    /**
     * Define the form elements for video assessment configuration.
     *
     * Creates comprehensive form interface including general settings,
     * availability dates, grading options, and notification preferences.
     *
     * @return void
     */
    public function definition() {
        global $CFG, $DB, $PAGE;
        $cm = $PAGE->cm;

        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('videoassessmentname', 'videoassessment'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(false, get_string('description', 'videoassessment'));

        $mform->addElement('selectyesno', 'allowstudentupload', get_string('allowstudentupload', 'videoassessment'));
        $mform->setDefault('allowstudentupload', 1);
        $mform->addHelpButton('allowstudentupload', 'allowstudentupload', 'videoassessment');

        // Video Publishing section.
        $mform->addElement('header', 'videopublishing', get_string('videopublishing', 'videoassessment'));
        $mform->setExpanded('videopublishing', true);

        $mform->addElement('advcheckbox', 'allowyoutube', get_string('allowyoutube', 'videoassessment'));
        $mform->setDefault('allowyoutube', 1);
        $mform->addHelpButton('allowyoutube', 'allowyoutube', 'videoassessment');

        $mform->addElement('advcheckbox', 'allowvideoupload', get_string('allowvideoupload', 'videoassessment'));
        $mform->setDefault('allowvideoupload', 1);
        $mform->addHelpButton('allowvideoupload', 'allowvideoupload', 'videoassessment');

        $mform->addElement('advcheckbox', 'allowvideorecord', get_string('allowvideorecord', 'videoassessment'));
        $mform->setDefault('allowvideorecord', 1);
        $mform->addHelpButton('allowvideorecord', 'allowvideorecord', 'videoassessment');

        $mform->addElement('header', 'availability', get_string('availability', 'assign'));
        $mform->setExpanded('availability', false);

        $name = get_string('allowsubmissionsfromdate', 'assign');
        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', $name, $options);
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'assign');

        $name = get_string('duedate', 'assign');
        $mform->addElement('date_time_selector', 'duedate', $name, ['optional' => true]);
        $mform->addHelpButton('duedate', 'duedate', 'assign');

        $name = get_string('cutoffdate', 'assign');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, ['optional' => true]);
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'assign');

        $name = get_string('gradingduedate', 'assign');
        $mform->addElement('date_time_selector', 'gradingduedate', $name, ['optional' => true]);
        $mform->addHelpButton('gradingduedate', 'gradingduedate', 'assign');

        $this->manage_video();

        $this->add_notifications();

        $this->standard_grading_coursemodule_elements_to_grading('grading');

        $mform->addElement(
            'radio',
            'class',
            get_string('classgrading', 'videoassessment'),
            get_string('open', 'videoassessment'),
            1
        );
        $mform->addHelpButton('class', 'classgrading', 'videoassessment');
        $mform->addElement('radio', 'class', null, get_string('close', 'videoassessment'), 0);
        $mform->setType('class', PARAM_INT);
        $mform->setDefault('class', 0);

        $mform->addElement('header', 'ratings', get_string('ratings', 'videoassessment'));
        $mform->addHelpButton('ratings', 'ratings', 'videoassessment');
        $mform->addElement('static', 'ratingerror');
        for ($i = 100; $i >= 0; $i--) {
            $ratingopts[$i] = $i . '%';
        }
        $mform->addElement('select', 'ratingteacher', get_string('teacher', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingteacher', 100);
        $mform->addHelpButton('ratingteacher', 'ratingteacher', 'videoassessment');
        $mform->addElement('select', 'ratingself', get_string('self', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingself', 0);
        $mform->addHelpButton('ratingself', 'ratingself', 'videoassessment');
        $mform->addElement('select', 'ratingpeer', get_string('peer', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingpeer', 0);
        $mform->addHelpButton('ratingpeer', 'ratingpeer', 'videoassessment');
        $mform->addElement('select', 'ratingclass', get_string('class', 'videoassessment'), $ratingopts);
        $mform->setDefault('ratingclass', 0);
        $mform->addHelpButton('ratingclass', 'ratingclass', 'videoassessment');

        $mform->addElement('selectyesno', 'delayedteachergrade', get_string('delayedteachergrade', 'videoassessment'));
        $mform->setDefault('delayedteachergrade', 1);
        $mform->addHelpButton('delayedteachergrade', 'delayedteachergrade', 'videoassessment');

        $students = get_enrolled_users($this->context);
        $maxusedpeers = min(count($students), self::MAX_USED_PEERS_LIMIT);
        $usedpeeropts = range(0, $maxusedpeers);
        $mform->addElement('select', 'usedpeers', get_string('usedpeers', 'videoassessment'), $usedpeeropts);
        $mform->setDefault('usedpeers', 0);
        $mform->addHelpButton('usedpeers', 'usedpeers', 'videoassessment');

        // Assign peers section.
        $mform->addElement('header', 'assignpeerssection', get_string('assignpeers', 'videoassessment'));
        $mform->addHelpButton('assignpeerssection', 'assignpeers', 'videoassessment');
        $mform->setExpanded('assignpeerssection', false);

        if ($cm) {
            // Show link to assign peers page for existing activities.
            $peersurl = new moodle_url('/mod/videoassessment/view.php', ['id' => $cm->id, 'action' => 'peers']);
            $linkhtml = '<a class="btn btn-secondary" href="' . $peersurl->out() . '">' .
                get_string('assignpeers', 'videoassessment') . '</a>';
            $mform->addElement('static', 'assignpeerslink', '', $linkhtml);
        } else {
            // Show message for new activities.
            $mform->addElement('static', 'assignpeersinfo', '',
                get_string('assignpeersaftersave', 'videoassessment'));
        }

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Validate form data for video assessment configuration.
     *
     * Performs comprehensive validation including rating percentages,
     * date consistency, and grading limits for both quick setup and
     * advanced configuration modes.
     *
     * @param array $data Form data to validate
     * @param array $files Uploaded files data
     * @return array Array of validation errors, empty if valid
     */
    public function validation($data, $files) {
        // Allow plugin videoassessment types to do any extra validation after the form has been submitted.
        $errors = parent::validation($data, $files);

        $ratingsum = $data['ratingteacher'] + $data['ratingself'] + $data['ratingpeer'] + $data['ratingclass'];
        if ($ratingsum != 100) {
            $errors['ratingerror'] = get_string('settotalratingtoahundredpercent', 'videoassessment');
        }

        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['duedate'])) {
            if ($data['duedate'] < $data['allowsubmissionsfromdate']) {
                $errors['duedate'] = get_string('duedatevalidation', 'assign');
            }
        }
        if (!empty($data['cutoffdate']) && !empty($data['duedate'])) {
            if ($data['cutoffdate'] < $data['duedate']) {
                $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'assign');
            }
        }
        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['cutoffdate'])) {
            if ($data['cutoffdate'] < $data['allowsubmissionsfromdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'assign');
            }
        }
        if ($data['gradingduedate']) {
            if ($data['allowsubmissionsfromdate'] && $data['allowsubmissionsfromdate'] > $data['gradingduedate']) {
                $errors['gradingduedate'] = get_string('gradingduefromdatevalidation', 'assign');
            }
            if ($data['duedate'] && $data['duedate'] > $data['gradingduedate']) {
                $errors['gradingduedate'] = get_string('gradingdueduedatevalidation', 'assign');
            }
        }

        return $errors;
    }

    /**
     * Add standard grading elements to the form with video assessment specific options.
     *
     * Creates grading configuration interface including advanced grading methods,
     * training materials, fairness bonus settings, and grade categories.
     *
     * @param string $itemname Grade item name for component integration
     * @return void
     */
    public function standard_grading_coursemodule_elements_to_grading(string $itemname) {
        global $COURSE, $CFG, $DB, $PAGE;
        $mform = &$this->_form;
        $component = "mod_{$this->_modname}";
        $itemnumber = component_gradeitems::get_itemnumber_from_itemname($component, $itemname);
        $gradepassfieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'gradepass');
        if ($this->_features->hasgrades) {

            if (!$this->_features->rating || $this->_features->gradecat) {
                $mform->addElement('header', 'modstandardgrade', get_string('grade', 'videoassessment'));
                $mform->addHelpButton('modstandardgrade', 'grade', 'videoassessment');
            }

            // If supports grades and grades arent being handled via ratings.
            if (!$this->_features->rating) {
                $mform->addElement('modgrade', 'grade', get_string('modgrade', 'videoassessment'));
                $mform->addHelpButton('grade', 'modgrade', 'videoassessment');
                $mform->setDefault('grade', $CFG->gradepointdefault);
            }

            if ($this->_features->advancedgrading
                && !empty($this->current->_advancedgradingdata['methods'])
                && !empty($this->current->_advancedgradingdata['areas'])) {

                if (count($this->current->_advancedgradingdata['areas']) == 1) {
                    // If there is just one gradable area (most cases), display just the selector
                    // without its name to make UI simplier.
                    $areadata = reset($this->current->_advancedgradingdata['areas']);
                    $areaname = key($this->current->_advancedgradingdata['areas']);
                    $mform->addElement(
                        'select',
                        'advancedgradingmethod_' . $areaname,
                        get_string('advancedgradingmethodsgroup', 'videoassessment'),
                        $this->current->_advancedgradingdata['methods']
                    );
                    $mform->addHelpButton('advancedgradingmethod_' . $areaname, 'gradingmethod', 'core_grading');
                } else {
                    // The module defines multiple gradable areas, display a selector
                    // for each of them together with a name of the area.
                    $areasgroup = [];
                    foreach ($this->current->_advancedgradingdata['areas'] as $areaname => $areadata) {
                        $areasgroup[] = $mform->createElement(
                            'select',
                            'advancedgradingmethod_' . $areaname,
                            $areadata['title'],
                            $this->current->_advancedgradingdata['methods']
                        );
                        $areasgroup[] = $mform->createElement(
                            'static',
                            'advancedgradingareaname_' . $areaname,
                            '',
                            $areadata['title']
                        );
                        $mform->setDefault(
                            'advancedgradingmethod_' . $areaname,
                            $this->current->{'advancedgradingmethod_'.$areaname},
                        );
                    }
                    $mform->addGroup(
                        $areasgroup,
                        'advancedgradingmethodsgroup',
                        get_string('advancedgradingmethodsgroup', 'videoassessment'),
                        [' ', '<br />'],
                        false
                    );
                    $mform->addHelpButton('advancedgradingmethodsgroup', 'advancedgradingmethodsgroup', 'videoassessment');
                }
            }
            $mform->addElement(
                'filemanager',
                'trainingvideo',
                get_string('trainingvideo', 'videoassessment'),
                null,
                [
                    'subdirs' => 0,
                    'maxbytes' => $COURSE->maxbytes,
                    'maxfiles' => 1,
                    'accepted_types' => ['video', 'audio'],
                ],
            );
            $mform->addElement('hidden', 'trainingvideoid');
            $mform->setType('trainingvideoid', PARAM_INT);
            $mform->addHelpButton('trainingvideo', 'trainingvideo', 'videoassessment');

            $mform->addElement(
                'textarea',
                'trainingdesc',
                get_string('trainingdesc', 'videoassessment'),
                ['cols' => 50, 'rows' => 8]
            );
            $mform->setDefault('trainingdesc', get_string('trainingdesctext', 'videoassessment'));
            $mform->addHelpButton('trainingdesc', 'trainingdesc', 'videoassessment');

            for ($i = 100; $i >= 0; $i--) {
                $ratingopts[$i] = $i . '%';
            }
            $mform->addElement('select', 'accepteddifference', get_string('accepteddifference', 'videoassessment'), $ratingopts);
            $mform->setDefault('accepteddifference', 20);
            $mform->addHelpButton('accepteddifference', 'accepteddifference', 'videoassessment');

            if ($this->_features->gradecat) {
                $mform->addElement(
                    'select',
                    'gradecat',
                    get_string('gradecategory', 'videoassessment'),
                    grade_get_categories_menu($COURSE->id, $this->_outcomesused)
                );
                $mform->addHelpButton('gradecat', 'gradecategoryonmodform', 'grades');
            }

            // Grade to pass.
            $mform->addElement('text', $gradepassfieldname, get_string('gradepass', 'grades'));
            $mform->addHelpButton($gradepassfieldname, 'gradepass', 'grades');
            $mform->setType($gradepassfieldname, PARAM_RAW);
        }
    }

    /**
     * Add video management interface elements to the form.
     *
     * Creates management links for video upload, deletion, association,
     * assessment, and rubric management for teachers.
     *
     * @return void
     */
    public function manage_video() {
        global $COURSE, $CFG, $DB, $PAGE;

        $cm = $PAGE->cm;

        if (!$cm) {
            return;
        }

        $viewurl = new moodle_url('/mod/videoassessment/view.php', ['id' => $cm->id]);
        $context = context_module::instance($cm->id);

        $va = $DB->get_record('videoassessment', ['id' => $cm->instance]);
        $course = $DB->get_record('course', ['id' => $va->course]);

        require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');
        $vaobj = new va($context, $cm, $course);
        $isteacher = $vaobj->is_teacher();

        $mform = &$this->_form;
        $mform->addElement('header', 'managevideos', get_string('managevideos', 'videoassessment'));
        $mform->addHelpButton('managevideos', 'managevideos', 'videoassessment');
        if ($isteacher) {
            if (va::uses_mobile_upload()) {
                $this->add_link_element(
                    'takevideo',
                    new moodle_url($viewurl, ['action' => 'upload', 'actionmodel' => 2]),
                    get_string('takevideo', 'videoassessment'),
                );
            } else {
                $this->add_link_element(
                    'uploadvideo',
                    new moodle_url($viewurl, ['action' => 'upload', 'actionmodel' => 2]),
                    get_string('uploadvideo', 'videoassessment'),
                );
                $this->add_link_element(
                    'videoassessment:bulkupload',
                    new moodle_url('/mod/videoassessment/bulkupload/index.php', ['cmid' => $cm->id]),
                    get_string('videoassessment:bulkupload', 'videoassessment'),
                );
            }
            $this->add_link_element(
                'deletevideos',
                new moodle_url('/mod/videoassessment/deletevideos.php', ['id' => $cm->id]),
                get_string('deletevideos', 'videoassessment'),
            );
            $this->add_link_element(
                'associate',
                new moodle_url($viewurl, ['action' => 'videos']),
                get_string('associate', 'videoassessment'),
            );
            $this->add_link_element(
                'assess',
                $viewurl,
                get_string('assess', 'videoassessment'),
            );
            $this->add_link_element(
                'publishvideos',
                new moodle_url($viewurl, ['action' => 'publish']),
                get_string('publishvideos', 'videoassessment'),
            );
            $this->add_link_element(
                'assignclass',
                new moodle_url('/mod/videoassessment/assignclass/index.php', ['id' => $cm->id]),
                get_string('assignclass', 'videoassessment'),
            );
            $this->add_link_element(
                'duplicaterubric',
                new moodle_url('/mod/videoassessment/rubric/duplicate.php', ['id' => $cm->id]),
                get_string('duplicaterubric', 'videoassessment'),
            );
        }
    }

    /**
     * Add notification configuration elements to the form.
     *
     * Creates comprehensive notification settings including teacher comments,
     * peer assessments, reminder notifications, and video upload alerts.
     *
     * @return void
     */
    public function add_notifications() {
        global $PAGE;
        $mform = &$this->_form;

        $mform->addElement('header', 'notifications', get_string('notifications', 'videoassessment'));
        $mform->addHelpButton('notifications', 'notifications', 'videoassessment');
        $notificationscarriergroup[] = $mform->createElement(
            'advcheckbox',
            'isregisteredemail',
            "",
            get_string('registeredemail', 'videoassessment'),
        );
        $mform->setDefault('isregisteredemail', 0);
        $notificationscarriergroup[] = $mform->createElement(
            'advcheckbox',
            'ismobilequickmail',
            "",
            get_string('mobilequickmail', 'videoassessment'),
        );
        $mform->setDefault('ismobilequickmail', 0);
        $mform->addGroup(
            $notificationscarriergroup,
            'notificationcarriergroup',
            get_string('notificationcarriergroup', 'videoassessment'),
            [' ', '<br />'],
            false,
        );
        $mform->addHelpButton('notificationcarriergroup', 'notificationcarriergroup', 'videoassessment');

        $mform->addElement(
            'advcheckbox',
            'teachercommentnotification',
            get_string('teachercommentnotification', 'videoassessment'),
            '<b>'.
            get_string('teachercomentnotificationlabel', 'videoassessment') .
            '</b><label class="teacher-notification-displaybtn collapsed"></label>',
        );
        $mform->setDefault('teachercommentnotification', 0);
        $mform->addHelpButton('teachercommentnotification', 'teachercommentnotification', 'videoassessment');
        $teachernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>1.'. get_string('whentosendnotification', 'videoassessment') .'</b></div>',
        );
        $teachernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isfirstassessmentbyteacher',
            "",
            get_string('firstassessmentbyteacher', 'videoassessment'),
        );
        $teachernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isadditionalassessment',
            "",
            get_string('additionalassessmentbyteacher', 'videoassessment'),
        );
        $teachernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>2.' . get_string('whatinfomationtosend', 'videoassessment') . '</b></div>',
            '',
        );
        $teachernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            get_string('whatinfomationtosendcontents', 'videoassessment'),
        );
        $teachernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>3.'. get_string('templatetextfornotification', 'videoassessment') .'</b></div>',
        );
        $teachernotificationgroup[] = $mform->createElement(
            'textarea',
            'teachernotificationtemplate',
            "",
            ['rows' => 10, 'cols' => 80],
        );
        $mform->setDefault('teachernotificationtemplate', get_string('teachernotificationtemplate', 'videoassessment'));
        $mform->addGroup($teachernotificationgroup, 'teachernotificationgroup', "", [' <br/>', '<br/>'], false);

        $mform->addElement(
            'advcheckbox',
            'peercommentnotification',
            '',
            '<b>'.
            get_string('peercomentnotificationlabel', 'videoassessment') .
            '</b><label class="teacher-notification-displaybtn collapsed"></label>',
        );
        $mform->setDefault('peercommentnotification', 0);
        $peernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>1.'. get_string('whentosendnotification', 'videoassessment') .'</b></div>',
        );
        $peernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isfirstassessmentbystudent',
            "",
            get_string('firstassessmentbystudent', 'videoassessment'),
        );
        $peernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>2.' . get_string('whatinfomationtosend', 'videoassessment') . '</b></div>',
            '',
        );
        $peernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            get_string('whatinfomationtosendcontents', 'videoassessment'),
        );
        $peernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>3.'. get_string('templatetextfornotification', 'videoassessment') .'</b></div>',
        );
        $peernotificationgroup[] = $mform->createElement(
            'textarea',
            'peertnotificationtemplate',
            "",
            ['rows' => 10, 'cols' => 80],
        );
        $mform->setDefault('peertnotificationtemplate', get_string('peertnotificationtemplate', 'videoassessment'));
        $mform->addGroup($peernotificationgroup, 'peernotificationgroup', "", [' <br/>', '<br/>'], false);

        $duadate = ["1" => 1, "2" => 2, "3" => 3, "4" => 4, "5" => 5];
        $mform->addElement(
            'advcheckbox',
            'remindernotification',
            "",
            '<b>'. get_string('remindernotification', 'videoassessment') .
            '</b><label class="reminder-notification-displaybtn collapsed"></label>',
        );
        $mform->setDefault('remindernotification', 0);
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>1.'. get_string('whentosendnotification', 'videoassessment') .'</b></div>',
        );
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isbeforeduedate',
            "",
            get_string('beforeduedate', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement(
            'select',
            'beforeduedate',
            get_string('daysbefore', 'videoassessment'),
            $duadate,
        );
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<span class="form-check-inline fitem" style="width: auto;">' .
            get_string('daysbefore', 'videoassessment') .'</span>',
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isonduedate',
            "",
            get_string('onduedate', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isafterduedate',
            "",
            get_string('afterduedateevery', 'videoassessment'),
            ['group' => 1]
        );
        $remindernotificationgroup[] = $mform->createElement('select', 'afterduedate', "", $duadate);
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<span class="form-check-inline fitem" style="width: auto;">' .
            get_string('days') . '</span>',
        );
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>2.' . get_string('whatinfomationtosend', 'videoassessment') . '</b></div>',
            '',
        );
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isnovideouploaded',
            "",
            get_string('onvideouploaded', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isnoselfassessment',
            "",
            get_string('onselfassessment', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isnoselfassessmentwithcomments',
            "",
            get_string('onselfassessmentwithcomments', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement('static', '', null, '</br>');
        $remindernotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isnopeerassessment',
            "",
            get_string('onpeerassessment', 'videoassessment'),
        );
        $remindernotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>3.'. get_string('templatetextfornotification', 'videoassessment') .'</b></div>',
        );
        $remindernotificationgroup[] = $mform->createElement(
            'textarea',
            'remindernotificationtemplate',
            "",
            ['rows' => 10, 'cols' => 80]);
        $mform->setDefault('remindernotificationtemplate', get_string('remindernotificationtemplate', 'videoassessment'));
        $mform->addGroup($remindernotificationgroup, 'remindernotificationgroup', "", ['', ' '], false);

        $mform->addElement(
            'advcheckbox',
            'videonotification',
            "",
            '<b>' . get_string('videouploadnotificationlabel', 'videoassessment') .
            '</b><label class="video-notification-displaybtn collapsed"></label>',
        );
        $mform->setDefault('videonotification', 0);
        $videonotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>1.'. get_string('whentosendnotification', 'videoassessment') .'</b></div>',
        );
        $videonotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'isfirstupload',
            "",
            get_string('videouploadforthefirsttime', 'videoassessment'),
        );
        $videonotificationgroup[] = $mform->createElement(
            'advcheckbox',
            'iswheneverupload',
            "",
            get_string('whenevervideoupload', 'videoassessment'),
        );
        $videonotificationgroup[] = $mform->createElement(
            'static',
            '',
            null,
            '<div class="max-with"><b>2.'. get_string('templatetextfornotification', 'videoassessment') .'</b></div>',
        );
        $videonotificationgroup[] = $mform->createElement(
            'textarea',
            'videonotificationtemplate',
            "",
            ['rows' => 10, 'cols' => 80],
        );
        $mform->setDefault('videonotificationtemplate', get_string('videonotificationtemplate', 'videoassessment'));
        $mform->addGroup($videonotificationgroup, 'videonotificationgroup', "", [' <br/>', '<br/>'], false);

        $PAGE->requires->js_call_amd('mod_videoassessment/mod_form', 'initNotificationFormChange');
        $PAGE->requires->css(new \moodle_url('/mod/videoassessment/mod_form.css'));
    }

    /**
     * Add a link element to the form for management actions.
     *
     * Creates clickable link elements with help buttons for various
     * video assessment management functions.
     *
     * @param string $linkname Name identifier for the link element
     * @param moodle_url $href URL for the link destination
     * @param string $linktext Display text for the link
     * @return void
     */
    private function add_link_element($linkname, $href, $linktext) {
        $mform = &$this->_form;
        $mform->addGroup([], $linkname . 'group', "<a class='managelink' href='$href'>$linktext</a>", null, false);
        $mform->addHelpButton($linkname . 'group', $linkname, 'videoassessment');
    }
}
