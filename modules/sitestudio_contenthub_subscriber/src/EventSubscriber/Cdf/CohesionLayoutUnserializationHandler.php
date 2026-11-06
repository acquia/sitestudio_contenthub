<?php

namespace Drupal\sitestudio_contenthub_subscriber\EventSubscriber\Cdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent;
use Drupal\cohesion\LayoutCanvas\LayoutCanvas;
use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles links during CohesionLayout entity unserialization.
 *
 * @see \Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent
 * @see \Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent
 */
class CohesionLayoutUnserializationHandler implements EventSubscriberInterface {

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[AcquiaContentHubEvents::UNSERIALIZE_ADDITIONAL_METADATA][] = [
      'onUnserializeCohesionLayout',
      100,
    ];
    return $events;
  }

  /**
   * CohesionLayoutSerializationHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   EntityTypeManager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger channel factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger_factory->get('sitestudio_contenthub');
  }

  /**
   * Handle links during CohesionLayout unserialization.
   *
   * @param \Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent $event
   *   Unserialize event for additional metadata.
   */
  public function onUnserializeCohesionLayout(UnserializeAdditionalMetadataEvent $event) {
    $entity = $event->getEntity();
    if (!$entity instanceof CohesionLayout) {
      return;
    }

    $metadata = $event->getCdf()->getMetadata();
    if (isset($metadata['additional_data']['processed_links'])) {
      $link_references = $this->resolveLinks($metadata['additional_data']['processed_links'], $entity);
      if (!empty($link_references)) {
        $this->applyLinks($entity, $link_references);
        $event->setEntity($entity);
      }
    }

    foreach ($metadata['additional_data']['processed_links_translations'] ?? [] as $langcode => $links) {
      if (!$entity->hasTranslation($langcode)) {
        $this->logger->warning(
          'Layout %id has links for translation %lang, which does not exist on this site; skipping.',
          [
            '%id' => $entity->id(),
            '%lang' => $langcode,
          ]
        );
        continue;
      }
      $translation = $entity->getTranslation($langcode);
      $link_references = $this->resolveLinks($links, $translation);
      if (!empty($link_references)) {
        // The translation is a separate object, so honour the parent's flag.
        $this->applyLinks($translation, $link_references, $entity->callApi);
        $event->setEntity($entity);
      }
    }
    $event->stopPropagation();
  }

  /**
   * Replaces link UUIDs with the local entity IDs.
   *
   * Links whose target cannot be resolved locally (unknown entity type or a
   * target absent on this site) are dropped from the update set and logged, so
   * a publisher UUID is never written into the canvas as an entity ID. The
   * canvas keeps the syndicated value, which may still be the publisher's ID.
   *
   * @param array $links
   *   Link references keyed by element UUID, with entity UUIDs.
   * @param \Drupal\cohesion_elements\Entity\CohesionLayout $layout
   *   The layout the links belong to, used for logging.
   *
   * @return array
   *   Link references with local entity IDs.
   */
  protected function resolveLinks(array $links, CohesionLayout $layout): array {
    foreach ($links as $element_uuid => $element) {
      foreach ($element as $index => $link_reference) {
        $entity_type = $link_reference['entity_type'] ?? NULL;
        if (!is_string($entity_type) ||
          $entity_type === '' ||
          !$this->entityTypeManager->hasDefinition($entity_type)
        ) {
          // Keeping it would write "<type>::<uuid>", which can't be parsed.
          unset($links[$element_uuid][$index]);
          $this->logger->warning(
            'Skipping link for layout %id (%lang): entity type %type is not available on this site.',
            [
              '%id' => $layout->id(),
              '%lang' => $layout->language()->getId(),
              '%type' => is_string($entity_type) && $entity_type !== '' ? $entity_type : '(missing)',
            ]
          );
          continue;
        }
        $linked_entity = $this->entityTypeManager
          ->getStorage($entity_type)
          ->loadByProperties(['uuid' => $link_reference['entity_id']]);
        $linked_entity = reset($linked_entity);

        if ($linked_entity instanceof ContentEntityInterface) {
          $links[$element_uuid][$index]['entity_id'] = $linked_entity->id();
          continue;
        }
        unset($links[$element_uuid][$index]);
        $this->logger->warning(
          'Link target %type %uuid not found for layout %id (%lang); the syndicated link value was left as-is and may still be the publisher ID.',
          [
            '%type' => $entity_type,
            '%uuid' => $link_reference['entity_id'],
            '%id' => $layout->id(),
            '%lang' => $layout->language()->getId(),
          ]
        );
      }
      if (empty($links[$element_uuid])) {
        unset($links[$element_uuid]);
      }
    }

    return $links;
  }

  /**
   * Applies link references to the layout canvas of a layout translation.
   *
   * @param \Drupal\cohesion_elements\Entity\CohesionLayout $layout
   *   The layout entity in a specific translation.
   * @param array $link_references
   *   Link references keyed by element UUID.
   * @param bool $call_api
   *   Whether the parent entity allows API calls.
   */
  protected function applyLinks(CohesionLayout $layout, array $link_references, bool $call_api = TRUE): void {
    $layout_canvas = $layout->getLayoutCanvasInstance();
    $this->updateLinks($layout_canvas, $link_references);
    $layout->setJsonValue(json_encode($layout_canvas));
    // preSave() only recompiles the default translation's template.
    if (!$layout->isDefaultTranslation() && $call_api && $layout->callApi) {
      $error = $layout->process();
      if ($error) {
        $this->logger->error(
          'Template recompile failed for layout %id (%lang): @error. The translation keeps its previous template until it is rebuilt.',
          [
            '%id' => $layout->id(),
            '%lang' => $layout->language()->getId(),
            '@error' => (string) $error,
          ]
        );
      }
    }
  }

  /**
   * Updates links in LayoutCanvas entity.
   *
   * @param \Drupal\cohesion\LayoutCanvas\LayoutCanvas $layoutCanvas
   *   LayoutCanvas instance.
   * @param array $updated_links
   *   Array of updated links, keyed by their property_key in LayoutCanvas.
   */
  protected function updateLinks(LayoutCanvas &$layoutCanvas, array $updated_links) {
    foreach ($layoutCanvas->iterateCanvas() as &$element) {
      if (array_key_exists($element->getUUID(), $updated_links)) {
        if ($element->getModel()) {
          foreach ($updated_links[$element->getUUID()] as $updated_link) {
            $link = $element->getModel()->getProperty($updated_link['path']);
            if ($link) {
              $link = $updated_link['entity_type'] . '::' . $updated_link['entity_id'];
              $element->getModel()->setProperty($updated_link['path'], $link);
            }
          }
        }
      }
    }
  }

}
