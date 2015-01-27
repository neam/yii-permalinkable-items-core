<?php

namespace neam\yii_permalinkable_items_core\behaviors;

use Exception;
use Route;
use RouteType;
use LanguageHelper;
use Yii;

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

    public function trailingSlashEquivalent($route)
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

        // Make sure we use the "edited" flavor of the item when we determine semantic routes so that language fallback contents are inactivated

        $owner = $this->owner;
        if ($this->owner->asa('i18n-attribute-messages') !== null) {
            $owner = $this->owner->edited();
        }

        // A. the node id of the first version of this item

        $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::ITEM));

        $routes = array();

        $route = new Route;
        $route->route = "/{$owner->node()->id}";
        $route->route_type_id = $routeType->id;

        $routes["node-id-route"] = $route;
        $routes["node-id-route-trailing-slash"] = $this->trailingSlashEquivalent($route);

        // Switch to the model's source language - the semantic route will be supplied in this language

        $_language = Yii::app()->language;
        Yii::app()->language = $owner->source_language;

        // B. the current semantic route based on current attribute values

        if (!empty($owner->slug_en)) {

            $route = new Route;

            // Use owner item's semanticRoute() to determine semanticRoute, fallback on this behavior's defaultSemanticRoute if not available
            //try {
            $route->route = $owner->semanticRoute();
            //} catch (\CException $e) {
            //    if (strpos($e->getMessage(), "and its behaviors do not have a method or closure named") !== false) {
            //        $route->route = $this->defaultSemanticRoute();
            //    } else {
            //        throw $e;
            //    }
            //}
            $route->route_type_id = $routeType->id;

            $routes["semantic-route"] = $route;
            $routes["semantic-route-trailing-slash"] = $this->trailingSlashEquivalent($route);

        }

        // Switch back to ordinary application language

        Yii::app()->language = $_language;

        // C. previous and later versions' routes

        // TODO

        // D. translation routes (node-id and semantic)

        $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::TRANSLATION));

        foreach (LanguageHelper::getLanguageList() as $code => $label) {

            $route = new Route;
            $route->route = "/" . str_replace("_", "-", $code) . $routes["node-id-route"]->route;
            $route->route_type_id = $routeType->id;
            $route->translation_route_language = $code;

            $routes["node-id-translation-route-{$code}"] = $route;
            $routes["node-id-translation-route-{$code}-trailing-slash"] = $this->trailingSlashEquivalent($route);

            // Skip semantic route for source language since it is already suggested above

            if ($code === $owner->source_language) {
                continue;
            }

            // Switch to the current language - the translated semantic route will be supplied in this language
            Yii::app()->language = $code;

            if (!empty($owner->slug)) {

                $owner->semanticRoute();
                $route = new Route;
                $route->route = $owner->semanticRoute();
                $route->route_type_id = $routeType->id;

                $routes["semantic-translation-route-{$code}"] = $route;
                $routes["semantic-translation-route-{$code}-trailing-slash"] = $this->trailingSlashEquivalent($route);

            }

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
