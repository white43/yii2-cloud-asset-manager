#### Installation

Preferred way to install this extension is to use `composer`.

```
composer require white43/yii2-cloud-asset-manager
```

From suggested packages you will need to choose and install one to work with your cloud storage.

#### Cloud configuration

```
$config = [
    'components' => [
        'assetManager' => [
            'class'    => \white43\CloudAssetManager\CloudAssetManager::class,
            'basePath' => 'relative/path/for/assets',
            'cache'    => 'cache', // Name of your cache component
            'baseUrl'  => '//your.cdn.com/relative/path/for/assets',
            'filesystem' => [
                'class'  => creocoder\flysystem\AwsS3Filesystem::class, // For AWS S3 or S3-compatible
                'key'    => 'Access Key ID',
                'secret' => 'Secret Access Key',
                'region' => 'eu-central-1',
                'bucket' => 'Your bucket name',
            ],
        ],
    ],
];
```

Assets will be automatically uploaded to cloud storage. On the browser side users will get files from that cloud. Cache component will be used to speed up page load.  

#### Local configuration

```
$config = [
    'components' => [
        'assetManager' => [
            'class'    => \white43\CloudAssetManager\LocalAssetManager::class,
            'cache'    => 'cache', // Name of your cache component
            ],
        ],
    ],
];
```

Assets will not be uploaded to cloud storage. This way may be useful for testing and/or when you just need to keep constant hashes for your assets without any cloud storage.

#### Upload your assets in the background

Add some configuration to your `console.php`.

```
$config = [
    'controllerMap' => [
        'warm-up' => \white43\CloudAssetManager\commands\WarmUpController::class,
    ],
    'components' => [
        'assetManager' => [
            'basePath' => '@app/web/assets', // @webroot doesn't exist in CLI mode  
        ],
    ],
];
```

Add some configuration to your `params.php`.

```
$params = [
    'assets-warm-up-bundles' => [
        \app\assets\AppAsset::class,
    ],
];
```

Run next command in the background (i.e. when a container is starting)

```
./yii warm-up
```