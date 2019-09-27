<?php
declare(strict_types = 1);
namespace Ig\IgSlug\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3Fluid\Fluid\View\Exception;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;

class SlugController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected $search=[];
    protected $depth=0; // get from .....

    /**
     * Initializes the backend module by setting internal variables, initializing the menu.
     */
    protected function init()
    {
        $this->id = (int)GeneralUtility::_GP('id');
        $this->objectManager=GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        $this->initializeSiteLanguages();
        $this->slugsUtility= $this->objectManager->get(\Ig\IgSlug\Utility\SlugsUtility::class, $this->siteLanguages);
        $this->slugTables=$this->slugsUtility->getSlugTables();
        $this->perms_clause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        if ($this->request->hasArgument('search')) {
            $this->search = $this->request->getArgument('search');
        }

    
        if (isset($this->search['table'])) {
            $activeTable = $this->search['table'];
            if (!isset($this->slugTables[$activeTable])) {
                throw new Exception(sprintf('access rights are missing on "%s"', $activeTable), 1549656272);
            }
            $this->slugTable=$this->slugTables[$activeTable];
        } else {
            $this->slugTable=reset($this->slugTables);
        }
        $this->activeTable=$this->slugTable['table'];
        // Why not in PageRendererViewHelper.php argument addInlineLanguageLabelFile
        $pageRenderer=GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineLanguageLabelFile('EXT:lang/Resources/Private/Language/locallang_core.xlf');
        $pageRenderer->addInlineLanguageLabelFile('EXT:ig_slug/Resources/Private/Language/locallang.xlf');

        $this->lang = isset($this->search['lang'])  && $this->search['lang']!='' ? intval($this->search['lang']) : null;
        $this->depth = isset($this->search['depth']) ? intval($this->search['depth']) : 0;


        $this->slugsUtility->setTable($this->slugTable['table']);
        $this->slugsUtility->setSlugFieldName($this->slugTable['slugFieldName']);
        $this->slugsUtility->setSlugLockedFieldName($this->slugTable['slugLockedFieldName']);
    
        // $BE_USER->check('tables_modify', 'pages');
        //$BE_USER->check('non_exclude_fields', this->table . ':' . $this->fieldName);
    }
    public function main()
    {
        $this->view = $this->getFluidTemplateObject();
        $this->search = GeneralUtility::_GP('search');
        $this->update=(int)GeneralUtility::_GP('update');
        $this->init();
        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/IgSlug/Confirm');
        $this->moduleTemplate->getPageRenderer()->addCssFile('EXT:ig_slug/Resources/Public/Css/ig_slug_be.css');
        $tableTitle = $this->slugTable['title'];
      
        $filterMenus=$this->modMenu();

        $this->view->assign('filterMenus', $filterMenus);
        $this->view->assign('search', $this->search);
      
        $fields=$this->slugsUtility->getSlugFields();
        $this->slugsUtility->setFieldNamesToShow($fields);
        $this->view->assign('fields', $fields);
        if ($this->update) {
            if ($this->slugTable['table']=='pages') {
                $pagesCount=$this->slugsUtility->populateSlugsByUidRecursive([$this->id], $this->depth, $this->lang);
            } else {
                $pageUids=$this->slugsUtility->getPageRecordsRecursive($this->id, $this->depth, [$this->id]);
                $pagesCount=$this->slugsUtility->populateSlugs($pageUids, $this->lang);
            }

            $message=\TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate($pagesCount!=1 ? 'igSlug.populatedSlugs' : 'igSlug.populatedSlug', 'ig_slug', [$pagesCount]);
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                '',
                $message,
                FlashMessage::OK
            );
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
        }
        if ($this->slugTable['table']=='pages') {
            $entries=$this->slugsUtility->viewSlugsByUidRecursive([$this->id], $this->depth, $this->lang);
        } else {
            $pageUids=$this->slugsUtility->getPageRecordsRecursive($this->id, $this->depth, [$this->id]);
            $entries=$this->slugsUtility->viewSlugs($pageUids, $this->lang);
        }
      
        $this->view->assign('entries', $entries);
        $this->view->assign('slugTables', $this->slugTables);
        $this->view->assign('activeTable', $this->activeTable);
      
        $this->view->assign('pageUid', $this->id);
        return $this->view->render();
    }
    
    /*
     * Action: list
     *
     * @return void
     */
    public function listAction()
    {
        $this->init();
        $tableTitle = $this->slugTable['title'];

        $filterMenus=$this->modMenu();
        $this->view->assign('filterMenus', $filterMenus);
        $this->view->assign('search', $this->search);
    
        $fields=$this->slugsUtility->getSlugFields();
        $this->slugsUtility->setFieldNamesToShow($fields);
        $this->view->assign('fields', $fields);

        if ($this->slugTable['table']=='pages') {
            $entries=$this->slugsUtility->viewSlugsByUidRecursive([$this->id], $this->depth, $this->lang);
        } else {
            $pageUids=$this->slugsUtility->getPageRecordsRecursive($this->id, $this->depth, [$this->id]);
            $entries=$this->slugsUtility->viewSlugs($pageUids, $this->lang);
        }
    
        $this->view->assign('entries', $entries);
        $this->view->assign('slugTables', $this->slugTables);
        $this->view->assign('activeTable', $this->activeTable);

        $this->view->assign('pageUid', $this->id);
    }

      
    /*
     * Action: update
     *
     * @return void
     */
    public function updateAction()
    {
        $this->init();
        if ($this->activeTable=='pages') {
            $pagesCount=$this->slugsUtility->populateSlugsByUidRecursive([$this->id], $this->depth, $this->lang);
        } else {
            $pageUids=$this->slugsUtility->getPageRecordsRecursive($this->id, $this->depth, [$this->id]);
            $pagesCount=$this->slugsUtility->populateSlugs($pageUids, $this->lang);
        }
        $this->addFlashMessage(\TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate($pagesCount!=1 ? 'igSlug.populatedSlugs' : 'igSlug.populatedSlug', 'ig_slug', [$pagesCount]));

        $this->forward('list');
    }



    protected function modMenu()
    {
        $lang = $this->getLanguageService();
        $menuArray = [
            'depth' => [
                0 => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_0'),
                1 => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_1'),
                2 => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_2'),
                3 => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_3'),
                4 => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_4'),
                999 => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_infi')
            ],
            'show' => [
                0 => $lang->sL('LLL:EXT:ig_slug/Resources/Private/Language/locallang.xlf:igSlug.all'),
                1 => $lang->sL('LLL:EXT:ig_slug/Resources/Private/Language/locallang.xlf:igSlug.changes'),

            ]
        
        ];
        // Languages:
        $menuArray['lang'] = [];
        foreach ($this->siteLanguages as $language) {
            $menuArray['lang'][$language->getLanguageId()] = $language->getTitle();
        }
        return $menuArray;
    }

    /**
     * returns a new standalone view, shorthand function
     *
     * @return StandaloneView
     */
    protected function getFluidTemplateObject()
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths([GeneralUtility::getFileAbsFileName('EXT:ig_slug/Resources/Private/Layouts')]);
        $view->setPartialRootPaths([GeneralUtility::getFileAbsFileName('EXT:ig_slug/Resources/Private/Partials')]);
        $view->setTemplateRootPaths([GeneralUtility::getFileAbsFileName('EXT:ig_slug/Resources/Private/Templates')]);

        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:ig_slug/Resources/Private/Templates/Slug/Index.html'));

        $view->getRequest()->setControllerExtensionName('ig_slug');
        $this->request=$view->getRequest();
        $this->id = (int)GeneralUtility::_GP('id');

        return $view;
    }

 
    /**
     * Since the AbstractFunctionModule cannot access the current request yet, we'll do it "old school"
     * to fetch the Site based on the current ID.
     */
    protected function initializeSiteLanguages()
    {
        /** @var SiteInterface $currentSite */
        $currentSite = $GLOBALS['TYPO3_REQUEST']->getAttribute('site');
        $this->siteLanguages = $currentSite->getAvailableLanguages($this->getBackendUser(), false, (int)$this->id);
    }
 

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
