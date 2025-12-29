/**
 * Peer assignment management for video assessment
 *
 * @module     mod_videoassessment/peer_assignment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    let students = {};
    let peerAssignments = {};
    let isExisting = false;
    let cmid = 0;
    let sesskey = '';
    let usedpeers = 0;
    let groups = {};

    /**
     * Update the hidden form field with current peer assignments.
     */
    function updateHiddenField() {
        const jsonValue = JSON.stringify(peerAssignments);
        // Try multiple selectors to find the hidden field.
        let field = $('#id_peerassignments');
        if (field.length === 0) {
            field = $('input[name="peerassignments"]');
        }
        console.log('Updating hidden field. Field found:', field.length > 0, 'Value:', jsonValue);
        if (field.length > 0) {
            field.val(jsonValue);
            console.log('Field value after update:', field.val());
        } else {
            console.error('Hidden field not found! Trying to create one...');
            // Create the hidden field if it doesn't exist.
            $('form.mform').append('<input type="hidden" name="peerassignments" id="id_peerassignments" value="' + jsonValue.replace(/"/g, '&quot;') + '">');
            console.log('Created hidden field with value:', jsonValue);
        }
    }

    /**
     * Get the current maximum number of peers allowed per user.
     *
     * @return {number} The maximum number of peers (0 = unlimited)
     */
    function getMaxPeers() {
        const val = parseInt($('#id_usedpeers').val());
        return isNaN(val) ? usedpeers : val;
    }

    /**
     * Add a peer to a user.
     *
     * @param {number} userid - The user ID
     * @param {number} peerid - The peer ID to add
     */
    function addPeer(userid, peerid) {
        console.log('Adding peer:', peerid, 'to user:', userid);
        if (!peerAssignments[userid]) {
            peerAssignments[userid] = [];
        }

        // Check if we've reached the maximum number of peers.
        const maxPeers = getMaxPeers();
        if (maxPeers > 0 && peerAssignments[userid].length >= maxPeers) {
            console.log('Maximum peers reached for user:', userid, '(limit:', maxPeers, ')');
            return; // Don't add more peers.
        }

        if (peerAssignments[userid].indexOf(peerid) === -1) {
            peerAssignments[userid].push(peerid);
        }

        console.log('Current peerAssignments:', peerAssignments);
        renderPeersForUser(userid);
        updateHiddenField();
    }

    /**
     * Remove a peer from a user.
     *
     * @param {number} userid - The user ID
     * @param {number} peerid - The peer ID to remove
     */
    function removePeer(userid, peerid) {
        if (peerAssignments[userid]) {
            const index = peerAssignments[userid].indexOf(peerid);
            if (index > -1) {
                peerAssignments[userid].splice(index, 1);
            }
        }

        renderPeersForUser(userid);
        updateHiddenField();
    }

    /**
     * Render the peers display for a specific user.
     *
     * @param {number} userid - The user ID
     */
    function renderPeersForUser(userid) {
        const container = $('#assigned-peers-' + userid);
        container.empty();

        const userPeers = peerAssignments[userid] || [];
        const maxPeers = getMaxPeers();
        const atLimit = maxPeers > 0 && userPeers.length >= maxPeers;

        userPeers.forEach(function(peerid) {
            if (students[peerid]) {
                const badge = $('<span class="peer-badge badge badge-secondary mr-1 mb-1"></span>')
                    .attr('data-peerid', peerid)
                    .css({'display': 'inline-block', 'margin': '2px'});

                badge.text(students[peerid] + ' ');

                const removeLink = $('<a href="#" class="remove-peer text-white">×</a>')
                    .attr('data-userid', userid)
                    .attr('data-peerid', peerid)
                    .css({'text-decoration': 'none', 'font-weight': 'bold'});

                badge.append(removeLink);
                container.append(badge);
            }
        });

        // Update the dropdown - disable if at limit or if peer already assigned.
        const select = $('select.add-peer-select[data-userid="' + userid + '"]');

        if (atLimit) {
            // Disable the entire dropdown when limit is reached.
            select.prop('disabled', true);
            select.css('opacity', '0.5');
        } else {
            select.prop('disabled', false);
            select.css('opacity', '1');

            // Enable/disable individual options based on whether already assigned.
            select.find('option').each(function() {
                const optionValue = parseInt($(this).val());
                if (optionValue && userPeers.indexOf(optionValue) > -1) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
        }
        select.val('');
    }

    /**
     * Assign peers randomly across all students (course-wide).
     */
    function assignRandomPeersCourse() {
        // Get number of peers from the usedpeers input.
        let numPeers = parseInt($('#id_usedpeers').val()) || usedpeers;
        if (numPeers <= 0) {
            numPeers = 1; // Default to 1 if not set.
        }

        const studentIds = Object.keys(students).map(Number);

        if (studentIds.length <= numPeers) {
            alert('Not enough students to assign ' + numPeers + ' peers each.');
            return;
        }

        // Confirm before proceeding.
        if (!confirm('This will reset all peer assignments and assign ' + numPeers + ' random peer(s) to each student. Continue?')) {
            return;
        }

        // Clear existing assignments.
        peerAssignments = {};

        // Simple random assignment algorithm.
        studentIds.forEach(function(userid) {
            peerAssignments[userid] = [];
            const availablePeers = studentIds.filter(function(id) {
                return id !== userid;
            });

            // Shuffle available peers.
            for (let i = availablePeers.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [availablePeers[i], availablePeers[j]] = [availablePeers[j], availablePeers[i]];
            }

            // Assign the first numPeers.
            peerAssignments[userid] = availablePeers.slice(0, numPeers);
        });

        // Re-render all users.
        studentIds.forEach(function(userid) {
            renderPeersForUser(userid);
        });

        updateHiddenField();
    }

    /**
     * Assign peers randomly within a specific group.
     *
     * @param {number} groupId - The group ID to assign peers within
     */
    function assignRandomPeersGroup(groupId) {
        // Get number of peers from the usedpeers input.
        let numPeers = parseInt($('#id_usedpeers').val()) || usedpeers;
        if (numPeers <= 0) {
            numPeers = 1; // Default to 1 if not set.
        }

        // Get group data.
        const group = groups[groupId];
        if (!group) {
            alert('Group not found.');
            return;
        }

        const groupMembers = group.members || [];
        if (groupMembers.length <= numPeers) {
            alert('Not enough students in group "' + group.name + '" to assign ' + numPeers + ' peers each.');
            return;
        }

        // Confirm before proceeding.
        if (!confirm('This will reset peer assignments for students in "' + group.name + '" and assign ' + numPeers + ' random peer(s) from within the group. Continue?')) {
            return;
        }

        // Clear existing assignments for group members only.
        groupMembers.forEach(function(userid) {
            peerAssignments[userid] = [];
        });

        // Assign peers only from within the group.
        groupMembers.forEach(function(userid) {
            const availablePeers = groupMembers.filter(function(id) {
                return id !== userid;
            });

            // Shuffle available peers.
            for (let i = availablePeers.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [availablePeers[i], availablePeers[j]] = [availablePeers[j], availablePeers[i]];
            }

            // Assign the first numPeers.
            peerAssignments[userid] = availablePeers.slice(0, numPeers);
        });

        // Re-render group members only.
        groupMembers.forEach(function(userid) {
            renderPeersForUser(userid);
        });

        updateHiddenField();
    }

    /**
     * Initialize the peer assignment interface.
     *
     * @param {Object} params - Initialization parameters
     */
    function init(params) {
        // Wait for document to be ready.
        $(function() {
            try {
                console.log('Peer assignment init called with params:', params);
                students = params.students || {};
                peerAssignments = params.existingPeers || {};
                isExisting = params.isExisting || false;
                cmid = params.cmid || 0;
                sesskey = params.sesskey || '';
                usedpeers = params.usedpeers || 0;
                groups = params.groups || {};

                console.log('Students:', students);
                console.log('Groups:', groups);
                console.log('Initial peerAssignments:', peerAssignments);

                // Convert peer arrays to have numeric keys.
                const numericPeers = {};
                Object.keys(peerAssignments).forEach(function(key) {
                    numericPeers[parseInt(key)] = peerAssignments[key].map(Number);
                });
                peerAssignments = numericPeers;

                console.log('After conversion peerAssignments:', peerAssignments);

                // Initialize the hidden field.
                updateHiddenField();

                // Handle add peer dropdown change (using event delegation).
                $(document).off('change.peerassign', '.add-peer-select');
                $(document).on('change.peerassign', '.add-peer-select', function() {
                    const userid = parseInt($(this).data('userid'));
                    const peerid = parseInt($(this).val());

                    if (peerid) {
                        addPeer(userid, peerid);
                    }
                });

                // Handle remove peer click (using event delegation).
                $(document).off('click.peerassign', '.remove-peer');
                $(document).on('click.peerassign', '.remove-peer', function(e) {
                    e.preventDefault();
                    const userid = parseInt($(this).data('userid'));
                    const peerid = parseInt($(this).data('peerid'));

                    removePeer(userid, peerid);
                });

                // Handle random assignment - Course button.
                const courseBtn = $('#random-peers-course');
                if (courseBtn.length) {
                    courseBtn.off('click.peerassign');
                    courseBtn.on('click.peerassign', function(e) {
                        e.preventDefault();
                        assignRandomPeersCourse();
                    });
                }

                // Handle random assignment - Group dropdown items.
                $(document).off('click.peerassign', '.random-peers-group-item');
                $(document).on('click.peerassign', '.random-peers-group-item', function(e) {
                    e.preventDefault();
                    const groupId = parseInt($(this).data('groupid'));
                    assignRandomPeersGroup(groupId);
                });

                // Listen for changes to Number of Peer Assessors to trim excess peers and update dropdowns.
                const usedPeersInput = $('#id_usedpeers');
                if (usedPeersInput.length) {
                    usedPeersInput.on('change keyup blur', function() {
                        const maxPeers = getMaxPeers();
                        
                        // If maxPeers > 0, trim excess peers from all users.
                        if (maxPeers > 0) {
                            Object.keys(peerAssignments).forEach(function(userid) {
                                const userPeers = peerAssignments[userid] || [];
                                if (userPeers.length > maxPeers) {
                                    // Remove excess peers from the end.
                                    peerAssignments[userid] = userPeers.slice(0, maxPeers);
                                }
                            });
                        }
                        
                        // Re-render all users to update UI.
                        Object.keys(students).forEach(function(userid) {
                            renderPeersForUser(parseInt(userid));
                        });
                        
                        // Update the hidden field with trimmed assignments.
                        updateHiddenField();
                    });
                }

                // Ensure hidden field is updated before form submission.
                const form = $('form.mform');
                if (form.length) {
                    form.off('submit.peerassign');
                    form.on('submit.peerassign', function() {
                        updateHiddenField();
                    });
                }

                // Also update on any form button click (Save, Cancel, etc.)
                const submitBtns = $('form.mform input[type="submit"]');
                if (submitBtns.length) {
                    submitBtns.off('click.peerassign');
                    submitBtns.on('click.peerassign', function() {
                        updateHiddenField();
                    });
                }

                console.log('Peer assignment initialization complete');
            } catch (error) {
                console.error('Error initializing peer assignment:', error);
            }
        });
    }

    return {
        init: init
    };
});

