<?php

/**
 * @file
 * Date administration code.
 */

use Drupal\date_api\DateGranularity;

/**
 * Settings for the default formatter.
 */
function date_default_formatter_settings_form($field, $instance, $view_mode, $form, &$form_state) {
  $display = $instance['display'][$view_mode];
  $settings = $display['settings'];
  $formatter = $display['type'];
  $form = array();

  $form['format_type'] = array(
    '#title' => t('Choose how users view dates and times:'),
    '#type' => 'select',
    '#options' => date_format_type_options(),
    '#default_value' => $settings['format_type'],
    '#description' => t('To add or edit options, visit <a href="@date-time-page">Date and time settings</a>.', array('@date-time-page' => url('admin/config/regional/date-time'))),
    '#weight' => 0,
  );

  $form['fromto'] = array(
    '#title' => t('Display:'),
    '#type' => 'select',
    '#options' => array(
      'both' => t('Both Start and End dates'),
      'value' => t('Start date only'),
      'value2' => t('End date only'),
    ),
    '#access' => $field['settings']['todate'],
    '#default_value' => $settings['fromto'],
    '#weight' => 1,
  );

  // Make the string translatable by keeping it as a whole rather than
  // translating prefix and suffix separately.
  list($prefix, $suffix) = explode('@count', t('Show @count value(s)'));
  $form['multiple_number'] = array(
    '#type' => 'textfield',
    '#title' => t('Multiple values:'),
    '#size' => 5,
    '#field_prefix' => $prefix,
    '#field_suffix' => $suffix,
    '#default_value' => $settings['multiple_number'],
    '#weight' => 2,
    '#access' => ($field['cardinality'] == FIELD_CARDINALITY_UNLIMITED) || ($field['cardinality'] > 1),
    '#description' => t('Identify a specific number of values to display, or leave blank to show all values.'),
  );

  list($prefix, $suffix) = explode('@isodate', t('starting from @isodate'));
  $form['multiple_from'] = array(
    '#type' => 'textfield',
    '#size' => 15,
    '#field_prefix' => $prefix,
    '#field_suffix' => $suffix,
    '#default_value' => $settings['multiple_from'],
    '#weight' => 3,
    '#access' => ($field['cardinality'] == FIELD_CARDINALITY_UNLIMITED) || ($field['cardinality'] > 1),
  );

  list($prefix, $suffix) = explode('@isodate', t('ending with @isodate'));
  $form['multiple_to'] = array(
    '#type' => 'textfield',
    '#size' => 15,
    '#field_prefix' => $prefix,
    '#field_suffix' => $suffix,
    '#default_value' => $settings['multiple_to'],
    '#weight' => 4,
    '#access' => ($field['cardinality'] == FIELD_CARDINALITY_UNLIMITED) || ($field['cardinality'] > 1),
    '#description' => t('Identify specific start and/or end dates in the format YYYY-MM-DDTHH:MM:SS, or leave blank for all available dates.'),
  );

  return $form;
}

/**
 * Settings for the interval formatter.
 */
function date_interval_formatter_settings_form($field, $instance, $view_mode, $form, &$form_state) {
  $display = $instance['display'][$view_mode];
  $settings = $display['settings'];
  $form = array();
  $form['interval'] = array(
    '#title' => t('Interval'),
    '#description' => t("How many time units should be shown in the 'time ago' string."),
    '#type' => 'select',
    '#options' => drupal_map_assoc(range(1, 6)),
    '#default_value' => $settings['interval'],
    '#weight' => 0,
  );
  // Uses the same options used by Views format_interval.
  $options = array(
    'raw time ago' => t('Time ago'),
    'time ago' => t('Time ago (with "ago" appended)'),
    'raw time hence' => t('Time hence'),
    'time hence' => t('Time hence (with "hence" appended)'),
    'raw time span' => t('Time span (future dates have "-" prepended)'),
    'inverse time span' => t('Time span (past dates have "-" prepended)'),
    'time span' => t('Time span (with "ago/hence" appended)'),
  );
  $form['interval_display'] = array(
    '#title' => t('Display'),
    '#description' => t("How to display the time ago or time hence for this field."),
    '#type' => 'select',
    '#options' => $options,
    '#default_value' => $settings['interval_display'],
    '#weight' => 0,
  );
  return $form;
}

/**
 * Settings summary for the default formatter.
 */
function date_default_formatter_settings_summary($field, $instance, $view_mode) {
  $display = $instance['display'][$view_mode];
  $settings = $display['settings'];
  $formatter = $display['type'];
  $format_types = date_format_type_options();
  $summary = array();
  $format = FALSE;
  switch ($formatter) {
    case 'date_plain':
      $format = t('Plain');
      break;
    case 'format_interval':
      $format = t('Interval');
      break;
    default:
      if (!empty($format_types[$settings['format_type']])) {
        $format = $format_types[$settings['format_type']];
      }
  }
  if ($format) {
    $summary[] = t('Display dates using the @format format', array('@format' => $format));
  }
  else {
    $summary[] = t('Display dates using the default format because the specified format (@format) is not defined', array('@format' => $settings['format_type']));
  }

  if (array_key_exists('fromto', $settings) && $field['settings']['todate']) {
    $options = array(
      'both' => t('Display both Start and End dates'),
      'value' => t('Display Start date only'),
      'value2' => t('Display End date only'),
    );
    $summary[] = $options[$settings['fromto']];
  }

  if (array_key_exists('multiple_number', $settings) && !empty($field['cardinality'])) {
    $summary[] = t('Show @count value(s) starting with @date1, ending with @date2', array(
      '@count' => !empty($settings['multiple_number']) ? $settings['multiple_number'] : t('all'),
      '@date1' => !empty($settings['multiple_from']) ? $settings['multiple_from'] : t('earliest'),
      '@date2' => !empty($settings['multiple_to']) ? $settings['multiple_to'] : t('latest'),
    ));
  }

  return $summary;
}

/**
 * Settings summary for the interval formatter.
 *
 * @TODO Add settings later.
 */
function date_interval_formatter_settings_summary($field, $instance, $view_mode) {
  $summary = array();
  $display = $instance['display'][$view_mode];
  $settings = $display['settings'];
  $formatter = $display['type'];
  $summary[] = t('Display time ago, showing @interval units.', array('@interval' => $settings['interval']));

  return $summary;
}

/**
 * Helper function for date_field_instance_settings_form().
 *
 * @see date_field_instance_settings_form_validate()
 */
function _date_field_instance_settings_form($field, $instance) {
  $widget = $instance['widget'];
  $settings = $instance['settings'];
  $widget_settings = $instance['widget']['settings'];

  $form['default_value'] = array(
    '#type' => 'select',
    '#title' => t('Default date'),
    '#default_value' => $settings['default_value'],
    '#options' => array('blank' => t('No default value'), 'now' => t('Now'), 'strtotime' => t('Relative')),
    '#weight' => 1,
    '#fieldset' => 'default_values',
  );

  $description = t("Describe a time by reference to the current day, like '+90 days' (90 days from the day the field is created) or '+1 Saturday' (the next Saturday). See !strtotime for more details.", array('!strtotime' => l(t('strtotime'), 'http://www.php.net/manual/en/function.strtotime.php')));
  $form['default_value_code'] = array(
    '#type' => 'textfield',
    '#title' => t('Relative default value'),
    '#description' => $description,
    '#default_value' => $settings['default_value_code'],
    '#states' => array(
      'visible' => array(
        ':input[name="instance[settings][default_value]"]' => array('value' => 'strtotime')),
      ),
    '#weight' => 1.1,
    '#fieldset' => 'default_values',
  );
  $form['default_value2'] = array(
    '#type' => !empty($field['settings']['todate']) ? 'select' : 'hidden',
    '#title' => t('Default end date'),
    '#default_value' => $settings['default_value2'],
    '#options' => array('same' => t('Same as Default date'), 'blank' => t('No default value'), 'now' => t('Now'), 'strtotime' => t('Relative')),
    '#weight' => 2,
    '#fieldset' => 'default_values',
  );
  $form['default_value_code2'] = array(
    '#type' => !empty($field['settings']['todate']) ? 'textfield' : 'hidden',
    '#title' => t('Relative default value for end date'),
    '#description' => $description,
    '#default_value' => $settings['default_value_code2'],
    '#states' => array(
      'visible' => array(
        ':input[name="instance[settings][default_value2]"]' => array('value' => 'strtotime')),
      ),
    '#weight' => 2.1,
    '#fieldset' => 'default_values',
  );

  $form['#element_validate'] = array('date_field_instance_settings_form_validate');

  $context = array(
    'field' => $field,
    'instance' => $instance,
  );
  drupal_alter('date_field_instance_settings_form', $form, $context);

  return $form;
}

/**
 * Form validation handler for _date_field_instance_settings_form().
 */
function date_field_instance_settings_form_validate(&$form, &$form_state) {
  $settings = $form_state['values']['instance']['settings'];

  if ($settings['default_value'] == 'strtotime') {
    $is_strtotime = @strtotime($settings['default_value_code']);
    if (!$is_strtotime) {
      form_set_error('instance][settings][default_value_code', t('The Strtotime default value is invalid.'));
    }
  }
  if (isset($settings['default_value2']) && $settings['default_value2'] == 'strtotime') {
    $is_strtotime = @strtotime($settings['default_value_code2']);
    if (!$is_strtotime) {
      form_set_error('instance][settings][default_value_code2', t('The Strtotime default value for the End Date is invalid.'));
    }
  }
}

/**
 * Helper function for date_field_settings_form().
 *
 * @see date_field_settings_validate()
 */
function _date_field_settings_form($field, $instance, $has_data) {
  $settings = $field['settings'];

  $form = array(
    '#element_validate' => array('date_field_settings_validate'),
  );

  // Make sure granularity is in the right format and has no empty values.
  if (!empty($settings['granularity']) && is_array($settings['granularity'])) {
    $granularity = array_filter($settings['granularity']);
  }
  $tz_handling = $settings['tz_handling'];

  $description = t('Select the date attributes to collect and store.');
  $options = DateGranularity::granularityNames();
  $checkbox_year = array(
    '#type' => 'checkbox',
    '#title' => check_plain($options['year']),
    '#value' => 'year',
    '#return_value' => 'year',
    '#disabled' => TRUE,
  );
  unset($options['year']);
  $form['granularity'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Date attributes to collect'),
    '#default_value' => $granularity,
    '#options' => $options,
    '#attributes' => array('class' => array('container-inline')),
    '#description' => $description,
    '#disabled' => $has_data,
    'year' => $checkbox_year,
  );

  $description = t('End dates are used to collect duration. E.g., allow an event to start on September 15, and end on September 16.');
  $form['enddate_get'] = array(
    '#type' => 'checkbox',
    '#title' => t('Collect an end date'),
    '#description' => $description,
    '#default_value' => (empty($settings['todate']) ? FALSE : TRUE),
    '#disabled' => $has_data,
  );
  $form['enddate_required'] = array(
    '#type' => 'checkbox',
    '#title' => t('Required'),
    '#default_value' => ((isset($settings['todate']) && $settings['todate'] === 'required') ? TRUE : FALSE),
    '#disabled' => $has_data,
    '#states' => array(
      'invisible' => array(
        'input[name="field[settings][enddate_get]"]' => array('checked' => FALSE),
      ),
    ),
  );
  $description = t('Select the timezone handling method for this date field.');
  $form['tz_handling'] = array(
    '#type' => 'select',
    '#title' => t('Time zone handling'),
    '#default_value' => $tz_handling,
    '#options' => date_timezone_handling_options(),
    '#description' => $description,
    '#disabled' => $has_data,
    '#attached' => array(
      'js' => array(drupal_get_path('module', 'date') . '/date_admin.js'),
    ),
  );
  // Force this value to hidden because we don't want to allow it to be changed
  // right now, but allow it to be a variable if needed.
  $form['timezone_db'] = array(
    '#type' => 'hidden',
    '#value' => date_get_timezone_db($tz_handling),
  );

  $form['cache_enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Cache dates'),
    '#description' => t('Date objects can be created and cached as date fields are loaded rather than when they are displayed to improve performance.'),
    '#default_value' => !empty($settings['cache_enabled']),
    '#weight' => 10,
  );
  $form['cache_count'] = array(
    '#type' => 'textfield',
    '#title' => t('Maximum dates per field'),
    '#default_value' => (isset($settings['cache_count'])) ? $settings['cache_count'] : 4,
    '#description' => t("If set to '0', all date values on every entity will be cached. Note that caching every date on fields that may have a large number of multiple or repeating values may create a significant performance penalty when the cache is cleared. The suggested setting for multiple value and repeating fields is no more than 4 values per field."),
    '#size' => 3,
    '#weight' => 11,
    '#states' => array(
      'visible' => array(
        'input[name="field[settings][cache_enabled]"]' => array('checked' => TRUE),
      ),
    ),
  );

  $context = array(
    'field' => $field,
    'instance' => $instance,
    'has_data' => $has_data,
  );
  drupal_alter('date_field_settings_form', $form, $context);

  return $form;
}

/**
 * Form validation handler for _date_field_settings_form().
 */
function date_field_settings_validate(&$form, &$form_state) {
  $field = &$form_state['values']['field'];

  if ($field['settings']['tz_handling'] == 'none') {
    form_set_value($form['timezone_db'], '', $form_state);
  }
  else {
    form_set_value($form['timezone_db'], date_get_timezone_db($field['settings']['tz_handling']), $form_state);
  }

  if ($field['settings']['tz_handling'] != 'none' && !in_array('hour', array_filter($field['settings']['granularity']))) {
    form_set_error('field[settings][tz_handling]', t('Dates without hours granularity must not use any timezone handling.'));
  }

  // Extract the correct 'todate' value out of the two end date checkboxes.
  if ($field['settings']['enddate_get']) {
    if ($field['settings']['enddate_required']) {
      $field['settings']['todate'] = 'required';
    }
    else {
      $field['settings']['todate'] = 'optional';
    }
  }
  else {
    $field['settings']['todate'] = '';
  }

  // Don't save the pseudo values created in the UI.
  unset($field['settings']['enddate_get'], $field['settings']['enddate_required']);

  if (!empty($field['settings']['cache_enabled'])) {
    if (!is_numeric($field['settings']['cache_count'])) {
      form_set_error('field[settings][cache_count]', t('The number of cache values must be a number.'));
    }
    elseif ($field['settings']['cache_count'] < 0) {
      form_set_error('field[settings][cache_count]', t('The number of cache values must be a number 0 or greater.'));
    }
  }
}

/**
 * Timezone handling options.
 *
 * The 'none' option will do no timezone conversions and will store and display
 * dates exactly as entered useful in locales or situations where timezone
 * conversions are not working reliably, for dates with no times, for historical
 * dates where timezones are irrelevant, or anytime conversion is unnecessary or
 * undesirable.
 */
function date_timezone_handling_options() {
  return array(
    'site' => t("Site's time zone"),
    'date' => t("Date's time zone"),
    'user' => t("User's time zone"),
    'utc' => 'UTC',
    'none' => t('No time zone conversion'),
  );
}
