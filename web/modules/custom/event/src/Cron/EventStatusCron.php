<?php

declare(strict_types=1);

namespace Drupal\event\Cron;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\node\NodeInterface;

/**
 * Cron job to automatically update event statuses
 * if event deadlines have expired.
 */
final class EventStatusCron {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new EventStatusCron object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    LoggerInterface $logger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->logger = $logger;
  }

  /**
   * Retrieves IDs of expired events that are still active.
   *
   * @return array
   *   An array of node IDs for expired events.
   */
  protected function getExpiredEventIds() : array {
    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('type', 'event')
      ->condition('field_end_date', date('Y-m-d H:i:s'), '<')
      ->condition('field_status', 'active')
      ->accessCheck(FALSE);

    return $query->execute();
  }

  /**
   * Updates the status of a single event to 'closed'.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node to update.
   */
  protected function updateEventStatus(NodeInterface $event) : void {
    $this->logger->info(
      'Updating event @id (current status: @status)',
      [
        '@id' => $event->id(),
        '@status' => $event->get('field_status')->value,
      ]
    );

    $event->set('field_status', 'closed')->save();

    $this->logger->info(
      'Event @id status has been updated to closed.',
      ['@id' => $event->id()]
    );
  }

  /**
   * Updates the status of specified events to 'closed'.
   *
   * @param array $event_ids
   *   Array of event node IDs to update.
   */
  protected function updateExpiredEvents(array $event_ids) : void {
    /** @var \Drupal\node\NodeInterface[] $events */
    $events = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($event_ids);

    foreach ($events as $event) {
      $this->updateEventStatus($event);
    }
  }

  /**
   * Updates the status of expired events.
   */
  public function run() : void {
    $this->logger->info(
      'Starting automatic event status update process.'
    );

    $event_ids = $this->getExpiredEventIds();

    if (empty($event_ids)) {
      $this->logger->info(
        'No expired events found that require updates.'
      );

      return;
    }

    $this->updateExpiredEvents($event_ids);
  }
}
