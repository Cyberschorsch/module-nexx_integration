<?php

/**
 * @file
 * Contains \Drupal\nexx_integration\Plugin\MediaEntity\Type\NexxVideo.
 */

namespace Drupal\nexx_integration\Plugin\MediaEntity\Type;

use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_entity\MediaBundleInterface;
use Drupal\media_entity\MediaInterface;
use Drupal\media_entity\MediaTypeBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides media type plugin for Image.
 *
 * @MediaType(
 *   id = "nexx_video",
 *   label = @Translation("Nexx video"),
 *   description = @Translation("Handles videos from nexxOmnia Video CMS.")
 * )
 */
class NexxVideo extends MediaTypeBase {
  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Config\Config $config
   *   Media entity config object.
   * @param LoggerInterface $logger
   *  The logger service
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, Config $config, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $config);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('config.factory')->get('media_entity.settings'),
      $container->get('logger.factory')->get('nexx_integration')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function providedFields() {
    return [
      'nexx_item_id' => 'Nexx item ID.',
      'subtitle' => 'The subtitle.',
      'teaser' => 'The teaser.',
      'description' => 'The description.',
      'uploaded' => 'Time of upload.',
      'is_ssc' => 'Is SSC.',
      'encoded_ssc' => 'SSC is encoded.',
      'validfrom_ssc' => 'Valid from: SSC.',
      'validto_ssc' => 'Valid to: SSC.',
      'encoded_html5' => 'HTML5 is encoded.',
      'is_mobile' => 'Is Mobile.',
      'encoded_mobile' => 'Mobile is encoded.',
      'validfrom_mobile' => 'Valid from: mobile.',
      'validto_mobile' => 'Valid to: SSC.',
      'is_hyve' => 'Is HYVE.',
      'encoded_hyve' => 'HYVE is encoded.',
      'validfrom_hyve' => 'Valid from: hyve.',
      'validto_hyve' => 'Valid to: hyve.',
      'active' => 'Is active',
      'deleted' => 'Is deleted',
      'blocked' => 'Is blocked',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getField(MediaInterface $media, $name) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultThumbnail() {
    return $this->config->get('icon_base') . '/nexxvideo.png';
  }

  /**
   * {@inheritdoc}
   */
  public function thumbnail(MediaInterface $media) {
    $teaser_field = $this->configuration['teaser_image_field'];
    $teaser_image = $media->{$teaser_field}->first()->entity;

    $source_field = $this->entityTypeManager->getStorage('media_bundle')
      ->load($teaser_image->bundle())
      ->getTypeConfiguration()['source_field'];

    if(!empty($source_field)) {

      /**
       * @var \Drupal\file\Entity\File $uri
       */
      $uri = $teaser_image->{$source_field}->first()->entity->getFileUri();
      $this->logger->debug("field map: @field", array('@field' => print_r($teaser_field, TRUE)));
      $this->logger->debug("thumbnail uri: @uri", array('@uri' => $uri));
      if ($uri) {
        return $uri;
      }
    }
    return $this->getDefaultThumbnail();
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var MediaBundleInterface $bundle */
    $bundle = $form_state->getFormObject()->getEntity();

    $default_bundle = !empty($values['channel_field']) ? $values['channel_field'] : $this->configuration['channel_field'];
    $form['channel_field'] = [
      '#type' => 'select',
      '#title' => 'Channel ' . $bundle->label() ?: $this->t('Fields'),
      '#options' => $this->getMediaEntityReferenceFields($bundle->id(), ['taxonomy_term']),
      '#empty_option' => $this->t('Select field'),
      '#default_value' => $default_bundle,
      '#description' => $this->t('The taxonomy which is used for videos. You can create a bundle without selecting a value for this dropdown initially. This dropdown can be populated after adding taxonomy term entity references to the bundle.'),
    ];

    $default_bundle = !empty($values['actor_field']) ? $values['actor_field'] : $this->configuration['actor_field'];
    $form['actor_field'] = [
      '#type' => 'select',
      '#title' => 'Actor ' . $bundle->label() ?: $this->t('Fields'),
      '#options' => $this->getMediaEntityReferenceFields($bundle->id(), ['taxonomy_term']),
      '#empty_option' => $this->t('Select field'),
      '#default_value' => $default_bundle,
      '#description' => $this->t('The taxonomy which is used for actors. You can create a bundle without selecting a value for this dropdown initially. This dropdown can be populated after adding taxonomy term entity references to the bundle.'),
    ];

    $default_bundle = !empty($values['tags_field']) ? $values['tags_field'] : $this->configuration['tags_field'];
    $form['tags_field'] = [
      '#type' => 'select',
      '#title' => 'Tags ' . $bundle->label() ?: $this->t('Fields'),
      '#options' => $this->getMediaEntityReferenceFields($bundle->id(), ['taxonomy_term']),
      '#empty_option' => $this->t('Select field'),
      '#default_value' => $default_bundle,
      '#description' => $this->t('The taxonomy which is used for tags. You can create a bundle without selecting a value for this dropdown initially. This dropdown can be populated after adding taxonomy term entity references to the bundle.'),
    ];

    $default_bundle = !empty($values['teaser_image_field']) ? $values['teaser_image_field'] : $this->configuration['teaser_image_field'];
    $form['teaser_image_field'] = [
      '#type' => 'select',
      '#title' => 'Teaser image ' . $bundle->label() ?: $this->t('Fields'),
      '#options' => $this->getMediaEntityReferenceFields($bundle->id(), ['media']),
      '#empty_option' => $this->t('Select field'),
      '#default_value' => $default_bundle,
      '#description' => $this->t('The field which is used for the teaser image. You can create a bundle without selecting a value for this dropdown initially. This dropdown can be populated after adding media, image or file fields to the bundle.'),
    ];
    return $form;
  }

  /**
   * Builds a list of references for a media entity.
   *
   * @param $bundle_id Entity type to get references for
   * @param $target_types Target types filter
   * @return array
   *   An array of field labels, keyed by field name.
   */
  protected function getMediaEntityReferenceFields($bundle_id, array $target_types) {
    $bundle_options = array();

    foreach ($this->entityFieldManager->getFieldDefinitions('media', $bundle_id) as $field_id => $field_info) {
      // filter entity_references which are not base fields

      if ($field_info->getType() === 'entity_reference' && !$field_info->getFieldStorageDefinition()
          ->isBaseField() && in_array($field_info->getSettings()['target_type'], $target_types)
      ) {
        $bundle_options[$field_id] = $field_info->getLabel();
      }
    }
    natsort($bundle_options);
    return $bundle_options;
  }
}
