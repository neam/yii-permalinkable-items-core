<?php

namespace neam\yii_permalinkable_items_core\traits;

use P3Media;
use Yii;

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
                'canonicalRoute' => array(self::HAS_ONE, 'Route', array('id' => 'node_id'), 'through' => 'node', 'on' => 'canonical = 1'),
            );
        }
        if ($config["routeClass"] == "FileRoute") {
            return array(
                'fileRoutes' => array(self::HAS_MANY, 'FileRoute', array('id' => 'node_id'), 'through' => 'node'),
                'canonicalFileRoutes' => array(self::HAS_MANY, 'FileRoute', array('id' => 'node_id'), 'through' => 'node', 'on' => 'canonical = 1'),
                'nonCanonicalFileRoutes' => array(self::HAS_MANY, 'FileRoute', array('id' => 'node_id'), 'through' => 'node', 'on' => 'canonical = 1'),
                // The following relations are parametrized and can thus only be referenced if the corresponding withRelationName() methods are invoked on the model before findAll()
                'specificCanonicalFileRoute' => array(self::HAS_ONE, 'FileRoute', array('id' => 'node_id'), 'through' => 'node', 'on' => 'canonical = 1', 'condition' => 'file_route_attribute_ref = :file_route_attribute_ref'),
                'specificNonCanonicalFileRoutes' => array(self::HAS_MANY, 'FileRoute', array('id' => 'node_id'), 'through' => 'node', 'on' => 'canonical IS NULL', 'condition' => 'file_route_attribute_ref = :file_route_attribute_ref'),
            );
        }
        return array();
    }

    public function withSpecificCanonicalFileRoute($file_route_attribute_ref)
    {
        $this->getDbCriteria()->mergeWith(array(
            'with' => 'specificCanonicalFileRoute',
            'params' => array(':file_route_attribute_ref' => $file_route_attribute_ref)
        ));
        return $this;
    }

    public function withSpecificNonCanonicalFileRoutes($file_route_attribute_ref)
    {
        $this->getDbCriteria()->mergeWith(array(
            'with' => 'specificNonCanonicalFileRoutes',
            'params' => array(':file_route_attribute_ref' => $file_route_attribute_ref)
        ));
        return $this;
    }

    public function specificCanonicalFileRoute($file_route_attribute_ref)
    {
        $item = $this->withSpecificCanonicalFileRoute($file_route_attribute_ref)->findByPk($this->id);
        return !empty($item) ? $item->specificCanonicalFileRoute : [];
    }

    public function specificNonCanonicalFileRoutes($file_route_attribute_ref)
    {
        $item = $this->withSpecificNonCanonicalFileRoutes($file_route_attribute_ref)->findByPk($this->id);
        return !empty($item) ? $item->specificNonCanonicalFileRoutes : [];
    }

    /**
     * Pushes files to their canonical file routes
     */
    public function pushFilesToS3()
    {

        $results = [];

        foreach ($this->canonicalFileRoutes as $canonicalFileRoute) {

            /** @var P3Media $file */
            $file = $this->{$canonicalFileRoute->file_route_attribute_ref};

            /** @var \EAmazonS3ResourceManager $resourceManager */
            $resourceManager = Yii::app()->getComponent('publicFilesResourceManager');
            $filePathS3 = $canonicalFileRoute->route;
            $filePath = $file->getFullPath();

            $fileSavedToAmazon = null;
            /*
            if ($resourceManager->getIsFileExists($filePathS3)) {
                // TODO: Ability to check hash if contents has changed
                $fileSavedToAmazon = "(already in place at $filePathS3)";
            } else {
            */
            // Always save file, since the contents may have changed
            $fileSavedToAmazon = $resourceManager->saveFile(null, $filePathS3, ['SourceFile' => $filePath]);
            /*
            }
            */

            // Set redirects to canonical route for corresponding non-canonical routes
            $correspondingNonCanonicalFileRoutes = $this->specificNonCanonicalFileRoutes($canonicalFileRoute->file_route_attribute_ref);
            $redirects = array();
            foreach ($correspondingNonCanonicalFileRoutes as $nonCanonicalFileRoute) {
                $redirects[$nonCanonicalFileRoute->route] = $resourceManager->saveFile("no-real-contents-redirect-only", $nonCanonicalFileRoute->route, ['WebsiteRedirectLocation' => $filePathS3]);
            }

            // Update P3 Media
            $file->s3_bucket = $resourceManager->bucket;
            $file->s3_path = $filePathS3;
            if (!$file->save()) {
                throw new \CException("Could not save file metadata");
            }

            $results[$this->currentPublicFileUrl($canonicalFileRoute->file_route_attribute_ref)] = compact("fileSavedToAmazon", "redirects");

        }

        return $results;

    }

    /**
     * Return the current public file url (without http(s)://)
     * @param $file_route_attribute_ref
     * @return mixed
     */
    public function currentPublicFileUrl($file_route_attribute_ref)
    {
        $file = $this->{$file_route_attribute_ref};
        return $file->s3_bucket . $file->s3_path;
    }

}
