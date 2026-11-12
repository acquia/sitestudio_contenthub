<?php

namespace Drupal\Tests\sitestudio_contenthub_subscriber\Unit\EventSubscriber\Cdf;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent;
use Drupal\cohesion\LayoutCanvas\LayoutCanvas;
use Drupal\sitestudio_contenthub_subscriber\EventSubscriber\Cdf\CohesionLayoutUnserializationHandler;
use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for handling CohesionLayout entities serialization.
 *
 * @group Cohesion
 *
 * @package Drupal\Tests\sitestudio_contenthub_subscriber\Unit\EventSubscriber\Cdf
 *
 * @covers \Drupal\sitestudio_contenthub_subscriber\EventSubscriber\Cdf\CohesionLayoutUnserializationHandler::CohesionLayoutUnserializationHandler
 */
class CohesionLayoutUnserializationHandlerTest extends UnitTestCase {

  const NODE_IDS = [
    'a48e537a-6ec9-4191-b518-ed5e6d559a12' => '30',
    'f8c3512b-aa9e-4008-8bf3-c03193ca3afa' => '40',
    'ae85e706-809b-4c6f-9ef5-0a0f6779d6c8' => '560',
    'dd871855-28df-475a-9d93-6cb4cba62d27' => '640',
  ];
  const LAYOUT_CANVAS_UUID = '3a68e76a-d224-4fff-9737-a13cfa481165';
  const ENTITY_TYPE = 'drupal8_content_entity';
  const JSON_VALUES = '{"canvas":[{"uid":"cpt_test_component","type":"component","title":"Test Component","enabled":true,"category":"category-10","componentId":"cpt_test_component","componentType":"link","uuid":"d23a59d5-c8d7-49b8-9cda-61fa01aadf55","parentUid":"root","children":[]},{"uid":"cpt_test_component","type":"component","title":"Test Component","enabled":true,"category":"category-10","componentId":"cpt_test_component","componentType":"link","uuid":"19c96fe4-db43-4f2f-8c91-8081b7a948fb","parentUid":"root","children":[]},{"uid":"cpt_test_component","type":"component","title":"Test Component","enabled":true,"category":"category-10","componentId":"cpt_test_component","componentType":"link","uuid":"ebafa6a9-b0b8-49cb-a481-d8c123c52e1c","parentUid":"root","children":[]},{"uid":"cpt_links_pattern_repeater","type":"component","title":"Links pattern repeater","enabled":true,"category":"category-4","componentId":"cpt_links_pattern_repeater","componentType":"component-pattern-repeater","uuid":"e7d1a1ac-abef-4971-9273-9d25bcc8db79","parentUid":"root","children":[]}],"mapper":{},"model":{"d23a59d5-c8d7-49b8-9cda-61fa01aadf55":{"settings":{"title":"Test Component"},"4793e582-fe13-490c-99d2-badcce843df7":"node::64"},"19c96fe4-db43-4f2f-8c91-8081b7a948fb":{"settings":{"title":"Test Component"},"4793e582-fe13-490c-99d2-badcce843df7":"node::56"},"ebafa6a9-b0b8-49cb-a481-d8c123c52e1c":{"settings":{"title":"Test Component"},"4793e582-fe13-490c-99d2-badcce843df7":"https:\/\/www.drupal.org\/"},"e7d1a1ac-abef-4971-9273-9d25bcc8db79":{"settings":{"title":"Links pattern repeater"},"d1653d6c-68ef-4b15-aabb-fdf3daf5064e":[{"f1a9f274-f030-47d5-a831-79a23da8a59e":"External","abdc6059-1b0d-498e-ac6a-daa58cbb85fb":"https:\/\/www.drupal.org\/"},{"f1a9f274-f030-47d5-a831-79a23da8a59e":"Node 1","abdc6059-1b0d-498e-ac6a-daa58cbb85fb":"node::4"},{"f1a9f274-f030-47d5-a831-79a23da8a59e":"Node 2","abdc6059-1b0d-498e-ac6a-daa58cbb85fb":"node::3"}]}},"previewModel":{"d23a59d5-c8d7-49b8-9cda-61fa01aadf55":{},"19c96fe4-db43-4f2f-8c91-8081b7a948fb":{},"ebafa6a9-b0b8-49cb-a481-d8c123c52e1c":{},"e7d1a1ac-abef-4971-9273-9d25bcc8db79":{}},"variableFields":{"d23a59d5-c8d7-49b8-9cda-61fa01aadf55":[],"19c96fe4-db43-4f2f-8c91-8081b7a948fb":[],"ebafa6a9-b0b8-49cb-a481-d8c123c52e1c":[],"e7d1a1ac-abef-4971-9273-9d25bcc8db79":[]},"meta":{"fieldHistory":[]}}';
  const LAYOUT_CANVAS_METADATA = [
    "metadata" => [
      "default_language" => "en",
      "dependencies" => [
        "module" => [
          "cohesion",
        ],
      ],
      "field" => [
        "uuid" => [
          "type" => "uuid",
        ],
        "type" => [
          "type" => "entity_reference",
          "target" => "node_type",
        ],
        "parent_id" => [
          "type" => "string",
        ],
        "parent_type" => [
          "type" => "string",
        ],
        "parent_field_name" => [
          "type" => "string",
        ],
        "last_entity_update" => [
          "type" => "string",
        ],
        "langcode" => [
          "type" => "language",
        ],
        "template" => [
          "type" => "string_long",
        ],
        "json_values" => [
          "type" => "string_long",
        ],
        "styles" => [
          "type" => "string_long",
        ],
        "default_langcode" => [
          "type" => "boolean",
        ],
        "revision_default" => [
          "type" => "boolean",
        ],
        "revision_translation_affected" => [
          "type" => "boolean",
        ],
      ],
      "data" => "eyJ1dWlkIjp7InZhbHVlIjp7ImVuIjp7InZhbHVlIjoiMjJkNWU5NDYtYTIxNC00MzcxLTlmNDUtNTI5YmRiNzY1OTg3In19fSwianNvbl92YWx1ZXMiOnsidmFsdWUiOnsiZW4iOnsidmFsdWUiOiJ7XCJjYW52YXNcIjpbe1widWlkXCI6XCJjcHRfdGVzdF9jb21wb25lbnRcIixcInR5cGVcIjpcImNvbXBvbmVudFwiLFwidGl0bGVcIjpcIlRlc3QgQ29tcG9uZW50XCIsXCJlbmFibGVkXCI6dHJ1ZSxcImNhdGVnb3J5XCI6XCJjYXRlZ29yeS0xMFwiLFwiY29tcG9uZW50SWRcIjpcImNwdF90ZXN0X2NvbXBvbmVudFwiLFwiY29tcG9uZW50VHlwZVwiOlwibGlua1wiLFwidXVpZFwiOlwiZDIzYTU5ZDUtYzhkNy00OWI4LTljZGEtNjFmYTAxYWFkZjU1XCIsXCJwYXJlbnRVaWRcIjpcInJvb3RcIixcImlzQ29udGFpbmVyXCI6MCxcImNoaWxkcmVuXCI6W119XSxcIm1hcHBlclwiOnt9LFwibW9kZWxcIjp7XCJkMjNhNTlkNS1jOGQ3LTQ5YjgtOWNkYS02MWZhMDFhYWRmNTVcIjp7XCJzZXR0aW5nc1wiOntcInRpdGxlXCI6XCJUZXN0IENvbXBvbmVudFwifSxcIjQ3OTNlNTgyLWZlMTMtNDkwYy05OWQyLWJhZGNjZTg0M2RmN1wiOlwibm9kZTo6NjRcIn19LFwicHJldmlld01vZGVsXCI6e1wiZDIzYTU5ZDUtYzhkNy00OWI4LTljZGEtNjFmYTAxYWFkZjU1XCI6e319LFwidmFyaWFibGVGaWVsZHNcIjp7XCJkMjNhNTlkNS1jOGQ3LTQ5YjgtOWNkYS02MWZhMDFhYWRmNTVcIjpbXX0sXCJtZXRhXCI6e1wiZmllbGRIaXN0b3J5XCI6W119fSJ9fX0sInN0eWxlcyI6eyJ2YWx1ZSI6eyJlbiI6eyJ2YWx1ZSI6IltdIn19fSwidGVtcGxhdGUiOnsidmFsdWUiOnsiZW4iOnsidmFsdWUiOiJ7XCJjb2hlc2lvbl90aGVtZVwiOlwie1xcdTAwMjJtZXRhZGF0YVxcdTAwMjI6e1xcdTAwMjJjb250ZXh0c1xcdTAwMjI6W119LFxcdTAwMjJ0d2lnXFx1MDAyMjpcXHUwMDIye3sgYXR0YWNoX2xpYnJhcnkoXFx1MDAyN2NvaGVzaW9uXFxcXFxcXC9nbG9iYWxfbGlicmFyaWVzLnJlc3BvbnNpdmVKc1xcdTAwMjcpIH19IHt7IGF0dGFjaF9saWJyYXJ5KFxcdTAwMjdjb2hlc2lvblxcXFxcXFwvZ2xvYmFsX2xpYnJhcmllcy5tYXRjaEhlaWdodFxcdTAwMjcpIH19IHt7IGF0dGFjaF9saWJyYXJ5KFxcdTAwMjdjb2hlc2lvblxcXFxcXFwvZ2xvYmFsX2xpYnJhcmllcy5jb2hNYXRjaEhlaWdodHNcXHUwMDI3KSB9fSB7eyBhdHRhY2hfbGlicmFyeShcXHUwMDI3Y29oZXNpb25cXFxcXFxcL2dsb2JhbF9saWJyYXJpZXMud2luZG93c2Nyb2xsXFx1MDAyNykgfX0ge3sgYXR0YWNoX2xpYnJhcnkoXFx1MDAyN2NvaGVzaW9uXFxcXFxcXC9lbGVtZW50X3RlbXBsYXRlcy5saW5rXFx1MDAyNykgfX0ge3sgYXR0YWNoX2xpYnJhcnkoXFx1MDAyN2NvaGVzaW9uXFxcXFxcXC9nbG9iYWxfbGlicmFyaWVzLnBhcmFsbGF4X3Njcm9sbGluZ1xcdTAwMjcpIH19IHslIHNldCBjb21wb25lbnRfdmFyaWFibGVfNDc5M2U1ODJfZmUxM180OTBjXzk5ZDJfYmFkY2NlODQzZGY3ICV9bm9kZTo6NjR7JSBlbmRzZXQgJX0ge3sgcmVuZGVyQ29tcG9uZW50KFxcXFxcXHUwMDIyY3B0X3Rlc3RfY29tcG9uZW50XFxcXFxcdTAwMjIsIGZhbHNlLCBfY29udGV4dCwge1xcXFxcXHUwMDIyNDc5M2U1ODItZmUxMy00OTBjLTk5ZDItYmFkY2NlODQzZGY3XFxcXFxcdTAwMjI6XFxcXFxcdTAwMjJjb21wb25lbnRfdmFyaWFibGVfNDc5M2U1ODJfZmUxM180OTBjXzk5ZDJfYmFkY2NlODQzZGY3XFxcXFxcdTAwMjJ9LCBcXFxcXFx1MDAyMmQyM2E1OWQ1LWM4ZDctNDliOC05Y2RhLTYxZmEwMWFhZGY1NVxcXFxcXHUwMDIyLCBcXFxcXFx1MDAyMlxcXFxcXHUwMDIyKSAgfX0gXFxcXG57JSBpZiBjb250ZW50IGlzIGRlZmluZWQgJX17JSBzZXQgY2F0Y2hfY2FjaGUgPSBjb250ZW50fHJlbmRlciAlfXslIGVuZGlmICV9XFx1MDAyMn1cIn0ifX19LCJwYXJlbnRfaWQiOnsidmFsdWUiOnsiZW4iOiI2NSJ9fSwicGFyZW50X3R5cGUiOnsidmFsdWUiOnsiZW4iOiJub2RlIn19LCJwYXJlbnRfZmllbGRfbmFtZSI6eyJ2YWx1ZSI6eyJlbiI6ImZpZWxkX2xheW91dF9jYW52YXMifX0sImxhc3RfZW50aXR5X3VwZGF0ZSI6eyJ2YWx1ZSI6eyJlbiI6ImVudGl0eXVwZGF0ZV8wMDMxIn19LCJkZWZhdWx0X2xhbmdjb2RlIjp7InZhbHVlIjp7ImVuIjoiMSJ9fSwicmV2aXNpb25fZGVmYXVsdCI6eyJ2YWx1ZSI6eyJlbiI6IjEifX0sInJldmlzaW9uX3RyYW5zbGF0aW9uX2FmZmVjdGVkIjp7InZhbHVlIjp7ImVuIjoiMSJ9fSwic2NoZWR1bGVkX3RyYW5zaXRpb25fZGF0ZSI6eyJ2YWx1ZSI6eyJlbiI6W119fSwic2NoZWR1bGVkX3RyYW5zaXRpb25fc3RhdGUiOnsidmFsdWUiOnsiZW4iOltdfX19",
      "languages" => [
        "en",
      ],
      "version" => 2,
    ],
    "additional_data" => [
      "processed_links" => [
        "d23a59d5-c8d7-49b8-9cda-61fa01aadf55" => [
          [
            "entity_type" => "node",
            "entity_id" => "dd871855-28df-475a-9d93-6cb4cba62d27",
            "path" => [
              "4793e582-fe13-490c-99d2-badcce843df7",
            ],
          ],
        ],
        "19c96fe4-db43-4f2f-8c91-8081b7a948fb" => [
          [
            "entity_type" => "node",
            "entity_id" => "ae85e706-809b-4c6f-9ef5-0a0f6779d6c8",
            "path" => [
              "4793e582-fe13-490c-99d2-badcce843df7",
            ],
          ],
        ],
        "e7d1a1ac-abef-4971-9273-9d25bcc8db79" => [
          [
            "entity_type" => "node",
            "entity_id" => "f8c3512b-aa9e-4008-8bf3-c03193ca3afa",
            "path" => [
              "d1653d6c-68ef-4b15-aabb-fdf3daf5064e",
              1,
              "abdc6059-1b0d-498e-ac6a-daa58cbb85fb",
            ],
          ],
          [
            "entity_type" => "node",
            "entity_id" => "a48e537a-6ec9-4191-b518-ed5e6d559a12",
            "path" => [
              "d1653d6c-68ef-4b15-aabb-fdf3daf5064e",
              2,
              "abdc6059-1b0d-498e-ac6a-daa58cbb85fb",
            ],
          ],
        ],
      ],
    ],
  ];
  const LINKS_METADATA = [
    "d23a59d5-c8d7-49b8-9cda-61fa01aadf55" => [
      [
        "entity_type" => "node",
        "entity_id" => "dd871855-28df-475a-9d93-6cb4cba62d27",
        "path" => [
          "4793e582-fe13-490c-99d2-badcce843df7",
        ],
      ],
    ],
    "19c96fe4-db43-4f2f-8c91-8081b7a948fb" => [
      [
        "entity_type" => "node",
        "entity_id" => "ae85e706-809b-4c6f-9ef5-0a0f6779d6c8",
        "path" => [
          "4793e582-fe13-490c-99d2-badcce843df7",
        ],
      ],
    ],
    "e7d1a1ac-abef-4971-9273-9d25bcc8db79" => [
      [
        "entity_type" => "node",
        "entity_id" => "f8c3512b-aa9e-4008-8bf3-c03193ca3afa",
        "path" => [
          "d1653d6c-68ef-4b15-aabb-fdf3daf5064e",
          1,
          "abdc6059-1b0d-498e-ac6a-daa58cbb85fb",
        ],
      ],
      [
        "entity_type" => "node",
        "entity_id" => "a48e537a-6ec9-4191-b518-ed5e6d559a12",
        "path" => [
          "d1653d6c-68ef-4b15-aabb-fdf3daf5064e",
          2,
          "abdc6059-1b0d-498e-ac6a-daa58cbb85fb",
        ],
      ],
    ],
  ];
  const LINKS = [
    "d23a59d5-c8d7-49b8-9cda-61fa01aadf55" => [
      [
        "entity_type" => "node",
        "entity_id" => "64",
        "path" => [
          "4793e582-fe13-490c-99d2-badcce843df7",
        ],
      ],
    ],
    "19c96fe4-db43-4f2f-8c91-8081b7a948fb" => [
      [
        "entity_type" => "node",
        "entity_id" => "56",
        "path" => [
          "4793e582-fe13-490c-99d2-badcce843df7",
        ],
      ],
    ],
    "e7d1a1ac-abef-4971-9273-9d25bcc8db79" => [
      [
        "entity_type" => "node",
        "entity_id" => "4",
        "path" => [
          "d1653d6c-68ef-4b15-aabb-fdf3daf5064e",
          1,
          "abdc6059-1b0d-498e-ac6a-daa58cbb85fb",
        ],
      ],
      [
        "entity_type" => "node",
        "entity_id" => "3",
        "path" => [
          "d1653d6c-68ef-4b15-aabb-fdf3daf5064e",
          2,
          "abdc6059-1b0d-498e-ac6a-daa58cbb85fb",
        ],
      ],
    ],
  ];
  const LINKS_AFTER_UNSERIALIZATION = [
    "d23a59d5-c8d7-49b8-9cda-61fa01aadf55" => [
      [
        "entity_type" => "node",
        "entity_id" => "640",
        "path" => [
          "4793e582-fe13-490c-99d2-badcce843df7",
        ],
      ],
    ],
    "19c96fe4-db43-4f2f-8c91-8081b7a948fb" => [
      [
        "entity_type" => "node",
        "entity_id" => "560",
        "path" => [
          "4793e582-fe13-490c-99d2-badcce843df7",
        ],
      ],
    ],
    "e7d1a1ac-abef-4971-9273-9d25bcc8db79" => [
      [
        "entity_type" => "node",
        "entity_id" => "40",
        "path" => [
          "d1653d6c-68ef-4b15-aabb-fdf3daf5064e",
          1,
          "abdc6059-1b0d-498e-ac6a-daa58cbb85fb",
        ],
      ],
      [
        "entity_type" => "node",
        "entity_id" => "30",
        "path" => [
          "d1653d6c-68ef-4b15-aabb-fdf3daf5064e",
          2,
          "abdc6059-1b0d-498e-ac6a-daa58cbb85fb",
        ],
      ],
    ],
  ];

  /**
   * EntityTypeManager Mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * CohesionLayout Mock.
   *
   * @var \Drupal\cohesion_elements\Entity\CohesionLayout|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cohesion_layout;

  /**
   * Logger factory mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * CDFObject.
   *
   * @var \Acquia\ContentHubClient\CDF\CDFObject
   */
  protected $cdf;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->loggerFactory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));

    $cohesion_layout = $this->getMockBuilder(CohesionLayout::class)
      ->disableOriginalConstructor()
      ->getMock();
    $cohesion_layout->method('getLayoutCanvasInstance')
      ->willReturn(new LayoutCanvas(self::JSON_VALUES));
    $cohesion_layout->method('isDefaultTranslation')->willReturn(TRUE);
    $this->cohesion_layout = $cohesion_layout;


    $entityStorage = $this->getMockBuilder(EntityStorageInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entityStorage->expects($this->any())
      ->method('loadByProperties')
      ->willReturnCallback(function ($argument) {
        $nodeMock = $this->getMockBuilder(NodeInterface::class)
          ->disableOriginalConstructor()
          ->getMock();
        $nodeMock->expects($this->any())
          ->method('id')
          ->will($this->returnValue(self::NODE_IDS[$argument['uuid']]));

        return [$nodeMock];
      });

    $entityTypeManager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entityTypeManager->expects($this->any())
      ->method('hasDefinition')
      ->willReturnCallback(function ($entity_type_id) {
        return $entity_type_id === 'node';
      });
    $entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($entityStorage);
    $this->entityTypeManager = $entityTypeManager;

    $timestamp = date('c');
    $this->cdf = new CDFObject(
      self::ENTITY_TYPE,
      self::LAYOUT_CANVAS_UUID,
      $timestamp,
      $timestamp,
      self::LAYOUT_CANVAS_UUID,
      self::LAYOUT_CANVAS_METADATA
    );
  }

  /**
   * Tests links unserialization on import of CohesionLayout field.
   */
  public function testOnUnserializeCohesionLayout() {

    $subscriber = new CohesionLayoutUnserializationHandler($this->entityTypeManager, $this->loggerFactory);
    $event = new UnserializeAdditionalMetadataEvent($this->cohesion_layout, $this->cdf);
    $metadata = $event->getCdf()->getMetadata();

    $this->assertTrue(isset($metadata['additional_data']['processed_links']));
    $this->assertEquals(
      self::LINKS_METADATA,
      $metadata['additional_data']['processed_links']
    );

    $this->assertEquals(
      self::LINKS,
      $this->cohesion_layout->getLayoutCanvasInstance()->getLinksReferences()
    );

    $subscriber->onUnserializeCohesionLayout($event);
    /** @var \Drupal\cohesion_elements\Entity\CohesionLayout $entity */
    $entity = $event->getEntity();
    $layout_canvas = $entity->getLayoutCanvasInstance();
    $this->assertEquals(
      self::LINKS_AFTER_UNSERIALIZATION,
      $layout_canvas->getLinksReferences()
    );

  }

  /**
   * Builds a CDF carrying only translation links.
   */
  protected function buildTranslationsCdf(array $translations, bool $withDefault = FALSE): CDFObject {
    $metadata = self::LAYOUT_CANVAS_METADATA;
    if (!$withDefault) {
      unset($metadata['additional_data']['processed_links']);
    }
    $metadata['additional_data']['processed_links_translations'] = $translations;
    $timestamp = date('c');
    return new CDFObject(
      self::ENTITY_TYPE,
      self::LAYOUT_CANVAS_UUID,
      $timestamp,
      $timestamp,
      self::LAYOUT_CANVAS_UUID,
      $metadata
    );
  }

  /**
   * Builds a layout mock exposing the given translations.
   *
   * @param \PHPUnit\Framework\MockObject\MockObject[] $translations
   *   Translation mocks keyed by langcode.
   */
  protected function buildLayoutWithTranslations(array $translations): CohesionLayout {
    $layout = $this->getMockBuilder(CohesionLayout::class)
      ->disableOriginalConstructor()
      ->getMock();
    $layout->method('getLayoutCanvasInstance')
      ->willReturn(new LayoutCanvas(self::JSON_VALUES));
    $layout->method('isDefaultTranslation')->willReturn(TRUE);
    $layout->method('hasTranslation')
      ->willReturnCallback(fn ($langcode) => isset($translations[$langcode]));
    $layout->method('getTranslation')
      ->willReturnCallback(fn ($langcode) => $translations[$langcode]);
    return $layout;
  }

  /**
   * Builds a non-default translation mock.
   */
  protected function buildTranslation(): CohesionLayout {
    $translation = $this->getMockBuilder(CohesionLayout::class)
      ->disableOriginalConstructor()
      ->getMock();
    $translation->method('getLayoutCanvasInstance')
      ->willReturn(new LayoutCanvas(self::JSON_VALUES));
    $translation->method('isDefaultTranslation')->willReturn(FALSE);
    return $translation;
  }

  /**
   * Tests translation-only links are resolved and the default is untouched.
   */
  public function testOnUnserializeTranslationLinks() {
    $translation = $this->buildTranslation();
    $translation->expects($this->once())->method('process');
    $layout = $this->buildLayoutWithTranslations(['de' => $translation]);

    $subscriber = new CohesionLayoutUnserializationHandler($this->entityTypeManager, $this->loggerFactory);
    // The 'fr' payload has no matching translation and must be skipped.
    $event = new UnserializeAdditionalMetadataEvent($layout, $this->buildTranslationsCdf([
      'de' => self::LINKS_METADATA,
      'fr' => self::LINKS_METADATA,
    ]));
    $subscriber->onUnserializeCohesionLayout($event);

    $this->assertEquals(
      self::LINKS_AFTER_UNSERIALIZATION,
      $translation->getLayoutCanvasInstance()->getLinksReferences()
    );
    $this->assertEquals(
      self::LINKS,
      $layout->getLayoutCanvasInstance()->getLinksReferences()
    );
  }

  /**
   * Tests default and translated links resolve to distinct local targets.
   */
  public function testOnUnserializeDistinctTranslationTargets() {
    $de_links = self::LINKS_METADATA;
    // The translation links the first component to a different node.
    $first = 'd23a59d5-c8d7-49b8-9cda-61fa01aadf55';
    $de_links[$first][0]['entity_id'] = 'ae85e706-809b-4c6f-9ef5-0a0f6779d6c8';
    $expected_de = self::LINKS_AFTER_UNSERIALIZATION;
    $expected_de[$first][0]['entity_id'] = '560';

    $translation = $this->buildTranslation();
    $layout = $this->buildLayoutWithTranslations(['de' => $translation]);

    $subscriber = new CohesionLayoutUnserializationHandler($this->entityTypeManager, $this->loggerFactory);
    $event = new UnserializeAdditionalMetadataEvent($layout, $this->buildTranslationsCdf(['de' => $de_links], TRUE));
    $subscriber->onUnserializeCohesionLayout($event);

    $this->assertEquals(
      self::LINKS_AFTER_UNSERIALIZATION,
      $layout->getLayoutCanvasInstance()->getLinksReferences()
    );
    $this->assertEquals(
      $expected_de,
      $translation->getLayoutCanvasInstance()->getLinksReferences()
    );
  }

  /**
   * Tests the parent's callApi flag is honoured for translations.
   */
  public function testOnUnserializeTranslationHonoursParentCallApi() {
    $translation = $this->buildTranslation();
    $translation->expects($this->never())->method('process');
    $layout = $this->buildLayoutWithTranslations(['de' => $translation]);
    $layout->callApi = FALSE;

    $subscriber = new CohesionLayoutUnserializationHandler($this->entityTypeManager, $this->loggerFactory);
    $event = new UnserializeAdditionalMetadataEvent(
      $layout,
      $this->buildTranslationsCdf(
        [
          'de' => self::LINKS_METADATA
        ]
      )
    );
    $subscriber->onUnserializeCohesionLayout($event);

    $this->assertEquals(
      self::LINKS_AFTER_UNSERIALIZATION,
      $translation->getLayoutCanvasInstance()->getLinksReferences()
    );
  }

  /**
   * Tests links to an entity type missing on this site never reach the canvas.
   */
  public function testOnUnserializeDropsUnknownEntityType() {
    $unknown = self::LINKS_METADATA;
    $first = 'd23a59d5-c8d7-49b8-9cda-61fa01aadf55';
    $unknown[$first][0]['entity_type'] = 'media';

    $translation = $this->buildTranslation();
    $translation->method('language')->willReturn($this->createMock(LanguageInterface::class));
    $layout = $this->buildLayoutWithTranslations(['de' => $translation]);

    $subscriber = new CohesionLayoutUnserializationHandler($this->entityTypeManager, $this->loggerFactory);
    $event = new UnserializeAdditionalMetadataEvent($layout, $this->buildTranslationsCdf(['de' => $unknown]));
    $subscriber->onUnserializeCohesionLayout($event);

    // The unusable reference keeps its syndicated value, not "media::<uuid>".
    $expected = self::LINKS_AFTER_UNSERIALIZATION;
    $expected[$first][0]['entity_id'] = '64';
    $this->assertEquals($expected, $translation->getLayoutCanvasInstance()->getLinksReferences());
  }

  /**
   * Tests links to entities missing locally are left untouched.
   */
  public function testOnUnserializeSkipsUnresolvedLinks() {
    $missing = self::LINKS_METADATA;
    $first = 'd23a59d5-c8d7-49b8-9cda-61fa01aadf55';
    $missing[$first][0]['entity_id'] = '11111111-2222-4333-8444-555555555555';

    $translation = $this->buildTranslation();
    $translation->method('language')->willReturn($this->createMock(LanguageInterface::class));
    $layout = $this->buildLayoutWithTranslations(['de' => $translation]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturnCallback(function ($argument) {
      if (!isset(self::NODE_IDS[$argument['uuid']])) {
        return [];
      }
      $node = $this->createMock(NodeInterface::class);
      $node->method('id')->willReturn(self::NODE_IDS[$argument['uuid']]);
      return [$node];
    });
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->willReturn(TRUE);
    $manager->method('getStorage')->willReturn($storage);

    $subscriber = new CohesionLayoutUnserializationHandler($manager, $this->loggerFactory);
    $event = new UnserializeAdditionalMetadataEvent($layout, $this->buildTranslationsCdf(['de' => $missing]));
    $subscriber->onUnserializeCohesionLayout($event);

    // The unresolved link keeps its original ID, the others are resolved.
    $expected = self::LINKS_AFTER_UNSERIALIZATION;
    $expected[$first][0]['entity_id'] = '64';
    $this->assertEquals($expected, $translation->getLayoutCanvasInstance()->getLinksReferences());
  }

  /**
   * Tests translation recompilation respects callApi.
   */
  public function testOnUnserializeTranslationSkipsRecompileWhenApiDisabled() {
    $translation = $this->buildTranslation();
    $translation->callApi = FALSE;
    $translation->expects($this->never())->method('process');
    $layout = $this->buildLayoutWithTranslations(['de' => $translation]);

    $subscriber = new CohesionLayoutUnserializationHandler($this->entityTypeManager, $this->loggerFactory);
    $event = new UnserializeAdditionalMetadataEvent(
      $layout,
      $this->buildTranslationsCdf(
        [
          'de' => self::LINKS_METADATA
        ]
      )
    );
    $subscriber->onUnserializeCohesionLayout($event);

    $this->assertEquals(
      self::LINKS_AFTER_UNSERIALIZATION,
      $translation->getLayoutCanvasInstance()->getLinksReferences()
    );
  }

}
