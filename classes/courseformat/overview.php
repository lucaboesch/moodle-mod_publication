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

namespace mod_publication\courseformat;

use cm_info;
use core\url;
use core_calendar\output\humandate;
use core\output\local\properties\text_align;
use core_courseformat\activityoverviewbase;
use core_courseformat\local\overview\overviewitem;
use core_courseformat\output\local\overview\overviewaction;

/**
 * Activity overview integration for mod_publication.
 *
 * @package    mod_publication
 * @copyright  2026 Simeon Naydenov <moniNaydenov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends activityoverviewbase {
    /** @var \publication The publication instance. */
    private \publication $publication;

    /** @var ?overviewitem Cached completion overview item (moved to end of extra items). */
    private ?overviewitem $completionitem = null;

    /**
     * Constructor.
     *
     * @param cm_info $cm The course module instance.
     */
    public function __construct(cm_info $cm) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/publication/locallib.php');

        parent::__construct($cm);
        $this->publication = new \publication($this->cm, $this->course, $this->context);
    }

    #[\Override]
    public function get_due_date_overview(): ?overviewitem {
        $instance = $this->publication->get_instance();
        $duedate = $instance->duedate;

        // Check for user/group override.
        $override = $this->publication->override_get_currentuserorgroup();
        if ($override && $override->submissionoverride && $override->duedate > 0) {
            $duedate = $override->duedate;
        }

        if (empty($duedate)) {
            return new overviewitem(
                name: get_string('duedate', 'publication'),
                value: null,
                content: '-',
            );
        }

        return new overviewitem(
            name: get_string('duedate', 'publication'),
            value: $duedate,
            content: humandate::create_from_timestamp($duedate),
        );
    }

    #[\Override]
    public function get_actions_overview(): ?overviewitem {
        if (!has_capability('mod/publication:approve', $this->context)) {
            return null;
        }

        $instance = $this->publication->get_instance();
        $pendingcount = 0;
        $actiontext = get_string('view');
        $alertlabel = get_string('approval_required', 'publication');

        if ($instance->obtainteacherapproval == 1) {
            $pendingcount = $this->publication->get_allfilestable(PUBLICATION_FILTER_APPROVALREQUIRED, true)->get_count();
            if ($pendingcount > 0) {
                $actiontext = get_string('giveapproval', 'publication');
            }
        }

        $content = new overviewaction(
            url: new url('/mod/publication/view.php', [
                'id' => $this->cm->id,
                'filter' => PUBLICATION_FILTER_APPROVALREQUIRED,
                'allfilespage' => 1,
            ]),
            text: $actiontext,
            badgevalue: $pendingcount > 0 ? (string)$pendingcount : null,
            badgetitle: $pendingcount > 0 ? $alertlabel : null,
        );

        return new overviewitem(
            name: get_string('actions'),
            value: $actiontext,
            content: $content,
            textalign: text_align::CENTER,
            alertcount: $pendingcount,
            alertlabel: $alertlabel,
        );
    }

    #[\Override]
    public function get_extra_overview_items(): array {
        $items = [];

        // Approvaltodate first (right after duedate) - for students only.
        $approvaltodateitem = $this->get_extra_approval_to_date_overview();
        if ($approvaltodateitem !== null) {
            $items['approvaltodate'] = $approvaltodateitem;
        }

        // Release status - for students only.
        $statusitem = $this->get_extra_release_status_overview();
        if ($statusitem !== null) {
            $items['releasestatus'] = $statusitem;
        }

        // Submissions count - for teachers only.
        $submissionsitem = $this->get_extra_submissions_overview();
        if ($submissionsitem !== null) {
            $items['submissions'] = $submissionsitem;
        }

        return $items;
    }

    /**
     * Get the submissions overview for teachers.
     *
     * @return overviewitem|null The overview item or null if user lacks capability.
     */
    private function get_extra_submissions_overview(): ?overviewitem {
        if (!has_capability('mod/publication:approve', $this->context)) {
            return null;
        }

        $submissions = $this->publication->get_allfilestable(PUBLICATION_FILTER_ALLFILES, true)->get_count();

        $mode = $this->publication->get_mode();
        if ($mode == PUBLICATION_MODE_ASSIGN_TEAMSUBMISSION) {
            $total = count($this->publication->get_groups($this->publication->get_groupingid()));
        } else {
            $total = count($this->publication->get_users([], true));
        }

        return new overviewitem(
            name: get_string('allfiles', 'publication'),
            value: $submissions,
            content: get_string('count_of_total', 'core', ['count' => $submissions, 'total' => $total]),
            textalign: text_align::END,
        );
    }

    /**
     * Get the release status overview for students.
     *
     * @return overviewitem|null The overview item or null if user lacks capability.
     */
    private function get_extra_release_status_overview(): ?overviewitem {
        global $USER;

        if (
            !has_capability('mod/publication:upload', $this->context, $USER, false)
            || has_capability('moodle/site:config', $this->context)
        ) {
            return null;
        }

        $filestable = $this->publication->get_filestable();
        $filestable->init();

        if (empty($filestable->data)) {
            return new overviewitem(
                name: get_string('publicationstatus', 'publication'),
                value: null,
                content: '-',
            );
        }

        $status = $this->get_combined_approval_status();

        return new overviewitem(
            name: get_string('publicationstatus', 'publication'),
            value: $status,
            content: $status,
        );
    }

    /**
     * Get the approval deadline overview for students.
     *
     * @return overviewitem|null The overview item or null if not applicable.
     */
    private function get_extra_approval_to_date_overview(): ?overviewitem {
        global $USER;

        // Only show for students (not admins/teachers).
        if (
            !has_capability('mod/publication:upload', $this->context, $USER, false)
            || has_capability('moodle/site:config', $this->context)
        ) {
            return null;
        }

        $instance = $this->publication->get_instance();

        // Only show when student approval is required.
        if (!$instance->obtainstudentapproval) {
            return null;
        }

        $approvaltodate = $instance->approvaltodate;

        // Check for user/group override.
        $override = $this->publication->override_get_currentuserorgroup();
        if ($override && $override->approvaloverride && $override->approvaltodate > 0) {
            $approvaltodate = $override->approvaltodate;
        }

        if (empty($approvaltodate)) {
            return null;
        }

        return new overviewitem(
            name: get_string('approvaltodate', 'publication'),
            value: $approvaltodate,
            content: humandate::create_from_timestamp($approvaltodate),
        );
    }

    /**
     * Calculate the combined approval status for the current user's files.
     *
     * @return string The status string.
     */
    private function get_combined_approval_status(): string {
        global $USER;

        $instance = $this->publication->get_instance();
        $contextid = $this->context->id;

        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_publication', 'attachment', $USER->id, 'timemodified', false);

        if (empty($files)) {
            return '-';
        }

        $hasapproved = false;
        $hasrejected = false;
        $haspending = false;

        foreach ($files as $file) {
            if ($file->get_filepath() == '/resources/') {
                continue;
            }

            $studentapproved = true;
            $studentrejected = false;
            $teacherapproved = true;
            $teacherrejected = false;

            if ($instance->obtainstudentapproval == 1) {
                $studentapproval = $this->publication->student_approval($file);
                if ($studentapproval == 1) {
                    $studentapproved = true;
                } else if ($studentapproval == 2) {
                    $studentrejected = true;
                    $studentapproved = false;
                } else {
                    $studentapproved = false;
                }
            }

            if ($instance->obtainteacherapproval == 1) {
                $teacherapproval = $this->publication->teacher_approval($file);
                if ($teacherapproval == 1) {
                    $teacherapproved = true;
                } else if ($teacherapproval == 2) {
                    $teacherrejected = true;
                    $teacherapproved = false;
                } else {
                    $teacherapproved = false;
                }
            }

            if ($studentapproved && $teacherapproved) {
                $hasapproved = true;
            } else if ($studentrejected || $teacherrejected) {
                $hasrejected = true;
            } else {
                $haspending = true;
            }
        }

        if ($hasrejected) {
            return get_string('rejected', 'publication');
        }
        if ($haspending) {
            return get_string('approval_required', 'publication');
        }
        if ($hasapproved) {
            return get_string('approved', 'publication');
        }

        return '-';
    }
}
