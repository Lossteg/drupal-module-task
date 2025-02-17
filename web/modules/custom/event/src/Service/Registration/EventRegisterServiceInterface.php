<?php

declare(strict_types=1);

namespace Drupal\event\Service\Registration;

use Drupal\node\NodeInterface;

/**
 * Provides an interface for the Event Register Service.
 */
interface EventRegisterServiceInterface {

  /**
   * Checks if an event has reached its maximum capacity.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   *
   * @return bool
   *   TRUE if the event is full, FALSE otherwise.
   */
  public function isRegistrationFull(NodeInterface $event): bool;

  /**
   * Checks if a user is already registered for an event.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   * @param int $userId
   *   The user ID.
   *
   * @return bool
   *   TRUE if the user is registered, FALSE otherwise.
   */
  public function isUserRegistered(NodeInterface $event, int $userId): bool;

  /**
   * Returns the registration status for a user.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   * @param int $userId
   *   The user ID.
   *
   * @return array
   *   An associative array:
   *   - 'status' (bool): Can the user register?
   *   - 'message' (string): Message explaining the status.
   */
  public function getRegistrationStatus(NodeInterface $event, int $userId): array;

  /**
   * Registers a user for the event if the registration status allows it.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   *
   * @return array
   *   An associative array:
   *   - 'status' (bool): TRUE if the registration was successful, FALSE otherwise.
   *   - 'message' (string): A message explaining the result.
   */
  public function register(NodeInterface $event): array;
}
