<?php

namespace white43\CloudAssetManager\commands;

use Yii;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\web\AssetBundle;

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
        /** @var \white43\CloudAssetManager\BaseAssetManager $am */
        $am = Yii::$app->get('assetManager');

        $time = -microtime(true);

        foreach ($this->getAssetsBundles() as $bundle) {
            try {
                $object = Yii::createObject($bundle);

                if (!$object instanceof AssetBundle) {
                    throw new InvalidConfigException('Bundles must be instances of type yii\web\AssetBundle');
                }

                $object->publish($am);
            } catch (\Throwable $e) {
                Yii::$app->errorHandler->logException($e);
            }
        }

        $time += microtime(true);
        echo sprintf('Assets have been warmed up. It took %.02f seconds', $time) . PHP_EOL;

        return ExitCode::OK;
    }

    /**
     * @return string[]
     */
    protected function getAssetsBundles(): array
    {
        $bundles = [];

        /** @var mixed $bundle */
        foreach (Yii::$app->params['assets-warm-up-bundles'] ?? [] as $bundle) {
            if (is_string($bundle)) {
                $bundles[] = $bundle;
            }
        }

        return $bundles;
    }
}
