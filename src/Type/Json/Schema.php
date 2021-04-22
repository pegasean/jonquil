<?php

declare(strict_types=1);

namespace Jonquil\Type\Json;

use Jonquil\Text\Translator;
use Jonquil\Type\Map;
use JsonSchema\Validator;

use InvalidArgumentException;

/**
 * Class Schema
 * @package Jonquil\Type\Json
 */
class Schema extends Map
{
    const SIMPLE_TYPES = [
        'array',
        'boolean',
        'integer',
        'null',
        'number',
        'object',
        'string',
    ];
    const TRAVERSAL_KEYWORDS = [
        'items',
        'additionalItems',
        'additionalProperties',
        'propertyNames',
        'contains',
        'not',
    ];
    const LIST_TRAVERSAL_KEYWORDS = [
        'items',
        'allOf',
        'anyOf',
        'oneOf',
    ];
    const MAP_TRAVERSAL_KEYWORDS = [
        'definitions',
        'properties',
        'patternProperties',
        'dependencies',
    ];
    const INDEX_KEYWORDS = [
        'items',
        'additionalItems',
        'properties',
        'additionalProperties',
    ];

    /**
     * @var array
     */
    protected $index;

    /**
     * @var array
     */
    private $validationErrors = [];

    /**
     * Initializes the class properties.
     *
     * @param string $schema
     */
    public function __construct(
        string $schema
    )
    {
        parent::__construct(json_decode($schema, true));

        $this->normalize();
        $this->buildIndex();
        $this->makeImmutable();
    }

    /**
     * Clones the Schema object.
     *
     * @return Schema
     */
    public function __clone()
    {
        return new self($this->toJson());
    }

    public function hasProperties(array $schema): bool
    {
        return $this->hasType($schema, 'object')
            && isset($schema['properties'])
            && is_array($schema['properties']);
    }

    public function hasItems(array $schema): bool
    {
        return $this->hasType($schema, 'array')
            && isset($schema['items'])
            && is_array($schema['items']);
    }

    public function hasType(array $schema, string $type): bool
    {
        if (!in_array($type, self::SIMPLE_TYPES)) {
            throw new InvalidArgumentException('Invalid JSON Schema type');
        }
        if (!isset($schema['type'])) {
            return false;
        }
        if (is_string($schema['type'])) {
            return $schema['type'] === $type;
        } elseif (is_array($schema['type'])) {
            return in_array($type, $schema['type']);
        } else {
            return false;
        }
    }

    public function getTypes(array $schema): array
    {
        $types = $schema['type'] ?? null;
        return is_array($types) ? $types : [$types];
    }

    public function getLabel(array $schema, Translator $translator = null): string
    {
        if (!isset($schema['label'])) {
            return '';
        }
        if ($translator) {
            return $translator->resolve($schema['label']);
        } elseif (is_array($schema['label'])) {
            return json_encode($schema['label']);
        } else {
            return strval($schema['label']);
        }
    }

    public function hasProperty(string $propertyPath): bool
    {
        return isset($this->index[$propertyPath]);
    }

    public function getProperty(string $propertyPath): array
    {
        if ($this->hasProperty($propertyPath)) {
            return $this->get($this->index[$propertyPath], []);
        } else {
            return [];
        }
    }

    public function getPropertyList(): array
    {
        return $this->index;
    }

    public function getPropertyMap(): Map
    {
        $map = new Map();
        foreach ($this->index as $key => $value) {
            $map->set($key, serialize($value));
        }
        return $map;
    }

    public function getBasicSchema(): Schema
    {
        $map = new Map();
        $this->traverse(function ($schema, $pointer) use ($map) {
            $newSchema = [];
            $properties = ['type', 'label'];
            foreach ($properties as $propertyName) {
                if (isset($schema[$propertyName])) {
                    $newSchema[$propertyName] = $schema[$propertyName];
                }
            }
            $map->set($pointer, $newSchema);
        });
        return new self($map->toJson());
    }

    public function validate(string $data): bool
    {
        $validator = new Validator();
        $validator->check(json_decode($data), json_decode($this->toJson()));

        $isValid = $validator->isValid();
        if ($isValid) {
            $this->validationErrors = [];
        } else {
            $this->validationErrors = $validator->getErrors();
        }

        return $validator->isValid();
    }

    public function isValid(): bool
    {
        $validator = new Validator();
        $schema = (object) ['$ref' => 'file://' . dirname(__FILE__) . '/schema.json'];
        $validator->check(json_decode($this->toJson()), $schema);

        $isValid = $validator->isValid();
        if ($isValid) {
            $this->validationErrors = [];
        } else {
            $this->validationErrors = $validator->getErrors();
        }

        return $isValid;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /*--------------------------------------------------------------------*/

    protected function buildIndex()
    {
        $this->index = [];
        $objectPointer = [];
        $branchDepthLimit = 0;
        $this->traverse(
            function ($schema, $schemaPointer, $keyword, $index)
                use (&$objectPointer, &$branchDepthLimit)
            {
                if (empty($schemaPointer) || $branchDepthLimit) {
                    return;
                } elseif (!in_array($keyword, $this::INDEX_KEYWORDS)) {
                    $branchDepthLimit = count($schemaPointer);
                    return;
                } elseif (($keyword === 'items') && is_null($index)) {
                    $objectPointer[] = '*';
                    return;
                } elseif (in_array($keyword, ['additionalItems', 'additionalProperties'])) {
                    $index = '*';
                }
                $objectPointer[] = $index;
                $this->index[implode('.', $objectPointer)] = $schemaPointer;
            },
            function ($schema, $schemaPointer)
                use (&$objectPointer, &$branchDepthLimit)
            {
                if (empty($schemaPointer) || $branchDepthLimit) {
                    if ($branchDepthLimit && ($branchDepthLimit >= count($schemaPointer))) {
                        $branchDepthLimit = 0;
                    }
                    return;
                }
                array_pop($objectPointer);
            }
        );
    }

    protected function sortProperties(array &$schema)
    {
        if (!$this->hasProperties($schema)) {
            return;
        }
        uasort($schema['properties'], function($a, $b) {
            $a = $a['propertyOrder'] ?? '';
            $b = $b['propertyOrder'] ?? '';
            if (!is_numeric($a)) {
                return !is_numeric($b) ? 0 : 1;
            } elseif (!is_numeric($b)) {
                return -1;
            }
            $a = (int) $a;
            $b = (int) $b;
            if ($a < $b) {
                return -1;
            } elseif ($a > $b) {
                return 1;
            } else {
                return 0;
            }
        });
    }

    protected function normalize()
    {
        $normalize = function(&$schema)
            use (&$normalize)
        {
            if ($this->hasProperties($schema)) {
                $this->sortProperties($schema);
                foreach ($schema['properties'] as &$subschema) {
                    $normalize($subschema);
                }
            } elseif ($this->hasItems($schema)) {
                if ((new Map($schema['items']))->isSequentiallyIndexed()) {
                    foreach ($schema['items'] as &$subschema) {
                        $normalize($subschema);
                    }
                } else {
                    if (isset($schema['additionalItems'])) {
                        unset($schema['additionalItems']);
                    }
                    $normalize($schema['items']);
                }
            }
        };
        $normalize($this->data);
    }

    protected function traverse(callable $preOrder = null, callable $postOrder = null)
    {
        $traverse = function($schema, $pointer, $preOrder, $postOrder, $keyword, $index)
            use (&$traverse)
        {
            if ($preOrder) {
                $preOrder($schema, $pointer, $keyword, $index);
            }
            foreach ($schema as $keyword => $container) {
                if (!is_array($container)) {
                    continue;
                }
                $isAssociativeArray = !(new Map($container))->isSequentiallyIndexed();
                if (!$isAssociativeArray) {
                    if (in_array($keyword, $this::LIST_TRAVERSAL_KEYWORDS)) {
                        foreach ($container as $index => $subschema) {
                            $newPointer = array_merge($pointer, [$keyword, $index]);
                            $traverse($subschema, $newPointer, $preOrder, $postOrder, $keyword, $index);
                        }
                    }
                } elseif (in_array($keyword, $this::MAP_TRAVERSAL_KEYWORDS)) {
                    foreach ($container as $propertyName => $subschema) {
                        $newPointer = array_merge($pointer, [$keyword, $propertyName]);
                        $traverse($subschema, $newPointer, $preOrder, $postOrder, $keyword, $propertyName);
                    }
                } elseif (in_array($keyword, $this::TRAVERSAL_KEYWORDS)) {
                    $newPointer = array_merge($pointer, [$keyword]);
                    $traverse($container, $newPointer, $preOrder, $postOrder, $keyword, null);
                }
            }
            if ($postOrder) {
                $postOrder($schema, $pointer, $keyword, $index);
            }
        };
        $traverse($this->data, [], $preOrder, $postOrder, null, null);
    }
}
