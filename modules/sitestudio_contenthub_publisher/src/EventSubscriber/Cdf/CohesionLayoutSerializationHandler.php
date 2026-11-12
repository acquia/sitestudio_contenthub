<?php

namespace Drupal\sitestudio_contenthub_publisher\EventSubscriber\Cdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent;
use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles links during CohesionLayout entity serialization.
 *
 * @see \Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent
 */
class CohesionLayoutSerializationHandler implements EventSubscriberInterface {

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[AcquiaContentHubEvents::SERIALIZE_ADDITIONAL_METADATA][] = [
      'onSerializeCohesionLayout',
      100,
    ];

    return $events;
  }

  /**
   * CohesionLayoutSerializationHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   EntityTypeManager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Handle links during CohesionLayout serialization.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent $event
   *   Serialize event for additional metadata.
   */
  public function onSerializeCohesionLayout(SerializeAdditionalMetadataEvent $event) {
    $entity = $event->getEntity();

    if (!$entity instanceof CohesionLayout) {
      return;
    }
    $linkReferences = $this->processLinks($entity);
    $translationLinks = [];
    foreach ($entity->getTranslationLanguages(FALSE) as $langcode => $language) {
      $links = $this->processLinks($entity->getTranslation($langcode));
      if (!empty($links)) {
        $translationLinks[$langcode] = $links;
      }
    }

    if (empty($linkReferences) && empty($translationLinks)) {
      return;
    }

    $cdf = $event->getCdf();
    $metadata = $cdf->getMetadata();
    $metadata['additional_data']['processed_links'] = $linkReferences;
    if (!empty($translationLinks)) {
      $metadata['additional_data']['processed_links_translations'] = $translationLinks;
    }
    $cdf->setMetadata($metadata);
    $event->setCdf($cdf);

    $event->stopPropagation();
  }

  /**
   * Collects link references of a layout translation, with IDs as UUIDs.
   *
   * @param \Drupal\cohesion_elements\Entity\CohesionLayout $layout
   *   The layout entity in a specific translation.
   *
   * @return array
   *   Link references keyed by element UUID.
   */
  protected function processLinks(CohesionLayout $layout): array {
    $linkReferences = $layout->getLayoutCanvasInstance()->getLinksReferences();
    foreach ($linkReferences as &$element) {
      foreach ($element as &$linkReference) {
        $entity_type = $linkReference['entity_type'] ?? NULL;
        if (!is_string($entity_type) || $entity_type === '' || !$this->entityTypeManager->hasDefinition($entity_type)) {
          continue;
        }
        $linkedEntity = $this->entityTypeManager
          ->getStorage($entity_type)
          ->load($linkReference['entity_id']);

        if ($linkedEntity instanceof ContentEntityInterface) {
          $linkReference['entity_id'] = $linkedEntity->uuid();
        }
      }
      unset($linkReference);
    }
    unset($element);

    return $linkReferences ?: [];
  }

}
