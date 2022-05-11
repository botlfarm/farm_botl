<?php

namespace Drupal\farm_botl\Plugin\QuickForm;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\farm_quick\Plugin\QuickForm\QuickFormBase;
use Drupal\farm_quick\Traits\QuickLogTrait;
use Psr\Container\ContainerInterface;

/**
 * BOTL animal observation form.
 *
 * @QuickForm(
 *   id = "botl_animal_observation",
 *   label = @Translation("Animal observations"),
 *   description = @Translation("Record standard animal observations."),
 *   helpText = @Translation("Use this form to record standard animal observations."),
 *   permissions = {
 *     "create observation log",
 *   }
 * )
 */
class AnimalObservation extends QuickFormBase {

  use QuickLogTrait;

  /**
   * Define the quantity measurements we want to capture.
   */
  protected $measurements = [

    // Example: animal weight measurement.
    // This array key must be unique.
    'weight' => [

      // The form table heading for this measurement.
      'heading' => 'Weight',

      // This should be one of keys from quantity_measures().
      // @see https://github.com/farmOS/farmOS/blob/029853883a93f898c608bdd1ebc7e90dc7d9e220/modules/core/quantity/quantity.module#L21
      'measure' => 'weight',

      // The units taxonomy term (optional)
      'units' => 'lbs',

      // Quantity label (optional).
      'label' => 'Current weight',
    ],

    // Additional measurements go here, following the pattern above...
  ];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a QuickFormBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $messenger);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $id = NULL) {
    $form = [];

    // Load a list of active animals.
    $animals = $this->entityTypeManager->getStorage('asset')->loadByProperties([
      'type' => 'animal',
      'status' => 'active',
    ]);

    // If there are no animals, stop here.
    if (empty($animals)) {
      $this->messenger->addWarning('No animals found.');
      return $form;
    }

    // Define the table headers.
    $headers = ['Animal'];
    foreach ($this->measurements as $measurement) {
      if (!empty($measurement['heading'])) {
        $headers[] = $measurement['heading'];
      }
    }

    // Create the table.
    $form['animals'] = [
      '#type' => 'table',
      '#header' => $headers,
    ];

    // Iterate through the animals.
    foreach ($animals as $animal) {

      // Add a link to the animal asset record.
      $form['animals'][$animal->id()]['asset'] = [
        '#type' => 'markup',
        '#markup' => $animal->toLink()->toString(),
      ];

      // Iterate through the measurements and add a numeric field for each.
      foreach ($this->measurements as $key => $measurement) {
        $form['animals'][$animal->id()][$key] = [
          '#type' => 'number',
          '#title' => $measurement['heading'],
          '#title_display' => 'invisible',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Iterate through submitted values and create observation logs.
    $animal_values = $form_state->getValue('animals');
    foreach ($animal_values as $id => $values) {

      // Load the animal asset.
      $animal = $this->entityTypeManager->getStorage('asset')->load($id);

      // Build quantities for each non-empty measurement.
      $quantities = [];
      foreach ($this->measurements as $key => $measurement) {
        if (!empty($values[$key])) {
          $quantity = [
            'value' => $values[$key],
          ];
          if (!empty($measurement['measure'])) {
            $quantity['measure'] = $measurement['measure'];
          }
          if (!empty($measurement['units'])) {
            $quantity['units'] = $measurement['units'];
          }
          if (!empty($measurement['label'])) {
            $quantity['label'] = $measurement['label'];
          }
          $quantities[] = $quantity;
        }
      }

      // Create an observation log.
      $this->createLog([
        'name' => $this->t('Animal observation: @animal', ['@animal' => $animal->label()]),
        'type' => 'observation',
        'asset' => $animal,
        'quantity' => $quantities,
      ]);
    }
  }

}
