<?php

declare(strict_types = 1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Ig\IgSlug\Utility;

/*
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
*/

/**
 * Generates, sanitizes and validates slugs for a TCA field
 */
class SlugHelper extends \TYPO3\CMS\Core\DataHandling\SlugHelper
{


    /**
     * Ensure root line caches are flushed to avoid any issue regarding moving of pages or dynamically creating
     * sites while managing slugs at the same request
     */
    protected function flushRootLineCaches(): void
    {
        // Changes: it is just too slow for multi domain sites with many slug conflicts - but uncommenting is also not the best idea [Daniel Abplanalp 2019-09-27]
        // @todo check if this is still the case
        //$cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        //$cacheManager->getCache('runtime')->flushByTag(RootlineUtility::RUNTIME_CACHE_TAG);
        //$cacheManager->getCache('rootline')->flush();
    }

}
