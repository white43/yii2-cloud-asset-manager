#### Installation

Preferred way to install this extension is to use `composer`.

```
composer require white43/yii2-cloud-asset-manager
```

From suggested packages you will need to choose and install one to work with your cloud storage.

#### Configuration

```
$config = [
    'components' => [
        'assetManager' => [
            'class'    => \white43\CloudAssetManager\AssetManager::class,
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
