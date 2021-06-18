<?php

namespace white43\CloudAssetManager;

use creocoder\flysystem\Filesystem;
use League\Flysystem\FileExistsException;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\helpers\FileHelper;

class CloudAssetManager extends BaseAssetManager
{
    const CACHE_META_KEY = 'cloud-assets-meta-%s';

    /**
     * @var Filesystem
     */
    public $filesystem;

    /**
     * Initializes the component.
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (empty($this->basePath)) {
            throw new InvalidConfigException('Relative path at the destination must be defined.');
        }

        $this->filesystem = Instance::ensure($this->filesystem, Filesystem::class);

        if (!empty($this->filesystem->cache)) {
            throw new InvalidConfigException('You should not use League\Flysystem\Cached\CachedAdapter due to its inefficiency. This extension has its own caching system.');
        }
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
            } elseif ($this->beforeCopy !== null) {
                $beforeCopy = $this->beforeCopy;
            }
            if (isset($options['afterCopy'])) {
                $afterCopy = $options['afterCopy'];
            } elseif ($this->afterCopy !== null) {
                $afterCopy = $this->afterCopy;
            }

            foreach ($directories as $directory) {
                $dstDirectory = substr($directory, $currentLength);

                if (isset($beforeCopy) && !$beforeCopy($directory, $dstDir . $dstDirectory)) {
                    continue;
                }

                $key = $this->getMetaKey($dir . $dstDirectory);
                $dirMeta = isset($meta[$key]) ? $meta[$key] : null;

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
                $dirMeta = isset($meta[$key]) ? $meta[$key] : null;

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
     * @param array $data
     * @param string $hash
     * @return array
     */
    private function getMetaFromRemoteData(array $data, $hash)
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
    private function getMetaKey($dir)
    {
        return rtrim(sprintf(self::CACHE_META_KEY, $dir), '-/');
    }
}
