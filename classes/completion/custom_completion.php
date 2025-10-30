<?php
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

namespace mod_publication\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the publication activity.
 *
 * Class for defining mod_publication's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given publication instance and a user.
 *
 * @package   mod_publication
 * @copyright Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/publication/locallib.php');
        require_once($CFG->libdir . '/grouplib.php');

        $this->validate_rule($rule);

        $cm = $this->cm;

        $userid = $this->userid;

        $publication = new \publication($cm, $cm->course, \context_module::instance($cm->id));
        $mode = $publication->get_mode();
        $status = COMPLETION_UNKNOWN;
        if ($rule == 'completionupload' && $publication->get_instance()->completionupload) {
            if ($mode == PUBLICATION_MODE_FILEUPLOAD) {
                $filescount = $DB->count_records('publication_file', [
                    'publication' => $publication->get_instance()->id,
                    'userid' => $userid,
                ]);
                $status = $filescount > 0 ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
            } else {
                $status = COMPLETION_COMPLETE;
            }
        } else if ($rule == 'completionassignsubmission'  && $publication->get_instance()->completionassignsubmission) {
            if ($mode == PUBLICATION_MODE_FILEUPLOAD) {
                $status = COMPLETION_COMPLETE;
            } else if ($mode == PUBLICATION_MODE_ASSIGN_IMPORT) {
                $filescount = $DB->count_records('publication_file', [
                    'publication' => $publication->get_instance()->id,
                    'userid' => $userid,
                ]);
                $status = $filescount > 0 ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
            } else if ($mode == PUBLICATION_MODE_ASSIGN_TEAMSUBMISSION) {
                $status = COMPLETION_INCOMPLETE;
                $groups = \groups_get_user_groups($cm->course, $userid);
                $groupids = [];
                if (!empty($groups[0])) {
                    $groupids = $groups[0];
                } else if (!$publication->requiregroup()) {
                    $groupids[] = 0; // Use groupid 0 for users without groups.
                }
                if (empty($groupids)) {
                    return $status; // User is not in any group and groups are required.
                }
                foreach ($groupids as $groupid) { // Iterate over all groups of the user.
                    $filescount = $DB->count_records('publication_file', [
                        'publication' => $publication->get_instance()->id,
                        'userid' => $groupid,
                    ]);
                    if ($filescount > 0) {
                        $status = COMPLETION_COMPLETE;
                        break;
                    }
                }
            }
        }
        return $status;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionupload', 'completionassignsubmission'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionupload' => get_string('completiondetail:upload', 'publication'),
            'completionassignsubmission' => get_string('completiondetail:assignsubmission', 'publication'),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionupload',
            'completionassignsubmission',
        ];
    }
}
