<?php

namespace League\Flysystem\Gcs;

use PulkitJalan\Google\Client as GoogleClient;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;

class GcsAdapter extends AbstractAdapter
{
    /**
     * @var string bucket name
     */
    protected $bucket;

    /**
     * @var GoogleClient Google Client
     */
    protected $client;

    /**
     * Constructor.
     *
     * @param GoogleClient $client
     * @param string       $bucket
     * @param string       $prefix
     * @param array        $options
     */
    public function __construct(
        GoogleClient $client,
        $bucket,
        $prefix = null,
        array $options = []
    ) {
        $this->client  = $client->make('storage');
        $this->bucket  = $bucket;
        $this->setPathPrefix($prefix);
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Get the GoogleClient bucket.
     *
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Get the GoogleClient instance.
     *
     * @return GoogleClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        $location = $this->applyPathPrefix($path);
        $objects = $this->client->listObjects($this->bucket, ['prefix' => $location]);

        return count($objects->getItems()) !== 0;
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        // TODO: Complete method!

        $postBody = new \Google_Service_Storage_StorageObject();
        $postBody->setName(basename($path));

        $args = [
            'uploadType' => 'multipart',
            'data' => $contents,
            'name' => basename($path),
        ];

        return $this->client->insert($this->bucket, $postBody, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($path, Config $config)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility($path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility($path, $visibility)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($dirname = '', $recursive = false)
    {
    }
}
