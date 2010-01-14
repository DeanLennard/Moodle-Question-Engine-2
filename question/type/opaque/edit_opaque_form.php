<?php
/**
 * Defines the editing form for the Opaque question type.
 *
 * @copyright &copy; 2006 The Open University
 * @author T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package opaquequestiontype
 *//** */
require_once(dirname(__FILE__) . '/locallib.php');

/**
 * Form definition base class. This defines the common fields that
 * all question types need. Question types should define their own
 * class that inherits from this one, and implements the definition_inner()
 * method.
 */
class question_edit_opaque_form extends question_edit_form {
    /**
     * Build the form definition.
     */
    function definition() {
        parent::definition();
        $mform =& $this->_form;
        $mform->removeElement('questiontext');
        $mform->removeElement('questiontextformat');
        $mform->removeElement('image');
        $mform->removeElement('generalfeedback');
        $mform->removeElement('defaultgrade');
        $mform->removeElement('penalty');
        $mform->addElement('hidden', 'defaultgrade');
        $mform->setType('defaultgrade', PARAM_INT);
        $mform->setDefault('defaultgrade', 1);
    }
    
    /**
     * Add question-type specific form fields.
     * 
     * @param object $mform the form being built. 
     */
    function definition_inner(&$mform) {
        $mform->addElement('select', 'engineid', get_string('questionengine', 'qtype_opaque'), installed_engine_choices());
        $mform->setType('engineid', PARAM_INT);
        $mform->addRule('engineid', null, 'required', null, 'client');
        $mform->setHelpButton('engineid', array('questionengine', get_string('questionengine', 'qtype_opaque'), 'qtype_opaque'));
        
        $mform->addElement('text', 'remoteid', get_string('questionid', 'qtype_opaque'), array('size' => 50));
        $mform->setType('remoteid', PARAM_RAW);
        $mform->addRule('remoteid', null, 'required', null, 'client');
        $mform->setHelpButton('remoteid', array('questionid', get_string('questionid', 'qtype_opaque'), 'qtype_opaque'));

        $mform->addElement('text', 'remoteversion', get_string('questionversion', 'qtype_opaque'), array('size' => 3));
        $mform->setType('remoteversion', PARAM_RAW);
        $mform->addRule('remoteversion', null, 'required', null, 'client');
    }
    
    protected function definition_adaptive(&$mform) {
    //do nothing
    }
    /**
     * Validate the submitted data.
     * 
     * @param $data the submitted data.
     * @return true if valid, or an array of error messages if not.
     */
    function validation(&$data, $files) {
        $partregexp = '[_a-z][_a-zA-Z0-9]*';
        $errors = parent::validation($data, $files);

        // Check we can connect to this questoin engine.
        $engine = load_engine_def($data['engineid']);
        if (is_string($engine)) {
            $errors['engineid'] = $engine;
        }

        $remoteidok = true;
        if (!preg_match("/^$partregexp(\\.$partregexp)*\$/", $data['remoteid'])) {
            $errors['remoteid'] = get_string('invalidquestionidsyntax', 'qtype_opaque');
            $remoteidok = false;
        }
        if (!preg_match('/^\d+\.\d+$/', $data['remoteversion'])) {
            $errors['remoteversion'] = get_string('invalidquestionversionsyntax', 'qtype_opaque');
            $remoteidok = false;
        }

        // Try connecting to the remote question engine both as extra validation of the id, and
        // also to get the default grade.
        if ($remoteidok) {
            $metadata = get_question_metadata($engine, $data['remoteid'], $data['remoteversion']);
            if (is_string($metadata)) {
                $errors['remoteid'] = $metadata;
            } else if (!isset($metadata['questionmetadata']['#']['scoring'][0]['#']['marks'][0]['#'])) {
                $errors['remoteid'] = get_string('maxgradenotreturned');
            } else {
                $this->_defaultgrade = $metadata['questionmetadata']['#']['scoring'][0]['#']['marks'][0]['#'];
            }
        }

        return $errors;
    }

    function get_data($slashed=true) {
        // We override get_data to to add the defaultgrade, which was determined during validation,
        // to the data that is returned.
        $data = parent::get_data($slashed);
        if (is_object($data) && isset($this->_defaultgrade)) {
            $data->defaultgrade = $this->_defaultgrade;
        }
        return $data;
    }

    function qtype() {
        return 'opaque';
    }
}
?>