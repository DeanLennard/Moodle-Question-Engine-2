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
 * Unit tests for the selection from drop down list question question definition class.
 *
 * @package qtype_sddl
 * @copyright 2011 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');
require_once($CFG->dirroot . '/question/type/sddl/simpletest/helper.php');


/**
 * Unit tests for the selection from drop down list question definition class.
 *
 * @copyright 2011 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_sddl_test extends UnitTestCase {
    /** @var qtype_sddl instance of the question type class to test. */
    protected $qtype;

    public function setUp() {
        $this->qtype = question_bank::get_qtype('sddl');;
    }

    public function tearDown() {
        $this->qtype = null;
    }

    public function assert_same_xml($expectedxml, $xml) {
        $this->assertEqual(str_replace("\r\n", "\n", $expectedxml),
                str_replace("\r\n", "\n", $xml));
    }

    /**
     * @return object the data to construct a question like
     * {@link qtype_sddl_test_helper::make_a_sddl_question()}.
     */
    protected function get_test_question_data() {
        global $USER;

        $sddl = new stdClass;
        $sddl->id = 0;
        $sddl->category = 0;
        $sddl->parent = 0;
        $sddl->questiontextformat = FORMAT_HTML;
        $sddl->defaultmark = 1;
        $sddl->penalty = 0.3333333;
        $sddl->length = 1;
        $sddl->stamp = make_unique_id_code();
        $sddl->version = make_unique_id_code();
        $sddl->hidden = 0;
        $sddl->timecreated = time();
        $sddl->timemodified = time();
        $sddl->createdby = $USER->id;
        $sddl->modifiedby = $USER->id;

        $sddl->name = 'Selection from drop down list question';
        $sddl->questiontext = 'The [[1]] brown [[2]] jumped over the [[3]] dog.';
        $sddl->generalfeedback = 'This sentence uses each letter of the alphabet.';
        $sddl->qtype = 'sddl';

        $sddl->options->shuffleanswers = true;

        test_question_maker::set_standard_combined_feedback_fields($sddl->options);

        $sddl->options->answers = array(
            (object) array('answer' => 'quick', 'feedback' => '1'),
            (object) array('answer' => 'fox', 'feedback' => '2'),
            (object) array('answer' => 'lazy', 'feedback' => '3'),
            (object) array('answer' => 'assiduous', 'feedback' => '3'),
            (object) array('answer' => 'dog', 'feedback' => '2'),
            (object) array('answer' => 'slow', 'feedback' => '1'),
        );

        return $sddl;
    }

    public function test_name() {
        $this->assertEqual($this->qtype->name(), 'sddl');
    }

    public function test_can_analyse_responses() {
        $this->assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_initialise_question_instance() {
        $qdata = $this->get_test_question_data();

        $expected = qtype_sddl_test_helper::make_a_sddl_question();
        $expected->stamp = $qdata->stamp;
        $expected->version = $qdata->version;

        $q = $this->qtype->make_question($qdata);

        $this->assertEqual($expected, $q);
    }

    public function test_get_random_guess_score() {
        $q = $this->get_test_question_data();
        $this->assertWithinMargin(0.5, $this->qtype->get_random_guess_score($q), 0.0000001);
    }

    public function test_get_possible_responses() {
        $q = $this->get_test_question_data();

        $this->assertEqual(array(
            1 => array(
                1 => new question_possible_response('quick', 1),
                2 => new question_possible_response('slow', 0),
                null => question_possible_response::no_response()),
            2 => array(
                1 => new question_possible_response('fox', 1),
                2 => new question_possible_response('dog', 0),
                null => question_possible_response::no_response()),
            3 => array(
                1 => new question_possible_response('lazy', 1),
                2 => new question_possible_response('assiduous', 0),
                null => question_possible_response::no_response()),
        ), $this->qtype->get_possible_responses($q));
    }

    public function test_xml_import() {
        $xml = '  <question type="sddl">
    <name>
      <text>A selection from drop down list question</text>
    </name>
    <questiontext format="moodle_auto_format">
      <text>Put these in order: [[1]], [[2]], [[3]].</text>
    </questiontext>
    <generalfeedback>
      <text>The answer is Alpha, Beta, Gamma.</text>
    </generalfeedback>
    <defaultgrade>3</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <shuffleanswers>1</shuffleanswers>
    <correctfeedback>
      <text><![CDATA[<p>Your answer is correct.</p>]]></text>
    </correctfeedback>
    <partiallycorrectfeedback>
      <text><![CDATA[<p>Your answer is partially correct.</p>]]></text>
    </partiallycorrectfeedback>
    <incorrectfeedback>
      <text><![CDATA[<p>Your answer is incorrect.</p>]]></text>
    </incorrectfeedback>
    <shownumcorrect/>
    <selectoption>
      <text>Alpha</text>
      <group>1</group>
    </selectoption>
    <selectoption>
      <text>Beta</text>
      <group>1</group>
    </selectoption>
    <selectoption>
      <text>Gamma</text>
      <group>1</group>
    </selectoption>
    <hint>
      <text>Try again.</text>
      <shownumcorrect />
    </hint>
    <hint>
      <text>These are the first three letters of the Greek alphabet.</text>
      <shownumcorrect />
      <clearwrong />
    </hint>
  </question>';
        $xmldata = xmlize($xml);

        $importer = new qformat_xml();
        $q = $importer->try_importing_using_qtypes(
                $xmldata['question'], null, null, 'sddl');

        $expectedq = new stdClass;
        $expectedq->qtype = 'sddl';
        $expectedq->name = 'A selection from drop down list question';
        $expectedq->questiontext = 'Put these in order: [[1]], [[2]], [[3]].';
        $expectedq->questiontextformat = FORMAT_MOODLE;
        $expectedq->generalfeedback = 'The answer is Alpha, Beta, Gamma.';
        $expectedq->defaultmark = 3;
        $expectedq->length = 1;
        $expectedq->penalty = 0.3333333;

        $expectedq->shuffleanswers = 1;
        $expectedq->correctfeedback = '<p>Your answer is correct.</p>';
        $expectedq->partiallycorrectfeedback = '<p>Your answer is partially correct.</p>';
        $expectedq->shownumcorrect = true;
        $expectedq->incorrectfeedback = '<p>Your answer is incorrect.</p>';

        $expectedq->choices = array(
            array('answer' => 'Alpha', 'selectgroup' => 1),
            array('answer' => 'Beta', 'selectgroup' => 1),
            array('answer' => 'Gamma', 'selectgroup' => 1),
        );

        $expectedq->hint = array('Try again.', 'These are the first three letters of the Greek alphabet.');
        $expectedq->hintshownumcorrect = array(true, true);
        $expectedq->hintclearwrong = array(false, true);

        $this->assert(new CheckSpecifiedFieldsExpectation($expectedq), $q);
    }

    public function test_xml_export() {
        $qdata = new stdClass;
        $qdata->id = 123;
        $qdata->qtype = 'sddl';
        $qdata->name = 'A select from drop down list question';
        $qdata->questiontext = 'Put these in order: [[1]], [[2]], [[3]].';
        $qdata->questiontextformat = FORMAT_MOODLE;
        $qdata->generalfeedback = 'The answer is Alpha, Beta, Gamma.';
        $qdata->defaultmark = 3;
        $qdata->length = 1;
        $qdata->penalty = 0.3333333;
        $qdata->hidden = 0;

        $qdata->options->shuffleanswers = 1;
        $qdata->options->correctfeedback = '<p>Your answer is correct.</p>';
        $qdata->options->partiallycorrectfeedback = '<p>Your answer is partially correct.</p>';
        $qdata->options->shownumcorrect = true;
        $qdata->options->incorrectfeedback = '<p>Your answer is incorrect.</p>';

        $qdata->options->answers = array(
            new question_answer('Alpha', 0, '1'),
            new question_answer('Beta', 0, '1'),
            new question_answer('Gamma', 0, '1'),
        );

        $qdata->hints = array(
            new question_hint_with_parts('Try again.', true, false),
            new question_hint_with_parts('These are the first three letters of the Greek alphabet.', true, true),
        );

        $exporter = new qformat_xml();
        $xml = $exporter->writequestion($qdata);

        $expectedxml = '<!-- question: 123  -->
  <question type="sddl">
    <name>
      <text>A select from drop down list question</text>
    </name>
    <questiontext format="moodle_auto_format">
      <text>Put these in order: [[1]], [[2]], [[3]].</text>
    </questiontext>
    <generalfeedback>
      <text>The answer is Alpha, Beta, Gamma.</text>
    </generalfeedback>
    <defaultgrade>3</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <shuffleanswers>1</shuffleanswers>
    <correctfeedback>
      <text><![CDATA[<p>Your answer is correct.</p>]]></text>
    </correctfeedback>
    <partiallycorrectfeedback>
      <text><![CDATA[<p>Your answer is partially correct.</p>]]></text>
    </partiallycorrectfeedback>
    <incorrectfeedback>
      <text><![CDATA[<p>Your answer is incorrect.</p>]]></text>
    </incorrectfeedback>
    <shownumcorrect/>
    <selectoption>
      <text>Alpha</text>
      <group>1</group>
    </selectoption>
    <selectoption>
      <text>Beta</text>
      <group>1</group>
    </selectoption>
    <selectoption>
      <text>Gamma</text>
      <group>1</group>
    </selectoption>
    <hint>
      <text>Try again.</text>
      <shownumcorrect/>
    </hint>
    <hint>
      <text>These are the first three letters of the Greek alphabet.</text>
      <shownumcorrect/>
      <clearwrong/>
    </hint>
  </question>
';

        $this->assert_same_xml($expectedxml, $xml);
    }
}
