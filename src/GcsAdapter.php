<?php

namespace League\Flysystem\Gcs;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use Google_Service_Storage;

class GcsAdapter extends AbstractAdapter
{
    /**
     * @var string bucket name
     */
    protected $bucket;

    /**
     * @var Google_Service_Storage Google Storage Client
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
        Google_Service_Storage $client,
        $bucket,
        $prefix = null,
        array $options = []
    ) {
        $this->client  = $client;
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
     * Get the Google_Service_Storage instance.
     *
     * @return Google_Service_Storage
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
        $objects = $this->client->objects->listObjects($this->bucket, ['prefix' => $location]);

        return count($objects->getItems()) !== 0;
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        $postBody = new \Google_Service_Storage_StorageObject();
        $postBody->setName($path);

        $args = [
            'uploadType' => 'multipart',
            'data' => $contents,
            'name' => $path,
        ];

        return $this->client->objects->insert($this->bucket, $postBody, $args);
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
        if ($this->copy($path, $newPath)) {
            return $this->delete($path);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath)
    {
        $postBody = new \Google_Service_Storage_StorageObject();

        return $this->client->objects->copy(
            $this->bucket,
            $this->applyPathPrefix($path),
            $this->bucket,
            $this->applyPathPrefix($newPath),
            $postBody
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        $this->client->objects->delete($this->bucket, $this->applyPathPrefix($path));

        return $this->has($path);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($path)
    {
        return $this->delete($path);
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
