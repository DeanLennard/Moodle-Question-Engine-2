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
 * Unit tests for the true-false question definition class.
 *
 * @package qtype_truefalse
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');

/**
 * Unit tests for the true-false question definition class.
 *
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_truefalse_question_test extends UnitTestCase {

    public function test_is_complete_response() {
        $question = test_question_maker::make_a_truefalse_question();

        $this->assertFalse($question->is_complete_response(array()));
        $this->assertTrue($question->is_complete_response(array('answer' => 0)));
        $this->assertTrue($question->is_complete_response(array('answer' => 1)));
    }

    public function test_is_gradable_response() {
        $question = test_question_maker::make_a_truefalse_question();

        $this->assertFalse($question->is_gradable_response(array()));
        $this->assertTrue($question->is_gradable_response(array('answer' => 0)));
        $this->assertTrue($question->is_gradable_response(array('answer' => 1)));
            }

    public function test_grading() {
        $question = test_question_maker::make_a_truefalse_question();

        $this->assertEqual(array(0, question_state::GRADED_INCORRECT),
                $question->grade_response(array('answer' => 0)));
        $this->assertEqual(array(1, question_state::GRADED_CORRECT),
                $question->grade_response(array('answer' => 1)));
    }

    public function test_get_correct_response() {
        $question = test_question_maker::make_a_truefalse_question();

        $this->assertEqual(array('answer' => 1),
                $question->get_correct_response());
    }
}
