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
 * Unit tests for the question definition base classes.
 *
 * @package    moodlecore
 * @subpackage questiontypes
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questiontype.php');


/**
 * Unit tests for the question definition base classes.
 *
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_response_answer_comparer implements question_response_answer_comparer {
    protected $answers = array();

    public function __construct($answers) {
        $this->answers = $answers;
    }

    public function get_answers() {
        return $this->answers;
    }

    public function compare_response_with_answer(array $response, question_answer $answer) {
        return $response['answer'] == $answer->answer;
    }
}

/**
 * Tests for {@link question_first_matching_answer_grading_strategy}.
 *
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_first_matching_answer_grading_strategy_test extends UnitTestCase {
    public function setUp() {
    }

    public function tearDown() {
    }

    public function test_no_answers_gives_null() {
        $question = new test_response_answer_comparer(array());
        $strategy = new question_first_matching_answer_grading_strategy($question);
        $this->assertNull($strategy->grade(array()));
    }

    public function test_matching_answer_returned1() {
        $answer = new question_answer(0, 'frog', 1, '', FORMAT_HTML);
        $question = new test_response_answer_comparer(array($answer));
        $strategy = new question_first_matching_answer_grading_strategy($question);
        $this->assertIdentical($answer, $strategy->grade(array('answer' => 'frog')));
    }

    public function test_matching_answer_returned2() {
        $answer = new question_answer(0, 'frog', 1, '', FORMAT_HTML);
        $answer2 = new question_answer(0, 'frog', 0.5, '', FORMAT_HTML);
        $question = new test_response_answer_comparer(array($answer, $answer2));
        $strategy = new question_first_matching_answer_grading_strategy($question);
        $this->assertIdentical($answer, $strategy->grade(array('answer' => 'frog')));
    }

    public function test_no_matching_answer_gives_null() {
        $answer = new question_answer(0, 'frog', 1, '', FORMAT_HTML);
        $answer2 = new question_answer(0, 'frog', 0.5, '', FORMAT_HTML);
        $question = new test_response_answer_comparer(array($answer, $answer2));
        $strategy = new question_first_matching_answer_grading_strategy($question);
        $this->assertNull($strategy->grade(array('answer' => 'toad')));
    }
}


/**
 * Test for question_hint and subclasses.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_hint_test extends UnitTestCase {
    public function test_basic() {
        $row = new stdClass();
        $row->id = 123;
        $row->hint = 'A hint';
        $row->hintformat = FORMAT_HTML;
        $hint = question_hint::load_from_record($row);
        $this->assertEqual($row->id, $hint->id);
        $this->assertEqual($row->hint, $hint->hint);
        $this->assertEqual($row->hintformat, $hint->hintformat);
    }

    public function test_with_parts() {
        $row = new stdClass();
        $row->id = 123;
        $row->hint = 'A hint';
        $row->hintformat = FORMAT_HTML;
        $row->shownumcorrect = 1;
        $row->clearwrong = 1;

        $hint = question_hint_with_parts::load_from_record($row);
        $this->assertEqual($row->id, $hint->id);
        $this->assertEqual($row->hint, $hint->hint);
        $this->assertEqual($row->hintformat, $hint->hintformat);
        $this->assertTrue($hint->shownumcorrect);
        $this->assertTrue($hint->clearwrong);
    }
}
