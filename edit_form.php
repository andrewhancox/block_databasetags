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
 * Form for editing tag block instances.
 *
 * @package   block_databasetags
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for editing tag block instances.
 *
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_databasetags_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_databasetags'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_databasetags'));

        $numberofdatabasetags = array();
        for ($i = 1; $i <= 200; $i++) {
            $numberofdatabasetags[$i] = $i;
        }
        $mform->addElement(
            'select',
            'config_numberofdatabasetags',
            get_string('numberoftags', 'block_databasetags'),
            $numberofdatabasetags
        );
        $mform->setDefault('config_numberofdatabasetags', 80);

        $cloudablefields = $this->get_cloudablefields();
        $lastcloudablefield = null;

        $mform->addElement('header', 'fieldstodisplay', get_string('fieldstodisplay', 'block_databasetags'));
        $mform->addElement('html', html_writer::div(get_string('fieldsintro', 'block_databasetags')));

        $mform->addElement('html', '<ul>');

        foreach ($cloudablefields as $checkbox) {
            if (isset($lastcheckbox) && $lastcheckbox->categoryname != $checkbox->categoryname && !empty($lastcheckbox->categoryname)) {
                $mform->addElement('html', "</ul>");
            }
            if (isset($lastcheckbox) && $lastcheckbox->coursename != $checkbox->coursename) {
                $mform->addElement('html', "</ul>");
            }
            if (isset($lastcheckbox) && $lastcheckbox->activityname != $checkbox->activityname) {
                $mform->addElement('html', "</ul>");
            }

            if (!isset($lastcheckbox) && !empty($lastcheckbox->categoryname)) {
                $mform->addElement('html', "<li>$checkbox->categoryname</li><ul>");
            } else if (isset($lastcheckbox) && $lastcheckbox->categoryname != $checkbox->categoryname) {
                $mform->addElement('html', "<li>$checkbox->categoryname</li><ul>");
            }

            if (!isset($lastcheckbox)) {
                $mform->addElement('html', "<li>$checkbox->coursename</li><ul>");
            } else if ($lastcheckbox->coursename != $checkbox->coursename) {
                $mform->addElement('html', "<li>$checkbox->coursename</li><ul>");
            }

            if (!isset($lastcheckbox)) {
                $mform->addElement('html', "<li>$checkbox->activityname</li><ul>");
            } else if ($lastcheckbox->activityname != $checkbox->activityname) {
                $mform->addElement('html', "<li>$checkbox->activityname</li><ul>");
            }

            $mform->addElement('html', '<li>');
            $mform->addElement('checkbox', 'config_field_' . $checkbox->fieldid, $checkbox->fieldname);
            $mform->addElement('html', '</li>');

            $lastcheckbox = $checkbox;
        }
        $mform->addElement('html', '</ul></ul>');
    }

    private function get_cloudablefields() {
        global $DB;

        $cloudablefields = array('linkedcheckbox', 'checkbox', 'tag');
        list($insql, $params) = $DB->get_in_or_equal($cloudablefields);
        $params[] = $this->page->course->id;

        $sql = "
        SELECT df.id as fieldid, d.name as activityname, df.name as fieldname, c.fullname as coursename, cc.name as categoryname
        FROM {course} c
        INNER JOIN {course_modules} cm on c.id = cm.course
        LEFT JOIN {course_categories} cc on cc.id = c.category
        INNER JOIN {modules} m on m.id = cm.module
        INNER JOIN {data} d on d.id = cm.instance
        INNER JOIN {data_fields} df on df.dataid = cm.instance
        WHERE m.name = 'data' AND df.type $insql
        ORDER BY cc.name, c.fullname, d.name, df.name
        ";
        return $DB->get_records_sql($sql, $params);
    }
}
