<?php

namespace Drupal\tagify_user_list;

use Drupal\Component\Utility\Html;
use Drupal\tagify\TagifyEntityAutocompleteMatcher;

/**
 * Matcher class to get autocompletion results for entity reference type user.
 */
class TagifyUserListEntityAutocompleteMatcher extends TagifyEntityAutocompleteMatcher {

  /**
   * Gets matched labels based on a given search string.
   *
   * @param string $target_type
   *   The ID of the target entity type.
   * @param string $selection_handler
   *   The plugin ID of the entity reference selection handler.
   * @param array $selection_settings
   *   An array of settings that will be passed to the selection handler.
   * @param string $string
   *   (optional) The label of the entity to query by.
   * @param array $selected
   *   An array of selected values.
   *
   * @return array
   *   An array of matched entity labels, in the format required by the AJAX
   *   autocomplete API (e.g. array('value' => $value, 'label' => $label)).
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see \Drupal\system\Controller\EntityAutocompleteController
   */
  public function getMatches($target_type, $selection_handler, array $selection_settings, $string = '', array $selected = []): array {
    $matches = [];
    $storage = $this->entityTypeManager->getStorage($target_type);
    $options = $selection_settings + [
      'target_type' => $target_type,
      'handler' => $selection_handler,
    ];
    $handler = $this->selectionManager->getInstance($options);
    if ($handler !== FALSE) {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $match_limit = isset($selection_settings['match_limit']) ? (int) $selection_settings['match_limit'] : 10;
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, $match_limit + count($selected));
      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          // Filter out already selected items.
          if (in_array($entity_id, $selected, TRUE)) {
            continue;
          }
          $info_label = NULL;
          $entity = $storage->load($entity_id);
          if (!empty($selection_settings['info_label'])) {
            $info_label = $this->token->replacePlain($selection_settings['info_label'], [$target_type => $entity], ['clear' => TRUE]);
            $info_label = trim(preg_replace('/\s+/', ' ', $info_label));
          }
          $context = $options + ['entity' => $entity];
          $this->moduleHandler->alter('tagify_autocomplete_match', $label, $info_label, $context);
          if ($label !== NULL) {
            $matches[$entity_id] = $this->buildTagifyUserListItem($entity_id, $label, $info_label);
          }
        }
      }
      if ($match_limit > 0) {
        $matches = array_slice($matches, 0, $match_limit, TRUE);
      }
      $this->moduleHandler->alterDeprecated('Use hook_tagify_autocomplete_match_alter() instead.', 'tagify_user_list_autocomplete_matches', $matches, $options);
    }

    return array_values($matches);
  }

  /**
   * Builds the array that represents the entity in tagify autocomplete user.
   *
   * @param string $entity_id
   *   The matched entity id.
   * @param string $label
   *   The matched label.
   * @param ?string $info_label
   *   The matched info label.
   *
   * @return array
   *   The tagify item array. Associative array with the following keys:
   *   - 'entity_id':
   *     The referenced entity ID.
   *   - 'label':
   *     The text to be shown in the autocomplete and tagify, IE: "My label"
   *   - 'info_label':
   *     The extra information to be shown below the entity label.
   *   - 'avatar':
   *     The user image.
   *   - 'attributes':
   *     A key-value array of extra properties sent directly to tagify, IE:
   *     ['--tag-bg' => '#FABADA']
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildTagifyUserListItem(string $entity_id, string $label, ?string $info_label): array {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage('user')->load($entity_id);
    // Get image path.
    $image_url = '';
    if ($entity->hasField('user_picture')
      && !$entity->get('user_picture')->isEmpty()
    ) {
      /** @var \Drupal\file\FileInterface $user_image */
      $user_image = $entity->get('user_picture')->entity;
      $image_style = 'thumbnail';
      /** @var \Drupal\image\Entity\ImageStyle $style */
      $style = $this->entityTypeManager->getStorage('image_style')->load($image_style);
      $image_url = $style->buildUrl($user_image->getFileUri());
    }
    $tagify_user_list_path = $this->moduleHandler->getModuleList()['tagify_user_list']->getPath();

    return [
      'entity_id' => $entity_id,
      'label' => Html::decodeEntities($label),
      'info_label' => $info_label,
      'avatar' => $image_url ?: '/' . $tagify_user_list_path . '/images/no-user.svg',
      'editable' => FALSE,
    ];
  }

}
