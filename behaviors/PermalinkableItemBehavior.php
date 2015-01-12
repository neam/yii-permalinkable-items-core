<?php

namespace neam\yii_permalinkable_items_core\behaviors;

use Exception;
use Route;
use RouteType;

/**
 * PermalinkableItemBehavior
 *
 * @uses CActiveRecordBehavior
 * @license BSD-3-Clause
 * @author See https://github.com/neam/yii-permalinkable-items-core/graphs/contributors
 */
class PermalinkableItemBehavior extends \CActiveRecordBehavior
{

    /**
     * @param CActiveRecord $owner
     * @throws Exception
     */
    public function attach($owner)
    {
        parent::attach($owner);
        if (!($owner instanceof \CActiveRecord)) {
            throw new Exception('Owner must be a CActiveRecord class');
        }
    }

    public function beforeSave($event)
    {

    }

    public function addTrailingSlashEquivalent($route)
    {
        $return = clone $route;
        $return->route .= "/";
        return $return;
    }

    /**
     * A. the node id of the first version of this item
     * B. the current semantic route based on current attribute values
     * C. upon publishing: previous versions' routes
     * D. translation routes
     * E. file routes
     * F. file translation routes
     */
    public function suggestedRoutes()
    {

        // A. the node id of the first version of this item

        $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::ITEM));

        $routes = array();

        $route = new Route;
        $route->route = "/{$this->owner->node()->id}";
        $route->route_type_id = $routeType->id;

        $routes[] = $route;
        $routes[] = $this->addTrailingSlashEquivalent($route);

        // B. the current semantic route based on current attribute values

        if (!empty($this->owner->slug_en)) {

            $route = new Route;

            // Use owner item's semanticRoute() to determine semanticRoute, fallback on this behavior's defaultSemanticRoute if not available
            //try {
            $route->route = $this->owner->semanticRoute();
            //} catch (\CException $e) {
            //    if (strpos($e->getMessage(), "and its behaviors do not have a method or closure named") !== false) {
            //        $route->route = $this->defaultSemanticRoute();
            //    } else {
            //        throw $e;
            //    }
            //}
            $route->route_type_id = $routeType->id;

            $routes[] = $route;
            $routes[] = $this->addTrailingSlashEquivalent($route);

        }

        return $routes;

    }

    public function defaultSemanticRoute()
    {
        if (empty($this->owner->slug_en)) {
            throw new \neam\yii_permalinkable_items_core\exceptions\SemanticRouteException("Item with node id $this->owner->node()->id has no english slug defined");
        }
        return "/{$this->owner->slug_en}";
    }

    public $suggestedUpdatesLog = null;

    /**
     * Takes into account existing routes
     */
    public function suggestedUpdatedRoutes()
    {
        $this->suggestedUpdatesLog = array();
        $currentRoutes = $this->owner->routes;
        $suggestedRoutes = $this->suggestedRoutes();
        $suggestedUpdatedRoutes = $currentRoutes;
        foreach ($suggestedRoutes as $suggestedRoute) {

            // If already exists, check if belongs to this or not
            $attributes = array(
                'route' => $suggestedRoute->route,
                'route_type_id' => $suggestedRoute->route_type_id
            );
            $existingRoute = Route::model()->findByAttributes($attributes);
            if (!empty($existingRoute)) {
                if ($existingRoute->node_id == $this->owner->node_id) {
                    // already belongs to current item - do nothing
                } else {
                    // belongs to another item - add to current item instead
                    $this->suggestedUpdatesLog[] = "Route '$existingRoute->route' which belonged to item {$existingRoute->node_id} is removed from that item and attached to this item instead";
                    $existingRoute->node_id = $this->owner->node_id;
                    $suggestedUpdatedRoutes[] = $existingRoute;
                }
            } else {
                // If not exists, add as suggested updated route
                $this->suggestedUpdatesLog[] = "Route '$suggestedRoute->route' is added and attached to this item";
                $suggestedUpdatedRoutes[] = $suggestedRoute;
            }
        }
        return $suggestedUpdatedRoutes;
    }

    public function getSuggestedUpdatesLog()
    {
        return $this->suggestedUpdatesLog;
    }

    public function printSuggestedRoutesDebug()
    {
        echo '<pre>';
        echo "routes:\n\n";
        print_r(\neam\util\U::arAttributes($this->owner->routes));
        echo "suggestedRoutes():\n\n";
        print_r(\neam\util\U::arAttributes($this->owner->suggestedRoutes()));
        echo "suggestedUpdatedRoutes():\n\n";
        print_r(\neam\util\U::arAttributes($this->owner->suggestedUpdatedRoutes()));
        echo '</pre>';
    }

}
