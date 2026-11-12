<?php

namespace Drupal\${name}\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form alter hook implementations.
 */
final class FormAlter {

  /**
   * Implements hook_form_FORM_ID_alter() for media_library_add_form.
   */
  #[Hook('form_media_library_add_form_alter')]
  public function mediaLibraryAddForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    // Add the Claro media library styles.
    $form['#attributes']['class'][] = 'media-library-add-form';
    $form['#attached']['library'][] = '${name}/media-library';

    // If there are unsaved media items, apply styling classes to various parts
    // of the form.
    if (isset($form['media'])) {
      $form['#attributes']['class'][] = 'media-library-add-form--with-input';

      // Put a wrapper around the informational message above the unsaved media
      // items.
      $form['description']['#template'] = '<p class="media-library-add-form__description">{{ text }}</p>';
    }
    else {
      $form['#attributes']['class'][] = 'media-library-add-form--without-input';
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for media_library_add_form_upload.
   */
  #[Hook('form_media_library_add_form_upload_alter')]
  public function mediaLibraryAddFormUpload(array &$form, FormStateInterface $form_state): void {
    $form['#attributes']['class'][] = 'media-library-add-form--upload';
    if (isset($form['container']['upload'])) {
      // Set this flag so we can prevent the details element from being added
      // in \Drupal\claro\ClaroPreRender::managedFile.
      $form['container']['upload']['#do_not_wrap_in_details'] = TRUE;
    }
    if (isset($form['container'])) {
      $form['container']['#attributes']['class'][] = 'media-library-add-form__input-wrapper';
    }
  }

}
