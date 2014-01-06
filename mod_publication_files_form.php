<?php
// This plugin is for Moodle - http://moodle.org/
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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/publication/locallib.php');

/**
 * Form for displaying and changing approval for publication files
 * 
 * @package mod_publication
 * @author Andreas Windbichler
 * @copyright TSC
 */
class mod_publication_files_form extends moodleform {	
	public function definition(){
		global $CFG, $OUTPUT, $DB, $USER;
		
		$publication = &$this->_customdata['publication'];
		$sid = &$this->_customdata['sid'];
		$filearea = &$this->_customdata['filearea'];
		
		$mform = $this->_form;
		$mform->addElement('header', 'myfiles', get_string('myfiles', 'publication'));
		$mform->setExpanded('myfiles');

		if($publication->get_instance()->obtainteacherapproval){
			$notice = get_string('notice_requireapproval', 'publication');
		}else{
			$notice = get_string('notice_noapproval', 'publication');
		}
		
		$mform->addElement('static','notice',get_string('notice', 'publication'), $notice);
		
		require_once($CFG->libdir.'/tablelib.php');
		$table = new html_table();
		
		$tablecolumns = array();
		$tableheaders = array();
		
		$tablecolumns[] = 'id';
		
		$fs = get_file_storage();
		$this->dir = $fs->get_area_tree($publication->get_context()->id, 'mod_publication', $filearea, $sid);
		
		$files = $fs->get_area_files($publication->get_context()->id,
				'mod_publication',
				$filearea,
				$sid,
				'timemodified',
				false);
		
		if (!isset($table->attributes)) {
			$table->attributes = array('class' => 'coloredrows');
		} else if (!isset($table->attributes['class'])) {
			$table->attributes['class'] = 'coloredrows';
		} else {
			$table->attributes['class'] .= ' coloredrows';
		}
		
		$options = array();
		$options[2] = get_string('student_approve', 'publication');
		$options[1] = get_string('student_reject', 'publication');
		
		$conditions = array();
		$conditions['publication'] = $publication->get_instance()->id;
		$conditions['userid'] = $USER->id;
		
		foreach($files as $file){
			$conditions['fileid'] = $file->get_id();
			$studentapproval = $DB->get_field('publication_file', 'studentapproval', $conditions);
			$teacherapproval = $DB->get_field('publication_file', 'teacherapproval', $conditions);
			$blocked = $DB->get_field('publication_file', 'blocked', $conditions);
			
			$studentapproval = (!is_null($studentapproval)) ? $studentapproval + 1 : null;
		
			$data = array();
			$data[] = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file));
			
			$dlurl = new moodle_url('/mod/publication/view.php',array('id'=>$publication->get_coursemodule()->id,'download'=>$file->get_id()));
			$data[] = html_writer::link($dlurl, $file->get_filename());
			
			if($publication->get_instance()->mode == PUBLICATION_MODE_IMPORT &&
				$publication->get_instance()->obtainstudentapproval){
				if($publication->is_open()){
					$data[] = html_writer::select($options, 'studentapproval[' . $file->get_id()  . ']', $studentapproval);
				}else{
					switch($studentapproval){
						case 2: $data[] = get_string('student_approved', 'publication'); break;
						case 1: $data[] = get_string('student_rejected', 'publication'); break;
						default: $data[] = '';
					}
				}
			}
			
			if($publication->get_instance()->mode == PUBLICATION_MODE_UPLOAD){
				if(is_null($teacherapproval)){
					$data[] = get_string('teacher_pending', 'publication');
				}else if($teacherapproval == 1){
					$data[] = get_string('teacher_approved', 'publication');
				}else{
					$data[] = get_string('teacher_rejected', 'publication');
				}
			}
			
			if($blocked){
				$data[] = get_string('teacher_blocked', 'publication');
			}
			
			$table->data[] = $data;
		}
		
		if(count($files) == 0){
			$mform->addElement('static', 'nofiles', '', get_string('nofiles', 'publication'));
		}
		
		$tablehtml = html_writer::table($table);
		
		$mform->addElement('html',$tablehtml);	

		// display submit buttons if necessary
		if($publication->get_instance()->mode == PUBLICATION_MODE_IMPORT){
			if($publication->get_instance()->obtainstudentapproval){
				if($publication->is_open()){
					$buttonarray=array();
					$buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
					$buttonarray[] = &$mform->createElement('reset', 'resetbutton', get_string('revert'));
					
					$mform->addGroup($buttonarray, 'submitgrp', '', array(' '), false);
				}else{
					$mform->addElement('static','approvaltimeover', '', get_string('approval_timeover', 'publication'));
				}
			}
		}
		
		if($publication->get_instance()->mode == PUBLICATION_MODE_UPLOAD){
			if($publication->is_open()){
				$buttonarray=array();
				$buttonarray[] = &$mform->createElement('submit', 'gotoupload', get_string('edit_uploads','publication'));
				$mform->addGroup($buttonarray, 'uploadgrp', '', array(' '), false);
			}else{
				$mform->addElement('static','edittimeover', '', get_string('edit_timeover', 'publication'));
			}
		}
		
		$mform->addElement('hidden', 'id', $publication->get_coursemodule()->id);
		$mform->setType('id', PARAM_INT);
		
	}
}