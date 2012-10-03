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
   * @param mixed $itime
   *   A DateTime object, a date/input_time_adjusted string, a unix input_time_adjustedstamp,
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
   *   @see http://us3.php.net/manual/en/dateinput_time_adjusted.createfromformat.php
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
   *     A locale name, using the pattern specified by the
   *     intlDateFormatter class. Used to control the result of the
   *     format() method if that class is available.
   *   - int $calendar
   *     A calendar method, using the value specified by the
   *     intlDateFormatter class. Used to control the result of the
   *     format() method if that class is available. Defaults to
   *     GREGORIAN.
   *   - string $langcode
   *     The Drupal langcode to use when formatting the output of this
   *     date. If NULL, Defaults to the language used to display the page.
   *     Used in the format_date() function.
   *
   * @TODO
   *     Potentially there will be additional ways to take advantage
   *     of locale and calendar in date handling in the future.
   */
  public function __construct($time = 'now', $timezone = NULL, $format = NULL, $settings = array()) {

    // We can set the langcode and locale using Drupal values.
    $this->langcode = !empty($settings['langcode']) ? $settings['langcode'] : language(LANGUAGE_TYPE_INTERFACE)->langcode;
    if (empty($settings['locale'])) {
      $settings['locale'] = $this->langcode . '-' . variable_get('site_default_country');
    }

    // If the calendar is not set and the IntlDateFormatter is available,
    // set the calendar to gregorian.
    if (empty($settings['calendar']) && class_exists('IntlDateFormatter')) {
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
   * Use the IntlDateFormatter to display the format, if available.
   * Because the IntlDateFormatter is not always available, we
   * need to know whether the $format string uses the standard
   * format strings used by the date() function or the alternative
   * format provided by the IntlDateFormatter.
   *
   * @param string $format
   *   A format string using either date() or IntlDateFormatter()
   *   format.
   * @params array $settings
   *   - string $is_intl
   *     Whether or not to use the IntlDateFormatter, if available.
   *     defaults to FALSE, can be changed to test the alternative
   *     processing methods. When using the Intl formatter, the
   *     format string must use the Intl pattern, which is different
   *     from the pattern used by the DateTime format function.
   *   - string $locale
   *     A locale name, using the format specified by the
   *     intlDateFormatter class. Used to control the result of the
   *     format() method if that class is available.
   *     Defaults to the locale set by the constructor.
   *   - int $calendar
   *     A calendar type, using the name specified by the
   *     intlDateFormatter class. Used to control the result of the
   *     format() method if that class is available.
   *     Defaults to the calendar type set by the constructor.
   *   - string $timezone
   *     A timezone name. Defaults to the timezone of the date object.
   *   - int $datetype
   *     The datetype to use in the formatter, defaults to
   *     IntlDateFormatter::FULL.
   *   - int $timetype
   *     The datetype to use in the formatter, defaults to
   *     IntlDateFormatter::FULL.
   *   - string $langcode
   *     The Drupal langcode to use when formatting the output of this
   *     date. If NULL, Defaults to the language used to display the page.
   *     Used in the format_date() function.
   *   - boolean $lenient
   *     Whether or not to use lenient processing. Defaults
   *     to FALSE;
   *
   * @return string
   *   The formatted value of the date.
   */
  function format($format, $settings = array()) {

     $is_intl  = isset($settings['is_intl'])   ? $settings['is_intl']  : FALSE;
     $langcode = !empty($settings['langcode']) ? $settings['langcode'] : $this->langcode;

     // Format the date and catch errors.
     try {

      // If we have what we need to use the IntlDateFormatter, do so.

      if ($this->canUseIntl() && $is_intl) {
        $value = parent::format($format, $is_intl, $settings);
      }

      // Otherwise, use the default Drupal method.

      else {

        // Encode markers that should be translated. 'A' becomes '\xEF\AA\xFF'.
        // xEF and xFF are invalid UTF-8 sequences, and we assume they are not in the
        // input string.
        // Paired backslashes are isolated to prevent errors in read-ahead evaluation.
        // The read-ahead expression ensures that A matches, but not \A.
        $format = preg_replace(array('/\\\\\\\\/', '/(?<!\\\\)([AaeDlMTF])/'), array("\xEF\\\\\\\\\xFF", "\xEF\\\\\$1\$1\xFF"), $format);
      
        // Call date_format().
        $format = parent::format($format);
      
        // Pass the langcode to _format_date_callback().
        _format_date_callback(NULL, $langcode);
      
        // Translate the marked sequences.
        $value = preg_replace_callback('/\xEF([AaeDlMTF]?)(.*?)\xFF/', '_format_date_callback', $format);
      }
    }
    catch (Exception $e) {
      $this->errors[] = t($e->getMessage());
    }
    return $value;
  }
}
