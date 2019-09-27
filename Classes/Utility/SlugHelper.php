<?php
declare(strict_types = 1);
namespace Ig\IgSlug\Utility;

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

use Doctrine\DBAL\Connection;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordState;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

/**
 * Generates, sanitizes and validates slugs for a TCA field
 */
class SlugHelper extends \TYPO3\CMS\Core\DataHandling\SlugHelper
{


    /**
     * Check if there are other records with the same slug that are located on the same site.
     *
     * @param string $slug
     * @param RecordState $state
     * @return bool
     * @throws \TYPO3\CMS\Core\Exception\SiteNotFoundException
     */
    public function isUniqueInSite(string $slug, RecordState $state): bool
    {
        $pageId = $state->resolveNodeAggregateIdentifier();
        $recordId = $state->getSubject()->getIdentifier();
        $languageId = $state->getContext()->getLanguageId();

        if (!MathUtility::canBeInterpretedAsInteger($pageId)) {
            // If this is a new page, we use the parent page to resolve the site
            $pageId = $state->getNode()->getIdentifier();
        }
        $pageId = (int)$pageId;

        if ($pageId < 0) {
            $pageId = $this->resolveLivePageId($recordId);
        }
        $queryBuilder = $this->createPreparedQueryBuilder();
        $this->applySlugConstraint($queryBuilder, $slug);
        $this->applyRecordConstraint($queryBuilder, $recordId);
        $this->applyLanguageConstraint($queryBuilder, $languageId);
        $this->applyWorkspaceConstraint($queryBuilder);
        $statement = $queryBuilder->execute();

        $records = $this->resolveVersionOverlays(
            $statement->fetchAll()
        );
        if (count($records) === 0) {
            return true;
        }
        //return true;

        // The installation contains at least ONE other record with the same slug
        // Now find out if it is the same root page ID
        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
        // Changes: it is just too slow for multi domain sites with many slug conflicts - but uncommenting is also not the best idea [Daniel Abplanalp 2019-09-27]
        //$siteMatcher->refresh();
        $siteOfCurrentRecord = $siteMatcher->matchByPageId($pageId);
        foreach ($records as $record) {
            try {
                $recordState = RecordStateFactory::forName($this->tableName)->fromArray($record);
                $siteOfExistingRecord = $siteMatcher->matchByPageId(
                    (int)$recordState->resolveNodeAggregateIdentifier()
                );
            } catch (SiteNotFoundException $exception) {
                // In case not site is found, the record is not
                // organized in any site or pseudo-site
                continue;
            }
            if ($siteOfExistingRecord->getRootPageId() === $siteOfCurrentRecord->getRootPageId()) {
                return false;
            }
        }

        // Otherwise, everything is still fine
        return true;
    }



}
