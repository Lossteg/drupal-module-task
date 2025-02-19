<?php

declare(strict_types=1);

namespace Drupal\event\Plugin\GraphQL\DataProducer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\event\Service\Registration\EventRegisterServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Registers a user for an event.
 *
 * @DataProducer(
 *   id = "register_for_event",
 *   name = @Translation("Register for event"),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Registration result")
 *   ),
 *   consumes = {
 *     "event_id" = @ContextDefinition("integer",
 *       label = @Translation("Event ID")
 *     )
 *   }
 * )
 */
final class RegisterForEvent extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The event registration service.
   *
   * @var \Drupal\event\Service\Registration\EventRegisterServiceInterface
   */
  protected EventRegisterServiceInterface $eventRegistration;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a RegisterForEvent object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\event\Service\Registration\EventRegisterServiceInterface $event_registration
   *   The event registration service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EventRegisterServiceInterface $event_registration
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->eventRegistration = $event_registration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('event.registration')
    );
  }

  /**
   * Registers user for an event.
   *
   * @param int $event_id
   *   The ID of the event.
   *
   * @return array
   *   The registration result with success status and message.
   */
  public function resolve(int $event_id): array {
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($event_id);

    if (!$node) {
      return [
        'success' => FALSE,
        'message' => 'Event not found',
      ];
    }

    $result = $this->eventRegistration->register($node);

    return [
      'success' => $result['status'],
      'message' => $result['message'],
    ];
  }

}
