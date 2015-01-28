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
     * The routes relation attribute
     * @var string
     */
    public $relation = null;

    /**
     * List of attributes for which file routes should be suggested
     * @var array
     */
    public $file_route_attributes = array();

    /**
     * List of route type refs for which routes should be suggested
     * If left empty, all routes will be suggested
     * @var array
     */
    public $routeTypeRefs = null;

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

    public function trailingSlashEquivalent(Route $route)
    {
        return $this->paddedEquivalent($route, "", "/");
    }

    public function paddedEquivalent(Route $route, $prefix, $suffix)
    {
        $return = clone $route;
        $return->route = $prefix . $return->route . $suffix;
        return $return;
    }

    /**
     * Suggests route, route_type_id, file_route_attribute_ref and translation_route_language for the following route types
     *
     * SHORT = 'short';
     * SEMANTIC = 'semantic';
     * FILE_SEMANTIC = 'file_semantic';
     * I18N_SHORT = 'i18n_short';
     * I18N_SEMANTIC = 'i18n_semantic';
     * I18N_FILE_SEMANTIC = 'i18n_file_semantic';
     *
     * Not in use:
     *
     * FILE_SHORT = 'file_short';
     * I18N_FILE_SHORT = 'i18n_file_short';
     */
    public function suggestedRoutes()
    {
        // Shorthand reference

        $rts =& $this->routeTypeRefs;

        // Make sure we use the "edited" flavor of the item when we determine semantic routes so that language fallback contents are inactivated

        $owner = $this->owner;
        if ($this->owner->asa('i18n-attribute-messages') !== null) {
            $owner = $this->owner->edited();
        }

        // Store the current display language

        $_language = Yii::app()->language;

        // RouteType::SHORT (TODO: the node id of the first version of this item)

        if (empty($rts) || in_array(RouteType::SHORT, $rts)) {

            $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::SHORT));

            $routes = array();

            $route = new Route;
            $route->route = "/{$owner->node()->id}";
            $route->route_type_id = $routeType->id;

            $routes[RouteType::SHORT] = $route;
            $routes[RouteType::SHORT . "-trailing-slash"] = $this->trailingSlashEquivalent($route);

        }

        // Switch to the model's source language - the semantic route will be supplied in this language

        Yii::app()->language = $owner->source_language;

        if (!empty($owner->slug_en)) {

            // RouteType::SEMANTIC - the current semantic route based on current attribute values

            if (empty($rts) || in_array(RouteType::SEMANTIC, $rts)) {

                $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::SEMANTIC));
                $route = new Route;

                // Use owner item's semanticRoute() to determine semanticRoute
                $route->route = $owner->semanticRoute();
                $route->route_type_id = $routeType->id;

                $routes[RouteType::SEMANTIC] = $route;
                $routes[RouteType::SEMANTIC . "-trailing-slash"] = $this->trailingSlashEquivalent($route);

            }

            // RouteType::FILE_SEMANTIC - the current semantic file route

            if (empty($rts) || in_array(RouteType::FILE_SEMANTIC, $rts)) {

                if (!empty($this->file_route_attributes)) {

                    foreach ($this->file_route_attributes as $file_route_attribute) {

                        $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::FILE_SEMANTIC));
                        $route = new Route;
                        $route->route = $owner->semanticFileRoute($file_route_attribute);
                        $route->route_type_id = $routeType->id;
                        $route->file_route_attribute_ref = $file_route_attribute;

                        $routes[RouteType::FILE_SEMANTIC . "-$file_route_attribute"] = $route;

                    }

                }
            }


        }

        // Switch back to ordinary application language

        Yii::app()->language = $_language;

        // translation routes

        foreach (LanguageHelper::getLanguageList() as $code => $label) {

            $lang = str_replace("_", "-", $code);

            // RouteType::I18N_SHORT

            if (empty($rts) || in_array(RouteType::I18N_SHORT, $rts)) {

                $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::I18N_SHORT));
                $route = new Route;
                $route->route = "/$lang" . $routes[RouteType::SHORT]->route;
                $route->route_type_id = $routeType->id;
                $route->translation_route_language = $code;

                $routes[RouteType::I18N_SHORT . "-{$code}"] = $route;
                $routes[RouteType::I18N_SHORT . "-{$code}-trailing-slash"] = $this->trailingSlashEquivalent($route);

            }

            // Switch to the current language - the translated semantic route will be supplied in this language

            Yii::app()->language = $code;

            if (!empty($owner->slug)) {

                // RouteType::I18N_SEMANTIC

                if (empty($rts) || in_array(RouteType::I18N_SEMANTIC, $rts)) {

                    $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::I18N_SEMANTIC));
                    $route = new Route;
                    $route->route = $owner->semanticRoute();
                    $route->route_type_id = $routeType->id;
                    $route->translation_route_language = $code;

                    $routes[RouteType::I18N_SEMANTIC . "-{$code}"] = $route;
                    $routes[RouteType::I18N_SEMANTIC . "-{$code}-trailing-slash"] = $this->trailingSlashEquivalent($route);

                }

                // RouteType::I18N_FILE_SEMANTIC

                if (empty($rts) || in_array(RouteType::I18N_FILE_SEMANTIC, $rts)) {

                    if (!empty($this->file_route_attributes)) {

                        foreach ($this->file_route_attributes as $file_route_attribute) {

                            $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::I18N_FILE_SEMANTIC));
                            $route = new Route;
                            $route->route = $owner->semanticFileRoute($file_route_attribute, $lang);
                            $route->route_type_id = $routeType->id;
                            $route->file_route_attribute_ref = $file_route_attribute;
                            $route->translation_route_language = $code;

                            $routes[RouteType::I18N_FILE_SEMANTIC . "-$file_route_attribute-$code"] = $route;

                        }

                    }

                }

            }

        }

        // Switch back to ordinary application language

        Yii::app()->language = $_language;

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
        $currentRoutes = $this->owner->{$this->relation};
        $suggestedRoutes = $this->suggestedRoutes();
        $suggestedUpdatedRoutes = $currentRoutes;
        foreach ($suggestedRoutes as $suggestedRoute) {

            // If already exists, check if belongs to this or not
            $attributes = array(
                'route' => $suggestedRoute->route
            );
            $existingRoute = Route::model()->findByAttributes($attributes);
            if (!empty($existingRoute)) {
                if ($existingRoute->node_id == $this->owner->node_id) {
                    // already belongs to current item - update suggested attributes
                    $existingRoute->route_type_id = $suggestedRoute->route_type_id;
                    $existingRoute->file_route_attribute_ref = $suggestedRoute->file_route_attribute_ref;
                    $existingRoute->translation_route_language = $suggestedRoute->translation_route_language;
                } else {
                    // belongs to another item - add to current item instead
                    $this->suggestedUpdatesLog[] = "Route '$existingRoute->route' which belonged to item {$existingRoute->node_id} will be removed from that item and attached to this item instead";
                    $existingRoute->node_id = $this->owner->node_id;
                    $suggestedUpdatedRoutes[] = $existingRoute;
                }
            } else {
                // If not exists, add as suggested updated route
                $this->suggestedUpdatesLog[] = "Route '$suggestedRoute->route' will be added and attached to this item";
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
        print_r(\neam\util\U::arAttributes($this->owner->{$this->relation}));
        echo "suggestedRoutes():\n\n";
        print_r(\neam\util\U::arAttributes($this->owner->suggestedRoutes()));
        echo "suggestedUpdatedRoutes():\n\n";
        print_r(\neam\util\U::arAttributes($this->owner->suggestedUpdatedRoutes()));
        echo '</pre>';
    }

}
