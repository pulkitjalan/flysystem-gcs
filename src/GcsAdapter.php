<?php

namespace League\Flysystem\Gcs;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use League\Flysystem\Util;

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
        \Google_Service_Storage $client,
        $bucket,
        $prefix = null,
        array $options = []
    ) {
        $this->client  = $client;
        $this->bucket  = $bucket;
        $this->setPathPrefix($prefix);
        $this->options = $options;
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
            'data'       => $contents,
            'name'       => $path,
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
        $postBody = new \Google_Service_Storage_StorageObject();
        $postBody->setName($path);

        $args = [
            'uploadType' => 'multipart',
            'data'       => $contents,
            'name'       => $path,
        ];

        return $this->client->objects->patch($this->bucket, $postBody, $args);
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
        $file = $this->client->objects->get($this->bucket, $path, ['alt' => 'media']);

        return array_merge($this->getMetadata($path), ['contents' => $file]);
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
        $result = $this->client->objects->get($this->bucket, $path, ['projection' => 'full']);

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
        $meta = $this->getMetadata($path);
        $acls = $meta->getAcl();

        foreach ($acls as $key => $acl) {
            if ($acl->getEntity() === 'allUsers') {
                return AdapterInterface::VISIBILITY_PUBLIC;
            }
        }

        return AdapterInterface::VISIBILITY_PRIVATE;
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility($path, $visibility)
    {
        $meta = $this->getMetadata($path);
        $acls = $meta->getAcl();

        $postBody = new \Google_Service_Storage_StorageObject();
        $postBody->setName($path);

        // if public then add allUsers acl else remove if it already exists
        if ($visibility === AdapterInterface::VISIBILITY_PUBLIC) {
            $acl = new \Google_Service_Storage_ObjectAccessControl();
            $acl->setEntity('allUsers');
            $acl->setRole('READER');

            $acls = array_merge($acls, [$acl]);
        } else {
            // if file is already public remove allUsers acl
            foreach ($acls as $key => $acl) {
                if ($acl->getEntity() === 'allUsers') {
                    unset($acls[$key]);
                }
            }
        }

        $postBody->setAcl($acls);

        $this->client->update($this->bucket, $path, $postBody);

        return compact('visibility');
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($dirname = '', $recursive = false)
    {
        $items = $this->listAllItems($dirname, $recursive);

        $result = array_map([$this, 'normalizeResponse'], $items);

        return Util::emulateDirectories($result);
    }

    /**
     * Recursively list all items in a bucket.
     *
     * @param string $dirname
     * @param bool   $recursive
     * @param array  $items
     * @param string $pageToken
     *
     * @return array Google_Service_Storage_StorageObject
     */
    protected function listAllItems($dirname = '', $recursive = false, $items = [], $pageToken = '')
    {
        $objects = $this->client->objects->listObjects($this->bucket, [
            'delimiter'  => ($recursive) ? '' : '/',
            'prefix'     => $this->applyPathPrefix($dirname),
            'projection' => 'full',
            'pageToken'  => $pageToken,
        ]);

        $items = array_merge($items, $objects->getItems());

        if ($objects->getNextPageToken()) {
            $items = $this->listAllItems($dirname, $recursive, $items, $objects->getNextPageToken());
        }

        return $items;
    }

    /**
     * Normalize a result from GCS.
     *
     * @param Google_Service_Storage_StorageObject $object
     * @param string                               $path
     *
     * @return array file metadata
     */
    protected function normalizeResponse(\Google_Service_Storage_StorageObject $object, $path = null)
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
