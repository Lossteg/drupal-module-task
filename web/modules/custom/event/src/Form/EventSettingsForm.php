<?php

declare(strict_types=1);

namespace Drupal\event\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a configuration form for event settings.
 */
class EventSettingsForm extends ConfigFormBase {

  /**
   * Returns configuration names for the form.
   *
   * @return array
   *   Array containing configuration names used in this form.
   */
  protected function getEditableConfigNames(): array {
    return ['event.settings'];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId(): string {
    return 'event_settings_form';
  }

  /**
   * Builds the configuration form for weather API settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('event.settings');

    $form['weather_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Weather API Key'),
      '#default_value' => $config->get('weather_api_key'),
      '#required' => TRUE,
    ];

    $form['weather_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Weather API URL'),
      '#default_value' => $config->get('weather_api_url'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Processes and saves the form submission values.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('event.settings')
      ->set('weather_api_key', (string) $form_state->getValue('weather_api_key'))
      ->set('weather_api_url', (string) $form_state->getValue('weather_api_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
