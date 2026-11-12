<?php

namespace Drupal\bootstrap_ui_kit\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class BootstrapUiKitSettingsForm extends ConfigFormBase
{
  const SETTINGS = 'bootstrap_ui_kit.settings';

  public function getFormId()
  {
    return 'bootstrap_ui_kit_settings_form';
  }

  protected function getEditableConfigNames()
  {
    return [static::SETTINGS];
  }

  public function buildForm(array $form, FormStateInterface $form_state, $theme = '')
  {
    $config = $this->config(static::SETTINGS);

    $form['enable_dev_kit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Developer Kit'),
      '#default_value' => $config->get('enable_dev_kit'),
      '#description' => $this->t('Displays example implementations for developers on the UI Kit.'),
    ];

    $form['iconography'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Iconography'),
    ];
    $form['iconography']['icons_source_folder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icons source folder'),
      '#default_value' => $config->get('icons_source_folder'),
    ];
    $form['iconography']['icons_sprite_file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icons sprite file'),
      '#default_value' => $config->get('icons_sprite_file'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    // Fieldsets do not create a nested value array unless #tree is TRUE.
    // The previous implementation tried to read $values['iconography'][...] which was always empty.
    // Retrieve values directly.
    $enable = (bool) $form_state->getValue('enable_dev_kit');
    $icons_source = (string) $form_state->getValue('icons_source_folder');
    $icons_sprite = (string) $form_state->getValue('icons_sprite_file');

    // Normalize: ensure leading slash for directory but not trailing slash.
    if ($icons_source !== '') {
      $icons_source = '/' . ltrim($icons_source, '/');
      $icons_source = rtrim($icons_source, '/');
    }
    if ($icons_sprite !== '') {
      $icons_sprite = '/' . ltrim($icons_sprite, '/');
    }

    $this->configFactory()->getEditable(static::SETTINGS)
      ->set('enable_dev_kit', $enable)
      ->set('icons_source_folder', $icons_source)
      ->set('icons_sprite_file', $icons_sprite)
      ->save();

    parent::submitForm($form, $form_state);
  }
}
