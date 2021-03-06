<?php

namespace Drupal\Tests\field_collection\Functional;

use Drupal\field_collection\Entity\FieldCollection;
use Drupal\field_collection\Entity\FieldCollectionItem;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

// TODO: Test field collections with no fields or with no data in their fields
//       once it's determined what is a good behavior for that situation.
//       Unless something is changed the Entity and the field entry for it
//       won't get created unless some data exists in it.

/**
 * Test basics.
 *
 * @group field_collection
 */
class FieldCollectionBasicTestCase extends BrowserTestBase {
  use FieldCollectionTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['field_collection', 'node', 'field', 'field_ui'];

  /**
   * Sets up the data structures for the tests.
   */
  public function setUp() {
    parent::setUp();
    $this->setUpFieldCollectionTest();
  }

  /**
   * Tests CRUD.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function testCRUD() {
    /** @var \Drupal\node\NodeInterface $node */
    /** @var \Drupal\field_collection\FieldCollectionItemInterface $field_collection_item */
    list ($node, $field_collection_item) = $this->createNodeWithFieldCollection('article');

    $this->assertEquals($node->{$this->field_collection_name}->value, $field_collection_item->id(), 'A field_collection_item has been successfully created and referenced.');

    $this->assertEquals($node->{$this->field_collection_name}->revision_id, $field_collection_item->revision_id->value, 'The new field_collection_item has the correct revision.');

    // Test adding an additional field_collection_item.
    $field_collection_item_2 = FieldCollectionItem::create(['field_name' => $this->field_collection_name]);

    $field_collection_item_2->{$this->inner_field_name}->setValue(2);

    $node->{$this->field_collection_name}[1] = ['field_collection_item' => $field_collection_item_2];

    $node->save();
    $this->nodeStorage->resetCache([$node->id()]);
    $node = Node::load($node->id());

    $this->assertTrue(!empty($field_collection_item_2->id()) && !empty($field_collection_item_2->getRevisionId()), 'Another field_collection_item has been saved.');

    $this->assertEquals(2, count(FieldCollectionItem::loadMultiple()), 'Field_collection_items have been stored.');

    $this->assertEquals($node->{$this->field_collection_name}->value, $field_collection_item->id(), 'Existing reference has been kept during update.');

    $this->assertEquals($node->{$this->field_collection_name}[0]->revision_id, $field_collection_item->getRevisionId(), 'Revision: Existing reference has been kept during update.');

    $this->assertEquals($node->{$this->field_collection_name}[1]->value, $field_collection_item_2->id(), 'New field_collection_item has been properly referenced.');

    $this->assertEquals($node->{$this->field_collection_name}[1]->revision_id, $field_collection_item_2->getRevisionId(), 'Revision: New field_collection_item has been properly referenced.');

    // Make sure deleting the field collection item removes the reference.
    $field_collection_item_2->delete();
    $this->nodeStorage->resetCache([$node->id()]);
    $node = Node::load($node->id());

    $this->assertTrue(!isset($node->{$this->field_collection_name}[1]), 'Reference correctly deleted.');

    // Make sure field_collections are removed during deletion of the host.
    $node->delete();

    $this->assertSame([], FieldCollectionItem::loadMultiple(), 'field_collection_item deleted when the host is deleted.');

    // Try deleting nodes with collections without any values.
    $node = $this->drupalCreateNode(['type' => 'article']);
    $node->delete();

    $this->nodeStorage->resetCache([$node->id()]);
    $node = Node::load($node->id());
    $this->assertFalse($node);

    // Test creating a field collection entity with a not-yet saved host entity.
    $node = $this->drupalCreateNode(['type' => 'article']);

    $field_collection_item = FieldCollectionItem::create(['field_name' => $this->field_collection_name]);

    $field_collection_item->{$this->inner_field_name}->setValue(3);
    $field_collection_item->setHostEntity($node);
    $field_collection_item->save();

    // Now the node should have been saved with the collection and the link
    // should have been established.
    $this->assertTrue(!empty($node->id()), 'Node has been saved with the collection.');

    $this->assertTrue(count($node->{$this->field_collection_name}) == 1 && !empty($node->{$this->field_collection_name}[0]->value) && !empty($node->{$this->field_collection_name}[0]->revision_id), 'Link has been established.');

    // Again, test creating a field collection with a not-yet saved host entity,
    // but this time save both entities via the host.
    $node = $this->drupalCreateNode(['type' => 'article']);

    $field_collection_item = FieldCollectionItem::create(['field_name' => $this->field_collection_name]);

    $field_collection_item->{$this->inner_field_name}->setValue(4);
    $field_collection_item->setHostEntity($node);
    $node->save();

    $this->assertTrue(!empty($field_collection_item->id()) && !empty($field_collection_item->getRevisionId()), 'Removed field collection item still exists.');

    $this->assertTrue(count($node->{$this->field_collection_name}) == 1 && !empty($node->{$this->field_collection_name}[0]->value) && !empty($node->{$this->field_collection_name}[0]->revision_id), 'Removed field collection item is archived.');

  }

  /**
   * Test deleting the field corresponding to a field collection.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFieldDeletion() {
    // Create a separate content type with the field collection field.
    $this->drupalCreateContentType(['type' => 'test_content_type', 'name' => 'Test content type']);

    $field_collection_field_1 = $this->field_collection_field;

    $field_collection_field_2 = $this->addFieldCollectionFieldToContentType('test_content_type');

    list(, $field_collection_item_1) = $this->createNodeWithFieldCollection('article');

    list(, $field_collection_item_2) = $this->createNodeWithFieldCollection('test_content_type');

    /** @var \Drupal\field_collection\FieldCollectionItemInterface $field_collection_item_1 */
    $field_collection_item_id_1 = $field_collection_item_1->id();
    /** @var \Drupal\field_collection\FieldCollectionItemInterface $field_collection_item_2 */
    $field_collection_item_id_2 = $field_collection_item_2->id();

    $field_collection_field_1->delete();
    field_purge_batch(100);

    $this->assertNull(FieldCollectionItem::load($field_collection_item_id_1), 'field_collection_item deleted with the field_collection field.');

    $this->assertNotNull(FieldCollectionItem::load($field_collection_item_id_2), 'Other field_collection_item still exists.');

    $this->assertNotNull(FieldCollection::load($this->field_collection_name), 'field_collection config entity still exists.');

    $field_collection_field_2->delete();
    field_purge_batch(100);

    $this->assertNull(FieldCollectionItem::load($field_collection_item_id_2), 'Other field_collection_item deleted with it\'s field.');

    $this->assertNull(FieldCollection::load($this->field_collection_name), 'field_collection config entity deleted.');
  }

  /**
   * Make sure the basic UI and access checks are working.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBasicUI() {
    $node = $this->drupalCreateNode(['type' => 'article']);

    // Login with new user that has no privileges.
    $user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($user);

    // Make sure access is denied.
    $path = "field_collection_item/add/field_test_collection/node/{$node->id()}";

    $this->drupalGet($path);
    $this->assertSession()->pageTextContains(t('Access denied'), 'Access has been denied.');

    // Login with new user that has basic edit rights.
    $user_privileged = $this->drupalCreateUser([
      'access content',
      'edit any article content',
    ]);

    $this->drupalLogin($user_privileged);

    // Test field collection item add form.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->drupalGet("node/{$node->id()}");
    $this->assertSession()->linkByHrefExists($path, 0, 'Add link is shown.');
    $this->drupalGet($path);

    $this->assertSession()->pageTextContains(t($this->inner_field_definition['label']));
    $edit = ["$this->inner_field_name[0][value]" => rand()];
    $this->submitForm($edit, t('Save'));

    $this->assertSession()->pageTextContains(t('Successfully added a @field.', ['@field' => $this->field_collection_name]));

    $this->assertSession()->pageTextContains($edit["$this->inner_field_name[0][value]"]);

    $field_collection_item = FieldCollectionItem::load(1);

    // Test field collection item edit form.
    $edit["$this->inner_field_name[0][value]"] = rand();
    $this->submitForm($edit, t('Save'));

    $this->assertSession()->pageTextContains(t('Successfully edited @field.', ['@field' => $field_collection_item->label()]));

    $this->assertSession()->pageTextContains($edit["$this->inner_field_name[0][value]"]);

    $this->drupalGet('field_collection_item/1');

    $this->assertSession()->pageTextContains($edit["$this->inner_field_name[0][value]"]);
  }

}
