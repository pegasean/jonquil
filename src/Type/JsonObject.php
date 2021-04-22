<?php

declare(strict_types=1);

namespace Jonquil\Type;

use Jonquil\Text\Translator;
use Jonquil\Type\Json\Schema;
use InvalidArgumentException;

/**
 * Class JsonObject
 * @package Jonquil\Type
 */
class JsonObject extends Map
{
    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * Initializes the class properties.
     *
     * @param array $data
     * @param Schema $schema
     */
    public function __construct(
        array $data,
        Schema $schema
    )
    {
        $this->schema = $schema;
        if (!$this->schema->validate(json_encode($data))) {
            throw new InvalidArgumentException('Invalid JSON Object');
        }
        parent::__construct($data, false);
    }

    public function toHtml(array $options = []): string
    {
        if ($this->isEmpty()) {
            return '';
        }
        $propertyMap = $this->schema->getPropertyMap()->toArray();
        $options = new Map($this->processHtmlExportOptions($options));
        return $this->convertToHtml($propertyMap, [], $options);
    }

    public function setTranslator(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function getValidationErrors(): array
    {
        return $this->schema->getValidationErrors();
    }

    /*--------------------------------------------------------------------*/

    protected function convertToHtml(array $tree, array $parentPointer, Map $options): string
    {
        $tbody = '';
        foreach ($tree as $name => $subtree) {
            $pointer = array_merge($parentPointer, [$name]);
            $path = implode('.', $pointer);
            $schema = is_array($subtree)
                ? $this->schema->getProperty($path)
                : $this->schema->get(unserialize($subtree));
            $types = $this->schema->getTypes($schema);

            $content = '';
            if (is_array($subtree)) {
                if (in_array('array', $types)) {
                    $values = $this->get($path);
                    if (is_array($values)) {
                        $values = new Map($values);
                        if ($values->isSequentiallyIndexed()) {
                            foreach ($values->getKeys() as $index) {
                                $branch = $subtree[$index] ?? $subtree['*'] ?? [];
                                if ($branch) {
                                    $content .= $this->convertToHtml($branch, array_merge($pointer, [$index]), $options);
                                }
                            }
                        }
                    }
                } else {
                    $content = $this->convertToHtml($subtree, $pointer, $options);
                }
            } else {
                $content = $this->renderAsHtml($this->get($path), $options);
            }

            if ($options->get('include_empty_values') || (strval($content) !== '')) {
                $label = $this->schema->getLabel($schema, $this->translator) ?: $name;
                $tbody .= '<tr><td>' . $label . '</td><td>' . $content . '</td></tr>';
            }
        }
        return $tbody ? ('<table' . $options->get('table_attributes') . '><tbody>' . $tbody . '</tbody></table>') : '';
    }

    protected function processHtmlExportOptions(array $options): array
    {
        return array_merge(parent::processHtmlExportOptions($options), [
            'include_empty_values' => boolval($options['include_empty_values'] ?? false),
        ]);
    }
}
