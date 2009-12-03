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
 * Essay question renderer class.
 *
 * @package qtype_essay
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Generates the output for essay questions.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_essay_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();

        // Answer field.
        $inputname = $qa->get_qt_field_name('answer');
        $response = $qa->get_last_qt_var('answer', '');
        if (empty($options->readonly)) {
            // the student needs to type in their answer so print out a text editor
            $answer = print_textarea(can_use_html_editor(), 18, 80, 630, 400, $inputname, $response, 0, true);

        } else {
            // it is read only, so just format the students answer and output it
            $answer = $this->output_tag('div', array('class' => 'answerreview'),
                    $question->format_text($response, true));
        }

        $result = '';
        $result .= $this->output_tag('div', array('class' => 'qtext'),
                $question->format_questiontext());

        $result .= $this->output_start_tag('div', array('class' => 'ablock clearfix'));
        $result .= $this->output_tag('div', array('class' => 'prompt'),
                get_string('answer', 'question'));
        $result .= $this->output_tag('div', array('class' => 'answer'), $answer);
        $result .= $this->output_end_tag('div');

        return $result;
    }
}
