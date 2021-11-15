<?php
declare(strict_types = 1);
namespace Ig\IgSlug\Exception;

use TYPO3\CMS\Core\Exception;

/**
 * An exception when table is not found in TCA
 */
class TableNotFoundException extends Exception
{
}
