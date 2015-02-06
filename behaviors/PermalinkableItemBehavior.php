<?php

namespace neam\yii_permalinkable_items_core\behaviors;

use Exception;
use Route;
use FileRoute;
use RouteType;
use FileRouteType;
use LanguageHelper;
use Yii;
use neam\yii_permalinkable_items_core\exceptions\SemanticRouteException;

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
    public $fileRouteAttributeRefs = array();

    /**
     * Route class, either Route or FileRoute
     * @var array
     */
    public $routeClass = null;

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
     * Suggests route, route_type_id, file_route_attribute_ref and translation_route_language for both routes and file routes
     * TODO: Clean up, separate route and file route suggestions more
     */
    public function suggestedRoutes()
    {

        $routes = array();

        // Make sure we use the "edited" flavor of the item when we determine semantic routes so that language fallback contents are inactivated

        $owner = $this->owner;
        if ($this->owner->asa('i18n-attribute-messages') !== null) {
            $owner = $this->owner->edited();
        }

        // Store the current display language

        $_language = Yii::app()->language;

        // RouteType::SHORT (TODO: the node id of the first version of this item)

        if ($this->routeClass == "Route") {

            $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::SHORT));

            $route = new Route;
            $route->route = "/{$owner->node()->id}";
            $route->route_type_id = $routeType->id;

            $routes[RouteType::SHORT] = $route;
            $routes[RouteType::SHORT . "-trailing-slash"] = $this->trailingSlashEquivalent($route);

        }

        // Switch to the model's source language - the semantic route will be supplied in this language

        Yii::app()->language = $owner->source_language;

        // RouteType::SEMANTIC - the current semantic route based on current attribute values

        if ($this->routeClass == "Route") {

            try {

                $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::SEMANTIC));
                $route = new Route;

                // Use owner item's semanticRoute() to determine semanticRoute
                $route->route = $owner->semanticRoute();
                $route->route_type_id = $routeType->id;

                $routes[RouteType::SEMANTIC] = $route;
                $routes[RouteType::SEMANTIC . "-trailing-slash"] = $this->trailingSlashEquivalent($route);

            } catch (SemanticRouteException $e) {
                Yii::log('SemanticRouteException: ' . $e->getMessage(), 'warning', __METHOD__);
                throw $e;
            }

        }

        // FileRouteType::FILE_SEMANTIC - the current semantic file route

        if ($this->routeClass == "FileRoute") {

            foreach ($this->fileRouteAttributeRefs as $file_route_attribute) {

                try {

                    $routeType = FileRouteType::model()->findByAttributes(array('ref' => FileRouteType::FILE_SEMANTIC));
                    $route = new FileRoute;
                    $route->route = $owner->semanticFileRoute($file_route_attribute);
                    $route->file_route_type_id = $routeType->id;
                    $route->file_route_attribute_ref = $file_route_attribute;
                    $route->canonical = 1;

                    $routes[FileRouteType::FILE_SEMANTIC . "-$file_route_attribute"] = $route;

                } catch (SemanticRouteException $e) {
                    Yii::log('SemanticRouteException: ' . $e->getMessage(), 'warning', __METHOD__);
                    throw $e;
                }

            }

        }

        // Switch back to ordinary application language

        Yii::app()->language = $_language;

        // Get metadata about multilingual relations

        $multilingualRelations = $this->owner->getMultilingualRelations();

        // Translation routes

        foreach (LanguageHelper::getLanguageList() as $code => $label) {

            $lang = str_replace("_", "-", $code);

            // RouteType::I18N_SHORT

            if ($this->routeClass == "Route") {

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

            // RouteType::I18N_SEMANTIC - Skip semantic route for source language since it is already suggested above

            if ($this->routeClass == "Route" && $code !== $owner->source_language) {

                try {

                    $routeType = RouteType::model()->findByAttributes(array('ref' => RouteType::I18N_SEMANTIC));
                    $route = new Route;
                    $route->route = $owner->semanticRoute();
                    $route->route_type_id = $routeType->id;
                    $route->translation_route_language = $code;

                    $routes[RouteType::I18N_SEMANTIC . "-{$code}"] = $route;
                    $routes[RouteType::I18N_SEMANTIC . "-{$code}-trailing-slash"] = $this->trailingSlashEquivalent($route);

                } catch (SemanticRouteException $e) {
                    Yii::log("SemanticRouteException (lang=" . Yii::app()->language . "): " . $e->getMessage(), 'warning', __METHOD__);
                }

            }

            // FileRouteType::I18N_FILE_SEMANTIC

            if ($this->routeClass == "FileRoute") {

                foreach ($this->fileRouteAttributeRefs as $file_route_attribute) {

                    if (!in_array($file_route_attribute, array_keys($multilingualRelations))) {
                        continue;
                    }

                    try {

                        $routeType = FileRouteType::model()->findByAttributes(array('ref' => FileRouteType::I18N_FILE_SEMANTIC));
                        $route = new FileRoute;
                        $route->file_route_attribute_ref = $multilingualRelations[$file_route_attribute][$code];
                        $route->route = $owner->semanticFileRoute($route->file_route_attribute_ref, $lang);
                        $route->file_route_type_id = $routeType->id;
                        $route->translation_route_language = $code;
                        $route->canonical = 1;

                        $routes[FileRouteType::I18N_FILE_SEMANTIC . "-$file_route_attribute-$code"] = $route;

                    } catch (SemanticRouteException $e) {
                        Yii::log('SemanticRouteException (lang=" . Yii::app()->language . "): ' . $e->getMessage(), 'warning', __METHOD__);
                    }

                }

            }

        }

        // Switch back to ordinary application language

        Yii::app()->language = $_language;

        // Suggest canonical routes

        $ref = RouteType::SEMANTIC . "-trailing-slash";
        if (isset($routes[$ref])) {
            $routes[$ref]->canonical = 1;
        }

        foreach ($this->fileRouteAttributeRefs as $file_route_attribute) {
            $ref = FileRouteType::I18N_FILE_SEMANTIC . "-$file_route_attribute-" . $owner->source_language;
            if (isset($routes[$ref])) {
                $routes[$ref]->canonical = 1;
            }
        }

        return $routes;

    }

    public $suggestedUpdatesLog = null;

    protected function unsetMatchingRouteInArray(&$routes, $q)
    {
        foreach ($routes as $k => $route) {
            if ($route->route === $q) {
                unset($routes[$k]);
            }
        }
        return null;
    }

    protected function searchRouteArray(&$routes, $q)
    {
        foreach ($routes as $k => $route) {
            if ($route->route === $q) {
                return $k;
            }
        }
        return null;
    }

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
            $routeClass = $this->routeClass;
            $existingRoute = $routeClass::model()->findByAttributes($attributes);
            if (!empty($existingRoute)) {
                if ($existingRoute->node_id == $this->owner->node_id) {
                    // already belongs to current item - we do nothing and keep it as it was
                } else {
                    // belongs to another item - add to current item instead
                    $this->suggestedUpdatesLog[] = "$routeClass '$existingRoute->route' which belonged to item {$existingRoute->node_id} will be removed from that item and attached to this item instead";
                    $suggestedUpdatedRoutes[] = $suggestedRoute;
                }
            } else {
                // check if we already have it amongst suggested routes
                if ($this->searchRouteArray($suggestedUpdatedRoutes, $suggestedRoute->route) !== null) {
                    // already suggested previously - a potential conflict
                    $this->suggestedUpdatesLog[] = "$routeClass '$suggestedRoute->route' was suggested more than once and will only be added once";
                } else {
                    // If not exists and not already suggested, add as suggested updated route
                    $this->suggestedUpdatesLog[] = "$routeClass '$suggestedRoute->route' will be added and attached to this item";
                    $suggestedUpdatedRoutes[] = $suggestedRoute;
                }
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
