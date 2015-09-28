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
 * @package    block_databasetags
 * @subpackage tag
 * @copyright  2015 onwards Andrew Hancox (andrewdchancox@googlemail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_databasetags extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_databasetags');
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function has_config() {
        return false;
    }

    public function applicable_formats() {
        return array('all' => true);
    }

    public function instance_allow_config() {
        return true;
    }

    public function specialization() {
        // Load userdefined title and make sure it's never empty.
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_databasetags');
        } else {
            $this->title = $this->config->title;
        }
    }

    public function get_content() {
        if (!isset($this->config)) {
            $this->config = new stdClass();
        }

        if (empty($this->config->numberofdatabasetags)) {
            $this->config->numberofdatabasetags = 80;
        }

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        $fieldids = $this->get_fieldids();
        $tags = $this->get_tags($fieldids);
        $this->content->text = $this->tag_print_cloud($tags, $this->config->numberofdatabasetags);

        return $this->content;
    }

    private function get_fieldids() {
        $config = (array)$this->config;

        $fieldids = array();
        foreach (array_keys($config) as $setting) {
            if (strpos($setting, 'field_') !== false) {
                $fieldids[] = substr($setting, 6);
            }
        }

        return $fieldids;
    }

    private static function can_access_course($courseid) {
        global $DB;
        static $cache = array();

        if (!array_key_exists($courseid, $cache)) {
            $course = $DB->get_record('course', array('id'=>$courseid));
            $cache[$courseid] = can_access_course($course);
        }

        return $cache[$courseid];
    }

    public static function get_tags($fieldids) {
        global $DB;

        if (empty($fieldids)) {
            return array();
        }

        list($insql, $params) = $DB->get_in_or_equal($fieldids);
        $sql = "
        SELECT dc.id, dc.content, df.name as fieldname, df.type as fieldtype, df.id as fieldid, df.dataid, d.course as courseid
        FROM {data_content} dc
        INNER JOIN {data_fields} df ON dc.fieldid = df.id
        INNER JOIN {data} d ON d.id = df.dataid
        WHERE dc.fieldid $insql
        ";
        $rawtags = $DB->get_records_sql($sql, $params);

        $splittags = array();
        foreach ($rawtags as $rawtag) {
            self::can_access_course($rawtag->courseid);

            if ($rawtag->fieldtype == 'tag') {
                $seperator = ',';
            } else if ($rawtag->fieldtype == 'checkbox' || $rawtag->fieldtype == 'linkedcheckbox') {
                $seperator = '##';
            }

            $tagsincontent = explode($seperator, $rawtag->content);
            $keyablefieldname = str_replace(' ', '_', $rawtag->fieldname);

            foreach ($tagsincontent as $tagincontent) {
                $tagincontent = trim($tagincontent);

                if (empty($tagincontent)) {
                    continue;
                }

                $key = $keyablefieldname . '_' . $tagincontent;
                if (!array_key_exists($key, $splittags)) {
                    $tag = new stdClass();
                    $tag->key = $key;
                    $tag->name = $tagincontent;
                    $tag->count = 1;
                    $tag->tagtype = $keyablefieldname;
                    $tag->fieldtype = $rawtag->fieldtype;
                    $tag->fieldid = $rawtag->fieldid;
                    $tag->dataid = $rawtag->dataid;

                    $splittags[$key] = $tag;
                } else {
                    $splittags[$key]->count += 1;
                }
            }
        }

        return $splittags;
    }

    public static function tag_print_cloud($tagsincloud, $nr_of_tags=150) {
        $maxcount = 0;
        foreach ($tagsincloud as $tag) {
            if ($tag->count > $maxcount) {
                $maxcount = $tag->count;
            }
        }

        ksort($tagsincloud);
        $tagsincloud = array_slice($tagsincloud, 0, $nr_of_tags);

        foreach ($tagsincloud as $tag) {
            $size = (int) (( $tag->count / $maxcount) * 20);
            $tag->class = "$tag->tagtype s$size";
        }

        $output = '';
        $output .= "\n<ul class='tag_cloud inline-list'>\n";
        foreach ($tagsincloud as $tag) {
            $fieldparam = "f_{$tag->fieldid}";
            if ($tag->fieldtype == 'checkbox' || $tag->fieldtype == 'linkedcheckbox') {
                $fieldparam .= '[]';
            }

            $params =  array(
                $fieldparam => $tag->name,
                'd' => $tag->dataid,
                'advanced' => 1
            );
            $url = new moodle_url('/mod/data/view.php', $params);
            $link = html_writer::link($url, $tag->name, array('class' => $tag->class));
            $output .= '<li>' . $link . '</li> ';
        }
        $output .= "\n</ul>\n";

        return $output;
    }
}
