<?php
declare(strict_types = 1);
namespace Ig\IgSlug\Exception;

use TYPO3\CMS\Core\Exception;

/**
 * An exception when slug field ist not found for table in TCA
 */
class SlugNotFoundException extends Exception
{
}
