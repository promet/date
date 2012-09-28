<?php
/**
 * @file
 * Definition of Drupal\date\Plugin\field\widget\DateTextWidget.
 */

namespace Drupal\date\Plugin\field\widget;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Widget\WidgetBase;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Plugin\PluginSettingsBase;
use Drupal\field\FieldInstance;

/**
 * Plugin implementation of the 'date' widget.
 *
 * @Plugin(
 *   id = "date_text",
 *   module = "date",
 *   label = @Translation("Text input"),
 *   field_types = {
 *     "date", 
 *     "datestamp",
 *     "datetime"
 *   },
 *   settings = {
 *     "input_format" = "",
 *     "input_format_custom" = "",
 *     "increment" = 15,
 *     "text_parts" = "",
 *     "year_range" = "-3:+3",
 *     "label_position" = "above",
 *   }
 * )
 */
class DateTextWidget extends WidgetBase {

  /**
   * Constructs a DateWidget object.
   *
   * @param array $plugin_id
   *   The plugin_id for the widget.
   * @param Drupal\Component\Plugin\Discovery\DiscoveryInterface $discovery
   *   The Discovery class that holds access to the widget implementation
   *   definition.
   * @param Drupal\field\FieldInstance $instance
   *   The field instance to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param int $weight
   *   The widget weight.
   */
  public function __construct($plugin_id, DiscoveryInterface $discovery, FieldInstance $instance, array $settings, $weight) {
    // Identify the function used to set the default value.
    $instance['default_value_function'] = 'date_default_value';
    parent::__construct($plugin_id, $discovery, $instance, $settings, $weight);
  }

  /**
   * Implements Drupal\field\Plugin\Type\Widget\WidgetInterface::settingsForm().
   */
  public function settingsForm(array $form, array &$form_state) {
    $field = $this->field;
    $instance = $this->instance;
    $widget = $instance['widget'];
    $settings = $widget['settings'];

    if (empty($settings['input_format'])) {
      $settings['input_format'] = date_default_format('date_select');
    }
    
    $element = array(
      '#element_validate' => array('date_field_widget_settings_form_validate'),
    );
    
    $options = array();
    if ($widget['type'] == 'date_popup' && module_exists('date_popup')) {
      $formats = date_popup_formats();
    }
    else {
      // Example input formats must show all possible date parts, so add seconds.
      $formats = str_replace('i', 'i:s', array_keys(system_get_date_formats('short')));
      $formats = drupal_map_assoc($formats);
    }
    $now = date_example_date();
    foreach ($formats as $f) {
      $options[$f] = date_format_date($now, 'custom', $f);
    }
    $element['input_format'] = array(
      '#type' => 'select',
      '#title' => t('Date entry options'),
      '#default_value' => $settings['input_format'],
      '#options' => $options,
      '#description' => t('Control the order and format of the options users see.'),
      '#weight' => 3,
      '#fieldset' => 'date_format',
    );
    // Only a limited set of formats is available for the Date Popup module.
    if ($widget['type'] != 'date_popup') {
      $element['input_format']['#options']['custom'] = t('Custom format');
      $element['input_format_custom'] = array(
        '#type' => 'textfield',
        '#title' => t('Custom input format'),
        '#default_value' => $settings['input_format_custom'],
        '#description' => t("Override the input format selected above. Define a php date format string like 'm-d-Y H:i' (see <a href=\"@link\">http://php.net/date</a> for more details).", array('@link' => 'http://php.net/date')),
        '#weight' => 5,
        '#fieldset' => 'date_format',
        '#attributes' => array('class' => array('indent')),
        '#states' => array(
          'visible' => array(
            ':input[name="instance[widget][settings][input_format]"]' => array('value' => 'custom'),
          ),
        ),
      );
    }
    else {
      $element['input_format_custom'] = array(
        '#type' => 'hidden',
        '#value' => '',
      );
    }
    
    if (in_array($widget['type'], array('date_select', 'date_popup'))) {
      $element['year_range'] = array(
        '#type' => 'date_year_range',
        '#default_value' => $settings['year_range'],
        '#fieldset' => 'date_format',
        '#weight' => 6,
      );
      $element['increment'] = array(
        '#type' => 'select', '#title' => t('Time increments'),
        '#default_value' => $settings['increment'],
        '#options' => array(
          1 => t('1 minute'),
          5 => t('5 minute'),
          10 => t('10 minute'),
          15 => t('15 minute'),
          30 => t('30 minute')),
        '#weight' => 7,
        '#fieldset' => 'date_format',
      );
    }
    else {
      $element['year_range'] = array(
        '#type' => 'hidden',
        '#value' => $settings['year_range'],
      );
      $element['increment'] = array(
        '#type' => 'hidden',
        '#value' => $settings['increment'],
      );
    }
    
    $element['label_position'] = array(
      '#type' => 'value',
      '#value' => $settings['label_position'],
    );
    $element['text_parts'] = array(
      '#type' => 'value',
      '#value' => $settings['text_parts'],
    );
    $element['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => t('Advanced settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#fieldset' => 'date_format',
      '#weight' => 9,
    );
    if (in_array($widget['type'], array('date_select'))) {
      $options = array('above' => t('Above'), 'within' => t('Within'), 'none' => t('None'));
      $description = t("The location of date part labels, like 'Year', 'Month', or 'Day' . 'Above' displays the label as titles above each date part. 'Within' inserts the label as the first option in the select list and in blank textfields. 'None' doesn't label any of the date parts. Theme functions like 'date_part_label_year' and 'date_part_label_month' control label text.");
    }
    else {
      $options = array('above' => t('Above'), 'none' => t('None'));
      $description = t("The location of date part labels, like 'Year', 'Month', or 'Day' . 'Above' displays the label as titles above each date part. 'None' doesn't label any of the date parts. Theme functions like 'date_part_label_year' and 'date_part_label_month' control label text.");
    }
    $element['advanced']['label_position'] = array(
      '#type' => 'radios',
      '#options' => $options,
      '#default_value' => $settings['label_position'],
      '#title' => t('Position of date part labels'),
      '#description' => $description,
    );
    $element['advanced']['text_parts'] = array(
      '#theme' => $widget['type'] == 'date_select' ? 'date_text_parts' : '',
    );
    $text_parts = (array) $settings['text_parts'];
    foreach (date_granularity_names() as $key => $value) {
      if ($widget['type'] == 'date_select') {
        $element['advanced']['text_parts'][$key] = array(
          '#type' => 'radios',
          '#default_value' => in_array($key, $text_parts) ? 1 : 0,
          '#options' => array(0 => '', 1 => ''),
        );
      }
      else {
        $element['advanced']['text_parts'][$key] = array(
          '#type' => 'value',
          '#value' => (int) in_array($key, (array) $settings['text_parts']),
        );
      }
    }
    
    $context = array(
      'field' => $field,
      'instance' => $instance,
    );
    drupal_alter('date_field_widget_settings_form', $element, $context);
    
    return $element;
  }

  /**
   * Implements Drupal\field\Plugin\Type\Widget\WidgetInterface::formElement().
   *
   * The widget builds out a complex date element in the following way:
   *
   * - A field is pulled out of the database which is comprised of one or
   *   more collections of start/end dates.
   *
   * - The dates in this field are all converted from the UTC values stored
   *   in the database back to the local time. This is done in #process
   *   to avoid making this change to dates that are not being processed,
   *   like those hidden with #access.
   *
   * - If values are empty, the field settings rules are used to determine
   *   if the default_values should be empty, now, the same, or use strtotime.
   *
   * - Each start/end combination is created using the date_combo element type
   *   defined by the date module. If the timezone is date-specific, a
   *   timezone selector is added to the first combo element.
   *
   * - The date combo element creates two individual date elements, one each
   *   for the start and end field, using the appropriate individual Date API
   *   date elements, like selects, textfields, or popups.
   *
   * - In the individual element validation, the data supplied by the user is
   *   used to update the individual date values.
   *
   * - In the combo date validation, the timezone is updated, if necessary,
   *   then the user input date values are used with that timezone to create
   *   date objects, which are used update combo date timezone and offset values.
   *
   * - In the field's submission processing, the new date values, which are in
   *   the local timezone, are converted back to their UTC values and stored.
   *
   */
  public function formElement(array $items, $delta, array $element, $langcode, array &$form, array &$form_state) {

    $field = $this->field;
    $instance = $this->instance;
    $field_name = $field['field_name'];
    $entity_type = $instance['entity_type'];
  
    // If this is a new entity, populate the field with the right default values.
    // This happens early so even fields later hidden with #access get those values.
    // We should only add default values to new entities, to avoid over-writing
    // a value that has already been set. This means we can't just check to see
    // if $items is empty, because it might have been set that way on purpose.
    // @see date_field_widget_properties_alter() where we flagged if this is a new entity.
  
    // We check !isset($items[$delta]['value']) because entity translation may create
    // a new translation entity for an existing entity and we don't want to clobber
    // values that were already set in that case.
    // @see http://drupal.org/node/1478848.
  
    $is_default = TRUE;
    $info = entity_get_info($entity_type);
    $id = $info['entity keys']['id'];
    if (!empty($form->$id) && !empty($form->$id['#value'])) {
      $is_default = FALSE;
    }
  
    // @TODO Repeating dates should probably be made into their own field type and completely separated out.
    // That will have to wait for a new branch since it may break other things, including other modules
    // that have an expectation of what the date field types are.
  
    // Since repeating dates cannot use the default Add more button, we have to handle our own behaviors here.
    // Return only the first multiple value for repeating dates, then clean up the 'Add more' bits in #after_build.
    // The repeating values will be re-generated when the repeat widget form is validated.
    // At this point we can't tell if this form element is going to be hidden by #access, and we're going to
    // lose all but the first value by doing this, so store the original values in case we need to replace them later.
    if (!empty($field['settings']['repeat'])) {
      if ($delta == 0) {
        $form['#after_build'] = array('date_repeat_after_build');
        $form_state['storage']['repeat_fields'][$field_name] = array_merge($form['#parents'], array($field_name));
        $form_state['storage']['date_items'][$field_name][$langcode] = $items;
      }
      else {
        return;
      }
    }
  
    module_load_include('inc', 'date_api', 'date_api_elements');
    $timezone = date_get_timezone($field['settings']['tz_handling'], isset($items[0]['timezone']) ? $items[0]['timezone'] : date_default_timezone());
  
    // TODO see if there's a way to keep the timezone element from ever being
    // nested as array('timezone' => 'timezone' => value)). After struggling
    // with this a while, I can find no way to get it displayed in the form
    // correctly and get it to use the timezone element without ending up
    // with nesting.
    if (is_array($timezone)) {
      $timezone = $timezone['timezone'];
    }
  
    $element += array(
      '#type' => 'date_combo',
      '#theme_wrappers' => array('date_combo'),
      '#weight' => $delta,
      '#default_value' => isset($items[$delta]) ? $items[$delta] : '',
      '#date_timezone' => $timezone,
      '#element_validate' => array('date_combo_validate'),
      '#date_is_default' => $is_default,
  
      // Store the original values, for use with disabled and hidden fields.
      '#date_items' => isset($items[$delta]) ? $items[$delta] : '',
    );
  
    $element['#title'] = $instance['label'];
  
    if ($field['settings']['tz_handling'] == 'date') {
      $element['timezone'] = array(
        '#type' => 'date_timezone',
        '#theme_wrappers' => array('date_timezone'),
        '#delta' => $delta,
        '#default_value' => $timezone,
        '#weight' => $instance['widget']['weight'] + 1,
        '#attributes' => array('class' => array('date-no-float')),
        '#date_label_position' => $instance['widget']['settings']['label_position'],
        );
    }

    return $element;
  }

  /**
   * Implements Drupal\field\Plugin\Type\Widget\WidgetInterface::errorElement().
   */
  //public function errorElement(array $element, array $error, array $form, array &$form_state) {
  //  return $element['value'];
  //}

}
