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
 * Defines the editing form for record audio and video questions.
 *
 * @package   qtype_recordrtc
 * @copyright 2019 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use qtype_recordrtc\widget_info;

require_once($CFG->dirroot . '/question/type/edit_question_form.php');


/**
 * The editing form for record audio and video questions.
 *
 * @copyright 2019 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_recordrtc_edit_form extends question_edit_form {

    /**
     * Get the current value for the question text that will be displayed in the form.
     *
     * @return string question text HTML.
     */
    protected function get_current_question_text(): string {
        $submitteddata = optional_param_array('questiontext', '', PARAM_RAW);
        if ($submitteddata) {
            // Form has been submitted, but it being re-displayed.
            return $submitteddata['text'];
        }
        if (isset($this->question->id)) {
            // Form is being loaded to edit an existing question.
            return $this->question->questiontext;
        }
        // Creating new question.
        return '';
    }

    /**
     * Get the current value for the mediatype field.
     *
     * @return string one of the qtype_recordrtc::MEDIA_TYPE_... constants.
     */
    protected function get_current_mediatype(): string {
        $mediatype = $this->optional_param('mediatype', '', PARAM_ALPHA);
        if ($mediatype) {
            // Form has been submitted, but it being re-displayed.
            return $mediatype;
        }
        if (isset($this->question->id)) {
            // Form is being loaded to edit an existing question.
            return $this->question->options->mediatype;
        }
        // Creating new question.
        // The next line needs to match the default below.
        return $this->get_default_value_wrapper('mediatype', qtype_recordrtc::MEDIA_TYPE_AUDIO);
    }

    protected function definition_inner($mform) {
        $currentmediatype = $this->get_current_mediatype();

        // Field for mediatype.
        $mediaoptions = [
            qtype_recordrtc::MEDIA_TYPE_AUDIO => get_string('audio', 'qtype_recordrtc'),
            qtype_recordrtc::MEDIA_TYPE_VIDEO => get_string('video', 'qtype_recordrtc'),
            qtype_recordrtc::MEDIA_TYPE_CUSTOM_AV => get_string('customav', 'qtype_recordrtc')
        ];
        $mediatype = $mform->createElement('select', 'mediatype', get_string('mediatype', 'qtype_recordrtc'), $mediaoptions);
        $mform->insertElementBefore($mediatype, 'questiontext');
        $mform->addHelpButton('mediatype', 'mediatype', 'qtype_recordrtc');
        $mform->setDefault('mediatype', $this->get_default_value_wrapper('mediatype', qtype_recordrtc::MEDIA_TYPE_AUDIO));

        // Add instructions and widget placeholder templates for question authors to copy and paste into the question text.
        $avplaceholder = $mform->createElement('static', 'avplaceholder', '',
                widget_info::make_placeholder('recorder1', 'audio', 120) . ' &nbsp; ' . widget_info::make_placeholder('recorder2', 'video', 90));
        $avplaceholdergroup = $mform->createElement('group', 'avplaceholdergroup',
                get_string('avplaceholder', 'qtype_recordrtc'), [$avplaceholder]);
        $mform->hideIf('avplaceholdergroup', 'mediatype', 'noteq', qtype_recordrtc::MEDIA_TYPE_CUSTOM_AV);
        $mform->insertElementBefore($avplaceholdergroup, 'defaultmark');
        $mform->addHelpButton('avplaceholdergroup', 'avplaceholder', 'qtype_recordrtc');

        // Add the update-form button.
        $verify = $mform->createElement('submit', 'updateform', get_string('updateform', 'qtype_recordrtc'));
        if ($currentmediatype !== qtype_recordrtc::MEDIA_TYPE_CUSTOM_AV) {
            // If the question is currently using custom A/V, then the refresh form button must always be visible,
            // so you can refresh the form if you change the media type.
            $mform->hideIf('updateform', 'mediatype', 'noteq', qtype_recordrtc::MEDIA_TYPE_CUSTOM_AV);
        }
        $mform->insertElementBefore($verify, 'defaultmark');
        $mform->registerNoSubmitButton('updateform');

        // Field for timelimitinseconds.
        $mform->addElement('duration', 'timelimitinseconds', get_string('timelimit', 'qtype_recordrtc'),
                ['units' => [60, 1], 'optional' => false]);
        $mform->addHelpButton('timelimitinseconds', 'timelimit', 'qtype_recordrtc');
        $mform->setDefault('timelimitinseconds',
                $this->get_default_value_wrapper('timelimitinseconds', qtype_recordrtc::DEFAULT_TIMELIMIT));

        // Fields for widget feedback.
        if ($currentmediatype === qtype_recordrtc::MEDIA_TYPE_CUSTOM_AV) {
            $this->add_per_input_feedback_fields();
        }
    }

    /**
     * Construct the part of the form with the per-input feedback fields.
     *
     * This method should only be called if the media type is currently MEDIA_TYPE_CUSTOM_AV.
     */
    public function add_per_input_feedback_fields(): void {
        $qtype = new qtype_recordrtc();
        $mform = $this->_form;

        // Work out what widgets we have.
        $widgets = $qtype->get_widget_placeholders($this->get_current_question_text());
        if (!$widgets) {
            // No widgets. Nothing to do.
            return;
        }

        // Add them to the form.
        $mform->addElement('header', 'feedbackheader', get_string('feedbackheader', 'qtype_recordrtc'));

        foreach ($widgets as $widget) {
            $mform->addElement('editor', $this->feedback_field($widget->name),
                    get_string('feedbackfor', 'qtype_recordrtc', $widget->name),
                    ['rows' => 3], $this->editoroptions);
            $mform->setType($widget->name, PARAM_RAW);
        }
    }

    protected function feedback_field(string $widgetname): string {
        return 'feedbackfor' . $widgetname;
    }

    /**
     * Wrapper around get_default_value so we can still support older Moodle versions.
     *
     * @param string $name the name of the form field.
     * @param mixed $default default value.
     * @return string|null default value for a given form element.
     */
    protected function get_default_value_wrapper(string $name, $default): ?string {
        if (method_exists($this, 'get_default_value')) {
            return $this->get_default_value($name, $default);
        } else {
            return $default;
        }
    }

    public function data_preprocessing($question): stdClass {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_per_input_feedbacks($question);
        return $question;
    }

    /**
     * Perform the necessary preprocessing for the fields added by
     * {@link add_per_input_feedback_fields()}.
     *
     * @param stdClass $question the data beig passed to the form.
     * @return stdClass updated $question
     */
    public function data_preprocessing_per_input_feedbacks(stdClass $question): stdClass {
        if (empty($question->options->answers)) {
            return $question;
        }

        $key = 0;
        foreach ($question->options->answers as $answer) {
            $widgetname = $answer->answer;
            $fieldname = $this->feedback_field($widgetname);

            // Prepare the feedback editor to display files in draft area.
            $draftitemid = file_get_submitted_draft_itemid('feedback[' . $key . ']');
            $question->{$fieldname}['text'] = file_prepare_draft_area(
                    $draftitemid,          // Draftid
                    $this->context->id,    // context
                    'question',            // component
                    'answerfeedback',      // filarea
                    !empty($answer->id) ? (int) $answer->id : null, // itemid
                    $this->fileoptions,    // options
                    $answer->feedback      // text.
            );
            $question->{$fieldname}['itemid'] = $draftitemid;
            $question->{$fieldname}['format'] = $answer->feedbackformat;
        }

        return $question;
    }

    public function validation($fromform, $files): array {
        $errors = parent::validation($fromform, $files);

        // Validate placeholders in the question text.
        $placeholdererrors = (new qtype_recordrtc)->validate_widget_placeholders(
                $fromform['questiontext']['text'], $fromform['mediatype']);
        if ($placeholdererrors) {
            $errors['questiontext'] = $placeholdererrors;
        }

        // Validate the time limit.
        switch ($fromform['mediatype']) {
            case qtype_recordrtc::MEDIA_TYPE_AUDIO :
                $maxtimelimit = get_config('qtype_recordrtc', 'audiotimelimit');
                break;

            case qtype_recordrtc::MEDIA_TYPE_VIDEO :
            case qtype_recordrtc::MEDIA_TYPE_CUSTOM_AV :
                // We are using the 'Max video recording duration' for customav media type,
                // because it is shorter than 'Max audio recording duration' and we need to
                // use the value of $data['timelimitinseconds'] as default for widgets in
                // question text when the bespoke duration is not specified by the widget itself.
                $maxtimelimit = get_config('qtype_recordrtc', 'videotimelimit');
                break;

            default: // Should not get here.
                $maxtimelimit = qtype_recordrtc::DEFAULT_TIMELIMIT;
                break;
        }
        if ($fromform['timelimitinseconds'] > $maxtimelimit) {
            $errors['timelimitinseconds'] = get_string('err_timelimit', 'qtype_recordrtc', format_time($maxtimelimit));
        }
        if ($fromform['timelimitinseconds'] <= 0) {
            $errors['timelimitinseconds'] = get_string('err_timelimitpositive', 'qtype_recordrtc');
        }
        return $errors;
    }

    public function qtype(): string {
        return 'recordrtc';
    }
}
