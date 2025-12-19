<?php

namespace Drupal\simple_conreg;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Locale\CountryManager;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregOptions {

  /**
   * Function to get cache ID for member classes.
   *
   * @param int $eid
   *   The event ID.
   *
   * @return string
   *   The cache identifier.
   */
  public static function getMemberClassCid(int $eid): string {
    return 'conreg:memberClasses:' . $eid . ':' . \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
  }

  /**
   * Return list of membership classes from config.
   *
   * @param int $eid
   *   The event ID.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   *
   * @return object
   *   Object containing arrays class array and options array.
   */
  public static function memberClasses(int $eid, ImmutableConfig|NULL &$config = NULL): object {
    $cid = self::getMemberClassCid($eid);
    if ($cache = \Drupal::cache()->get($cid)) {
      return $cache->data;
    }

    // If config not passed in, we need to load it.
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }

    $memberClasses = (object) [
      'classes' => [],
      'options' => [],
    ];

    $classArray = $config->get('member.classes');
    if (empty($classArray)) {
      // Member classes not stored, so check for legacy fieldset configurations.
      $memberClasses->classes['Default'] = self::convertFieldsetToMemberClass($config, 'Default');
      $memberClasses->options['Default'] = 'Default';
      for ($cnt = 1; $cnt <= 5; $cnt++) {
        $fieldsetConfig = SimpleConregConfig::getFieldsetConfig($eid, $cnt);
        if (!empty($fieldsetConfig)) {
          $memberClasses->classes[$cnt] = self::convertFieldsetToMemberClass($fieldsetConfig, $cnt);
          $memberClasses->options[$cnt] = $cnt;
        }
      }
    }
    else {
      // Build object variable from configuration array.
      foreach ($classArray as $classRef => $classVals) {
        $className = $classVals['name'] ?? $classRef;
        $memberClasses->classes[$classRef] = (object) [
          'name' => $className,
        ];
        $memberClasses->options[$classRef] = $className;
        foreach ($classVals as $category => $catVals) {
          if ($category != 'name') {
            $memberClasses->classes[$classRef]->$category = (object) [];
            foreach ($catVals as $entryName => $entryVal) {
              $memberClasses->classes[$classRef]->$category->$entryName = $entryVal;
            }
          }
        }
      }
    }
    \Drupal::cache()->set($cid, $memberClasses);
    return $memberClasses;
  }

  /**
   * Function to save member classes to configuration.
   *
   * @param int $eid
   *   The event ID.
   * @param object $memberClasses
   *   Array of member classes to save.
   */
  public static function saveMemberClasses(int $eid, object $memberClasses): void {
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.' . $eid);
    // Get existing classes and check they haven't been deleted.
    $classArray = $config->get('member.classes');
    foreach ($classArray as $classRef => $val) {
      if (!array_key_exists($classRef, $memberClasses->classes)) {
        // Member Class not in memberClasses, so delete from configuration.
        $config->clear("member.classes.$classRef");
      }
    }
    // Save all member classes to configuration.
    foreach ($memberClasses->classes as $classRef => $classVals) {
      $config->set("member.classes.$classRef.name", $classVals->name);
      foreach ($classVals as $category => $catVals) {
        if ($category != 'name') {
          foreach ($catVals as $entryName => $entryVal) {
            $config->set("member.classes.$classRef.$category.$entryName", $entryVal);
          }
        }
      }
    }
    $config->save();
    \Drupal::cache()->invalidate(self::getMemberClassCid($eid));
  }

  /**
   * Function used to convert legacy fieldsets into MemberClasses.
   *
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   * @param string $name
   *   The name of the class.
   *
   * @return object
   *   Object containing the class details.
   */
  private static function convertFieldsetToMemberClass(ImmutableConfig $config, string $name) {
    $class = (object) [
      'name' => $name,
      'fields' => (object) [],
      'mandatory' => (object) [],
      'max_length' => (object) [],
      'extras' => (object) [],
    ];
    foreach ($config->get('fields') as $key => $value) {
      if (preg_match('/(.*)_label$/', $key, $matches)) {
        $field = $matches[1];
        $class->fields->$field = $value;
      }
      if (preg_match('/.*_description$|age_min$|age_max$/', $key, $matches)) {
        $description = $matches[0];
        $class->fields->$description = $value;
      }
      if (preg_match('/(.*)_mandatory$/', $key, $matches)) {
        $mandatory = $matches[1];
        $class->mandatory->$mandatory = $value;
      }
      if (preg_match('/(.*)_max_length$/', $key, $matches)) {
        $max_length = $matches[1];
        $class->max_length->$max_length = $value;
      }
    }
    foreach ($config->get('extras') as $key => $value) {
      $class->extras->$key = $value;
    }

    return $class;
  }

  /**
   * Function to get cache ID for member types.
   *
   * @param int $eid
   *   The event ID.
   *
   * @return string
   *   The cache identifier.
   */
  public static function getMemberTypeCid(int $eid) {
    return 'conreg:memberTypes:' . $eid . ':' . \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
  }

  /**
   * Return list of membership types from config.
   *
   * @param int $eid
   *   The event ID.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   *
   * @return object
   *   Object containing type details.
   */
  public static function memberTypes(int $eid, ImmutableConfig|NULL $config = NULL): object {
    $cid = self::getMemberTypeCid($eid);
    if ($cache = \Drupal::cache()->get($cid)) {
      return $cache->data;
    }

    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $showRemaining = $config->get('payments.show_remaining') ?? FALSE;
    $days = self::days($eid, $config);

    // If we need to show remaining memberships, fetch number of members of each
    // type from database.
    $numberOfMembers = [];
    if ($showRemaining) {
      // Get the number of members by type.
      foreach (SimpleConregStorage::adminMemberSummaryLoad($eid) as $entry) {
        $numberOfMembers[$entry['member_type']] = $entry['num'];
      }
    }

    $memberTypes = new \stdClass();
    $memberTypes->types = [];
    $memberTypes->firstOptions = [];
    $memberTypes->publicOptions = [];
    $memberTypes->privateOptions = [];
    $memberTypes->publicNames = [];

    $typesArray = $config->get('member.types');
    if (empty($typesArray)) {
      $typesArray = self::convertLegacyMemberTypes($eid, $config);
    }
    foreach ($typesArray as $typeCode => $typeVals) {
      $type = new \stdClass();
      $soldOut = FALSE;
      foreach ($typeVals as $key => $val) {
        if ($key == 'days') {
          $type->days = [];
          $type->dayOptions = [];
          if (isset($val)) {
            foreach ($val as $dayCode => $dayVals) {
              $type->days[$dayCode] = (object) [
                'name' => $days[$dayCode],
                'description' => $dayVals['description'],
                'price' => $dayVals['price'],
              ];
              $type->dayOptions[$dayCode] = $dayVals['description'];
            }
          }
        }
        elseif ($key == 'confirmation') {
          $type->confirmation = (object)[
            'override' => $val['override'] ?: FALSE,
            'template_subject' => $val['template_subject'] ?: '',
            'template_body' => $val['template_body'] ?: '',
            'template_format' => $val['template_format'] ?: '',
          ];
        }
        elseif (!empty($key)) {
          $type->$key = $val;
        }
      }
      if (!isset($type->confirmation)) {
        $type->confirmation = (object)[
          'override' => FALSE,
          'template_subject' => '',
          'template_body' => '',
          'template_format' => '',
        ];
      }
      // Display number of remaining memberships if required.
      if ($showRemaining && $typeVals['number_allowed']) {
        // If limited number for type, calculate number left.
        $numberForType = isset($numberOfMembers[$typeCode]) && $numberOfMembers[$typeCode] ? $numberOfMembers[$typeCode] : 0;
        $numberRemaining = $typeVals['number_allowed'] - $numberForType;
        $displayDescription = t('%description (%number remaining)', ['%description' => $type->description, '%number' => $numberRemaining]);
        if ($numberRemaining <= 0) {
          $soldOut = TRUE;
        }
        $type->remaining = $numberRemaining;
      }
      else {
        // No number limit so just show description.
        $displayDescription = $type->description;
      }
      $memberTypes->types[$typeCode] = $type;
      if ($type->active && $type->allowFirst && !$soldOut) {
        $memberTypes->firstOptions[$typeCode] = $displayDescription;
      }
      if ($type->active && !$soldOut) {
        $memberTypes->publicOptions[$typeCode] = $displayDescription;
        $memberTypes->publicNames[$typeCode] = $type->name;
      }
      $memberTypes->privateOptions[$typeCode] = $displayDescription;
    }
    $tags = ['event:' . $eid . ':type'];
    if ($showRemaining) {
      $tags[] = 'event:' . $eid . ':remaining';
    }
    \Drupal::cache()->set($cid, $memberTypes, Cache::PERMANENT, $tags);
    return $memberTypes;
  }

  /**
   * Return list of membership types from config.
   *
   * @param int $eid
   *   The event ID.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   *
   * @return array
   *   Array of member types.
   */
  public static function convertLegacyMemberTypes(int $eid, ImmutableConfig &$config): array {
    // One type per line.
    $types = explode("\n", $config->get('member_types'));
    $typeVals = [];
    foreach ($types as $type) {
      if (!empty($type)) {
        $typeFields = array_pad(explode('|', $type), 8, '');
        [
          $code,
          $desc,
          $name,
          $price,
          $badgeType,
          $fieldset,
          $allowFirst,
          $active,
          $defaultDays,
        ] = $typeFields;
        // Remove any extra spacing.
        $code = trim($code);
        $fieldset = trim($fieldset);
        // If fieldset not specified, use 0.
        if (empty($fieldset)) {
          $fieldset = 0;
        }
        $days = NULL;
        // If extra fields, they will contain day details.
        $days = [];
        if (strpos($defaultDays, '~') !== FALSE) {
          [$dayDesc, $dayCode] = array_pad(explode('~', $defaultDays), 2, '');
          $days[$dayCode] = [
            'description' => trim($dayDesc),
            'price' => trim($price),
          ];
          $defaultDays = $dayCode;
        }
        $fieldCount = count($typeFields);
        if ($fieldCount > 9) {
          for ($fieldNo = 9; $fieldNo < $fieldCount; $fieldNo++) {
            [$dayCode, $dayDesc, , $dayPrice] = array_pad(explode('~', $typeFields[$fieldNo]), 4, '');
            $days[$dayCode] = [
              'description' => trim($dayDesc),
              'price' => trim($dayPrice),
            ];
          }
        }
        $typeVals[$code] = (object) [
          'name' => trim($name),
          'description' => trim($desc),
          'price' => trim($price),
          'badgeType' => trim($badgeType),
          'memberClass' => trim($fieldset) == 0 ? 'Default' : trim($fieldset),
          'allowFirst' => trim($allowFirst),
          'active' => trim($active),
          'defaultDays' => trim($defaultDays),
          'days' => $days,
        ];
      }
    }

    return $typeVals;
  }

  /**
   * Function to save member types to configuration.
   *
   * @param int $eid
   *   The event ID.
   * @param object $memberTypes
   *   Object containing the membership types.
   */
  public static function saveMemberTypes(int $eid, object $memberTypes): void {
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.' . $eid);
    // Get existing types and check they haven't been deleted.
    $typeArray = $config->get('member.types');
    foreach ($typeArray as $typeRef => $val) {
      $config->clear("member.types.$typeRef");
    }
    // Save all member types to configuration.
    foreach ($memberTypes->types as $typeRef => $typeVals) {
      foreach ($typeVals as $key => $val) {
        if ($key == 'days' && isset($val)) {
          $config->clear("member.types.$typeRef.days");
          foreach ($val as $dayCode => $dayVals) {
            $config->set("member.types.$typeRef.days.$dayCode.description", $dayVals->description);
            $config->set("member.types.$typeRef.days.$dayCode.price", $dayVals->price);
          }
        }
        elseif ($key == 'dayOptions') {
          // Do nothing - dayoptions are generated.
        }
        elseif ($key == 'confirmation') {
          $config->set("member.types.$typeRef.confirmation.override", $val->override);
          $config->set("member.types.$typeRef.confirmation.template_subject", $val->template_subject);
          $config->set("member.types.$typeRef.confirmation.template_body", $val->template_body);
          $config->set("member.types.$typeRef.confirmation.template_format", $val->template_format);
        }
        elseif ($key == '') {
          // Don't save empty key.
        }
        else {
          $config->set("member.types.$typeRef.$key", $val);
        }
      }
    }
    $config->save();
    \Drupal::cache()->invalidate(self::getMembertypeCid($eid));
  }

  /**
   * Return list of membership upgrade paths available from config.
   *
   * @param int $eid
   *   The event ID.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   */
  public static function memberUpgrades($eid, ImmutableConfig|null &$config = NULL) {
    // Store upgrade options in a static array.
    static $member_upgrades = [];

    // If member upgrades previously stored, just return them.
    if (!empty($member_upgrades[$eid])) {
      return $member_upgrades[$eid];
    }

    // If config not passed in, we need to load it.
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }

    $types = self::memberTypes($eid, $config);
    // One upgrades per line.
    $upgrades = explode("\n", $config->get('member_upgrades'));
    $upgradeOptions = [];
    $upgradeVals = [];
    foreach ($upgrades as $upgrade) {
      if (!empty($upgrade)) {
        [$upid, $fromtype, $fromdays, $totype, $todays, $tobadge, $desc, $price] = array_pad(explode('|', $upgrade), 8, '');
        // If list not present, add from type as first option.
        if (!isset($upgradeOptions[$fromtype][$fromdays])) {
          $upgradeOptions[$fromtype][$fromdays][0] = $types->types[$fromtype]->name;
        }
        // Store.
        $upgradeOptions[$fromtype][$fromdays][$upid] = $desc;
        $upgradeVals[$upid] = (object) [
          'fromType' => $fromtype,
          'fromDays' => $fromdays,
          'toType' => $totype,
          'toDays' => $todays,
          'toBadgeType' => $tobadge,
          'desc' => $desc,
          'price' => $price,
        ];
      }
    }

    // Stash member upgrades in static variable in case needed again.
    $member_upgrades[$eid] = (object) [
      'options' => $upgradeOptions,
      'upgrades' => $upgradeVals,
    ];
    return $member_upgrades[$eid];
  }

  /**
   * Return list of badge types from config.
   *
   * @param int $eid
   *   The event ID.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   */
  public static function badgeTypes($eid, ImmutableConfig|null $config = NULL) {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    // One type per line.
    $types = explode("\n", $config->get('badge_types'));
    $badgeTypes = [];
    foreach ($types as $type) {
      if (strlen(trim($type))) {
        [$code, $badgeType] = explode('|', $type);
        $badgeTypes[trim($code)] = trim($badgeType);
      }
    }
    return $badgeTypes;
  }

  /**
   * Return list of badge name options from config.
   *
   * @param int $eid
   *   The event ID.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   *
   * @return array
   *   List of badge name options.
   */
  public static function badgeNameOptions(int $eid, ImmutableConfig|null &$config = NULL): array {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    // One type per line.
    $options = explode("\n", $config->get('badge_name_options'));
    $badgeNameOptions = [];
    foreach ($options as $option) {
      if (!empty($option)) {
        [$code, $badgeOption] = explode('|', $option);
        $badgeNameOptions[trim($code)] = trim($badgeOption);
      }
    }
    return $badgeNameOptions;
  }

  /**
   * If member name has been entered, customise the badge name options.
   *
   * @param int $eid
   *   The event ID.
   * @param string $firstName
   *   The member's first name.
   * @param string $lastName
   *   The member's last name.
   * @param int $maxLength
   *   The maximum length of the badge name.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   *
   * @return array
   *   A list of options for the badge name selector.
   */
  public static function badgeNameOptionsForName(int $eid, string $firstName, string $lastName, int $maxLength, ImmutableConfig|null $config = NULL): array {
    $badgeNameOptions = self::badgeNameOptions($eid, $config);
    if (!(empty($firstName) && empty($lastName))) {
      if (array_key_exists('F', $badgeNameOptions)) {
        $badgeNameOptions['F'] = substr($firstName, 0, $maxLength);
      }
      if (array_key_exists('N', $badgeNameOptions)) {
        $badgeNameOptions['N'] = substr($firstName . ' ' . $lastName, 0, $maxLength);
      }
      if (array_key_exists('L', $badgeNameOptions)) {
        $badgeNameOptions['L'] = substr($lastName . ', ' . $firstName, 0, $maxLength);
      }
    }
    return $badgeNameOptions;
  }

  /**
   * Return list of days from config.
   *
   * @param int $eid
   *   The event ID.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration settings.
   *
   * @return array
   *   Array of days.
   */
  public static function days(int $eid, ImmutableConfig|null $config = NULL): array {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    // One type per line.
    $dayLines = explode("\n", $config->get('days'));
    $days = [];
    foreach ($dayLines as $dayLine) {
      if (trim($dayLine)) {
        [$dayCode, $dayName] = explode('|', $dayLine);
        $days[trim($dayCode)] = trim($dayName) ?? '';
      }
    }
    return $days;
  }

  /**
   * Return list of membership add-ons from config.
   *
   * @param int $eid
   *   The event ID.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   *
   * @return array
   *   Member add-on options and prices.
   */
  public static function memberAddons(int $eid, ImmutableConfig|null $config = NULL): array {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    // One type per line.
    $addOns = explode("\n", $config->get('add_ons.options') ?: '');
    $addOnOptions = [];
    $addOnPrices = [];
    foreach ($addOns as $addOn) {
      if (!empty($addOn)) {
        [$desc, $price] = explode('|', $addOn);
        $addOnOptions[$desc] = $desc;
        $addOnPrices[$desc] = $price;
      }
    }
    return [$addOnOptions, $addOnPrices];
  }

  /**
   * Return list of membership add-ons from config.
   *
   * @param int $eid
   *   The event ID.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration object.
   * @param bool $reset
   *   If true reset cached version.
   *
   * @return array
   *   List of countries.
   */
  public static function memberCountries(int $eid, ImmutableConfig|null $config = NULL, bool $reset = FALSE): array {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $cid = 'simple_conreg:countryList_' . $eid . '_' . $language;
    // Check if previously used country list available.
    if (!$reset && $cache = \Drupal::cache()->get($cid)) {
      return $cache->data;
    }
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    // Get country list from country manager.
    $manager = new CountryManager(\Drupal::moduleHandler());
    $countries = $manager->getList();

    // Get no country label, if set.
    $noCountryLabel = trim($config->get('reference.no_country_label'));
    // Combine no country label with country list.
    $countryOptions = empty($noCountryLabel)
      ? $countries
      : array_merge([0 => $noCountryLabel], $countries);

    // Cache for future use.
    \Drupal::cache()->set($cid, $countryOptions);
    return $countryOptions;
  }

  /**
   * Return list of display options for membership list.
   *
   * @param int $eid
   *   The event ID.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   *
   * @return array
   *   List of display options.
   */
  public static function display(int $eid = 1, ImmutableConfig|null $config = NULL): array {
    // Get the config display options.
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $options = explode("\n", $config->get('display_options.options'));
    $display_options = [];
    foreach ($options as $option) {
      [$code, $description] = array_pad(explode('|', trim($option)), 2, '');
      $display_options[$code] = $description;
    }
    return $display_options;
  }

  /**
   * Return list of communications methods.
   *
   * @param int $eid
   *   The event ID.
   * @param Drupal\Core\Config\ImmutableConfig|null $config
   *   The configuration settings.
   * @param bool $publicOnly
   *   If true, return only pyblic methods.
   *
   * @return array
   *   List of communications methods.
   */
  public static function communicationMethod(int $eid, ImmutableConfig|null $config = NULL, bool $publicOnly = TRUE): array {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    // One communications method per line.
    $methodOptions = [];
    $communicationsMethods = $config->get('communications_method.options');
    if (!$communicationsMethods) {
      return $methodOptions;
    }
    $methods = explode("\n", $communicationsMethods);
    foreach ($methods as $method) {
      [$code, $description, $public] = array_pad(explode('|', trim($method)), 3, '');
      if (!$publicOnly || $public == '1' || $public == '') {
        $methodOptions[$code] = $description;
      }
    }
    return $methodOptions;
  }

  /**
   * Return list of payment methods.
   *
   * @return array
   *   List of communications methods.
   */
  public static function paymentMethod(): array {
    return [
      'Stripe' => t('Stripe'),
      'Bank Transfer' => t('Bank Transfer'),
      'Cash' => t('Cash'),
      'Cheque' => t('Cheque'),
      'Credit Card' => t('Credit Card'),
      'Free' => t('Free'),
      'Groats' => t('Groats'),
      'PayPal' => t('PayPal'),
    ];
  }

  /**
   * Return yes and no.
   *
   * @return array
   *   List containing yes and no.
   */
  public static function yesNo(): array {
    return [
      0 => t('No'),
      1 => t('Yes'),
    ];
  }

}
