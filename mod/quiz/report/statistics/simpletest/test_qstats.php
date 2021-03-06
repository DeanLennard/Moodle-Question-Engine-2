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
 * Unit tests for (some of) mod/quiz/report/statistics/qstats.php.
 *
 * @package    quiz
 * @subpackage statistics
 * @copyright  2008 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/qstats.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');


/**
 * Test helper subclass of quiz_statistics_question_stats
 *
 * @copyright 2010 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_quiz_statistics_question_stats extends quiz_statistics_question_stats {
    public function set_step_data($states) {
        $this->lateststeps = $states;
    }

    protected function get_random_guess_score($questiondata) {
        return 0;
    }
}


/**
 * Unit tests for (some of) quiz_statistics_question_stats.
 *
 * @copyright  2008 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_statistics_question_stats_test extends UnitTestCase {
    public static $includecoverage = array('mod/quiz/report/reportlib.php');

    /** @var qstats object created to test class. */
    protected $qstats;

    public function test_qstats() {
        global $CFG;
        //data is taken from randomly generated attempts data generated by contrib/tools/generators/qagenerator/
        $steps = $this->get_records_from_csv($CFG->dirroot.'/mod/quiz/report/statistics/simpletest/mdl_question_states.csv');
        //data is taken from questions mostly generated by contrib/tools/generators/generator.php
        $questions = $this->get_records_from_csv($CFG->dirroot.'/mod/quiz/report/statistics/simpletest/mdl_question.csv');
        $this->qstats = new testable_quiz_statistics_question_stats($questions, 22, 10045.45455);
        $this->qstats->set_step_data($steps);
        $this->qstats->compute_statistics();

        //values expected are taken from contrib/tools/quiz_tools/stats.xls
        $facility = array(0,0,0,0,null,null,null,41.19318182,81.36363636,71.36363636,65.45454545,65.90909091,36.36363636,59.09090909,50,59.09090909,63.63636364,45.45454545,27.27272727,50);
        $this->qstats_q_fields('facility', $facility, 100);
        $sd = array(0,0,0,0,null,null,null,1912.733589,251.2738111,322.6312277,333.4199022,337.5811591,492.3659639,503.2362797,511.7663157,503.2362797,492.3659639,509.6471914,455.8423058,511.7663157);
        $this->qstats_q_fields('sd', $sd, 1000);
        $effectiveweight = array(0,0,0,0,0,0,0,26.58464457,3.368456046,3.253955259,7.584083694,3.79658376,3.183278505,4.532356904,7.78856243,10.08351572,8.381139345,8.727645713,7.946277111,4.769500946);
        $this->qstats_q_fields('effectiveweight', $effectiveweight);
        $discriminationindex = array(null,null,null,null,null,null,null,25.88327077,1.170256965,-4.207816809,28.16930644,-2.513606859,-12.99017581,-8.900638238,8.670004606,29.63337745,15.18945843,16.21079629,15.52451404,-8.396734802);
        $this->qstats_q_fields('discriminationindex', $discriminationindex);
        $discriminativeefficiency = array(null,null,null,null,null,null,null,27.23492723,1.382386552,-4.691171307,31.12404354,-2.877487579,-17.5074184,-10.27568922,10.86956522,34.58997279,17.4790556,20.14359793,22.06477733,-10);
        $this->qstats_q_fields('discriminativeefficiency', $discriminativeefficiency);
    }

    public function qstats_q_fields($fieldname, $values, $multiplier=1) {
        foreach ($this->qstats->questions as $question) {
            $value = array_shift($values);
            if ($value !== null) {
                $this->assertWithinMargin($question->_stats->{$fieldname}*$multiplier, $value, 1E-6);
            } else {
                $this->assertEqual($question->_stats->{$fieldname}*$multiplier, $value);
            }
        }
    }

    public function get_fields_from_csv($line) {
        $line = trim($line);
        $items = preg_split('!,!', $line);
        while (list($key) = each($items)) {
            if ($items[$key]!='') {
                if ($start = ($items[$key]{0}=='"')) {
                    $items[$key] = substr($items[$key], 1);
                    while (!$end = ($items[$key]{strlen($items[$key])-1}=='"')) {
                        $item = $items[$key];
                        unset($items[$key]);
                        list($key) = each($items);
                        $items[$key] = $item.','.$items[$key];
                    }
                    $items[$key] = substr($items[$key], 0, strlen($items[$key])-1);
                }

            }
        }
        return $items;
    }

    public function get_records_from_csv($filename) {
        $filecontents = file($filename, FILE_IGNORE_NEW_LINES);
        $records = array();
        $keys = $this->get_fields_from_csv(array_shift($filecontents));//first line is field names
        while (NULL !== ($line = array_shift($filecontents))) {
            $data = $this->get_fields_from_csv($line);
            $arraykey = reset($data);
            $object = new stdClass();
            foreach ($keys as $key) {
                $value = array_shift($data);
                if ($value !== NULL) {
                    $object->{$key} = $value;
                } else {
                    $object->{$key} = '';
                }
            }
            $records[$arraykey] = $object;
        }
        return $records;
    }
}
