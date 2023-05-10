<?php
declare(strict_types = 1);

namespace Ig\IgSlug\Controller;

use Ig\IgSlug\Utility\SlugsUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\View\Exception;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Type\Bitmask\Permission;


class SlugController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected array $search = [];
    protected int $depth = 0; // get from .....
    protected ?ModuleData $moduleData = null;
    protected ModuleTemplate $moduleTemplate;

    
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly BackendUriBuilder $backendUriBuilder,
        protected readonly PageRenderer $pageRenderer,
    ) {
    }

    /**
     * Init module state.
     * This isn't done within __construct() since the controller
     * object is only created once in extbase when multiple actions are called in
     * one call. When those change module state, the second action would see old state.
     */
    public function initializeAction(): void
    {
        $this->moduleData = $this->request->getAttribute('moduleData');
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->moduleTemplate->setTitle(LocalizationUtility::translate('LLL:EXT:ig_slug/Resources/Private/Language/locallang_rebuild.xlf:mlang_labels_tablabel'));
        $this->moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
    }


    /**
     * Assign default variables to ModuleTemplate view
     */
    protected function initializeView(): void
    {
        // Load requireJS modules, CSS
        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/modal.js');
        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/context-menu.js');
        $this->pageRenderer->loadJavaScriptModule('@ig/igslug/slug-confirm.js');
        $this->pageRenderer->addCssFile('EXT:ig_slug/Resources/Public/Css/ig_slug_be.css');
        //$this->pageRenderer->addInlineLanguageLabelFile('EXT:lang/Resources/Private/Language/locallang_core.xlf');
        //$this->pageRenderer->addInlineLanguageLabelFile('EXT:ig_slug/Resources/Private/Language/locallang.xlf');
    }
    
    /**
     * Initializes the backend module by setting internal variables, initializing the menu.
     */
    protected function init(): void
    {
        $this->id = (int)($this->request->getQueryParams()['id'] ?? $this->request->getParsedBody()['id'] ?? 0);
        $this->initializeSiteLanguages();
        $this->slugsUtility = GeneralUtility::makeInstance(SlugsUtility::class, $this->siteLanguages);

        $this->slugTables = $this->slugsUtility->getSlugTables();
        if ($this->request->hasArgument('search')) {
            $this->search = $this->request->getArgument('search');
        } else {
            $this->search = (array)$this->moduleData->get('search', []);
        }

    
        if (isset($this->search['table'])) {
            $activeTable = $this->search['table'];
            if (!isset($this->slugTables[$activeTable])) {
                throw new Exception(sprintf('access rights are missing on "%s"', $activeTable), 1549656272);
            }
            $this->slugTable = $this->slugTables[$activeTable];
        } else {
            $this->slugTable = reset($this->slugTables);
        }
        $this->activeTable = $this->slugTable['table'];

        $this->lang = isset($this->search['lang'])  && $this->search['lang'] != '' ? intval($this->search['lang']) : null;
        $this->depth = isset($this->search['depth']) ? intval($this->search['depth']) : 0;

        $this->slugsUtility->setTable($this->slugTable['table']);
        $this->slugsUtility->setSlugFieldName($this->slugTable['slugFieldName']);
        $this->slugsUtility->setSlugLockedFieldName($this->slugTable['slugLockedFieldName']);
    }
    
    /*
     * Action: list
     */
    public function listAction(): ResponseInterface
    {
        $this->init();
        $this->moduleData->set('search', $this->search);

        $this->getBackendUser()->pushModuleData($this->moduleData->getModuleIdentifier(), $this->moduleData->toArray());

        $filterMenus = $this->modMenu();
    
        $fields = $this->slugsUtility->getSlugFields();
        $this->slugsUtility->setFieldNamesToShow($fields);

        if ($this->slugTable['table'] == 'pages') {
            $entries = $this->slugsUtility->viewSlugsByUidRecursive([$this->id], $this->depth, $this->lang);
        } else {
            $pageUids = $this->slugsUtility->getPageRecordsRecursive($this->id, $this->depth, [$this->id]);
            $entries = $this->slugsUtility->viewSlugs($pageUids, $this->lang);
        }

        // show pageinfo in header right
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)) ?: [];
        // The page will show only if there is a valid page and if this page
        // may be viewed by the user
        if ($this->pageinfo !== []) {
            $this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($this->pageinfo);
        }

        // own menu item or subitem of web info
        $disableOwnMenuItem = (int)(GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ig_slug')['disableOwnMenuItem'] ?? 0);        
        if ($disableOwnMenuItem) {
            // show menu in web info module
            $this->moduleTemplate->makeDocHeaderModuleMenu(['id' => $this->id]);
            $routeName = 'web_info_IgSlug';
        } else {
            $routeName = 'web_IgSlug';
        }

        
        $this->moduleTemplate->assignMultiple([
            'search' => $this->search,
            'filterMenus' => $filterMenus,
            'fields' => $fields,
            'entries' => $entries,
            'slugTables' => $this->slugTables,
            'activeTable' => $this->activeTable,
            'pageUid' => $this->id,
            'rebuildUrl' => $this->backendUriBuilder->buildUriFromRoute($routeName, [
                'id' => $this->id,
                'search' => $this->search,
                'action' => 'update',
            ]),
        ]);
        /*
        // as main menu
        $this->uriBuilder->setRequest($this->request);
        $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('IgSlugModuleMenu');
        $menu->addMenuItem(
            $menu->makeMenuItem()
                 ->setTitle(LocalizationUtility::translate('LLL:EXT:ig_slug/Resources/Private/Language/locallang.xlf:igSlug.populateSlug', 'ig_slug'))
                 ->setHref($this->uriBuilder->uriFor('update'))
                 ->setActive(true)
        );
        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        */
        return $this->moduleTemplate->renderResponse('Slug/List');
    }

      
    /*
     * Action: update
     */
    public function updateAction(): ResponseInterface
    {
        $this->init();
        if ($this->activeTable == 'pages') {
            $pagesCount = $this->slugsUtility->populateSlugsByUidRecursive([$this->id], $this->depth, $this->lang);
        } else {
            $pageUids = $this->slugsUtility->getPageRecordsRecursive($this->id, $this->depth, [$this->id]);
            $pagesCount = $this->slugsUtility->populateSlugs($pageUids, $this->lang);
        }
        $this->addFlashMessage(LocalizationUtility::translate($pagesCount != 1 ? 'igSlug.populatedSlugs' : 'igSlug.populatedSlug', 'ig_slug', [$pagesCount]));

        return new ForwardResponse('list');
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
