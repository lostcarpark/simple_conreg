<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregOptions.
 */

namespace Drupal\simple_conreg;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregOptions
{

  // Function to get cache ID for member classes.
  public static function getMemberClassCID($eid)
  {
    return 'conreg:memberClasses:' . $eid . ':' . \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
  }

  /**
   * Return list of membership classes (for customising field list a member type sees) from config.
   *
   * Parameters: Optional config.
   */
  public static function memberClasses($eid, &$config = NULL)
  {
    $cid = self::getMemberClassCID($eid);
    if ($cache = \Drupal::cache()->get($cid)) {
      return $cache->data;
    }

    // If config not passed in, we need to load it.
    if (is_null($config))
      $config = SimpleConregConfig::getConfig($eid);

    $memberClasses = (object)[
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
        $className = isset($classVals['name']) ? $classVals['name'] : $classRef;
        $memberClasses->classes[$classRef] = (object)[
          'name' => $className,
        ];
        $memberClasses->options[$classRef] = $className;
        foreach ($classVals as $category => $catVals) {
          if ($category != 'name') {
            $memberClasses->classes[$classRef]->$category = (object)[];
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

  // Function to save member classes to 
  public static function saveMemberClasses($eid, $memberClasses)
  {
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
    \Drupal::cache()->invalidate(self::getMemberClassCID($eid));
  }

  // Function used to convert legacy fieldsets into MemberClasses.
  private static function convertFieldsetToMemberClass($config, $name)
  {
    $class = (object)[
      'name' => $name,
      'fields' => (object)[],
      'mandatory' => (object)[],
      'max_length' => (object)[],
      'extras' => (object)[],
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

  // Function to get cache ID for member types.
  public static function getMemberTypeCID($eid)
  {
    return 'conreg:memberTypes:' . $eid . ':' . \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
  }

  /**
   * Return list of membership types from config.
   *
   * Parameters: Event ID, Optional config.
   */
  public static function memberTypes($eid, &$config = NULL)
  {
    $cid = self::getMemberTypeCID($eid);
    if ($cache = \Drupal::cache()->get($cid)) {
      return $cache->data;
    }

    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $days = self::days($eid, $config);

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
      foreach ($typeVals as $key => $val) {
        if ($key == 'days') {
          $type->days = [];
          $type->dayOptions = [];
          if (isset($val)) {
            foreach ($val as $dayCode => $dayVals) {
              $type->days[$dayCode] = (object)['name' => $days[$dayCode], 'description' => $dayVals['description'], 'price' => $dayVals['price']];
              $type->dayOptions[$dayCode] = $dayVals['description'];
            }
          }
        }
        elseif (!empty($key)) {
          $type->$key = $val;
        }
      }
      $memberTypes->types[$typeCode] = $type;
      if ($type->active && $type->allowFirst) {
        $memberTypes->firstOptions[$typeCode] = $type->description;
      }
      if ($type->active) {
        $memberTypes->publicOptions[$typeCode] = $type->description;
        $memberTypes->publicNames[$typeCode] = $type->name;
      }
      $memberTypes->privateOptions[$typeCode] = $type->description;
    }
    \Drupal::cache()->set($cid, $memberTypes);
    return $memberTypes;
  }

  /**
   * Return list of membership types from config.
   *
   * Parameters: Event ID, config.
   */
  public static function convertLegacyMemberTypes($eid, &$config)
  {
    $types = explode("\n", $config->get('member_types')); // One type per line.
    $typeVals = [];
    foreach ($types as $type) {
      if (!empty($type)) {
        $typeFields = array_pad(explode('|', $type), 8, '');
        list($code, $desc, $name, $price, $badgeType, $fieldset, $allowFirst, $active, $defaultDays) = $typeFields;
        // Remove any extra spacing.
        $code = trim($code);
        $fieldset = trim($fieldset);
        // If fieldset not specified, use 0.
        if (empty($fieldset)) {
          $fieldset = 0;
        }
        $days = null;
        // If extra fields, they will contain day details.
        $days = [];
        $dayOptions = [];
        if (strpos($defaultDays, '~') !== FALSE) {
          list($dayDesc, $dayCode) = array_pad(explode('~', $defaultDays), 2, '');
          $days[$dayCode] = [
            'description' => trim($dayDesc),
            'price' => trim($price)
          ];
          $defaultDays = $dayCode;
        }
        $fieldCount = count($typeFields);
        if ($fieldCount > 9) {
          for ($fieldNo = 9; $fieldNo < $fieldCount; $fieldNo++) {
            list($dayCode, $dayDesc, $dayName, $dayPrice) = array_pad(explode('~', $typeFields[$fieldNo]), 4, '');
            $days[$dayCode] = [
              'description' => trim($dayDesc),
              'price' => trim($dayPrice)
            ];
          }
        }
        $typeVals[$code] = (object)[
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

  // Function to save member types to configuration.
  public static function saveMemberTypes($eid, $memberTypes)
  {
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('simple_conreg.settings.' . $eid);
    // Get existing types and check they haven't been deleted.
    $typeArray = $config->get('member.types');
    foreach ($typeArray as $typeRef => $val) {
      if (!array_key_exists($typeRef, $memberTypes->types)) {
        // Member type not in memberTypes, so delete from configuration.
        $config->clear("member.types.$typeRef");
      }
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
        elseif ($key == '') {
        // Don't save empty key.
        }
        else {
          $config->set("member.types.$typeRef.$key", $val);
        }
      }
    }
    $config->save();
    \Drupal::cache()->invalidate(self::getMembertypeCID($eid));
  }

  /**
   * Return list of membership upgrade paths available from config.
   *
   * Parameters: Optional config.
   */
  public static function memberUpgrades($eid, &$config = NULL)
  {
    static $member_upgrades = []; // Store upgrade options in a static array.

    // If member upgrades previously stored, just return them.
    if (!empty($member_upgrades[$eid])) {
      return $member_upgrades[$eid];
    }

    // If config not passed in, we need to load it.
    if (is_null($config))
      $config = SimpleConregConfig::getConfig($eid);

    $types = self::memberTypes($eid, $config);
    $upgrades = explode("\n", $config->get('member_upgrades')); // One upgrades per line.
    $upgradeOptions = [];
    $upgradeVals = [];
    foreach ($upgrades as $upgrade) {
      if (!empty($upgrade)) {
        list($upid, $fromtype, $fromdays, $totype, $todays, $tobadge, $desc, $price) = array_pad(explode('|', $upgrade), 8, '');
        // If list not present, add from type as first option.
        if (!isset($upgradeOptions[$fromtype][$fromdays])) {
          $upgradeOptions[$fromtype][$fromdays][0] = $types->types[$fromtype]->name;
        }
        // Store 
        $upgradeOptions[$fromtype][$fromdays][$upid] = $desc;
        $upgradeVals[$upid] = (object)[
          'fromType' => $fromtype,
          'fromDays' => $fromdays,
          'toType' => $totype,
          'toDays' => $todays,
          'toBadgeType' => $tobadge,
          'desc' => $desc,
          'price' => $price];
      }
    }

    // Stash member upgrades in static variable in case needed again.
    $member_upgrades[$eid] = (object)['options' => $upgradeOptions, 'upgrades' => $upgradeVals];
    return $member_upgrades[$eid];
  }

  /**
   * Return list of badge types from config.
   *
   * Parameters: Optional config.
   */
  public static function badgeTypes($eid, &$config = NULL)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $types = explode("\n", $config->get('badge_types')); // One type per line.
    $badgeTypes = [];
    foreach ($types as $type) {
      list($code, $badgeType) = explode('|', $type);
      $badgeTypes[trim($code)] = trim($badgeType);
    }
    return $badgeTypes;
  }

  /**
   * Return list of badge name options from config.
   *
   * Parameters: Optional config.
   */
  public static function badgeNameOptions($eid, &$config = NULL)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $options = explode("\n", $config->get('badge_name_options')); // One type per line.
    $badgeNameOptions = [];
    foreach ($options as $option) {
      if (!empty($option)) {
        list($code, $badgeOption) = explode('|', $option);
        $badgeNameOptions[trim($code)] = trim($badgeOption);
      }
    }
    return $badgeNameOptions;
  }

  /**
   * If member name has been entered, customise the badge name options.
   */
  public static function badgeNameOptionsForName($eid, $firstName, $lastName, $maxLength, &$config = NULL)
  {
    $badgeNameOptions = self::badgeNameOptions($eid, $config);
    if (!(empty($firstName) && empty($lastName))) {
      if (array_key_exists('F', $badgeNameOptions))
        $badgeNameOptions['F'] = substr($firstName, 0, $maxLength);
      if (array_key_exists('N', $badgeNameOptions))
        $badgeNameOptions['N'] = substr($firstName . ' ' . $lastName, 0, $maxLength);
      if (array_key_exists('L', $badgeNameOptions))
        $badgeNameOptions['L'] = substr($lastName . ', ' . $firstName, 0, $maxLength);
    }
    return $badgeNameOptions;
  }

  /**
   * Return list of days from config.
   *
   * Parameters: Optional config.
   */
  public static function days($eid, &$config = NULL)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $dayLines = explode("\n", $config->get('days')); // One type per line.
    $days = [];
    foreach ($dayLines as $dayLine) {
      list($dayCode, $dayName) = explode('|', $dayLine);
      $days[trim($dayCode)] = trim($dayName);
    }
    return $days;
  }

  /**
   * Return list of membership add-ons from config.
   *
   * Parameters: Optional config.
   */
  public static function memberAddons($eid, &$config = NULL)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $addOns = explode("\n", $config->get('add_ons.options')); // One type per line.
    $addOnOptions = array();
    $addOnPrices = array();
    foreach ($addOns as $addOn) {
      if (!empty($addOn)) {
        list($desc, $price) = explode('|', $addOn);
        $addOnOptions[$desc] = $desc;
        $addOnPrices[$desc] = $price;
      }
    }
    return array($addOnOptions, $addOnPrices);
  }

  /**
   * Return list of membership add-ons from config.
   *
   * Parameters: Optional config.
   */
  public static function memberCountries($eid, &$config = NULL)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $countries = explode("\n", $config->get('reference.countries')); // One country per line.
    $countryOptions = array();
    foreach ($countries as $country) {
      if (!empty($country)) {
        list($code, $name) = explode('|', $country);
        $countryOptions[$code] = $name;
      }
    }
    return $countryOptions;
  }

  /**
   * Return list of display options for membership list.
   */
  public static function display($eid = 1, $config = NULL)
  {
    // Get the config display options.
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $options = explode("\n", $config->get('display_options.options'));
    $display_options = [];
    foreach ($options as $option) {
      list($code, $description) = array_pad(explode('|', trim($option)), 2, '');
      $display_options[$code] = $description;
    }
    return $display_options;
  /* return ['F' => t('Full name and badge name'),
   'B' => t('Badge name only'),
   'N' => t('Not at all')]; */
  }

  /**
   * Return list of communications methods (from ).
   */
  public static function communicationMethod($eid, $config = NULL, $publicOnly = TRUE)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }
    $methods = explode("\n", $config->get('communications_method.options')); // One communications method per line.
    $methodOptions = array();
    foreach ($methods as $method) {
      list($code, $description, $public) = array_pad(explode('|', trim($method)), 3, '');
      if (!$publicOnly || $public == '1' || $public == '')
        $methodOptions[$code] = $description;
    }
    return $methodOptions;
  /* return ['E' => t('Electronic only'),
   'P' => t('Paper only'),
   'B' => t('Both electronic and paper')]; */
  }

  /**
   * Return list of payment methods.
   */
  public static function paymentMethod()
  {
    return ['Stripe' => t('Stripe'),
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
   */
  public static function yesNo()
  {
    return [
      0 => t('No'),
      1 => t('Yes'),
    ];
  }

}
