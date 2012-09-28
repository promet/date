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
class DateSelectWidget extends DateTextWidget {


}
