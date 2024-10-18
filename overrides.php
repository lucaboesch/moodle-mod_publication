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

/**
 * Displays a single mod_publication instance
 *
 * @package       mod_publication
 * @author        Philipp Hager
 * @author        Andreas Windbichler
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/publication/locallib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.

$url = new moodle_url('/mod/publication/overrides.php', ['id' => $id]);
$cm = get_coursemodule_from_id('publication', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, true, $cm);
$PAGE->set_url($url);

$context = context_module::instance($cm->id);

require_capability('mod/publication:manageoverrides', $context);

$publication = new publication($cm, $course, $context);

$pagetitle = strip_tags($course->shortname . ': ' . format_string($publication->get_instance()->name));

// Print the page header.
$PAGE->set_pagelayout('admin');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
$PAGE->add_body_class('limitedwidth');
$activityheader = $PAGE->activityheader;
$activityheader->set_attrs([
    'description' => '',
    'hidecompletion' => true,
    'title' => $activityheader->is_title_allowed() ? format_string($publication->get_instance()->name, true, ['context' => $context]) : ""
]);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('overrides', 'mod_assign'), 2);

$publicationinstance = $publication->get_instance();
$publicationmode = $publication->get_mode();
$templatecontext = $publication->overrides_export_for_template();

$mode = $publication->get_mode();

echo $OUTPUT->render_from_template('mod_publication/overrides', $templatecontext);


echo $OUTPUT->footer();
