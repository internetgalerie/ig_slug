<?php

declare(strict_types=1);

namespace Ig\IgSlug\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
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

            if ($GLOBALS['TCA'][$this->table]['ctrl']['languageField']) {
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
            $iconSize = class_exists(IconSize::class) ? IconSize::SMALL : Icon::SIZE_SMALL;
            $iconHtml = $iconFactory->getIconForRecord('pages', $entry, $iconSize)->render();
            $entry['iconWithLink'] = BackendUtility::wrapClickMenuOnIcon($iconHtml, 'pages', $record['uid']);
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

    /**
     * Update the slug entry in the database
     *
     * Delete runtime Cache for rootline -> slug generate
     *
     * @param array $entry The entry to update
     */
    public function updateEntry(array $entry): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->update($this->table)
            ->where(
                $queryBuilder->expr()
                    ->eq('uid', (int)$entry['uid'])
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
                $queryBuilder->expr()->eq('uid', $t3verOid)
            )->executeQuery()
            ->fetchOne();
    }

    /**
     * Create redirects for changes of slug
     *
     * @param array $entry The entry to update
     */
    public function createRedirects(array $entry): int
    {
        // Autoredirect for new slugs for table pages only
        if($this->table !== 'pages') {
            return 0;
        }
        // Create redirects for changes of slug
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($entry['uid']);
        $language = $site->getLanguageById($entry['sys_language_uid']);
        $baseUrl = $language->getBase()->getHost();
        $languagePath = rtrim($language->getBase()->getPath(), '/');
        $linkService = GeneralUtility::makeInstance(LinkService::class);
        $linkDetails = [
            'type' => LinkService::TYPE_PAGE,
            'pageuid' => $entry['uid'],
            'parameters' => '_language=' . $entry['sys_language_uid']
        ];
        $target = $linkService->asString($linkDetails);

        $slugVariants = $this->getSlugVariants($entry['slug'], $site, $languagePath);

        $created = 0;
        foreach ($slugVariants as $slugVariant) {
            $created += $this->upsertRedirect($slugVariant, $baseUrl, $target);
        }

        return $created;
    }

    /**
     * Returns all slug variants to redirect, including suffixes from PageTypeSuffix enhancers.
     * Only suffixes mapping to page type 0 (default page) are included.
     *
     * @param string $slug The base slug
     * @param Site $site
     * @return string[]
     */
    private function getSlugVariants(string $slug, Site $site, string $languagePath): array
    {
        // Normalize: ensure single leading slash, no trailing slash
        $slug = $languagePath . '/' . trim($slug, '/');

        $variants = [$slug]; // always include bare slug

        $routeEnhancers = $site->getConfiguration()['routeEnhancers'] ?? [];

        foreach ($routeEnhancers as $enhancer) {
            // Only handle PageType enhancers
            if (($enhancer['type'] ?? '') !== 'PageType') {
                continue;
            }

            $default = $enhancer['default'] ?? '';
            $map = $enhancer['map'] ?? [];

            foreach ($map as $suffix => $pageType) {
                $suffix = (string)$suffix;

                if ($suffix === '' || (int)$pageType !== 0) {
                    continue;
                }

                $variant = $slug . $suffix;
                if (!in_array($variant, $variants, true)) {
                    $variants[] = $variant;
                }
            }

            // Also add the default suffix explicitly if it's not empty and not already covered
            if ($default !== '' && $default !== '/') {
                $defaultVariant = $slug . $default;
                if (!in_array($defaultVariant, $variants, true)) {
                    $variants[] = $defaultVariant;
                }
            }
        }

        return $variants;
    }

    /**
     * Insert or update a single redirect record.
     *
     * @return int 1 if a record was inserted/updated, 0 on skip
     */
    private function upsertRedirect(string $sourcePath, string $sourceHost, string $target): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_redirect');
        $queryBuilder = $connection->createQueryBuilder();

        $existing = $queryBuilder
            ->select('uid')
            ->from('sys_redirect')
            ->where(
                $queryBuilder->expr()->eq(
                    'source_path',
                    $queryBuilder->createNamedParameter($sourcePath, Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'source_host',
                    $queryBuilder->createNamedParameter($sourceHost, Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchOne();

        $now = time();

        // Fire own BeforeRedirectPersistedEvent (instead of ModifyAutoCreateRedirectRecordBeforePersistingEvent)

        if ($existing) {
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->update('sys_redirect')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter((int)$existing, Connection::PARAM_INT)
                    )
                )
                ->set('target', $target)
                ->set('target_statuscode', 301)
                ->set('updatedon', $now)
                ->executeStatement();
        } else {
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder
                ->insert('sys_redirect')
                ->values([
                    'pid' => 0,
                    'updatedon' => $now,
                    'createdon' => $now,
                    'source_path' => $sourcePath,
                    'source_host' => $sourceHost,
                    'target' => $target,
                    'target_statuscode' => 301,
                    //'creation_type' => 0,
                    'description' => 'Generated by ig_slug',
                ])
                ->executeStatement();
        }
        // Fire own AfterRedirectPersistedEvent (instead of AfterAutoCreateRedirectHasBeenPersistedEvent)
        return 1;
    }
}
