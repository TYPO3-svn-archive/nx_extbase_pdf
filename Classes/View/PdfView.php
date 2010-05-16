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
	protected $pdflatexCommandOptions = '-file-line-error -halt-on-error ';
	
	/**
	 * Path to search input files in LaTeX. Several pathes are separated by :
	 * @var string
	 */
	protected $texInputPath = '.:';
	
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
	 * Set the TEXINPUTS environment variable. Adds a trailing colon of missing.
	 *
	 * @param string $texInputPath TEXINPUTS path specification
	 * @return void
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 * @api
	 */
	public function setTexInputPath($texInputPath) {
		$individualPaths = explode(':', $texInputPath);
		array_walk($individualPaths, array('t3lib_div', 'getFileAbsFileName'));
		$this->texInputPath = implode(':', $individualPaths) . ':';
	}
	
	/**
	 * Get the value of the TEXINPUTS environment variable.
	 *
	 * @return string TEXINPUTS environment variable
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 * @api
	 */
	public function getTexInputPath() {
		return $this->texInputPath;
	}
	
	/**
	 * Initialize view
	 *
	 * @return void
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
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
		if (isset($extbaseFrameworkConfiguration['view']['texInputPath']) && strlen($extbaseFrameworkConfiguration['view']['texInputPath']) > 0) {
			$this->setTexInputPath('.:' . t3lib_div::getFileAbsFileName($extbaseFrameworkConfiguration['view']['texInputPath']) . ':');
		}
	}
	
	/**
	 * Build parser configuration
	 *
	 * @return Tx_Fluid_Core_Parser_Configuration
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 * @api
	 */
	protected function buildParserConfiguration() {
		$parserConfiguration = parent::buildParserConfiguration();
		
		$parserConfiguration->setShortHandOpenSymbol('\\\\fluid{');
		
		$interceptor = t3lib_div::makeInstance('Tx_NxExtbasePdf_Interceptor_Escape');
		$interceptor->injectObjectManager($this->objectManager);
		$parserConfiguration->addInterceptor($interceptor);
		return $parserConfiguration;
	}
	
	/**
	 * Find the LaTeX template according to $this->templatePathAndFilenamePattern and render the template.
	 * If "layoutName" is set in a PostParseFacet callback, it will render the file with the given layout.
	 *
	 * @param string $actionName If set, the view of the specified action will be rendered instead. Default is the action specified in the Request object
	 * @return string Rendered PDF file
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 * @api
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
	 * @api
	 */
	public function renderToFile($actionName = NULL) {
		return $this->buildPdf(parent::render($actionName));
	}
	
	/**
	 * Run the parsed template through latex compiler and read the resulting pdf
	 *
	 * @param string $parsedTemplate The parsed LaTeX template ready for compilation
	 * @return string Generated PDF
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function getPdf($parsedTemplate) {
		return t3lib_div::getURL($this->buildPdf($parsedTemplate));
	}
	
	/**
	 * Build a PDF file from the parsed template
	 *
	 * @param string $parsedTemplate
	 * @return string Path to generated PDF file
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
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
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function buildTemplateHash($template) {
		return md5($template);
	}

	/**
	 * Get the temporary directory used as working directory for LaTeX
	 *
	 * @return string Absolute path to temporary directory
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function getTemporaryDirectory() {
		return PATH_site . 'typo3temp/tx_nxextbasepdf/';
	}

	/**
	 * Run the LaTeX compiler on LaTeX code to generate a PDF file
	 *
	 * @throws Tx_NxExtbasePdf_Exception_PdfGenerationFailure if the PDF could not be created for some reason
	 * @param string $file Name of PDF file to generate
	 * @param string $template LaTeX code to generate PDF from
	 * @return void
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function runLatex($file, $template) {
		$texFile = substr($file, 0, -3) . 'tex';
		$this->writeTexFile($texFile, $template);
		
		try {
			do {
				$this->runCommand($this->getLatexCommand($texFile));
			} while($this->checkLogForRerun($texFile));
			
		} catch (Tx_NxExtbasePdf_Exception_CommandExecutionFailure $e) {
			// TODO somehow extract the actual error message
			throw new Tx_NxExtbasePdf_Exception_PdfGenerationFailure('Failed to generate PDF. Probaply there is a syntax error in your template file: ' . $this->extractFirstErrorFromLogFile($texFile), 1272488425);
		} catch (Tx_NxExtbasePdf_Exception_IOError $e) {
			throw new Tx_NxExtbasePdf_Exception_PdfGenerationFailure('Failed to find log file. Probably there is a problem with write permissions in temporary directory', 1272920122);
		} catch (Tx_NxExtbasePdf_Exception_CommandNotFound $e) {
			throw new Tx_NxExtbasePdf_Exception_PdfGenerationFailure('Problem with your LaTeX installation. Command ' . $this->pdflatexCommand . ' not found.', 1272920193);
		}
		
	}

	/**
	 * Write out the content to a tex file in typo3temp dir
	 *
	 * @param string $texFile Path to the tex file
	 * @param string $content LaTeX code to write
	 * @return void
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function writeTexFile($texFile, $content) {
		t3lib_div::writeFileToTypo3tempDir($texFile, $content);
	}

	/**
	 * Build the command that is to be run to compile the LaTeX code into a PDF file
	 *
	 * @throws Tx_NxExtbasePdf_Exception_CommandNotFound if the pdflatex command does not exist
	 * @param string $texFile tex file to run command on
	 * @return string Command string to execute
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function getLatexCommand($texFile) {
		if (!t3lib_exec::checkCommand($this->pdflatexCommand)) {
			throw new Tx_NxExtbasePdf_Exception_CommandNotFound('The command "' . $this->pdflatexCommand . '" was not found', 1272487533);
		}
		
		return 'TEXINPUTS=' . escapeshellarg($this->texInputPath) . ' '
			. t3lib_exec::getCommand($this->pdflatexCommand)
			. ' -output-directory ' . $this->getTemporaryDirectory()
			. ($this->pdflatexCommandOptions !== '' ? ' ' . $this->pdflatexCommandOptions : '')
			. ' ' . $texFile;
	}

	/**
	 * Execute a given command
	 *
	 * @throws Tx_NxExtbasePdf_Exception_CommandExecutionFailure if the execution failed
	 * @param string $command Command to execute
	 * @return array Lines of output of command
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function runCommand($command) {
		$exitCode = 0;
		$output = array();
		exec($command, $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new Tx_NxExtbasePdf_Exception_CommandExecutionFailure('Failure during execution of command "' . $command . '", exit code ' . $exitCode, 1272488086);
		}

		return $output;
	}

	/**
	 * Check wether LaTeX has to be run again to adapt to changed labels
	 *
	 * @param string $texFile tex file to check
	 * @return boolean Whether pdflatex has to be run again
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function checkLogForRerun($texFile) {
		$logFile = substr($texFile, 0, -3) . 'log';

		// Check log for a string like
		//
		// LaTeX Warning: Label(s) may have changed. Rerun to get cross-references right.
		//
		// to see if another run of LaTeX is needed

		return $this->scanLogForPattern($logFile, '/LaTeX Warning: Label\(s\) may have changed\. Rerun to get cross-references right\./') !== '';
		//                                        '/LaTeX Warning: Label\(s\) may have changed\. Rerun to get cross-references right\./'
	}

	/**
	 * Scan the logfile for a regular expression
	 *
	 * @throws Tx_NxExtbasePdf_Exception_IOError if logfile was not accessible
	 * @param string $logFile Logfile to scan
	 * @param string $pattern Pattern to search
	 * @return string The found line or empty string if not found
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function scanLogForPattern($logFile, $pattern) {
		$log = file_get_contents($logFile);
		if ($log === FALSE) {
			throw new Tx_NxExtbasePdf_Exception_IOError('Could not open log file "' . $logFile . '"', 1272919118);
		}

		$numberOfMatches = preg_match($pattern, $log, $matches);
		$match = '';
		if ($numberOfMatches > 0) {
			$match = $matches[0];
		}
		return $match;
	}

	/**
	 * Search the log file for the first error message
	 *
	 * @param string $texFile Tex file to search log for
	 * @return string The error message or empty string if not found
	 * @author Lienhart Woitok <lienhart.woitok@netlogix.de>
	 */
	protected function extractFirstErrorFromLogFile($texFile) {
		$logFile = substr($texFile, 0, -3) . 'log';
		return str_replace("\n", '', $this->scanLogForPattern($logFile, '~^[^[:space:]:]+\.(?:tex|sty):\d+: .*\.$~msU'));
	}
}

?>