<?php

namespace kartavik\Collections;

use kartavik\Collections\Exceptions\InvalidElementException;

/**
 * Class Collection
 * @package kartavik\Collections
 */
class Collection extends \ArrayObject implements \JsonSerializable
{
    /** @var string */
    protected $type = null;

    public function __construct(
        string $type,
        array $elements = [],
        int $flags = 0,
        string $iteratorClass = \ArrayIterator::class
    ) {
        $this->type = $type;

        foreach ($elements as $element) {
            $this->instanceOfType($element);
        }

        parent::__construct($elements, $flags, $iteratorClass);
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @param mixed $value
     *
     * @throws InvalidElementException
     */
    public function append($value): void
    {
        $this->instanceOfType($value);

        parent::append($value);
    }

    /**
     * @param mixed $index
     * @param mixed $value
     *
     * @throws InvalidElementException
     */
    public function offsetSet($index, $value): void
    {
        $this->instanceOfType($value);

        parent::offsetSet($index, $value);
    }

    public function jsonSerialize(): array
    {
        return (array)$this;
    }

    /**
     * @param $object
     *
     * @throws InvalidElementException
     */
    public function instanceOfType($object): void
    {
        $type = $this->type();

        if (!$object instanceof $type) {
            throw new InvalidElementException($object, $type);
        }
    }

    public function map(callable $function, Collection ...$arrays): Collection
    {
        $mappedType = get_class(call_user_func(
            $function,
            $this->offsetGet(0)
        ));

        return Collection::{$mappedType}(array_map(
            $function,
            $this->jsonSerialize(),
            $arrays
        ));
    }

    public function chunk(int $size): Collection
    {
        $mappedType = get_class($this->offsetGet(0));
        /** @var Collection $collection */
        $collection = Collection::{Collection::class}();
        $chunked = array_chunk($this->jsonSerialize(), $size);

        foreach ($chunked as $index => $chunk) {
            $collection->append(Collection::{$mappedType}());

            foreach ($chunk as $item) {
                $collection[$index]->append($item);
            }
        }

        return $collection;
    }

    public function column(string $property, callable $function = null): Collection
    {
        $getterType = get_class($this->offsetGet(0)->{$property}());

        if (!is_null($function)) {
            /** @var Collection $collection */
            $collection = Collection::{$getterType}();

            foreach ($this->jsonSerialize() as $item) {
                $collection->append(call_user_func($function, $item->{$property}()));
            }

            return $collection;
        } else {
            return Collection::{$getterType}(array_map(
                function ($item) use ($property) {
                    return $item->{$property}();
                },
                $this->jsonSerialize()
            ));
        }
    }

    public function pop(): object
    {
        $last = $this->count() - 1;
        $element = $this->offsetGet($last);
        $this->offsetUnset($last);

        return $element;
    }

    public function sum(callable $function)
    {
        $sum = 0;

        foreach ($this as $element) {
            $sum += call_user_func($function, $element);
        }

        return $sum;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return Collection
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if (!empty($arguments) && is_array($arguments[0])) {
            $arguments = $arguments[0];
        }

        if (!class_exists($name)) {
            throw new \BadMethodCallException("Class with name {$name} does not exist!");
        }

        return new static($name, $arguments);
    }
}