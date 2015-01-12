<?php

namespace neam\yii_permalinkable_items_core\behaviors;

/**
 * PermalinkableItemBehavior
 *
 * @uses CActiveRecordBehavior
 * @license BSD-3-Clause
 * @author See https://github.com/neam/yii-permalinkable-items-core/graphs/contributors
 */
class PermalinkableItemBehavior extends CActiveRecordBehavior
{

    /**
     * @param CActiveRecord $owner
     * @throws Exception
     */
    public function attach($owner)
    {
        parent::attach($owner);
        if (!($owner instanceof CActiveRecord)) {
            throw new Exception('Owner must be a CActiveRecord class');
        }
    }

    public function beforeSave($event)
    {
        $this->initiateNode();
    }

    /**
     * A. the node id of the first version of this item
     * B. the current semantic route based on current attribute values
     * C. upon publishing: previous versions' routes
     * D. translation routes
     * E. file routes
     * F. file translation routes
     */
    public function suggestRoutes()
    {

        return "foo";

    }

}
