<?php

/**
 * @file
 * Definition of DateObject.
 */
namespace Drupal\date_api;

use DateTime;
use DateTimezone;
use Exception;

/**
 * This class is an extension of the PHP DateTime class.
 *
 * It extends the PHP DateTime class with more flexible initialization
 * parameters, allowing a date to be created from an existing date object,
 * a timestamp, a string with an unknown format, a string with a known
 * format, or an array of date parts. It also adds an errors array
 * and a __toString() method to the date object. 
 * 
 * In addition, it swaps the IntlDateFormatter into the format() method,
 * if it is available. The format() method is also extended with a new
 * parameter to identify if the format should use the IntlDateFormatter
 * and a settings array to provide settings needed by the formatter.
 * The IntlDateFormatter will only be used if the function is available,
 * a locale and calendar have been set, and $use_intl is TRUE, otherwise
 * the parent format() method is used to create the formatted result in
 * the usual way. The locale and calendar can either be set globally in
 * the object and reused over and over as the date is repeatedly formatted,
 * or set specifically in the format() method for the requested format.
 *
 * This class is less lenient than the parent DateTime class. It changes
 * the default behavior for handling date values like '2011-00-00'.
 * The parent class would convert that value to '2010-11-30' and report
 * a warning but not an error. This extension treats that as an error.
 *
 * As with the base class, a date object may be created even if it has
 * errors. It has an errors array attached to it that explains what the
 * errors are. This is less disruptive than allowing datetime exceptions
 * to abort processing. The calling script can decide what to do about
 * errors by checking the hasErrors() method and using the messages in
 * the errors array.
 *
 * Translation of error messages is not handled in this class. Error
 * messages are all in English.
 *
 */
class DateObject extends DateTime {

  /**
   * Make sure Intl constants are available in case the
   * IntlDateFormatter is not available and has not defined them.
   */
  const GREGORIAN = 1;

  /**
   * An array of possible date parts.
   */
  public static $date_parts = array(
                               'year',
                               'month',
                               'day',
                               'hour',
                               'minute',
                               'second',
                              );

  /**
   * The value of the input_time_adjusted value passed to the constructor.
   */
  public $input_time_raw = '';

  /**
   * The prepared input_time_adjusted, without input_timezone_adjusted, for this date.
   */
  public $input_time_adjusted = '';

  /**
   * The value of the input_timezone_adjusted passed to the constructor.
   */
  public $input_timezone_raw = '';

  /**
   * The prepared input_timezone_adjusted object used to construct this date.
   */
  public $input_timezone_adjusted = '';

  /**
   * The value of the format passed to the constructor.
   */
  public $input_format_raw = '';

  /**
   * The prepared format, if provided.
   */
  public $input_format_adjusted = '';

  /**
   * The default dateinput_time_adjusted format, used in __toString() and elsewhere.
   */
  public $default_format = 'Y-m-d H:i:s';

  /**
   * The value of the locale setting passed to the constructor.
   */
  public $locale = NULL;

  /**
   * The value of the calendar setting passed to the constructor.
   */
  public $calendar = NULL;

  /**
   * An array of errors encountered when creating this date.
   */
  public $errors = array();

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
   *
   * @TODO
   *     Potentially there will be additional ways to take advantage
   *     of locale and calendar in date handling in the future.
   */
  public function __construct($time = 'now', $timezone = NULL, $format = NULL, $settings = array()) {

    // Unpack settings.
    $this->validate_format = !empty($settings['validate_format']) ? $settings['validate_format'] : TRUE;
    $this->locale = !empty($settings['locale']) ? $settings['locale'] : NULL;
    $this->calendar = !empty($settings['calendar']) ? $settings['calendar'] : self::GREGORIAN;

    // Store the original input so it is available for validation.
    $this->input_time_raw = $time;
    $this->input_timezone_raw = $timezone;
    $this->input_format_raw = $format;

    // Massage the input values as necessary.
    $this->prepareTime($time);
    $this->prepareTimezone($timezone);
    $this->prepareFormat($format);

    // Create a date from an input DateTime object.
    if ($this->inputIsObject()) {
      $this->constructFromObject();
    }

    // Create date from array of date parts.
    elseif ($this->inputIsArray()) {
      $this->constructFromArray();
    }

    // Create a date from a Unix input_time_adjustedstamp.
    elseif ($this->inputIsTimestamp()) {
      $this->constructFromTimestamp();
    }

    // Create a date from a input_time_adjusted string and an expected format.
    elseif ($this->inputIsFormat()) {
      $this->constructFromFormat();
    }

    // Create a date from any other input.
    else {
      $this->constructFallback();
    }

    // Clean up the error messages.
    $this->getErrors();
    $this->errors = array_unique($this->errors);
    return FALSE;
  }

  /**
   * Implementation of __toString() for dates. The base DateTime
   * class does not implement this.
   *
   * @see https://bugs.php.net/bug.php?id=62911 and
   * http://www.serverphorums.com/read.php?7,555645
   */
  public function __toString() {
    return $this->format(self::$default_format) . ' ' . $this->getTimeZone()->getName();
  }

  /**
   * Prepare the input value before trying to use it.
   * Can be overridden to handle special cases.
   *
   * @param mixed $input_time_adjusted
   *   An input value, which could be a input_time_adjustedstamp, a string,
   *   or an array of date parts.
   */
  public function prepareTime($time) {
    $this->input_time_adjusted = $time;
  }

  /**
   * Prepare the input_timezone_adjusted before trying to use it.
   * Most imporantly, make sure we have a valid input_timezone_adjusted
   * object before moving further.
   *
   * @param mixed $tz_input
   *   Either a input_timezone_adjusted name or a input_timezone_adjusted object or NULL.
   */
  public function prepareTimezone($timezone) {

    // If the passed in input_timezone_adjusted is a valid input_timezone_adjusted object, use it.
    if ($timezone instanceOf DateTimezone) {
      $timezone_adjusted = $timezone;
    }

    // When the passed-in input_time_adjusted is a DateTime object with its own
    // input_timezone_adjusted, try to use the date's input_timezone_adjusted.
    elseif (empty($timezone) && $this->input_time_adjusted instanceOf DateTime) {
      $timezone_adjusted = $this->input_time_adjusted->getTimezone();
    }

    // Allow string input_timezone_adjusted input, and create a input_timezone_adjusted from it.
    elseif (!empty($timezone) && is_string($timezone)) {
      $timezone_adjusted = new DateTimeZone($timezone);
    }

    // Default to the system input_timezone_adjusted when not explicitly provided.
    // If the system input_timezone_adjusted is missing, use 'UTC'.
    if (empty($timezone_adjusted) || !$timezone_adjusted instanceOf DateTimezone) {
      $system_timezone = date_default_timezone_get();
      $timezone_name = !empty($system_timezone) ? $system_timezone: 'UTC';
      $timezone_adjusted = new DateTimeZone($timezone_name);
    }

    // We are finally certain that we have a usable input_timezone_adjusted.
    $this->input_timezone_adjusted = $timezone_adjusted;
  }

  /**
   * Prepare the input format before trying to use it.
   * Can be overridden to handle special cases.
   *
   * @param string $format
   *   A PHP format string.
   */
  public function prepareFormat($format) {
    $this->input_format_adjusted = $format;
  }

  /**
   * Check if input is a DateTime object.
   *
   * @return boolean
   *   TRUE if the input input_time_adjusted is a DateTime object.
   */
  public function inputIsObject() {
    return $this->input_time_adjusted instanceOf DateTime;
  }

  /**
   * Create a date object from an input date object.
   */
  public function constructFromObject() {
    try {
      $this->input_time_adjusted = $this->input_time_adjusted->format(self::$default_format);
      parent::__construct($this->input_time_adjusted, $this->input_timezone_adjusted);
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  /**
   * Check if input input_time_adjusted seems to be a input_time_adjustedstamp.
   *
   * Providing an input format will prevent ISO values without separators
   * from being mis-interpreted as input_time_adjustedstamps. Providing a format can also
   * avoid interpreting a value like '2010' with a format of 'Y' as a
   * input_time_adjustedstamp. The 'U' format indicates this is a input_time_adjustedstamp.
   *
   * @return boolean
   *   TRUE if the input input_time_adjusted is a input_time_adjustedstamp.
   */
  public function inputIsTimestamp() {
    return is_numeric($this->input_time_adjusted) && (empty($this->input_format_adjusted) || $this->input_format_adjusted == 'U');
  }

  /**
   * Create a date object from input_time_adjustedstamp input.
   *
   * The input_timezone_adjusted for input_time_adjustedstamps is always UTC. In this case the
   * input_timezone_adjusted we set controls the input_timezone_adjusted used when displaying
   * the value using format().
   */
  public function constructFromTimestamp() {
    try {
      parent::__construct('', $this->input_timezone_adjusted);
      $this->setTimestamp($this->input_time_adjusted);
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  /**
   * Check if input is an array of date parts.
   *
   * @return boolean
   *   TRUE if the input input_time_adjusted is a DateTime object.
   */
  public function inputIsArray() {
    return is_array($this->input_time_adjusted);
  }

  /**
   * Create a date object from an array of date parts.
   *
   * Convert the input value into an ISO date, forcing a full ISO
   * date even if some values are missing.
   */
  public function constructFromArray() {
    try {
      parent::__construct('', $this->input_timezone_adjusted);
      $this->input_time_adjusted = self::prepareArray($this->input_time_adjusted, TRUE);
      if (self::checkArray($this->input_time_adjusted)) {
        // Even with validation, we can end up with a value that the
        // parent class won't handle, like a year outside the range
        // of -9999 to 9999, which will pass checkdate() but
        // fail to construct a date object.
        $this->input_time_adjusted = self::arrayToISO($this->input_time_adjusted);
        parent::__construct($this->input_time_adjusted, $this->input_timezone_adjusted);
      }
      else {
        throw new Exception('The array contains invalid values.');
      }
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  /**
   * Check if input is a string with an expected format.
   *
   * @return boolean
   *   TRUE if the input input_time_adjusted is a string with an expected format.
   */
  public function inputIsFormat() {
    return is_string($this->input_time_adjusted) && !empty($this->input_format_adjusted);
  }

  /**
   * Create a date object from an input format.
   *
   */
  public function constructFromFormat() {
    // Try to create a date from the format and use it if possible.
    // A regular try/catch won't work right here, if the value is
    // invalid it doesn't return an exception.
    try {
      parent::__construct('', $this->input_timezone_adjusted);
      $date = parent::createFromFormat($this->input_format_adjusted, $this->input_time_adjusted, $this->input_timezone_adjusted);
      if (!$date instanceOf DateTime) {
        throw new Exception('The date cannot be created from a format.');
      }
      else {
        $this->setTimestamp($date->getTimestamp());
        $this->setTimezone($date->getTimezone());

        try {
          // The createFromFormat function is forgiving, it might
          // create a date that is not exactly a match for the provided
          // value, so test for that. For instance, an input value of
          // '11' using a format of Y (4 digits) gets created as
          // '0011' instead of '2011'.
          // Use the parent::format() because we do not want to use
          // the IntlDateFormatter here.
          if ($this->validate_format && parent::format($this->input_format_adjusted) != $this->input_time_raw) {
            throw new Exception('The created date does not match the input value.');
          }
        }
        catch (Exception $e) {
          $this->errors[] = $e->getMessage();
        }
      }
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  /**
   * Fallback construction for values that don't match any of the
   * other patterns.
   *
   * Let the parent dateTime attempt to turn this string into a
   * valid date.
   */
  public function constructFallback() {
    try {
      @parent::__construct($this->input_time_adjusted, $this->input_timezone_adjusted);
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  /**
   * Examine getLastErrors() and see what errors to report.
   *
   * We're interested in two kinds of errors: anything that DateTime
   * considers an error, and also a warning that the date was invalid.
   * PHP creates a valid date from invalid data with only a warning,
   * 2011-02-30 becomes 2011-03-03, for instance, but we don't want that.
   *
   * @see http://us3.php.net/manual/en/input_time_adjusted.getlasterrors.php
   */
  public function getErrors() {
    $errors = $this->getLastErrors();
    if (!empty($errors['errors'])) {
      $this->errors += $errors['errors'];
    }
    if (!empty($errors['warnings']) && in_array('The parsed date was invalid', $errors['warnings'])) {
      $this->errors[] = 'The date is invalid.';
    }
  }

  /**
   * Detect if there were errors in the processing of this date.
   */
  function hasErrors() {
    if (count($this->errors)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Creates an ISO date from an array of values.
   *
   * @param array $array
   *   An array of date values keyed by date part.
   * @param bool $force_valid_date
   *   (optional) Whether to force a full date by filling in missing
   *   values. Defaults to FALSE.
   *
   * @return string
   *   The date as an ISO string.
   */
  public static function arrayToISO($array, $force_valid_date = FALSE) {
    $array = self::prepareArray($array, $force_valid_date);
    $input_time_adjusted = '';
    if ($array['year'] !== '') {
      $input_time_adjusted = self::datePad(intval($array['year']), 4);
      if ($force_valid_date || $array['month'] !== '') {
        $input_time_adjusted .= '-' . self::datePad(intval($array['month']));
        if ($force_valid_date || $array['day'] !== '') {
          $input_time_adjusted .= '-' . self::datePad(intval($array['day']));
        }
      }
    }
    if ($array['hour'] !== '') {
      $input_time_adjusted .= $input_time_adjusted ? 'T' : '';
      $input_time_adjusted .= self::datePad(intval($array['hour']));
      if ($force_valid_date || $array['minute'] !== '') {
        $input_time_adjusted .= ':' . self::datePad(intval($array['minute']));
        if ($force_valid_date || $array['second'] !== '') {
          $input_time_adjusted .= ':' . self::datePad(intval($array['second']));
        }
      }
    }
    return $input_time_adjusted;
  }

  /**
   * Creates a complete array from a possibly incomplete array of date parts.
   *
   * @param array $array
   *   An array of date values keyed by date part.
   * @param bool $force_valid_date
   *   (optional) Whether to force a valid date by filling in missing
   *   values with valid values or just to use empty values instead.
   *   Defaults to FALSE.
   *
   * @return array
   *   A complete array of date parts.
   */
  public static function prepareArray($array, $force_valid_date = FALSE) {
    if ($force_valid_date) {
      $array += array(
                 'year'   => 0,
                 'month'  => 1,
                 'day'    => 1,
                 'hour'   => 0,
                 'minute' => 0,
                 'second' => 0,
                );
    }
    else {
      $array += array(
                 'year'   => '',
                 'month'  => '',
                 'day'    => '',
                 'hour'   => '',
                 'minute' => '',
                 'second' => '',
                );
    }
    return $array;
  }

  /**
   * Check that an array of date parts has a year, month, and day,
   * and that those values create a valid date. If input_time_adjusted is provided,
   * verify that the input_time_adjusted values are valid. Sort of an
   * equivalent to checkdate().
   *
   * @param array $array
   *   An array of datetime values keyed by date part.
   *
   * @return boolean
   *   TRUE if the datetime parts contain valid values, otherwise FALSE.
   */
  public static function checkArray($array) {
    $valid_date = FALSE;
    $valid_input_time_adjusted = TRUE;
    // Check for a valid date using checkdate(). Only values that
    // meet that test are valid.
    if (array_key_exists('year', $array) && array_key_exists('month', $array) && array_key_exists('day', $array)) {
      if (@checkdate($array['month'], $array['day'], $array['year'])) {
        $valid_date = TRUE;
      }
    }
    // Testing for valid input_time_adjusted is reversed. Missing time is OK,
    // but incorrect values are not.
    foreach (array('hour', 'minute', 'second') as $key) {
      if (array_key_exists($key, $array)) {
        $value = $array[$key];
        switch ($value) {
          case 'hour':
            if (!preg_match('/^([1-2][0-3]|[01]?[0-9])$/', $value)) {
              $valid_input_time_adjusted = FALSE;
            }
            break;
          case 'minute':
          case 'second':
          default:
            if (!preg_match('/^([0-5][0-9]|[0-9])$/', $value)) {
              $valid_input_time_adjusted = FALSE;
            }
            break;
        }
      }
    }
    return $valid_date && $valid_input_time_adjusted;
  }

  /**
   * Helper function to left pad date parts with zeros.
   *
   * Provided because this is needed so often with dates.
   *
   * @param int $value
   *   The value to pad.
   * @param int $size
   *   (optional) Size expected, usually 2 or 4. Defaults to 2.
   *
   * @return string
   *   The padded value.
   */
  public static function datePad($value, $size = 2) {
    return sprintf("%0" . $size . "d", $value);
  }


  /**
   * Test if the IntlDateFormatter is available and we have the
   * right information to be able to use it.
   */
  function canUseIntl() {
    return class_exists('IntlDateFormatter') && !empty($this->calendar) && !empty($this->locale);
  }

  /**
   * Use the IntlDateFormatter to display the format, if possible.
   * Because the IntlDateFormatter is not always available, we
   * add an optional array of settings that provides the information
   * the IntlDateFormatter will need.
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
   *   - boolean $lenient
   *     Whether or not to use lenient processing. Defaults
   *     to FALSE;
   *
   * @return string
   *   The formatted value of the date.
   */
  function format($format, $settings = array()) {

     $is_intl  = isset($settings['is_intl'])   ? $settings['is_intl']  : FALSE;
     $locale   = !empty($settings['locale'])   ? $settings['locale']   : $this->locale;
     $calendar = !empty($settings['calendar']) ? $settings['calendar'] : $this->calendar;
     $timezone = !empty($settings['timezone']) ? $settings['timezone'] : $this->getTimezone()->getName();
     $lenient  = !empty($settings['lenient'])  ? $settings['lenient']  : FALSE;

     // Format the date and catch errors.
     try {

      // If we have what we need to use the IntlDateFormatter, do so.

      if ($this->canUseIntl() && $is_intl) {
        $datetype = !empty($settings['datetype']) ? $settings['datetype'] : IntlDateFormatter::FULL;
        $timetype = !empty($settings['timetype']) ? $settings['timetype'] : IntlDateFormatter::FULL;
        $formatter = new IntlDateFormatter($locale, $datetype, $timetype, $timezone, $calendar);
        $formatter->setLenient($lenient);
        $value = $formatter->format($format);
      }

      // Otherwise, use the parent method.

      else {
        $value = parent::format($format);
      }
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
    return $value;
  }
}
