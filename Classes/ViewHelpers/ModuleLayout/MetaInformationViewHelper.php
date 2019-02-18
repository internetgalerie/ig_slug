<?php
declare(strict_types = 1);
namespace Ig\IgSlug\ViewHelpers\ModuleLayout;

// a pageUid attribute in ModuleLayoutViewHelper.php would be nicer

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;
use TYPO3\CMS\Backend\ViewHelpers\ModuleLayoutViewHelper;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3Fluid\Fluid\View\Exception;


class MetaInformationViewHelper extends AbstractViewHelper {
    use CompileWithRenderStatic;


    /**
     * Initializes the arguments
     *
     * @throws \TYPO3Fluid\Fluid\Core\ViewHelper\Exception
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('pageUid', 'int', 'page Uid', false);
    }

    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): void {
      $viewHelperVariableContainer = $renderingContext->getViewHelperVariableContainer();
      self::ensureProperNesting($viewHelperVariableContainer); // not really needed, nonody else are needed this one
      $moduleTemplate = $viewHelperVariableContainer->get(ModuleLayoutViewHelper::class, ModuleTemplate::class);

      if($arguments['pageUid']) {
	$moduleTemplate->getDocHeaderComponent()->setMetaInformation(\TYPO3\CMS\Backend\Utility\BackendUtility::readPageAccess($arguments['pageUid'], ''));
      }
    }

    /**
     * @param ViewHelperVariableContainer $viewHelperVariableContainer
     * @throws Exception
     */
    private static function ensureProperNesting(ViewHelperVariableContainer $viewHelperVariableContainer): void
    {
        if (!$viewHelperVariableContainer->exists(ModuleLayoutViewHelper::class, ModuleTemplate::class)) {
            throw new Exception(sprintf('%s must be nested in <f.be.moduleLayout> view helper', self::class), 1531216505);
        }
    }
  
}

