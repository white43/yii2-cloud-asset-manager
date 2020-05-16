<?php

namespace white43\CloudAssetManager;

use creocoder\flysystem\Filesystem;
use League\Flysystem\FileExistsException;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use yii\di\Instance;
use yii\helpers\FileHelper;

class AssetManager extends \yii\web\AssetManager
{
    private const CACHE_META_KEY = 'cloud-assets-meta-%s';
    private const CACHE_HASH_KEY = 'cloud-assets-hash-%s';

    /**
     * @var Cache
     */
    public $cache;

    /**
     * @var Filesystem
     */
    public $filesystem;

    /**
     * @var array
     */
    public $filterFilesOptions = [];

    /**
     * @var array published assets
     */
    private $_published = [];

    /**
     * Initializes the component.
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        if (empty($this->basePath)) {
            throw new InvalidConfigException('Relative path at the destination must be defined.');
        }

        $this->beforeCopy = function ($from, $to) {
            return strncmp(basename($from), '.', 1) !== 0;
        };

        $this->cache = Instance::ensure($this->cache, Cache::class);
        $this->filesystem = Instance::ensure($this->filesystem, Filesystem::class);

        if (!empty($this->filesystem->cache)) {
            throw new InvalidConfigException('You must not use League\Flysystem\Cached\CachedAdapter due to its inefficiency. This extension has its own caching system.');
        }
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

        [$basePath, $baseUrl] = $this->publishDirectory($dirname, [
            'only' => [
                substr($src, strlen($dirname)),
            ],
        ]);

        return [$basePath, $baseUrl . '/' . $filename];
    }

    /**
     * @param string $src
     * @param array $options
     * @return string[]
     */
    protected function publishDirectory($src, $options)
    {
        $dir = $this->hash($src);
        $dstDir = $this->basePath . DIRECTORY_SEPARATOR . $dir;

        $finalKey = $this->getMetaKey($dir . '-completed');
        $completed = \Yii::$app->cache->get($finalKey);

        if (!empty($options['forceCopy']) || ($this->forceCopy && !isset($options['forceCopy'])) || !$completed) {
            $currentLength = strlen($src);
            $directories = FileHelper::findDirectories($src, array_merge($this->filterFilesOptions, $options));
            $files = FileHelper::findFiles($src, array_merge($this->filterFilesOptions, $options));

            $meta = $this->getMetaFromRemoteData($this->filesystem->listContents($dstDir, true), $dir);

            if (isset($options['beforeCopy'])) {
                $beforeCopy = $options['beforeCopy'];
            } else if ($this->beforeCopy !== null) {
                $beforeCopy = $this->beforeCopy;
            }
            if (isset($options['afterCopy'])) {
                $afterCopy = $options['afterCopy'];
            } else if ($this->afterCopy !== null) {
                $afterCopy = $this->afterCopy;
            }

            foreach ($directories as $directory) {
                $dstDirectory = substr($directory, $currentLength);

                if (isset($beforeCopy) && !$beforeCopy($directory, $dstDir . $dstDirectory)) {
                    continue;
                }

                $key = $this->getMetaKey($dir . $dstDirectory);
                $dirMeta = $meta[$key] ?? null;

                if (!isset($dirMeta)) {
                    $this->filesystem->createDir($dstDir . $dstDirectory);
                }
            }

            foreach ($files as $file) {
                $dstFile = substr($file, $currentLength);
                $dstBaseFile = basename($dstFile);

                if (isset($beforeCopy) && !$beforeCopy($file, $dstDir . $dstFile)) {
                    continue;
                }

                $key = $this->getMetaKey(dirname($dir . $dstFile));
                $dirMeta = $meta[$key] ?? null;

                try {
                    if (!isset($dirMeta[$dstBaseFile])) {
                        $this->filesystem->writeStream($dstDir . $dstFile, fopen($file, 'r'));

                        if (isset($afterCopy)) {
                            $afterCopy($file, $dstDir . $dstFile);
                        }
                    }
                } catch (FileExistsException $e) {
                    // Do nothing
                }
            }

            \Yii::$app->cache->set($finalKey, true);
        }

        return [$dstDir, $this->baseUrl . '/' . $dir];
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

    /**
     * @param array $data
     * @param string $hash
     * @return array
     */
    private function getMetaFromRemoteData(array $data, string $hash): array
    {
        $meta = [];
        $key = $this->getMetaKey($hash);
        $meta[$key] = [];

        foreach ($data as $item) {
            if ($item['type'] === 'file') {
                $key = $this->getMetaKey(substr($item['dirname'], strlen($this->basePath) + 1));
                $meta[$key][$item['basename']] = 1;
            }
        }

        return $meta;
    }

    /**
     * @param string $dir
     * @return string
     */
    private function getMetaKey(string $dir): string
    {
        return rtrim(sprintf(self::CACHE_META_KEY, $dir), '-/');
    }
}


