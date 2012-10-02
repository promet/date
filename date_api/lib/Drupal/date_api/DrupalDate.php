<?php

/**
 * @file
 * Definition of DrupalDate.
 */
namespace Drupal\date_api;

use Drupal\date_api\DateObject;
use DateTime;
use DateTimezone;
use Exception;

/**
 * This class is an extension of the Drupal DateObject class.
 *
 * This class extends the basic component and adds in Drupal-specific
 * handling, like translation of the format() method and error
 * messages.
 *
 * @see Drupal/date_api/DateObject.php
 */
class DrupalDate extends DateObject {

  /**
   * The language code to use when formatting this date.
   */
  public $langcode;

  /**
   * Constructs a date object.
   *
   * @param mixed $time
   *   A DateTime object, a date/time string, a unix timestamp,
   *   or an array of date parts, like ('year' => 2014, 'month => 4).
   *   Defaults to 'now'.
   * @param mixed $timezone
   *   PHP DateTimeZone object, string or NULL allowed.
   *   Defaults to NULL.
   * @param string $format
   *   PHP date() type format for parsing. $format is recommended in order
   *   to use things like negative years, which php's parser fails on, or
   *   any other specialized input with a known format. If provided the
   *   date will be created using the createFromFormat() method.
   *   @see http://us3.php.net/manual/en/datetime.createfromformat.php
   *   Defaults to NULL.
   * @params array $settings
   *   - boolean $validate_format
   *     The format used in createFromFormat() allows slightly different
   *     values than format(). If we use an input format that works in
   *     both functions we can add a validation step to confirm that the
   *     date created from a format string exactly matches the input.
   *     We need to know if this can be relied on to do that validation.
   *     Defaults to TRUE.
   *   - string $locale
   *     A locale name, using the format specified by the
   *     intlDateFormatter class. Used to control the result of the
   *     format() method if that class is available.
   *   - string $calendar
   *     A locale name, using the format specified by the
   *     intlDateFormatter class. Used to control the result of the
   *     format() method if that class is available.
   *   - string $langcode
   *     The Drupal langcode to use when formatting the output of this
   *     date. If NULL, Defaults to the language used to display the page.
   *     Used in the format_date() function.
   */
  public function __construct($time = 'now', $timezone = NULL, $format = NULL, $settings = array()) {

    // We can set the langcode and locale using Drupal values.
    $this->langcode = !empty($settings['langcode']) ? $settings['langcode'] : language(LANGUAGE_TYPE_INTERFACE)->langcode;
    if (empty($settings['locale'])) {
      $settings['locale'] = $this->langcode . '-' . variable_get('site_default_country');
    }

    // If the calendar is not set and the IntlDateFormatter is available,
    // set the calendar to gregorian.
    if (empty($settings['calendar']) && class_exists(IntlDateFormatter)) {
      $settings['calendar'] = IntlDateFormatter::GREGORIAN;
    }

    // Instantiate the parent class.
    parent::__construct($time, $timezone, $format, $settings);

    // Attempt to translate the error messages.
    foreach ($this->errors as &$error) {
      $error = t($error);
    }
  }

  /**
   * Override format display to include translation of the
   * formatted dates. Use the IntlDateFormatter if available,
   * otherwise use Drupal's format_date() function.
   */
  public function format($format, $type = 'custom') {

    // The parent class will use the IntlDateFormatter, if available.
    if ($this->canUseIntl()) {
      return parent::format($format);
    }
    // If that is not available, use format_date().
    else {
      if ($type != 'custom') {
        $format = variable_get('date_format_' . $type);
      }
      $timestamp = parent::format('U');
      return format_date($timestamp, $type, $format, $this->timezone_name, $this->langcode);
    }
  }
}
