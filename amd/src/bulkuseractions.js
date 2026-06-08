// This file is part of mod_publication for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JS to enable/disable Go button and dropdown of bulk actions in allfilesform
 * based on the selection of users/groups and individual files via the checkboxes
 * from the allfilestable, and to keep the user/group and file checkboxes in sync.
 *
 * @module    mod_publication/bulkuseractions
 * @author    Clemens Marx
 * @copyright 2025 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    var BulkUserActions = {};
    BulkUserActions.init = function() {
        /**
         * Enable/disable the Go button and dropdown based on whether any user/group
         * or file checkbox is selected.
         */
        function updateWithSelectControls() {
            var anyChecked = $('.userselection:checked, .fileselection:checked').length > 0;
            $('#withselect-go').prop('disabled', !anyChecked);
            $('#withselect-action').prop('disabled', !anyChecked);
        }

        /**
         * Recompute a user/group row checkbox from the state of its file checkboxes:
         * checked if all are checked, indeterminate if some are, unchecked if none.
         *
         * @param {string|number} itemid The user/group id shared via data-itemid.
         */
        function syncRowFromFiles(itemid) {
            var $row = $('.userselection[data-itemid="' + itemid + '"]');
            if ($row.length === 0) {
                return;
            }
            var $files = $('.fileselection[data-itemid="' + itemid + '"]');
            if ($files.length === 0) {
                return;
            }
            var checkedCount = $files.filter(':checked').length;
            $row.prop('checked', checkedCount === $files.length);
            $row.prop('indeterminate', checkedCount > 0 && checkedCount < $files.length);
        }

        // Checking/unchecking a user/group checkbox toggles all its file checkboxes.
        $(document).on('change', '.userselection', function() {
            var $row = $(this);
            var checked = $row.prop('checked');
            $row.prop('indeterminate', false);
            $('.fileselection[data-itemid="' + $row.data('itemid') + '"]').prop('checked', checked);
            updateWithSelectControls();
        });

        // Changing a file checkbox keeps its user/group row checkbox honest.
        $(document).on('change', '.fileselection', function() {
            syncRowFromFiles($(this).data('itemid'));
            updateWithSelectControls();
        });

        // Select all/none header checkbox toggles every user/group and file checkbox.
        $(document).on('click', '#selectallnone', function() {
            var checked = $(this).prop('checked');
            $('.userselection').prop('checked', checked).prop('indeterminate', false);
            $('.fileselection').prop('checked', checked);
            updateWithSelectControls();
        });

        // Run once on page load.
        $(updateWithSelectControls);
    };
    return BulkUserActions;
});
