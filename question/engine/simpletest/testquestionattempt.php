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
 * This file contains tests for the question_attempt class.
 *
 * Action methods like start, process_action and finish are assumed to be
 * tested by testintegration.php.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../lib.php');
require_once(dirname(__FILE__) . '/helpers.php');

class question_attempt_test extends UnitTestCase {#
    private $question;
    private $usageid;
    private $qa;

    public function setUp() {
        $this->question = new question_definition();
        $this->question->defaultmark = 3;
        $this->usageid = 13;
        $this->qa = new question_attempt($this->question, $this->usageid);
    }

    public function tearDown() {
        $this->question = null;
        $this->useageid = null;
        $this->qa = null;
    }

    public function test_constructor_sets_maxmark() {
        $qa = new question_attempt($this->question, $this->usageid);
        $this->assertIdentical($this->question, $qa->get_question());
        $this->assertEqual(3, $qa->get_max_mark());
    }

    public function test_maxmark_beats_default_mark() {
        $this->question->maxmark = 2;
        $qa = new question_attempt($this->question, $this->usageid);
        $this->assertEqual(2, $qa->get_max_mark());
    }

    public function test_get_set_number_in_usage() {
        $this->qa->set_number_in_usage(7);
        $this->assertEqual(7, $this->qa->get_number_in_usage());
    }

    public function test_fagged_initially_false() {
        $this->assertEqual(false, $this->qa->is_flagged());
    }

    public function test_set_is_flagged() {
        $this->qa->set_flagged(true);
        $this->assertEqual(true, $this->qa->is_flagged());
    }

    public function test_get_qt_field_name() {
        $name = $this->qa->get_qt_field_name('test');
        $this->assertPattern('/^' . preg_quote($this->qa->get_field_prefix()) . '/', $name);
        $this->assertPattern('/_test$/', $name);
    }

    public function test_get_im_field_name() {
        $name = $this->qa->get_im_field_name('test');
        $this->assertPattern('/^' . preg_quote($this->qa->get_field_prefix()) . '/', $name);
        $this->assertPattern('/_!test$/', $name);
    }

    public function test_get_field_prefix() {
        $this->qa->set_number_in_usage(7);
        $name = $this->qa->get_field_prefix();
        $this->assertPattern('/' . preg_quote($this->usageid) . '/', $name);
        $this->assertPattern('/' . preg_quote($this->qa->get_number_in_usage()) . '/', $name);
    }
}


/**
 * These tests use a standard fixture of a question_attempt with three steps.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_with_steps_test extends UnitTestCase {
    private $qa;

    public function setUp() {
        $question = new question_definition();
        $question->maxmark = 2;
        $this->qa = new testable_question_attempt($question, 0);
        for ($i = 0; $i < 3; $i++) {
            $step = new question_attempt_step(array('i' => $i));
            $this->qa->add_step($step);
        }
    }

    public function tearDown() {
        $this->qa = null;
    }

    public function test_get_step_before_start() {
        $this->expectException();
        $step = $this->qa->get_step(-1);
    }

    public function test_get_step_at_start() {
        $step = $this->qa->get_step(0);
        $this->assertEqual(0, $step->get_qt_var('i'));
    }

    public function test_get_step_at_end() {
        $step = $this->qa->get_step(2);
        $this->assertEqual(2, $step->get_qt_var('i'));
    }

    public function test_get_step_past_end() {
        $this->expectException();
        $step = $this->qa->get_step(3);
    }

    public function test_get_num_steps() {
        $this->assertEqual(3, $this->qa->get_num_steps());
    }

    public function test_get_last_step() {
        $step = $this->qa->get_last_step();
        $this->assertEqual(2, $step->get_qt_var('i'));
    }

    public function test_get_last_qt_var_there1() {
        $this->assertEqual(2, $this->qa->get_last_qt_var('i'));
    }

    public function test_get_last_qt_var_there2() {
        $this->qa->get_step(0)->set_qt_var('_x', 'a value');
        $this->assertEqual('a value', $this->qa->get_last_qt_var('_x'));
    }

    public function test_get_last_qt_var_missing() {
        $this->assertNull($this->qa->get_last_qt_var('notthere'));
    }

    public function test_get_last_qt_var_missing_default() {
        $this->assertEqual('default', $this->qa->get_last_qt_var('notthere', 'default'));
    }

    public function test_get_last_im_var_missing() {
        $this->assertNull($this->qa->get_last_qt_var('notthere'));
    }

    public function test_get_last_im_var_there() {
        $this->qa->get_step(1)->set_im_var('_x', 'a value');
        $this->assertEqual('a value', '' . $this->qa->get_last_im_var('_x'));
    }

    public function test_get_state_gets_state_of_last() {
        $this->qa->get_step(2)->set_state(question_state::GRADED_CORRECT);
        $this->qa->get_step(1)->set_state(question_state::GRADED_INCORRECT);
        $this->assertEqual(question_state::GRADED_CORRECT, $this->qa->get_state());
    }

    public function test_get_mark_gets_mark_of_last() {
        // $qa->maxgrade = 2
        $this->assertEqual(2, $this->qa->get_max_mark());
        $this->qa->get_step(2)->set_fraction(0.5);
        $this->qa->get_step(1)->set_fraction(0.1);
        $this->assertEqual(1, $this->qa->get_mark());
    }

    public function test_get_fraction_gets_fraction_of_last() {
        $this->qa->get_step(2)->set_fraction(0.5);
        $this->qa->get_step(1)->set_fraction(0.1);
        $this->assertEqual(0.5, $this->qa->get_fraction());
    }

    public function test_get_fraction_returns_null_if_none() {
        $this->assertNull($this->qa->get_fraction());
    }

    public function test_format_mark() {
        $this->qa->get_step(2)->set_fraction(0.5);
        $this->assertEqual('1.00', $this->qa->format_mark(2));
    }

    public function test_format_max_mark() {
        $this->assertEqual('2.0000000', $this->qa->format_max_mark(7));
    }

    public function test_get_min_fraction() {
        $this->qa->set_min_fraction(-1);
        $this->assertEqual(-1, $this->qa->get_min_fraction(0));
    }

    public function test_cannot_get_min_fraction_before_start() {
        $qa = new question_attempt(null, null);
        $this->expectException();
        $qa->get_min_fraction();
    }
}


class question_attempt_db_test extends data_loading_method_test_base {
    public function test_load() {
        $records = $this->build_db_records(array(
            array('id', 'questionattemptid', 'questionusageid', 'numberinusage',
                              'interactionmodel', 'questionid', 'maxmark', 'minfraction', 'flagged',
                                                                             'questionsummary', 'rightanswer', 'responsesummary', 'timemodified',
                                                                                                     'attemptstepid', 'sequencenumber', 'state', 'fraction',
                                                                                                                          'timecreated', 'userid', 'name', 'value'),
            array(1, 1, 1, 1, 'deferredfeedback', 1, 2.0000000, 0.0000000, 0, '', '', '', 1256233790, 1, 0,  1,      null, 1256233700, 1,       null, null),
            array(2, 1, 1, 1, 'deferredfeedback', 1, 2.0000000, 0.0000000, 0, '', '', '', 1256233790, 2, 1,  2,      null, 1256233705, 1,   'answer',  '1'),
            array(3, 1, 1, 1, 'deferredfeedback', 1, 2.0000000, 0.0000000, 1, '', '', '', 1256233790, 3, 2,  2,      null, 1256233710, 1,   'answer',  '0'),
            array(4, 1, 1, 1, 'deferredfeedback', 1, 2.0000000, 0.0000000, 0, '', '', '', 1256233790, 4, 3,  2,      null, 1256233715, 1,   'answer',  '1'),
            array(5, 1, 1, 1, 'deferredfeedback', 1, 2.0000000, 0.0000000, 0, '', '', '', 1256233790, 5, 4, 26, 1.0000000, 1256233720, 1,  '!finish',  '1'),
            array(6, 1, 1, 1, 'deferredfeedback', 1, 2.0000000, 0.0000000, 0, '', '', '', 1256233790, 6, 5, 57, 0.5000000, 1256233790, 1, '!comment', 'Not good enough!'),
            array(7, 1, 1, 1, 'deferredfeedback', 1, 2.0000000, 0.0000000, 0, '', '', '', 1256233790, 6, 5, 57, 0.5000000, 1256233790, 1,    '!mark',  '1'),
            array(8, 1, 1, 1, 'deferredfeedback', 1, 2.0000000, 0.0000000, 0, '', '', '', 1256233790, 6, 5, 57, 0.5000000, 1256233790, 1, '!maxmark',  '2'),
        ));

        $qa = question_attempt::load_from_records($records, 1);

        $this->assertEqual(6, $qa->get_num_steps());

        $step = $qa->get_step(0);
        $this->assertEqual(1, $step->get_state());
        $this->assertNull($step->get_fraction());
        $this->assertEqual(1256233700, $step->get_timecreated());
        $this->assertEqual(1, $step->get_user_id());
        $this->assertEqual(array(), $step->get_all_data());

        $step = $qa->get_step(1);
        $this->assertEqual(2, $step->get_state());
        $this->assertNull($step->get_fraction());
        $this->assertEqual(1256233705, $step->get_timecreated());
        $this->assertEqual(1, $step->get_user_id());
        $this->assertEqual(array('answer' => '1'), $step->get_all_data());

        $step = $qa->get_step(2);
        $this->assertEqual(2, $step->get_state());
        $this->assertNull($step->get_fraction());
        $this->assertEqual(1256233710, $step->get_timecreated());
        $this->assertEqual(1, $step->get_user_id());
        $this->assertEqual(array('answer' => '0'), $step->get_all_data());

        $step = $qa->get_step(3);
        $this->assertEqual(2, $step->get_state());
        $this->assertNull($step->get_fraction());
        $this->assertEqual(1256233715, $step->get_timecreated());
        $this->assertEqual(1, $step->get_user_id());
        $this->assertEqual(array('answer' => '1'), $step->get_all_data());

        $step = $qa->get_step(4);
        $this->assertEqual(26, $step->get_state());
        $this->assertEqual(1, $step->get_fraction());
        $this->assertEqual(1256233720, $step->get_timecreated());
        $this->assertEqual(1, $step->get_user_id());
        $this->assertEqual(array('!finish' => '1'), $step->get_all_data());

        $step = $qa->get_step(5);
        $this->assertEqual(57, $step->get_state());
        $this->assertEqual(0.5, $step->get_fraction());
        $this->assertEqual(1256233790, $step->get_timecreated());
        $this->assertEqual(1, $step->get_user_id());
        $this->assertEqual(array('!comment' => 'Not good enough!', '!mark' => '1', '!maxmark' => '2'),
                $step->get_all_data());
    }
}
