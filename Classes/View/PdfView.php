<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Lienhart Woitok <lienhart.woitok@netlogix.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * A view that renders as pdf via LaTeX
 *
 * @version $Id$
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class Tx_NxExtbasePdf_View_PdfView extends Tx_Fluid_View_TemplateView {
	
	/**
	 * Name of pdflatex command
	 * @var string
	 */
	protected $pdflatexCommand = 'pdflatex';
	
	/**
	 * Options to pass to pdflatex command
	 * @var string
	 */
	protected $pdflatexCommandOptions = '';
	
	/**
	 * File pattern for resolving the template file
	 * @var string
	 */
	protected $templatePathAndFilenamePattern = '@templateRoot/@controller/@action.tex';

	/**
	 * Directory pattern for global partials. Not part of the public API, should not be changed for now.
	 * @var string
	 */
	private $partialPathAndFilenamePattern = '@partialRoot/@partial.tex';

	/**
	 * File pattern for resolving the layout
	 * @var string
	 */
	protected $layoutPathAndFilenamePattern = '@layoutRoot/@layout.tex';
	
	/**
	 * Initialize view
	 *
	 * @return void
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 */
	public function initializeView() {
		parent::initializeView();
		// Template Path Override
		$extbaseFrameworkConfiguration = Tx_Extbase_Dispatcher::getExtbaseFrameworkConfiguration();
		if (isset($extbaseFrameworkConfiguration['view']['templateRootPath']) && strlen($extbaseFrameworkConfiguration['view']['templateRootPath']) > 0) {
			$this->setTemplateRootPath(t3lib_div::getFileAbsFileName($extbaseFrameworkConfiguration['view']['templateRootPath']));
		}
		if (isset($extbaseFrameworkConfiguration['view']['layoutRootPath']) && strlen($extbaseFrameworkConfiguration['view']['layoutRootPath']) > 0) {
			$this->setLayoutRootPath(t3lib_div::getFileAbsFileName($extbaseFrameworkConfiguration['view']['layoutRootPath']));
		}
		if (isset($extbaseFrameworkConfiguration['view']['partialRootPath']) && strlen($extbaseFrameworkConfiguration['view']['partialRootPath']) > 0) {
			$this->setPartialRootPath(t3lib_div::getFileAbsFileName($extbaseFrameworkConfiguration['view']['partialRootPath']));
		}
		
	}
	
	/**
	 * Build parser configuration
	 *
	 * @return Tx_Fluid_Core_Parser_Configuration
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function buildParserConfiguration() {
		$parserConfiguration = parent::buildParserConfiguration();
		
		$parserConfiguration->setShortHandOpenSymbol('\\\\fluid{');
		
		//$interceptor = t3lib_div::makeInstance('Tx_ExtbasePdf_Interceptor_CurlyBrackets');
		
		//$parserConfiguration->addInterceptor($interceptor);
		return $parserConfiguration;
	}
	
	/**
	 * Find the LaTeX template according to $this->templatePathAndFilenamePattern and render the template.
	 * If "layoutName" is set in a PostParseFacet callback, it will render the file with the given layout.
	 *
	 * @param string $actionName If set, the view of the specified action will be rendered instead. Default is the action specified in the Request object
	 * @return string Rendered PDF file
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	public function render($actionName = NULL) {
		$content = $this->getPdf(parent::render($actionName));
//		$this->controllerContext->getResponse()->setHeader('Content-Type', 'application/pdf');
//		$this->controllerContext->getResponse()->setContent($content);
//		$this->controllerContext->getResponse()->send();
//		var_dump($this->controllerContext->getResponse()->getHeaders());
		return $content;
	}
	
	/**
	 * Find the LaTeX template according to $this->templatePathAndFilenamePattern and render the template.
	 * If "layoutName" is set in a PostParseFacet callback, it will render the file with the given layout.
	 *
	 * @param string $actionName If set, the view of the specified action will be rendered instead. Default is the action specified in the Request object
	 * @return string Path to rendered PDF file
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	public function renderToFile($actionName = NULL) {
		return $this->buildPdf(parent::render($actionName));
	}
	
	/**
	 * Run the parsed template through latex compiler and read the resulting pdf
	 *
	 * @param string $parsedTemplate The parsed LaTeX template ready for compilation
	 * @return string Generated PDF
	 */
	protected function getPdf($parsedTemplate) {
		return t3lib_div::getURL($this->buildPdf($parsedTemplate));
	}
	
	/**
	 * Build a PDF file from the parsed template
	 *
	 * @param string $parsedTemplate
	 * @return string Path to generated PDF file
	 */
	protected function buildPdf($parsedTemplate) {
		$templateHash = $this->buildTemplateHash($parsedTemplate);
		
		$pdfPath = $this->getTemporaryDirectory() . $templateHash . '.pdf';
		
		if (!file_exists($pdfPath)) {
			$this->runLatex($pdfPath, $parsedTemplate);
		}
		
		return $pdfPath;
	}
	
	/**
	 * Generate a unique hash for the template
	 *
	 * @param string $template The template to generate hash for
	 * @return string Hash of the template
	 */
	protected function buildTemplateHash($template) {
		return md5($template);
	}
	
	protected function getTemporaryDirectory() {
		return PATH_site . 'typo3temp/tx_nxextbasepdf/';
	}
	
	protected function runLatex($file, $template) {
		$texFile = substr($file, 0, -3) . 'tex';
		$this->writeTexFile($texFile, $template);
		
		try {
			$this->runCommand($this->getLatexCommand($texFile));
			
			
		} catch (Tx_NxExtbasePdf_Exception_CommandExecutionFailure $e) {
			// TODO somehow extract the actual error message
			throw new Tx_NxExtbasePdf_Exception_PdfGenerationFailure('Failed to generate PDF. Probaply there is a syntax error in your template file', 1272488425);
		}
		
	}
	
	protected function writeTexFile($texFile, $content) {
		t3lib_div::writeFileToTypo3tempDir($texFile, $content);
	}
	
	protected function getLatexCommand($texFile) {
		if (!t3lib_exec::checkCommand($this->pdflatexCommand)) {
			throw new Tx_NxExtbasePdf_Exception_CommandNotFound('The command "' . $this->pdflatexCommand . '" was not found', 1272487533);
		}
		
		return t3lib_exec::getCommand($this->pdflatexCommand)
			. ' -output-directory ' . $this->getTemporaryDirectory()
			. ($this->pdflatexCommandOptions !== '' ? ' ' . $this->pdflatexCommandOptions : '')
			. ' ' . $texFile;
	}
	
	protected function runCommand($command) {
		$exitCode = 0;
		exec($command, $ignoredOutput, $exitCode);
		
		if ($exitCode !== 0) {
			throw new Tx_NxExtbasePdf_Exception_CommandExecutionFailure('Failure during execution of command "' . $command . '", exit code ' . $exitCode, 1272488086);
		}
	}
}

?>