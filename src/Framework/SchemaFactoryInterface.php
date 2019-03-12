<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\GraphQl\Framework;

use GraphQL\Type\Schema;


/**
 * Class SchemaFactory
 *
 * @package OxidProfessionalServices\GraphQl\Core\Schema
 */
interface SchemaFactoryInterface
{

    /**
     * @return Schema
     */
    public function getSchema(): Schema;
}
