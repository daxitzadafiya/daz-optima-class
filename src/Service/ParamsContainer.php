<?php

namespace Daz\OptimaClass\Service;

class ParamsContainer
{
    public $params = [];

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    /**
     * Check if a key exists in the container.
     *
     * @param string $key
     * @return bool
    */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->params);
    }

    /**
     * Get the value of a specific key.
     *
     * @param string $key
     * @return mixed|null
    */
    public function get(string $key)
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Set or update a key in the container.
     *
     * @param string $key
     * @param mixed $value
     * @return void
    */
    public function set(string $key, $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * Remove a key from the container.
     *
     * @param string $key
     * @return void
    */
    public function remove(string $key): void
    {
        unset($this->params[$key]);
    }
}