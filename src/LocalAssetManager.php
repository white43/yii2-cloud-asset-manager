<?php

namespace white43\CloudAssetManager;

use yii\base\InvalidConfigException;

class LocalAssetManager extends BaseAssetManager
{
    public function init()
    {
        parent::init();

        $this->basePath = \Yii::getAlias($this->basePath);
        if (!is_dir($this->basePath)) {
            throw new InvalidConfigException("The directory does not exist: {$this->basePath}");
        }

        $this->basePath = realpath($this->basePath);
    }
}
