<?php

/**
 * @file
 * Date API elements themes and validation.
 * This file is only included during the edit process to reduce memory usage.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\date_api\DateGranularity;

/**
 * Implements hook_element_info().
 *
 * Parameters for date form elements, designed to have sane defaults so any
 * or all can be omitted.
 *
 * Fill the element #default_value with a date in datetime format,
 * (YYYY-MM-DD HH:MM:SS), adjusted to the proper local timezone.
 *
 * NOTE - Converting a date stored in the database from UTC to the local zone
 * and converting it back to UTC before storing it is not handled by this
 * element and must be done in pre-form and post-form processing!!
 *
 * The date_select element will create a collection of form elements, with a
 * separate select or textfield for each date part. The whole collection will
 * get reformatted back to a date value of the requested type during validation.
 *
 * The date_timezone element will create a drop-down selector to pick a
 * timezone name.
 *
 * The date_year_range element will create two textfields (for users with
 * JavaScript enabled they will appear as drop-down selectors with an option
 * for custom text entry) to pick a range of years that will be passed to form
 * submit handlers as a single string (e.g., -3:+3).
 *
 * #date_timezone
 *   The local timezone to be used to create this date.
 *
 * #date_format
 *   A format string that describes the format and order of date parts to
 *   display in the edit form for this element. This makes it possible
 *   to show date parts in a custom order, or to leave some of them out.
 *   Be sure to add 'A' or 'a' to get an am/pm selector. Defaults to the
 *   short site default format.
 *
 * #date_label_position
 *   Handling option for date part labels, like 'Year', 'Month', and 'Day',
 *   can be 'above' the date part, 'within' it, or 'none', default is 'above' .
 *   The 'within' option shows the label as the first option in a select list
 *   or the default value for an empty textfield, taking up less screen space.
 *
 * #date_increment
 *   Increment minutes and seconds by this amount, default is 1.
 *
 * #date_year_range
 *   The number of years to go back and forward in a year selector,
 *   default is -3:+3 (3 back and 3 forward).
 *
 * #date_text_parts
 *   Array of date parts that should use textfields instead of selects
 *   i.e. array('year') will format the year as a textfield and other
 *   date parts as drop-down selects.
 */
function _date_api_element_info() {
  $date_base = array(
    '#input' => TRUE, '#tree' => TRUE,
    '#date_timezone' => '',
    '#date_flexible' => 0,
    '#date_text_parts' => array(),
    '#date_increment' => 1,
    '#date_year_range' => '-3:+3',
    '#date_label_position' => 'above',
  );
  if (module_exists('ctools')) {
    $date_base['#pre_render'] = array('ctools_dependent_pre_render');
  }
  $type['date_timezone'] = array(
    '#input' => TRUE, '#tree' => TRUE,
    '#process' => array('date_timezone_element_process'),
    '#theme_wrappers' => array('date_timezone'),
    '#value_callback' => 'date_timezone_element_value_callback',
  );
  $type['date_year_range'] = array(
    '#input' => TRUE,
    '#process' => array('date_year_range_element_process'),
    '#value_callback' => 'date_year_range_element_value_callback',
    '#element_validate' => array('date_year_range_validate'),
  );
  $types['datelist'] = array(
    '#input' => TRUE,
    '#element_validate' => array('datelist_validate'),
    '#process' => array('form_process_datelist'),
    '#value_callback' => 'datelist_value_callback',
    '#theme' => 'datelist',
    '#theme_wrappers' => array('form_element'),
    '#date_date_format' => variable_get('date_format_short', 'm/d/Y - H:i'),
    '#date_year_range' => '1900:2050',
    '#date_increment' => 1,
    '#date_calendar' => 'gregorian',
  );
  return $type;
}

/**
 * Process callback which creates a date_year_range form element.
 */
function date_year_range_element_process($element, &$form_state, $form) {
  // Year range is stored in the -3:+3 format, but collected as two separate
  // textfields.
  $element['years_back'] = array(
    '#type' => 'textfield',
    '#title' => t('Starting year'),
    '#default_value' => $element['#value']['years_back'],
    '#size' => 10,
    '#maxsize' => 10,
    '#attributes' => array('class' => array('select-list-with-custom-option', 'back')),
    '#description' => t('Enter a relative value (-9, +9) or an absolute year such as 2015.'),
  );
  $element['years_forward'] = array(
    '#type' => 'textfield',
    '#title' => t('Ending year'),
    '#default_value' => $element['#value']['years_forward'],
    '#size' => 10,
    '#maxsize' => 10,
    '#attributes' => array('class' => array('select-list-with-custom-option', 'forward')),
    '#description' => t('Enter a relative value (-9, +9) or an absolute year such as 2015.'),
  );

  $element['#tree'] = TRUE;
  $element['#attached']['js'][] = drupal_get_path('module', 'date_api') . '/date_year_range.js';

  $context = array(
   'form' => $form,
  );
  drupal_alter('date_year_range_process', $element, $form_state, $context);

  return $element;
}

/**
 * Element value callback for the date_year_range form element.
 */
function date_year_range_element_value_callback($element, $input = FALSE, &$form_state = array()) {
  // Convert the element's default value from a string to an array (to match
  // what we will get from the two textfields when the form is submitted).
  if ($input === FALSE) {
    list($years_back, $years_forward) = explode(':', $element['#default_value']);
    return array(
      'years_back' => $years_back,
      'years_forward' => $years_forward,
    );
  }
}

/**
 * Element validation function for the date_year_range form element.
 */
function date_year_range_validate(&$element, &$form_state) {
  // Recombine the two submitted form values into the -3:+3 format we will
  // validate and save.
  $year_range_submitted = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
  $year_range = $year_range_submitted['years_back'] . ':' . $year_range_submitted['years_forward'];
  drupal_array_set_nested_value($form_state['values'], $element['#parents'], $year_range);
  if (!date_range_valid($year_range)) {
    form_error($element['years_back'], t('Starting year must be in the format -9, or an absolute year such as 1980.'));
    form_error($element['years_forward'], t('Ending year must be in the format +9, or an absolute year such as 2030.'));
  }
}

/**
 * Element value callback for date_timezone element.
 */
function date_timezone_element_value_callback($element, $input = FALSE, &$form_state = array()) {
  $return = '';
  if ($input !== FALSE) {
    $return = $input;
  }
  elseif (!empty($element['#default_value'])) {
    $return = array('timezone' => $element['#default_value']);
  }
  return $return;
}

/**
 * Creates a timezone form element.
 *
 * @param array $element
 *   The timezone form element.
 *
 * @return array
 *   the timezone form element
 */
function date_timezone_element_process($element, &$form_state, $form) {
  if (date_hidden_element($element)) {
    return $element;
  }

  $element['#tree'] = TRUE;
  $label = theme('date_part_label_timezone', array('part_type' => 'select', 'element' => $element));
  $element['timezone'] = array(
    '#type' => 'select',
    '#title' => $element['#date_label_position'] == 'above' ? $label : '',
    '#options' => system_time_zones($element['#required']),
    '#value' => $element['#value'],
    '#weight' => $element['#weight'],
    '#required' => $element['#required'],
    '#theme' => 'date_select_element',
    '#theme_wrappers' => array('form_element'),
  );
  if (isset($element['#element_validate'])) {
    array_push($element['#element_validate'], 'date_timezone_validate');
  }
  else {
    $element['#element_validate'] = array('date_timezone_validate');
  }

  $context = array(
   'form' => $form,
  );
  drupal_alter('date_timezone_process', $element, $form_state, $context);

  return $element;
}

/**
 *  Validation for timezone input
 *
 *  Move the timezone value from the nested field back to the original field.
 */
function date_timezone_validate($element, &$form_state) {
  if (date_hidden_element($element)) {
    return;
  }

  form_set_value($element, $element['#value']['timezone'], $form_state);
}