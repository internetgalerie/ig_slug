<?php
declare(strict_types = 1);
namespace Ig\IgSlug\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\Database\Query\Restriction\BackendWorkspaceRestriction;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

class SlugUtility
{
    protected $slugHelper;
    protected $table;
    protected $slugFieldName;
    protected $slugLockedFieldName=null;
    protected $hasToBeUniqueInSite;
    protected $hasToBeUniqueInPid;
    protected $fieldNamesToShow;
  
    /**
     * Instantiate the form protection before a simulated user is initialized.
     *
     * @param string $table
     * @param string $slugFieldName
     * @param string $slugLockedFieldName
     * @param array $fieldNamesToShow
     */
    public function __construct(string $table, string $slugFieldName, string $slugLockedFieldName=null, array $fieldNamesToShow)
    {
        $this->table=$table;
        $this->slugFieldName=$slugFieldName;
        $this->slugLockedFieldName=$slugLockedFieldName;
        $this->fieldNamesToShow=$fieldNamesToShow;
        // get info of slug field
        $fieldConfig = $GLOBALS['TCA'][$this->table]['columns'][$this->slugFieldName]['config'];
        $evalInfo = !empty($fieldConfig['eval']) ? GeneralUtility::trimExplode(',', $fieldConfig['eval'], true) : [];
        $this->hasToBeUniqueInSite = in_array('uniqueInSite', $evalInfo, true);
        $this->hasToBeUniqueInPid = in_array('uniqueInPid', $evalInfo, true);
        $this->slugHelper = GeneralUtility::makeInstance(SlugHelper::class, $this->table, $this->slugFieldName, $fieldConfig);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_language');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $result=$queryBuilder->select('*')->from('sys_language') ->execute();
        $this->flags=[];
        while ($row = $result->fetch()) {
            $this->flags[$row['uid']]=$row['flag'];
        }
    }

    /**
     * builds slut changes entry for output from database entry
     *
     * @param array $record DB Entry
     * @param int $depth tree deepth
     * @param bool $parentHasUpdates parents needs updates
     * @return array
     */
    public function getEntryByRecord($record, int $depth=0, bool $parentHasUpdates=false)
    {
        $recordId = (int)$record['uid'];
        $pid = (int)$record['pid'];

        if ($pid === -1) {
            $pid= $this->getLiveVersionPid($record['t3ver_oid']);
        }
        $slugLocked= isset($this->slugLockedFieldName) && $record[$this->slugLockedFieldName]==1;
        if ($slugLocked) {
            $slug=$record[$this->slugFieldName];
        } else {
            $slug = $this->slugHelper->generate($record, $pid);
        
            $state = RecordStateFactory::forName($this->table)->fromArray($record, $pid, $recordId);
            if ($this->hasToBeUniqueInSite && !$this->slugHelper->isUniqueInSite($slug, $state)) {
                $slug = $this->slugHelper->buildSlugForUniqueInSite($slug, $state);
            }
            if ($this->hasToBeUniqueInPid && !$this->slugHelper->isUniqueInPid($slug, $state)) {
                $slug = $this->slugHelper->buildSlugForUniqueInPid($slug, $state);
            }
        }
        $entry=[
            'uid' => $recordId,
            'updated' => $slug!=$record[$this->slugFieldName],
            'parentUpdated' => $parentHasUpdates,
            'newSlug' =>$slug,
            'slug' => $record[$this->slugFieldName],
            'slugLocked' => $slugLocked,
            'slugFieldValues' => '',
            'depth' => $depth,
            'depthLast' => false
        ];
        if ($this->table=='pages') {
            // Attributes for page tree
            $tcaCtrl = $GLOBALS['TCA'][$this->table]['ctrl'];
            $typeicon_column=$GLOBALS['TCA'][$this->table]['ctrl']['typeicon_column'];
            $entry[$typeicon_column]= $record[$typeicon_column];
            $entry['nav_hide'] = $record['nav_hide'];
            $entry['is_siteroot'] = $record['is_siteroot'];
            $entry['module'] = $record['module'];

            if ($GLOBALS['TCA'][$this->table]['ctrl']['languageField']) {
                $languageId = (int)$record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']];
                $pageIdInDefaultLanguage = $languageId > 0 ? (int)$record['l10n_parent'] : $recordId;
                $entry[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']]=$languageId;
                $entry['flag']= isset($this->flags[$languageId]) ? 'flags-' . $this->flags[$languageId] : 'flags-multiple';
            }

            if (isset($tcaCtrl['enablecolumns']) && is_array($tcaCtrl['enablecolumns'])) {
                foreach ($tcaCtrl['enablecolumns'] as $name) {
                    $entry[$name] = $record[$name];
                }
            }
            // Create entries for output
            $depthHTML='';
            for ($d=$depth;$d>0;$d--) {
                $depthHTML = '<span class="treeline-icon treeline-icon-clear"></span>' . $depthHTML;
            }
            $entry['depthHTML']=$depthHTML;
            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
            $iconHtml = $iconFactory->getIconForRecord('pages', $entry, Icon::SIZE_SMALL)->render();
            $entry['iconWithLink'] = BackendUtility::wrapClickMenuOnIcon($iconHtml, 'pages', $record['uid']);
            /*
             */
        }
    
        foreach ($this->fieldNamesToShow as $fieldName) {
            if ($entry['slugFieldValues'] &&  $record[$fieldName]) {
                $entry['slugFieldValues'] .= ', ';
            }
            $entry['slugFieldValues'] .= $record[$fieldName];
            $entry[$fieldName] = $record[$fieldName];
        }
        return $entry;
    }

    public function updateEntry($entry)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->update($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($entry['uid'], \PDO::PARAM_INT)
                )
            )
            ->set($this->slugFieldName, $entry['newSlug'])
            ->execute();
        // Delete runtime Cache for rootline -> slug generate
        $runtimeCache = GeneralUtility::makeInstance(CacheManager::class)->getCache('cache_runtime');
        $runtimeCache->set('backendUtilityPageForRootLine', []);
        $runtimeCache->set('backendUtilityBeGetRootLine', []);
    }
  
    protected function getLiveVersionPid($t3ver_oid)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $liveVersion = $queryBuilder
                     ->select('pid')
                     ->from('pages')
                     ->where(
                         $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($t3ver_oid, \PDO::PARAM_INT))
                     )->execute()->fetch();
        return (int)$liveVersion['pid'];
    }
}
