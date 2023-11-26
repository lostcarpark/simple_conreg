<?php

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregAdminMemberOptions extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The HTTP request.
   *
   * @var Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Storage for private data.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $privateTempStoreFactory;

  /**
   * Constructor for member lookup form.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The database connection.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The store for private data.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   */
  public function __construct(AccountProxyInterface $currentUser, PrivateTempStoreFactory $privateTempStoreFactory, Request $request) {
    $this->currentUser = $currentUser;
    $this->privateTempStoreFactory = $privateTempStoreFactory;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('current_user'),
      $container->get('tempstore.private'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_admin_member_options';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $selection = 0) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    // Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $fieldOptions = FieldOptions::getFieldOptions($eid);
    $groupList = $fieldOptions->getFieldOptionGroupedList();

    $groupTitles = [];
    $optionTitles = [];
    $groupAdded = FALSE;
    foreach ($groupList as $val) {
      if (empty($val['optid'])) {
        // Optid not set, so entry is group heading.
        $groupAdded = FALSE;
        $grpid = $val['grpid'];
        $groupTitle = $val['group_title'];
        $groupTitles[$grpid] = $groupTitle;
      }
      else {
        // Entry is option.
        if ($this->currentUser->hasPermission('view field option ' . $val['optid'] . ' event ' . $eid)) {
          // Only display if user has permission to see option.
          if (!$groupAdded) {
            $options[$grpid] = $groupTitle;
            $groupAdded = TRUE;
          }
          $options[$grpid . "_" . $val['optid']] = " - " . $val['option_title'];
          $optionTitles[$val['optid']] = $val['option_title'];
        }
      }
    }

    // Check if user can see any options.
    if (empty($options)) {
      // User cannot see any options - display error message.
      $form['simple_conreg_event'] = [
        '#markup' => $this->t("You don't have permission to see any options. Please contact your administrator."),
        '#prefix' => '<h3>',
        '#suffix' => '</h3>',
      ];
      return $form;
    }

    $group = $this->request->query->get('group');
    $option = $this->request->query->get('option');

    $tempstore = $this->privateTempStoreFactory->get('simple_conreg');
    // If form values submitted, use the display value that was submitted over
    // the passed in values.
    if (isset($form_values['selOption'])) {
      $selection = $form_values['selOption'];
    }
    elseif (empty($selection)) {
      if (!empty($group)) {
        $group = preg_replace('/[^0-9]/i', '', $group);
        if (!empty($option)) {
          $option = preg_replace('/[^0-9]/i', '', $option);
          $selection = $group . '_' . $option;
        }
        else {
          $selection = $group;
        }
      }
      elseif (!empty($option)) {
        $option = preg_replace('/[^0-9]/i', '', $option);
        $selection = $option;
      }
      else {
        // If display not submitted from form or passed in through URL, take
        // last value from session.
        $selection = $tempstore->get('adminMemberSelectedOption');
      }
    }
    if (empty($selection) || !array_key_exists($selection, $options)) {
      // If still no display specified, or invalid option, default to first key
      // in displayOptions.
      $selection = key($options);
    }
    [$selGroup, $selOption] = array_pad(explode('_', $selection), 2, '');

    $tempstore->set('adminMemberSelectedOption', $selection);

    $form = [
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ],
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    ];

    $form['selOption'] = [
      '#type' => 'select',
      '#title' => $this->t('Select member option'),
      '#options' => $options,
      '#default_value' => $selection,
      '#required' => TRUE,
      '#ajax' => [
        'wrapper' => 'memberform',
        'callback' => [$this, 'updateDisplayCallback'],
        'event' => 'change',
      ],
    ];

    $showEmail = ($form_values['showEmail'] ?? FALSE);

    $form['showEmail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show email address'),
      '#default_value' => FALSE,
      '#ajax' => [
        'wrapper' => 'memberform',
        'callback' => [$this, 'updateDisplayCallback'],
        'event' => 'change',
      ],
    ];

    $headers = [
      'first_name' => [
        'data' => $this->t('First name'),
        'field' => 'm.first_name',
      ],
      'last_name' => [
        'data' => $this->t('Last name'),
        'field' => 'm.last_name',
      ],
    ];

    if ($showEmail) {
      $headers['email'] = ['data' => $this->t('Email'), 'field' => 'm.email'];
    }

    // Check if single option selected.
    $displayOpts = [];
    $optionTotals = [];
    if (!empty($selOption)) {
      // Specific option selected.
      $headers['option_' . $selOption] = [
        'data' => $optionTitles[$selOption],
        'field' => 'option_' . $selOption,
      ];
      $displayOpts[] = $selOption;
      $optionTotals[$selOption] = 0;
    }
    elseif (!empty($selGroup)) {
      // Group heading selected.
      $selOption = [];
      foreach ($groupList as $groupOption) {
        if ($groupOption['grpid'] == $selGroup && !empty($groupOption['optid']) && $this->currentUser->hasPermission('view field option ' . $groupOption['optid'] . ' event ' . $eid)) {
          $selOption[] = $groupOption['optid'];
          $displayOpts[] = $groupOption['optid'];
          $headers['option_' . $groupOption['optid']] = [
            'data' => $groupOption['option_title'],
            'field' => 'option_' . $groupOption['optid'],
          ];
          $optionTotals[$groupOption['optid']] = 0;
        }
      }
    }

    $headers['total'] = ['data' => 'Total', 'field' => 'total'];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => ['id' => 'simple-conreg-admin-member-list'],
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];

    if (!empty($selOption)) {
      // Fetch all entries for selected option or group.
      $entries = FieldOptionStorage::adminOptionMemberListLoad($eid, $selOption);
      // Reorganise to put all entries for a member in the same row.
      $optRows = [];
      foreach ($entries as $entry) {
        if (!isset($optRows[$entry['mid']])) {
          $optRows[$entry['mid']] = [
            'mid' => $entry['mid'],
            'first_name' => $entry['first_name'],
            'last_name' => $entry['last_name'],
            'email' => $entry['email'],
          ];
        }
        $optRows[$entry['mid']]['option_' . $entry['optid']] = ($entry['is_selected'] ? '✓ ' . $entry['option_detail'] : '');
      }
      // Track the total number of members in the category.
      $totalRows = 0;

      // Now loop through the combined results.
      foreach ($optRows as $entry) {
        $row = [];
        $row['first_name'] = [
          '#markup' => Html::escape($entry['first_name']),
        ];
        $row['last_name'] = [
          '#markup' => Html::escape($entry['last_name']),
        ];
        if ($showEmail) {
          $row['email'] = [
            '#markup' => Html::escape($entry['email']),
          ];
        }
        $rowTotal = 0;
        foreach ($displayOpts as $display) {
          if (isset($entry['option_' . $display])) {
            $val = $entry['option_' . $display];
            $rowTotal++;
            $optionTotals[$display]++;
          }
          else {
            $val = '';
          }
          $row['option_' . $display] = [
            '#markup' => Html::escape($val),
          ];
        }
        $row['total'] = [
          '#markup' => $rowTotal,
        ];
        $form['table'][] = $row;
        $totalRows++;
      }
    }

    // Populate final row of table with totals.
    $totalRow = [
      'first_name' => [
        '#markup' => $this->t('Total members'),
        '#wrapper_attributes' => ['colspan' => 2, 'class' => ['table-total']],
      ],
    ];
    if ($showEmail) {
      $totalRow['email'] = [
        '#markup' => '',
        '#wrapper_attributes' => ['class' => ['table-total']],
      ];
    }
    foreach ($displayOpts as $display) {
      $totalRow['option_' . $display] = [
        '#markup' => $optionTotals[$display],
        '#wrapper_attributes' => ['class' => ['table-total']],
      ];
    }
    $totalRow['total'] = [
      '#markup' => $this->t('@total members', ['@total' => $totalRows]),
      '#wrapper_attributes' => ['class' => ['table-total']],
    ];
    $form['table'][] = $totalRow;

    return $form;
  }

  /**
   * Callback function for "display" drop down.
   */
  public function updateDisplayCallback(array $form, FormStateInterface $form_state) {
    // Return new form.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
