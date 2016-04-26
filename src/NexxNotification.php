<?php

/**
 * @file
 * Contains Drupal\nexx_integration\NexxNotification
 */

namespace Drupal\nexx_integration;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class NexxNotification implements NexxNotificationInterface {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $nodeQuery;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Notify nexxOMNIA video CMS when channel or actor terms have been updated,
   * or when a video has been created.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity query object for taxonomy terms.
   * @param QueryFactory $query
   *   The entity query object for taxonomy terms.
   * @param ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param LoggerChannelFactory $logger
   *  The logger service
   * @param Client $http_client
   *  The HTTP client
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    QueryFactory $query,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactory $logger,
    Client $http_client
  ) {
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->nodeQuery = $query->get('node');
    $this->config = $config_factory->get('nexx_integration.settings');
    $this->logger = $logger->get('nexx_integration');
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  function insert($streamtype, $reference_number, $value) {
    if ($streamtype === 'video') {
      throw new \InvalidArgumentException(sprintf('Streamtype cannot be "%s" in insert operation.', $streamtype));
    }
    $this->notificateNexx($streamtype, $reference_number, 'insert', $value);
    $this->logger->info("insert @type. Reference number: @reference, value @value", array(
        '@type' => $streamtype,
        '@reference' => $reference_number,
        '@value' => $value
      )
    );

  }

  /**
   * {@inheritdoc}
   */
  function update($streamtype, $reference_number, $value) {
    $this->notificateNexx($streamtype, $reference_number, 'update', $value);
    $this->logger->info("update @type. Reference number: @reference, value @value", array(
        '@type' => $streamtype,
        '@reference' => $reference_number,
        '@value' => $value
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  function delete($streamtype, $reference_number) {
    if ($streamtype === 'video') {
      throw new \InvalidArgumentException(sprintf('Streamtype cannot be "%s" in delete operation.', $streamtype));
    }
    $this->notificateNexx($streamtype, $reference_number, 'delete');
    $this->logger->info("delete @type. Reference number: @reference", array(
        '@type' => $streamtype,
        '@reference' => $reference_number
      )
    );
  }

  /**
   * Send a crud notification to nexx
   *
   * @param string $streamtype
   *   The data type to update. Possible values are:
   *   - "actor"
   *   - "channel"
   *   - "tag"
   *   - "video"
   * @param string $reference_number
   *   Reference id. In case of streamtype video, this is the nexx ID in all
   *   other cases, this is the corresponding drupal id.
   * @param string $action
   *   CRUD operation. Possible values are:
   *   - "insert"
   *   - "update"
   *   - "delete"
   * @param string $value
   *   The value to be set.
   */
  protected function notificateNexx(
    $streamtype,
    $reference_number,
    $action,
    $value = NULL
  ) {
    $api_url = $this->config->get('nexx_api_url');
    $api_authkey = $this->config->get('nexx_api_authkey');

    $data = [
      'streamtype' => $streamtype,
      'action' => $action,
      'refnr' => $reference_number,
      'authkey' =>  $api_authkey
    ];
    if (isset($value)) {
      $data['value'] = $value;
    }


    try {
      $headers = array(
        'Content-Type' => 'application/json'
      );
      $options = array(
        'headers' => $headers,
        'body' => Json::encode($data),
      );
      $this->httpClient->post($api_url, $options);
      $this->logger->info("Successful notification. Streamtype '@streamtype', action '@action', refnr '@refnr', value '@value'", array(
        '@streamtype' => $streamtype,
        '@action' => $action,
        '@refnr' => $reference_number,
        '@value' => $value
      )
      );
    } catch (RequestException $e) {
      $this->logger->error("HTTP request failed: @error", array(
          '@error' => $e->getMessage()
        )
      );
    }
  }
}

