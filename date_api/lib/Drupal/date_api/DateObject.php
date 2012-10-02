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
 * format, or an array of date parts. It also adds an errors array and
 * a __toString() method to the date object. Lastly, the class attempts
 * to use the IntlDateFormatter in the format() method, if it is
 * available and locale and calendar information were provided to the
 * constructor.
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
 * Translation of error messages is not handled in this class, they are
 * all in English.
 *
 */
class DateObject extends DateTime {

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
   * The value of the time value passed to the constructor.
   */
  public $time_original = '';

  /**
   * The prepared time, without timezone, for this date.
   */
  public $time = '';

  /**
   * The value of the timezone passed to the constructor.
   */
  public $timezone_original = '';

  /**
   * The prepared timezone object for this date.
   */
  public $timezone = '';

  /**
   * The timzone name used by this class.
   */
  public $timezone_name = '';

  /**
   * The value of the format passed to the constructor.
   */
  public $format_original = '';

  /**
   * The prepared format, if provided.
   */
  public $format = '';

  /**
   * The default datetime format, used in __toString() and elsewhere.
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
   *   - boolean $use_international
   *     Whether or not to use the IntlDateFormatter, if available.
   *     defaults to TRUE, can be set to FALSE to test the alternative
   *     processing.
   */
  public function __construct($time = 'now', $timezone = NULL, $format = NULL, $settings = array()) {

    // Unpack settings.
    $this->validate_format = !empty($settings['validate_format']) ? $settings['validate_format'] : TRUE;
    $this->locale = !empty($settings['locale']) ? $settings['locale'] : NULL;
    $this->calendar = !empty($settings['calendar']) ? $settings['calendar'] : NULL;
    $this->use_international = isset($settings['use_international']) ? $settings['use_international'] : TRUE;

    // Store the original input so it is available for validation.
    $this->time_original = $time;
    $this->timezone_original = $timezone;
    $this->format_original = $format;

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

    // Create a date from a Unix timestamp.
    elseif ($this->inputIsTimestamp()) {
      $this->constructFromTimestamp();
    }

    // Create a date from a time string and an expected format.
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
   * Prepare the input value before trying to use it.
   * Can be overridden to handle special cases.
   *
   * @param mixed $time
   *   An input value, which could be a timestamp, a string,
   *   or an array of date parts.
   */
  public function prepareTime($time) {
    $this->time = $time;
  }

  /**
   * Prepare the timezone before trying to use it.
   * Most imporantly, make sure we have a valid timezone
   * object before moving further.
   *
   * @param mixed $tz_input
   *   Either a timezone name or a timezone object or NULL.
   */
  public function prepareTimezone($tz_input) {

    // If the passed in timezone is a valid timezone object, use it.
    if ($tz_input instanceOf DateTimezone) {
      $timezone = $tz_input;
    }

    // When the passed-in time is a DateTime object with its own
    // timezone, try to use the date's timezone.
    elseif (empty($tz_input) && $this->time instanceOf DateTime) {
      $timezone = $this->time->getTimezone();
    }

    // Allow string timezone input, and create a timezone from it.
    elseif (!empty($tz_input) && is_string($tz_input)) {
      $timezone = new DateTimeZone($tz_input);
    }

    // Default to the system timezone when not explicitly provided.
    // If the system timezone is missing, use 'UTC'.
    if (empty($timezone) || !$timezone instanceOf DateTimezone) {
      $system_timezone = date_default_timezone_get();
      $timezone_name = !empty($system_timezone) ? $system_timezone: 'UTC';
      $timezone = new DateTimeZone($timezone_name);
    }

    // We are finally certain that we have a usable timezone.
    $this->timezone_name = $timezone->getName();
    $this->timezone = $timezone;
  }

  /**
   * Prepare the input format before trying to use it.
   * Can be overridden to handle special cases.
   *
   * @param string $format
   *   A PHP format string.
   */
  public function prepareFormat($format) {
    $this->format = $format;
  }

  /**
   * Check if input is a DateTime object.
   *
   * @return boolean
   *   TRUE if the input time is a DateTime object.
   */
  public function inputIsObject() {
    return $this->time instanceOf DateTime;
  }

  /**
   * Create a date object from an input date object.
   */
  public function constructFromObject() {
    try {
      $this->time = $this->time->format(self::$default_format);
      parent::__construct($this->time, $this->timezone);
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  /**
   * Check if input time seems to be a timestamp.
   *
   * Providing an input format will prevent ISO values without separators
   * from being mis-interpreted as timestamps. Providing a format can also
   * avoid interpreting a value like '2010' with a format of 'Y' as a
   * timestamp. The 'U' format indicates this is a timestamp.
   *
   * @return boolean
   *   TRUE if the input time is a timestamp.
   */
  public function inputIsTimestamp() {
    return is_numeric($this->time) && (empty($this->format) || $this->format == 'U');
  }

  /**
   * Create a date object from timestamp input.
   *
   * The timezone for timestamps is always UTC. In this case the
   * timezone we set controls the timezone used when displaying
   * the value using format().
   */
  public function constructFromTimestamp() {
    try {
      parent::__construct('', $this->timezone);
      $this->setTimestamp($this->time);
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  /**
   * Check if input is an array of date parts.
   *
   * @return boolean
   *   TRUE if the input time is a DateTime object.
   */
  public function inputIsArray() {
    return is_array($this->time);
  }

  /**
   * Create a date object from an array of date parts.
   *
   * Convert the input value into an ISO date, forcing a full ISO
   * date even if some values are missing.
   */
  public function constructFromArray() {
    try {
      parent::__construct('', $this->timezone);
      $this->time = self::prepareArray($this->time, TRUE);
      if (self::checkArray($this->time)) {
        // Even with validation, we can end up with a value that the
        // parent class won't handle, like a year outside the range
        // of -9999 to 9999, which will pass checkdate() but
        // fail to construct a date object.
        $this->time = self::toISO($this->time);
        parent::__construct($this->time, $this->timezone);
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
   *   TRUE if the input time is a string with an expected format.
   */
  public function inputIsFormat() {
    return is_string($this->time) && !empty($this->format);
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
      parent::__construct('', $this->timezone);
      $date = parent::createFromFormat($this->format, $this->time, $this->timezone);
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
          if ($this->validate_format && $this->format($this->format) != $this->time_original) {
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
      @parent::__construct($this->time, $this->timezone);
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
   * @see http://us3.php.net/manual/en/time.getlasterrors.php
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
   * Test if the IntlDateFormatter is available and we have the
   * right settings to be able to use it.
   */
  function canUseIntl() {
    return $this->use_international && class_exists('IntlDateFormatter') && !empty($this->calendar) && !empty($this->locale);
  }

  /**
   * Use the IntlDateFormatter to display the format, if available.
   */
  function format($format) {
    if ($this->canUseIntl()) {
      $formatter = new IntlDateFormatter($this->locale, IntlDateFormatter::FULL, IntlDateFormatter::FULL, $this->timezone_name, $this->calendar);
      return $formatter->format($format);
    }
    else {
      return parent::format($format);
    }
  }

  /**
   * Returns all standard date parts in an array.
   *
   * @param object $date
   *   A date object.
   *
   * @return array
   *   An array of formatted date part values, keyed by date parts.
   */
  public static function toArray($date) {
    return array(
            'year'   => $date->format('Y'),
            'month'  => $date->format('n'),
            'day'    => $date->format('j'),
            'hour'   => intval($date->format('H')),
            'minute' => intval($date->format('i')),
            'second' => intval($date->format('s')),
           );
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
  public static function toISO($array, $force_valid_date = FALSE) {
    $array = self::prepareArray($array, $force_valid_date);
    $time = '';
    if ($array['year'] !== '') {
      $time = self::datePad(intval($array['year']), 4);
      if ($force_valid_date || $array['month'] !== '') {
        $time .= '-' . self::datePad(intval($array['month']));
        if ($force_valid_date || $array['day'] !== '') {
          $time .= '-' . self::datePad(intval($array['day']));
        }
      }
    }
    if ($array['hour'] !== '') {
      $time .= $time ? 'T' : '';
      $time .= self::datePad(intval($array['hour']));
      if ($force_valid_date || $array['minute'] !== '') {
        $time .= ':' . self::datePad(intval($array['minute']));
        if ($force_valid_date || $array['second'] !== '') {
          $time .= ':' . self::datePad(intval($array['second']));
        }
      }
    }
    return $time;
  }

  /**
   * Creates a complete array from a possibly incomplete array of date parts.
   *
   * @param array $array
   *   An array of date values keyed by date part.
   * @param bool $force_valid_date
   *   (optional) Whether to force a valid date by filling in missing
   *   values with valid values. Defaults to FALSE.
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
   * and that those values create a valid date. If time is provided,
   * verify that the time values are valid.
   *
   * @param array $array
   *   An array of datetime values keyed by date part.
   *
   * @return boolean
   *   TRUE if the datetime parts contain valid values, otherwise FALSE.
   */
  public static function checkArray($array) {
    $valid_date = FALSE;
    $valid_time = TRUE;
    // Check for a valid date using checkdate(). Only values that
    // meet that test are valid.
    if (array_key_exists('year', $array) && array_key_exists('month', $array) && array_key_exists('day', $array)) {
      if (@checkdate($array['month'], $array['day'], $array['year'])) {
        $valid_date = TRUE;
      }
    }
    // Testing for valid time is reversed. Missing time is OK,
    // but incorrect values are not.
    foreach (array('hour', 'minute', 'second') as $key) {
      if (array_key_exists($key, $array)) {
        $value = $array[$key];
        switch ($value) {
          case 'hour':
            if (!preg_match('/^([1-2][0-3]|[01]?[0-9])$/', $value)) {
              $valid_time = FALSE;
            }
            break;
          case 'minute':
          case 'second':
          default:
            if (!preg_match('/^([0-5][0-9]|[0-9])$/', $value)) {
              $valid_time = FALSE;
            }
            break;
        }
      }
    }
    return $valid_date && $valid_time;
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
}
