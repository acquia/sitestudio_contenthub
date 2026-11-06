<?php

namespace Drupal\Tests\sitestudio_contenthub_subscriber\Kernel;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent;
use Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent;
use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Round trip of a translated layout through the real Content Hub handlers.
 *
 * The publisher and subscriber handlers are dispatched through the event
 * dispatcher, and the subscriber side uses real entity translations.
 *
 * @group Cohesion
 */
class CohesionLayoutTranslationRoundTripTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'acquia_contenthub',
    'cohesion',
    'cohesion_elements',
    'cohesion_templates',
    'sitestudio_contenthub_subscriber',
    'sitestudio_contenthub_publisher',
    'entity_reference_revisions',
    'file',
    'language',
    'node',
  ];

  const ELEMENT = 'd23a59d5-c8d7-49b8-9cda-61fa01aadf55';
  const LINK_PATH = '4793e582-fe13-490c-99d2-badcce843df7';
  const EN_UUID = 'a48e537a-6ec9-4191-b518-ed5e6d559a12';
  const DE_UUID = 'f8c3512b-aa9e-4008-8bf3-c03193ca3afa';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('cohesion', ['coh_usage']);
    $this->installEntitySchema('cohesion_layout');
    $this->installEntitySchema('component_content');
    $this->installConfig(['node', 'field', 'language']);
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    \Drupal::moduleHandler()->loadInclude('cohesion', 'install');
    \Drupal::moduleHandler()->loadInclude('cohesion_elements', 'install');

    NodeType::create(['type' => 'test', 'name' => 'test'])->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
    \Drupal::languageManager()->reset();
  }

  /**
   * Builds layout canvas JSON with a single link component.
   */
  protected function canvasJson(string $link): string {
    return json_encode([
      'canvas' => [[
        'uid' => 'cpt_test_component',
        'type' => 'component',
        'title' => 'Test Component',
        'enabled' => TRUE,
        'category' => 'category-10',
        'componentId' => 'cpt_test_component',
        'componentType' => 'link',
        'uuid' => self::ELEMENT,
        'parentUid' => 'root',
        'children' => [],
      ]],
      'mapper' => new \stdClass(),
      'model' => [
        self::ELEMENT => [
          'settings' => ['title' => 'Test Component'],
          self::LINK_PATH => $link,
        ],
      ],
      'previewModel' => new \stdClass(),
      'variableFields' => new \stdClass(),
      'meta' => ['fieldHistory' => []],
    ]);
  }

  /**
   * Creates a saved layout with a German translation.
   */
  protected function createLayout(string $id, string $en_link, string $de_link, bool $with_translation = TRUE): CohesionLayout {
    $layout = CohesionLayout::create([
      'id' => $id,
      'json_values' => $this->canvasJson($en_link),
      'parent_type' => 'component_content',
      'parent_field_name' => 'field_layout_canvas',
    ]);
    $layout->callApi = FALSE;
    if ($with_translation) {
      $translation = $layout->addTranslation('de', ['json_values' => $this->canvasJson($de_link)]);
      $translation->callApi = FALSE;
    }
    $layout->save();

    // Reload so translation objects start from the stored state.
    $layout = CohesionLayout::load($id);
    $layout->callApi = FALSE;
    return $layout;
  }

  /**
   * Builds a CDF as Content Hub would for a layout.
   */
  protected function buildCdf(): CDFObject {
    $timestamp = date('c');
    $cdf = new CDFObject(
      'drupal8_content_entity',
      'layout-uuid',
      $timestamp,
      $timestamp,
      'origin',
      [
        'languages' => [
          'en',
          'de'
        ]
      ]
    );
    $cdf->addAttribute('entity_type', CDFAttribute::TYPE_STRING, 'cohesion_layout');
    return $cdf;
  }

  /**
   * Links of a layout in the given language, keyed by their entity ID.
   */
  protected function linkId(CohesionLayout $layout, string $langcode): string {
    $links = $layout->getTranslation($langcode)->getLayoutCanvasInstance()->getLinksReferences();
    return $links[self::ELEMENT][0]['entity_id'];
  }

  /**
   * Tests each language resolves to its own subscriber-local node.
   */
  public function testTranslatedLinksSurviveRoundTrip() {
    // Publisher: node 64 (en) and node 99 (de).
    Node::create(['title' => 'en', 'nid' => 64, 'uuid' => self::EN_UUID, 'type' => 'test'])->save();
    Node::create(['title' => 'de', 'nid' => 99, 'uuid' => self::DE_UUID, 'type' => 'test'])->save();
    $publisher_layout = $this->createLayout('201', 'node::64', 'node::99');

    $cdf = $this->buildCdf();
    $dispatcher = \Drupal::service('event_dispatcher');
    $serialize = new SerializeAdditionalMetadataEvent($publisher_layout, $cdf);
    $dispatcher->dispatch($serialize, AcquiaContentHubEvents::SERIALIZE_ADDITIONAL_METADATA);
    $metadata = $serialize->getCdf()->getMetadata();

    $this->assertSame(
      self::EN_UUID,
      $metadata['additional_data']['processed_links'][self::ELEMENT][0]['entity_id']
    );
    $this->assertSame(
      self::DE_UUID,
      $metadata['additional_data']['processed_links_translations']['de'][self::ELEMENT][0]['entity_id']
    );

    // Subscriber: same content, different local IDs.
    Node::load(64)->delete();
    Node::load(99)->delete();
    Node::create(['title' => 'en', 'nid' => 640, 'uuid' => self::EN_UUID, 'type' => 'test'])->save();
    Node::create(['title' => 'de', 'nid' => 990, 'uuid' => self::DE_UUID, 'type' => 'test'])->save();
    // Content Hub populates translations before dispatching the event.
    $subscriber_layout = $this->createLayout('202', 'node::64', 'node::64');

    $unserialize = new UnserializeAdditionalMetadataEvent($subscriber_layout, $serialize->getCdf());
    $dispatcher->dispatch($unserialize, AcquiaContentHubEvents::UNSERIALIZE_ADDITIONAL_METADATA);

    $imported = $unserialize->getEntity();
    $this->assertSame('640', $this->linkId($imported, 'en'));
    $this->assertSame('990', $this->linkId($imported, 'de'));
  }

  /**
   * Tests a link target missing on the subscriber is left as it was.
   */
  public function testMissingTargetIsNotWrittenAsUuid() {
    Node::create(['title' => 'en', 'nid' => 64, 'uuid' => self::EN_UUID, 'type' => 'test'])->save();
    Node::create(['title' => 'de', 'nid' => 99, 'uuid' => self::DE_UUID, 'type' => 'test'])->save();
    $publisher_layout = $this->createLayout('203', 'node::64', 'node::99');

    $cdf = $this->buildCdf();
    $dispatcher = \Drupal::service('event_dispatcher');
    $serialize = new SerializeAdditionalMetadataEvent($publisher_layout, $cdf);
    $dispatcher->dispatch($serialize, AcquiaContentHubEvents::SERIALIZE_ADDITIONAL_METADATA);

    // The German target is never syndicated to the subscriber.
    Node::load(99)->delete();
    $subscriber_layout = $this->createLayout('204', 'node::64', 'node::5');
    $unserialize = new UnserializeAdditionalMetadataEvent($subscriber_layout, $serialize->getCdf());
    $dispatcher->dispatch($unserialize, AcquiaContentHubEvents::UNSERIALIZE_ADDITIONAL_METADATA);

    $imported = $unserialize->getEntity();
    $this->assertSame('64', $this->linkId($imported, 'en'));
    // Untouched: neither the publisher's German ID (99) nor a UUID.
    $this->assertSame('5', $this->linkId($imported, 'de'));
    $this->assertStringNotContainsString(self::DE_UUID, $imported->getTranslation('de')->getJsonValues());
  }

  /**
   * Tests a translation absent on the subscriber is skipped, not created.
   */
  public function testMissingTranslationIsSkipped() {
    Node::create(['title' => 'en', 'nid' => 64, 'uuid' => self::EN_UUID, 'type' => 'test'])->save();
    Node::create(['title' => 'de', 'nid' => 99, 'uuid' => self::DE_UUID, 'type' => 'test'])->save();
    $publisher_layout = $this->createLayout('205', 'node::64', 'node::99');

    $cdf = $this->buildCdf();
    $dispatcher = \Drupal::service('event_dispatcher');
    $serialize = new SerializeAdditionalMetadataEvent($publisher_layout, $cdf);
    $dispatcher->dispatch($serialize, AcquiaContentHubEvents::SERIALIZE_ADDITIONAL_METADATA);

    $subscriber_layout = $this->createLayout('206', 'node::64', 'node::64', FALSE);
    $unserialize = new UnserializeAdditionalMetadataEvent($subscriber_layout, $serialize->getCdf());
    $dispatcher->dispatch($unserialize, AcquiaContentHubEvents::UNSERIALIZE_ADDITIONAL_METADATA);

    $imported = $unserialize->getEntity();
    $this->assertFalse($imported->hasTranslation('de'));
    $this->assertSame('64', $this->linkId($imported, 'en'));
  }

}
