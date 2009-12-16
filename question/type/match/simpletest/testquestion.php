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
 * Unit tests for the matching question definition classes.
 *
 * @package qtype_match
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');


/**
 * Unit tests for the matching question definition class.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_match_question_test extends UnitTestCase {

    public function test_get_expected_data() {
        $question = test_question_maker::make_a_matching_question();
        $question->init_first_step(new question_attempt_step());

        $this->assertEqual(array('sub0' => PARAM_INT, 'sub1' => PARAM_INT,
                'sub2' => PARAM_INT, 'sub3' => PARAM_INT), $question->get_expected_data());
    }

    public function test_is_complete_response() {
        $question = test_question_maker::make_a_matching_question();
        $question->init_first_step(new question_attempt_step());

        $this->assertFalse($question->is_complete_response(array()));
        $this->assertFalse($question->is_complete_response(
                array('sub0' => '1', 'sub1' => '1', 'sub2' => '1', 'sub3' => '0')));
        $this->assertFalse($question->is_complete_response(array('sub1' => '1')));
        $this->assertTrue($question->is_complete_response(
                array('sub0' => '1', 'sub1' => '1', 'sub2' => '1', 'sub3' => '1')));
    }

    public function test_is_gradable_response() {
        $question = test_question_maker::make_a_matching_question();
        $question->init_first_step(new question_attempt_step());

        $this->assertFalse($question->is_gradable_response(array()));
        $this->assertFalse($question->is_gradable_response(
                array('sub0' => '0', 'sub1' => '0', 'sub2' => '0', 'sub3' => '0')));
        $this->assertTrue($question->is_gradable_response(
                array('sub0' => '1', 'sub1' => '0', 'sub2' => '0', 'sub3' => '0')));
        $this->assertTrue($question->is_gradable_response(array('sub1' => '1')));
        $this->assertTrue($question->is_gradable_response(
                array('sub0' => '1', 'sub1' => '1', 'sub2' => '3', 'sub3' => '1')));
    }

    public function test_grading() {
        $question = test_question_maker::make_a_matching_question();
        $question->shufflestems = false;
        $question->init_first_step(new question_attempt_step());

        $choiceorder = $question->get_choice_order();
        $orderforchoice = array_combine(array_values($choiceorder), array_keys($choiceorder));

        $this->assertEqual(array(1, question_state::GRADED_CORRECT),
                $question->grade_response(array('sub0' => $orderforchoice[0],
                        'sub1' => $orderforchoice[1], 'sub2' => $orderforchoice[1], 'sub3' => $orderforchoice[0])));
        $this->assertEqual(array(0.25, question_state::GRADED_PARTCORRECT),
                $question->grade_response(array('sub0' => $orderforchoice[0])));
        $this->assertEqual(array(0, question_state::GRADED_INCORRECT),
                $question->grade_response(array('sub0' => $orderforchoice[1], 'sub1' => $orderforchoice[2], 'sub2' => $orderforchoice[0], 'sub3' => $orderforchoice[1])));
    }

    public function test_get_correct_response() {
        $question = test_question_maker::make_a_matching_question();
        $question->shufflestems = false;
        $question->init_first_step(new question_attempt_step());

        $choiceorder = $question->get_choice_order();
        $orderforchoice = array_combine(array_values($choiceorder), array_keys($choiceorder));

        $this->assertEqual(array('sub0' => $orderforchoice[0], 'sub1' => $orderforchoice[1], 'sub2' => $orderforchoice[1], 'sub3' => $orderforchoice[0]),
                $question->get_correct_response());
    }
}
