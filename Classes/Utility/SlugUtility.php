<?php

declare(strict_types=1);

namespace Ig\IgSlug\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SlugUtility
{
    protected $slugHelper;

    protected $table;

    protected $slugFieldName;

    protected $slugLockedFieldName = null;

    protected $hasToBeUniqueInSite;

    protected $hasToBeUniqueInPid;

    protected $hasToBeUniqueInTable;

    protected $fieldNamesToShow;

    protected $flags = [];

    /**
     * Instantiate the form protection before a simulated user is initialized.
     */
    public function __construct(
        string $table,
        string $slugFieldName,
        ?string $slugLockedFieldName,
        array $fieldNamesToShow,
        array $siteLanguages
    ) {
        $this->table = $table;
        $this->slugFieldName = $slugFieldName;
        $this->slugLockedFieldName = $slugLockedFieldName;
        $this->fieldNamesToShow = $fieldNamesToShow;
        // get info of slug field
        $fieldConfig = $GLOBALS['TCA'][$this->table]['columns'][$this->slugFieldName]['config'];
        $evalInfo = empty($fieldConfig['eval']) ? [] : GeneralUtility::trimExplode(',', $fieldConfig['eval'], true);
        $this->hasToBeUniqueInSite = in_array('uniqueInSite', $evalInfo, true);
        $this->hasToBeUniqueInPid = in_array('uniqueInPid', $evalInfo, true);
        $this->hasToBeUniqueInTable = in_array('unique', $evalInfo, true);
        $this->slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            $this->table,
            $this->slugFieldName,
            $fieldConfig
        );
        foreach ($siteLanguages as $siteLanguageUid => $siteLanguage) {
            $this->flags[$siteLanguageUid] = $siteLanguage->getFlagIdentifier();
        }
    }

    /**
     * builds slug changes entry for output from database entry
     *
     * @param array $record DB Entry
     * @param int $depth tree deepth
     * @param bool $parentHasUpdates parents needs updates
     */
    public function getEntryByRecord($record, int $depth = 0, bool $parentHasUpdates = false): array
    {
        $recordId = (int)$record['uid'];
        $pid = (int)$record['pid'];

        if ($pid === -1) {
            $pid = $this->getLiveVersionPid($record['t3ver_oid']);
        }

        $slugLocked = $this->slugLockedFieldName !== null && isset($record[$this->slugLockedFieldName])
                    && $record[$this->slugLockedFieldName] == 1;
        if ($slugLocked) {
            $slug = $record[$this->slugFieldName];
        } else {
            $slug = $this->slugHelper->generate($record, $pid);

            $state = RecordStateFactory::forName($this->table)->fromArray($record, $pid, $recordId);
            if ($this->hasToBeUniqueInSite && !$this->slugHelper->isUniqueInSite($slug, $state)) {
                $slug = $this->slugHelper->buildSlugForUniqueInSite($slug, $state);
            }

            if ($this->hasToBeUniqueInPid && !$this->slugHelper->isUniqueInPid($slug, $state)) {
                $slug = $this->slugHelper->buildSlugForUniqueInPid($slug, $state);
            }

            if ($this->hasToBeUniqueInTable && !$this->slugHelper->isUniqueInTable($slug, $state)) {
                $slug = $this->slugHelper->buildSlugForUniqueInTable($slug, $state);
            }
        }

        $entry = [
            'uid' => $recordId,
            'updated' => $slug != $record[$this->slugFieldName],
            'parentUpdated' => $parentHasUpdates,
            'newSlug' => $slug,
            'slug' => $record[$this->slugFieldName],
            'slugLocked' => $slugLocked,
            'slugFieldValues' => '',
            'depth' => $depth,
            'depthLast' => false,
        ];
        if ($this->table == 'pages') {
            // Attributes for page tree
            $tcaCtrl = $GLOBALS['TCA'][$this->table]['ctrl'];
            $typeiconColumn = $GLOBALS['TCA'][$this->table]['ctrl']['typeicon_column'];
            $entry[$typeiconColumn] = $record[$typeiconColumn];
            $entry['nav_hide'] = $record['nav_hide'];
            $entry['is_siteroot'] = $record['is_siteroot'];
            $entry['module'] = $record['module'];

            if (isset($GLOBALS['TCA'][$this->table]['ctrl']['languageField'])) {
                $languageId = (int)$record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']];
                $entry[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']] = $languageId;
                $entry['flag'] = $this->flags[$languageId] ?? 'flags-multiple';
            }

            if (isset($tcaCtrl['enablecolumns']) && is_array($tcaCtrl['enablecolumns'])) {
                foreach ($tcaCtrl['enablecolumns'] as $name) {
                    $entry[$name] = $record[$name];
                }
            }

            // Create entries for output
            $depthHTML = '';
            for ($d = $depth; $d > 0; --$d) {
                $depthHTML = '<span class="treeline-icon treeline-icon-clear"></span>' . $depthHTML;
            }

            $entry['depthHTML'] = $depthHTML;
            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
            $iconHtml = $iconFactory->getIconForRecord('pages', $entry, Icon::SIZE_SMALL)->render();
            $entry['iconWithLink'] = BackendUtility::wrapClickMenuOnIcon($iconHtml, 'pages', $record['uid']);
            /*
             */
        }

        foreach ($this->fieldNamesToShow as $fieldName) {
            if ($entry['slugFieldValues'] && $record[$fieldName]) {
                $entry['slugFieldValues'] .= ', ';
            }

            $entry['slugFieldValues'] .= $record[$fieldName];
            $entry[$fieldName] = $record[$fieldName];
        }

        return $entry;
    }

    public function updateEntry(array $entry): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->update($this->table)
                     ->where(
                         $queryBuilder->expr()
->eq('uid', $queryBuilder->createNamedParameter($entry['uid'], \PDO::PARAM_INT))
                     )
                     ->set($this->slugFieldName, $entry['newSlug'])
                     ->executeStatement();
        // Delete runtime Cache for rootline -> slug generate
        $runtimeCache = GeneralUtility::makeInstance(CacheManager::class)->getCache('runtime');
        $runtimeCache->set('backendUtilityPageForRootLine', []);
        $runtimeCache->set('backendUtilityBeGetRootLine', []);
    }

    protected function getLiveVersionPid(int $t3verOid): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()
->removeAll()
->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder
                     ->select('pid')
                     ->from('pages')
                     ->where(
                         $queryBuilder->expr()
->eq('uid', $queryBuilder->createNamedParameter($t3verOid, \PDO::PARAM_INT))
                     )->executeQuery()
                     ->fetchOne();
    }
}
