<?php

namespace white43\CloudAssetManager\commands;

use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Class AssetsWarmUpController
 * @package app\commands
 */
class WarmUpController extends Controller
{
    /**
     * @return int
     * @throws \yii\base\InvalidConfigException
     */
    public function actionIndex()
    {
        /** @var \white43\CloudAssetManager\AssetManager $am */
        $am = \Yii::$app->get('assetManager');

        $time = -microtime(true);

        foreach ($this->getAssetsBundles() as $bundle) {
            try {
                /** @var \yii\web\AssetBundle $bundle */
                $bundle = \Yii::createObject($bundle);
                $bundle->publish($am);
            } catch (\Throwable $e) {
                \Yii::$app->errorHandler->logException($e);
            }
        }

        $time += microtime(true);
        echo sprintf('Assets have been warmed up. It took %.02f seconds', $time) . PHP_EOL;

        return ExitCode::OK;
    }

    /**
     * @return array
     */
    protected function getAssetsBundles()
    {
        if (!empty(\Yii::$app->params['assets-warm-up-bundles'])) {
            return \Yii::$app->params['assets-warm-up-bundles'];
        }

        return [];
    }
}
