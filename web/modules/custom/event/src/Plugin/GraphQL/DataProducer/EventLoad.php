<?php

declare(strict_types=1);

namespace Drupal\event\Plugin\GraphQL\DataProducer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Loads an event by ID.
 *
 * @DataProducer(
 *   id = "event_by_id",
 *   name = @Translation("Load event by ID"),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Event")
 *   ),
 *   consumes = {
 *     "id" = @ContextDefinition("integer",
 *       label = @Translation("Event ID")
 *     )
 *   }
 * )
 */
final class EventLoad extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an EventLoad object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Resolves an event node by ID.
   *
   * @param int $id
   *   The event node ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The event node or NULL if not found or not of event type.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function resolve(int $id): ?NodeInterface {
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($id);

    return ($node instanceof NodeInterface && $node->bundle() === 'event')
      ? $node
      : NULL;
  }

}
