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
 * Video assessment
 *
 * @package
 * @module     mod_videoassessment/mod_form
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function ($) {

    /**
     * Initializes the behavior of the training selector.
     *
     * Shows or hides the training video, accepted difference, and description
     * fields based on the selected training option.
     */
    function initTrainingChange() {
        const training = $('#id_training');
        const video = $('#fitem_id_trainingvideo');
        const point = $('#fitem_id_accepteddifference');
        const desc = $('#fitem_id_trainingdesc');

        if (training.length) {
            if (training.val() != 1) {
                video.hide();
                point.hide();
                desc.hide();
            }

            training.on('change', function () {
                if ($(this).val() == 1) {
                    video.show();
                    point.show();
                    desc.show();
                } else {
                    video.hide();
                    point.hide();
                    desc.hide();
                }
            });
        }
    }

    /**
     * Initializes peer assessment option change handling.
     *
     * Automatically selects "1" as the number of peers when a peer assessment
     * value greater than 0 is chosen.
     */
    function initQuickSetupPeerChange() {
        const peerAssess = $('#id_peerassess');
        peerAssess.on('change', function () {
            if ($(this).val() > 0) {
                $('#id_numberofpeers').find('option[value="1"]').prop('selected', true);
            }
        });
    }

    /**
     * Initializes display toggling for fairness bonus and self-fairness bonus fields.
     *
     * Shows or hides relevant bonus percentage and score fields based on whether
     * the corresponding toggle is enabled.
     */
    function initFairnessBonusChange() {
        /**
         * Toggles visibility of specified form fields based on the value of a toggle input.
         *
         * If the toggle field's value is `1`, the given fields will be shown;
         * otherwise, they will be hidden. Also attaches a change listener to update
         * visibility dynamically when the user changes the toggle input.
         *
         * @param {string} toggleId - jQuery selector for the toggle input element.
         * @param {string[]} fields - Array of jQuery selectors for the fields to show/hide.
         */
        function toggleBonusFields(toggleId, fields) {
            const toggle = $(toggleId);
            if (toggle.length) {
                const val = toggle.val();
                if (val != 1) {
                    fields.forEach(f => $(f).hide());
                }

                toggle.on('change', function () {
                    if ($(this).val() == 1) {
                        fields.forEach(f => $(f).show());
                    } else {
                        fields.forEach(f => $(f).hide());
                    }
                });
            }
        }

        toggleBonusFields('#id_fairnessbonus', [
            '#fitem_id_bonuspercentage',
            '#fgroup_id_bonusscoregroup1',
            '#fgroup_id_bonusscoregroup2',
            '#fgroup_id_bonusscoregroup3',
            '#fgroup_id_bonusscoregroup4',
            '#fgroup_id_bonusscoregroup5',
            '#fgroup_id_bonusscoregroup6'
        ]);

        toggleBonusFields('#id_selffairnessbonus', [
            '#fitem_id_selfbonuspercentage',
            '#fgroup_id_selfbonusscoregroup1',
            '#fgroup_id_selfbonusscoregroup2',
            '#fgroup_id_selfbonusscoregroup3',
            '#fgroup_id_selfbonusscoregroup4',
            '#fgroup_id_selfbonusscoregroup5',
            '#fgroup_id_selfbonusscoregroup6'
        ]);
    }

    /**
     * Initializes logic for switching between video upload types.
     *
     * Toggles visibility of relevant form fields for uploading a video file,
     * linking to YouTube, or recording a new video. Also reorders some form
     * elements for better UX on mobile.
     */
    function initUploadTypeChange() {
        const uploadRadio = $('#id_upload_0');
        const youtubeRadio = $('#id_upload_1');
        const recordNewVideo = $('#id_upload_2');

        const precent = $('#fitem_id_precent').length ? $('#fitem_id_precent') : $('#fitem_id_mobilevideo');
        const video = $('#fitem_id_video').length ? $('#fitem_id_video') : $('#fitem_id_mobilevideo');
        const url = $('#id_url').length ? $('#id_url') : $('#id_mobileurl');
        const recordContent = $('#recordrtc');
        const submitButtons = $('#fgroup_id_buttonar');

        if ($('#mobileform').length) {
            $('#fgroup_id_recordradios').hide();
        }

        $('.col-md-3').each(function () {
            if ($(this).children().length == 0) {
                $(this).remove();
            }
        });

        const rearrangeRadios = (groupId, radioId) => {
            if ($(groupId).length) {
                const radio = $(radioId).parent();
                const target = $(groupId).children('div').first();
                const label = $(groupId).find('span a');
                target.append(radio);
                target.append(label);
            }
        };

        rearrangeRadios('#fgroup_id_radios', '#id_upload_1');
        rearrangeRadios('#fgroup_id_recordradios', '#id_upload_2');

        // Default to YouTube if it exists, otherwise keep current selection.
        if (youtubeRadio.length) {
            uploadRadio.prop('checked', false);
            recordNewVideo.prop('checked', false);
            youtubeRadio.prop('checked', true);
        }

        const updateVisibility = () => {
            if (uploadRadio.is(':checked')) {
                url.hide();
                recordContent.hide();
                video.show();
                precent.show();
                submitButtons.show();
            } else if (youtubeRadio.is(':checked')) {
                video.hide();
                precent.hide();
                recordContent.hide();
                url.show();
                submitButtons.show();
            } else if (recordNewVideo.is(':checked')) {
                video.hide();
                url.hide();
                precent.hide();
                recordContent.show();
                submitButtons.hide();
            }
        };

        uploadRadio.on('change', updateVisibility);
        youtubeRadio.on('change', updateVisibility);
        recordNewVideo.on('change', updateVisibility);
        updateVisibility();
    }

    /**
     * Initializes the notification settings form toggle behavior.
     *
     * Enables expand/collapse toggling for each notification group section and
     * adjusts form field layout styles.
     */
    function initNotificationFormChange() {
        const toggleGroup = (btnClass, groupId) => {
            $(document).on('click', btnClass, function (e) {
                e.preventDefault();
                const btn = $(this);
                if (btn.hasClass('expanded')) {
                    btn.removeClass('expanded').addClass('collapsed');
                    $(groupId).hide();
                } else {
                    btn.removeClass('collapsed').addClass('expanded');
                    $(groupId).show();
                }
            });
        };

        toggleGroup('.teacher-notification-displaybtn', '#fgroup_id_teachernotificationgroup');
        toggleGroup('.reminder-notification-displaybtn', '#fgroup_id_remindernotificationgroup');
        toggleGroup('.peer-notification-displaybtn', '#fgroup_id_peernotificationgroup');
        toggleGroup('.video-notification-displaybtn', '#fgroup_id_videonotificationgroup');

        $('#fgroup_id_teachernotificationgroup').hide();
        $('#fgroup_id_remindernotificationgroup').hide();
        $('#fgroup_id_peernotificationgroup').hide();
        $('#fgroup_id_videonotificationgroup').hide();

        $('#id_isbeforeduedate').parent().css('width', 'auto');
        $('#id_isafterduedate').parent().css('width', 'auto');
    }

    return {
        initTrainingChange: initTrainingChange,
        initQuickSetupPeerChange: initQuickSetupPeerChange,
        initFairnessBonusChange: initFairnessBonusChange,
        initUploadTypeChange: initUploadTypeChange,
        initNotificationFormChange: initNotificationFormChange
    };
});
