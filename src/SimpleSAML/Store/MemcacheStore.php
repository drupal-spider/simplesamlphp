<?php

declare(strict_types=1);

namespace SimpleSAML\Store;

use SimpleSAML\Assert\Assert;
use SimpleSAML\{Configuration, Memcache};

/**
 * A memcache based data store.
 *
 * @package simplesamlphp/simplesamlphp
 */
class MemcacheStore implements StoreInterface
{
    /**
     * This variable contains the session name prefix.
     *
     * @var string
     */
    private string $prefix;


    /**
     * This function implements the constructor for this class. It loads the Memcache configuration.
     * @throws \Exception
     */
    public function __construct()
    {
        $config = Configuration::getInstance();
        $this->prefix = $config->getOptionalString('memcache_store.prefix', 'simpleSAMLphp');
    }


    /**
     * Retrieve a value from the data store.
     *
     * @param string $type The data type.
     * @param string $key The key.
     * @return mixed|null The value.
     * @throws \SimpleSAML\Error\Exception
     * @throws \Exception
     */
    public function get(string $type, string $key): mixed
    {
        return Memcache::get($this->prefix . '.' . $type . '.' . $key);
    }


    /**
     * Save a value to the data store.
     *
     * @param string $type The data type.
     * @param string $key The key.
     * @param mixed $value The value.
     * @param int|null $expire The expiration time (unix timestamp), or NULL if it never expires.
     * @throws \Exception
     */
    public function set(string $type, string $key, mixed $value, ?int $expire = null): void
    {
        Assert::nullOrGreaterThan($expire, 2592000);

        if ($expire === null) {
            $expire = 0;
        }

        Memcache::set($this->prefix . '.' . $type . '.' . $key, $value, $expire);
    }


    /**
     * Delete a value from the data store.
     *
     * @param string $type The data type.
     * @param string $key The key.
     * @throws \Exception
     */
    public function delete(string $type, string $key): void
    {
        Memcache::delete($this->prefix . '.' . $type . '.' . $key);
    }
}
