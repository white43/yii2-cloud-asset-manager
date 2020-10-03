<?php

namespace white43\CloudAssetManager;

use yii\base\InvalidArgumentException;
use yii\caching\Cache;
use yii\di\Instance;
use yii\helpers\FileHelper;

class BaseAssetManager extends \yii\web\AssetManager
{
    const CACHE_HASH_KEY = 'cloud-assets-hash-%s';

    /**
     * @var Cache
     */
    public $cache;

    /**
     * @var array
     */
    public $filterFilesOptions = [];

    /**
     * @var array published assets
     */
    protected $_published = [];

    /**
     * Initializes the component.
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        $this->beforeCopy = function ($from, $to) {
            return strncmp(basename($from), '.', 1) !== 0;
        };

        $this->cache = Instance::ensure($this->cache, Cache::class);
    }

    /**
     * @param string $path
     * @param array $options
     * @return string[]
     */
    public function publish($path, $options = [])
    {
        $path = \Yii::getAlias($path);

        if (isset($this->_published[$path])) {
            return $this->_published[$path];
        }

        if (!is_string($path) || ($src = realpath($path)) === false) {
            throw new InvalidArgumentException("The file or directory to be published does not exist: $path");
        }

        if (is_file($src)) {
            return $this->_published[$path] = $this->publishFile($src);
        }

        return $this->_published[$path] = $this->publishDirectory($src, $options);
    }

    /**
     * @param string $src
     * @return string[]
     */
    protected function publishFile($src)
    {
        $dirname = dirname($src);
        $filename = basename($src);

        list($basePath, $baseUrl) = $this->publishDirectory($dirname, [
            'only' => [
                substr($src, strlen($dirname)),
            ],
        ]);

        return [$basePath, $baseUrl . '/' . $filename];
    }

    /**
     * @param string $path
     * @return string
     */
    protected function hash($path)
    {
        if (is_file($path)) {
            $path = dirname($path);
        }

        $key = sprintf(self::CACHE_HASH_KEY, $path);
        $hash = $this->cache->get($key);

        if ($hash === false) {
            $files = FileHelper::findFiles($path, $this->filterFilesOptions);
            $hashes = [];

            foreach ($files as $file) {
                $hashes[] = md5_file($file);
            }

            sort($hashes);
            $hash = md5(join('', $hashes));

            $this->cache->set($key, $hash);
        }

        return $hash;
    }
}