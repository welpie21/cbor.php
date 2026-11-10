<?php

namespace Beau\CborPHP\classes;

use Beau\CborPHP\abstracts\AbstractTaggedValue;
use Beau\CborPHP\exceptions\CborException;
use Beau\CborPHP\interfaces\CborTag;
use Beau\CborPHP\utils\CborByteString;
use Beau\CborPHP\utils\CborMap;
use ReflectionClass;

/**
 * The tag handlers of one Cbor instance: checked once, and indexed so that the
 * encoder and decoder find a handler with one array lookup.
 *
 * @internal Use the `tags` option of {@see \Beau\CborPHP\Cbor}.
 */
final class TagRegistry
{
    /**
     * Handlers by tag number, for the decoder.
     *
     * @var array<int, CborTag>
     */
    public readonly array $byNumber;

    /**
     * [tag number, handler] by the class of an encoded object, or false when no
     * handler takes that class. Exact classes are filled in here; the encoder adds
     * subclasses and classes without a handler as it meets them.
     *
     * @var array<class-string, array{int, CborTag}|false>
     */
    public array $byClass = [];

    /**
     * The handlers that encode, by class: [tag number, handler, position in the order given].
     *
     * @var array<class-string, array{int, CborTag, int}>
     */
    private readonly array $encoders;

    /**
     * @param array<mixed> $tags
     * @throws CborException
     */
    public function __construct(array $tags)
    {
        $byNumber = [];
        $encoders = [];

        foreach ($tags as $handler) {
            if (!$handler instanceof CborTag) {
                throw new CborException("A tag handler must implement " . CborTag::class . ", got " . get_debug_type($handler));
            }

            $name = get_debug_type($handler);
            $tag = $handler->tag();

            if ($tag < 0) {
                throw new CborException("Tag number must not be negative: $tag, from $name");
            }

            if (isset($byNumber[$tag])) {
                throw new CborException("Tag $tag has two handlers: " . get_debug_type($byNumber[$tag]) . " and $name");
            }

            $byNumber[$tag] = $handler;
            $class = $handler->class();

            if ($class === null) {
                continue; // The handler only decodes.
            }

            if (!class_exists($class) && !interface_exists($class)) {
                throw new CborException("$name::class() returned an unknown class: $class");
            }

            // The name as declared, so that it matches $object::class.
            $class = (new ReflectionClass($class))->getName();

            foreach ([AbstractTaggedValue::class, CborByteString::class, CborMap::class] as $builtIn) {
                if (is_a($class, $builtIn, true)) {
                    throw new CborException("$class cannot have a tag handler: the encoder writes it itself");
                }
            }

            if (isset($this->byClass[$class])) {
                throw new CborException("$class has two tag handlers: for tag {$this->byClass[$class][0]} and tag $tag");
            }

            $this->byClass[$class] = [$tag, $handler];
            $encoders[$class] = [$tag, $handler, count($encoders)];
        }

        $this->byNumber = $byNumber;
        $this->encoders = $encoders;
    }

    /**
     * Find the handler for an object whose exact class has none: the first handler,
     * in the order given, whose class the object is an instance of.
     *
     * The encoder calls this once for each class, and keeps the answer in $byClass.
     *
     * @return array{int, CborTag}|false
     */
    public function find(object $value): array|false
    {
        $best = null;

        // Look up each parent class and interface of the object in $encoders. That is a
        // few hash lookups, whatever the number of handlers, where a loop over the
        // handlers with instanceof gets slower with each handler.
        foreach (class_parents($value) + class_implements($value) as $ancestor) {
            $entry = $this->encoders[$ancestor] ?? null;

            if ($entry !== null && ($best === null || $entry[2] < $best[2])) {
                $best = $entry;
            }
        }

        return $best === null ? false : [$best[0], $best[1]];
    }
}
