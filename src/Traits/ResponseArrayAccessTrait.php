<?php

namespace AnourValar\HttpClient\Traits;

trait ResponseArrayAccessTrait
{
    /**
     * @see \ArrayAccess
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->responseBodyJson[] = $value;
        } else {
            $this->responseBodyJson[$offset] = $value;
        }
    }

    /**
     * @see \ArrayAccess
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->responseBodyJson[$offset]);
    }

    /**
     * @see \ArrayAccess
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->responseBodyJson[$offset]);
    }

    /**
     * @see \ArrayAccess
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return ( $this->responseBodyJson[$offset] ?? null );
    }
}
