<?php

namespace Drupal\field_collection;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Class FieldCollectionEmbedWidgetBuilder
 * @package Drupal\field_collection
 */
class FieldCollectionEmbedWidgetBuilder implements TrustedCallbackInterface
{
    /**
     * @return string[]
     */
    public static function trustedCallbacks()
    {
        return ['preRender'];
    }

    /**
     * @param $build
     * @return mixed
     */
    public static function preRender($build)
    {
        $build['#required'] = TRUE;
        return $build;
    }
}
