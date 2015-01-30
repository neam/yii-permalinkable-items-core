<?php

namespace neam\yii_permalinkable_items_core\traits;

/**
 * PermalinkableItemTrait
 */
trait PermalinkableItemTrait
{

    /**
     * Use in the item's relation() method to include the relevant relations
     */
    public function permalinkableItemRelations()
    {
        $behaviors = $this->behaviors();
        $config = $behaviors["PermalinkableItemBehavior"];
        if ($config["routeClass"] == "Route") {
            return array(
                'routes' => array(self::HAS_MANY, 'Route', array('id' => 'node_id'), 'through' => 'node'),
                'canonicalRoute' => array(self::HAS_ONE, 'Route', array('id' => 'node_id'), 'through' => 'node', 'on' => 'canonicalRoute.canonical = 1'),
            );
        }
        if ($config["routeClass"] == "FileRoute") {
            return array(
                'fileRoutes' => array(self::HAS_MANY, 'FileRoute', array('id' => 'node_id'), 'through' => 'node'),
                'canonicalFileRoutes' => array(self::HAS_MANY, 'FileRoute', array('id' => 'node_id'), 'through' => 'node', 'on' => 'canonicalFileRoute.canonical = 1'),
                'canonicalFileRoute' => array(self::HAS_ONE, 'FileRoute', array('id' => 'node_id'), 'through' => 'node', 'on' => 'canonicalFileRoute.canonical = 1', 'condition' => 'file_route_attribute_ref = :file_route_attribute_ref'),
            );
        }
        return array();
    }

}
