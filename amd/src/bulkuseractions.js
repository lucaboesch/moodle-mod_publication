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
 * based on the selection of users via the checkboxes from the allfilestable.
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
         * Enable/disable the Go button and dropdown based on selection of users.
         */
        function updateWithSelectControls() {
            var anyChecked = $('.userselection:checked').length > 0;
            $('#withselect-go').prop('disabled', !anyChecked);
            $('#withselect-action').prop('disabled', !anyChecked);
        }
        // Listen for changes on checkboxes.
        $(document).on('change', '.userselection', updateWithSelectControls);
        // Also run on page load.
        $(updateWithSelectControls);
        // If select all/none checkbox clicked, update.
        $(document).on('click', '#selectallnone', function() {
            var checked = $(this).prop('checked');
            $('.userselection').prop('checked', checked);
            updateWithSelectControls();
        });
    };
    return BulkUserActions;
});
