<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\View\Layout\Reader;

use Magento\Framework\View\Layout;
use Magento\Framework\View\Layout\Element;
use Magento\Framework\View\Layout\ReaderPool;
use Magento\Framework\Config\DataInterfaceFactory;

/**
 * Class UiComponent
 */
class UiComponent implements Layout\ReaderInterface
{
    /**#@+
     * Supported types
     */
    const TYPE_UI_COMPONENT = 'uiComponent';
    /**#@-*/

    /**
     * List of supported attributes
     *
     * @var array
     */
    protected $attributes = ['group', 'component', 'acl', 'condition'];

    /**
     * @var Layout\ScheduledStructure\Helper
     */
    protected $layoutHelper;

    /**
     * @var string|null
     */
    protected $scopeType;

    /**
     * @var DataInterfaceFactory
     */
    private $uiConfigFactory;

    /**
     * @var ReaderPool
     */
    private $readerPool;

    /**
     * @param Layout\ScheduledStructure\Helper $helper
     * @param DataInterfaceFactory $uiConfigFactory
     * @param ReaderPool $readerPool
     * @param string|null $scopeType
     */
    public function __construct(
        Layout\ScheduledStructure\Helper $helper,
        DataInterfaceFactory $uiConfigFactory,
        ReaderPool $readerPool,
        $scopeType = null
    ) {
        $this->layoutHelper = $helper;
        $this->scopeType = $scopeType;
        $this->uiConfigFactory = $uiConfigFactory;
        $this->readerPool = $readerPool;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedNodes()
    {
        return [self::TYPE_UI_COMPONENT];
    }

    /**
     * {@inheritdoc}
     */
    public function interpret(Context $readerContext, Element $currentElement)
    {
        $attributes = $this->getAttributes($currentElement);
        $scheduledStructure = $readerContext->getScheduledStructure();
        $referenceName = $this->layoutHelper->scheduleStructure(
            $scheduledStructure,
            $currentElement,
            $currentElement->getParent(),
            ['attributes' => $attributes]
        );

        $scheduledStructure->setStructureElementData($referenceName, ['attributes' => $attributes]);
        $configPath = (string)$currentElement->getAttribute('ifconfig');
        if (!empty($configPath)) {
            $scheduledStructure->setElementToIfconfigList($referenceName, $configPath, $this->scopeType);
        }

        $elements = [];
        $config = $this->uiConfigFactory->create(['componentName' => $referenceName])->get($referenceName);
        $this->getLayoutElementsFromUiConfiguration([$referenceName => $config], $elements);
        foreach ($elements as $layoutElement) {
            $layoutElement = simplexml_load_string(
                $layoutElement,
                Element::class
            );
            $this->readerPool->interpret($readerContext, $layoutElement);
        }

        return $this;
    }

    /**
     * Find layout elements in UI configuration for correct layout generation
     *
     * @param array $config
     * @param array $elements
     * @return void
     */
    private function getLayoutElementsFromUiConfiguration(array $config, array &$elements = [])
    {
        foreach ($config as $data) {
            if (isset($data['arguments']['block']['layout'])) {
                $elements[] = $data['arguments']['block']['layout'];
            }
            if (isset($data['children']) && !empty($data['children'])) {
                $this->getLayoutElementsFromUiConfiguration($data['children'], $elements);
            }
        }
    }

    /**
     * Get ui component attributes
     *
     * @param Element $element
     * @return array
     */
    protected function getAttributes(Element $element)
    {
        $attributes = [];
        foreach ($this->attributes as $attributeName) {
            $attributes[$attributeName] = (string)$element->getAttribute($attributeName);
        }

        return $attributes;
    }
}
