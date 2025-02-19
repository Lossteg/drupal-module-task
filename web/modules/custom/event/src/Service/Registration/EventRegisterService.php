<?php

declare(strict_types=1);

namespace Drupal\event\Service\Registration;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Provides services for event registration.
 */
final class EventRegisterService implements EventRegisterServiceInterface {

  use MessengerTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs a new EventRegisterService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   */
  public function __construct(
    Connection $database,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    DateFormatterInterface $date_formatter
  ) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Checks if an event has reached its maximum capacity.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   *
   * @return bool
   *   TRUE if event is full, FALSE otherwise.
   */
  public function isRegistrationFull(NodeInterface $event): bool {
    $maxParticipants = (int) $event->get('field_maximum_participants')->value;

    $currentParticipants = $this->database
      ->select('event_registrations', 'er')
      ->condition('er.event_id', $event->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    return $currentParticipants >= $maxParticipants;
  }

  /**
   * Checks if a user is already registered for an event.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   * @param int $userId
   *   The user ID to check.
   *
   * @return bool
   *   TRUE if user is registered, FALSE otherwise.
   */
  public function isUserRegistered(NodeInterface $event, int $userId): bool {
    $exists = $this->database->select('event_registrations', 'er')
      ->condition('er.event_id', $event->id())
      ->condition('er.user_id', $userId)
      ->countQuery()
      ->execute()
      ->fetchField();

    return (bool) $exists;
  }

  /**
   * Returns the registration status for the current user.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   * @param int $userId
   *   The user ID to check.
   *
   * @return array
   *   An array containing:
   *   - 'status': (bool) Can the user register?
   *   - 'message': (string) Message explaining the status.
   */
  public function getRegistrationStatus(NodeInterface $event, int $userId): array {
    if ($event->get('field_status')->value !== 'active') {
      return [
        'status' => FALSE,
        'message' => t('This event is no longer active.'),
      ];
    }

    if($this->isUserRegistered($event, $userId)) {
      return [
        'status' => FALSE,
        'message' => t('You`re already registered for this event.'),
      ];
    }

    if($this->isRegistrationFull($event)) {
      return [
        'status' => FALSE,
        'message' =>  t('Registration for this event is full.'),
      ];
    }

    return [
      'status' => TRUE,
      'message' => '',
    ];
  }

  /**
   * Register user for the event if register status is TRUE.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   *
   * @return array
   *   An array containing:
   *   - 'status': (bool) Registration status.
   *   - 'message': (string) Status message.
   *
   * @throws \Exception
   *   Throws exception if database transaction fails.
   *
   * @triggers cache_tags.invalidator
   *   Invalidates 'event_registration:[event_id]' cache tag on successful registration.
   */
  public function register(NodeInterface $event): array  {
    try {
      $userId = (int)$this->currentUser->id();

      $registrationStatus = $this->getRegistrationStatus($event, $userId);
      if (!$registrationStatus['status']) {
        return [
          'status' => FALSE,
          'message' => $registrationStatus['message']
        ];
      } else {
          $this->cacheTagsInvalidator->invalidateTags([
            'event_registration:' . $event->id(),
          ]);
      }

      $transaction = $this->database->startTransaction();
      $this->database->insert('event_registrations')
        ->fields([
          'event_id' => $event->id(),
          'user_id' => $userId,
          'created' => \Drupal::time()->getRequestTime(),
        ])
        ->execute();

      return [
        'status' => TRUE,
        'message' => t('You`ve successfully registered for this event!')
      ];
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }

      return [
        'status' => FALSE,
        'message' => t('An error occurred during registration.'),
      ];
    }
  }

  /**
   * Gets list of registered users for the event.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   *
   * @return array
   *   Array of registered users with their details.
   */
  public function getRegisteredUsers(NodeInterface $event): array {
    $query = $this->database->select('event_registrations', 'er')
      ->fields('u', ['uid', 'name', 'mail'])
      ->fields('er', ['created'])
      ->condition('er.event_id', $event->id())
      ->orderBy('er.created', 'DESC');

    $query->join(
      'users_field_data',
      'u',
      'er.user_id = u.uid'
    );

    $registrations = $query->execute()->fetchAll();

    return array_map(function($record) {
      return [
        'uid' => $record->uid,
        'name' => $record->name,
        'email' => $record->mail,
        'registered' => $this->dateFormatter->format(
          $record->created,
          'medium'
        ),
      ];
    }, $registrations);
  }

}
