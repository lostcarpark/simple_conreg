<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregConfig.
 */

namespace Drupal\simple_conreg;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregConfig {

  /**
   * Cet current event config.
   *
   * Parameters: Event ID.
   */
  public static function getConfig($eid) {

    // Get event configuration from config.
    $config = \Drupal::config('simple_conreg.settings.'.$eid);
    if (empty($config->get('payments.system'))) {
      $config = \Drupal::config('simple_conreg.settings');
    }
    
    return $config;
  }
    
  /**
   * Get specified fieldset config.
   *
   * Parameters: Event ID, Fieldset.
   */
  public static function getFieldsetConfig($eid, $fieldset = 0) {

    // If fieldset is not null or zero.
    if (!empty($fieldset)) {
      $configName = 'simple_conreg.settings.'.$eid.'.fieldset.'.$fieldset;
      $config = \Drupal::config($configName);
      if (empty($config->get('fields.first_name_label'))) {
        // No field settings have been stored, so use main config.
        $config = SimpleConregConfig::getConfig($eid);
      }
    } else {
      // Using default fields settings from main event config.
      $config = SimpleConregConfig::getConfig($eid);
    }

    return $config;

  }
  
  /**
   * Save specified fieldset config.
   *
   * Parameters: Event ID, Fieldset, FormVals.
   */
  public static function saveFieldsetConfig($eid, $fieldset, $vals) {
    // Get an editable config for the specified fieldset.
    if (!empty($fieldset)) {
      $configName = 'simple_conreg.settings.'.$eid.'.fieldset.'.$fieldset;
    } else {
      $configName = 'simple_conreg.settings.'.$eid;
    }
    $config = \Drupal::service('config.factory')->getEditable($configName);
    $config->set('fields.first_name_label', $vals['simple_conreg_fields']['first_name_label']);
    $config->set('fields.last_name_label', $vals['simple_conreg_fields']['last_name_label']);
    $config->set('fields.name_description', $vals['simple_conreg_fields']['name_description']);
    $config->set('fields.email_label', $vals['simple_conreg_fields']['email_label']);
    $config->set('fields.membership_type_label', $vals['simple_conreg_fields']['membership_type_label']);
    $config->set('fields.membership_type_description', $vals['simple_conreg_fields']['membership_type_description']);
    $config->set('fields.membership_days_label', $vals['simple_conreg_fields']['membership_days_label']);
    $config->set('fields.membership_days_description', $vals['simple_conreg_fields']['membership_days_description']);
    $config->set('fields.badge_name_option_label', $vals['simple_conreg_fields']['badge_name_option_label']);
    $config->set('fields.badge_name_label', $vals['simple_conreg_fields']['badge_name_label']);
    $config->set('fields.badge_name_description', $vals['simple_conreg_fields']['badge_name_description']);
    $config->set('fields.display_label', $vals['simple_conreg_fields']['display_label']);
    $config->set('fields.display_description', $vals['simple_conreg_fields']['display_description']);
    $config->set('fields.communication_method_label', $vals['simple_conreg_fields']['communication_method_label']);
    $config->set('fields.communication_method_description', $vals['simple_conreg_fields']['communication_method_description']);
    $config->set('fields.same_address_label', $vals['simple_conreg_fields']['same_address_label']);
    $config->set('fields.street_label', $vals['simple_conreg_fields']['street_label']);
    $config->set('fields.street2_label', $vals['simple_conreg_fields']['street2_label']);
    $config->set('fields.city_label', $vals['simple_conreg_fields']['city_label']);
    $config->set('fields.county_label', $vals['simple_conreg_fields']['county_label']);
    $config->set('fields.postcode_label', $vals['simple_conreg_fields']['postcode_label']);
    $config->set('fields.country_label', $vals['simple_conreg_fields']['country_label']);
    $config->set('fields.phone_label', $vals['simple_conreg_fields']['phone_label']);
    $config->set('fields.birth_date_label', $vals['simple_conreg_fields']['birth_date_label']);
    $config->set('fields.age_label', $vals['simple_conreg_fields']['age_label']);
    $config->set('fields.age_min', $vals['simple_conreg_fields']['age_min']);
    $config->set('fields.age_max', $vals['simple_conreg_fields']['age_max']);
    $config->set('fields.first_name_mandatory', $vals['simple_conreg_mandatory']['first_name']);
    $config->set('fields.last_name_mandatory', $vals['simple_conreg_mandatory']['last_name']);
    $config->set('fields.street_mandatory', $vals['simple_conreg_mandatory']['street']);
    $config->set('fields.street2_mandatory', $vals['simple_conreg_mandatory']['street2']);
    $config->set('fields.city_mandatory', $vals['simple_conreg_mandatory']['city']);
    $config->set('fields.county_mandatory', $vals['simple_conreg_mandatory']['county']);
    $config->set('fields.postcode_mandatory', $vals['simple_conreg_mandatory']['postcode']);
    $config->set('fields.country_mandatory', $vals['simple_conreg_mandatory']['country']);
    $config->set('fields.birth_date_mandatory', $vals['simple_conreg_mandatory']['birth_date']);
    $config->set('fields.age_mandatory', $vals['simple_conreg_mandatory']['age']);
    $config->set('fields.first_name_max_length', $vals['simple_conreg_max_lengths']['first_name']);
    $config->set('fields.last_name_max_length', $vals['simple_conreg_max_lengths']['last_name']);
    $config->set('fields.badge_name_max_length', $vals['simple_conreg_max_lengths']['badge_name']);
    $config->set('extras.flag1', $vals['simple_conreg_extras']['flag1']);
    $config->set('extras.flag2', $vals['simple_conreg_extras']['flag2']);
    $config->save();
  }

}

