<?php

namespace Drupal\sitestudio_contenthub_subscriber\EventSubscriber\UnserializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField\FieldEntityReferenceBase;
use Drupal\acquia_contenthub\PrunedEntitiesTracker;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Entity/image/file field reference handling.
 */
class SitestudioEntityReferenceFieldUnserializer extends FieldEntityReferenceBase implements EventSubscriberInterface {

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SitestudioEntityReferenceFieldSerializer constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\acquia_contenthub\PrunedEntitiesTracker $tracker
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   EntityTypeManager service.
   */
  public function __construct(LoggerInterface $logger, PrunedEntitiesTracker $tracker, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($logger, $tracker);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::UNSERIALIZE_CONTENT_ENTITY_FIELD] = [
      'onUnserializeContentField',
      10,
    ];
    return $events;
  }

  /**
   * Extracts the target storage and retrieves the referenced entity.
   *
   * @param \Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent $event
   *   The unserialize event.
   *
   * @throws \Exception
   */
  public function onUnserializeContentField(UnserializeCdfEntityFieldEvent $event) {
    $field = $event->getField();
    if ($event->getFieldMetadata()['type'] !== 'cohesion_entity_reference_revisions') {
      return;
    }
    $values = [];

    if (!empty($field['value'])) {
      foreach ($field['value'] as $langcode => $value) {
        foreach ($value as $item) {
          $entity = $this->getEntity($item, $event);
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $values[$langcode][$event->getFieldName()][] = [
            'target_id' => $entity->id(),
            'target_revision_id' => $entity->getRevisionId(),
          ];
        }
      }
    }

    $event->setValue($values);
    $event->stopPropagation();
  }

}
