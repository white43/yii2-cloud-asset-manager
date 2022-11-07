#### What it is

This small library is useful when you need to have repeatable hashes of your
asset bundles (thus, repeatable URLs of `css`, `js`, etc.) across deploys. It
helps to keep CDN cache warm and declines the number of the requests to the
origin. Nevertheless, it may be used in production environment as is without
uploading asset bundles to any remote storage. It relies on the contents of
your assets not on their timestamps, so it generates constant hashes and
caches them.

#### Installation

The preferred way to install this extension is to use `composer`.

```Shell
composer require white43/yii2-cloud-asset-manager
```

Next, you need to choose and install an appropriate adapter (which knows how
to talk to different storages) from the list of the supported adapters. They
can be found [here](https://github.com/thephpleague/flysystem#officially-supported-adapters).

For instance:

```Shell
composer require league/flysystem-aws-s3-v3
```

#### Cloud configuration

```PHP
<?php

$config = [
    'components' => [
        'assetManager' => [
            'class'    => \white43\CloudAssetManager\CloudAssetManager::class,
            'basePath' => 'local/path/to/assets',
            'baseUrl'  => '//your.cdn.com/remote/path/to/assets',
            'cache'    => 'cache', // Name of your cache component
            'verbose'  => true, // To dump copying process to stdout
            'adapter'  => function (): \League\Flysystem\FilesystemAdapter {
                $s3 = new \Aws\S3\S3Client([
                    'credentials' => [
                        'key' => 'Access Key ID',
                        'secret' => 'Secret Access Key',
                    ],
                    'region' => 'eu-central-1',
                    'version' => 'latest',
                    // The following options are useful when you need to connect to a S3-compatible storage
                    // 'endpoint' => '',
                    // 'use_path_style_endpoint' => true,
                ]);

                return new \League\Flysystem\AwsS3V3\AwsS3V3Adapter($s3, 'Your bucket name');
            },
        ],
    ],
];
```

Your asset bundles will be automatically uploaded to the chosen cloud storage.
On the browser side users will get files from that storage. Cache component
will be used to speed up page load.

If you have already configured filesystem for any other purposes as a component,
it is possible pass the name of the filesystem component.

```PHP
<?php

$config = [
    'components' => [
        'fs' => [
            'class' => \League\Flysystem\Filesystem::class,
            // ...
        ],
        'assetManager' => [
            'class'      => \white43\CloudAssetManager\CloudAssetManager::class,
            // ...
            'filesystem' => 'fs',
        ],
    ],
];
```

If you prefer having components in Dependency Injection Container, it is not a
problem.

```PHP
<?php

Yii::$container->set(\League\Flysystem\Filesystem::class, function () {
    // ...
    return new \League\Flysystem\Filesystem(...);
})

$config = [
    'components' => [
        'assetManager' => [
            'class'      => \white43\CloudAssetManager\CloudAssetManager::class,
            // ...
            'filesystem' => \League\Flysystem\Filesystem::class,
        ],
    ],
];
```

#### Local configuration

```PHP
<?php

$config = [
    'components' => [
        'assetManager' => [
            'class'    => \white43\CloudAssetManager\LocalAssetManager::class,
            'cache'    => 'cache', // Name of your cache component
            'basePath' => '@app/web/assets', // @webroot doesn't exist in CLI mode
        ],
    ],
];
```

Assets will not be uploaded to any cloud storage. This way may be useful for
testing and/or when you just need to keep constant hashes for your assets
without any cloud storage.

#### Upload your assets in the background

Add some configuration to your `console.php`.

```PHP
<?php

$config = [
    'controllerMap' => [
        'warm-up' => \white43\CloudAssetManager\commands\WarmUpController::class,
    ],
];
```

Add some configuration to your `params.php`.

```PHP
<?php

$params = [
    'assets-warm-up-bundles' => [
        \app\assets\AppAsset::class,
    ],
];
```

Run next command in the background (i.e. when a container is starting)

```Shell
./yii warm-up
```
