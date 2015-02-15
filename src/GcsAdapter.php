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
     * @param Google_Service_Storage $client
     * @param string                 $bucket
     * @param string                 $prefix
     * @param array                  $options
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
        $objects = $this->client->objects->listObjects($this->bucket, ['prefix' => $location, 'maxResults' => 1]);

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
        return $this->readObject($path);
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
    }

    /**
     * Read an object from the Google_Service_Storage.
     *
     * @param string $path
     *
     * @return array
     */
    protected function readObject($path)
    {
        $file = $this->client->objects->readObject($this->bucket, $path, ['alt' => 'media']);

        return array_merge($this->getMetadata($path), ['contents' => $file]);
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
        $location = $this->applyPathPrefix($path);
        $newLocation = $this->applyPathPrefix($newPath);

        $postBody = new \Google_Service_Storage_StorageObject();

        return $this->client->objects->copy($this->bucket, $location, $this->bucket, $newLocation, $postBody);
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
        $this->write(rtrim($path, '/').'/', '', $config);

        if (! $this->has($path)) {
            return false;
        }

        return ['path' => $path, 'type' => 'dir'];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $result = $this->client->objects->readObject($this->bucket, $path, ['projection' => 'full']);

        return $this->normalizeResponse($result, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        $meta = $this->getMetadata($path);

        return $meta['mimetype'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        $meta = $this->getMetadata($path);

        return $meta['size'];
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        $meta = $this->getMetadata($path);

        return $meta['timestamp'];
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
        $objects = $this->client->objects->listObjects($this->bucket, ['prefix' => $dirname]);
    }

    /**
     * Normalize a result from GCS.
     *
     * @param Google_Service_Storage_StorageObject $object
     * @param string                               $path
     *
     * @return array file metadata
     */
    protected function normalizeResponse(Google_Service_Storage_StorageObject $object, $path = null)
    {
        $result = ['path' => $path ?: $this->removePathPrefix($object->getName())];
        $result['dirname'] = Util::dirname($result['path']);
        $result['timestamp'] = strtotime($object->getUpdated());

        if (substr($result['path'], -1) === '/') {
            $result['type'] = 'dir';
            $result['path'] = rtrim($result['path'], '/');

            return $result;
        }

        $result['type'] = 'file';
        $result['size'] = $object->getSize();
        $result['mimetype'] = $object->getContentType();

        return $result;
    }
}
