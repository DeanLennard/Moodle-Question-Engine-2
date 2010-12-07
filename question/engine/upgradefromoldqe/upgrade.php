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
 * This file contains the code required to upgrade all the attempt data from
 * old versions of Moodle into the tables used by the new question engine.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


global $CFG;
require_once($CFG->libdir . '/questionlib.php');


/**
 * This class manages upgrading all the question attempts from the old database
 * structure to the new question engine.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_engine_attempt_upgrader {
    /** @var question_engine_upgrade_question_loader */
    protected $questionloader;

    public function __construct() {
        $this->questionloader = new question_engine_upgrade_question_loader();
    }

    protected function print_progress($done, $outof) {
        print_progress($done, $outof);
    }

    public function convert_all_quiz_attempts() {
        $quizids = get_records_menu('quiz', '', '', 'id', 'id,1');
        $done = 0;
        $outof = count($quizids);

        foreach ($quizids as $quizid => $notused) {
            $this->print_progress($done, $outof);

            $quiz = get_record('quiz', 'id', $quizid);
            $this->update_all_attemtps_at_quiz($quiz);

            $done += 1;
        }

        $this->print_progress($outof, $outof);
    }

    public function update_all_attemtps_at_quiz($quiz) {
        global $CFG;
        begin_sql();

        $quizattemptsrs = get_recordset('quiz_attempts', 'quiz', $quiz->id, 'uniqueid');
        $questionsessionsrs = get_recordset_sql("
                SELECT *
                FROM {$CFG->prefix}question_sessions
                WHERE attemptid IN (SELECT uniqueid FROM {$CFG->prefix}quiz_attempts
                    WHERE quiz = {$quiz->id})
                ORDER BY attemptid, questionid
        ");

        $questionsstatesrs = get_recordset_sql("
                SELECT *
                FROM {$CFG->prefix}question_states
                WHERE attempt IN (SELECT uniqueid FROM {$CFG->prefix}quiz_attempts
                    WHERE quiz = {$quiz->id})
                ORDER BY attempt, question, seq_number
        ");

        while ($attempt = rs_fetch_next_record($quizattemptsrs)) {
            while ($qsession = $this->get_next_question_session($attempt, $questionsessionsrs)) {
                $question = $this->questionloader->load_question($qsession->questionid);
                $qstates = $this->get_question_states($attempt, $question, $questionsstatesrs);
                $this->convert_attempt($quiz, $attempt, $question, $qsession, $qstates);
            }
        }

        rs_close($quizattemptsrs);
        rs_close($questionsessionsrs);
        rs_close($questionsstatesrs);

        commit_sql();

        return false; // Signal failure, since no work was acutally done.
    }

    protected function convert_quiz_attempt($quiz, $attempt, $questionsessionsrs, $questionsstatesrs) {
        $qas = array();
        while ($qsession = $this->get_next_question_session($attempt, $questionsessionsrs)) {
            $question = $this->load_question($qsession->questionid, $quiz->id);
            $qstates = $this->get_question_states($attempt, $question, $questionsstatesrs);
            try {
                $qas[$qsession->questionid] = $this->convert_question_attempt($quiz, $attempt, $question, $qsession, $qstates);
            } catch (Exception $e) {
                notify($e->getMessage());
            }
        }

        $this->save_usage($quiz->preferredbehaviour, $attempt->uniqueid, $qas, $quiz->questions, $attempt->layout);
    }

    protected function save_usage($preferredbehaviour, $qubaid, $qas, $quizlayout, $attemptlayout) {
        $missing = array();

        $layout = explode(',', $attemptlayout);
        $questionkeys = array_combine(array_values($layout), array_keys($layout));
        $questionorder = array_filter(explode(',', $quizlayout), create_function('$x', 'return $x != 0;'));

        $this->set_quba_preferred_behaviour($qubaid, $preferredbehaviour);

        $i = 0;
        foreach ($questionorder as $questionid) {
            $i++;

            if (!array_key_exists($questionid, $qas)) {
                $missing[] = $questionid;
                continue;
            }

            $qa = $qas[$questionid];
            $qa->questionusageid = $qubaid;
            $qa->slot = $i;
            $this->insert_record('question_attempts', $qa);
            $layout[$questionkeys[$questionid]] = $qa->slot;

            foreach ($qa->steps as $step) {
                $step->questionattemptid = $qa->id;
                $this->insert_record('question_attempt_steps', $step);

                foreach ($step->data as $name => $value) {
                    $datum = new stdClass();
                    $datum->attemptstepid = $step->id;
                    $datum->name = $name;
                    $datum->value = $value;
                    $this->insert_record('question_attempt_step_data', $datum, false);
                }
            }
        }

        $this->set_quiz_attempt_layout($qubaid, implode(',', $layout));

        if ($missing) {
            notify("Question sessions for questions " .
                    implode(', ', $missing) .
                    " were missing when upgrading question usage {$qubaid}.");
        }
    }

    protected function set_quba_preferred_behaviour($qubaid, $preferredbehaviour) {
        set_field('question_usages', 'preferredbehaviour', $preferredbehaviour, 'id', $qubaid);
    }

    protected function set_quiz_attempt_layout($qubaid, $layout) {
        set_field('quiz_attempts', 'layout', $layout, 'uniqueid', $qubaid);
    }

    protected function escape_fields($record) {
        foreach (get_object_vars($record) as $field => $value) {
            if (is_string($value)) {
                $record->$field = addslashes($value);
            }
        }
    }
    protected function insert_record($table, $record, $saveid = true) {
        $this->escape_fields($record);
        $newid = insert_record($table, $record, $saveid);
        if ($saveid) {
            $record->id = $newid;
        }
    }

    public function load_question($questionid, $quizid = null) {
        return $this->questionloader->load_question($questionid, $quizid);
    }

    public function get_next_question_session($attempt, $questionsessionsrs) {
        $qsession = rs_fetch_record($questionsessionsrs);

        if (!$qsession || $qsession->attemptid != $attempt->uniqueid) {
            // No more question sessions belonging to this attempt.
            return false;
        }

        // Session found, move the pointer in the RS and return the record.
        rs_next_record($questionsessionsrs);
        return $qsession;
    }

    public function get_question_states($attempt, $question, $questionsstatesrs) {
        $qstates = array();

        while ($state = rs_fetch_record($questionsstatesrs)) {
            if (!$state || $state->attempt != $attempt->uniqueid ||
                    $state->question != $question->id) {
                // We have found all the states for this attempt. Stop.
                break;
            }

            // Add the new state to the array, and advance.
            $qstates[$state->seq_number] = $state;
            rs_next_record($questionsstatesrs);
        }

        return $qstates;
    }

    public function convert_attempt($quiz, $attempt, $question, $qsession, $qstates) {
        print_object($attempt);
        print_object($question);
        print_object($qsession);
        print_object($qstates);
        // TODO
    }
}

/**
 * This class deals with loading (and caching) question definitions during the
 * question engine upgrade.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_engine_upgrade_question_loader {
    private $cache = array();

    public function load_question($questionid) {
        global $QTYPES;

        if (isset($this->cache[$questionid])) {
            return $this->cache[$questionid];
        }

        $question = get_record('question', 'id', $questionid);

        if (!array_key_exists($question->qtype, $QTYPES)) {
            $question->qtype = 'missingtype';
            $question->questiontext = '<p>' . get_string('warningmissingtype', 'quiz') . '</p>' . $question->questiontext;
        }

        $QTYPES[$question->qtype]->get_question_options($question);

        $this->cache[$questionid] = $question;

        return $this->cache[$questionid];
    }
}


abstract class qbehaviour_converter {
    protected $qtypeupdater;

    protected $qa;

    protected $quiz;
    protected $attempt;
    protected $question;
    protected $qsession;
    protected $qstates;

    protected $sequencenumber;
    protected $finishstate;
    protected $alreadystarted;

    public function __construct($quiz, $attempt, $question, $qsession, $qstates) {
        $this->quiz = $quiz;
        $this->attempt = $attempt;
        $this->question = $question;
        $this->qsession = $qsession;
        $this->qstates = $qstates;
    }

    protected abstract function behaviour_name();

    protected function convert() {
        $this->qtypeupdater = $this->make_qtype_updater();

        $qa = new stdClass();
        $qa->questionid = $this->question->id;
        $qa->behaviour = $this->behaviour_name();
        $qa->maxmark = $this->question->maxmark;
        $qa->minfraction = 0;
        $qa->flagged = 0;
        $qa->questionsummary = $this->qtypeupdater->question_summary($this->question);
        $qa->rightanswer = $this->qtypeupdater->right_answer($this->question);
        $qa->responsesummary = '';
        $qa->timemodified = 0;
        $qa->steps = array();

        $this->qa = $qa;

        $this->convert_steps();
    }

    protected function convert_steps() {
        $this->finishstate = null;
        $this->startstate = null;
        $this->sequencenumber = 0;
        foreach ($this->qstates as $state) {
            $this->process_state($state);
        }
    }

    protected function process_state($state) {
        $step = $this->make_step($state);

        $method = 'process' . $state->event;
        $this->$method($step, $state);
    }

    protected function add_step($step) {
        $step->sequencenumber = $this->sequencenumber;
        $this->qa->steps[] = $step;
        $this->sequencenumber++;
    }

    protected function unexpected_event($state) {
        throw new coding_exception("Unexpected event {$state->event} in state {$state->id} in question session {$this->qsession->id}.");
    }

    protected function process0($step, $state) {
        if ($this->startstate) {
            if ($state->answer == reset($this->qstates)->answer) {
                return;
            } else {
                throw new coding_exception("Two inconsistent open states for question session {$this->qsession->id}.");
            }
        }
        $step->state = 'todo';
        $this->startstate = $state;
        $this->add_step($step);
    }

    protected function process1($step, $state) {
        $this->unexpected_event($state);
    }

    protected function process2($step, $state) {
        if ($this->qtypeupdater->was_answered($state)) {
            $step->state = 'complete';
        } else {
            $step->state = 'todo';
        }
        $this->add_step($step);
    }

    protected function process3($step, $state) {
        // TODO
        return $this->process6($step, $state);
    }

    protected function process4($step, $state) {
        $this->unexpected_event($state);
    }

    protected function process5($step, $state) {
        $this->unexpected_event($state);
    }

    protected abstract function process6($step, $state);
    protected abstract function process7($step, $state);

    protected function process8($step, $state) {
        return $this->process6($step, $state);
    }

    protected function process9($step, $state) {
        if (!$this->finishstate) {
            $submitstate = clone($state);
            $submitstate->event = 8;
            $submitstate->grade = 0;
            $this->process_state($submitstate);
        }

        $step->data['-comment'] = addslashes($this->qsession->manualcomment);
        if ($this->question->maxmark > 0) {
            $step->fraction = $state->grade / $this->question->maxmark;
            $step->state = $this->manual_graded_state_for_fraction($step->fraction);
            $step->data['-mark'] = $state->grade;
            $step->data['-maxmark'] = $this->question->maxmark;
        } else {
            $step->state = 'manfinished';
        }
        unset($step->data['answer']);
        $step->userid = null;
        $this->add_step($step);
    }

    protected function process10($step, $state) {
        $this->unexpected_event($state);
    }

    /**
     * @param stdClass $question a question definition
     * @return qtype_updater
     */
    protected function make_qtype_updater() {
        $class = 'qtype_' . $this->question->qtype . '_updater';
        return new $class($this, $this->question);
    }

    public function to_text($html) {
        return trim(html_to_text($html, 0, false));
    }

    protected function graded_state_for_fraction($fraction) {
        if ($fraction < 0.000001) {
            return 'gradedwrong';
        } else if ($fraction > 0.999999) {
            return 'gradedright';
        } else {
            return 'gradedpartial';
        }
    }

    protected function manual_graded_state_for_fraction($fraction) {
        if ($fraction < 0.000001) {
            return 'mangrwrong';
        } else if ($fraction > 0.999999) {
            return 'mangrright';
        } else {
            return 'mangrpartial';
        }
    }

    public function get_converted_qa() {
        $this->convert();
        return $this->qa;
    }

    protected function make_step($state){
        $step = new stdClass();
        $step->data = array();

        if ($this->sequencenumber == 0) {
            $this->qtypeupdater->set_first_step_data_elements($state, $step->data);
        } else {
            $this->qtypeupdater->set_data_elements_for_step($state, $step->data);
        }

        $step->fraction = null;
        $step->timecreated = $state->timestamp;
        $step->userid = $this->attempt->userid;

        $summary = $this->qtypeupdater->response_summary($state);
        if (!is_null($summary)) {
            $this->qa->responsesummary = $summary;
        }
        $this->qa->timemodified = max($this->qa->timemodified, $state->timestamp);

        return $step;
    }
}


class qbehaviour_informationitem_converter extends qbehaviour_converter {
    protected function behaviour_name() {
        return 'informationitem';
    }

    protected function process0($step, $state) {
        if ($this->startstate) {
            return;
        }
        $step->state = 'todo';
        $this->startstate = $state;
        $this->add_step($step);
    }

    protected function process2($step, $state) {
        $this->unexpected_event($state);
    }

    protected function process3($step, $state) {
        $this->unexpected_event($state);
    }

    protected function process6($step, $state) {
        if ($this->finishstate) {
            return;
        }

        $step->state = 'finished';
        $step->data['-finish'] = '1';
        $this->finishstate = $state;
        $this->add_step($step);
    }

    protected function process7($step, $state) {
        return $this->process6($step, $state);
    }

    protected function process8($step, $state) {
        return $this->process6($step, $state);
    }
}


class qbehaviour_opaque_converter extends qbehaviour_converter {
    protected function behaviour_name() {
        return 'opaque';
    }

    protected function process0($step, $state) {
        global $CFG;
        $ok = parent::process0($step, $state);
        $step->data['-_preferredbehaviour'] = ($this->quiz->preferredbehaviour ? 'interactive' : 'deferredfeedback');;
        $step->data['-_language'] = $CFG->lang;
        $step->data['-_userid'] = $step->userid;
        $step->data['-_statestring'] = 'You have [N] attempts.';
        return $ok;
    }

    protected function process2($step, $state) {
        $step->state = 'todo';
        $this->add_step($step);
    }

    protected function process3($step, $state) {
        return $this->process2($step, $state);
    }

    protected function process6($step, $state) {
        if ($this->finishstate) {
            throw new coding_exception("Two finish states found for opaque question session {$this->qsession->id}.");
        }

        if ($this->question->maxmark > 0) {
            $step->fraction = $state->grade / $this->question->maxmark;
            $step->state = $this->graded_state_for_fraction($step->fraction);
        } else {
            $step->state = 'finished';
        }
        $this->finishstate = $state;
        $this->add_step($step);
    }

    protected function process7($step, $state) {
        $this->unexpected_event($state);
    }

    protected function process8($step, $state) {
        return $this->process6($step, $state);
    }
}


class qbehaviour_manualgraded_converter extends qbehaviour_converter {
    protected function behaviour_name() {
        return 'manualgraded';
    }

    protected function process6($step, $state) {
        $step->state = 'needsgrading';
        if (!$this->finishstate) {
            $step->data['-finish'] = '1';
            $this->finishstate = $state;
        }
        $this->add_step($step);
    }

    protected function process7($step, $state) {
        return $this->process6($step, $state);
    }
}


class qbehaviour_interactive_converter extends qbehaviour_converter {
    protected $triesleft;

    protected function behaviour_name() {
        return 'interactive';
    }

    protected function process0($step, $state) {
        $ok = parent::process0($step, $state);
        $this->triesleft = 1;
        if (!empty($this->question->hints)) {
            $this->triesleft += count($this->question->hints);
        }
        $step->data['-_triesleft'] = $this->triesleft;
        return $ok;
    }

    protected function process3($step, $state) {
        return $this->process6($step, $state);
    }

    protected function process6($step, $state) {
        if ($this->finishstate) {
            if (!$this->qtypeupdater->compare_answers($this->finishstate->answer, $state->answer) ||
                    $this->finishstate->grade != $state->grade ||
                    $this->finishstate->raw_grade != $state->raw_grade ||
                    $this->finishstate->penalty != $state->penalty) {
                throw new coding_exception("Two inconsistent finish states found for question session {$this->qsession->id}.");
            } else if ($this->triesleft) {
                $step->data = array('-finish' => '1');
                if ($this->question->maxmark > 0) {
                    $step->fraction = $state->grade / $this->question->maxmark;
                    $step->state = $this->graded_state_for_fraction($step->fraction);
                } else {
                    $step->state = 'finished';
                }
                $this->finishstate = $state;
                $this->add_step($step);
                $this->triesleft = 0;
                return;
            } else {
                return;
            }
        }

        if ($this->question->maxmark > 0) {
            $step->fraction = $state->grade / $this->question->maxmark;
            $step->state = $this->graded_state_for_fraction($step->fraction);
        } else {
            $step->state = 'finished';
        }

        $this->triesleft--;
        $step->data['-submit'] = '1';
        if ($this->triesleft && $step->state != 'gradedright') {
            $step->state = 'todo';
            $step->fraction = null;
            $step->data['-_triesleft'] = $this->triesleft;
        } else {
            $this->triesleft = 0;
        }
        $this->finishstate = $state;
        $this->add_step($step);
    }

    protected function process7($step, $state) {
        $this->unexpected_event($state);
    }

    protected function process10($step, $state) {
        if (!$this->finishstate) {
            $oldcount = $this->sequencenumber;
            $this->process3($step, $state);
            if ($this->sequencenumber != $oldcount + 1) {
                throw new coding_exception('Submit before try again did not keep the step.');
            }
            $step = $this->make_step($state);
        }

        $step->state = 'todo';
        $step->data = array('-tryagain' => 1);
        $this->finishstate = null;
        $this->add_step($step);
    }
}


class qbehaviour_deferredfeedback_converter extends qbehaviour_converter {
    protected function behaviour_name() {
        return 'deferredfeedback';
    }

    protected function process6($step, $state) {
        if (!$this->startstate) {
            // WTF, but this has happened a few times in our DB. It seems it is safe to ignore.
            return;
        }

        if ($this->finishstate) {
            if ($this->finishstate->answer != $state->answer ||
                    $this->finishstate->event != $state->event ||
                    $this->finishstate->grade != $state->grade ||
                    $this->finishstate->raw_grade != $state->raw_grade ||
                    $this->finishstate->penalty != $state->penalty) {
                throw new coding_exception("Two inconsistent finish states found for question session {$this->qsession->id}.");
            } else {
                return;
            }
        }

        if ($this->question->maxmark > 0) {
            $step->fraction = $state->grade / $this->question->maxmark;
            $step->state = $this->graded_state_for_fraction($step->fraction);
        } else {
            $step->state = 'finished';
        }
        $step->data['-finish'] = '1';
        $this->finishstate = $state;
        $this->add_step($step);
    }

    protected function process7($step, $state) {
        $this->unexpected_event($state);
    }
}


abstract class qtype_updater {
    /** @var question_engine_attempt_upgrader */
    protected $question;
    protected $updater;

    public function __construct($updater, $question) {
        $this->updater = $updater;
        $this->question = $question;
    }

    protected function to_text($html) {
        return $this->updater->to_text($html);
    }

    public function question_summary() {
        return $this->to_text($this->question->questiontext);
    }

    public function compare_answers($answer1, $answer2) {
        return $answer1 == $answer2;
    }

    public abstract function right_answer();
    public abstract function response_summary($state);
    public abstract function was_answered($state);
    public abstract function set_first_step_data_elements($state, &$data);
    public abstract function set_data_elements_for_step($state, &$data);
}

class qtype_multichoice_updater extends qtype_updater {
    public function right_answer() {
        if ($this->question->options->single) {
            foreach ($this->question->options->answers as $ans) {
                if ($ans->fraction > 0.999) {
                    return $this->to_text($ans->answer);
                }
            }

        } else {
            $rightbits = array();
            foreach ($this->question->options->answers as $ans) {
                if ($ans->fraction >= 0.000001) {
                    $rightbits[] = $this->to_text($ans->answer);
                }
            }
            return implode('; ', $rightbits);
        }
    }

    public function response_summary($state) {
        list($order, $responses) = split(':', $state->answer);
        if ($this->question->options->single) {
            if (is_numeric($responses)) {
                return $this->to_text($this->question->options->answers[$responses]->answer);
            } else {
                return null;
            }

        } else {
            if (!empty($responses)) {
                $responses = explode(',', $responses);
                $bits = array();
                foreach ($responses as $response) {
                    $bits[] = $this->to_text($this->question->options->answers[$response]->answer);
                }
                return implode('; ', $bits);
            } else {
                return null;
            }
        }
    }

    public function was_answered($state) {
        list($order, $responses) = split(':', $state->answer);
        if ($this->question->options->single) {
            return is_numeric($responses);
        } else {
            return !empty($responses);
        }
    }

    public function set_first_step_data_elements($state, &$data) {
        list($order, $responses) = split(':', $state->answer);
        $data['_order'] = $order;
    }

    public function set_data_elements_for_step($state, &$data) {
        list($order, $responses) = split(':', $state->answer);
        $order = explode(',', $order);
        $flippedorder = array_combine(array_values($order), array_keys($order));
        if ($this->question->options->single) {
            if (is_numeric($responses)) {
                $data['answer'] = $flippedorder[$responses] + 1;
            }

        } else {
            if (!empty($responses)) {
                $responses = explode(',', $responses);
                $bits = array();
                foreach ($responses as $response) {
                    $data['choice' . ($flippedorder[$response] + 1)] = 1;
                }
            }
        }
    }
}


class qtype_shortanswer_updater extends qtype_updater {
    public function right_answer() {
        foreach ($this->question->options->answers as $ans) {
            if ($ans->fraction > 0.999) {
                return $ans->answer;
            }
        }
    }

    public function was_answered($state) {
        return !empty($state->answer);
    }

    public function response_summary($state) {
        if (!empty($state->answer)) {
            return $state->answer;
        } else {
            return null;
        }
    }

    public function set_first_step_data_elements($state, &$data) {
    }

    public function set_data_elements_for_step($state, &$data) {
        if (!empty($state->answer)) {
            $data['answer'] = $state->answer;
        }
    }
}


class qtype_essay_updater extends qtype_updater {
    public function right_answer() {
        return '';
    }

    public function response_summary($state) {
        if (!empty($state->answer)) {
            return $this->to_text($state->answer);
        } else {
            return null;
        }
    }

    public function was_answered($state) {
        return !empty($state->answer);
    }

    public function set_first_step_data_elements($state, &$data) {
    }

    public function set_data_elements_for_step($state, &$data) {
        if (!empty($state->answer)) {
            $data['answer'] = $state->answer;
        }
    }
}


class qtype_numerical_updater extends qtype_updater {
    public function right_answer() {
        foreach ($this->question->options->answers as $ans) {
            if ($ans->fraction > 0.999) {
                return $ans->answer;
            }
        }
    }

    public function response_summary($state) {
        if (!empty($state->answer)) {
            return $state->answer;
        } else {
            return null;
        }
    }

    public function was_answered($state) {
        return !empty($state->answer);
    }

    public function set_first_step_data_elements($state, &$data) {
        $data['_separators'] = '.$,';
    }

    public function set_data_elements_for_step($state, &$data) {
        if (!empty($state->answer)) {
            $data['answer'] = $state->answer;
        }
    }
}


class qtype_description_updater extends qtype_updater {
    public function right_answer() {
        return '';
    }

    public function was_answered($state) {
        return false;
    }

    public function response_summary($state) {
        return '';
    }

    public function set_first_step_data_elements($state, &$data) {
    }

    public function set_data_elements_for_step($state, &$data) {
    }
}


class qtype_truefalse_updater extends qtype_updater {
    public function right_answer() {
        foreach ($this->question->options->answers as $ans) {
            if ($ans->fraction > 0.999) {
                return $ans->answer;
            }
        }
    }

    public function response_summary($state) {
        if (is_numeric($state->answer)) {
            return $this->question->options->answers[$state->answer]->answer;
        } else {
            return null;
        }
    }

    public function was_answered($state) {
        return !empty($state->answer);
    }

    public function set_first_step_data_elements($state, &$data) {
    }

    public function set_data_elements_for_step($state, &$data) {
        if (is_numeric($state->answer)) {
            $data['answer'] = (int) ($state->answer == $this->question->options->trueanswer);
        }
    }
}


class qtype_match_updater extends qtype_updater {
    protected $stems;
    protected $choices;
    protected $right;
    protected $stemorder;
    protected $choiceorder;
    protected $flippedchoiceorder;

    public function __construct($updater, $question) {
        parent::__construct($updater, $question);
    }

    public function question_summary() {
        $this->stems = array();
        $this->choices = array();
        $this->right = array();

        foreach ($this->question->options->subquestions as $matchsub) {
            $ans = $matchsub->answertext;
            $key = array_search($matchsub->answertext, $this->choices);
            if ($key === false) {
                $key = $matchsub->id;
                $this->choices[$key] = $matchsub->answertext;
            }

            if ($matchsub->questiontext !== '') {
                $this->stems[$matchsub->id] = $this->to_text($matchsub->questiontext);
                $this->right[$matchsub->id] = $key;
            }
        }

        return $this->to_text($this->question->questiontext) . ' {' .
                implode('; ', $this->stems) . '} -> {' . implode('; ', $this->choices) . '}';
    }

    public function right_answer() {
        $answer = array();
        foreach ($this->stems as $key => $stem) {
            $answer[$stem] = $this->choices[$this->right[$key]];
        }
        return $this->make_summary($answer);
    }

    protected function explode_answer($answer) {
        $bits = explode(',', $answer);
        $selections = array();
        foreach ($bits as $bit) {
            list($stem, $choice) = explode('-', $bit);
            $selections[$stem] = $choice;
        }
        return $selections;
    }

    protected function make_summary($pairs) {
        $bits = array();
        foreach ($pairs as $stem => $answer) {
            $bits[] = $stem . ' -> ' . $answer;
        }
        return implode('; ', $bits);
    }

    protected function lookup_choice($choice) {
        foreach ($this->question->options->subquestions as $matchsub) {
            if ($matchsub->code == $choice) {
                if (array_key_exists($matchsub->id, $this->choices)) {
                    return $matchsub->id;
                } else {
                    return array_search($matchsub->answertext, $this->choices);
                }
            }
        }
        return null;
    }

    public function response_summary($state) {
        $choices = $this->explode_answer($state->answer);
        $pairs = array();
        foreach ($choices as $stem => $choice) {
            if ($choice) {
                $pairs[$this->stems[$stem]] = $this->choices[$this->lookup_choice($choice)];
            }
        }
        if ($pairs) {
            return $this->make_summary($pairs);
        } else {
            return null;
        }
    }

    public function was_answered($state) {
        $choices = $this->explode_answer($state->answer);
        foreach ($choices as $choice) {
            if ($choice) {
                return true;
            }
        }
        return false;
    }

    public function set_first_step_data_elements($state, &$data) {
        $choices = $this->explode_answer($state->answer);
        foreach ($choices as $key => $notused) {
            if (array_key_exists($key, $this->stems)) {
                $this->stemorder[] = $key;
            }
        }

        $this->choiceorder = array_keys($this->choices);
        shuffle($this->choiceorder);
        $this->flippedchoiceorder = array_combine(array_values($this->choiceorder), array_keys($this->choiceorder));

        $data['_stemorder'] = implode(',', $this->stemorder);
        $data['_choiceorder'] = implode(',', $this->choiceorder);
    }

    public function set_data_elements_for_step($state, &$data) {
        $choices = $this->explode_answer($state->answer);

        foreach ($this->stemorder as $i => $key) {
            $choice = $choices[$key];
            if (!$choice) {
                continue;
            }
            $choice = $this->lookup_choice($choice);
            $data['sub' . $i] = $this->flippedchoiceorder[$choice] + 1;
        }
    }
}


class qtype_oumultiresponse_updater extends qtype_updater {
    public function question_summary() {
        $bits = array();
        foreach ($this->question->options->answers as $ans) {
            $bits[] = $this->to_text($ans->answer);
        }
        return parent::question_summary() . ': ' . implode('; ', $bits);
    }

    public function right_answer() {
        $rightbits = array();
        foreach ($this->question->options->answers as $ans) {
            if ($ans->fraction >= 0.5) {
                $rightbits[] = $this->to_text($ans->answer);
            }
        }
        return implode('; ', $rightbits);
    }

    protected function parse_response($answer) {
        list($order, $responsepart) = split(':', $answer);
        $bits = explode(',', $responsepart);

        $responses = array();
        if ($responsepart) {
            foreach ($bits as $bit) {
                list($choice, $history) = explode('h', $bit);
                if (substr($history, 0, 1) === '1') {
                    $responses[] = $choice;
                }
            }
        }

        return array($order, $responses);
    }

    public function response_summary($state) {
        list($order, $responses) = $this->parse_response($state->answer);

        $bits = array();
        foreach ($responses as $response) {
            $bits[] = $this->to_text($this->question->options->answers[$response]->answer);
        }
        return implode('; ', $bits);
    }

    public function was_answered($state) {
        list($order, $responses) = split(':', $state->answer);
        return !empty($responses);
    }

    public function set_first_step_data_elements($state, &$data) {
        list($order, $responses) = $this->parse_response($state->answer);
        $data['_order'] = $order;
    }

    public function set_data_elements_for_step($state, &$data) {
        list($order, $responses) = $this->parse_response($state->answer);
        $order = explode(',', $order);

        $flippedorder = array_combine(array_values($order), array_keys($order));

        foreach ($responses as $response) {
            $data['choice' . ($flippedorder[$response] + 1)] = 1;
        }
    }
}


class qtype_ddwtos_updater extends qtype_updater {
    protected $choices;
    protected $rightchoices;
    protected $places;
    protected $choiceindexmap;
    protected $shuffleorders;

    public function __construct($updater, $question) {
        parent::__construct($updater, $question);
    }

    public function question_summary() {
        $this->choices = array();
        $choiceindexmap= array();

        // Store the choices in arrays by group.
        $i = 1;
        foreach ($this->question->options->answers as $choicedata) {
            $options = unserialize($choicedata->feedback);

            if (array_key_exists($options->draggroup, $this->choices)) {
                $this->choices[$options->draggroup][] = $choicedata->answer;
            } else {
                $this->choices[$options->draggroup][1] = $choicedata->answer;
            }

            end($this->choices[$options->draggroup]);
            $this->choiceindexmap[$i] = array($options->draggroup, $choicedata->answer,
                    key($this->choices[$options->draggroup]));
            $i += 1;
        }

        $this->places = array();
        $this->rightchoices = array();

        // Break up the question text, and store the fragments, places and right answers.
        $bits = preg_split('/\[\[(\d+)]]/', $this->question->questiontext, null, PREG_SPLIT_DELIM_CAPTURE);
        array_shift($bits);
        $i = 1;

        while (!empty($bits)) {
            $choice = array_shift($bits);

            list($group, $choicetext, $choiceindex) = $this->choiceindexmap[$choice];
            $this->places[$i] = $group;
            $this->rightchoices[$i] = $choicetext;

            array_shift($bits);
            $i += 1;
        }

        $bits = array(parent::question_summary());
        foreach ($this->places as $place => $group) {
            $bits[] = '[[' . $place . ']] -> {' .
                    implode(' / ', $this->choices[$group]) . '}';
        }
        return implode('; ', $bits);
    }

    public function right_answer() {
        return $this->make_summary($this->rightchoices);
    }

    public function compare_answers($answer1, $answer2) {
        list($answer1) = explode('=', $answer1);
        list($answer2) = explode('=', $answer2);
        return $answer1 == $answer2;
    }

    protected function explode_answer($answer) {
        list($answer) = explode('=', $answer);
        $bits = explode(';', $answer);

        $selections = array();
        foreach ($bits as $bit) {
            list($place, $choice) = explode('-', $bit);
            if ($place === '' || $choice === '0') {
                continue;
            }

            $selections[$place + 1] = $choice;
        }

        return $selections;
    }

    protected function make_summary($choices) {
        $answers = array();
        foreach ($choices as $group => $ans) {
            $answers[] = '{' . $ans . '}';
        }
        return implode(' ', $answers);
    }

    public function response_summary($state) {
        $choices = $this->explode_answer($state->answer);

        $answers = array();
        foreach ($choices as $place => $choice) {
            list($group, $choicetext, $choiceindex) = $this->choiceindexmap[$choice];
            $answers[$place] = $this->choices[$this->places[$place]][$choiceindex];
        }
        if ($answers) {
            return $this->make_summary($answers);
        } else {
            return null;
        }
    }

    public function was_answered($state) {
        $choices = $this->explode_answer($state->answer);
        foreach ($choices as $choice) {
            if ($choice !== '') {
                return true;
            }
        }
        return false;
    }

    public function set_first_step_data_elements($state, &$data) {
        foreach ($this->choices as $group => $notused) {
            $this->shuffleorders[$group] = array_keys($this->choices[$group]);
            if ($this->question->options->shuffleanswers) {
                srand($state->attempt);
                shuffle($this->shuffleorders[$group]);
            }
            $this->shuffleorders[$group] = array_combine(
                    array_values($this->shuffleorders[$group]), array_keys($this->shuffleorders[$group]));
        }

        foreach ($this->choices as $group => $choices) {
            $indices = array();
            foreach ($this->shuffleorders[$group] as $key => $notused) {
                $indices[] = $key;
            }
            $data['_choiceorder' . $group] = implode(',', $indices);
        }
    }

    public function set_data_elements_for_step($state, &$data) {
        $choices = $this->explode_answer($state->answer);

        foreach ($choices as $place => $choice) {
            list($group, $choicetext, $choiceindex) = $this->choiceindexmap[$choice];
            $data['p' . $place] = $this->shuffleorders[$group][$choiceindex] + 1;
        }
    }
}


class qtype_opaque_updater extends qtype_updater {
    public function question_summary() {
        return $this->question->options->remoteid . '.' . $this->question->options->remoteversion;
    }

    public function right_answer() {
        return '[UNKNOWN]';
    }

    protected function explode_answer($answer) {
        // We store the reponses by turning the associative array $state->responses
        // into a string as follows. For example, array('f2' => 'No, never - ever', 'f1' => '10')
        // becomes 'f1-10,f2-No\, never - ever'. That is, comma separated pairs, sorted by key,
        // key and value linked with a '-', commas in vales escaped with '\'. 

        // Deal with special case: no responses at all.
        if (empty($answer)) {
            return array();
        }

        // Split the responses on non-backslash-escaped commas.
        $bits = preg_split('/(?<!\\\\)\\,/', $answer);

        // Now set $state->responses properly.
        $responses = array();
        foreach ($bits as $reponse) {
            list($key, $value) = explode('-', $reponse, 2);
            $responses[$key] = str_replace('\,', ',', $value);
        }

        return $responses;
    }

    public function response_summary($state) {
        $responses = $this->explode_answer($state->answer);

        if (!empty($responses['__answerLine'])) {
            return $responses['__answerLine'];

        } else if (!empty($responses['__actionSummary'])) {
            return $responses['__actionSummary'];

        } else {
            return implode(', ', $responses);
        }
    }

    public function was_answered($state) {
        return false;
    }

    public function set_first_step_data_elements($state, &$data) {
        $responses = $this->explode_answer($state->answer);
        foreach ($responses as $name => $value) {
            $data[$name] = $value;
        }
    }

    public function set_data_elements_for_step($state, &$data) {
        $responses = $this->explode_answer($state->answer);
        foreach ($responses as $name => $value) {
            if ($name == '__questionLine') {
                continue;
            } else if ($name == '__actionSummary') {
                $name = '-_actionSummary';
            }
            $data[$name] = $value;
        }
    }
}
