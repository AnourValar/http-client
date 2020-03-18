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
    public function offsetSet($offset, $value)
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
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->responseBodyJson[$offset]);
    }

    /**
     * @see \ArrayAccess
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->responseBodyJson[$offset]);
    }

    /**
     * @see \ArrayAccess
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return ( $this->responseBodyJson[$offset] ?? null );
    }
}
