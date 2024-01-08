<?php

declare(strict_types=1);

namespace Ig\IgSlug\Exception;

use TYPO3\CMS\Core\Exception;

/**
 * An exception when backend user has no permission to modify slug field of the table
 */
class AccessDeniedException extends Exception
{
}
