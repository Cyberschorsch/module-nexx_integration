<?php

/**
 * @file
 * Contains \Drupal\nexx_integration\Form\SettingsForm.
 */

namespace Drupal\nexx_integration\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManagerInterface;

/**
 * Defines a form that configures nexx video settings.
 */
class SettingsForm extends ConfigFormBase {
  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The display plugin manager.
   *
   * @var \Drupal\entity_embed\EntityEmbedDisplay\EntityEmbedDisplayManager
   */
  protected $displayPluginManager;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityManagerInterface $entity_manager){
    parent::__construct($config_factory);
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nexx_integration_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $settings = $this->config('nexx_integration.settings');

    $api_url = !empty($values['nexx_api_url']) ? $values['nexx_api_url'] : $settings->get('nexx_api_url');
    $form['nexx_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Url'),
      '#default_value' => $api_url,
    ];

    $api_key = !empty($values['nexx_api_authkey']) ? $values['nexx_api_authkey'] : $settings->get('nexx_api_authkey');
    $form['nexx_api_authkey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API authkey'),
      '#default_value' => $api_key,
    ];

    $omnia_id = !empty($values['omnia_id']) ? $values['omnia_id'] : $settings->get('omnia_id');
    $form['omnia_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Omnia ID'),
      '#description' => 'The unique identifier of the site, given by nexx.tv.',
      '#default_value' => $omnia_id,
    ];

    // Add the embed type plugin settings.
    $form['type_settings'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="nexx-type-settings-wrapper">',
      '#suffix' => '</div>',
    ];

    $entity_type = $this->entityManager->getDefinition('media');
    $bundle = !empty($values['type_settings']['video_bundle']) ? $values['type_settings']['video_bundle'] : $settings->get('video_bundle');
    $form['type_settings']['video_bundle'] = array(
      '#type' => 'select',
      '#title' => $entity_type->getBundleLabel() ?: $this->t('Bundles'),
      '#options' => $this->getEntityBundleOptions($entity_type),
      '#default_value' => $bundle,
      '#description' => $this->t('The bundle which is used for videos.'),
    );
    $form['type_settings']['bundles']['#access'] = !empty($form['bundles']['#options']);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('nexx_integration.settings')
      ->set('video_bundle', $values['type_settings']['video_bundle'])
      ->set('channel_vocabulary', $values['vocabulary_settings']['channel_vocabulary'])
      ->set('actor_vocabulary', $values['vocabulary_settings']['actor_vocabulary'])
      ->set('nexx_api_url', $values['nexx_api_url'])
      ->set('nexx_api_authkey', $values['nexx_api_authkey'])
      ->set('omnia_id', $values['omnia_id'])
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'nexx_integration.settings',
    ];
  }

  /**
   * Builds a list of entity type options.
   *
   * @return array
   *   An array of entity type labels, keyed by entity type name.
   */
  protected function getEntityTypeOptions() {
    $options = $this->entityManager->getEntityTypeLabels(TRUE);

    foreach ($options as $group => $group_types) {
      foreach (array_keys($group_types) as $entity_type_id) {
        // Filter out entity types that do not have a view builder class.
        if (!$this->entityManager->getDefinition($entity_type_id)->hasViewBuilderClass()) {
          unset($options[$group][$entity_type_id]);
        }
      }
    }

    return $options;
  }

  /**
   * Ajax callback to update the form fields which depend on embed type.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return AjaxResponse
   *   Ajax response with updated options for the embed type.
   */
  public function updateTypeSettings(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Update options for entity type bundles.
    $response->addCommand(new ReplaceCommand(
      '#nexx-type-settings-wrapper',
      $form['type_settings']
    ));

    return $response;
  }

  /**
   * Builds a list of entity type bundle options.
   *
   * Configuration entity types without a view builder are filtered out while
   * all other entity types are kept.
   *
   * @return array
   *   An array of bundle labels, keyed by bundle name.
   */
  protected function getEntityBundleOptions(EntityTypeInterface $entity_type) {
    $bundle_options = array();
    // If the entity has bundles, allow option to restrict to bundle(s).
    if ($entity_type->hasKey('bundle')) {
      foreach ($this->entityManager->getBundleInfo($entity_type->id()) as $bundle_id => $bundle_info) {
        $bundle_options[$bundle_id] = $bundle_info['label'];
      }
      natsort($bundle_options);
    }
    return $bundle_options;
  }
}
