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
 * Multiple choice question definition classes.
 *
 * @package qtype_multichoice
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Base class for multiple choice questions. The parts that are common to
 * single select and multiple select.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_multichoice_base extends question_graded_automatically {
    const LAYOUT_DROPDOWN = 0;
    const LAYOUT_VERTICAL = 1;
    const LAYOUT_HORIZONTAL = 2;

    public $answers;

    public $shuffleanswers;
    public $answernumbering;
    public $layout = self::LAYOUT_VERTICAL;
    public $correctfeedback;
    public $partiallycorrectfeedback;
    public $incorrectfeedback;

    protected $order = null;

    public function init_first_step(question_attempt_step $step) {
        if ($step->has_qt_var('_order')) {
            $this->order = explode(',', $step->get_qt_var('_order'));
        } else {
            $this->order = array_keys($this->answers);
            if ($this->shuffleanswers) {
                shuffle($this->order);
            }
            $step->set_qt_var('_order', implode(',', $this->order));
        }
    }

    public function get_order(question_attempt $qa) {
        $this->init_order($qa);
        return $this->order;
    }

    protected function init_order(question_attempt $qa) {
        if (is_null($this->order)) {
            $this->order = explode(',', $qa->get_step(0)->get_qt_var('_order'));
        }
    }
}


/**
 * Represents a multiple choice question where only one choice should be selected.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_multichoice_single_question extends qtype_multichoice_base {
    public function get_renderer() {
        return renderer_factory::get_renderer('qtype_multichoice', 'single');
    }

    public function get_min_fraction() {
        $minfraction = 0;
        foreach ($this->answers as $ans) {
            $minfraction = min($minfraction, $ans->fraction);
        }
        return $minfraction;
    }

    /**
     * Return an array of the question type variables that could be submitted
     * as part of a question of this type, with their types, so they can be
     * properly cleaned.
     * @return array variable name => PARAM_... constant.
     */
    public function get_expected_data() {
        return array('answer' => PARAM_INT);
    }

    public function get_correct_response() {
        foreach ($this->order as $key => $answerid) {
            if (question_state::graded_state_for_fraction(
                    $this->answers[$answerid]->fraction) == question_state::GRADED_CORRECT) {
                return array('answer' => $key);
            }
        }
        return array();
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return array_key_exists('answer', $newresponse) == array_key_exists('answer', $prevresponse) &&
            (!array_key_exists('answer', $prevresponse) || $newresponse['answer'] == $prevresponse['answer']);
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response);
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    public function grade_response(array $response) {
        $fraction = $this->answers[$this->order[$response['answer']]]->fraction;
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }
}


/**
 * Represents a multiple choice question where multiple choices can be selected.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_multichoice_multi_question extends qtype_multichoice_base {
    public function get_renderer() {
        return renderer_factory::get_renderer('qtype_multichoice', 'multi');
    }

    public function get_min_fraction() {
        return 0;
    }

    /**
     * @param integer $key choice number
     * @return string the question-type variable name.
     */
    protected function field($key) {
        return 'choice' . $key;
    }

    public function get_expected_data() {
        $expected = array();
        foreach ($this->order as $key => $notused) {
            $expected[$this->field($key)] = PARAM_BOOL;
        }
        return $expected;
    }

    public function get_correct_response() {
        $response = array();
        foreach ($this->order as $key => $ans) {
            if (question_state::graded_state_for_fraction($this->answers[$ans]->fraction) !=
                    question_state::GRADED_INCORRECT) {
                $response[$this->field($key)] = 1;
            }
        }
        return $response;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        $same = true;
        foreach ($this->order as $key => $notused) {
            $fieldname = $this->field($key);
            $same = $same && array_key_exists($fieldname, $newresponse) == array_key_exists($fieldname, $prevresponse) &&
                    (!array_key_exists($fieldname, $prevresponse) || $newresponse[$fieldname] == $prevresponse[$fieldname]);
        }
        return $same;
    }

    public function is_complete_response(array $response) {
        $isresponse = false;
        foreach ($this->order as $key => $notused) {
            $isresponse = $isresponse || !empty($response[$this->field($key)]);
        }
        return $isresponse;
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    public function grade_response(array $response) {
        $fraction = 0;
        foreach ($this->order as $key => $ansid) {
            if (!empty($response[$this->field($key)])) {
                $fraction += $this->answers[$ansid]->fraction;
            }
        }
        $fraction = min(max(0, $fraction), 1.0);
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }
}
