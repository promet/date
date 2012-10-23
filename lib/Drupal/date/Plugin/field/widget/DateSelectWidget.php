<?php
/**
 * @file
 * Definition of Drupal\date\Plugin\field\widget\DateSelectWidget.
 */

namespace Drupal\date\Plugin\field\widget;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Widget\WidgetBase;
use Drupal\date\Plugin\field\widget\DateTextWidget;

/**
 * Plugin implementation of the 'date' widget.
 *
 * @Plugin(
 *   id = "date_select",
 *   module = "date",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "date", 
 *     "datestamp",
 *   },
 *   settings = {
 *     "date_date_format" = "Y-m-d",
 *     "date_date_element" = "date",
 *     "input_format_custom" = "",
 *     "increment" = 15,
 *     "text_parts" = {},
 *     "year_range" = "-3:+3",
 *   }
 * )
 */
class DateSelectWidget extends DateWidgetBase {

  function settingsForm(array $form, array &$form_state) {
    $element = parent::settingsForm(array $form, array &$form_state);

    $element['text_parts'] = array(
      '#type' => 'value',
      '#value' => $settings['text_parts'],
    );

    $element['advanced']['text_parts'] = array('#theme' => 'date_text_parts');
    $text_parts = (array) $settings['text_parts'];
    foreach (DateGranularity::granularityNames() as $key => $value) {
      $element['advanced']['text_parts'][$key] = array(
        '#type' => 'radios',
        '#default_value' => in_array($key, $text_parts) ? 1 : 0,
        '#options' => array(0 => '', 1 => ''),
      ); 
    }

    return $element;
  }
}
