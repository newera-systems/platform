<?php

namespace Oro\Bundle\FilterBundle\Tests\Unit\Form\Type\Filter;

use Oro\Bundle\FilterBundle\Tests\Unit\Form\Type\AbstractTypeTestCase;
use Oro\Bundle\FilterBundle\Form\Type\Filter\DateRangeFilterType;
use Oro\Bundle\FilterBundle\Form\Type\DateRangeType;
use Oro\Bundle\FilterBundle\Form\Type\Filter\FilterType;

class DateRangeFilterTypeTest extends AbstractTypeTestCase
{
    /**
     * @var DateRangeFilterType
     */
    private $type;

    protected function setUp()
    {
        parent::setUp();
        $translator = $this->createMockTranslator();
        $this->type = new DateRangeFilterType($translator);
        $this->factory->addType(new DateRangeType($translator));
        $this->factory->addType(new FilterType($translator));
    }

    /**
     * {@inheritDoc}
     */
    protected function getTestFormType()
    {
        return $this->type;
    }

    public function testGetName()
    {
        $this->assertEquals(DateRangeFilterType::NAME, $this->type->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function setDefaultOptionsDataProvider()
    {
        return array(
            array(
                'defaultOptions' => array(
                    'field_type' => DateRangeType::NAME,
                    'operator_choices' => array(
                        DateRangeFilterType::TYPE_BETWEEN => 'label_date_type_between',
                        DateRangeFilterType::TYPE_NOT_BETWEEN => 'label_date_type_not_between',
                    )
                )
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function bindDataProvider()
    {
        return array(
            'empty' => array(
                'bindData' => array(),
                'formData' => array('type' => null, 'value' => array('start' => '', 'end' => '')),
                'viewData' => array('type' => null, 'value' => array('start' => '', 'end' => '')),
            ),
        );
    }
}
